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
$pending_requests = []; $processing_requests = [];

$services = [
    ['key'=>'a','letter'=>'A','name'=>'Student Information','desc'=>'Personal, academic, and status records','codes'=>['SPR','SAP','SIR','SID','PCE','CUR','AHX','PSR','TRC','SSR','YLR','MMR','REG','IRR','PRB','AWG','SUS','LOA','WTH','DRP','TRF','GRD','DSM','RTN']],
    ['key'=>'b','letter'=>'B','name'=>'Academic Performance','desc'=>'Grades, GWA, and academic standing','codes'=>['GPS','FGR','MGR','SGR','GCR','GCM','INC','FSR','REX','GRS','CLR','GWA','AST','DLH']],
    ['key'=>'c','letter'=>'C','name'=>'Enrollment','desc'=>'Registration, schedules, and enrollment transactions','codes'=>['ERR','COR','EAS','SEN','ADR','CSR','CER','OUR','LER','ECW','LAR','RTS']],
    ['key'=>'d','letter'=>'D','name'=>'Certifications & Graduation','desc'=>'Transcripts, certifications, and graduation records','codes'=>['TOR','CTC','COE','CRG','COG','CCP','CUE','GMC','CAS','CGW','CNR','AUT','GAP','GEV','GCL','CGL','GRL','GRC','GST','DEG','DOG','LAH','DIP','DRR','CMR']],
    ['key'=>'e','letter'=>'E','name'=>'Transfer & Curriculum','desc'=>'Transfer, admission, subjects, and curriculum records','codes'=>['ADC','PAR','TSR','TEV','CRS','VAS','APR','TFE','TCD','SWR','CAT','SCI','CDC','CDS','UCH','PRQ','CCH','CVR','APG','PCO','SEQ']],
    ['key'=>'f','letter'=>'F','name'=>'Requests & Inquiries','desc'=>'Common registrar requests and concerns','codes'=>['TRQ','CRQ','GIQ','GCQ','EVQ','DPQ','AUQ','ARC','SCQ','GEQ','TCQ','DFQ','RCC','GRI']],
];
$code_to_service = [];
foreach ($services as $svc) { foreach ($svc['codes'] as $c) { $code_to_service[$c] = $svc['key']; } }
$service_requests = [];
$service_stats = [];
foreach ($services as $svc) {
    $service_requests[$svc['key']] = [];
    $service_stats[$svc['key']] = ['total'=>0,'pending'=>0,'processing'=>0,'ready'=>0,'completed'=>0];
}

$all_students = [];
$all_users = [];
$all_requests = [];
$recent_activities = [];
$all_announcements = [];

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

        $stmt = $pdo->query("SELECT r.id, r.user_id, u.student_id, r.request_type, r.details, r.status, r.created_at, u.full_name as student_name FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE UPPER(r.status) = 'PENDING' ORDER BY r.created_at ASC LIMIT 10");
        $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT r.id, r.request_type, r.details, r.status, r.ticket_number, r.created_at, u.full_name as student_name FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE UPPER(r.status) IN ('PROCESSING','ACCEPTED','READY') ORDER BY r.created_at ASC LIMIT 10");
        $processing_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT id, username, email, full_name, student_id, mobile, birthdate, program, college, course, year_level, graduation_year, profile_picture, is_active, is_verified, created_at, last_login FROM users WHERE LOWER(TRIM(role)) <> 'admin' ORDER BY id ASC");
        $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT id, username, email, full_name, student_id, role, is_active, last_login FROM users ORDER BY id ASC");
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT r.id, r.request_type, r.status, r.ticket_number, r.created_at, u.full_name as student_name FROM requests r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 50");
        $all_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT activity_type, description, created_at FROM user_activities ORDER BY created_at DESC LIMIT 20");
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { error_log($e->getMessage()); }

    try {
        $stmt = $pdo->query("SELECT r.id, r.request_type, r.details, r.status, r.ticket_number, r.created_at, u.full_name as student_name FROM requests r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 500");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $code_to_service[$row['request_type']] ?? null;
            if (!$key) continue;
            $service_requests[$key][] = $row;
            $service_stats[$key]['total']++;
            $st = strtoupper($row['status']);
            if ($st === 'PENDING') $service_stats[$key]['pending']++;
            elseif (in_array($st, ['PROCESSING','ACCEPTED'])) $service_stats[$key]['processing']++;
            elseif ($st === 'READY') $service_stats[$key]['ready']++;
            elseif (in_array($st, ['COMPLETED','CLAIMED'])) $service_stats[$key]['completed']++;
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }

    try {
        $stmt = $pdo->query("SELECT id, title, content AS body, priority, created_at FROM announcements WHERE is_active = TRUE ORDER BY created_at DESC LIMIT 100");
        $all_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$count_total = $count_pending + $count_processing + $count_ready + $count_completed + $count_rejected;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UMak · Admin Dashboard</title>
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

.sidebar { position:fixed; top:0; left:0; bottom:0; width:240px; background:linear-gradient(180deg,#002244 0%,#003366 100%); color:rgba(255,255,255,0.75); padding:1.25rem 0; overflow-y:auto; z-index:100; }
.sidebar-brand { display:flex; align-items:center; gap:10px; padding:0 1.5rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.10); margin-bottom:0.75rem; }
.sidebar-brand-icon { width:32px; height:32px; background:var(--gold); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--primary); font-weight:700; font-size:0.9rem; font-family:'Libre Baskerville', serif; }
.sidebar-brand-text { font-size:0.92rem; font-weight:700; color:#fff; }
.sidebar-section { font-size:0.6rem; font-weight:700; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:0.1em; padding:1rem 1.5rem 0.4rem; }
.sidebar-link { display:flex; align-items:center; gap:12px; padding:0.62rem 1.5rem; font-size:0.8rem; font-weight:500; color:rgba(255,255,255,0.78); text-decoration:none; transition:all 0.15s; border-left:3px solid transparent; cursor:pointer; }
.sidebar-link:hover { background:rgba(255,255,255,0.06); color:#fff; }
.sidebar-link.active { background:rgba(255,215,0,0.12); color:var(--gold); border-left-color:var(--gold); font-weight:600; }
.sidebar-link svg { width:18px; height:18px; stroke:currentColor; stroke-width:2; fill:none; flex-shrink:0; }
.sidebar-link .svc-letter { width:18px; height:18px; border-radius:5px; background:rgba(255,255,255,0.12); color:#fff; font-size:0.62rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-family:'Libre Baskerville', serif; }
.sidebar-link:hover .svc-letter { background:var(--gold); color:var(--primary); }
.sidebar-link .svc-count { margin-left:auto; font-size:0.62rem; font-weight:700; background:var(--gold); color:var(--primary); padding:1px 7px; border-radius:999px; }

.main {
    position:relative; margin-left:240px; min-height:100vh; padding:1.5rem 2rem 3rem;
    background:
        radial-gradient(900px 260px at 18% 0%, rgba(120,170,225,0.55) 0%, rgba(120,170,225,0) 70%),
        radial-gradient(700px 240px at 85% 0%, rgba(255,215,0,0.22) 0%, rgba(255,215,0,0) 70%),
        linear-gradient(180deg,#cddff2 0%,#e3edf8 170px,#f4f7fb 380px,#ffffff 100%);
    background-repeat:no-repeat;
}

.topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }
.search-box { flex:1; max-width:400px; position:relative; }
.search-box input { width:100%; padding:0.6rem 1rem 0.6rem 2.5rem; background:#fff; border:1px solid var(--border-md); border-radius:8px; font-size:0.8rem; color:var(--text-1); transition:all 0.15s; }
.search-box input:focus { outline:none; border-color:var(--primary-light); box-shadow:0 0 0 3px rgba(0,51,102,0.10); }
.search-box .search-icon { position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:var(--text-3); stroke-width:2; fill:none; }
.topbar-actions { display:flex; align-items:center; gap:0.75rem; }
.icon-action { width:38px; height:38px; display:flex; align-items:center; justify-content:center; background:#fff; border:1px solid var(--border-md); border-radius:8px; cursor:pointer; position:relative; transition:all 0.15s; }
.icon-action:hover { background:var(--surface-2); }
.icon-action svg { width:18px; height:18px; stroke:var(--text-2); stroke-width:2; fill:none; }
.icon-action .badge { position:absolute; top:-2px; right:-2px; min-width:16px; height:16px; background:#ef4444; color:#fff; border-radius:8px; font-size:0.55rem; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 4px; }
.user-menu { display:flex; align-items:center; gap:10px; padding:0.35rem 0.75rem 0.35rem 0.35rem; background:#fff; border:1px solid var(--border-md); border-radius:8px; cursor:pointer; transition:all 0.15s; }
.user-menu:hover { background:var(--surface-2); }
.user-avatar { width:30px; height:30px; background:var(--gold); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:0.68rem; font-weight:700; }
.user-name { font-size:0.75rem; font-weight:600; color:var(--text-1); }
.user-menu { position:relative; user-select:none; }
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

.page-header { margin-bottom:1.5rem; padding-top:0.5rem; }
.page-header h1 { font-family:'Inter', system-ui, sans-serif; font-size:2rem; font-weight:700; color:var(--primary); margin-bottom:0.25rem; letter-spacing:-0.015em; }
.page-header p { font-size:0.85rem; color:var(--text-2); }

.block-head { display:flex; align-items:center; justify-content:space-between; margin:0 0 0.75rem; }
.block-title { font-size:1.05rem; font-weight:700; color:var(--text-1); display:flex; align-items:center; gap:8px; font-family:'Libre Baskerville', Georgia, serif; }
.block-title::before { content:''; width:4px; height:18px; background:var(--gold); border-radius:2px; }
.block-sub { font-size:0.76rem; color:var(--text-3); }

.services-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.75rem; }
.service-card { background:#fff; border:1px solid var(--border); border-radius:10px; padding:1rem 1.1rem; box-shadow:var(--shadow-sm); text-align:left; cursor:pointer; transition:all 0.15s; display:flex; flex-direction:column; gap:0.75rem; color:inherit; }
.service-card:hover { transform:translateY(-2px); box-shadow:0 8px 18px rgba(16,24,40,0.10); border-color:var(--border-md); }
.service-top { display:flex; align-items:center; gap:12px; }
.service-letter { width:36px; height:36px; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:700; border-radius:9px; flex-shrink:0; font-family:'Libre Baskerville', serif; }
.service-name { font-size:0.9rem; font-weight:600; color:var(--text-1); line-height:1.25; }
.service-desc { font-size:0.74rem; color:var(--text-3); margin-top:2px; line-height:1.35; }
.service-stats { display:flex; gap:0.5rem; flex-wrap:wrap; padding-top:0.7rem; border-top:1px solid var(--border); }
.svc-pill { font-size:0.68rem; font-weight:600; padding:3px 10px; border-radius:999px; background:var(--surface-3); color:var(--text-2); }
.svc-pill.p { background:var(--st-pending-bg); color:var(--st-pending-fg); }
.svc-pill.i { background:var(--st-info-bg); color:var(--st-info-fg); }
.svc-pill.o { background:var(--st-ok-bg); color:var(--st-ok-fg); }

.section-card { background:#fff; border:1px solid var(--border); border-radius:10px; margin-bottom:1.5rem; overflow:hidden; box-shadow:var(--shadow-sm); }
.section-card-header { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; border-bottom:1px solid var(--border); }
.section-card-title { font-size:0.92rem; font-weight:700; color:var(--text-1); display:flex; align-items:center; gap:8px; font-family:'Libre Baskerville', Georgia, serif; }
.section-card-title::before { content:''; width:4px; height:16px; background:var(--gold); border-radius:2px; }
.table-wrap { overflow-x:auto; }

.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { text-align:left; font-size:0.66rem; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:0.07em; padding:0.75rem 1.25rem; background:var(--surface-2); border-bottom:1px solid var(--border); white-space:nowrap; }
.data-table tbody td { padding:0.85rem 1.25rem; font-size:0.78rem; color:var(--text-1); border-bottom:1px solid var(--border); vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover { background:var(--surface-2); }
.code-tag { font-size:0.62rem; font-family:ui-monospace,'Courier New',monospace; color:var(--text-3); font-weight:600; letter-spacing:0.05em; padding:1px 6px; background:var(--surface-3); border-radius:4px; margin-left:6px; }
.cell-sub { display:block; font-size:0.7rem; color:var(--text-3); max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px; }

.status-badge { display:inline-block; font-size:0.62rem; font-weight:700; padding:3px 11px; border-radius:999px; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap; background:var(--surface-3); color:var(--text-2); }
.status-badge.pending { background:var(--st-pending-bg); color:var(--st-pending-fg); }
.status-badge.processing, .status-badge.accepted, .status-badge.verified { background:var(--st-info-bg); color:var(--st-info-fg); }
.status-badge.ready, .status-badge.completed { background:var(--st-ok-bg); color:var(--st-ok-fg); }
.status-badge.claimed { background:var(--surface-3); color:var(--text-2); }
.status-badge.rejected { background:var(--st-bad-bg); color:var(--st-bad-fg); }
.status-badge.admin { background:var(--st-info-bg); color:var(--st-info-fg); }
.status-badge.student { background:var(--st-ok-bg); color:var(--st-ok-fg); }
.status-badge.urgent { background:var(--st-bad-bg); color:var(--st-bad-fg); }
.status-badge.normal { background:var(--st-pending-bg); color:var(--st-pending-fg); }
.status-badge.info { background:var(--st-info-bg); color:var(--st-info-fg); }

.action-btns { display:flex; gap:6px; }
.btn-icon { display:inline-flex; align-items:center; gap:4px; padding:5px 10px; font-size:0.68rem; font-weight:600; border-radius:6px; cursor:pointer; text-decoration:none; border:1px solid transparent; transition:all 0.15s; white-space:nowrap; }
.btn-icon:disabled { opacity:0.5; cursor:not-allowed; }
.btn-icon.accept { background:#10b981; color:#fff; }
.btn-icon.accept:hover:not(:disabled) { background:#059669; }
.btn-icon.reject { background:#fff; color:#dc2626; border-color:#fecaca; }
.btn-icon.reject:hover:not(:disabled) { background:#fef2f2; }
.btn-icon.view { background:var(--surface-3); color:var(--text-1); }
.btn-icon.view:hover { background:var(--border-md); }
.btn-icon.ready { background:var(--primary); color:#fff; }
.btn-icon.ready:hover:not(:disabled) { background:var(--primary-dark); }
.btn-icon.email { background:#8b5cf6; color:#fff; }
.btn-icon.email:hover:not(:disabled) { background:#7c3aed; }
.empty-row { text-align:center; padding:2.5rem 1rem; color:var(--text-3); font-size:0.78rem; }

.modal-overlay { position:fixed; inset:0; background:rgba(10,20,40,0.55); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); z-index:9000; display:none; align-items:center; justify-content:center; padding:1.5rem; }
.modal-overlay.open { display:flex; animation:modalFadeIn 0.25s ease; }
@keyframes modalFadeIn { from { opacity:0; } to { opacity:1; } }
.modal-box { background:#fff; border-radius:14px; max-width:1000px; width:100%; max-height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 60px -12px rgba(0,0,0,0.45); animation:modalSlideUp 0.3s cubic-bezier(0.22,1,0.36,1); }
@keyframes modalSlideUp { from { opacity:0; transform:translateY(20px) scale(0.97); } to { opacity:1; transform:translateY(0) scale(1); } }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:1.15rem 1.5rem; border-bottom:1px solid var(--border); background:var(--surface-2); flex-shrink:0; }
.modal-header h3 { font-size:1rem; font-weight:700; color:var(--text-1); display:flex; align-items:center; gap:10px; }
.modal-header h3 svg { width:20px; height:20px; stroke:var(--primary); stroke-width:2; fill:none; flex-shrink:0; }
.modal-header .svc-badge { width:26px; height:26px; background:var(--primary); color:#fff; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:700; }
.modal-close { width:32px; height:32px; border:none; background:transparent; cursor:pointer; color:var(--text-3); display:flex; align-items:center; justify-content:center; border-radius:6px; transition:all 0.15s; font-size:1.3rem; }
.modal-close:hover { background:var(--surface-3); color:var(--text-1); }
.modal-body { padding:1.5rem; overflow-y:auto; flex:1; }
.modal-body .data-table thead th:first-child, .modal-body .data-table tbody td:first-child { padding-left:0.75rem; }
.modal-empty { text-align:center; padding:3rem 1rem; color:var(--text-3); font-size:0.85rem; }
.modal-sub { font-size:0.78rem; color:var(--text-3); margin-bottom:1rem; }

.ann-form { background:var(--surface-2); border:1px solid var(--border); border-radius:10px; padding:1.1rem 1.25rem 1.25rem; margin-bottom:1.5rem; }
.ann-form-title { font-size:0.85rem; font-weight:700; color:var(--text-1); margin-bottom:0.9rem; display:flex; align-items:center; gap:8px; }
.ann-form-title::before { content:''; width:4px; height:15px; background:var(--gold); border-radius:2px; }
.form-row { margin-bottom:0.85rem; }
.form-row label { display:block; font-size:0.7rem; font-weight:600; color:var(--text-2); margin-bottom:5px; text-transform:uppercase; letter-spacing:0.05em; }
.form-input { width:100%; padding:0.6rem 0.8rem; font-size:0.82rem; color:var(--text-1); background:#fff; border:1px solid var(--border-md); border-radius:8px; transition:all 0.15s; }
.form-input:focus { outline:none; border-color:var(--primary-light); box-shadow:0 0 0 3px rgba(0,51,102,0.10); }
textarea.form-input { resize:vertical; min-height:96px; line-height:1.5; }
.form-bottom { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.form-bottom .priority-field { min-width:180px; }
.form-bottom .char-hint { font-size:0.68rem; color:var(--text-3); margin-top:4px; }
.btn-post { display:inline-flex; align-items:center; gap:8px; padding:0.65rem 1.4rem; font-size:0.8rem; font-weight:600; color:#fff; background:var(--primary); border:1px solid var(--primary); border-radius:8px; cursor:pointer; transition:all 0.15s; }
.btn-post:hover:not(:disabled) { background:var(--primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,51,102,0.25); }
.btn-post:disabled { opacity:0.6; cursor:not-allowed; }
.btn-post svg { width:15px; height:15px; stroke:currentColor; stroke-width:2.2; fill:none; }
.ann-title-cell { font-weight:600; }
.ann-title-cell .cell-sub { max-width:380px; font-weight:400; }

.fab-announce { position:fixed; bottom:28px; right:28px; width:58px; height:58px; background:var(--primary); border:2px solid var(--gold); border-radius:14px; box-shadow:0 6px 18px rgba(0,51,102,0.30); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s ease; z-index:150; }
.fab-announce:hover { transform:scale(1.07); background:var(--primary-light); }
.fab-announce svg { width:30px; height:30px; stroke:#fff; stroke-width:1.8; fill:none; stroke-linecap:round; stroke-linejoin:round; }
.fab-announce::after { content:attr(data-label); position:absolute; right:calc(100% + 10px); top:50%; transform:translateY(-50%) translateX(4px); background:var(--text-1); color:#fff; font-size:0.72rem; font-weight:600; padding:6px 12px; border-radius:6px; white-space:nowrap; opacity:0; pointer-events:none; transition:all 0.15s; }
.fab-announce:hover::after { opacity:1; transform:translateY(-50%) translateX(0); }
.fab-top { position:fixed; bottom:98px; right:34px; width:46px; height:46px; background:#fff; border:2px solid var(--primary); border-radius:12px; box-shadow:0 6px 18px rgba(0,51,102,0.18); cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:150; opacity:0; visibility:hidden; transform:translateY(10px); transition:all 0.25s ease; }
.fab-top.show { opacity:1; visibility:visible; transform:translateY(0); }
.fab-top:hover { background:var(--primary); }
.fab-top svg { width:22px; height:22px; stroke:var(--primary); stroke-width:2.4; fill:none; stroke-linecap:round; stroke-linejoin:round; transition:stroke 0.15s; }
.fab-top:hover svg { stroke:#fff; }
@media(max-width:768px) { .fab-announce { right:20px; bottom:20px; } .fab-top { right:26px; bottom:90px; } }

.report-actions { display:flex; gap:10px; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border); flex-wrap:wrap; }
.btn-export { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; font-size:0.8rem; font-weight:600; border-radius:8px; cursor:pointer; transition:all 0.2s ease; border:1px solid transparent; text-decoration:none; }
.btn-export svg { width:16px; height:16px; flex-shrink:0; }
.btn-export.btn-csv { background:#10b981; color:#fff; border-color:#10b981; }
.btn-export.btn-csv:hover { background:#059669; border-color:#059669; transform:translateY(-1px); box-shadow:0 4px 12px rgba(16,185,129,0.3); }
.btn-export.btn-pdf { background:#dc2626; color:#fff; border-color:#dc2626; }
.btn-export.btn-pdf:hover { background:#b91c1c; border-color:#b91c1c; transform:translateY(-1px); box-shadow:0 4px 12px rgba(220,38,38,0.3); }

.toast-container { position:fixed; top:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:0.5rem; }
.toast { display:flex; align-items:center; gap:10px; padding:0.85rem 1.25rem; background:#fff; border:1px solid var(--border); border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.10); font-size:0.78rem; color:var(--text-1); min-width:280px; max-width:400px; animation:slideIn 0.3s ease; }
.toast.success { border-left:4px solid #10b981; }
.toast.error   { border-left:4px solid #ef4444; }
.toast.info    { border-left:4px solid var(--primary); }
.toast .toast-icon { width:20px; height:20px; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.toast.success .toast-icon { color:#10b981; }
.toast.error   .toast-icon { color:#ef4444; }
.toast.info    .toast-icon { color:var(--primary); }
.toast .toast-icon svg { width:20px; height:20px; stroke:currentColor; stroke-width:2; fill:none; }
@keyframes slideIn  { from { transform:translateX(120%); opacity:0; } to { transform:translateX(0); opacity:1; } }
@keyframes slideOut { from { transform:translateX(0); opacity:1; } to { transform:translateX(120%); opacity:0; } }
.toast.hiding { animation:slideOut 0.3s ease forwards; }

.drawer-backdrop { position: fixed; inset: 0; background: rgba(10,20,40,0.45); z-index: 8500; opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; }
.drawer-backdrop.open { opacity: 1; visibility: visible; }
.request-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: 480px; max-width: 100vw; background: #fff; box-shadow: -8px 0 32px rgba(0,0,0,0.15); z-index: 8600; display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.request-drawer.open { transform: translateX(0); }
.drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 1.15rem 1.4rem; border-bottom: 1px solid var(--border); background: var(--surface-2); flex-shrink: 0; }
.drawer-head h3 { font-size: 1rem; font-weight: 700; color: var(--text-1); line-height: 1.35; margin: 0; }
.drawer-head .drawer-ticket { display: inline-block; font-family: ui-monospace, 'Courier New', monospace; font-size: 0.72rem; font-weight: 700; color: var(--primary); background: var(--accent-soft); padding: 2px 8px; border-radius: 4px; margin-top: 4px; }
.drawer-close { width: 32px; height: 32px; flex-shrink: 0; border: none; background: transparent; color: var(--text-3); cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 1.3rem; transition: background 0.15s; }
.drawer-close:hover { background: var(--surface-3); color: var(--text-1); }
.drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.4rem; }
.drawer-section { margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border); }
.drawer-section:last-child { border-bottom: none; padding-bottom: 0; }
.drawer-section-title { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-3); margin-bottom: 0.75rem; }
.drawer-grid { display: grid; grid-template-columns: 130px 1fr; gap: 10px 12px; font-size: 0.82rem; }
.drawer-label { color: var(--text-3); }
.drawer-value { color: var(--text-1); font-weight: 500; word-break: break-word; }
.drawer-value.mono { font-family: ui-monospace, 'Courier New', monospace; font-size: 0.78rem; }
.drawer-status-badge { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; }
.drawer-status-badge.pending   { background: var(--st-pending-bg); color: var(--st-pending-fg); }
.drawer-status-badge.processing,
.drawer-status-badge.accepted  { background: var(--st-info-bg);    color: var(--st-info-fg); }
.drawer-status-badge.ready,
.drawer-status-badge.completed { background: var(--st-ok-bg);      color: var(--st-ok-fg); }
.drawer-status-badge.rejected  { background: var(--st-bad-bg);     color: var(--st-bad-fg); }
.drawer-timeline { position: relative; padding-left: 22px; }
.drawer-timeline::before { content: ''; position: absolute; left: 5px; top: 8px; bottom: 8px; width: 2px; background: var(--border-md); }
.timeline-item { position: relative; padding-bottom: 1rem; }
.timeline-item:last-child { padding-bottom: 0; }
.timeline-item::before { content: ''; position: absolute; left: -22px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2px solid var(--border-md); z-index: 1; }
.timeline-item.done::before { background: var(--primary); border-color: var(--primary); }
.timeline-item.current::before { background: var(--gold); border-color: var(--gold); box-shadow: 0 0 0 4px rgba(255,215,0,0.25); }
.timeline-item .tl-status { font-size: 0.82rem; font-weight: 600; color: var(--text-1); margin-bottom: 2px; }
.timeline-item .tl-meta { font-size: 0.7rem; color: var(--text-3); }
.drawer-loading, .drawer-error { padding: 3rem 1rem; text-align: center; color: var(--text-3); font-size: 0.85rem; }
.drawer-foot { display: flex; gap: 8px; padding: 1rem 1.4rem; border-top: 1px solid var(--border); background: var(--surface-2); flex-shrink: 0; flex-wrap: wrap; }
.drawer-foot button { flex: 1; min-width: 100px; }

@media(max-width:1100px) { .services-grid { grid-template-columns:repeat(2,1fr); } }
@media(max-width:768px) {
    .sidebar { transform:translateX(-100%); transition:transform 0.3s; }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; padding:1rem; }
    .topbar { flex-wrap:wrap; }
    .search-box { max-width:none; width:100%; }
    .modal-box { max-height:90vh; }
    .modal-body { padding:1rem; }
    .page-header h1 { font-size:1.5rem; }
}
@media(max-width:560px) { .services-grid { grid-template-columns:1fr; } .request-drawer { width:100vw; } .drawer-grid { grid-template-columns:110px 1fr; } }
</style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<div class="drawer-backdrop" id="requestDrawerBackdrop" onclick="closeRequestDrawer()"></div>
<aside class="request-drawer" id="requestDrawer" role="dialog" aria-modal="true">
    <div class="drawer-head">
        <div>
            <h3>Request Details</h3>
            <span class="drawer-ticket" id="drawerTicket">—</span>
        </div>
        <button class="drawer-close" onclick="closeRequestDrawer()" aria-label="Close">&times;</button>
    </div>
    <div class="drawer-body" id="drawerBody">
        <div class="drawer-loading">Loading...</div>
    </div>
    <div class="drawer-foot" id="drawerFoot" style="display:none;"></div>
</aside>

<button type="button" class="fab-announce" onclick="openModal('announcements')" aria-label="Post announcement" title="Post announcement" data-label="Post Announcement">
    <svg viewBox="0 0 30 24">
        <g transform="translate(0,2) scale(0.85)">
            <path d="m3 11 18-5v12L3 14v-3z"/>
            <path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>
        </g>
        <path d="M22.5 7l3-2.2"/>
        <path d="M23.5 12h4.5"/>
        <path d="M22.5 17l3 2.2"/>
    </svg>
</button>

<button type="button" class="fab-top" id="fabTop" onclick="scrollToTop()" aria-label="Back to top" title="Back to top">
    <svg viewBox="0 0 24 24"><path d="M5 15l7-7 7 7"/></svg>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">U</div>
        <div class="sidebar-brand-text">UMak Admin</div>
    </div>

    <a href="Admin_Dashboard.php" class="sidebar-link active">
        <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        Dashboard
    </a>
    <a href="#" class="sidebar-link" onclick="openModal('student-records'); return false;">
        <svg viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
        Student Records
    </a>
    <a href="#" class="sidebar-link" onclick="openModal('enrollment'); return false;">
        <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Enrollment
    </a>
    <a href="#" class="sidebar-link" onclick="openModal('academic-records'); return false;">
        <svg viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        Academic Records
    </a>

    <a href="#" class="sidebar-link" onclick="openModal('document-requests'); return false;">
        <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Document Requests
    </a>
        <a href="reports.php" class="sidebar-link">
        <svg viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Reports
    </a>

    <div class="sidebar-section">Services</div>
    <?php foreach ($services as $svc): ?>
        <a href="#" class="sidebar-link" onclick="openModal('svc-<?php echo $svc['key']; ?>'); return false;">
            <span class="svc-letter"><?php echo $svc['letter']; ?></span>
            <?php echo htmlspecialchars($svc['name']); ?>
            <?php if ($service_stats[$svc['key']]['pending'] > 0): ?>
                <span class="svc-count"><?php echo $service_stats[$svc['key']]['pending']; ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-section">System</div>
    <a href="#" class="sidebar-link" onclick="openModal('user-roles'); return false;">
    <svg viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    User Accounts
</a>
    <a href="#" class="sidebar-link" onclick="openModal('settings'); return false;">
        <svg viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
        Settings
    </a>
    <a href="#" class="sidebar-link" onclick="openModal('activity-log'); return false;">
        <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Activity Log
    </a>
</aside>

<main class="main">

    <div class="topbar">
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search...">
        </div>

        <div class="topbar-actions">
            <button class="icon-action" title="Messages">
                <svg viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </button>
            <button class="icon-action" title="Notifications">
                <svg viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?php if ($count_pending > 0): ?>
                    <span class="badge"><?php echo $count_pending; ?></span>
                <?php endif; ?>
            </button>
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

    <div class="page-header">
        <h1>Admin Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($user_firstname); ?>. Manage student document requests.</p>
    </div>

    <div class="block-head">
        <div class="block-title">Registrar Services</div>
        <div class="block-sub">Click a service to view and manage its requests</div>
    </div>
    <div class="services-grid">
        <?php foreach ($services as $svc): $st = $service_stats[$svc['key']]; ?>
            <button type="button" class="service-card" onclick="openModal('svc-<?php echo $svc['key']; ?>')">
                <div class="service-top">
                    <div class="service-letter"><?php echo $svc['letter']; ?></div>
                    <div>
                        <div class="service-name"><?php echo htmlspecialchars($svc['name']); ?></div>
                        <div class="service-desc"><?php echo htmlspecialchars($svc['desc']); ?></div>
                    </div>
                </div>
                <div class="service-stats">
                    <span class="svc-pill"><?php echo $st['total']; ?> total</span>
                    <span class="svc-pill p"><?php echo $st['pending']; ?> pending</span>
                    <span class="svc-pill i"><?php echo $st['processing'] + $st['ready']; ?> in progress</span>
                    <span class="svc-pill o"><?php echo $st['completed']; ?> done</span>
                </div>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="section-card">
        <div class="section-card-header">
            <div class="section-card-title">Incoming Requests</div>
        </div>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Request Type</th>
                    <th>Date Filed</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_requests)): ?>
                    <tr><td colspan="6" class="empty-row">No pending requests at the moment. All documents are up to date.</td></tr>
                <?php else: ?>
                    <?php foreach ($pending_requests as $req): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['student_id'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($req['student_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($req['request_type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                        <td><span class="status-badge pending"><?php echo htmlspecialchars($req['status']); ?></span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-icon view" onclick="openRequestDrawer(<?php echo (int)$req['id']; ?>)" title="View details">View</button>
                                <button class="btn-icon accept" onclick="updateRequest(<?php echo $req['id']; ?>, 'accept', this)">Accept</button>
                                <button class="btn-icon reject" onclick="updateRequest(<?php echo $req['id']; ?>, 'reject', this)">Reject</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="section-card">
        <div class="section-card-header">
            <div class="section-card-title">Processing / Ready for Pickup</div>
        </div>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Name</th>
                    <th>Request Type</th>
                    <th>Date Filed</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($processing_requests)): ?>
                    <tr><td colspan="6" class="empty-row">No requests in progress.</td></tr>
                <?php else: ?>
                    <?php foreach ($processing_requests as $req): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['ticket_number'] ?? '—'); ?></strong></td>
                        <td><?php echo htmlspecialchars($req['student_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($req['request_type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                        <td><span class="status-badge <?php echo strtolower($req['status']); ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-icon view" onclick="openRequestDrawer(<?php echo (int)$req['id']; ?>)" title="View details">View</button>
                                <?php if (strtoupper($req['status']) === 'PROCESSING' || strtoupper($req['status']) === 'ACCEPTED'): ?>
                                    <button class="btn-icon ready" onclick="updateRequest(<?php echo $req['id']; ?>, 'ready', this)">Mark Ready</button>
                                <?php elseif (strtoupper($req['status']) === 'READY'): ?>
                                    <button class="btn-icon accept" onclick="updateRequest(<?php echo $req['id']; ?>, 'complete', this)">Complete</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</main>

<!-- STUDENT RECORDS MODAL -->
<div class="modal-overlay" id="modal-student-records" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                Student Records
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-3);margin-left:8px;"><?php echo count($all_students); ?> total</span>
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($all_students)): ?>
                <div class="modal-empty">No student records found.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Mobile</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($s['student_id'] ?? '—'); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['full_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['program'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['mobile'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($s['is_active'])): ?>
                                    <span class="status-badge completed">Active</span>
                                <?php else: ?>
                                    <span class="status-badge rejected">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ENROLLMENT MODAL -->
<div class="modal-overlay" id="modal-enrollment" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Enrollment
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($all_students)): ?>
                <div class="modal-empty">No enrolled students found.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Student ID</th><th>Full Name</th><th>College</th><th>Course</th><th>Year Level</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['student_id'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['full_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['college'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['course'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['year_level'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ACADEMIC RECORDS MODAL -->
<div class="modal-overlay" id="modal-academic-records" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                Academic Records
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-empty">Academic records module is coming soon.</div>
        </div>
    </div>
</div>

<!-- DOCUMENT REQUESTS MODAL -->
<div class="modal-overlay" id="modal-document-requests" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Document Requests
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($all_requests)): ?>
                <div class="modal-empty">No document requests found.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Student</th><th>Request Type</th><th>Ticket #</th><th>Date Filed</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_requests as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['id']); ?></td>
                            <td><?php echo htmlspecialchars($r['student_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($r['request_type'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($r['ticket_number'] ?? '—'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                            <td><span class="status-badge <?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ANNOUNCEMENTS MODAL -->
<div class="modal-overlay" id="modal-announcements" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                Announcements
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">

            <div class="ann-form">
                <div class="ann-form-title">Post New Announcement</div>
                <div class="form-row">
                    <label for="annTitle">Title</label>
                    <input type="text" id="annTitle" class="form-input" maxlength="150" placeholder="e.g. Enrollment schedule for 2nd semester">
                </div>
                <div class="form-row">
                    <label for="annBody">Message</label>
                    <textarea id="annBody" class="form-input" maxlength="2000" placeholder="Write the announcement students will see..."></textarea>
                </div>
                <div class="form-bottom">
                    <div class="priority-field">
                        <label for="annPriority" style="display:block;font-size:0.7rem;font-weight:600;color:var(--text-2);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.05em;">Priority</label>
                        <select id="annPriority" class="form-input">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="info">Info</option>
                        </select>
                    </div>
                    <button type="button" class="btn-post" id="btnPostAnn" onclick="postAnnouncement(this)">
                        <svg viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        Post Announcement
                    </button>
                </div>
            </div>

            <p class="modal-sub">Published announcements · <?php echo count($all_announcements); ?> total · visible to students on their Announcements page</p>
            <?php if (empty($all_announcements)): ?>
                <div class="modal-empty">No announcements yet. Post your first one above.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Announcement</th><th>Priority</th><th>Posted</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_announcements as $an): ?>
                        <tr>
                            <td class="ann-title-cell">
                                <?php echo htmlspecialchars($an['title']); ?>
                                <span class="cell-sub" title="<?php echo htmlspecialchars($an['body']); ?>"><?php echo htmlspecialchars($an['body']); ?></span>
                            </td>
                            <td><span class="status-badge <?php echo htmlspecialchars(strtolower($an['priority'] ?? 'normal')); ?>"><?php echo htmlspecialchars($an['priority'] ?? 'normal'); ?></span></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($an['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon reject" onclick="deleteAnnouncement(<?php echo (int)$an['id']; ?>, this)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ========== REPORTS MODAL ========== -->
<div class="modal-overlay" id="modal-reports" onclick="closeModal(event)">
    <div class="modal-box" style="max-width:1200px;">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Reports
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">

            <div class="block-title" style="font-size:0.95rem;margin-bottom:0.75rem;">Document Request Summary</div>
            <table class="data-table" id="reportsTable" style="margin-bottom:1.5rem;">
                <thead>
                    <tr><th>Status</th><th>Count</th></tr>
                </thead>
                <tbody>
                    <tr><td>Pending</td><td><strong><?php echo $count_pending; ?></strong></td></tr>
                    <tr><td>Processing</td><td><strong><?php echo $count_processing; ?></strong></td></tr>
                    <tr><td>Ready for Pickup</td><td><strong><?php echo $count_ready; ?></strong></td></tr>
                    <tr><td>Completed</td><td><strong><?php echo $count_completed; ?></strong></td></tr>
                    <tr><td>Rejected</td><td><strong><?php echo $count_rejected; ?></strong></td></tr>
                    <tr style="background:#f1f5f9;"><td><strong>TOTAL</strong></td><td><strong><?php echo $count_total; ?></strong></td></tr>
                </tbody>
            </table>

            <div class="block-title" style="font-size:0.95rem;margin-bottom:0.75rem;">
                Student Master List
                <span style="font-size:0.72rem;font-weight:400;color:var(--text-3);margin-left:8px;">
                    <?php echo count($all_students); ?> registered student<?php echo count($all_students) === 1 ? '' : 's'; ?>
                </span>
            </div>

            <?php if (empty($all_students)): ?>
                <div class="modal-empty">No students registered yet.</div>
            <?php else: ?>
                <div class="table-wrap" style="max-height:420px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                <table class="data-table" id="studentsReportTable">
                    <thead style="position:sticky;top:0;z-index:2;">
                        <tr>
                            <th>ID</th>
                            <th>Photo</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>College</th>
                            <th>Course</th>
                            <th>Year Level</th>
                            <th>Program</th>
                            <th>Grad. Year</th>
                            <th>Birthdate</th>
                            <th>Status</th>
                            <th>Verified</th>
                            <th>Registered</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['id']); ?></td>
                            <td>
                                <?php if (!empty($s['profile_picture']) && file_exists(__DIR__ . '/../../uploads/profile_pictures/' . $s['profile_picture'])): ?>
                                    <img src="../../uploads/profile_pictures/<?php echo htmlspecialchars($s['profile_picture']); ?>"
                                         alt=""
                                         style="width:32px;height:32px;border-radius:6px;object-fit:cover;display:block;">
                                <?php else: ?>
                                    <?php
                                        $p = explode(' ', trim($s['full_name'] ?? 'U'));
                                        $ini = count($p) >= 2 
                                            ? strtoupper(substr($p[0],0,1) . substr($p[1],0,1))
                                            : strtoupper(substr($s['full_name'] ?? 'U', 0, 2));
                                    ?>
                                    <div style="width:32px;height:32px;border-radius:6px;background:var(--gold);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;font-family:'Libre Baskerville',serif;">
                                        <?php echo $ini; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($s['student_id'] ?? '—'); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['full_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['mobile'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['college'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['course'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['year_level'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['program'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['graduation_year'] ?? '—'); ?></td>
                            <td><?php echo !empty($s['birthdate']) ? date('M d, Y', strtotime($s['birthdate'])) : '—'; ?></td>
                            <td>
                                <?php if (!empty($s['is_active'])): ?>
                                    <span class="status-badge completed">Active</span>
                                <?php else: ?>
                                    <span class="status-badge rejected">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['is_verified'])): ?>
                                    <span class="status-badge completed">Yes</span>
                                <?php else: ?>
                                    <span class="status-badge pending">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($s['created_at']) ? date('M d, Y', strtotime($s['created_at'])) : '—'; ?></td>
                            <td><?php echo !empty($s['last_login']) ? date('M d, Y g:i A', strtotime($s['last_login'])) : 'Never'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <div class="report-actions">
                <button class="btn-export btn-csv" onclick="exportCSV()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Full Report CSV
                </button>
                <button class="btn-export btn-pdf" onclick="exportPDF()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Generate PDF
                </button>
            </div>

        </div>
    </div>
</div>

<!-- USER ROLES MODAL -->
<div class="modal-overlay" id="modal-user-roles" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>User Accounts <span style="font-size:0.7rem;font-weight:400;color:var(--text-3);margin-left:8px;"><?php echo count($all_users); ?> total</span></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">

            <div class="ann-form">
                <div class="ann-form-title" id="userFormTitle">Add New User</div>
                <input type="hidden" id="uId" value="">
                <div class="form-row"><label>Full Name</label><input type="text" id="uFull" class="form-input"></div>
                <div class="form-row"><label>Email</label><input type="email" id="uEmail" class="form-input"></div>
                <div class="form-row" id="uCreateOnly1"><label>Username</label><input type="text" id="uUser" class="form-input"></div>
                <div class="form-row" id="uCreateOnly2"><label>Student ID (optional)</label><input type="text" id="uSid" class="form-input"></div>
                <div class="form-bottom">
                    <div class="priority-field" id="uCreateOnly3">
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:var(--text-2);margin-bottom:5px;text-transform:uppercase;">Role</label>
                        <select id="uRole" class="form-input"><option value="student">Student</option><option value="admin">Admin</option></select>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn-icon view" id="uCancel" style="display:none;" onclick="resetUserForm()">Cancel</button>
                        <button type="button" class="btn-post" id="uSave" onclick="saveUser(this)">Save User</button>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($all_users as $u): ?>
                <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['username'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($u['full_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                    <td><span class="status-badge <?php echo htmlspecialchars(strtolower($u['role'])); ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td><?php echo !empty($u['is_active']) ? '<span class="status-badge completed">Active</span>' : '<span class="status-badge rejected">Inactive</span>'; ?></td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-icon view" onclick='editUser(<?php echo json_encode(["id"=>(int)$u["id"],"full_name"=>$u["full_name"],"email"=>$u["email"]], JSON_HEX_APOS|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                            <button class="btn-icon email" onclick="resetPassword(<?php echo (int)$u['id']; ?>, this)">Reset PW</button>
                            <?php if ((int)$u['id'] !== $user_id): ?>
                            <button class="btn-icon reject" onclick="toggleUser(<?php echo (int)$u['id']; ?>, this)"><?php echo !empty($u['is_active']) ? 'Deactivate' : 'Activate'; ?></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<!-- SETTINGS MODAL -->
<div class="modal-overlay" id="modal-settings" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                Settings
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-empty">Settings module is coming soon.</div>
        </div>
    </div>
</div>

<!-- ACTIVITY LOG MODAL -->
<div class="modal-overlay" id="modal-activity-log" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Activity Log
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($recent_activities)): ?>
                <div class="modal-empty">No recent activities found.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Type</th><th>Description</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activities as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['activity_type'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['description'] ?? '—'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($a['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php foreach ($services as $svc):
    $rows = $service_requests[$svc['key']]; ?>
<div class="modal-overlay" id="modal-svc-<?php echo $svc['key']; ?>" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <span class="svc-badge"><?php echo $svc['letter']; ?></span>
                <?php echo htmlspecialchars($svc['name']); ?>
            </h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-sub"><?php echo htmlspecialchars($svc['desc']); ?> · <?php echo count($rows); ?> request<?php echo count($rows) === 1 ? '' : 's'; ?></p>
            <?php if (empty($rows)): ?>
                <div class="modal-empty">No requests for this service yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Ticket #</th><th>Student</th><th>Record</th><th>Date Filed</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): $su = strtoupper($r['status']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['ticket_number'] ?? '—'); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['student_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="code-tag" style="margin-left:0;"><?php echo htmlspecialchars($r['request_type']); ?></span>
                                <span class="cell-sub" title="<?php echo htmlspecialchars($r['details'] ?? ''); ?>"><?php echo htmlspecialchars($r['details'] ?? ''); ?></span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                            <td><span class="status-badge <?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon view" onclick="openRequestDrawer(<?php echo (int)$r['id']; ?>)" title="View details">View</button>
                                    <?php if ($su === 'PENDING'): ?>
                                        <button class="btn-icon accept" onclick="updateRequest(<?php echo (int)$r['id']; ?>, 'accept', this)">Accept</button>
                                        <button class="btn-icon reject" onclick="updateRequest(<?php echo (int)$r['id']; ?>, 'reject', this)">Reject</button>
                                    <?php elseif ($su === 'PROCESSING' || $su === 'ACCEPTED'): ?>
                                        <button class="btn-icon ready" onclick="updateRequest(<?php echo (int)$r['id']; ?>, 'ready', this)">Mark Ready</button>
                                    <?php elseif ($su === 'READY'): ?>
                                        <button class="btn-icon accept" onclick="updateRequest(<?php echo (int)$r['id']; ?>, 'complete', this)">Complete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>

function toggleUserDropdown(event) {
    event.stopPropagation();
    const menu = document.getElementById('userMenu');
    if (menu) menu.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    if (menu && !menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const menu = document.getElementById('userMenu');
        if (menu) menu.classList.remove('open');
    }
});

function openModal(name) {
    const modal = document.getElementById('modal-' + name);
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

document.addEventListener('DOMContentLoaded', function() {
    const m = location.hash.replace('#', '');
    if (m === 'announcements' || m === 'user-roles') {
        openModal(m);
        history.replaceState(null, '', location.pathname + location.search);
    }
});

(function() {
    const fabTop = document.getElementById('fabTop');
    document.body.addEventListener('scroll', function() {
        fabTop.classList.toggle('show', document.body.scrollTop > 250);
    });
})();
function scrollToTop() {
    document.body.scrollTo({ top: 0, behavior: 'smooth' });
}

function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;

    const icons = {
        success: '<svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>',
        error:   '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
        info:    '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    };

    toast.innerHTML = `<div class="toast-icon">${icons[type] || icons.info}</div><div>${message}</div>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

const CSRF_TOKEN = '<?php echo $csrf_token; ?>';

async function postAnnouncement(btnEl) {
    const titleEl = document.getElementById('annTitle');
    const bodyEl = document.getElementById('annBody');
    const priority = document.getElementById('annPriority').value;
    const title = titleEl.value.trim();
    const body = bodyEl.value.trim();

    if (!title || !body) {
        showToast('error', 'Please fill in both the title and the message.');
        return;
    }
    if (!confirm('📢 Post this announcement?\n\nStudents will see it on their Announcements page right away.')) return;

    const originalHTML = btnEl.innerHTML;
    btnEl.disabled = true;
    btnEl.textContent = 'Posting...';

    try {
        const res = await fetch('../api/announcements.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'create', title: title, body: body, priority: priority })
        });
        const data = await res.json();

        if (data.success) {
            showToast('success', '📢 ' + data.message);
            setTimeout(() => { location.hash = 'announcements'; location.reload(); }, 700);
        } else {
            showToast('error', 'Error: ' + (data.message || 'Unknown error'));
            btnEl.disabled = false;
            btnEl.innerHTML = originalHTML;
        }
    } catch (err) {
        showToast('error', 'Network error: ' + err.message);
        btnEl.disabled = false;
        btnEl.innerHTML = originalHTML;
    }
}

async function deleteAnnouncement(id, btnEl) {
    if (!confirm('🗑️ Delete this announcement?\n\nIt will also disappear from the students\' Announcements page. This cannot be undone.')) return;

    const originalText = btnEl.textContent.trim();
    btnEl.disabled = true;
    btnEl.textContent = '...';

    try {
        const res = await fetch('../api/announcements.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const data = await res.json();

        if (data.success) {
            showToast('success', '🗑️ ' + data.message);
            setTimeout(() => { location.hash = 'announcements'; location.reload(); }, 600);
        } else {
            showToast('error', 'Error: ' + (data.message || 'Unknown error'));
            btnEl.disabled = false;
            btnEl.textContent = originalText;
        }
    } catch (err) {
        showToast('error', 'Network error: ' + err.message);
        btnEl.disabled = false;
        btnEl.textContent = originalText;
    }
}
async function userApi(payload) {
    const res = await fetch('../api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify(payload)
    });
    return res.json();
}

function resetUserForm() {
    ['uId','uFull','uEmail','uUser','uSid'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('uRole').value = 'student';
    document.getElementById('userFormTitle').textContent = 'Add New User';
    ['uCreateOnly1','uCreateOnly2','uCreateOnly3'].forEach(id => document.getElementById(id).style.display = '');
    document.getElementById('uCancel').style.display = 'none';
}

function editUser(u) {
    document.getElementById('uId').value = u.id;
    document.getElementById('uFull').value = u.full_name || '';
    document.getElementById('uEmail').value = u.email || '';
    document.getElementById('userFormTitle').textContent = 'Edit User #' + u.id;
    ['uCreateOnly1','uCreateOnly2','uCreateOnly3'].forEach(id => document.getElementById(id).style.display = 'none');
    document.getElementById('uCancel').style.display = '';
    document.getElementById('uFull').focus();
}

async function saveUser(btn) {
    const id = document.getElementById('uId').value;
    const payload = {
        action: id ? 'update' : 'create',
        id: id ? parseInt(id) : undefined,
        full_name: document.getElementById('uFull').value.trim(),
        email: document.getElementById('uEmail').value.trim(),
        username: document.getElementById('uUser').value.trim(),
        student_id: document.getElementById('uSid').value.trim(),
        role: document.getElementById('uRole').value
    };
    btn.disabled = true;
    try {
        const data = await userApi(payload);
        if (data.success) {
            if (data.temp_password) alert('User created.\n\nTemporary password (ipakita lang ngayon):\n' + data.temp_password);
            showToast('success', data.message);
            setTimeout(() => { location.hash = 'user-roles'; location.reload(); }, 500);
        } else { showToast('error', data.message); btn.disabled = false; }
    } catch (e) { showToast('error', 'Network error: ' + e.message); btn.disabled = false; }
}

async function toggleUser(id, btn) {
    if (!confirm('Change this account\'s active status?')) return;
    btn.disabled = true;
    try {
        const data = await userApi({ action: 'toggle_active', id });
        if (data.success) {
            showToast('success', data.message);
            setTimeout(() => { location.hash = 'user-roles'; location.reload(); }, 500);
        } else { showToast('error', data.message); btn.disabled = false; }
    } catch (e) { showToast('error', 'Network error: ' + e.message); btn.disabled = false; }
}

async function resetPassword(id, btn) {
    if (!confirm('Reset this user\'s password? A new temporary password will be generated.')) return;
    btn.disabled = true;
    try {
        const data = await userApi({ action: 'reset_password', id });
        btn.disabled = false;
        if (data.success) alert('Temporary password (ipakita lang ngayon):\n' + data.temp_password);
        else showToast('error', data.message);
    } catch (e) { showToast('error', 'Network error: ' + e.message); btn.disabled = false; }
}

// ============================================
// EXPORT CSV (Formatted Report)
// ============================================
function exportCSV() {
    const csvRows = [];
    const divider = '════════════════════════════════════════════════════════════════';
    const subDiv  = '────────────────────────────────────────────────────────────────';

    csvRows.push([divider]);
    csvRows.push(['UNIVERSITY OF MAKATI']);
    csvRows.push(['REGISTRAR\'S OFFICE']);
    csvRows.push(['COMPREHENSIVE REPORT']);
    csvRows.push([divider]);
    csvRows.push(['Generated:', new Date().toLocaleString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
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

    const totalReq = <?php echo $count_total; ?>;
    if (totalReq === 0) {
        csvRows.push(['Note: No document requests were recorded as of the date of this report.']);
        csvRows.push([]);
    }

    csvRows.push([divider]);
    csvRows.push(['II. STUDENT MASTER LIST']);
    csvRows.push(['Total Registered Students: <?php echo count($all_students); ?>']);
    csvRows.push([divider]);
    csvRows.push([]);

    // CSV Header — walang ID at Photo (14 cols lang)
    csvRows.push([
        'Student ID', 'Full Name', 'Email Address', 'Mobile No.',
        'College', 'Course', 'Year Level', 'Program', 'Grad. Year',
        'Birthdate', 'Status', 'Verified', 'Date Registered', 'Last Login'
    ]);
    csvRows.push([subDiv]);

    const studentsTable = document.getElementById('studentsReportTable');
    if (studentsTable) {
        const rows = studentsTable.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            const cells = row.querySelectorAll('td');
            const rowData = [];
            cells.forEach(function(cell, i) {
                // Skip ID (index 0) at Photo (index 1)
                if (i === 0 || i === 1) return;
                let text = cell.innerText.replace(/\s+/g, ' ').trim();
                if (text === '' || text === '—') text = 'N/A';
                rowData.push(text);
            });
            if (rowData.length > 0) csvRows.push(rowData);
        });
    }
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
// EXPORT PDF (Formatted Report — Landscape A4)
// ============================================
function exportPDF() {
    const printWindow = window.open('', '_blank', 'width=1100,height=750');

    const studentRows = [];
    const studentsTable = document.getElementById('studentsReportTable');
    if (studentsTable) {
        const rows = studentsTable.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            const cells = row.querySelectorAll('td');
            const rowData = [];
            cells.forEach(function(cell, i) {
                // Skip ID (index 0) at Photo (index 1)
                if (i === 0 || i === 1) return;
                let text = cell.innerText.replace(/\s+/g, ' ').trim();
                if (text === '' || text === '—') text = 'N/A';
                rowData.push(text);
            });
            if (rowData.length > 0) studentRows.push(rowData);
        });
    }

    let studentsHtml = '';
    if (studentRows.length === 0) {
        studentsHtml = '<tr><td colspan="14" style="text-align:center;padding:30px;color:#666;font-style:italic;">No students registered yet.</td></tr>';
    } else {
        studentRows.forEach(function(r, idx) {
            const bgColor = idx % 2 === 0 ? '#ffffff' : '#f9fafb';
            studentsHtml += '<tr style="background:' + bgColor + ';">';
            r.forEach(function(cell) {
                studentsHtml += '<td>' + cell.replace(/</g, '&lt;') + '</td>';
            });
            studentsHtml += '</tr>';
        });
    }

    const totalReq = <?php echo $count_total; ?>;

    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>UMak Registrar Report - <?php echo date('M d, Y'); ?></title>
            <style>
                @page { size: A4 landscape; margin: 12mm 10mm; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Times New Roman', Georgia, serif;
                    padding: 20px;
                    color: #1a1a1a;
                    font-size: 11px;
                    line-height: 1.4;
                    padding-top: 60px;
                }

                .header {
                    text-align: center;
                    border-bottom: 3px double #003366;
                    padding-bottom: 16px;
                    margin-bottom: 24px;
                }
                .header .univ {
                    font-size: 18px;
                    font-weight: 700;
                    color: #003366;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                }
                .header .office {
                    font-size: 13px;
                    font-weight: 600;
                    color: #333;
                    letter-spacing: 0.04em;
                    margin-bottom: 2px;
                }
                .header .report-title {
                    font-size: 15px;
                    font-weight: 700;
                    color: #003366;
                    text-transform: uppercase;
                    letter-spacing: 0.12em;
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid #cfd5dd;
                }
                .header .meta {
                    font-size: 10px;
                    color: #555;
                    margin-top: 10px;
                    display: flex;
                    justify-content: center;
                    gap: 24px;
                    flex-wrap: wrap;
                }
                .header .meta strong { color: #333; }

                .section { margin-bottom: 28px; }
                .section-title {
                    font-size: 13px;
                    font-weight: 700;
                    color: #003366;
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    padding-bottom: 6px;
                    border-bottom: 2px solid #FFD700;
                    margin-bottom: 12px;
                    display: flex;
                    justify-content: space-between;
                    align-items: baseline;
                }
                .section-title .count-note {
                    font-size: 10px;
                    font-weight: 400;
                    color: #666;
                    text-transform: none;
                    letter-spacing: 0;
                }

                .summary-table {
                    width: 60%;
                    border-collapse: collapse;
                    font-size: 11px;
                    margin: 0 auto 12px;
                }
                .summary-table th {
                    background: #003366;
                    color: #fff;
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    padding: 8px 16px;
                    border: 1px solid #003366;
                    text-align: left;
                }
                .summary-table th:last-child { text-align: right; }
                .summary-table td {
                    padding: 8px 16px;
                    border: 1px solid #cfd5dd;
                }
                .summary-table td:last-child {
                    text-align: right;
                    font-weight: 700;
                    font-family: 'Courier New', monospace;
                }
                .summary-table tr.total-row {
                    background: #f0f4f9;
                    font-weight: 700;
                    border-top: 2px solid #003366;
                }
                .summary-table tr.total-row td {
                    color: #003366;
                    font-weight: 700;
                }
                .note-line {
                    text-align: center;
                    font-style: italic;
                    font-size: 10px;
                    color: #666;
                    margin-top: 8px;
                }

                /* STUDENT TABLE — 14 columns aligned */
                .student-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 8px;
                    table-layout: fixed;
                }
                .student-table th {
                    background: #003366;
                    color: #fff;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.02em;
                    font-size: 7px;
                    padding: 6px 3px;
                    border: 1px solid #003366;
                    text-align: left;
                    vertical-align: middle;
                    word-wrap: break-word;
                    line-height: 1.2;
                }
                .student-table td {
                    padding: 5px 3px;
                    border: 1px solid #d1d9e0;
                    vertical-align: top;
                    word-wrap: break-word;
                    word-break: break-word;
                    line-height: 1.3;
                }

                /* Column widths — 14 columns = 100% */
                .student-table th:nth-child(1),  .student-table td:nth-child(1)  { width: 7%;  }
                .student-table th:nth-child(2),  .student-table td:nth-child(2)  { width: 11%; }
                .student-table th:nth-child(3),  .student-table td:nth-child(3)  { width: 15%; }
                .student-table th:nth-child(4),  .student-table td:nth-child(4)  { width: 7%;  }
                .student-table th:nth-child(5),  .student-table td:nth-child(5)  { width: 14%; }
                .student-table th:nth-child(6),  .student-table td:nth-child(6)  { width: 14%; }
                .student-table th:nth-child(7),  .student-table td:nth-child(7)  { width: 6%;  }
                .student-table th:nth-child(8),  .student-table td:nth-child(8)  { width: 9%;  }
                .student-table th:nth-child(9),  .student-table td:nth-child(9)  { width: 5%;  }
                .student-table th:nth-child(10), .student-table td:nth-child(10) { width: 7%;  }
                .student-table th:nth-child(11), .student-table td:nth-child(11) { width: 5%;  }
                .student-table th:nth-child(12), .student-table td:nth-child(12) { width: 5%;  }
                .student-table th:nth-child(13), .student-table td:nth-child(13) { width: 6%;  }
                .student-table th:nth-child(14), .student-table td:nth-child(14) { width: 5%;  }

                .footer {
                    margin-top: 32px;
                    padding-top: 16px;
                    border-top: 2px solid #003366;
                    text-align: center;
                    font-size: 9px;
                    color: #666;
                    line-height: 1.6;
                }
                .footer .footer-brand {
                    font-weight: 700;
                    color: #003366;
                    letter-spacing: 0.05em;
                }

                @media print {
                    body { padding: 0; }
                    .no-print { display: none !important; }
                    .student-table tr { page-break-inside: avoid; }
                    .student-table thead { display: table-header-group; }
                }

                .preview-toolbar {
                    position: fixed;
                    top: 0; left: 0; right: 0;
                    background: #003366;
                    color: #fff;
                    padding: 10px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    z-index: 9999;
                    font-family: 'Segoe UI', Arial, sans-serif;
                }
                .preview-toolbar span { font-size: 12px; }
                .preview-toolbar .btns { display: flex; gap: 10px; }
                .preview-toolbar button {
                    background: #FFD700;
                    color: #003366;
                    border: none;
                    padding: 6px 16px;
                    border-radius: 6px;
                    font-size: 11px;
                    font-weight: 700;
                    cursor: pointer;
                }
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
                        year: 'numeric', month: 'long', day: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    })}</span>
                    <span><strong>Generated by:</strong> <?php echo htmlspecialchars($user_fullname); ?></span>
                </div>
            </div>

            <div class="section">
                <div class="section-title">
                    <span>I. Document Request Summary</span>
                </div>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Request Status</th>
                            <th>Number of Requests</th>
                        </tr>
                    </thead>
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
                    <span>II. Student Master List</span>
                    <span class="count-note">(${studentRows.length} registered student${studentRows.length === 1 ? '' : 's'})</span>
                </div>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Mobile No.</th>
                            <th>College</th>
                            <th>Course</th>
                            <th>Year Level</th>
                            <th>Program</th>
                            <th>Grad. Year</th>
                            <th>Birthdate</th>
                            <th>Status</th>
                            <th>Verified</th>
                            <th>Date Registered</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${studentsHtml}
                    </tbody>
                </table>
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

async function updateRequest(requestId, action, btnEl) {
    const confirmMessages = {
        accept:   '✅ Accept this request?\n\nStatus will change to PROCESSING and a ticket number will be generated.',
        reject:   '❌ Reject this request?\n\nThis action cannot be undone.',
        ready:    '📦 Mark as Ready for Pickup?\n\nThe student will be notified that the document is ready.',
        complete: '🎉 Mark as Completed?\n\nThe student has claimed the document.',
    };

    if (!confirm(confirmMessages[action] || 'Proceed with this action?')) return;

    const originalText = btnEl.textContent.trim();
    btnEl.disabled = true;
    btnEl.textContent = '...';

    try {
        const res = await fetch('../api/update_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, action: action })
        });

        const data = await res.json();

        if (data.success) {
            btnEl.textContent = '✓ Done';
            btnEl.style.background = '#10b981';
            let msg = `Request #${requestId} updated to ${data.status}`;
            if (data.ticket) msg += ` — Ticket: ${data.ticket}`;
            showToast('success', msg);
            setTimeout(() => location.reload(), 900);
        } else {
            showToast('error', 'Error: ' + (data.message || 'Unknown error'));
            btnEl.disabled = false;
            btnEl.textContent = originalText;
        }
    } catch (err) {
        showToast('error', 'Network error: ' + err.message);
        btnEl.disabled = false;
        btnEl.textContent = originalText;
    }
}

let currentDrawerRequestId = null;

function openRequestDrawer(requestId) {
    currentDrawerRequestId = requestId;
    const drawer = document.getElementById('requestDrawer');
    const backdrop = document.getElementById('requestDrawerBackdrop');
    const body = document.getElementById('drawerBody');
    const foot = document.getElementById('drawerFoot');
    const ticket = document.getElementById('drawerTicket');

    ticket.textContent = '—';
    body.innerHTML = '<div class="drawer-loading">Loading...</div>';
    foot.style.display = 'none';
    foot.innerHTML = '';

    drawer.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch('../api/get_requests.php?id=' + encodeURIComponent(requestId))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="drawer-error">⚠️ ' + (data.message || 'Unable to load request.') + '</div>';
                return;
            }
            renderRequestDrawer(data.request);
        })
        .catch(err => {
            body.innerHTML = '<div class="drawer-error">⚠️ Network error: ' + err.message + '</div>';
        });
}

function closeRequestDrawer() {
    document.getElementById('requestDrawer').classList.remove('open');
    document.getElementById('requestDrawerBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    currentDrawerRequestId = null;
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function formatDateTime(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    const opts = { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
    return d.toLocaleString('en-US', opts);
}

function renderRequestDrawer(req) {
    const body = document.getElementById('drawerBody');
    const foot = document.getElementById('drawerFoot');
    const ticket = document.getElementById('drawerTicket');

    const pd = req.parsed_details || {};
    const status = (req.status || 'PENDING').toUpperCase();
    const statusLower = status.toLowerCase();

    ticket.textContent = req.ticket_number || 'No ticket yet';

    const steps = ['Submitted', 'Verified', 'Processing', 'Ready for Pickup', 'Released'];
    let currentStep = 0;
    if (status === 'PENDING') currentStep = 0;
    else if (status === 'ACCEPTED' || status === 'VERIFIED') currentStep = 1;
    else if (status === 'PROCESSING') currentStep = 2;
    else if (status === 'READY') currentStep = 3;
    else if (status === 'COMPLETED' || status === 'CLAIMED') currentStep = 4;

    const isRejected = status === 'REJECTED';

    let timelineHtml = '';
    const history = req.history || [];
    function findHistory(statusName) {
        return history.find(h => (h.new_status || '').toUpperCase() === statusName) || null;
    }

    steps.forEach((label, i) => {
        let cls = '';
        if (isRejected) { cls = i <= 0 ? 'done' : ''; }
        else { if (i < currentStep) cls = 'done'; else if (i === currentStep) cls = 'current'; }

        let meta = '';
        if (i === 0) { meta = formatDateTime(req.created_at); }
        else {
            const statusKey = ['PENDING', 'ACCEPTED', 'PROCESSING', 'READY', 'COMPLETED'][i];
            const h = findHistory(statusKey);
            if (h) {
                meta = formatDateTime(h.changed_at);
                if (h.changed_by_name) meta += ' · by ' + escapeHtml(h.changed_by_name);
            }
        }

        timelineHtml += `
            <div class="timeline-item ${cls}">
                <div class="tl-status">${escapeHtml(label)}</div>
                ${meta ? `<div class="tl-meta">${meta}</div>` : ''}
            </div>
        `;
    });

    body.innerHTML = `
        <div class="drawer-section">
            <div class="drawer-section-title">Status</div>
            <span class="drawer-status-badge ${statusLower}">${escapeHtml(status)}</span>
            ${req.priority ? `<span style="margin-left:8px;font-size:0.72rem;color:var(--text-3);">Priority: <strong>${escapeHtml(req.priority)}</strong></span>` : ''}
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Student</div>
            <div class="drawer-grid">
                <div class="drawer-label">Name</div>
                <div class="drawer-value">${escapeHtml(req.student_name || '—')}</div>
                <div class="drawer-label">Student ID</div>
                <div class="drawer-value mono">${escapeHtml(req.student_id || '—')}</div>
                <div class="drawer-label">Email</div>
                <div class="drawer-value">${escapeHtml(req.student_email || '—')}</div>
                <div class="drawer-label">Program</div>
                <div class="drawer-value">${escapeHtml(req.student_program || '—')}</div>
                <div class="drawer-label">Mobile</div>
                <div class="drawer-value">${escapeHtml(req.student_mobile || '—')}</div>
            </div>
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Document</div>
            <div class="drawer-grid">
                <div class="drawer-label">Type</div>
                <div class="drawer-value"><span class="code-tag" style="margin-left:0;">${escapeHtml(req.request_type || '—')}</span></div>
                ${pd.category ? `<div class="drawer-label">Category</div><div class="drawer-value">${escapeHtml(pd.category)}</div>` : ''}
                ${pd.document ? `<div class="drawer-label">Name</div><div class="drawer-value">${escapeHtml(pd.document)}</div>` : ''}
                <div class="drawer-label">Purpose</div>
                <div class="drawer-value">${escapeHtml(pd.purpose || pd.raw || '—')}</div>
                ${pd.copies ? `<div class="drawer-label">Copies</div><div class="drawer-value">${escapeHtml(pd.copies)}</div>` : ''}
                ${pd.release ? `<div class="drawer-label">Release</div><div class="drawer-value">${escapeHtml(pd.release)}</div>` : ''}
            </div>
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Timeline</div>
            ${isRejected ? '<div style="color:var(--st-bad-fg);font-weight:600;font-size:0.82rem;">This request was rejected.</div>' : `<div class="drawer-timeline">${timelineHtml}</div>`}
        </div>

        ${req.processed_by_name ? `
        <div class="drawer-section">
            <div class="drawer-section-title">Processed By</div>
            <div class="drawer-grid">
                <div class="drawer-label">Admin</div>
                <div class="drawer-value">${escapeHtml(req.processed_by_name)}</div>
                <div class="drawer-label">Processed</div>
                <div class="drawer-value">${formatDateTime(req.processed_at)}</div>
                ${req.released_at ? `<div class="drawer-label">Released</div><div class="drawer-value">${formatDateTime(req.released_at)}</div>` : ''}
            </div>
        </div>` : ''}
    `;

    let actions = '';
    if (status === 'PENDING') {
        actions += `<button class="btn-icon accept" style="padding:9px 16px;font-size:0.78rem;" onclick="drawerAction('accept')">Accept</button>`;
        actions += `<button class="btn-icon reject" style="padding:9px 16px;font-size:0.78rem;" onclick="drawerAction('reject')">Reject</button>`;
    } else if (status === 'PROCESSING' || status === 'ACCEPTED') {
        actions += `<button class="btn-icon ready" style="padding:9px 16px;font-size:0.78rem;" onclick="drawerAction('ready')">Mark Ready</button>`;
        actions += `<button class="btn-icon reject" style="padding:9px 16px;font-size:0.78rem;" onclick="drawerAction('reject')">Reject</button>`;
    } else if (status === 'READY') {
        actions += `<button class="btn-icon accept" style="padding:9px 16px;font-size:0.78rem;" onclick="drawerAction('complete')">Complete</button>`;
    }

    if (actions) { foot.innerHTML = actions; foot.style.display = 'flex'; }
    else { foot.style.display = 'none'; }
}

async function drawerAction(action) {
    if (!currentDrawerRequestId) return;

    const confirmMessages = {
        accept:   '✅ Accept this request?',
        reject:   '❌ Reject this request? This cannot be undone.',
        ready:    '📦 Mark as Ready for Pickup?',
        complete: '🎉 Mark as Completed?',
    };
    if (!confirm(confirmMessages[action] || 'Proceed?')) return;

    const foot = document.getElementById('drawerFoot');
    const originalHtml = foot.innerHTML;
    foot.innerHTML = '<div style="padding:0.5rem;color:var(--text-3);font-size:0.8rem;">Updating...</div>';

    try {
        const res = await fetch('../api/update_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: currentDrawerRequestId, action: action })
        });
        const data = await res.json();

        if (data.success) {
            showToast('success', `Request updated to ${data.status}` + (data.ticket ? ` — Ticket: ${data.ticket}` : ''));
            closeRequestDrawer();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('error', 'Error: ' + (data.message || 'Unknown error'));
            foot.innerHTML = originalHtml;
        }
    } catch (err) {
        showToast('error', 'Network error: ' + err.message);
        foot.innerHTML = originalHtml;
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRequestDrawer();
});

</script>
</body>
</html>