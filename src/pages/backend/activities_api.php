<?php
/**
 * activities_api.php
 * Zone Activities Summary — dashboard donut chart.
 *
 *   GET activities_api.php
 *
 * Returns for current calendar year:
 *   year, total,
 *   satsang, satsang_avg, satsang_dates,
 *   sitting, sitting_avg, sitting_dates,
 *   events,  events_avg,  event_count
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

$conn    = get_connection();
$year    = (int)(new DateTime())->format('Y');
$y_start = "$year-01-01";
$y_end   = "$year-12-31";

// ── Helper: run query safely, return first row or empty array ─────────────
function safe_query(mysqli $conn, string $sql): array {
    $r = $conn->query($sql);
    if (!$r) return [];          // query failed — treat as 0 values
    $row = $r->fetch_assoc();
    return $row ?? [];
}

// ── Ensure optional tables exist ──────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS tbl_abhyasis_sitting (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        abhyasi_id          INT NOT NULL,
        sitting_attend_date DATE NOT NULL,
        preceptor_name      VARCHAR(150) DEFAULT NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_abhyasi_date (abhyasi_id, sitting_attend_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$conn->query("
    CREATE TABLE IF NOT EXISTS tbl_event (
        evt_id             INT AUTO_INCREMENT PRIMARY KEY,
        event_name         VARCHAR(255) NOT NULL,
        event_date         DATE NOT NULL,
        location           VARCHAR(255) DEFAULT NULL,
        district           VARCHAR(150) DEFAULT NULL,
        no_of_attendancies INT DEFAULT 0,
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$conn->query("
    CREATE TABLE IF NOT EXISTS tbl_event_abhyasis (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        evt_id     INT NOT NULL,
        abhyasi_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_evt_abhyasi (evt_id, abhyasi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 1. Satsang attendance ─────────────────────────────────────────────────
$sat = safe_query($conn,
    "SELECT COUNT(*) AS satsang_total,
            COUNT(DISTINCT satsang_attend_date) AS satsang_dates
     FROM   tbl_abhyasis_attedance
     WHERE  is_attend = 1
       AND  satsang_attend_date BETWEEN '$y_start' AND '$y_end'"
);
$satsang       = (int)($sat['satsang_total'] ?? 0);
$satsang_dates = (int)($sat['satsang_dates'] ?? 0);
$satsang_avg   = $satsang_dates > 0 ? (int)round($satsang / $satsang_dates) : 0;

// ── 2. Individual sittings ────────────────────────────────────────────────
$sit = safe_query($conn,
    "SELECT COUNT(*) AS sitting_total,
            COUNT(DISTINCT sitting_attend_date) AS sitting_dates
     FROM   tbl_abhyasis_sitting
     WHERE  sitting_attend_date BETWEEN '$y_start' AND '$y_end'"
);
$sitting       = (int)($sit['sitting_total'] ?? 0);
$sitting_dates = (int)($sit['sitting_dates'] ?? 0);
$sitting_avg   = $sitting_dates > 0 ? (int)round($sitting / $sitting_dates) : 0;

// ── 3. Events & attendees ─────────────────────────────────────────────────
$evt = safe_query($conn,
    "SELECT COUNT(ea.id)             AS events_total,
            COUNT(DISTINCT e.evt_id) AS event_count
     FROM   tbl_event e
     LEFT JOIN tbl_event_abhyasis ea ON ea.evt_id = e.evt_id
     WHERE  e.event_date BETWEEN '$y_start' AND '$y_end'"
);
$events      = (int)($evt['events_total'] ?? 0);
$event_count = (int)($evt['event_count']  ?? 0);
$events_avg  = $event_count > 0 ? (int)round($events / $event_count) : 0;

$conn->close();

send_json([
    'year'          => $year,
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
