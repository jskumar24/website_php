<?php
/**
 * reports_api.php
 * All reporting endpoints — routed via ?action=...
 *
 * Actions (GET unless noted):
 *   by-role              ?role=preceptor|cc|dc|nc|amc
 *   by-practicing        ?is_regular=yes|no  [&center_id=]
 *   attendance           ?attended=1|0  [&center_id=] [&date_from=] [&date_to=]
 *   missing-field        ?field=srcm_id|mobile_no  [&center_id=]
 *   sitting              [?center_id=] [&preceptor_name=] [&date_from=] [&date_to=]
 *   preceptors-list
 *   individual           ?abhyasi_id=  [&date_from=] [&date_to=]
 *   events               ?filter_by=district|daterange|abhyasi  [+related filters]
 *   districts-list
 *   all-abhyasis         [?center_id=]
 *   by-age               [?center_id=] [&age_min=] [&age_max=]
 *   by-professional      ?professional=
 *   professionals-list
 *   preceptor-sitting-count  [?center_id=] [&date_from=] [&date_to=]
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$action = trim($_GET['action'] ?? '');
if (!$action) send_json(['error' => 'action param required'], 400);

// ── Helpers ───────────────────────────────────────────────────────────────
function get_vol_pk(mysqli $conn): string {
    $res = $conn->query("SHOW KEYS FROM tbl_volunteer_work WHERE Key_name = 'PRIMARY'");
    $row = $res ? $res->fetch_assoc() : null;
    return $row['Column_name'] ?? 'id';
}

$abhyasi_cols = "
    CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
    a.srcm_id, a.mobile_no, a.email_id,
    a.gender, a.age, a.professional,
    c.center_name
";

function ensure_sitting(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_abhyasis_sitting (
            id INT AUTO_INCREMENT PRIMARY KEY,
            abhyasi_id INT NOT NULL,
            sitting_attend_date DATE NOT NULL,
            preceptor_name VARCHAR(150) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_abhyasi_date (abhyasi_id, sitting_attend_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensure_event_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_event (
            evt_id INT AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            location   VARCHAR(255) DEFAULT NULL,
            district   VARCHAR(150) DEFAULT NULL,
            no_of_attendancies INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_event_abhyasis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            evt_id INT NOT NULL,
            abhyasi_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_evt_abhyasi (evt_id, abhyasi_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── by-role ───────────────────────────────────────────────────────────────
if ($action === 'by-role') {
    $role = trim($_GET['role'] ?? '');
    if (!$role) send_json(['error' => 'role param required'], 400);
    $conn   = get_connection();
    $vol_pk = get_vol_pk($conn);
    global $abhyasi_cols;
    $like = '%' . strtolower($role) . '%';
    $stmt = $conn->prepare("
        SELECT DISTINCT $abhyasi_cols
        FROM tbl_volunteer_work_abhyasi va
        JOIN tbl_abhyasis       a  ON a.abhyasi_id = va.abhyasi_id
        JOIN tbl_volunteer_work vw ON vw.$vol_pk   = va.vol_id
        LEFT JOIN tbl_centers   c  ON c.center_id  = a.center_id
        WHERE LOWER(vw.volunteer_name) LIKE ?
        ORDER BY c.center_name, abhyasi_name
    ");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();
    send_json($data);
}

// ── by-practicing ─────────────────────────────────────────────────────────
if ($action === 'by-practicing') {
    $center_id  = trim($_GET['center_id']  ?? '');
    $is_regular = strtolower(trim($_GET['is_regular'] ?? ''));
    $conn = get_connection();
    global $abhyasi_cols;

    $where_parts = [];
    $params = [];
    $types  = '';
    if ($center_id !== '') {
        $where_parts[] = 'a.center_id = ?';
        $params[] = (int)$center_id;
        $types .= 'i';
    }
    if (in_array($is_regular, ['yes', 'no'])) {
        $where_parts[] = 'LOWER(a.is_regular_practicing) = ?';
        $params[] = $is_regular;
        $types .= 's';
    }
    $where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    $sql  = "SELECT $abhyasi_cols FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id $where ORDER BY c.center_name, a.first_name, a.last_name";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($data);
}

// ── attendance ────────────────────────────────────────────────────────────
if ($action === 'attendance') {
    $center_id = trim($_GET['center_id'] ?? '');
    $attended  = trim($_GET['attended']  ?? '1');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to   = trim($_GET['date_to']   ?? '');
    $conn = get_connection();

    if ($attended === '1') {
        // ATTENDED
        $att_on_parts = ['att.abhyasi_id = a.abhyasi_id', 'att.is_attend = 1'];
        $params = [];
        $types  = '';
        if ($date_from) { $att_on_parts[] = 'att.satsang_attend_date >= ?'; $params[] = $date_from; $types .= 's'; }
        if ($date_to)   { $att_on_parts[] = 'att.satsang_attend_date <= ?'; $params[] = $date_to;   $types .= 's'; }
        $att_on = implode(' AND ', $att_on_parts);

        $center_where = '';
        if ($center_id !== '') { $center_where = 'AND a.center_id = ?'; $params[] = (int)$center_id; $types .= 'i'; }

        $sql  = "
            SELECT a.abhyasi_id,
                   CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
                   a.srcm_id, a.mobile_no, a.email_id, c.center_name,
                   GROUP_CONCAT(DATE_FORMAT(att.satsang_attend_date,'%Y-%m-%d') ORDER BY att.satsang_attend_date DESC SEPARATOR ', ') AS satsang_dates
            FROM tbl_abhyasis a
            LEFT JOIN tbl_centers c ON c.center_id = a.center_id
            INNER JOIN tbl_abhyasis_attedance att ON $att_on
            WHERE 1=1 $center_where
            GROUP BY a.abhyasi_id, a.first_name, a.last_name, a.srcm_id, a.mobile_no, a.email_id, c.center_name
            ORDER BY c.center_name, a.first_name, a.last_name
        ";
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        // NOT ATTENDED
        $center_where  = '';
        $center_params = [];
        $center_types  = '';
        if ($center_id !== '') {
            $center_where  = 'AND a.center_id = ?';
            $center_params = [(int)$center_id];
            $center_types  = 'i';
        }
        $date_range_cond = '';
        $range_params    = [];
        $range_types     = '';
        if ($date_from) { $date_range_cond .= ' AND att2.satsang_attend_date >= ?'; $range_params[] = $date_from; $range_types .= 's'; }
        if ($date_to)   { $date_range_cond .= ' AND att2.satsang_attend_date <= ?'; $range_params[] = $date_to;   $range_types .= 's'; }

        $params = array_merge($center_params, $range_params);
        $types  = $center_types . $range_types;
        $sql = "
            SELECT a.abhyasi_id,
                   CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
                   a.srcm_id, a.mobile_no, a.email_id, c.center_name,
                   NULL AS satsang_dates
            FROM tbl_abhyasis a
            LEFT JOIN tbl_centers c ON c.center_id = a.center_id
            WHERE (a.is_regular_practicing IS NULL OR LOWER(TRIM(a.is_regular_practicing)) = 'no' OR TRIM(a.is_regular_practicing) = '')
            $center_where
            AND NOT EXISTS (
                SELECT 1 FROM tbl_abhyasis_attedance att2
                WHERE att2.abhyasi_id = a.abhyasi_id AND att2.is_attend = 1 $date_range_cond
            )
            ORDER BY c.center_name, a.first_name, a.last_name
        ";
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        }
    }
    $conn->close();
    send_json($data);
}

// ── missing-field ─────────────────────────────────────────────────────────
if ($action === 'missing-field') {
    $field     = trim($_GET['field']     ?? '');
    $center_id = trim($_GET['center_id'] ?? '');
    if (!in_array($field, ['srcm_id', 'mobile_no']))
        send_json(['error' => 'field must be srcm_id or mobile_no'], 400);

    $conn = get_connection();
    global $abhyasi_cols;
    $where_parts = ["(a.$field IS NULL OR TRIM(a.$field) = '')"];
    $params = [];
    $types  = '';
    if ($center_id !== '') {
        $where_parts[] = 'a.center_id = ?';
        $params[] = (int)$center_id;
        $types .= 'i';
    }
    $where = 'WHERE ' . implode(' AND ', $where_parts);
    $sql   = "SELECT $abhyasi_cols FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id $where ORDER BY c.center_name, a.first_name, a.last_name";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($data);
}

// ── missing-details ───────────────────────────────────────────────────────
// Returns abhyasis missing any of: srcm_id, mobile_no, email_id, professional, age
if ($action === 'missing-details') {
    $center_id = trim($_GET['center_id'] ?? '');
    $conn = get_connection();
    global $abhyasi_cols;
    $where_parts = ["(
        a.srcm_id     IS NULL OR TRIM(a.srcm_id)     = '' OR
        a.mobile_no   IS NULL OR TRIM(a.mobile_no)   = '' OR
        a.email_id    IS NULL OR TRIM(a.email_id)    = '' OR
        a.professional IS NULL OR TRIM(a.professional) = '' OR
        a.age         IS NULL
    )"];
    $params = [];
    $types  = '';
    if ($center_id !== '') {
        $where_parts[] = 'a.center_id = ?';
        $params[] = (int)$center_id;
        $types .= 'i';
    }
    $where = 'WHERE ' . implode(' AND ', $where_parts);
    $sql   = "SELECT $abhyasi_cols, a.is_regular_practicing FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id $where ORDER BY c.center_name, a.first_name, a.last_name";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($data);
}

// ── sitting report ────────────────────────────────────────────────────────
if ($action === 'sitting') {
    $center_id      = trim($_GET['center_id']      ?? '');
    $preceptor_name = trim($_GET['preceptor_name'] ?? '');
    $date_from      = trim($_GET['date_from']      ?? '');
    $date_to        = trim($_GET['date_to']        ?? '');

    $conn = get_connection();
    ensure_sitting($conn);

    $where_parts = [];
    $params = [];
    $types  = '';
    if ($center_id !== '')      { $where_parts[] = 'a.center_id = ?';                   $params[] = (int)$center_id;                                   $types .= 'i'; }
    if ($preceptor_name !== '') { $where_parts[] = 'LOWER(s.preceptor_name) LIKE ?';    $params[] = '%' . strtolower($preceptor_name) . '%';            $types .= 's'; }
    if ($date_from !== '')      { $where_parts[] = 's.sitting_attend_date >= ?';         $params[] = $date_from;                                         $types .= 's'; }
    if ($date_to !== '')        { $where_parts[] = 's.sitting_attend_date <= ?';         $params[] = $date_to;                                           $types .= 's'; }
    $where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    $sql = "
        SELECT
            CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
            a.srcm_id, a.mobile_no, a.email_id, a.gender, a.age, a.professional,
            c.center_name, s.preceptor_name,
            GROUP_CONCAT(DATE_FORMAT(s.sitting_attend_date,'%Y-%m-%d') ORDER BY s.sitting_attend_date DESC SEPARATOR ', ') AS sitting_dates,
            COUNT(s.id) AS sitting_count
        FROM tbl_abhyasis_sitting s
        JOIN  tbl_abhyasis  a ON a.abhyasi_id = s.abhyasi_id
        LEFT JOIN tbl_centers c ON c.center_id = a.center_id
        $where
        GROUP BY a.abhyasi_id, a.first_name, a.last_name, a.srcm_id, a.mobile_no, a.email_id,
                 a.gender, a.age, a.professional, c.center_name, s.preceptor_name
        ORDER BY c.center_name, s.preceptor_name, a.first_name, a.last_name
    ";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($data);
}

// ── preceptors-list ───────────────────────────────────────────────────────
if ($action === 'preceptors-list') {
    $conn   = get_connection();
    $vol_pk = get_vol_pk($conn);
    $stmt   = $conn->prepare("
        SELECT DISTINCT
            CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS preceptor_name
        FROM tbl_volunteer_work_abhyasi va
        JOIN tbl_abhyasis       a  ON a.abhyasi_id = va.abhyasi_id
        JOIN tbl_volunteer_work vw ON vw.$vol_pk   = va.vol_id
        WHERE LOWER(vw.volunteer_name) LIKE '%preceptor%'
        ORDER BY preceptor_name
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();
    send_json(array_values(array_filter(array_column($rows, 'preceptor_name'), fn($v) => trim($v) !== '')));
}

// ── individual report ─────────────────────────────────────────────────────
if ($action === 'individual') {
    $abhyasi_id = trim($_GET['abhyasi_id'] ?? '');
    $date_from  = trim($_GET['date_from']  ?? '');
    $date_to    = trim($_GET['date_to']    ?? '');
    if (!$abhyasi_id) send_json(['error' => 'abhyasi_id required'], 400);

    $conn = get_connection();
    $aid  = (int)$abhyasi_id;

    // Profile
    $stmt = $conn->prepare("
        SELECT a.abhyasi_id,
               CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
               a.srcm_id, a.mobile_no, a.email_id,
               a.is_regular_practicing, a.gender, a.age, a.professional,
               c.center_name, sc.subcenter_name
        FROM tbl_abhyasis a
        LEFT JOIN tbl_centers   c  ON c.center_id    = a.center_id
        LEFT JOIN tbl_subcenters sc ON sc.subcenter_id = a.subcenter_id
        WHERE a.abhyasi_id = ?
    ");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$profile) { $conn->close(); send_json(['error' => 'Abhyasi not found'], 404); }

    // Attendance
    $att_where  = 'WHERE att.abhyasi_id = ?';
    $att_params = [$aid];
    $att_types  = 'i';
    if ($date_from) { $att_where .= ' AND att.satsang_attend_date >= ?'; $att_params[] = $date_from; $att_types .= 's'; }
    if ($date_to)   { $att_where .= ' AND att.satsang_attend_date <= ?'; $att_params[] = $date_to;   $att_types .= 's'; }
    $stmt = $conn->prepare("SELECT DATE_FORMAT(att.satsang_attend_date,'%Y-%m-%d') AS date, att.is_attend FROM tbl_abhyasis_attedance att $att_where ORDER BY att.satsang_attend_date DESC");
    $stmt->bind_param($att_types, ...$att_params);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Sittings
    ensure_sitting($conn);
    $sit_where  = 'WHERE s.abhyasi_id = ?';
    $sit_params = [$aid];
    $sit_types  = 'i';
    if ($date_from) { $sit_where .= ' AND s.sitting_attend_date >= ?'; $sit_params[] = $date_from; $sit_types .= 's'; }
    if ($date_to)   { $sit_where .= ' AND s.sitting_attend_date <= ?'; $sit_params[] = $date_to;   $sit_types .= 's'; }
    $stmt = $conn->prepare("SELECT DATE_FORMAT(s.sitting_attend_date,'%Y-%m-%d') AS date, COALESCE(s.preceptor_name,'—') AS preceptor_name FROM tbl_abhyasis_sitting s $sit_where ORDER BY s.sitting_attend_date DESC");
    $stmt->bind_param($sit_types, ...$sit_params);
    $stmt->execute();
    $sittings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    $attended_cnt = count(array_filter($attendance, fn($r) => (int)$r['is_attend'] === 1));
    $absent_cnt   = count(array_filter($attendance, fn($r) => (int)$r['is_attend'] === 0));

    send_json([
        'profile'    => $profile,
        'attendance' => $attendance,
        'sittings'   => $sittings,
        'summary'    => [
            'total_att'     => count($attendance),
            'attended'      => $attended_cnt,
            'absent'        => $absent_cnt,
            'total_sitting' => count($sittings),
        ],
    ]);
}

// ── events report ─────────────────────────────────────────────────────────
if ($action === 'events') {
    $filter_by  = trim($_GET['filter_by']  ?? '');
    $district   = trim($_GET['district']   ?? '');
    $date_from  = trim($_GET['date_from']  ?? '');
    $date_to    = trim($_GET['date_to']    ?? '');
    $abhyasi_id = trim($_GET['abhyasi_id'] ?? '');

    $conn = get_connection();
    ensure_event_tables($conn);

    if ($filter_by === 'abhyasi' && $abhyasi_id !== '') {
        $aid  = (int)$abhyasi_id;
        $stmt = $conn->prepare("
            SELECT e.evt_id, e.event_name,
                   DATE_FORMAT(e.event_date,'%Y-%m-%d') AS event_date,
                   e.location, e.district, e.no_of_attendancies,
                   CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,'')) AS abhyasi_name,
                   a.srcm_id, a.mobile_no, c.center_name,
                   COUNT(ea2.abhyasi_id) AS total_registered
            FROM tbl_event_abhyasis ea
            JOIN tbl_event e    ON e.evt_id     = ea.evt_id
            JOIN tbl_abhyasis a ON a.abhyasi_id = ea.abhyasi_id
            LEFT JOIN tbl_centers c ON c.center_id = a.center_id
            LEFT JOIN tbl_event_abhyasis ea2 ON ea2.evt_id = e.evt_id
            WHERE ea.abhyasi_id = ?
            GROUP BY e.evt_id, e.event_name, e.event_date, e.location,
                     e.district, e.no_of_attendancies,
                     a.first_name, a.last_name, a.srcm_id, a.mobile_no, c.center_name
            ORDER BY e.event_date DESC
        ");
        $stmt->bind_param('i', $aid);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $where_parts = [];
        $params = [];
        $types  = '';
        if ($filter_by === 'district' && $district !== '') {
            $where_parts[] = 'LOWER(e.district) LIKE ?';
            $params[] = '%' . strtolower($district) . '%';
            $types .= 's';
        }
        if ($date_from !== '') { $where_parts[] = 'e.event_date >= ?'; $params[] = $date_from; $types .= 's'; }
        if ($date_to   !== '') { $where_parts[] = 'e.event_date <= ?'; $params[] = $date_to;   $types .= 's'; }
        $where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

        $sql = "
            SELECT e.evt_id, e.event_name,
                   DATE_FORMAT(e.event_date,'%Y-%m-%d') AS event_date,
                   e.location, e.district, e.no_of_attendancies,
                   COUNT(ea.abhyasi_id) AS total_registered,
                   GROUP_CONCAT(CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,'')) ORDER BY a.first_name SEPARATOR ', ') AS abhyasi_names
            FROM tbl_event e
            LEFT JOIN tbl_event_abhyasis ea ON ea.evt_id = e.evt_id
            LEFT JOIN tbl_abhyasis a ON a.abhyasi_id = ea.abhyasi_id
            $where
            GROUP BY e.evt_id, e.event_name, e.event_date, e.location, e.district, e.no_of_attendancies
            ORDER BY e.event_date DESC
        ";
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        }
    }
    $conn->close();
    send_json($data);
}

// ── districts-list ────────────────────────────────────────────────────────
if ($action === 'districts-list') {
    $conn = get_connection();
    ensure_event_tables($conn);
    $result = $conn->query("SELECT DISTINCT district FROM tbl_event WHERE district IS NOT NULL AND district != '' ORDER BY district");
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    send_json(array_column($rows, 'district'));
}

// ── all-abhyasis ──────────────────────────────────────────────────────────
if ($action === 'all-abhyasis') {
    $center_id = trim($_GET['center_id'] ?? '');
    $conn = get_connection();
    global $abhyasi_cols;
    if ($center_id !== '') {
        $cid  = (int)$center_id;
        $stmt = $conn->prepare("SELECT $abhyasi_cols, a.is_regular_practicing FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id WHERE a.center_id = ? ORDER BY c.center_name, a.first_name, a.last_name");
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $data = $conn->query("SELECT $abhyasi_cols, a.is_regular_practicing FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id ORDER BY c.center_name, a.first_name, a.last_name")->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($data);
}

// ── by-age ────────────────────────────────────────────────────────────────
if ($action === 'by-age') {
    $center_id = trim($_GET['center_id'] ?? '');
    $age_min   = (int)($_GET['age_min'] ?? 0);
    $age_max   = (int)($_GET['age_max'] ?? 150);
    $conn = get_connection();
    global $abhyasi_cols;
    $where_parts = ['a.age IS NOT NULL', 'a.age >= ?', 'a.age <= ?'];
    $params = [$age_min, $age_max];
    $types  = 'ii';
    if ($center_id !== '') { $where_parts[] = 'a.center_id = ?'; $params[] = (int)$center_id; $types .= 'i'; }
    $where = 'WHERE ' . implode(' AND ', $where_parts);
    $stmt  = $conn->prepare("SELECT $abhyasi_cols, a.is_regular_practicing FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id $where ORDER BY a.age ASC, c.center_name, a.first_name, a.last_name");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();
    send_json($data);
}

// ── by-professional ───────────────────────────────────────────────────────
if ($action === 'by-professional') {
    $professional = trim($_GET['professional'] ?? '');
    $center_id    = trim($_GET['center_id']    ?? '');
    if ($professional === '') send_json(['error' => 'professional param required'], 400);
    $conn = get_connection();
    global $abhyasi_cols;
    $where_parts = ["LOWER(TRIM(a.professional)) = LOWER(TRIM(?))"];
    $params = [$professional];
    $types  = 's';
    if ($center_id !== '') { $where_parts[] = 'a.center_id = ?'; $params[] = (int)$center_id; $types .= 'i'; }
    $where = 'WHERE ' . implode(' AND ', $where_parts);
    $stmt = $conn->prepare("SELECT $abhyasi_cols, a.is_regular_practicing FROM tbl_abhyasis a LEFT JOIN tbl_centers c ON c.center_id = a.center_id $where ORDER BY c.center_name, a.first_name, a.last_name");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();
    send_json($data);
}

// ── professionals-list ────────────────────────────────────────────────────
if ($action === 'professionals-list') {
    $conn   = get_connection();
    $result = $conn->query("SELECT DISTINCT TRIM(professional) AS professional FROM tbl_abhyasis WHERE professional IS NOT NULL AND TRIM(professional) != '' ORDER BY professional");
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    send_json(array_column($rows, 'professional'));
}

// ── preceptor-sitting-count ───────────────────────────────────────────────
if ($action === 'preceptor-sitting-count') {
    $center_id = trim($_GET['center_id'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to   = trim($_GET['date_to']   ?? '');
    $conn = get_connection();
    ensure_sitting($conn);

    $where_parts = ["s.preceptor_name IS NOT NULL", "TRIM(s.preceptor_name) != ''"];
    $params = [];
    $types  = '';
    if ($center_id !== '') { $where_parts[] = 'a.center_id = ?'; $params[] = (int)$center_id; $types .= 'i'; }
    if ($date_from !== '') { $where_parts[] = 's.sitting_attend_date >= ?'; $params[] = $date_from; $types .= 's'; }
    if ($date_to   !== '') { $where_parts[] = 's.sitting_attend_date <= ?'; $params[] = $date_to;   $types .= 's'; }
    $where = 'WHERE ' . implode(' AND ', $where_parts);

    $sql = "
        SELECT s.preceptor_name, c.center_name,
               COUNT(s.id) AS sitting_count,
               DATE_FORMAT(MAX(s.sitting_attend_date),'%Y-%m-%d') AS last_sitting_date
        FROM tbl_abhyasis_sitting s
        JOIN  tbl_abhyasis  a ON a.abhyasi_id = s.abhyasi_id
        LEFT JOIN tbl_centers c ON c.center_id = a.center_id
        $where
        GROUP BY s.preceptor_name, c.center_name
        ORDER BY sitting_count DESC, s.preceptor_name
    ";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    send_json($data);
}

// ── pdf-satsang: Sunday Satsang count per center per month (YTD) ──────────
if ($action === 'pdf-satsang') {
    $yr      = (int)(new DateTime())->format('Y');
    $y_start = "$yr-01-01";
    $today   = (new DateTime())->format('Y-m-d');
    $conn    = get_connection();
    $sql = "
        SELECT
            COALESCE(c.center_name, 'Unknown') AS center_name,
            DATE_FORMAT(att.satsang_attend_date, '%M %Y')  AS month_name,
            DATE_FORMAT(att.satsang_attend_date, '%Y-%m')  AS month_sort,
            COUNT(*) AS attend_count
        FROM tbl_abhyasis_attedance att
        LEFT JOIN tbl_abhyasis  a ON a.abhyasi_id  = att.abhyasi_id
        LEFT JOIN tbl_centers   c ON c.center_id   = a.center_id
        WHERE att.is_attend = 1
          AND att.satsang_attend_date BETWEEN '$y_start' AND '$today'
        GROUP BY c.center_name, month_sort, month_name
        ORDER BY c.center_name, month_sort
    ";
    $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    send_json($data);
}

// ── pdf-sitting: Sitting count per center / preceptor / month (YTD) ───────
if ($action === 'pdf-sitting') {
    $yr      = (int)(new DateTime())->format('Y');
    $y_start = "$yr-01-01";
    $today   = (new DateTime())->format('Y-m-d');
    $conn    = get_connection();
    ensure_sitting($conn);
    $sql = "
        SELECT
            COALESCE(c.center_name, 'Unknown')                AS center_name,
            COALESCE(s.preceptor_name, 'Unknown')             AS preceptor_name,
            DATE_FORMAT(s.sitting_attend_date, '%M %Y')       AS month_name,
            DATE_FORMAT(s.sitting_attend_date, '%Y-%m')       AS month_sort,
            COUNT(*) AS sitting_count
        FROM tbl_abhyasis_sitting s
        JOIN  tbl_abhyasis  a ON a.abhyasi_id = s.abhyasi_id
        LEFT JOIN tbl_centers c ON c.center_id = a.center_id
        WHERE s.sitting_attend_date BETWEEN '$y_start' AND '$today'
        GROUP BY c.center_name, s.preceptor_name, month_sort, month_name
        ORDER BY c.center_name, s.preceptor_name, month_sort
    ";
    $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    send_json($data);
}

// ── pdf-events: Event report per event (YTD) ─────────────────────────────
if ($action === 'pdf-events') {
    $yr      = (int)(new DateTime())->format('Y');
    $y_start = "$yr-01-01";
    $today   = (new DateTime())->format('Y-m-d');
    $conn    = get_connection();
    ensure_event_tables($conn);
    $sql = "
        SELECT
            e.event_name,
            DATE_FORMAT(e.event_date, '%d %b %Y')   AS event_date,
            COALESCE(e.location, '')                AS location,
            COALESCE(e.district, '')                AS district,
            e.no_of_attendancies                    AS registered_member,
            COUNT(ea.abhyasi_id)                    AS attendant_abhyasis
        FROM tbl_event e
        LEFT JOIN tbl_event_abhyasis ea ON ea.evt_id = e.evt_id
        WHERE e.event_date BETWEEN '$y_start' AND '$today'
        GROUP BY e.evt_id, e.event_name, e.event_date, e.location,
                 e.district, e.no_of_attendancies
        ORDER BY e.event_date ASC
    ";
    $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    send_json($data);
}

send_json(['error' => "Unknown action: $action"], 400);
