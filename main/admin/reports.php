<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../Front_Page.php');
    exit();
}

require_once __DIR__ . '/../../config/rbac.php';
checkRole(['admin']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$user_id = (int)$_SESSION['user_id'];
$user_fullname = $_SESSION['full_name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@umak.edu.ph';
$user_role = $_SESSION['role'] ?? 'admin';
$user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;

$nameParts = explode(' ', $user_fullname);
$user_initials = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
    : strtoupper(substr($user_fullname, 0, 2));

require_once __DIR__ . '/../../config/db_connect_pg.php';
$database = new Database();
$pdo = $database->getConnection();

// Kunin ang admin info mula sa database (hindi na umaasa sa session / hardcoded fallback)
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($me) {
            $user_fullname = $me['full_name'] ?: $user_fullname;
            $user_email    = $me['email'] ?: $user_email;
            $user_role     = $me['role'] ?: $user_role;
            $user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;
            $nameParts = explode(' ', trim($user_fullname));
            $user_initials = count($nameParts) >= 2
                ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
                : strtoupper(substr($user_fullname, 0, 2));
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$count_pending = 0; $count_processing = 0; $count_ready = 0; $count_completed = 0; $count_rejected = 0;
$all_students = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $s = strtoupper($row['status']); $c = (int)$row['count'];
            if ($s === 'PENDING') $count_pending += $c;
            elseif (in_array($s, ['PROCESSING','ACCEPTED'])) $count_processing += $c;
            elseif ($s === 'READY') $count_ready += $c;
            elseif (in_array($s, ['COMPLETED','CLAIMED'])) $count_completed += $c;
            elseif ($s === 'REJECTED') $count_rejected += $c;
        }

        $stmt = $pdo->query("SELECT id, username, email, full_name, student_id, mobile, birthdate, program, college, course, year_level, graduation_year, profile_picture, is_active, is_verified, created_at, last_login FROM users WHERE LOWER(TRIM(role)) <> 'admin' ORDER BY full_name ASC");
        $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$count_total = $count_pending + $count_processing + $count_ready + $count_completed + $count_rejected;

/* ---------- UMak colleges (same list as the registration form) ---------- */
$colleges = [
    ['abbr'=>'CCAPS',   'name'=>'College of Continuing, Advanced and Professional Studies'],
    ['abbr'=>'IAD',     'name'=>'Institute of Arts and Design'],
    ['abbr'=>'CBA',     'name'=>'College of Business Administration'],
    ['abbr'=>'IOA',     'name'=>'Institute of Accountancy and Finance'],
    ['abbr'=>'CCIS',    'name'=>'College of Computing and Information Sciences'],
    ['abbr'=>'CHK',     'name'=>'College of Human Kinetics'],
    ['abbr'=>'CGPP',    'name'=>'College of Governance and Public Policy'],
    ['abbr'=>'ION',     'name'=>'Institute of Nursing'],
    ['abbr'=>'IOP',     'name'=>'Institute of Pharmacy'],
    ['abbr'=>'IIHS',    'name'=>'Institute of Imaging Health Sciences'],
    ['abbr'=>'CITE',    'name'=>'College of Innovative Teacher Education'],
    ['abbr'=>'COS',     'name'=>'College of Science'],
    ['abbr'=>'CTHM',    'name'=>'College of Tourism and Hospitality Management'],
    ['abbr'=>'CET',     'name'=>'College of Engineering and Technology'],
    ['abbr'=>'SOL',     'name'=>'School of Law'],
    ['abbr'=>'CITE-HSU','name'=>'College of Innovative Teacher Education – Higher School ng UMak'],
    ['abbr'=>'IDEM',    'name'=>'Institute for Disaster and Emergency Management'],
    ['abbr'=>'ISDNB',   'name'=>'Institute for Social Development and Nation Building'],
    ['abbr'=>'ITEST',   'name'=>'Institute for Technical Education and Skills Training'],
];
$college_groups = [];
foreach ($colleges as $c) { $college_groups[$c['abbr']] = ['abbr'=>$c['abbr'], 'name'=>$c['name'], 'students'=>[]]; }
$college_groups['OTHER'] = ['abbr'=>'OTHER', 'name'=>'Unassigned / Other', 'students'=>[]];

function resolveCollegeKey($raw, $groups) {
    $raw = trim((string)$raw);
    if ($raw === '') return 'OTHER';
    $upper = strtoupper($raw);
    if (isset($groups[$upper])) return $upper;
    if (preg_match('/\(([^)]+)\)\s*$/', $raw, $m)) {
        $abbr = strtoupper(trim($m[1]));
        if (isset($groups[$abbr])) return $abbr;
    }
    foreach ($groups as $k => $g) {
        if ($k === 'OTHER') continue;
        if (strcasecmp($g['name'], $raw) === 0) return $k;
    }
    return 'OTHER';
}

foreach ($all_students as $s) {
    $key = resolveCollegeKey($s['college'] ?? '', $college_groups);
    $college_groups[$key]['students'][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UMak · Reports</title>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root {
    --primary:#003366; --primary-dark:#002244; --primary-light:#1a4d7a;
    --gold:#FFD700; --gold-dark:#E6C200;
    --bg:#f4f7fb; --surface:#ffffff; --surface-2:#f5f6f8; --surface-3:#eceef2;
    --border:#e3e6eb; --border-md:#cfd5dd;
    --text-1:#1a2a3a; --text-2:#4a5a6a; --text-3:#7b8896;
    --accent-soft:rgba(0,51,102,0.07);
    --shadow-sm:0 1px 2px rgba(16,24,40,0.05);
    --st-pending-bg:#fef6dd; --st-pending-fg:#8a6100;
    --st-info-bg:#e6effa;    --st-info-fg:#1a4d7a;
    --st-ok-bg:#e3f4e8;      --st-ok-fg:#1e7e34;
    --st-bad-bg:#fde8e8;     --st-bad-fg:#b91c1c;
}

html { height:100%; margin:0; padding:0; overflow:hidden; }
body {
    font-family:'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    background:var(--bg); color:var(--text-1);
    height:100%; margin:0; padding:0; overflow-y:auto; overflow-x:hidden;
    -webkit-font-smoothing:antialiased;
}
button, input, select, textarea { font-family:inherit; }
h1, h2, h3, .serif { font-family:'Libre Baskerville', Georgia, serif; }

.main {
    position:relative; margin-left:0; min-height:100vh; padding:1.5rem 2rem 3rem;
    background:
        radial-gradient(900px 260px at 18% 0%, rgba(120,170,225,0.55) 0%, rgba(120,170,225,0) 70%),
        radial-gradient(700px 240px at 85% 0%, rgba(255,215,0,0.22) 0%, rgba(255,215,0,0) 70%),
        linear-gradient(180deg,#cddff2 0%,#e3edf8 170px,#f4f7fb 380px,#ffffff 100%);
    background-repeat:no-repeat;
}

.topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }

.btn-home { display:inline-flex; align-items:center; gap:8px; padding:0.55rem 1.1rem; font-size:0.8rem; font-weight:600; color:var(--primary); background:var(--gold); border:1px solid var(--gold-dark); border-radius:8px; text-decoration:none; cursor:pointer; transition:all 0.15s; }
.btn-home:hover { background:var(--gold-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,51,102,0.18); }
.btn-home svg { width:16px; height:16px; stroke:currentColor; stroke-width:2.2; fill:none; stroke-linecap:round; stroke-linejoin:round; }

.topbar-actions { display:flex; align-items:center; gap:0.75rem; }
.user-menu { display:flex; align-items:center; gap:10px; padding:0.35rem 0.75rem 0.35rem 0.35rem; background:#fff; border:1px solid var(--border-md); border-radius:8px; cursor:pointer; transition:all 0.15s; position:relative; user-select:none; }
.user-menu:hover { background:var(--surface-2); }
.user-avatar { width:30px; height:30px; background:var(--gold); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:0.68rem; font-weight:700; }
.user-name { font-size:0.75rem; font-weight:600; color:var(--text-1); }
.user-dropdown { position:absolute; top:calc(100% + 8px); right:0; width:260px; background:#fff; border:1px solid var(--border-md); border-radius:12px; box-shadow:0 12px 40px rgba(0,51,102,0.18), 0 4px 12px rgba(0,0,0,0.06); padding:0.5rem; opacity:0; visibility:hidden; transform:translateY(-8px); transition:all 0.2s cubic-bezier(0.22,1,0.36,1); z-index:200; }
.user-menu.open .user-dropdown { opacity:1; visibility:visible; transform:translateY(0); }
.user-dropdown-header { display:flex; align-items:center; gap:12px; padding:0.75rem 0.85rem; }
.user-dropdown-avatar { width:42px; height:42px; background:var(--gold); border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:0.9rem; font-weight:700; flex-shrink:0; font-family:'Libre Baskerville', serif; }
.user-dropdown-info { min-width:0; flex:1; }
.user-dropdown-name { font-size:0.82rem; font-weight:600; color:var(--text-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-dropdown-email { font-size:0.7rem; color:var(--text-3); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-dropdown-divider { height:1px; background:var(--border); margin:0.4rem 0.5rem; }
.user-dropdown-item { display:flex; align-items:center; gap:10px; padding:0.6rem 0.85rem; font-size:0.8rem; font-weight:500; color:var(--text-2); text-decoration:none; border-radius:8px; transition:all 0.15s; cursor:pointer; }
.user-dropdown-item:hover { background:var(--surface-2); color:var(--text-1); }
.user-dropdown-item svg { width:16px; height:16px; flex-shrink:0; opacity:0.7; }
.user-dropdown-item.user-dropdown-logout { color:#dc2626; }
.user-dropdown-item.user-dropdown-logout:hover { background:#fef2f2; color:#b91c1c; }
.user-dropdown-item.user-dropdown-logout svg { opacity:1; }

/* ============ AUDIT-LOG STYLE PANEL ============ */
.log-panel { background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 6px 24px rgba(0,51,102,0.08); padding:1.5rem 1.75rem 1.75rem; }
.log-title { font-family:'Inter', system-ui, sans-serif; font-size:1.7rem; font-weight:700; color:var(--primary); letter-spacing:-0.01em; margin-bottom:1rem; }

/* summary chips */
.summary-strip { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.25rem; }
.sum-chip { display:inline-flex; align-items:center; gap:8px; padding:5px 12px; border-radius:999px; font-size:0.72rem; font-weight:600; background:var(--surface-3); color:var(--text-2); }
.sum-chip b { font-size:0.82rem; }
.sum-chip.p { background:var(--st-pending-bg); color:var(--st-pending-fg); }
.sum-chip.i { background:var(--st-info-bg); color:var(--st-info-fg); }
.sum-chip.o { background:var(--st-ok-bg); color:var(--st-ok-fg); }
.sum-chip.r { background:var(--st-bad-bg); color:var(--st-bad-fg); }
.sum-chip.t { background:var(--primary); color:#fff; }

/* filter bar */
.filter-bar { display:flex; align-items:flex-end; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.f-field { display:flex; flex-direction:column; min-width:140px; }
.f-field.grow { flex:1; min-width:200px; }
.f-field label { font-size:0.62rem; font-weight:600; color:var(--text-3); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:2px; }
.f-field input, .f-field select { background:transparent; border:none; border-bottom:1px solid var(--border-md); padding:0.45rem 0.15rem; font-size:0.8rem; color:var(--text-1); transition:border-color 0.15s; border-radius:0; }
.f-field input:focus, .f-field select:focus { outline:none; border-bottom-color:var(--primary); box-shadow:0 1px 0 0 var(--primary); }
.f-field select { cursor:pointer; }
.f-date { display:flex; align-items:flex-end; gap:0.6rem; }
.f-date .sep { color:var(--text-3); font-size:0.8rem; padding-bottom:0.5rem; }
.btn-clear { align-self:flex-end; background:transparent; border:none; color:var(--primary-light); font-size:0.74rem; font-weight:600; cursor:pointer; padding:0.45rem 0.25rem; }
.btn-clear:hover { text-decoration:underline; }

/* table */
.log-table-wrap { border:1px solid var(--border); border-radius:12px; overflow:auto; max-height:560px; background:#fff; }
.log-table { width:100%; border-collapse:separate; border-spacing:0; }
.log-table thead th { position:sticky; top:0; z-index:3; background:#fff; text-align:left; font-size:0.66rem; font-weight:700; color:var(--primary-light); text-transform:uppercase; letter-spacing:0.07em; padding:0.85rem 1rem; border-bottom:1px solid var(--border); white-space:nowrap; }
.log-table tbody td { padding:0.7rem 1rem; font-size:0.74rem; color:var(--text-1); border-bottom:2px solid #fff; background:#f1f3f6; vertical-align:middle; white-space:nowrap; }
.log-table tbody tr.stu-row:hover td { background:#e6edf6; }

/* college category header */
.group-head td { padding:0 !important; background:#fff !important; border-bottom:none !important; }
.group-head .gh { display:flex; align-items:center; gap:10px; padding:0.8rem 1rem; margin-top:0.35rem; background:linear-gradient(90deg, rgba(0,51,102,0.07), rgba(0,51,102,0.02)); border-left:4px solid var(--gold); cursor:pointer; user-select:none; }
.group-head .gh:hover { background:linear-gradient(90deg, rgba(0,51,102,0.12), rgba(0,51,102,0.04)); }
.gh .abbr { background:var(--primary); color:#fff; font-size:0.66rem; font-weight:700; padding:3px 9px; border-radius:6px; letter-spacing:0.05em; font-family:'Libre Baskerville', serif; }
.gh .cname { font-size:0.8rem; font-weight:600; color:var(--text-1); }
.gh .gcount { margin-left:auto; font-size:0.68rem; font-weight:700; background:var(--gold); color:var(--primary); padding:2px 10px; border-radius:999px; }
.gh .chev { width:14px; height:14px; stroke:var(--text-3); stroke-width:2.4; fill:none; transition:transform 0.2s; flex-shrink:0; }
tbody.collapsed .chev { transform:rotate(-90deg); }
tbody.collapsed .stu-row { display:none !important; }
tbody.group-empty-note .stu-row { display:none; }

.status-badge { display:inline-block; font-size:0.6rem; font-weight:700; padding:3px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap; background:var(--surface-3); color:var(--text-2); }
.status-badge.pending { background:var(--st-pending-bg); color:var(--st-pending-fg); }
.status-badge.completed { background:var(--st-ok-bg); color:var(--st-ok-fg); }
.status-badge.rejected { background:var(--st-bad-bg); color:var(--st-bad-fg); }

.avatar-sm { width:26px; height:26px; border-radius:6px; background:var(--gold); color:var(--primary); display:inline-flex; align-items:center; justify-content:center; font-size:0.62rem; font-weight:700; font-family:'Libre Baskerville', serif; vertical-align:middle; margin-right:8px; flex-shrink:0; }
.avatar-img { width:26px; height:26px; border-radius:6px; object-fit:cover; vertical-align:middle; margin-right:8px; }
.name-cell { display:flex; align-items:center; }

.empty-row { text-align:center; padding:2.5rem 1rem; color:var(--text-3); font-size:0.78rem; display:none; }
.log-foot { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-top:1.1rem; }
.showing { font-size:0.74rem; color:var(--text-3); }
.showing b { color:var(--text-1); }

.report-actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn-export { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; font-size:0.8rem; font-weight:600; border-radius:8px; cursor:pointer; transition:all 0.2s ease; border:1px solid transparent; text-decoration:none; }
.btn-export svg { width:16px; height:16px; flex-shrink:0; }
.btn-export.btn-csv { background:var(--primary); color:#fff; border-color:var(--primary); }
.btn-export.btn-csv:hover { background:var(--primary-dark); border-color:var(--primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,51,102,0.28); }
.btn-export.btn-pdf { background:var(--gold); color:var(--primary); border-color:var(--gold-dark); }
.btn-export.btn-pdf:hover { background:var(--gold-dark); border-color:var(--gold-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(230,194,0,0.35); }

.toast-container { position:fixed; top:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:0.5rem; }
.toast { display:flex; align-items:center; gap:10px; padding:0.85rem 1.25rem; background:#fff; border:1px solid var(--border); border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.10); font-size:0.78rem; color:var(--text-1); min-width:280px; max-width:400px; animation:slideIn 0.3s ease; }
.toast.success { border-left:4px solid var(--primary); }
.toast.error   { border-left:4px solid #ef4444; }
.toast.info    { border-left:4px solid var(--gold); }
@keyframes slideIn  { from { transform:translateX(120%); opacity:0; } to { transform:translateX(0); opacity:1; } }
@keyframes slideOut { from { transform:translateX(0); opacity:1; } to { transform:translateX(120%); opacity:0; } }
.toast.hiding { animation:slideOut 0.3s ease forwards; }

.fab-top { position:fixed; bottom:28px; right:28px; width:46px; height:46px; background:#fff; border:2px solid var(--primary); border-radius:12px; box-shadow:0 6px 18px rgba(0,51,102,0.18); cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:150; opacity:0; visibility:hidden; transform:translateY(10px); transition:all 0.25s ease; }
.fab-top.show { opacity:1; visibility:visible; transform:translateY(0); }
.fab-top:hover { background:var(--primary); }
.fab-top svg { width:22px; height:22px; stroke:var(--primary); stroke-width:2.4; fill:none; stroke-linecap:round; stroke-linejoin:round; transition:stroke 0.15s; }
.fab-top:hover svg { stroke:#fff; }

@media(max-width:768px) {
    .main { padding:1rem; }
    .log-panel { padding:1rem; }
    .filter-bar { gap:1rem; }
    .f-field { min-width:120px; }
}
</style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<button type="button" class="fab-top" id="fabTop" onclick="scrollToTop()" aria-label="Back to top" title="Back to top">
    <svg viewBox="0 0 24 24"><path d="M5 15l7-7 7 7"/></svg>
</button>

<main class="main">

    <div class="topbar">
        <a href="Admin_Dashboard.php" class="btn-home" title="Back to Dashboard">
            <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Home
        </a>

        <div class="topbar-actions">
            <div class="user-menu" id="userMenu" onclick="toggleUserDropdown(event)">
                <div class="user-avatar"><?php echo $user_initials; ?></div>
                <span class="user-name"><?php echo htmlspecialchars($user_firstname); ?></span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:2px;opacity:0.5;">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>

                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-avatar"><?php echo $user_initials; ?></div>
                        <div class="user-dropdown-info">
                            <div class="user-dropdown-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                            <div class="user-dropdown-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                    </div>
                    <div class="user-dropdown-divider"></div>
                    <a href="../../includes/logout.php" class="user-dropdown-item user-dropdown-logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="log-panel">

        <h1 class="log-title">Reports</h1>

        <!-- Document request summary -->
        <div class="summary-strip" id="reportsTable">
            <span class="sum-chip p">Pending <b><?php echo $count_pending; ?></b></span>
            <span class="sum-chip i">Processing <b><?php echo $count_processing; ?></b></span>
            <span class="sum-chip i">Ready for Pickup <b><?php echo $count_ready; ?></b></span>
            <span class="sum-chip o">Completed <b><?php echo $count_completed; ?></b></span>
            <span class="sum-chip r">Rejected <b><?php echo $count_rejected; ?></b></span>
            <span class="sum-chip t">Total Requests <b><?php echo $count_total; ?></b></span>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <div class="f-field">
                <label>Date Registered</label>
                <div class="f-date">
                    <input type="date" id="fDateFrom" onchange="applyFilters()">
                    <span class="sep">–</span>
                    <input type="date" id="fDateTo" onchange="applyFilters()">
                </div>
            </div>

            <div class="f-field">
                <label>College</label>
                <select id="fCollege" onchange="applyFilters()">
                    <option value="">All Colleges</option>
                    <?php foreach ($college_groups as $g): ?>
                        <option value="<?php echo htmlspecialchars($g['abbr']); ?>">
                            <?php echo $g['abbr'] === 'OTHER' ? 'Unassigned / Other' : htmlspecialchars($g['abbr'] . ' — ' . $g['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="f-field">
                <label>Status</label>
                <select id="fStatus" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="f-field">
                <label>Verified</label>
                <select id="fVerified" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="yes">Verified</option>
                    <option value="no">Not Verified</option>
                </select>
            </div>

            <div class="f-field grow">
                <label>Search</label>
                <input type="text" id="fSearch" placeholder="Name, ID, email, course..." oninput="applyFilters()">
            </div>

            <button type="button" class="btn-clear" onclick="clearFilters()">Clear filters</button>
        </div>

        <!-- Table, categorized per college -->
        <div class="log-table-wrap">
            <table class="log-table" id="studentsReportTable">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                    </tr>
                </thead>

                <?php foreach ($college_groups as $key => $g):
                    if ($key === 'OTHER' && empty($g['students'])) continue; ?>
                <tbody class="college-group" data-group="<?php echo htmlspecialchars($key); ?>" data-name="<?php echo htmlspecialchars($g['name']); ?>">
                    <tr class="group-head">
                        <td colspan="10">
                            <div class="gh" onclick="toggleGroup(this)">
                                <svg class="chev" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                <span class="abbr"><?php echo $key === 'OTHER' ? 'N/A' : htmlspecialchars($key); ?></span>
                                <span class="cname"><?php echo htmlspecialchars($g['name']); ?></span>
                                <span class="gcount"><span class="gc-num"><?php echo count($g['students']); ?></span> student<?php echo count($g['students']) === 1 ? '' : 's'; ?></span>
                            </div>
                        </td>
                    </tr>

                    <?php foreach ($g['students'] as $s):
                        $p = explode(' ', trim($s['full_name'] ?? 'U'));
                        $ini = count($p) >= 2
                            ? strtoupper(substr($p[0],0,1) . substr($p[1],0,1))
                            : strtoupper(substr($s['full_name'] ?? 'U', 0, 2));

                        $regDate    = !empty($s['created_at']) ? date('Y-m-d', strtotime($s['created_at'])) : '';
                        $regText    = !empty($s['created_at']) ? date('M d, Y', strtotime($s['created_at'])) : '—';
                        $loginText  = !empty($s['last_login']) ? date('M d, Y g:i A', strtotime($s['last_login'])) : 'Never';
                        $birthText  = !empty($s['birthdate']) ? date('M d, Y', strtotime($s['birthdate'])) : '—';
                        $isActive   = !empty($s['is_active']);
                        $isVerified = !empty($s['is_verified']);

                        $exportRow = [
                            $s['student_id'] ?? '—',
                            $s['full_name'] ?? '—',
                            $s['email'] ?? '—',
                            $s['mobile'] ?? '—',
                            $s['course'] ?? '—',
                            $s['year_level'] ?? '—',
                            $s['program'] ?? '—',
                            $s['graduation_year'] ?? '—',
                            $birthText,
                            $isActive ? 'Active' : 'Inactive',
                            $isVerified ? 'Yes' : 'No',
                            $regText,
                            $loginText,
                        ];
                        $searchBlob = strtolower(implode(' ', [
                            $s['student_id'] ?? '', $s['full_name'] ?? '', $s['email'] ?? '',
                            $s['course'] ?? '', $s['program'] ?? '', $s['mobile'] ?? '', $s['username'] ?? ''
                        ]));
                    ?>
                    <tr class="stu-row"
                        data-date="<?php echo $regDate; ?>"
                        data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>"
                        data-verified="<?php echo $isVerified ? 'yes' : 'no'; ?>"
                        data-search="<?php echo htmlspecialchars($searchBlob); ?>"
                        data-export="<?php echo htmlspecialchars(json_encode($exportRow, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">
                        <td><strong><?php echo htmlspecialchars($s['student_id'] ?? '—'); ?></strong></td>
                        <td>
                            <div class="name-cell">
                                <?php if (!empty($s['profile_picture']) && file_exists(__DIR__ . '/../../uploads/profile_pictures/' . $s['profile_picture'])): ?>
                                    <img class="avatar-img" src="../../uploads/profile_pictures/<?php echo htmlspecialchars($s['profile_picture']); ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar-sm"><?php echo $ini; ?></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($s['full_name'] ?? '—'); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($s['email'] ?? '—'); ?></td>
                        <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($s['course'] ?? ''); ?>"><?php echo htmlspecialchars($s['course'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($s['year_level'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($s['mobile'] ?? '—'); ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="status-badge completed">Active</span>
                            <?php else: ?>
                                <span class="status-badge rejected">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isVerified): ?>
                                <span class="status-badge completed">Yes</span>
                            <?php else: ?>
                                <span class="status-badge pending">No</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $regText; ?></td>
                        <td><?php echo $loginText; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php endforeach; ?>
            </table>
            <div class="empty-row" id="emptyRow">No students match your filters.</div>
        </div>

        <div class="log-foot">
            <div class="showing">Showing <b id="shownCount"><?php echo count($all_students); ?></b> of <b><?php echo count($all_students); ?></b> registered student<?php echo count($all_students) === 1 ? '' : 's'; ?></div>

            <div class="report-actions">
                <button class="btn-export btn-csv" onclick="exportCSV()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export CSV
                </button>
                <button class="btn-export btn-pdf" onclick="exportPDF()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Print / Save as PDF
                </button>
            </div>
        </div>

    </div>

</main>

<script>

function toggleUserDropdown(event) {
    event.stopPropagation();
    const menu = document.getElementById('userMenu');
    if (menu) menu.classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    if (menu && !menu.contains(e.target)) menu.classList.remove('open');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const menu = document.getElementById('userMenu');
        if (menu) menu.classList.remove('open');
    }
});

(function() {
    const fabTop = document.getElementById('fabTop');
    document.body.addEventListener('scroll', function() {
        fabTop.classList.toggle('show', document.body.scrollTop > 250);
    });
})();
function scrollToTop() { document.body.scrollTo({ top: 0, behavior: 'smooth' }); }

function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<div>' + message + '</div>';
    container.appendChild(toast);
    setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 300); }, 3500);
}

// ============================================
// COLLAPSE / FILTERS
// ============================================
function toggleGroup(el) {
    const tbody = el.closest('tbody');
    if (tbody) tbody.classList.toggle('collapsed');
}

function applyFilters() {
    const from     = document.getElementById('fDateFrom').value;
    const to       = document.getElementById('fDateTo').value;
    const college  = document.getElementById('fCollege').value;
    const status   = document.getElementById('fStatus').value;
    const verified = document.getElementById('fVerified').value;
    const q        = document.getElementById('fSearch').value.trim().toLowerCase();

    const filtersActive = !!(from || to || college || status || verified || q);
    let totalShown = 0;

    document.querySelectorAll('tbody.college-group').forEach(function(group) {
        let visible = 0;
        const groupMatch = !college || group.dataset.group === college;

        group.querySelectorAll('tr.stu-row').forEach(function(row) {
            let ok = groupMatch;
            if (ok && from && (!row.dataset.date || row.dataset.date < from)) ok = false;
            if (ok && to   && (!row.dataset.date || row.dataset.date > to))   ok = false;
            if (ok && status   && row.dataset.status   !== status)   ok = false;
            if (ok && verified && row.dataset.verified !== verified) ok = false;
            if (ok && q && row.dataset.search.indexOf(q) === -1) ok = false;

            row.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });

        const num = group.querySelector('.gc-num');
        if (num) {
            num.textContent = visible;
            num.parentElement.lastChild.textContent = ' student' + (visible === 1 ? '' : 's');
        }

        // Itago lang ang college na walang match KAPAG may aktibong filter.
        // Kapag walang filter, ipakita lahat (kasama ang walang student).
        group.style.display = (filtersActive && visible === 0) ? 'none' : '';
        totalShown += visible;
    });

    document.getElementById('shownCount').textContent = totalShown;
    document.getElementById('emptyRow').style.display = totalShown === 0 ? 'block' : 'none';
}

function clearFilters() {
    ['fDateFrom','fDateTo','fSearch'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    ['fCollege','fStatus','fVerified'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = 0;
    });

    // Buksan ulit ang lahat ng naka-collapse na college
    document.querySelectorAll('tbody.college-group').forEach(function(group) {
        group.classList.remove('collapsed');
    });

    applyFilters();

    const wrap = document.querySelector('.log-table-wrap');
    if (wrap) wrap.scrollTop = 0;
}

// Collect currently visible rows, grouped per college
function collectVisibleGroups() {
    const result = [];
    document.querySelectorAll('tbody.college-group').forEach(function(group) {
        if (group.style.display === 'none') return;
        const rows = [];
        group.querySelectorAll('tr.stu-row').forEach(function(row) {
            if (row.style.display === 'none') return;
            try {
                const data = JSON.parse(row.dataset.export);
                rows.push(data.map(function(v) {
                    v = String(v === null || v === undefined ? '' : v).replace(/\s+/g, ' ').trim();
                    return (v === '' || v === '—') ? 'N/A' : v;
                }));
            } catch (e) {}
        });
        if (rows.length) result.push({ abbr: group.dataset.group, name: group.dataset.name, rows: rows });
    });
    return result;
}

const EXPORT_HEADERS = [
    'Student ID', 'Full Name', 'Email Address', 'Mobile No.', 'Course', 'Year Level',
    'Program', 'Grad. Year', 'Birthdate', 'Status', 'Verified', 'Date Registered', 'Last Login'
];

// ============================================
// EXPORT CSV (grouped per college)
// ============================================
function exportCSV() {
    const groups = collectVisibleGroups();
    const totalStudents = groups.reduce((n, g) => n + g.rows.length, 0);

    const csvRows = [];
    const divider = '════════════════════════════════════════════════════════════════';
    const subDiv  = '────────────────────────────────────────────────────────────────';

    csvRows.push([divider]);
    csvRows.push(['UNIVERSITY OF MAKATI']);
    csvRows.push(['REGISTRAR\'S OFFICE']);
    csvRows.push(['COMPREHENSIVE REPORT']);
    csvRows.push([divider]);
    csvRows.push(['Generated:', new Date().toLocaleString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
    })]);
    csvRows.push(['Generated by:', '<?php echo htmlspecialchars($user_fullname); ?>']);
    csvRows.push([divider]);
    csvRows.push([]);

    csvRows.push(['I. DOCUMENT REQUEST SUMMARY']);
    csvRows.push([subDiv]);
    csvRows.push(['Request Status', 'Number of Requests']);
    csvRows.push([subDiv]);
    csvRows.push(['Pending',          '<?php echo $count_pending; ?>']);
    csvRows.push(['Processing',       '<?php echo $count_processing; ?>']);
    csvRows.push(['Ready for Pickup', '<?php echo $count_ready; ?>']);
    csvRows.push(['Completed',        '<?php echo $count_completed; ?>']);
    csvRows.push(['Rejected',         '<?php echo $count_rejected; ?>']);
    csvRows.push([subDiv]);
    csvRows.push(['TOTAL', '<?php echo $count_total; ?>']);
    csvRows.push([subDiv]);
    csvRows.push([]);

    if (<?php echo $count_total; ?> === 0) {
        csvRows.push(['Note: No document requests were recorded as of the date of this report.']);
        csvRows.push([]);
    }

    csvRows.push([divider]);
    csvRows.push(['II. STUDENT MASTER LIST (PER COLLEGE)']);
    csvRows.push(['Total Students in this report: ' + totalStudents]);
    csvRows.push([divider]);
    csvRows.push([]);

    groups.forEach(function(g) {
        csvRows.push([g.name + (g.abbr === 'OTHER' ? '' : ' (' + g.abbr + ')') + ' — ' + g.rows.length + ' student' + (g.rows.length === 1 ? '' : 's')]);
        csvRows.push(EXPORT_HEADERS);
        g.rows.forEach(function(r) { csvRows.push(r); });
        csvRows.push([subDiv]);
        csvRows.push([]);
    });

    csvRows.push([divider]);
    csvRows.push([]);
    csvRows.push(['© ' + new Date().getFullYear() + ' University of Makati · Registrar\'s Office · All rights reserved.']);
    csvRows.push(['This is a system-generated report. No signature required.']);

    const csvContent = csvRows.map(function(row) {
        return row.map(function(cell) {
            return '"' + String(cell).replace(/"/g, '""') + '"';
        }).join(',');
    }).join('\n');

    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'UMak_Registrar_Report_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showToast('success', '📊 Report downloaded!');
}

// ============================================
// EXPORT PDF (grouped per college — Landscape A4)
// ============================================
function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function exportPDF() {
    const printWindow = window.open('', '_blank', 'width=1100,height=750');
    const groups = collectVisibleGroups();
    const totalStudents = groups.reduce((n, g) => n + g.rows.length, 0);
    const totalReq = <?php echo $count_total; ?>;

    let sectionsHtml = '';
    if (groups.length === 0) {
        sectionsHtml = '<div class="note-line">No students match the selected filters.</div>';
    } else {
        groups.forEach(function(g) {
            let rowsHtml = '';
            g.rows.forEach(function(r, idx) {
                rowsHtml += '<tr style="background:' + (idx % 2 === 0 ? '#ffffff' : '#f9fafb') + ';">';
                r.forEach(function(cell) { rowsHtml += '<td>' + esc(cell) + '</td>'; });
                rowsHtml += '</tr>';
            });

            sectionsHtml += `
                <div class="college-block">
                    <div class="college-title">
                        <span>${esc(g.name)}${g.abbr === 'OTHER' ? '' : ' (' + esc(g.abbr) + ')'}</span>
                        <span class="count-note">${g.rows.length} student${g.rows.length === 1 ? '' : 's'}</span>
                    </div>
                    <table class="student-table">
                        <thead><tr>${EXPORT_HEADERS.map(h => '<th>' + esc(h) + '</th>').join('')}</tr></thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>`;
        });
    }

    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>UMak Registrar Report - <?php echo date('M d, Y'); ?></title>
            <style>
                @page { size: A4 landscape; margin: 12mm 10mm; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Times New Roman', Georgia, serif; padding: 20px; color: #1a1a1a; font-size: 11px; line-height: 1.4; padding-top: 60px; }

                .header { text-align: center; border-bottom: 3px double #003366; padding-bottom: 16px; margin-bottom: 24px; }
                .header .univ { font-size: 18px; font-weight: 700; color: #003366; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 4px; }
                .header .office { font-size: 13px; font-weight: 600; color: #333; letter-spacing: 0.04em; margin-bottom: 2px; }
                .header .report-title { font-size: 15px; font-weight: 700; color: #003366; text-transform: uppercase; letter-spacing: 0.12em; margin-top: 8px; padding-top: 8px; border-top: 1px solid #cfd5dd; }
                .header .meta { font-size: 10px; color: #555; margin-top: 10px; display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; }
                .header .meta strong { color: #333; }

                .section { margin-bottom: 28px; }
                .section-title { font-size: 13px; font-weight: 700; color: #003366; text-transform: uppercase; letter-spacing: 0.06em; padding-bottom: 6px; border-bottom: 2px solid #FFD700; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: baseline; }
                .section-title .count-note { font-size: 10px; font-weight: 400; color: #666; text-transform: none; letter-spacing: 0; }

                .summary-table { width: 60%; border-collapse: collapse; font-size: 11px; margin: 0 auto 12px; }
                .summary-table th { background: #003366; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; padding: 8px 16px; border: 1px solid #003366; text-align: left; }
                .summary-table th:last-child { text-align: right; }
                .summary-table td { padding: 8px 16px; border: 1px solid #cfd5dd; }
                .summary-table td:last-child { text-align: right; font-weight: 700; font-family: 'Courier New', monospace; }
                .summary-table tr.total-row { background: #f0f4f9; font-weight: 700; border-top: 2px solid #003366; }
                .summary-table tr.total-row td { color: #003366; font-weight: 700; }
                .note-line { text-align: center; font-style: italic; font-size: 10px; color: #666; margin-top: 8px; }

                .college-block { margin-bottom: 22px; page-break-inside: auto; }
                .college-title { background: #f0f4f9; border-left: 5px solid #FFD700; color: #003366; font-weight: 700; font-size: 11px; padding: 6px 10px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: baseline; }
                .college-title .count-note { font-size: 9px; font-weight: 400; color: #555; }

                .student-table { width: 100%; border-collapse: collapse; font-size: 7.5px; table-layout: auto; }
                .student-table th { background: #003366; color: #fff; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; font-size: 6.5px; padding: 5px 3px; border: 1px solid #003366; text-align: left; vertical-align: middle; line-height: 1.2; }
                .student-table td { padding: 4px 3px; border: 1px solid #d1d9e0; vertical-align: top; word-wrap: break-word; word-break: break-word; line-height: 1.3; }

                .footer { margin-top: 32px; padding-top: 16px; border-top: 2px solid #003366; text-align: center; font-size: 9px; color: #666; line-height: 1.6; }
                .footer .footer-brand { font-weight: 700; color: #003366; letter-spacing: 0.05em; }

                @media print {
                    body { padding: 0; }
                    .no-print { display: none !important; }
                    .student-table tr { page-break-inside: avoid; }
                    .student-table thead { display: table-header-group; }
                    .college-title { page-break-after: avoid; }
                }

                .preview-toolbar { position: fixed; top: 0; left: 0; right: 0; background: #003366; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 9999; font-family: 'Segoe UI', Arial, sans-serif; }
                .preview-toolbar span { font-size: 12px; }
                .preview-toolbar .btns { display: flex; gap: 10px; }
                .preview-toolbar button { background: #FFD700; color: #003366; border: none; padding: 6px 16px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; }
                .preview-toolbar button:hover { background: #e6c200; }
                @media print { .preview-toolbar { display: none; } }
            </style>
        </head>
        <body>

            <div class="preview-toolbar no-print">
                <span>📄 <strong>Report Preview</strong> — Landscape A4</span>
                <div class="btns">
                    <button onclick="window.print()">🖨️ Print / Save as PDF</button>
                    <button onclick="window.close()">✕ Close</button>
                </div>
            </div>

            <div class="header">
                <div class="univ">University of Makati</div>
                <div class="office">Registrar's Office</div>
                <div class="report-title">Comprehensive Report</div>
                <div class="meta">
                    <span><strong>Generated:</strong> ${new Date().toLocaleString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
                    })}</span>
                    <span><strong>Generated by:</strong> <?php echo htmlspecialchars($user_fullname); ?></span>
                </div>
            </div>

            <div class="section">
                <div class="section-title"><span>I. Document Request Summary</span></div>
                <table class="summary-table">
                    <thead><tr><th>Request Status</th><th>Number of Requests</th></tr></thead>
                    <tbody>
                        <tr><td>Pending</td><td><?php echo $count_pending; ?></td></tr>
                        <tr><td>Processing</td><td><?php echo $count_processing; ?></td></tr>
                        <tr><td>Ready for Pickup</td><td><?php echo $count_ready; ?></td></tr>
                        <tr><td>Completed</td><td><?php echo $count_completed; ?></td></tr>
                        <tr><td>Rejected</td><td><?php echo $count_rejected; ?></td></tr>
                        <tr class="total-row"><td>Total</td><td><?php echo $count_total; ?></td></tr>
                    </tbody>
                </table>
                ${totalReq === 0 ? '<div class="note-line">Note: No document requests were recorded as of the date of this report.</div>' : ''}
            </div>

            <div class="section">
                <div class="section-title">
                    <span>II. Student Master List (Per College)</span>
                    <span class="count-note">(${totalStudents} student${totalStudents === 1 ? '' : 's'})</span>
                </div>
                ${sectionsHtml}
            </div>

            <div class="footer">
                <div class="footer-brand">© <?php echo date('Y'); ?> University of Makati · Registrar's Office</div>
                <div>This is a system-generated report. No signature required.</div>
            </div>

        </body>
        </html>
    `;

    printWindow.document.write(html);
    printWindow.document.close();
    showToast('info', '🖨️ Report preview opened — Click Print or Save as PDF');
}

</script>
</body>
</html>