<?php
/**
 * subcenters_api.php
 * Handles CRUD for tbl_subcenters.
 *
 *   GET    subcenters_api.php                         → all sub-centers
 *   GET    subcenters_api.php?center_id=1             → filtered by center
 *   GET    subcenters_api.php?id=1                    → single sub-center
 *   POST   subcenters_api.php                         → create  (JSON body)
 *   PUT    subcenters_api.php?id=1                    → update  (JSON body)
 *   DELETE subcenters_api.php?id=1                    → delete
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
    if ($id) {
        $stmt = $conn->prepare(
            "SELECT s.*, c.center_name
             FROM tbl_subcenters s
             LEFT JOIN tbl_centers c ON c.center_id = s.center_id
             WHERE s.subcenter_id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close(); $conn->close();
        if (!$row) send_json(['error' => 'Sub-center not found'], 404);
        send_json($row);
    } elseif ($center_id) {
        $stmt = $conn->prepare(
            "SELECT s.*, c.center_name
             FROM tbl_subcenters s
             LEFT JOIN tbl_centers c ON c.center_id = s.center_id
             WHERE s.center_id = ?
             ORDER BY s.subcenter_id"
        );
        $stmt->bind_param('i', $center_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close(); $conn->close();
        send_json($data);
    } else {
        $result = $conn->query(
            "SELECT s.*, c.center_name
             FROM tbl_subcenters s
             LEFT JOIN tbl_centers c ON c.center_id = s.center_id
             ORDER BY s.subcenter_id"
        );
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        send_json($data);
    }
}

// ── POST (create) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = get_json_body();
    foreach (['subcenter_name', 'location_name', 'center_id'] as $f) {
        if (empty(trim((string)($d[$f] ?? ''))))
            send_json(['error' => "Missing required field: $f"], 400);
    }
    $conn           = get_connection();
    $subcenter_name = trim($d['subcenter_name']);
    $location_name  = trim($d['location_name']);
    $cid            = (int)$d['center_id'];
    $stmt = $conn->prepare(
        "INSERT INTO tbl_subcenters (subcenter_name, location_name, center_id) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('ssi', $subcenter_name, $location_name, $cid);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'subcenter_id' => $new_id], 201);
}

// ── PUT (update) ──────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $d = get_json_body();
    $allowed = ['subcenter_name', 'location_name', 'center_id'];
    $fields  = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $d) && trim((string)($d[$k] ?? '')) !== '')
            $fields[$k] = $k === 'center_id' ? (int)$d[$k] : trim((string)$d[$k]);
    }
    if (empty($fields)) send_json(['error' => 'No valid fields provided'], 400);

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    $types  = '';
    $values = [];
    foreach ($fields as $k => $v) {
        $types  .= ($k === 'center_id') ? 'i' : 's';
        $values[] = $v;
    }
    $types  .= 'i';
    $values[] = $id;

    $conn = get_connection();
    $stmt = $conn->prepare("UPDATE tbl_subcenters SET $set WHERE subcenter_id = ?");
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close(); $conn->close();
    if ($affected === 0) send_json(['error' => 'Sub-center not found or no changes made'], 404);
    send_json(['success' => true, 'subcenter_id' => $id, 'updated' => $fields]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) send_json(['error' => 'id is required'], 400);
    $conn = get_connection();
    $chk  = $conn->prepare("SELECT subcenter_id FROM tbl_subcenters WHERE subcenter_id = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close(); $conn->close();
        send_json(['error' => "Sub-center $id not found"], 404);
    }
    $chk->close();
    $stmt = $conn->prepare("DELETE FROM tbl_subcenters WHERE subcenter_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'deleted_id' => $id]);
}

send_json(['error' => 'Method not allowed'], 405);
