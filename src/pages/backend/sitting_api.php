<?php
/**
 * sitting_api.php
 * Handles individual sitting records for tbl_abhyasis_sitting.
 *
 *   GET  sitting_api.php?center_id=1              → map of abhyasi_id → [{date, preceptor_name}]
 *   POST sitting_api.php?action=save              → bulk save (JSON body {records:[...]})
 *   GET  sitting_api.php?action=chart             → monthly counts this/last year
 *   GET  sitting_api.php?action=preceptors        → per-preceptor stats (current year)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method    = $_SERVER['REQUEST_METHOD'];
$action    = $_GET['action']    ?? '';
$center_id = $_GET['center_id'] ?? null;

// ── Ensure table ──────────────────────────────────────────────────────────
function ensure_table(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_abhyasis_sitting (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            abhyasi_id          INT NOT NULL,
            sitting_attend_date DATE NOT NULL,
            preceptor_name      VARCHAR(150) DEFAULT NULL,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_abhyasi_date (abhyasi_id, sitting_attend_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── GET sitting list for center ───────────────────────────────────────────
if ($method === 'GET' && $action === '') {
    if (!$center_id) send_json(['error' => 'center_id is required'], 400);

    $conn = get_connection();
    ensure_table($conn);
    $cid  = (int)$center_id;

    $stmt = $conn->prepare("
        SELECT s.abhyasi_id,
               DATE_FORMAT(s.sitting_attend_date, '%Y-%m-%d') AS sitting_attend_date,
               COALESCE(s.preceptor_name, '')                  AS preceptor_name
        FROM tbl_abhyasis_sitting s
        JOIN tbl_abhyasis a ON a.abhyasi_id = s.abhyasi_id
        WHERE a.center_id = ?
        ORDER BY s.sitting_attend_date DESC
    ");
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();

    // Build map: abhyasi_id → [{date, preceptor_name}, ...]
    $result = [];
    foreach ($rows as $row) {
        $aid = $row['abhyasi_id'];
        if (!isset($result[$aid])) $result[$aid] = [];
        $result[$aid][] = [
            'date'           => $row['sitting_attend_date'],
            'preceptor_name' => $row['preceptor_name'],
        ];
    }
    send_json($result);
}

// ── POST bulk save sittings ───────────────────────────────────────────────
if ($method === 'POST' && $action === 'save') {
    $data    = get_json_body();
    $records = $data['records'] ?? [];
    if (empty($records)) send_json(['error' => 'No records provided'], 400);

    $conn = get_connection();
    ensure_table($conn);

    $saved      = 0;
    $duplicates = [];

    $chk  = $conn->prepare("SELECT id FROM tbl_abhyasis_sitting WHERE abhyasi_id = ? AND sitting_attend_date = ?");
    $ins  = $conn->prepare("INSERT INTO tbl_abhyasis_sitting (abhyasi_id, sitting_attend_date, preceptor_name) VALUES (?, ?, ?)");
    $name = $conn->prepare("SELECT CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) AS name FROM tbl_abhyasis WHERE abhyasi_id = ?");

    foreach ($records as $rec) {
        $abhyasi_id     = (int)$rec['abhyasi_id'];
        $sitting_date   = $rec['sitting_attend_date'];
        $preceptor_name = trim($rec['preceptor_name'] ?? '') ?: null;

        $chk->bind_param('is', $abhyasi_id, $sitting_date);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();

        if ($existing) {
            $name->bind_param('i', $abhyasi_id);
            $name->execute();
            $nr    = $name->get_result()->fetch_assoc();
            $label = trim($nr['name'] ?? (string)$abhyasi_id) . " ($sitting_date)";
            $duplicates[] = $label;
        } else {
            $ins->bind_param('iss', $abhyasi_id, $sitting_date, $preceptor_name);
            $ins->execute();
            $saved++;
        }
    }
    $chk->close(); $ins->close(); $name->close(); $conn->close();
    send_json(['success' => true, 'saved' => $saved, 'duplicates' => $duplicates]);
}

// ── GET chart: monthly sitting counts this year vs last year ──────────────
if ($method === 'GET' && $action === 'chart') {
    $today     = new DateTime();
    $this_year = (int)$today->format('Y');
    $last_year = $this_year - 1;

    $labels = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];

    $conn = get_connection();
    ensure_table($conn);

    $year_data = function(int $year) use ($conn, $center_id): array {
        $y_start = "$year-01-01";
        $y_end   = "$year-12-31";
        if ($center_id) {
            $stmt = $conn->prepare("
                SELECT MONTH(s.sitting_attend_date) AS mon, COUNT(*) AS cnt
                FROM tbl_abhyasis_sitting s
                INNER JOIN tbl_abhyasis a ON a.abhyasi_id = s.abhyasi_id
                WHERE s.sitting_attend_date BETWEEN ? AND ? AND a.center_id = ?
                GROUP BY MONTH(s.sitting_attend_date)
            ");
            $cid = (int)$center_id;
            $stmt->bind_param('ssi', $y_start, $y_end, $cid);
        } else {
            $stmt = $conn->prepare("
                SELECT MONTH(sitting_attend_date) AS mon, COUNT(*) AS cnt
                FROM tbl_abhyasis_sitting
                WHERE sitting_attend_date BETWEEN ? AND ?
                GROUP BY MONTH(sitting_attend_date)
            ");
            $stmt->bind_param('ss', $y_start, $y_end);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $map = [];
        foreach ($rows as $r) $map[(int)$r['mon']] = (int)$r['cnt'];
        return array_map(fn($m) => $map[$m] ?? 0, range(1, 12));
    };

    $this_data = $year_data($this_year);
    $last_data = $year_data($last_year);
    $conn->close();

    $this_total = array_sum($this_data);
    $last_total = array_sum($last_data);
    $growth     = ($last_total > 0) ? round(($this_total - $last_total) / $last_total * 100, 1) : 0;

    send_json([
        'labels'    => $labels,
        'this_year' => ['label' => (string)$this_year, 'data' => $this_data, 'total' => $this_total],
        'last_year' => ['label' => (string)$last_year, 'data' => $last_data, 'total' => $last_total],
        'growth'    => $growth,
    ]);
}

// ── GET preceptors summary for current year ───────────────────────────────
if ($method === 'GET' && $action === 'preceptors') {
    $today   = new DateTime();
    $yr      = (int)$today->format('Y');
    $y_start = "$yr-01-01";
    $y_end   = "$yr-12-31";

    $conn = get_connection();
    ensure_table($conn);

    $result = $conn->query("
        SELECT
            COALESCE(preceptor_name, 'Unknown') AS preceptor_name,
            COUNT(*)                             AS total_sittings,
            COUNT(DISTINCT abhyasi_id)           AS unique_abhyasis,
            MAX(sitting_attend_date)             AS last_sitting
        FROM tbl_abhyasis_sitting
        WHERE sitting_attend_date BETWEEN '$y_start' AND '$y_end'
        GROUP BY COALESCE(preceptor_name, 'Unknown')
        ORDER BY total_sittings DESC
        LIMIT 10
    ");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        $conn->close();
        send_json(['year' => $yr, 'preceptors' => [], 'total' => 0]);
    }

    $max_sittings = max(array_column($rows, 'total_sittings'));
    $total_all    = array_sum(array_column($rows, 'total_sittings'));

    $preceptors = [];
    foreach ($rows as $r) {
        $ts  = (int)$r['total_sittings'];
        $ua  = (int)$r['unique_abhyasis'];
        $pct = ($max_sittings > 0) ? round($ts / $max_sittings * 100) : 0;
        $share = ($total_all > 0) ? round($ts / $total_all * 100) : 0;
        $d   = new DateTime($r['last_sitting']);
        $preceptors[] = [
            'preceptor_name'  => $r['preceptor_name'],
            'total_sittings'  => $ts,
            'unique_abhyasis' => $ua,
            'last_sitting'    => $d->format('d M Y'),
            'pct'             => $pct,
            'share'           => $share,
        ];
    }
    $conn->close();
    send_json(['year' => $yr, 'preceptors' => $preceptors, 'total' => $total_all]);
}

send_json(['error' => 'Method not allowed or unknown action'], 405);
