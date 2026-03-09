<?php
/**
 * auth_api.php
 * Login endpoint — replaces auth.py
 *
 *   POST auth_api.php   { "srcm_id": "INXXXXX" }
 *
 * Access rules:
 *   Allowed to log in : Preceptor, ZC, CC, DC, NC
 *   role_level "full" : NC / ZC / CC / DC  (full site access)
 *   role_level "preceptor": Preceptor only (limited access)
 *   can_delete        : ZC and DC only
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$ALLOWED_ROLES = ['preceptor', 'zc', 'cc', 'dc', 'nc'];
$FULL_ROLES    = ['zc', 'cc', 'dc', 'nc'];
$DELETE_ROLES  = ['zc', 'dc'];
$LABEL_MAP     = ['preceptor' => 'Preceptor', 'zc' => 'ZC', 'cc' => 'CC', 'dc' => 'DC', 'nc' => 'NC'];

$d       = get_json_body();
$srcm_id = trim($d['srcm_id'] ?? '');

if ($srcm_id === '') {
    send_json(['error' => 'srcm_id is required'], 400);
}

$conn = get_connection();

// ── Auto-detect PK of tbl_volunteer_work ──────────────────────────────────
$pk_res = $conn->query("SHOW KEYS FROM tbl_volunteer_work WHERE Key_name = 'PRIMARY'");
$pk_row = $pk_res ? $pk_res->fetch_assoc() : null;
$vol_pk = $pk_row['Column_name'] ?? 'vol_id';

// ── 1. Find abhyasi by SRCM ID ────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT abhyasi_id,
           TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS full_name,
           srcm_id
    FROM tbl_abhyasis
    WHERE UPPER(TRIM(srcm_id)) = UPPER(TRIM(?))
    LIMIT 1
");
$stmt->bind_param('s', $srcm_id);
$stmt->execute();
$abhyasi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$abhyasi) {
    $conn->close();
    send_json(['error' => 'SRCM ID not found. Please check your ID or contact your coordinator.'], 401);
}

// ── 2. Fetch all volunteer roles for this abhyasi ─────────────────────────
$stmt2 = $conn->prepare("
    SELECT LOWER(TRIM(vw.volunteer_name)) AS role_name
    FROM tbl_volunteer_work_abhyasi va
    JOIN tbl_volunteer_work vw ON vw.$vol_pk = va.vol_id
    WHERE va.abhyasi_id = ?
");
$stmt2->bind_param('i', $abhyasi['abhyasi_id']);
$stmt2->execute();
$rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
$conn->close();

$all_roles  = array_column($rows, 'role_name');
$user_roles = array_values(array_filter($all_roles, fn($r) => in_array($r, $ALLOWED_ROLES)));

if (empty($user_roles)) {
    send_json(['error' => 'Access denied. Only Preceptors, ZC, CC, DC and NC may log in.'], 403);
}

// ── 3. Determine access level ─────────────────────────────────────────────
$has_full   = (bool) array_filter($user_roles, fn($r) => in_array($r, $FULL_ROLES));
$can_delete = (bool) array_filter($user_roles, fn($r) => in_array($r, $DELETE_ROLES));
$role_level = $has_full ? 'full' : 'preceptor';

$display = array_values(array_filter(
    array_map(fn($r) => $LABEL_MAP[$r] ?? null, $user_roles),
    fn($v) => $v !== null
));

send_json([
    'success'    => true,
    'srcm_id'    => $abhyasi['srcm_id'],
    'name'       => $abhyasi['full_name'],
    'roles'      => $display,
    'role_level' => $role_level,
    'can_delete' => $can_delete,
]);
