<?php
/**
 * events_api.php
 * Handles CRUD for tbl_event, event abhyasis, and event images.
 *
 *   GET    events_api.php                                      → all events
 *   GET    events_api.php?id=1                                 → single event
 *   POST   events_api.php                                      → create event (JSON body)
 *   PUT    events_api.php?id=1                                 → update event (JSON body)
 *   DELETE events_api.php?id=1                                 → delete event
 *
 *   GET    events_api.php?action=abhyasis&id=1                 → abhyasis for event
 *   POST   events_api.php?action=add_abhyasis&id=1             → add abhyasis to event (JSON body)
 *   DELETE events_api.php?action=remove_abhyasi&id=1&abhyasi_id=2 → remove abhyasi from event
 *
 *   GET    events_api.php?action=images&id=1                   → images for event
 *   POST   events_api.php?action=upload_images&id=1            → upload images (multipart)
 *   DELETE events_api.php?action=delete_image&img_id=5         → delete image
 *
 *   GET    events_api.php?action=serve_image&file=xxx.jpg      → serve image file
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id'])     ? (int)$_GET['id']     : null;

// ── Ensure tables ─────────────────────────────────────────────────────────
function ensure_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_event (
            evt_id              INT AUTO_INCREMENT PRIMARY KEY,
            event_name          VARCHAR(255) NOT NULL,
            event_date          DATE         NOT NULL,
            location            VARCHAR(255) DEFAULT NULL,
            district            VARCHAR(150) DEFAULT NULL,
            no_of_attendancies  INT          DEFAULT 0,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_event_abhyasis (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            evt_id     INT NOT NULL,
            abhyasi_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_evt_abhyasi (evt_id, abhyasi_id),
            FOREIGN KEY (evt_id) REFERENCES tbl_event(evt_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS tbl_event_images (
            img_id     INT AUTO_INCREMENT PRIMARY KEY,
            evt_id     INT          NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (evt_id) REFERENCES tbl_event(evt_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── Upload folder ─────────────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../../assets/uploads/event_images/');
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ── Serve image file ──────────────────────────────────────────────────────
if ($action === 'serve_image') {
    $file = basename($_GET['file'] ?? '');
    $path = UPLOAD_DIR . $file;
    if (!$file || !file_exists($path)) { http_response_code(404); exit; }
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = match($ext) { 'jpg','jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', default => 'application/octet-stream' };
    header("Content-Type: $mime");
    readfile($path);
    exit;
}

// ── GET ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Images for event
    if ($action === 'images' && $id) {
        $conn = get_connection(); ensure_tables($conn);
        $stmt = $conn->prepare("SELECT img_id, evt_id, image_path, created_at FROM tbl_event_images WHERE evt_id = ? ORDER BY created_at");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close(); $conn->close();
        send_json($data);
    }

    // Abhyasis for event
    if ($action === 'abhyasis' && $id) {
        $conn = get_connection(); ensure_tables($conn);
        $stmt = $conn->prepare("
            SELECT a.abhyasi_id,
                   CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'')) AS abhyasi_name,
                   a.srcm_id, a.mobile_no, a.email_id, a.is_regular_practicing,
                   c.center_name
            FROM tbl_event_abhyasis ea
            JOIN tbl_abhyasis a ON a.abhyasi_id = ea.abhyasi_id
            LEFT JOIN tbl_centers c ON c.center_id = a.center_id
            WHERE ea.evt_id = ?
            ORDER BY a.first_name, a.last_name
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close(); $conn->close();
        send_json($data);
    }

    // Single event
    if ($id) {
        $conn = get_connection(); ensure_tables($conn);
        $stmt = $conn->prepare("SELECT e.*, COUNT(ea.abhyasi_id) AS registered_count FROM tbl_event e LEFT JOIN tbl_event_abhyasis ea ON ea.evt_id = e.evt_id WHERE e.evt_id = ? GROUP BY e.evt_id");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close(); $conn->close();
        if (!$row) send_json(['error' => 'Event not found'], 404);
        send_json($row);
    }

    // All events
    $conn = get_connection(); ensure_tables($conn);
    $result = $conn->query("
        SELECT e.*, COUNT(ea.abhyasi_id) AS registered_count
        FROM tbl_event e
        LEFT JOIN tbl_event_abhyasis ea ON ea.evt_id = e.evt_id
        GROUP BY e.evt_id
        ORDER BY e.event_date DESC
    ");
    $data = $result->fetch_all(MYSQLI_ASSOC);
    // Convert dates
    foreach ($data as &$row) {
        if (!empty($row['event_date'])) $row['event_date'] = (string)$row['event_date'];
        if (!empty($row['created_at'])) $row['created_at'] = (string)$row['created_at'];
    }
    $conn->close();
    send_json($data);
}

// ── POST ──────────────────────────────────────────────────────────────────
if ($method === 'POST') {

    // Add abhyasis to event
    if ($action === 'add_abhyasis' && $id) {
        $d = get_json_body();
        $abhyasi_ids = $d['abhyasi_ids'] ?? [];
        if (empty($abhyasi_ids)) send_json(['error' => 'No abhyasi_ids provided'], 400);

        $conn = get_connection(); ensure_tables($conn);
        $ins  = $conn->prepare("INSERT INTO tbl_event_abhyasis (evt_id, abhyasi_id) VALUES (?,?)");
        $name = $conn->prepare("SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS name FROM tbl_abhyasis WHERE abhyasi_id=?");
        $added = 0; $duplicates = [];

        foreach ($abhyasi_ids as $aid) {
            $aid = (int)$aid;
            $ins->bind_param('ii', $id, $aid);
            if (!$ins->execute()) {
                // Duplicate key
                $name->bind_param('i', $aid);
                $name->execute();
                $nr = $name->get_result()->fetch_assoc();
                $duplicates[] = trim($nr['name'] ?? (string)$aid);
            } else {
                $added++;
            }
        }
        // Update count
        $conn->query("UPDATE tbl_event SET no_of_attendancies = (SELECT COUNT(*) FROM tbl_event_abhyasis WHERE evt_id = $id) WHERE evt_id = $id");
        $ins->close(); $name->close(); $conn->close();
        send_json(['success' => true, 'added' => $added, 'duplicates' => $duplicates]);
    }

    // Upload images
    if ($action === 'upload_images' && $id) {
        if (!isset($_FILES['images'])) send_json(['error' => 'No files provided'], 400);
        $files = $_FILES['images'];
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        $conn = get_connection(); ensure_tables($conn);
        $ins  = $conn->prepare("INSERT INTO tbl_event_images (evt_id, image_path) VALUES (?,?)");
        $saved = [];

        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $name  = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmp   = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $err   = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
            if ($err !== UPLOAD_ERR_OK || !$name) continue;
            $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXT)) continue;
            $fname    = bin2hex(random_bytes(16)) . ".$ext";
            $filepath = UPLOAD_DIR . $fname;
            if (!move_uploaded_file($tmp, $filepath)) continue;
            $rel_path = "assets/uploads/event_images/$fname";
            $ins->bind_param('is', $id, $rel_path);
            $ins->execute();
            $saved[] = ['filename' => $fname, 'path' => $rel_path];
        }
        $ins->close(); $conn->close();
        send_json(['success' => true, 'uploaded' => count($saved), 'files' => $saved]);
    }

    // Create event
    $d = get_json_body();
    if (empty($d['event_name']) || empty($d['event_date']))
        send_json(['error' => 'event_name and event_date are required'], 400);
    $conn = get_connection(); ensure_tables($conn);
    $stmt = $conn->prepare("INSERT INTO tbl_event (event_name, event_date, location, district, no_of_attendancies) VALUES (?,?,?,?,?)");
    $event_name          = trim($d['event_name']);
    $event_date          = $d['event_date'];
    $location            = trim($d['location'] ?? '') ?: null;
    $district            = trim($d['district'] ?? '') ?: null;
    $no_of_attendancies  = (int)($d['no_of_attendancies'] ?? 0);
    $stmt->bind_param('ssssi', $event_name, $event_date, $location, $district, $no_of_attendancies);
    $stmt->execute();
    $evt_id = $conn->insert_id;
    $stmt->close(); $conn->close();
    send_json(['success' => true, 'evt_id' => $evt_id]);
}

// ── PUT ───────────────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $d = get_json_body();
    $conn = get_connection(); ensure_tables($conn);
    $stmt = $conn->prepare("UPDATE tbl_event SET event_name=?, event_date=?, location=?, district=?, no_of_attendancies=? WHERE evt_id=?");
    $event_name          = trim($d['event_name'] ?? '');
    $event_date          = $d['event_date'] ?? '';
    $location            = trim($d['location']  ?? '') ?: null;
    $district            = trim($d['district']  ?? '') ?: null;
    $no_of_attendancies  = (int)($d['no_of_attendancies'] ?? 0);
    $stmt->bind_param('ssssii', $event_name, $event_date, $location, $district, $no_of_attendancies, $id);
    $stmt->execute();
    $stmt->close(); $conn->close();
    send_json(['success' => true]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {

    // Remove abhyasi from event
    if ($action === 'remove_abhyasi' && $id) {
        $abhyasi_id = (int)($_GET['abhyasi_id'] ?? 0);
        if (!$abhyasi_id) send_json(['error' => 'abhyasi_id required'], 400);
        $conn = get_connection();
        $stmt = $conn->prepare("DELETE FROM tbl_event_abhyasis WHERE evt_id=? AND abhyasi_id=?");
        $stmt->bind_param('ii', $id, $abhyasi_id);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE tbl_event SET no_of_attendancies=(SELECT COUNT(*) FROM tbl_event_abhyasis WHERE evt_id=$id) WHERE evt_id=$id");
        $conn->close();
        send_json(['success' => true]);
    }

    // Delete image
    if ($action === 'delete_image') {
        $img_id = (int)($_GET['img_id'] ?? 0);
        if (!$img_id) send_json(['error' => 'img_id required'], 400);
        $conn = get_connection(); ensure_tables($conn);
        $stmt = $conn->prepare("SELECT image_path FROM tbl_event_images WHERE img_id=?");
        $stmt->bind_param('i', $img_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) { $conn->close(); send_json(['error' => 'Image not found'], 404); }
        // Delete physical file
        $base     = __DIR__ . '/../../';
        $filepath = $base . $row['image_path'];
        if (file_exists($filepath)) unlink($filepath);
        $del = $conn->prepare("DELETE FROM tbl_event_images WHERE img_id=?");
        $del->bind_param('i', $img_id);
        $del->execute();
        $del->close(); $conn->close();
        send_json(['success' => true]);
    }

    // Delete event
    if ($id) {
        $conn = get_connection();
        $stmt = $conn->prepare("DELETE FROM tbl_event WHERE evt_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close(); $conn->close();
        send_json(['success' => true]);
    }
}

send_json(['error' => 'Method not allowed or unknown action'], 405);
