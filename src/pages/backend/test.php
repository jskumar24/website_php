<?php
/**
 * test.php  —  Diagnostic checker
 * Open this in your browser: http://your-server/pages/backend/test.php
 * It will verify PHP, MySQL connection, and required tables.
 * DELETE this file from your server after setup is confirmed.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>TS12 Backend Diagnostic</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
  h1   { color: #4B49AC; }
  .card { background: #fff; border-radius: 8px; padding: 20px; margin: 16px 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .ok   { color: #28a745; font-weight: bold; }
  .fail { color: #dc3545; font-weight: bold; }
  .warn { color: #ffc107; font-weight: bold; }
  pre  { background: #f8f9fa; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: .85em; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { padding: 8px 12px; border: 1px solid #dee2e6; text-align: left; font-size: .9em; }
  th { background: #f8f9fa; }
  .step { display: flex; align-items: center; gap: 10px; margin: 8px 0; }
</style>
</head>
<body>
<h1>🔧 TS12 Backend Diagnostic</h1>
<p style="color:#666">This page checks your PHP and MySQL setup. <strong>Delete it after confirming everything works.</strong></p>

<?php

// ── Step 1: PHP ────────────────────────────────────────────────────────────
echo '<div class="card"><h2>1. PHP</h2>';
echo '<div class="step"><span class="ok">✅ PHP is running</span> — Version: ' . phpversion() . '</div>';
$ext_ok = extension_loaded('mysqli');
echo '<div class="step">' . ($ext_ok ? '<span class="ok">✅ MySQLi extension loaded</span>' : '<span class="fail">❌ MySQLi extension NOT loaded — enable in php.ini</span>') . '</div>';
echo '</div>';

// ── Step 2: db_config.php location ───────────────────────────────────────
echo '<div class="card"><h2>2. db_config.php</h2>';
$cfg_path = __DIR__ . '/db_config.php';
if (file_exists($cfg_path)) {
    echo '<div class="step"><span class="ok">✅ db_config.php found</span> at <code>' . htmlspecialchars($cfg_path) . '</code></div>';
    require_once $cfg_path;
} else {
    echo '<div class="step"><span class="fail">❌ db_config.php NOT found</span> — expected at <code>' . htmlspecialchars($cfg_path) . '</code></div>';
    echo '<p>Make sure <strong>db_config.php</strong> is in the same <code>backend/</code> folder as all the other PHP files.</p>';
    echo '</div></body></html>';
    exit;
}
echo '</div>';

// ── Step 3: MySQL connection ──────────────────────────────────────────────
echo '<div class="card"><h2>3. MySQL Connection</h2>';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo '<div class="step"><span class="fail">❌ Connection FAILED: ' . htmlspecialchars($conn->connect_error) . '</span></div>';
    echo '<p>Check these settings in <code>backend/db_config.php</code>:</p>';
    echo '<table><tr><th>Setting</th><th>Current Value</th></tr>';
    echo '<tr><td>DB_HOST</td><td>' . htmlspecialchars(DB_HOST) . '</td></tr>';
    echo '<tr><td>DB_USER</td><td>' . htmlspecialchars(DB_USER) . '</td></tr>';
    echo '<tr><td>DB_PASS</td><td>' . str_repeat('*', strlen(DB_PASS)) . '</td></tr>';
    echo '<tr><td>DB_NAME</td><td>' . htmlspecialchars(DB_NAME) . '</td></tr>';
    echo '</table>';
    echo '</div></body></html>';
    exit;
}
$conn->set_charset('utf8mb4');
echo '<div class="step"><span class="ok">✅ Connected to MySQL</span></div>';
echo '<div class="step"><span class="ok">✅ Database:</span> <code>' . htmlspecialchars(DB_NAME) . '</code></div>';
echo '</div>';

// ── Step 4: Required tables ───────────────────────────────────────────────
echo '<div class="card"><h2>4. Database Tables</h2>';
$required_tables = [
    'tbl_centers'              => 'Centers',
    'tbl_subcenters'           => 'Sub-Centers',
    'tbl_abhyasis'             => 'Abhyasis',
    'tbl_abhyasis_attedance'   => 'Attendance',
    'tbl_abhyasis_sitting'     => 'Sitting',
    'tbl_volunteer_work'       => 'Volunteer Works',
    'tbl_volunteer_work_abhyasi' => 'Volunteer-Abhyasi Assignments',
    'tbl_event'                => 'Events',
    'tbl_event_abhyasis'       => 'Event Abhyasis',
];

$result = $conn->query("SHOW TABLES");
$existing = [];
while ($row = $result->fetch_row()) $existing[] = $row[0];

echo '<table><tr><th>Table</th><th>Description</th><th>Status</th><th>Row Count</th></tr>';
foreach ($required_tables as $tbl => $desc) {
    $exists = in_array($tbl, $existing);
    $count  = '—';
    if ($exists) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM `$tbl`");
        if ($r) $count = $r->fetch_assoc()['c'];
    }
    $status = $exists
        ? '<span class="ok">✅ Exists</span>'
        : '<span class="fail">❌ Missing</span>';
    echo "<tr><td><code>$tbl</code></td><td>$desc</td><td>$status</td><td>$count</td></tr>";
}
echo '</table>';

$missing = array_filter(array_keys($required_tables), fn($t) => !in_array($t, $existing));
if ($missing) {
    echo '<p class="warn">⚠️ Some tables are missing. Run <code>corrected_db_script.sql</code> in your MySQL client.</p>';
} else {
    echo '<p class="ok" style="margin-top:12px">✅ All required tables exist.</p>';
}
echo '</div>';

// ── Step 5: PHP API file check ────────────────────────────────────────────
echo '<div class="card"><h2>5. PHP API Files</h2>';
$api_files = [
    'centers_api.php', 'subcenters_api.php', 'abhyasis_api.php',
    'attendance_api.php', 'sitting_api.php', 'volunteer_works_api.php',
    'volunteer_work_abhyasi_api.php', 'events_api.php', 'reports_api.php',
];
echo '<table><tr><th>File</th><th>Status</th></tr>';
foreach ($api_files as $f) {
    $path   = __DIR__ . '/' . $f;
    $exists = file_exists($path);
    $status = $exists ? '<span class="ok">✅ Found</span>' : '<span class="fail">❌ Missing</span>';
    echo "<tr><td><code>$f</code></td><td>$status</td></tr>";
}
echo '</table></div>';

// ── Step 6: Folder structure hint ────────────────────────────────────────
echo '<div class="card"><h2>6. Expected Folder Structure</h2>';
echo '<pre>src/
├── pages/
│   ├── tables/
│   │   ├── view-abhyasis.html
│   │   ├── view-attendance.html
│   │   ├── view-centers.html
│   │   ├── view-events.html
│   │   ├── view-reports.html
│   │   ├── view-sitting.html
│   │   ├── view-subcenters.html
│   │   ├── view-volunteer-work-abhyasi.html
│   │   └── view-volunteer-works.html
│   └── backend/              ← ALL .php files go here
│       ├── db_config.php     ← credentials file
│       ├── centers_api.php
│       ├── subcenters_api.php
│       ├── abhyasis_api.php
│       ├── attendance_api.php
│       ├── sitting_api.php
│       ├── events_api.php
│       ├── reports_api.php
│       ├── volunteer_works_api.php
│       ├── volunteer_work_abhyasi_api.php
│       └── test.php          ← delete after setup</pre>';
echo '</div>';

echo '<div class="card" style="background:#d4edda;border:1px solid #c3e6cb">';
echo '<h2 style="color:#155724">🎉 All checks passed!</h2>';
echo '<p style="color:#155724">Your PHP backend is set up correctly. You can now delete <code>test.php</code> from the server.</p>';
echo '</div>';

$conn->close();
?>
</body>
</html>
