<?php
/**
 * centers_api.php
 * Handles CRUD for tbl_centers.
 *
 * Routes (simulated via $_GET['action'] or HTTP method + id):
 *   GET    centers_api.php              → list all centers
 *   GET    centers_api.php?id=1         → single center
 *   POST   centers_api.php              → create center  (JSON body)
 *   PUT    centers_api.php?id=1         → update center  (JSON body)
 *   DELETE centers_api.php?id=1         → delete center
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $conn = get_connection();
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM tbl_centers WHERE center_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        if (!$row) send_json(['error' => 'Center not found'], 404);
        send_json($row);
    } else {
        $result = $conn->query("SELECT * FROM tbl_centers ORDER BY center_id");
        $data   = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        send_json($data);
    }
}

// ── POST (create) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = get_json_body();
    $required = ['center_name', 'zone', 'location_name', 'district'];
    foreach ($required as $f) {
        if (empty(trim($d[$f] ?? '')))
            send_json(['error' => "Missing required field: $f"], 400);
    }
    $conn = get_connection();
    $stmt = $conn->prepare(
        "INSERT INTO tbl_centers (center_name, zone, location_name, village, district)
         VALUES (?, ?, ?, ?, ?)"
    );
    $center_name   = trim($d['center_name']);
    $zone          = trim($d['zone']);
    $location_name = trim($d['location_name']);
    $village       = trim($d['village'] ?? '');
    $district      = trim($d['district']);
    $stmt->bind_param('sssss', $center_name, $zone, $location_name, $village, $district);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();
    $conn->close();
    send_json(['success' => true, 'center_id' => $new_id], 201);
}

// ── PUT (update) ──────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $d = get_json_body();
    $allowed = ['center_name', 'zone', 'location_name', 'village', 'district'];
    $fields  = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $d))
            $fields[$k] = trim((string)($d[$k] ?? ''));
    }
    if (empty($fields)) send_json(['error' => 'No valid fields provided'], 400);

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    $values = array_values($fields);
    $types  = str_repeat('s', count($fields)) . 'i';
    $values[] = $id;

    $conn = get_connection();
    $stmt = $conn->prepare("UPDATE tbl_centers SET $set WHERE center_id = ?");
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    if ($affected === 0) send_json(['error' => 'Center not found or no changes made'], 404);
    send_json(['success' => true, 'center_id' => $id, 'updated' => $fields]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $conn = get_connection();
    $chk  = $conn->prepare("SELECT center_id FROM tbl_centers WHERE center_id = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close(); $conn->close();
        send_json(['error' => "Center $id not found"], 404);
    }
    $chk->close();
    $stmt = $conn->prepare("DELETE FROM tbl_centers WHERE center_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    send_json(['success' => true, 'deleted_id' => $id]);
}

send_json(['error' => 'Method not allowed'], 405);
