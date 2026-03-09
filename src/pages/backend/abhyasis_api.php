<?php
/**
 * abhyasis_api.php
 * Handles CRUD for tbl_abhyasis.
 *
 *   GET    abhyasis_api.php                      → all abhyasis
 *   GET    abhyasis_api.php?center_id=1          → filtered by center
 *   GET    abhyasis_api.php?id=1                 → single abhyasi
 *   POST   abhyasis_api.php                      → create  (JSON body)
 *   PUT    abhyasis_api.php?id=1                 → update  (JSON body)
 *   DELETE abhyasis_api.php?id=1                 → delete
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method    = $_SERVER['REQUEST_METHOD'];
$id        = isset($_GET['id'])        ? (int)$_GET['id']        : null;
$center_id = isset($_GET['center_id']) ? (int)$_GET['center_id'] : null;

// ── GET ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $conn = get_connection();
    $base_select = "SELECT a.*,
                    CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
                    c.center_name,
                    s.subcenter_name
             FROM tbl_abhyasis a
             LEFT JOIN tbl_centers    c ON c.center_id    = a.center_id
             LEFT JOIN tbl_subcenters s ON s.subcenter_id = a.subcenter_id";

    if ($id) {
        $stmt = $conn->prepare("$base_select WHERE a.abhyasi_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close(); $conn->close();
        if (!$row) send_json(['error' => 'Abhyasi not found'], 404);
        send_json($row);
    } elseif ($center_id) {
        $stmt = $conn->prepare("$base_select WHERE a.center_id = ? ORDER BY a.first_name, a.last_name");
        $stmt->bind_param('i', $center_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close(); $conn->close();
        send_json($data);
    } else {
        $result = $conn->query("$base_select ORDER BY a.abhyasi_id");
        $data   = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        send_json($data);
    }
}

// ── POST (create) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = get_json_body();
    foreach (['first_name', 'last_name', 'center_id'] as $f) {
        if (empty(trim((string)($d[$f] ?? ''))))
            send_json(['error' => "Missing required field: $f"], 400);
    }
    $conn = get_connection();
    $stmt = $conn->prepare(
        "INSERT INTO tbl_abhyasis
           (first_name, last_name, srcm_id, mobile_no, email_id,
            professional, age, is_regular_practicing, center_id, subcenter_id, gender)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $first_name             = trim($d['first_name']);
    $last_name              = trim($d['last_name']);
    $srcm_id                = trim($d['srcm_id'] ?? '');
    $mobile_no              = trim($d['mobile_no'] ?? '');
    $email_id               = trim($d['email_id'] ?? '');
    $professional           = trim($d['professional'] ?? '');
    $age                    = ($d['age'] !== null && $d['age'] !== '') ? (int)$d['age'] : null;
    $is_regular_practicing  = trim($d['is_regular_practicing'] ?? '');
    $cid                    = (int)$d['center_id'];
    $scid                   = ($d['subcenter_id'] !== null && $d['subcenter_id'] !== '') ? (int)$d['subcenter_id'] : null;
    $gender                 = trim($d['gender'] ?? '');
    $stmt->bind_param('ssssssiisss',
        $first_name, $last_name, $srcm_id, $mobile_no, $email_id,
        $professional, $age, $is_regular_practicing, $cid, $scid, $gender
    );
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'abhyasi_id' => $new_id], 201);
}

// ── PUT (update) ──────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $d = get_json_body();
    $allowed = ['first_name','last_name','srcm_id','mobile_no','email_id',
                'professional','age','is_regular_practicing','center_id','subcenter_id','gender'];
    $int_fields = ['center_id', 'subcenter_id', 'age'];
    $fields  = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $d)) continue;
        $v = $d[$k];
        if ($v === null || trim((string)$v) === '') {
            $fields[$k] = null;
        } else {
            $fields[$k] = in_array($k, $int_fields) ? (int)$v : trim((string)$v);
        }
    }
    if (empty($fields)) send_json(['error' => 'No valid fields provided'], 400);

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    $types  = '';
    $values = [];
    foreach ($fields as $k => $v) {
        $types  .= in_array($k, $int_fields) ? 'i' : 's';
        $values[] = $v;
    }
    $types  .= 'i';
    $values[] = $id;

    $conn = get_connection();
    $stmt = $conn->prepare("UPDATE tbl_abhyasis SET $set WHERE abhyasi_id = ?");
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close(); $conn->close();
    if ($affected === 0) send_json(['error' => 'Abhyasi not found or no changes made'], 404);
    send_json(['success' => true, 'abhyasi_id' => $id, 'updated' => $fields]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $conn = get_connection();
    $chk  = $conn->prepare("SELECT abhyasi_id FROM tbl_abhyasis WHERE abhyasi_id = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close(); $conn->close();
        send_json(['error' => "Abhyasi $id not found"], 404);
    }
    $chk->close();
    $stmt = $conn->prepare("DELETE FROM tbl_abhyasis WHERE abhyasi_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'deleted_id' => $id]);
}

send_json(['error' => 'Method not allowed'], 405);
