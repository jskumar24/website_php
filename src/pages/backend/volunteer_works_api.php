<?php
/**
 * volunteer_works_api.php
 * CRUD for tbl_volunteer_work.
 * Dynamically detects the primary key column name (handles tables with
 * different PK column names: vol_work_id, id, etc.)
 *
 *   GET    volunteer_works_api.php          → all volunteer works
 *   GET    volunteer_works_api.php?id=1     → single record
 *   POST   volunteer_works_api.php          → create  (JSON body)
 *   PUT    volunteer_works_api.php?id=1     → update  (JSON body)
 *   DELETE volunteer_works_api.php?id=1     → delete
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── Detect PK column of tbl_volunteer_work ────────────────────────────────
function get_vol_pk(mysqli $conn): string {
    $res = $conn->query("SHOW KEYS FROM tbl_volunteer_work WHERE Key_name = 'PRIMARY'");
    $row = $res ? $res->fetch_assoc() : null;
    return $row['Column_name'] ?? 'id';
}

// ── GET ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $conn = get_connection();
    $pk   = get_vol_pk($conn);

    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM tbl_volunteer_work WHERE $pk = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close(); $conn->close();
        if (!$row) send_json(['error' => 'Volunteer work not found'], 404);
        send_json($row);
    } else {
        $result = $conn->query("SELECT * FROM tbl_volunteer_work ORDER BY $pk");
        $data   = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        send_json($data);
    }
}

// ── POST (create) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = get_json_body();
    if (empty(trim($d['volunteer_name'] ?? '')))
        send_json(['error' => 'Missing required field: volunteer_name'], 400);

    $conn          = get_connection();
    $pk            = get_vol_pk($conn);
    $volunteer_name = trim($d['volunteer_name']);
    $stmt = $conn->prepare("INSERT INTO tbl_volunteer_work (volunteer_name) VALUES (?)");
    $stmt->bind_param('s', $volunteer_name);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'id' => $new_id, 'vol_work_id' => $new_id, 'pk' => $pk], 201);
}

// ── PUT (update) ──────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $d = get_json_body();
    $volunteer_name = trim($d['volunteer_name'] ?? '');
    if ($volunteer_name === '') send_json(['error' => 'No valid fields provided'], 400);

    $conn = get_connection();
    $pk   = get_vol_pk($conn);
    $stmt = $conn->prepare("UPDATE tbl_volunteer_work SET volunteer_name = ? WHERE $pk = ?");
    $stmt->bind_param('si', $volunteer_name, $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close(); $conn->close();
    if ($affected === 0) send_json(['error' => 'Volunteer work not found or no changes made'], 404);
    send_json(['success' => true, 'id' => $id, 'updated' => ['volunteer_name' => $volunteer_name]]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $conn = get_connection();
    $pk   = get_vol_pk($conn);

    $chk = $conn->prepare("SELECT $pk FROM tbl_volunteer_work WHERE $pk = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close(); $conn->close();
        send_json(['error' => "Volunteer work $id not found"], 404);
    }
    $chk->close();
    $stmt = $conn->prepare("DELETE FROM tbl_volunteer_work WHERE $pk = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'deleted_id' => $id]);
}

send_json(['error' => 'Method not allowed'], 405);
