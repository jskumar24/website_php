<?php
/**
 * attendance_api.php
 * Handles attendance operations.
 *
 *   GET  attendance_api.php?center_id=1&date=2024-01-07              → list for center+date
 *   POST attendance_api.php?action=save                              → bulk save attendance
 *   GET  attendance_api.php?action=chart&center_id=1&month=2024-01  → chart data
 *   GET  attendance_api.php?action=months&center_id=1               → distinct months
 *   GET  attendance_api.php?action=summary&center_id=1              → summary stats
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Ensure attendance table exists ─────────────────────────────────────────
function ensure_table(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_abhyasis_attedance (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            abhyasi_id          INT NOT NULL,
            satsang_attend_date DATE NOT NULL,
            is_attend           TINYINT(1) NOT NULL DEFAULT 0,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_abhyasi_date (abhyasi_id, satsang_attend_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── GET attendance list ───────────────────────────────────────────────────
if ($method === 'GET' && $action === '') {
    $center_id   = $_GET['center_id'] ?? '';
    $attend_date = $_GET['date'] ?? '';
    $is_regular  = strtolower($_GET['is_regular'] ?? '');

    if (!$center_id || !$attend_date)
        send_json(['error' => 'center_id and date are required'], 400);

    $conn = get_connection();
    ensure_table($conn);

    $where_extra = '';
    $params      = [$attend_date, (int)$center_id];
    $types       = 'si';

    if (in_array($is_regular, ['yes', 'no'])) {
        $where_extra  = 'AND LOWER(a.is_regular_practicing) = ?';
        $params[]     = $is_regular;
        $types       .= 's';
    }

    $stmt = $conn->prepare("
        SELECT
            a.abhyasi_id,
            CONCAT(a.first_name, ' ', a.last_name) AS abhyasi_name,
            a.srcm_id,
            a.mobile_no,
            a.is_regular_practicing,
            c.center_name,
            s.subcenter_name,
            att.satsang_attend_date,
            att.is_attend,
            att.id AS attendance_id
        FROM tbl_abhyasis a
        LEFT JOIN tbl_centers    c   ON c.center_id    = a.center_id
        LEFT JOIN tbl_subcenters s   ON s.subcenter_id = a.subcenter_id
        LEFT JOIN tbl_abhyasis_attedance att
            ON att.abhyasi_id = a.abhyasi_id AND att.satsang_attend_date = ?
        WHERE a.center_id = ?
        $where_extra
        ORDER BY a.first_name, a.last_name
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();

    // Convert date objects to string
    foreach ($rows as &$row) {
        if (!empty($row['satsang_attend_date']))
            $row['satsang_attend_date'] = (string)$row['satsang_attend_date'];
    }
    send_json($rows);
}

// ── POST bulk save attendance ─────────────────────────────────────────────
if ($method === 'POST' && $action === 'save') {
    $data    = get_json_body();
    $records = $data['records'] ?? [];
    if (empty($records)) send_json(['error' => 'No records provided'], 400);

    $conn = get_connection();
    ensure_table($conn);

    $saved      = 0;
    $duplicates = [];

    // Check existing
    $chk  = $conn->prepare("SELECT id FROM tbl_abhyasis_attedance WHERE abhyasi_id = ? AND satsang_attend_date = ?");
    $ins  = $conn->prepare("INSERT INTO tbl_abhyasis_attedance (abhyasi_id, satsang_attend_date, is_attend) VALUES (?,?,?)");
    $name = $conn->prepare("SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS name FROM tbl_abhyasis WHERE abhyasi_id = ?");

    foreach ($records as $rec) {
        $abhyasi_id         = (int)$rec['abhyasi_id'];
        $satsang_attend_date = $rec['satsang_attend_date'];
        $is_attend          = (strtoupper($rec['is_attend'] ?? '') === 'P') ? 1 : 0;

        $chk->bind_param('is', $abhyasi_id, $satsang_attend_date);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();

        if ($existing) {
            $name->bind_param('i', $abhyasi_id);
            $name->execute();
            $nr  = $name->get_result()->fetch_assoc();
            $duplicates[] = trim($nr['name'] ?? (string)$abhyasi_id);
        } else {
            $ins->bind_param('isi', $abhyasi_id, $satsang_attend_date, $is_attend);
            $ins->execute();
            $saved++;
        }
    }
    $chk->close(); $ins->close(); $name->close(); $conn->close();
    send_json(['success' => true, 'saved' => $saved, 'duplicates' => $duplicates]);
}

// ── GET chart data ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'chart') {
    $center_id   = $_GET['center_id'] ?? null;
    $month_str   = $_GET['month']    ?? null;
    $compare_str = $_GET['compare']  ?? null;

    $today = new DateTime();

    $parse_ym = function($s) use ($today): array {
        $parts = explode('-', $s);
        if (count($parts) === 2) return [(int)$parts[0], (int)$parts[1]];
        return [(int)$today->format('Y'), (int)$today->format('m')];
    };

    [$cur_year, $cur_month]  = $month_str  ? $parse_ym($month_str)  : [(int)$today->format('Y'), (int)$today->format('m')];
    if ($compare_str) {
        [$prev_year, $prev_month] = $parse_ym($compare_str);
    } else {
        if ($cur_month === 1) { $prev_year = $cur_year - 1; $prev_month = 12; }
        else { $prev_year = $cur_year; $prev_month = $cur_month - 1; }
    }

    $conn = get_connection();
    ensure_table($conn);

    $get_month_data = function(int $year, int $month) use ($conn, $center_id): array {
        $last_day = (int)(new DateTime("$year-$month-01"))->format('t');
        $ym_start = sprintf('%d-%02d-01', $year, $month);
        $ym_end   = sprintf('%d-%02d-%02d', $year, $month, $last_day);

        if ($center_id) {
            $stmt = $conn->prepare("
                SELECT att.satsang_attend_date AS dt,
                  SUM(CASE WHEN att.is_attend=1 THEN 1 ELSE 0 END) AS present_count,
                  SUM(CASE WHEN att.is_attend=0 THEN 1 ELSE 0 END) AS absent_count,
                  COUNT(*) AS total
                FROM tbl_abhyasis_attedance att
                INNER JOIN tbl_abhyasis a ON a.abhyasi_id=att.abhyasi_id
                WHERE att.satsang_attend_date BETWEEN ? AND ? AND a.center_id=?
                GROUP BY att.satsang_attend_date ORDER BY att.satsang_attend_date
            ");
            $cid = (int)$center_id;
            $stmt->bind_param('ssi', $ym_start, $ym_end, $cid);
        } else {
            $stmt = $conn->prepare("
                SELECT satsang_attend_date AS dt,
                  SUM(CASE WHEN is_attend=1 THEN 1 ELSE 0 END) AS present_count,
                  SUM(CASE WHEN is_attend=0 THEN 1 ELSE 0 END) AS absent_count,
                  COUNT(*) AS total
                FROM tbl_abhyasis_attedance
                WHERE satsang_attend_date BETWEEN ? AND ?
                GROUP BY satsang_attend_date ORDER BY satsang_attend_date
            ");
            $stmt->bind_param('ss', $ym_start, $ym_end);
        }
        $stmt->execute();
        $rows   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $result = [];
        foreach ($rows as $row) {
            $d_obj    = new DateTime($row['dt']);
            $result[] = [
                'date'    => $d_obj->format('Y-m-d'),
                'label'   => $d_obj->format('j M'),
                'present' => (int)($row['present_count'] ?? 0),
                'absent'  => (int)($row['absent_count']  ?? 0),
                'total'   => (int)($row['total']         ?? 0),
            ];
        }
        return $result;
    };

    $this_data = $get_month_data($cur_year,  $cur_month);
    $last_data = $get_month_data($prev_year, $prev_month);
    $conn->close();

    send_json([
        'this_month' => [
            'label'      => (new DateTime("$cur_year-$cur_month-01"))->format('F Y'),
            'year_month' => sprintf('%d-%02d', $cur_year, $cur_month),
            'sundays'    => $this_data,
        ],
        'last_month' => [
            'label'      => (new DateTime("$prev_year-$prev_month-01"))->format('F Y'),
            'year_month' => sprintf('%d-%02d', $prev_year, $prev_month),
            'sundays'    => $last_data,
        ],
    ]);
}

// ── GET distinct months ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'months') {
    $center_id = $_GET['center_id'] ?? null;
    $conn = get_connection();
    ensure_table($conn);
    if ($center_id) {
        $stmt = $conn->prepare("
            SELECT DISTINCT DATE_FORMAT(att.satsang_attend_date,'%Y-%m') AS ym,
                   DATE_FORMAT(att.satsang_attend_date,'%M %Y') AS label
            FROM tbl_abhyasis_attedance att
            INNER JOIN tbl_abhyasis a ON a.abhyasi_id=att.abhyasi_id
            WHERE a.center_id=? ORDER BY ym DESC
        ");
        $cid = (int)$center_id;
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query("
            SELECT DISTINCT DATE_FORMAT(satsang_attend_date,'%Y-%m') AS ym,
                   DATE_FORMAT(satsang_attend_date,'%M %Y') AS label
            FROM tbl_abhyasis_attedance ORDER BY ym DESC
        ");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($rows);
}

// ── GET summary stats (fields match index.html expectations) ──────────────
if ($method === 'GET' && $action === 'summary') {
    $center_id = $_GET['center_id'] ?? null;
    $today     = new DateTime();
    $yr        = (int)$today->format('Y');
    $mo        = (int)$today->format('m');
    $y_start   = "$yr-01-01";
    $y_end     = "$yr-12-31";
    $m_start   = sprintf('%d-%02d-01', $yr, $mo);
    $m_end     = sprintf('%d-%02d-%02d', $yr, $mo, (int)$today->format('t'));
    $month_label = $today->format('F Y');   // e.g. "March 2026"

    $conn = get_connection();
    ensure_table($conn);

    // ── Helper: run a query and return one row ────────────────────────────
    $q = function(string $sql, array $params = [], string $types = '') use ($conn) {
        if (empty($params)) {
            $r = $conn->query($sql);
            return $r ? $r->fetch_assoc() : [];
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?? [];
    };

    if ($center_id) {
        $cid    = (int)$center_id;
        $yr_row = $q("SELECT SUM(CASE WHEN att.is_attend=1 THEN 1 ELSE 0 END) AS present,
                             COUNT(DISTINCT att.abhyasi_id) AS total_abhyasis
                      FROM tbl_abhyasis_attedance att
                      INNER JOIN tbl_abhyasis a ON a.abhyasi_id = att.abhyasi_id
                      WHERE att.satsang_attend_date BETWEEN ? AND ? AND a.center_id = ?",
                     [$y_start, $y_end, $cid], 'ssi');
        $mo_row = $q("SELECT SUM(CASE WHEN att.is_attend=1 THEN 1 ELSE 0 END) AS present,
                             COUNT(DISTINCT att.abhyasi_id) AS total_abhyasis
                      FROM tbl_abhyasis_attedance att
                      INNER JOIN tbl_abhyasis a ON a.abhyasi_id = att.abhyasi_id
                      WHERE att.satsang_attend_date BETWEEN ? AND ? AND a.center_id = ?",
                     [$m_start, $m_end, $cid], 'ssi');

        // Sparkline: last 6 satsang dates for this center
        $stmt = $conn->prepare(
            "SELECT att.satsang_attend_date AS dt,
                    SUM(CASE WHEN att.is_attend=1 THEN 1 ELSE 0 END) AS present
             FROM tbl_abhyasis_attedance att
             INNER JOIN tbl_abhyasis a ON a.abhyasi_id = att.abhyasi_id
             WHERE att.satsang_attend_date BETWEEN ? AND ? AND a.center_id = ?
             GROUP BY att.satsang_attend_date ORDER BY att.satsang_attend_date DESC LIMIT 6"
        );
        $stmt->bind_param('ssi', $y_start, $y_end, $cid);

        // Max abhyasis in center for pct calculation
        $tot_row = $q("SELECT COUNT(*) AS cnt FROM tbl_abhyasis WHERE center_id = ?", [$cid], 'i');
        $total_abhyasis = (int)($tot_row['cnt'] ?? 1) ?: 1;
    } else {
        $yr_row = $q("SELECT SUM(CASE WHEN is_attend=1 THEN 1 ELSE 0 END) AS present,
                             COUNT(DISTINCT abhyasi_id) AS total_abhyasis
                      FROM tbl_abhyasis_attedance
                      WHERE satsang_attend_date BETWEEN '$y_start' AND '$y_end'");
        $mo_row = $q("SELECT SUM(CASE WHEN is_attend=1 THEN 1 ELSE 0 END) AS present,
                             COUNT(DISTINCT abhyasi_id) AS total_abhyasis
                      FROM tbl_abhyasis_attedance
                      WHERE satsang_attend_date BETWEEN '$m_start' AND '$m_end'");

        // Sparkline: last 6 satsang dates across all centers
        $stmt = $conn->prepare(
            "SELECT satsang_attend_date AS dt,
                    SUM(CASE WHEN is_attend=1 THEN 1 ELSE 0 END) AS present
             FROM tbl_abhyasis_attedance
             WHERE satsang_attend_date BETWEEN ? AND ?
             GROUP BY satsang_attend_date ORDER BY satsang_attend_date DESC LIMIT 6"
        );
        $stmt->bind_param('ss', $y_start, $y_end);

        $tot_row = $q("SELECT COUNT(*) AS cnt FROM tbl_abhyasis");
        $total_abhyasis = (int)($tot_row['cnt'] ?? 1) ?: 1;
    }

    $stmt->execute();
    $spark_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    // Build sparkline array (chronological order for chart)
    $sparkline = [];
    foreach (array_reverse($spark_raw) as $r) {
        $dt = new DateTime($r['dt']);
        $sparkline[] = ['label' => $dt->format('j M'), 'present' => (int)($r['present'] ?? 0)];
    }

    $yearly_present  = (int)($yr_row['present'] ?? 0);
    $monthly_present = (int)($mo_row['present'] ?? 0);

    // Progress bar values: ratio of present vs total abhyasis (capped 0–1)
    $yearly_pct  = $total_abhyasis > 0 ? min(round($yearly_present  / $total_abhyasis, 2), 1) : 0;
    $monthly_pct = $total_abhyasis > 0 ? min(round($monthly_present / $total_abhyasis, 2), 1) : 0;

    send_json([
        'year_label'      => (string)$yr,          // "2026"
        'yearly_present'  => $yearly_present,
        'yearly_pct'      => $yearly_pct,           // 0.0 – 1.0 for ProgressBar.Circle
        'month_label'     => $month_label,           // "March 2026"
        'monthly_present' => $monthly_present,
        'monthly_pct'     => $monthly_pct,           // 0.0 – 1.0 for ProgressBar.Circle
        'sparkline'       => $sparkline,             // [{label, present}, ...]
    ]);
}

// ── GET activities summary (Zone Activities donut) ────────────────────────
if ($method === 'GET' && $action === 'activities') {
    $today   = new DateTime();
    $yr      = (int)$today->format('Y');
    $y_start = "$yr-01-01";
    $y_end   = "$yr-12-31";

    $conn = get_connection();
    ensure_table($conn);

    // Ensure sitting table
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_abhyasis_sitting (
        id INT AUTO_INCREMENT PRIMARY KEY,
        abhyasi_id INT NOT NULL,
        sitting_attend_date DATE NOT NULL,
        preceptor_name VARCHAR(150) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_abhyasi_date (abhyasi_id, sitting_attend_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure event tables
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_event (
        evt_id INT AUTO_INCREMENT PRIMARY KEY,
        event_name VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        location VARCHAR(255) DEFAULT NULL,
        district VARCHAR(150) DEFAULT NULL,
        no_of_attendancies INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_event_abhyasis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evt_id INT NOT NULL,
        abhyasi_id INT NOT NULL,
        UNIQUE KEY uq_evt_abhyasi (evt_id, abhyasi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 1. Satsang
    $r1 = $conn->query("SELECT COUNT(*) AS t, COUNT(DISTINCT satsang_attend_date) AS d
                        FROM tbl_abhyasis_attedance
                        WHERE is_attend=1 AND satsang_attend_date BETWEEN '$y_start' AND '$y_end'");
    $s1 = $r1 ? ($r1->fetch_assoc() ?? []) : [];
    $satsang       = (int)($s1['t'] ?? 0);
    $satsang_dates = (int)($s1['d'] ?? 0);
    $satsang_avg   = $satsang_dates > 0 ? (int)round($satsang / $satsang_dates) : 0;

    // 2. Sittings
    $r2 = $conn->query("SELECT COUNT(*) AS t, COUNT(DISTINCT sitting_attend_date) AS d
                        FROM tbl_abhyasis_sitting
                        WHERE sitting_attend_date BETWEEN '$y_start' AND '$y_end'");
    $s2 = $r2 ? ($r2->fetch_assoc() ?? []) : [];
    $sitting       = (int)($s2['t'] ?? 0);
    $sitting_dates = (int)($s2['d'] ?? 0);
    $sitting_avg   = $sitting_dates > 0 ? (int)round($sitting / $sitting_dates) : 0;

    // 3. Events
    $r3 = $conn->query("SELECT COUNT(ea.abhyasi_id) AS t, COUNT(DISTINCT e.evt_id) AS ec
                        FROM tbl_event e
                        LEFT JOIN tbl_event_abhyasis ea ON ea.evt_id = e.evt_id
                        WHERE e.event_date BETWEEN '$y_start' AND '$y_end'");
    $s3 = $r3 ? ($r3->fetch_assoc() ?? []) : [];
    $events      = (int)($s3['t']  ?? 0);
    $event_count = (int)($s3['ec'] ?? 0);
    $events_avg  = $event_count > 0 ? (int)round($events / $event_count) : 0;

    $conn->close();

    send_json([
        'year'          => $yr,
        'total'         => $satsang + $sitting + $events,
        'satsang'       => $satsang,
        'satsang_avg'   => $satsang_avg,
        'satsang_dates' => $satsang_dates,
        'sitting'       => $sitting,
        'sitting_avg'   => $sitting_avg,
        'sitting_dates' => $sitting_dates,
        'events'        => $events,
        'events_avg'    => $events_avg,
        'event_count'   => $event_count,
    ]);
}

send_json(['error' => 'Method not allowed or unknown action'], 405);
