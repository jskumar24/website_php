<?php
/**
 * volunteer_work_abhyasi_api.php
 * CRUD for tbl_volunteer_work_abhyasi (assignments of abhyasis to volunteer roles).
 * Dynamically detects PK columns of both tbl_volunteer_work and tbl_volunteer_work_abhyasi.
 *
 *   GET    volunteer_work_abhyasi_api.php          → all assignments (with names)
 *   POST   volunteer_work_abhyasi_api.php          → create assignment  (JSON body)
 *   PUT    volunteer_work_abhyasi_api.php?id=X     → update assignment  (JSON body)
 *   DELETE volunteer_work_abhyasi_api.php?id=X     → delete assignment
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── Detect PKs ────────────────────────────────────────────────────────────
function get_vol_pk(mysqli $conn): string {
    $res = $conn->query("SHOW KEYS FROM tbl_volunteer_work WHERE Key_name = 'PRIMARY'");
    $row = $res ? $res->fetch_assoc() : null;
    return $row['Column_name'] ?? 'vol_work_id';
}

function get_own_pk(mysqli $conn): string {
    $res = $conn->query("SHOW KEYS FROM tbl_volunteer_work_abhyasi WHERE Key_name = 'PRIMARY'");
    $row = $res ? $res->fetch_assoc() : null;
    return $row['Column_name'] ?? 'vol_abhy_id';
}

// ── Ensure table exists ───────────────────────────────────────────────────
function ensure_table(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_volunteer_work_abhyasi (
            vol_abhy_id INT AUTO_INCREMENT PRIMARY KEY,
            abhyasi_id  INT NOT NULL,
            vol_id      INT NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── GET all assignments ───────────────────────────────────────────────────
if ($method === 'GET') {
    $conn   = get_connection();
    ensure_table($conn);
    $vol_pk = get_vol_pk($conn);
    $own_pk = get_own_pk($conn);

    $result = $conn->query("
        SELECT va.$own_pk AS id,
               va.abhyasi_id,
               va.vol_id,
               CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
               a.srcm_id,
               a.center_id,
               vw.volunteer_name
        FROM tbl_volunteer_work_abhyasi va
        LEFT JOIN tbl_abhyasis       a  ON a.abhyasi_id = va.abhyasi_id
        LEFT JOIN tbl_volunteer_work vw ON vw.$vol_pk   = va.vol_id
        ORDER BY abhyasi_name
    ");
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    send_json($data);
}

// ── POST (create) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = get_json_body();
    foreach (['abhyasi_id', 'vol_id'] as $f) {
        if (empty(trim((string)($d[$f] ?? ''))))
            send_json(['error' => "Missing required field: $f"], 400);
    }

    $conn      = get_connection();
    ensure_table($conn);
    $own_pk    = get_own_pk($conn);
    $abhyasi_id = (int)$d['abhyasi_id'];
    $vol_id     = (int)$d['vol_id'];

    // Duplicate check
    $chk = $conn->prepare("SELECT 1 FROM tbl_volunteer_work_abhyasi WHERE abhyasi_id = ? AND vol_id = ?");
    $chk->bind_param('ii', $abhyasi_id, $vol_id);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close(); $conn->close();
        send_json(['error' => 'This abhyasi is already assigned to the selected volunteer work.'], 409);
    }
    $chk->close();

    $stmt = $conn->prepare("INSERT INTO tbl_volunteer_work_abhyasi (abhyasi_id, vol_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $abhyasi_id, $vol_id);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'id' => $new_id], 201);
}

// ── PUT (update) ──────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $d = get_json_body();

    $allowed = ['abhyasi_id', 'vol_id'];
    $fields  = [];
    foreach ($allowed as $k) {
        if (isset($d[$k]) && trim((string)$d[$k]) !== '')
            $fields[$k] = (int)$d[$k];
    }
    if (empty($fields)) send_json(['error' => 'No valid fields provided'], 400);

    $conn   = get_connection();
    $own_pk = get_own_pk($conn);
    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    $types  = str_repeat('i', count($fields)) . 'i';
    $values = array_values($fields);
    $values[] = $id;

    $stmt = $conn->prepare("UPDATE tbl_volunteer_work_abhyasi SET $set WHERE $own_pk = ?");
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close(); $conn->close();
    if ($affected === 0) send_json(['error' => 'Record not found or no changes made'], 404);
    send_json(['success' => true, 'id' => $id, 'updated' => $fields]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $conn   = get_connection();
    $own_pk = get_own_pk($conn);

    $chk = $conn->prepare("SELECT 1 FROM tbl_volunteer_work_abhyasi WHERE $own_pk = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close(); $conn->close();
        send_json(['error' => "Record $id not found"], 404);
    }
    $chk->close();

    $stmt = $conn->prepare("DELETE FROM tbl_volunteer_work_abhyasi WHERE $own_pk = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'deleted_id' => $id]);
}

send_json(['error' => 'Method not allowed'], 405);
