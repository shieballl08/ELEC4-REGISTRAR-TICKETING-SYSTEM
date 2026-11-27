<?php
// ============================================
// ENABLE ERROR REPORTING FOR DEBUGGING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// START SESSION - MUST BE FIRST! (BEFORE ANY OUTPUT)
// ============================================
session_start();

// ============================================
// FAKE LOGIN SESSION - FOR TESTING ONLY
// ============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Juan Dela Cruz';
    $_SESSION['email'] = 'test@umak.edu.ph';
    $_SESSION['role'] = 'student';
    $_SESSION['logged_in'] = true;
    $_SESSION['college'] = 'College of Information Technology';
    $_SESSION['course'] = 'BS Information Technology';
    $_SESSION['year_level'] = '3rd Year';
    $_SESSION['student_id'] = '2026-00001';
}

// ============================================
// INCLUDE DATABASE CONNECTION
// ============================================
$configPath = dirname(__DIR__, 2) . '/config/db_connect.php';

if (file_exists($configPath)) {
    require_once $configPath;
} else {
    $fallbackPath = '../../config/db_connect.php';
    if (file_exists($fallbackPath)) {
        require_once $fallbackPath;
    } else {
        die('Database connection file not found. Please check your folder structure.');
    }
}

// ============================================
// GET USER DATA FROM SESSION
// ============================================
$user_id = $_SESSION['user_id'] ?? 1;
$user_fullname = $_SESSION['full_name'] ?? 'Juan Dela Cruz';
$user_email = $_SESSION['email'] ?? 'juan.delacruz@umak.edu.ph';
$user_role = $_SESSION['role'] ?? 'student';

$nameParts = explode(' ', $user_fullname);
$user_initials = '';
if (count($nameParts) >= 2) {
    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $user_initials = strtoupper(substr($user_fullname, 0, 2));
}

// ============================================
// GET ADDITIONAL USER DATA FROM DATABASE
// ============================================
try {
    if (function_exists('getDBConnection')) {
        $pdo = getDBConnection();
        
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userData = $stmt->fetch();
            
            if ($userData) {
                $user_fullname = $userData['full_name'] ?? $user_fullname;
                $user_email = $userData['email'] ?? $user_email;
                $user_mobile = $userData['mobile'] ?? '';
                $user_birthdate = $userData['birthdate'] ?? '';
                $user_role = $userData['role'] ?? 'student';
            }
        }
    }
    
    $user_college = $_SESSION['college'] ?? 'College of Information Technology';
    $user_course = $_SESSION['course'] ?? 'BS Information Technology';
    $user_year_level = $_SESSION['year_level'] ?? '3rd Year';
    $user_student_id = $_SESSION['student_id'] ?? '2026-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
    
    // ============================================
    // CLEARANCE DATA - FETCH FROM DATABASE ONLY
    // ============================================
    $clearances = [];
    if (isset($pdo) && $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM clearance_requests WHERE user_id = ? ORDER BY request_date DESC");
        $stmt->execute([$user_id]);
        $clearances = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Database error in clearance.php: " . $e->getMessage());
    $user_college = 'College of Information Technology';
    $user_course = 'BS Information Technology';
    $user_year_level = '3rd Year';
    $user_student_id = '2026-' . str_pad($user_id ?? 1, 5, '0', STR_PAD_LEFT);
    $clearances = [];
}

// ============================================
// CLEARANCE CHECKLIST ITEMS - EMPTY (NO SAMPLE DATA)
// ============================================
$clearance_items = [];

$total_items = 0;
$completed_items = 0;
$clearance_progress = 0;

// ============================================
// UPDATE SESSION WITH LATEST DATA
// ============================================
$_SESSION['full_name'] = $user_fullname;
$_SESSION['email'] = $user_email;
$_SESSION['role'] = $user_role;
$_SESSION['college'] = $user_college;
$_SESSION['course'] = $user_course;
$_SESSION['year_level'] = $user_year_level;
$_SESSION['student_id'] = $user_student_id;

// Background image (kept for reference but not used in the new design)
$bg_image_path = '../../images/dashboard_bg.jpg';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Clearance</title>

    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    
    <style>
        /* ========== GLOBAL ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text-1);
            transition: background-color 0.2s ease, color 0.2s ease;
            -webkit-font-smoothing: antialiased;
        }
        button, input, select, textarea { font-family: inherit; }
        h1, h2, h3, .serif { font-family: 'Libre Baskerville', Georgia, serif; }

        :root {
            --primary: #003366;
            --primary-dark: #002244;
            --primary-light: #1a4d7a;
            --secondary: #FFD700;
            --secondary-dark: #E6C200;
            --bg: #f5f6f8;
            --surface: #ffffff;
            --surface-2: #f5f6f8;
            --surface-3: #eceef2;
            --border: #e3e6eb;
            --border-md: #cfd5dd;
            --text-1: #1a2a3a;
            --text-2: #4a5a6a;
            --text-3: #7b8896;
            --accent-soft: rgba(0,51,102,0.07);
            --accent-text: #003366;
            --btn: #003366;
            --btn-hover: #002244;
            --shadow-sm: 0 1px 2px rgba(16,24,40,0.05);
            --shadow-lg: 0 12px 32px rgba(16,24,40,0.14);
            --st-pending-bg: #fef6dd;  --st-pending-fg: #8a6100;
            --st-info-bg:    #e6effa;  --st-info-fg:    #1a4d7a;
            --st-ok-bg:      #e3f4e8;  --st-ok-fg:      #1e7e34;
            --st-bad-bg:     #fde8e8;  --st-bad-fg:     #b91c1c;
        }
        body.dark-mode {
            --bg: #0d1117;
            --surface: #161b22;
            --surface-2: #0d1117;
            --surface-3: #21262d;
            --border: rgba(255,255,255,0.09);
            --border-md: rgba(255,255,255,0.16);
            --text-1: #e6edf3;
            --text-2: #b4bdc8;
            --text-3: #8b96a3;
            --accent-soft: rgba(255,215,0,0.10);
            --accent-text: #ffd700;
            --btn: #2f5d93;
            --btn-hover: #3a6eab;
            --shadow-lg: 0 12px 32px rgba(0,0,0,0.5);
            --st-pending-bg: rgba(184,134,11,0.2); --st-pending-fg: #f5b342;
            --st-info-bg: rgba(26,77,122,0.3);     --st-info-fg: #7aa9d9;
            --st-ok-bg: rgba(5,150,105,0.2);       --st-ok-fg: #34d399;
            --st-bad-bg: rgba(185,28,28,0.2);      --st-bad-fg: #f87171;
        }

        /* ========== DARK MODE (header / nav only) ========== */
        body.dark-mode .header { background: #161b22; }
        body.dark-mode .brand-name { color: #ffffff; }
        body.dark-mode .side-nav,
        body.dark-mode .side-nav-header { background: #0d1117; border-color: rgba(255,255,255,0.08); }
        body.dark-mode .side-nav-brand { color: #ffffff; }
        body.dark-mode .side-nav-link { color: #b0b0c8; }
        body.dark-mode .side-nav-link:hover { background: #3a3a55; color: #e6edf3; }
        body.dark-mode .side-nav-link.active { background: rgba(255,215,0,0.12); color: #ffd700; }
        body.dark-mode .side-nav-close { background: #3a3a55; color: #b0b0c8; }
        body.dark-mode .side-nav-footer { border-top-color: rgba(255,255,255,0.08); color: #8a8aaa; }
        body.dark-mode .dropdown-menu { background: #0d1117; border-color: rgba(255,255,255,0.08); }
        body.dark-mode .dd-header { border-bottom-color: rgba(255,255,255,0.08); }
        body.dark-mode .dd-item:hover { background: #3a3a55; }
        body.dark-mode .avatar-btn { background: rgba(255,215,0,0.08); border-color: rgba(255,215,0,0.12); }
        body.dark-mode .avatar-btn:hover { background: rgba(255,215,0,0.15); }
        body.dark-mode .avatar-circle { background: #ffd700; color: #0d1117; }
        body.dark-mode .avatar-name { color: #e6edf3; }
        body.dark-mode .hamburger-btn { color: rgba(255,255,255,0.8); }
        body.dark-mode .hamburger-btn:hover { background: rgba(255,215,0,0.12); }
        body.dark-mode footer { border-top-color: rgba(255,255,255,0.08) !important; }

        /* ========== HEADER ========== */
        .header { background: var(--primary); position: sticky; top: 0; z-index: 100; transition: background 0.3s ease; width: 100%; padding: 0; }
        .header-inner { max-width: 100%; margin: 0; padding: 0 0.75rem; height: 68px; display: flex; align-items: center; gap: 1rem; }
        .brand { display: flex; flex-direction: column; gap: 1px; min-width: 140px; text-decoration: none; }
        .brand-name { font-size: 1.2rem; font-weight: 700; color: #fff; letter-spacing: 0.01em; line-height: 1; }
        .brand-sub  { font-size: 0.55rem; color: rgba(255,255,255,0.5); font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; }
        .hamburger-btn { display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: rgba(255,255,255,0.85); cursor: pointer; padding: 4px; margin-right: 2px; border-radius: 6px; transition: background 0.15s; }
        .hamburger-btn:hover { background: rgba(255,215,0,0.12); }
        .hamburger-btn svg { width: 24px; height: 24px; stroke: currentColor; stroke-width: 2.2; fill: none; }
        .header-nav-links { display: flex; align-items: center; gap: 0.15rem; margin-left: auto; flex-wrap: nowrap; }
        .header-nav-link { color: rgba(255,255,255,0.6); padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.7rem; text-decoration: none; transition: all 0.2s ease; white-space: nowrap; background: transparent !important; border: none !important; cursor: pointer; font-weight: 400; letter-spacing: 0.02em; }
        .header-nav-link:hover { color: rgba(255,255,255,0.95); background: rgba(255,215,0,0.08) !important; }
        .header-nav-link.active { color: #ffffff !important; background: rgba(255,215,0,0.12) !important; font-weight: 500; }
        .nav-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; margin-left: 0.5rem; }
        .avatar-btn { display: flex; align-items: center; gap: 6px; background: rgba(255,215,0,0.12); border: 1px solid rgba(255,215,0,0.2); padding: 3px 8px 3px 3px; cursor: pointer; transition: background 0.15s; }
        .avatar-btn:hover { background: rgba(255,215,0,0.2); }
        .avatar-circle { width: 26px; height: 26px; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; color: var(--primary); }
        .avatar-name { font-size: 0.75rem; color: rgba(255,255,255,0.85); font-weight: 400; }
        .dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); width: 260px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg); z-index: 200; display: none; overflow: hidden; }
        .dropdown-menu.open { display: block; animation: dropIn 0.14s ease; }
        @keyframes dropIn { from { opacity:0; transform: translateY(-6px); } to { opacity:1; transform: translateY(0); } }
        .dd-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); }
        .dd-name { font-size: 0.875rem; font-weight: 600; color: var(--text-1); }
        .dd-email { font-size: 0.75rem; color: var(--text-3); margin-top: 2px; }
        .dd-section { padding: 8px 8px; }
        .dd-label { font-size: 0.625rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-3); padding: 4px 8px 6px; }
        .dd-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; font-size: 0.8125rem; color: var(--text-2); cursor: pointer; transition: background 0.12s; }
        .dd-item:hover { background: var(--surface-2); }
        .dd-item.danger { color: #dc2626; }
        .dd-item.danger:hover { background: #fef2f2; }
        .dd-divider { height: 1px; background: var(--border); margin: 4px 8px; }

        /* ========== SIDE NAV ========== */
        .side-nav { position: fixed; top: 0; left: -320px; width: 300px; max-width: 80vw; height: 100vh; background: var(--surface); box-shadow: 4px 0 24px rgba(0,0,0,0.06); z-index: 300; transition: left 0.28s cubic-bezier(0.2, 0.9, 0.4, 1.1), background 0.3s ease; display: flex; flex-direction: column; border-right: 1px solid var(--border); overflow-y: auto; }
        .side-nav.open { left: 0; }
        .side-nav-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.25rem 0.75rem; border-bottom: 1px solid var(--border); background: var(--surface); }
        .side-nav-brand { font-size: 1.1rem; font-weight: 700; color: var(--primary); text-decoration: none; }
        .side-nav-brand span { color: var(--secondary-dark); }
        .side-nav-close { background: var(--surface-3); border: none; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-2); transition: background 0.15s; }
        .side-nav-close:hover { background: var(--surface-2); color: var(--text-1); }
        .side-nav-close svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2.5; fill: none; }
        .side-nav-body { padding: 0.75rem 1rem 1rem; flex: 1; }
        .side-nav-link { display: flex; align-items: center; gap: 10px; padding: 0.6rem 0.9rem; font-size: 0.85rem; font-weight: 500; color: var(--text-2); text-decoration: none; transition: background 0.12s, color 0.12s; margin-bottom: 2px; cursor: pointer; }
        .side-nav-link:hover { background: var(--surface-2); color: var(--text-1); }
        .side-nav-link.active { background: rgba(0,51,102,0.06); color: var(--primary); font-weight: 600; }
        .side-nav-link svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }
        .side-nav-divider { height: 1px; background: var(--border); margin: 0.5rem 0.5rem; }
        .side-nav-footer { padding: 0.75rem 1rem 1.5rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--text-3); }
        .nav-overlay { position: fixed; inset: 0; background: rgba(10,20,40,0.35); backdrop-filter: blur(2px); z-index: 299; opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; }
        .nav-overlay.open { opacity: 1; visibility: visible; }

        /* ========== PAGE ========== */
        .page { max-width: 1280px; margin: 0 auto; padding: 1.75rem 1.5rem 3rem; }

        /* Soft ombre dashboard canvas (matches Certifications) */
        .dashboard-canvas {
            min-height: calc(100vh - 68px);
            background:
                radial-gradient(900px 260px at 18% 0%, rgba(120,170,225,0.55) 0%, rgba(120,170,225,0) 70%),
                radial-gradient(700px 240px at 85% 0%, rgba(255,215,0,0.22) 0%, rgba(255,215,0,0) 70%),
                linear-gradient(180deg, #cddff2 0%, #e3edf8 170px, #f4f7fb 380px, #ffffff 100%);
            background-repeat: no-repeat;
        }
        body.dark-mode .dashboard-canvas { background: linear-gradient(180deg, #0b1626 0%, #0d1117 320px); }

        /* ========== PAGE HEAD ========== */
        .page-head { padding: 0.75rem 0 0.5rem; margin-bottom: 1.5rem; }
        .page-title { font-family: 'Inter', system-ui, sans-serif; font-size: 2rem; font-weight: 700; color: var(--accent-text); letter-spacing: -0.015em; }
        .page-sub { color: var(--text-2); font-size: 0.85rem; margin-top: 4px; }
        body.dark-mode .page-title { color: var(--text-1); }

        /* Breadcrumb (kept from original) */
        .crumb { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: var(--text-3); margin-bottom: 4px; }
        .crumb a { color: var(--text-2); text-decoration: none; }
        .crumb a:hover { color: var(--accent-text); }

        /* ========== CARD BASE ========== */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--shadow-sm); }

        /* ========== SECTION HEADER ========== */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin: 2rem 0 0.75rem; flex-wrap: wrap; gap: 0.75rem; }
        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 18px; background: var(--secondary); border-radius: 2px; }
        .section-meta { font-size: 0.75rem; color: var(--text-3); }

        /* ========== STATS STRIP ========== */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); margin-bottom: 1.1rem; }
        .stat-box { padding: 0.8rem 1.25rem; display: flex; align-items: baseline; gap: 10px; border-right: 1px solid var(--border); }
        .stat-box:last-child { border-right: none; }
        .stat-box .stat-value { font-size: 1.35rem; font-weight: 700; color: var(--accent-text); line-height: 1; }
        .stat-box .stat-label { font-size: 0.76rem; color: var(--text-3); }

        /* ========== PROGRESS BAR ========== */
        .progress-card { padding: 1.1rem 1.35rem 1.25rem; margin-bottom: 1.5rem; }
        .progress-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem; }
        .progress-title { font-size: 0.95rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
        .progress-title::before { content: ''; width: 4px; height: 16px; background: var(--secondary); border-radius: 2px; }
        .progress-pct { font-size: 1rem; font-weight: 700; color: var(--accent-text); }
        .progress-bar-track { width: 100%; height: 14px; background: rgba(0,51,102,0.08); border-radius: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, var(--secondary-dark) 0%, var(--secondary) 100%); border-radius: 10px; box-shadow: 0 0 12px rgba(255,215,0,0.4); transition: width 0.8s ease; }
        .progress-stats { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-2); margin-top: 8px; }

        /* ========== CHECKLIST ========== */
        .checklist-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-item .check-icon { font-size: 1rem; width: 24px; text-align: center; }
        .checklist-item .check-label { color: var(--text-1); flex: 1; }
        .checklist-item .check-status { font-size: 0.7rem; color: var(--text-2); }
        .checklist-item.completed .check-label { text-decoration: line-through; color: var(--text-3); }

        /* ========== SERVICE CARDS ========== */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .service-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            border-top: 3px solid var(--secondary);
            padding: 1.05rem 1.1rem;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-height: 130px;
            text-decoration: none;
            color: inherit;
        }
        .service-card:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(16,24,40,0.10); }
        .service-tag { font-size: 0.64rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--accent-text); }
        .service-name { font-size: 0.95rem; font-weight: 700; color: var(--text-1); line-height: 1.3; font-family: 'Libre Baskerville', Georgia, serif; }
        .service-desc { font-size: 0.78rem; color: var(--text-3); line-height: 1.45; flex: 1; }
        .service-foot { display: flex; align-items: center; justify-content: flex-end; margin-top: 0.25rem; }
        .service-btn {
            padding: 5px 12px;
            background: transparent;
            color: var(--accent-text);
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid var(--border-md);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .service-btn:hover { background: var(--primary); border-color: var(--primary); color: #fff; }
        body.dark-mode .service-btn:hover { background: var(--secondary); border-color: var(--secondary); color: #0d1117; }

        /* ========== REQUEST ITEMS ========== */
        .request-list { display: flex; flex-direction: column; }
        .request-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 6px 0;
            transition: background 0.2s ease;
        }
        .request-item:last-child { border-bottom: none; }
        .request-item:hover { background: var(--surface-2); }
        .request-item .req-info { display: flex; flex-direction: column; gap: 2px; }
        .request-item .req-name { font-size: 0.85rem; font-weight: 600; color: var(--accent-text); }
        body.dark-mode .request-item .req-name { color: var(--text-1); }
        .request-item .req-date { font-size: 0.72rem; color: var(--text-3); }
        .request-item .req-dept { font-size: 0.72rem; color: var(--text-3); margin-top: 2px; }
        .request-item .req-items { font-size: 0.72rem; color: var(--text-3); }

        /* ========== STATUS BADGES ========== */
        .status-badge { display: inline-block; padding: 3px 12px; font-weight: 600; font-size: 0.72rem; border-radius: 999px; white-space: nowrap; background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-badge.pending { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-badge.processing, .status-badge.accepted, .status-badge.verified { background: var(--st-info-bg); color: var(--st-info-fg); }
        .status-badge.completed, .status-badge.claimed, .status-badge.ready { background: var(--st-ok-bg); color: var(--st-ok-fg); }
        .status-badge.rejected { background: var(--st-bad-bg); color: var(--st-bad-fg); }

        /* Legacy status classes (kept for backward-compat with DB output) */
        .req-status.status-pending { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .req-status.status-processing { background: var(--st-info-bg); color: var(--st-info-fg); }
        .req-status.status-ready, .req-status.status-completed { background: var(--st-ok-bg); color: var(--st-ok-fg); }
        .req-status.status-rejected { background: var(--st-bad-bg); color: var(--st-bad-fg); }

        /* ========== EMPTY STATE ========== */
        .empty-state { padding: 2.5rem 1rem; text-align: center; color: var(--text-3); font-size: 0.85rem; }
        .empty-state .empty-icon { font-size: 1.8rem; margin-bottom: 0.4rem; }
        .empty-state .empty-title { font-size: 0.9rem; font-weight: 600; color: var(--text-2); margin-bottom: 0.25rem; }

        /* ========== BUTTONS ========== */
        .btn-primary { display: inline-flex; align-items: center; gap: 5px; padding: 9px 18px; background: var(--btn); color: #fff; font-size: 0.84rem; font-weight: 600; border: 1px solid var(--btn); border-radius: 8px; cursor: pointer; transition: background 0.15s; }
        .btn-primary:hover { background: var(--btn-hover); }
        .btn-secondary { display: inline-flex; align-items: center; gap: 5px; padding: 9px 18px; background: transparent; color: var(--text-2); font-size: 0.84rem; font-weight: 600; border: 1px solid var(--border-md); border-radius: 8px; cursor: pointer; transition: all 0.15s; }
        .btn-secondary:hover { background: var(--surface-3); }

        /* ========== MODAL ========== */
        .modal-backdrop { position: fixed; inset: 0; background: rgba(10,20,40,0.45); z-index: 400; display: flex; align-items: center; justify-content: center; padding: 1rem; opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; }
        .modal-backdrop.open { opacity: 1; visibility: visible; }
        .modal { background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg); width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto; border-radius: 12px; transform: scale(0.94); transition: transform 0.25s ease; }
        .modal-backdrop.open .modal { transform: scale(1); }
        .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--surface); z-index: 1; }
        .modal-title { font-size: 0.98rem; font-weight: 700; color: var(--text-1); font-family: 'Libre Baskerville', Georgia, serif; }
        .modal-close { width: 30px; height: 30px; border: none; background: var(--surface-3); color: var(--text-2); cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: background 0.15s; }
        .modal-close:hover { background: var(--border-md); color: var(--text-1); }
        .modal-body { padding: 1.25rem 1.4rem 1.4rem; }

        /* Profile modal */
        .profile-avatar-section { display: flex; align-items: center; gap: 1.25rem; padding: 0.75rem 0 1rem; border-bottom: 1px solid var(--border); margin-bottom: 1.25rem; }
        .profile-avatar { width: 64px; height: 64px; border-radius: 50%; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: var(--primary); flex-shrink: 0; }
        .profile-avatar-actions { display: flex; flex-direction: column; gap: 4px; }
        .change-photo-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: transparent; border: 1px solid var(--border-md); font-size: 0.75rem; font-weight: 500; color: var(--text-2); cursor: pointer; border-radius: 6px; transition: all 0.15s; }
        .change-photo-btn:hover { background: var(--surface-2); color: var(--text-1); }
        .helper-text { font-size: 0.65rem; color: var(--text-3); }
        .profile-form-group { margin-bottom: 1rem; }
        .profile-form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-2); margin-bottom: 4px; }
        .profile-form-group input, .profile-form-group select { width: 100%; padding: 9px 12px; border: 1px solid var(--border-md); font-size: 0.85rem; background: var(--surface); color: var(--text-1); border-radius: 8px; }
        .profile-form-group input:disabled { background: var(--surface-3); color: var(--text-2); cursor: not-allowed; }
        .profile-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .profile-error { display: none; font-size: 0.7rem; color: var(--st-bad-fg); margin-top: 3px; }
        .profile-success { display: none; font-size: 0.8rem; color: var(--st-ok-fg); background: var(--st-ok-bg); padding: 8px 12px; border-radius: 6px; margin-top: 0.5rem; }
        .profile-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .btn-save { padding: 9px 20px; background: var(--btn); color: #fff; border: 1px solid var(--btn); font-size: 0.84rem; font-weight: 600; cursor: pointer; border-radius: 8px; }
        .btn-save:hover { background: var(--btn-hover); }

        /* ========== OFF-CANVAS (profile) ========== */
        .offcanvas { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.92); width: 460px; max-width: 90vw; max-height: 80vh; background: var(--surface); box-shadow: 0 20px 60px rgba(0,0,0,0.2); z-index: 250; transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1), opacity 0.3s ease; display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; opacity: 0; visibility: hidden; pointer-events: none; }
        .offcanvas.open { transform: translate(-50%, -50%) scale(1); opacity: 1; visibility: visible; pointer-events: auto; }
        .offcanvas-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0; border-radius: 12px 12px 0 0; }
        .offcanvas-header h3 { font-size: 1rem; font-weight: 600; color: var(--text-1); margin: 0; display: flex; align-items: center; gap: 8px; }
        .offcanvas-close { width: 30px; height: 30px; border: none; background: var(--surface-3); color: var(--text-2); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; border-radius: 6px; }
        .offcanvas-close:hover { background: var(--border-md); color: var(--text-1); }
        .offcanvas-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
        .offcanvas-section { margin-bottom: 1.5rem; }
        .offcanvas-section:last-child { margin-bottom: 0; }
        .offcanvas-section-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); margin-bottom: 0.75rem; }
        .offcanvas-overlay { position: fixed; inset: 0; background: rgba(10,20,40,0.5); backdrop-filter: blur(4px); z-index: 249; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .offcanvas-overlay.open { opacity: 1; visibility: visible; }
        .profile-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem 1rem; font-size: 0.85rem; }
        .profile-info-grid .info-label { color: var(--text-3); font-weight: 400; }
        .profile-info-grid .info-value { color: var(--text-1); font-weight: 500; word-break: break-word; }
        .profile-header-card { display: flex; align-items: center; gap: 1.25rem; padding: 0.5rem 0 1.25rem; border-bottom: 1px solid var(--border); margin-bottom: 1.25rem; }
        .profile-header-card .profile-avatar-large { width: 60px; height: 60px; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; color: var(--primary); flex-shrink: 0; border-radius: 50%; }
        .profile-header-card .profile-name { font-size: 1.05rem; font-weight: 600; color: var(--text-1); }
        .profile-header-card .profile-role { font-size: 0.8rem; color: var(--text-3); }
        .profile-edit-btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 0.5rem; padding: 0.5rem 1.25rem; border: 1px solid var(--border-md); background: transparent; font-size: 0.8125rem; font-weight: 500; color: var(--text-2); cursor: pointer; transition: all 0.15s; border-radius: 8px; }
        .profile-edit-btn:hover { background: var(--surface-2); border-color: var(--text-3); }

        /* ========== FAB ========== */
        .fab { position: fixed; bottom: 28px; right: 28px; width: 52px; height: 52px; background: var(--primary); border: 2px solid var(--secondary); box-shadow: 0 6px 18px rgba(0,51,102,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 150; border-radius: 12px; }
        .fab:hover { transform: scale(1.05); background: var(--primary-light); }
        .fab svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }

        /* ========== RESPONSIVE ========== */
        @media(max-width:1024px){ .services-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width:768px){
            .offcanvas { width: 85vw; max-height: 85vh; }
            .header-nav-links { gap: 0.05rem; }
            .header-nav-link { font-size: 0.6rem; padding: 0.25rem 0.4rem; }
            .profile-info-grid { grid-template-columns: 1fr; gap: 0.5rem; }
            .profile-header-card { flex-direction: column; text-align: center; }
            .page-title { font-size: 1.4rem; }
            .request-item { flex-direction: column; align-items: flex-start; }
            .services-grid { grid-template-columns: 1fr 1fr; }
            .stat-box { flex-direction: column; gap: 4px; padding: 0.7rem 0.9rem; }
            .page { padding: 1.25rem 1rem 2.5rem; }
        }
        @media(max-width:480px){
            .header-nav-links { display: none !important; }
            .page { padding: 1rem; }
            .offcanvas { width: 92vw; max-height: 90vh; }
            .services-grid { grid-template-columns: 1fr; }
            .profile-form-row { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="../../includes/side_nav.css">
</head>
<body>

<!-- ========== SIDE NAV OVERLAY ========== -->
<div class="nav-overlay" id="sideNavOverlay" onclick="closeSideNav()"></div>

<!-- ========== SIDE NAV ========== -->
<div class="side-nav" id="sideNav">
    <div class="side-nav-header">
        <a href="#" class="side-nav-brand">UMak<span>·Registrar</span></a>
        <button class="side-nav-close" onclick="closeSideNav()">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="side-nav-body">
        <a href="../User_Dashboard.php" class="side-nav-link">
            <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"/></svg>
            Dashboard
        </a>
        <a href="certificates.php" class="side-nav-link">
            <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Certificates
        </a>
        <a href="tor.php" class="side-nav-link">
            <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            TOR
        </a>
        <a href="clearance.php" class="side-nav-link active">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Clearance
        </a>
        <a href="enrollment.php" class="side-nav-link">
            <svg viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            Enrollment
        </a>
        <a href="contact_registrar.php" class="side-nav-link">
            <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Contact Registrar
        </a>
        <a href="help_center.php" class="side-nav-link">
            <svg viewBox="0 0 24 24"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Help Center / FAQs
        </a>
        <div class="side-nav-divider"></div>
        <div class="side-nav-dark-toggle" onclick="toggleDarkModeFromSideNav()">
            <span style="display:flex;align-items:center;gap:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                Dark Mode
            </span>
            <span class="toggle-track"><span class="toggle-thumb"></span></span>
        </div>
    </div>
    <div class="side-nav-footer">&copy; 2026 UMak · Registrar</div>
</div>

<!-- ========== HEADER ========== -->
<?php include '../../includes/user_header.php'; ?>

<!-- ========== PROFILE SETTINGS MODAL ========== -->
<div class="modal-backdrop" id="profileModalBackdrop" onclick="if(event.target===this) closeProfileModal()">
    <div class="modal" id="profileModalBox">
        <div class="modal-head">
            <span class="modal-title">Profile Settings</span>
            <button class="modal-close" onclick="closeProfileModal()" aria-label="Close profile settings">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="profile-avatar-section">
                <div class="profile-avatar" id="profileAvatar"><?php echo $user_initials; ?></div>
                <div class="profile-avatar-actions">
                    <button type="button" class="change-photo-btn" id="changePhotoBtn" aria-label="Change photo">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Change Photo
                    </button>
                    <input type="file" id="photoInput" accept="image/jpeg,image/png" style="display:none">
                    <span class="helper-text">JPG or PNG, max 2MB</span>
                </div>
            </div>
            <form id="profileForm" novalidate>
                <div class="profile-form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" value="<?php echo htmlspecialchars($user_fullname); ?>" required>
                    <div class="profile-error" id="fullNameError">Full name is required.</div>
                </div>
                <div class="profile-form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                </div>
                <div class="profile-form-row">
                    <div class="profile-form-group">
                        <label for="studentId">Student ID</label>
                        <input type="text" id="studentId" value="<?php echo htmlspecialchars($user_student_id ?? '2026-12345'); ?>" disabled>
                    </div>
                    <div class="profile-form-group">
                        <label for="yearLevel">Year Level</label>
                        <select id="yearLevel">
                            <option value="1st" <?php echo ($user_year_level ?? '') == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd" <?php echo ($user_year_level ?? '') == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd" <?php echo ($user_year_level ?? '') == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th" <?php echo ($user_year_level ?? '') == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>
                </div>
                <div class="profile-form-group">
                    <label for="college">College</label>
                    <input type="text" id="college" value="<?php echo htmlspecialchars($user_college ?? 'College of Information Technology'); ?>" disabled>
                </div>
                <div class="profile-form-group">
                    <label for="course">Course</label>
                    <input type="text" id="course" value="<?php echo htmlspecialchars($user_course ?? 'BS Information Technology'); ?>" disabled>
                </div>
                <div class="profile-success" id="profileSuccess">Profile updated successfully!</div>
                <div class="profile-footer">
                    <button type="button" class="btn-secondary" onclick="closeProfileModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== OFF-CANVAS OVERLAY ========== -->
<div class="offcanvas-overlay" id="offcanvasOverlay" onclick="closeOffcanvas()"></div>

<!-- ========== OFF-CANVAS PANEL ========== -->
<div class="offcanvas" id="offcanvasPanel">
    <div class="offcanvas-header">
        <h3><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> My Profile</h3>
        <button class="offcanvas-close" onclick="closeOffcanvas()">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="offcanvas-body">
        <div class="profile-header-card">
            <div class="profile-avatar-large"><?php echo $user_initials; ?></div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($user_role); ?> · <?php echo htmlspecialchars($user_year_level ?? '3rd Year'); ?></div>
            </div>
        </div>
        <div class="offcanvas-section">
            <div class="offcanvas-section-title">Student Information</div>
            <div class="profile-info-grid">
                <span class="info-label">Student ID</span>
                <span class="info-value"><?php echo htmlspecialchars($user_student_id ?? '2026-XXXXX'); ?></span>
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($user_fullname); ?></span>
                <span class="info-label">Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
                <span class="info-label">College</span>
                <span class="info-value"><?php echo htmlspecialchars($user_college ?? 'College of Information Technology'); ?></span>
                <span class="info-label">Program / Course</span>
                <span class="info-value"><?php echo htmlspecialchars($user_course ?? 'Bachelor of Science in Information Technology'); ?></span>
                <span class="info-label">Year Level</span>
                <span class="info-value"><?php echo htmlspecialchars($user_year_level ?? '3rd Year'); ?></span>
                <span class="info-label">Academic Year</span>
                <span class="info-value">2026–2027</span>
            </div>
        </div>
        <button class="profile-edit-btn" onclick="closeOffcanvas(); openProfileModal();">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Edit Profile
        </button>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="dashboard-canvas">
<main class="page">

    <!-- Page Header -->
    <div class="page-head">
        <div class="crumb">
            <a href="../User_Dashboard.php">Dashboard</a>
            <span>/</span>
            <span style="color:var(--text-1);font-weight:500;">Clearance</span>
        </div>
        <h1 class="page-title">Clearance Management</h1>
        <p class="page-sub">Track your clearance progress and requirements.</p>
    </div>

    <!-- Stats strip -->
    <div class="card stats-grid">
        <div class="stat-box">
            <div class="stat-value"><?php echo $clearance_progress; ?>%</div>
            <div class="stat-label">Progress</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $completed_items; ?>/<?php echo $total_items; ?></div>
            <div class="stat-label">Completed Items</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo count($clearances); ?></div>
            <div class="stat-label">Requests</div>
        </div>
    </div>

    <!-- Clearance Progress -->
    <section class="progress-card card">
        <div class="progress-head">
            <div class="progress-title">Clearance Progress</div>
            <span class="progress-pct"><?php echo $clearance_progress; ?>%</span>
        </div>
        <div class="progress-bar-track">
            <div class="progress-bar-fill" style="width:<?php echo $clearance_progress; ?>%;"></div>
        </div>
        <div class="progress-stats">
            <span>Completed: <?php echo $completed_items; ?> / <?php echo $total_items; ?> items</span>
            <span><?php echo $total_items - $completed_items; ?> items remaining</span>
        </div>
    </section>

    <!-- Clearance Checklist -->
    <section>
        <div class="section-header">
            <h2 class="section-title">Clearance Checklist</h2>
        </div>
        <div class="card" style="padding:1rem 1.25rem 1.25rem;">
            <?php if (isset($clearance_items) && !empty($clearance_items)): ?>
                <?php foreach ($clearance_items as $section_key => $section): ?>
                    <div style="margin-bottom:1rem;">
                        <h4 style="font-size:0.85rem;font-weight:600;color:var(--text-2);margin-bottom:4px;"><?php echo htmlspecialchars($section['label']); ?></h4>
                        <?php foreach ($section['items'] as $item): ?>
                            <div class="checklist-item <?php echo $item['status'] === 'completed' ? 'completed' : ''; ?>">
                                <span class="check-icon"><?php echo $item['status'] === 'completed' ? '✅' : '⬜'; ?></span>
                                <span class="check-label"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="check-status"><?php echo $item['status'] === 'completed' ? 'Completed' : 'Pending'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-title">No clearance items available</div>
                    <div>Your checklist will appear once your clearance is processed.</div>
                </div>
            <?php endif; ?>
            <div style="margin-top:1rem;display:flex;justify-content:flex-end;gap:10px;">
                <button class="btn-primary" onclick="alert('📋 Refreshing clearance status...')">↻ Refresh Status</button>
            </div>
        </div>
    </section>

    <!-- Clearance Services -->
    <section>
        <div class="section-header">
            <h2 class="section-title">Clearance Services</h2>
        </div>
        <div class="services-grid">
            <div class="service-card" onclick="openClearanceRequest('graduation')">
                <div class="service-tag">Graduation</div>
                <div class="service-name">Graduation Clearance</div>
                <div class="service-desc">Complete requirements for graduation.</div>
                <div class="service-foot"><button type="button" class="service-btn" onclick="event.stopPropagation(); openClearanceRequest('graduation')">Request</button></div>
            </div>
            <div class="service-card" onclick="openClearanceRequest('exit')">
                <div class="service-tag">Exit</div>
                <div class="service-name">Exit Clearance</div>
                <div class="service-desc">For students leaving the university.</div>
                <div class="service-foot"><button type="button" class="service-btn" onclick="event.stopPropagation(); openClearanceRequest('exit')">Request</button></div>
            </div>
            <div class="service-card" onclick="openClearanceRequest('document')">
                <div class="service-tag">Document</div>
                <div class="service-name">Document Clearance</div>
                <div class="service-desc">Clearance for document release.</div>
                <div class="service-foot"><button type="button" class="service-btn" onclick="event.stopPropagation(); openClearanceRequest('document')">Request</button></div>
            </div>
            <div class="service-card" onclick="openClearanceRequest('final')">
                <div class="service-tag">Evaluation</div>
                <div class="service-name">Final Evaluation</div>
                <div class="service-desc">Check your overall clearance status.</div>
                <div class="service-foot"><button type="button" class="service-btn" onclick="event.stopPropagation(); openClearanceRequest('final')">Request</button></div>
            </div>
        </div>
    </section>

    <!-- Clearance Request History -->
    <section>
        <div class="section-header">
            <h2 class="section-title">My Clearance Requests</h2>
            <span class="section-meta">Total: <?php echo count($clearances); ?></span>
        </div>
        <div class="card" id="clearanceRequestsCard">
            <?php if (empty($clearances)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-title">No clearance requests yet</div>
                    <div>Click on any clearance service above to request.</div>
                </div>
            <?php else: ?>
                <div class="request-list">
                    <?php foreach ($clearances as $clearance): ?>
                        <div class="request-item">
                            <div class="req-info">
                                <span class="req-name"><?php echo htmlspecialchars($clearance['clearance_type']); ?></span>
                                <span class="req-date">📅 Requested: <?php echo date('M d, Y', strtotime($clearance['request_date'])); ?></span>
                                <span class="req-dept">🏢 Department: <?php echo htmlspecialchars($clearance['department']); ?></span>
                                <span class="req-items">📝 Progress: <?php echo htmlspecialchars($clearance['items_completed']); ?> completed</span>
                            </div>
                            <span class="req-status status-badge <?php echo $clearance['status_class'] ?? 'pending'; ?>">
                                <?php echo htmlspecialchars($clearance['status'] ?? 'Pending'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>
</div>

<!-- ============================================
     FOOTER — Included from includes/footer.php
     ============================================ -->
<?php include '../../includes/footer.php'; ?>

<button class="fab" onclick="openOffcanvas()" aria-label="Open profile panel">
    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
</button>

<script>
// ========== DARK MODE ==========
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); const isDark = document.body.classList.contains('dark-mode'); localStorage.setItem('darkMode', isDark); }
const savedDarkMode = localStorage.getItem('darkMode');
if (savedDarkMode === 'true') { document.body.classList.add('dark-mode'); }
function toggleDarkModeFromSideNav() { document.body.classList.toggle('dark-mode'); const isDark = document.body.classList.contains('dark-mode'); localStorage.setItem('darkMode', isDark); }

// ========== SIDE NAV ==========
function openSideNav() { document.getElementById('sideNav').classList.add('open'); document.getElementById('sideNavOverlay').classList.add('open'); }
function closeSideNav() { document.getElementById('sideNav').classList.remove('open'); document.getElementById('sideNavOverlay').classList.remove('open'); }
function handleSideNavAction(action) { if (action === 'My Profile') { openOffcanvas(); closeSideNav(); return; } alert('📌 ' + action + '\n\nThis will open the ' + action + ' section.'); closeSideNav(); }

// ========== DROPDOWN ==========
function toggleDropdown() { var dd = document.getElementById('profileDropdown'); if (dd) dd.classList.toggle('open'); }
function closeDropdown() { var dd = document.getElementById('profileDropdown'); if (dd) dd.classList.remove('open'); }
document.addEventListener('click', function(e) { var wrap = document.getElementById('profileWrap'); if (wrap && !wrap.contains(e.target)) closeDropdown(); });

// ========== PROFILE MODAL ==========
function openProfileModal() {
    closeDropdown();
    var backdrop = document.getElementById('profileModalBackdrop');
    if (!backdrop) return;
    document.getElementById('profileSuccess').style.display = 'none';
    document.getElementById('fullNameError').style.display = 'none';
    document.getElementById('profileForm').reset();
    document.getElementById('fullName').value = '<?php echo addslashes($user_fullname); ?>';
    document.getElementById('email').value = '<?php echo addslashes($user_email); ?>';
    document.getElementById('studentId').value = '<?php echo addslashes($user_student_id ?? '2026-12345'); ?>';
    document.getElementById('college').value = '<?php echo addslashes($user_college ?? 'College of Information Technology'); ?>';
    document.getElementById('course').value = '<?php echo addslashes($user_course ?? 'BS Information Technology'); ?>';
    document.getElementById('yearLevel').value = '<?php echo addslashes($user_year_level ?? '3rd'); ?>';
    document.getElementById('profileAvatar').textContent = '<?php echo $user_initials; ?>';
    backdrop.classList.add('open');
}
function closeProfileModal() {
    document.getElementById('profileModalBackdrop').classList.remove('open');
    document.getElementById('fullNameError').style.display = 'none';
    document.getElementById('profileSuccess').style.display = 'none';
}
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fullName = document.getElementById('fullName').value.trim();
    var errorEl = document.getElementById('fullNameError');
    var successEl = document.getElementById('profileSuccess');
    if (!fullName) { errorEl.style.display = 'block'; successEl.style.display = 'none'; return; }
    errorEl.style.display = 'none';
    successEl.style.display = 'block';
    var nameParts = fullName.split(' ');
    var initials = nameParts.length >= 2 ? nameParts[0].charAt(0).toUpperCase() + nameParts[1].charAt(0).toUpperCase() : nameParts[0].charAt(0).toUpperCase();
    document.getElementById('profileAvatar').textContent = initials || 'JD';
    setTimeout(function() { closeProfileModal(); alert('✅ Profile updated successfully!'); }, 800);
});
document.getElementById('changePhotoBtn').addEventListener('click', function() { document.getElementById('photoInput').click(); });
document.getElementById('photoInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png'].includes(file.type)) { alert('Please select a JPG or PNG image.'); this.value = ''; return; }
    if (file.size > 2 * 1024 * 1024) { alert('File size exceeds 2MB. Please select a smaller image.'); this.value = ''; return; }
    var reader = new FileReader();
    reader.onload = function(ev) { var avatar = document.getElementById('profileAvatar'); avatar.textContent = '📷'; setTimeout(function() { avatar.textContent = '<?php echo $user_initials; ?>'; }, 800); alert('✅ Photo updated successfully! (demo)'); };
    reader.readAsDataURL(file);
    this.value = '';
});

// ========== OFF-CANVAS ==========
function openOffcanvas() { document.getElementById('offcanvasPanel').classList.add('open'); document.getElementById('offcanvasOverlay').classList.add('open'); }
function closeOffcanvas() { document.getElementById('offcanvasPanel').classList.remove('open'); document.getElementById('offcanvasOverlay').classList.remove('open'); }

function doSignOut() { if (confirm('Are you sure you want to sign out?')) { window.location.href = '../../includes/logout.php'; } }

// ========== CLEARANCE REQUEST ==========
function openClearanceRequest(type) {
    var names = { 'graduation': 'Graduation Clearance', 'exit': 'Exit Clearance', 'document': 'Document Clearance', 'final': 'Final Evaluation' };
    var name = names[type] || 'Clearance';
    if (confirm('📋 Request ' + name + '?\n\nYou will be redirected to the request form.')) {
        alert('✅ ' + name + ' request submitted successfully!\n\nReference #: CLR-' + Date.now().toString().slice(-6) + '\n\nYou will be notified once your clearance is processed.');
    }
}
</script>
</body>
</html>