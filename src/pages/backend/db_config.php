<?php
/**
 * db_config.php
 * Shared MySQL connection helper.
 * Place this file in the same backend/ folder as the other _api.php files.
 *
 * ── Edit these credentials to match your MySQL setup ──────────────────────
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root123');
define('DB_NAME', 'attendance_db');

// ── Always return JSON, even on PHP fatal errors ───────────────────────────
// This ensures fetch() in the browser always gets parseable JSON, never raw PHP HTML errors.
header('Content-Type: application/json');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'error' => 'PHP Fatal Error: ' . $err['message'],
            'file'  => basename($err['file']),
            'line'  => $err['line'],
        ]);
    }
});

ini_set('display_errors', '0');
error_reporting(E_ALL);

// ── Database connection ────────────────────────────────────────────────────
function get_connection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Database connection failed: ' . $conn->connect_error,
            'hint'  => 'Check DB_HOST / DB_USER / DB_PASS / DB_NAME in backend/db_config.php',
        ]);
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ── Send JSON response and exit ────────────────────────────────────────────
function send_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Read raw JSON POST body ────────────────────────────────────────────────
function get_json_body(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
