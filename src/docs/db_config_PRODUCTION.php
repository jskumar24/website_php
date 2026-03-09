<?php
/**
 * db_config.php — PRODUCTION VERSION
 * 
 * STEP 1: Fill in your Hostinger MySQL credentials below.
 * STEP 2: Rename this file to  db_config.php
 * STEP 3: Upload to  public_html/ts12/pages/backend/db_config.php
 *
 * Find these values in Hostinger hPanel → MySQL Databases
 */
define('DB_HOST', 'YOUR_MYSQL_HOST');      // e.g. mysql.hostinger.com
define('DB_USER', 'YOUR_DB_USERNAME');     // user you created in hPanel
define('DB_PASS', 'YOUR_STRONG_PASSWORD'); // password for that user
define('DB_NAME', 'ts12_attendance');      // database name you created

// ── Always return JSON, even on PHP fatal errors ──────────────────────
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

ini_set('display_errors', '0');   // Never show errors to browser in production
error_reporting(E_ALL);

// ── Database connection ───────────────────────────────────────────────
function get_connection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Database connection failed',
            'hint'  => 'Check DB credentials in db_config.php',
        ]);
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ── Send JSON response and exit ───────────────────────────────────────
function send_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Read raw JSON POST body ───────────────────────────────────────────
function get_json_body(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
