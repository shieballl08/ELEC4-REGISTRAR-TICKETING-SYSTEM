<?php
// ============================================
// ENABLE ERROR REPORTING FOR DEBUGGING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// START SESSION - MUST BE FIRST!
// ============================================
session_start();

// ============================================
// CHECK IF USER IS LOGGED IN
// ============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../Front_Page.php');
    exit();
}

// ============================================
// GET USER DATA FROM SESSION
// ============================================
$user_id = $_SESSION['user_id'] ?? 1;
$user_fullname = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? 'user@umak.edu.ph';
$user_role = $_SESSION['role'] ?? 'student';

// Get first name for welcome message
$user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;

// ============================================
// GET INITIALS FOR AVATAR
// ============================================
$nameParts = explode(' ', $user_fullname);
$user_initials = '';
if (count($nameParts) >= 2) {
    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $user_initials = strtoupper(substr($user_fullname, 0, 2));
}

// ============================================
// AUTO-DETECT DATABASE CONNECTION (POSTGRESQL)
// ============================================
$possiblePaths = [
    __DIR__ . '/../../config/db_connect_pg.php',
    __DIR__ . '/../config/db_connect_pg.php',
    'C:/xampp/htdocs/ELEC_4_SYSTEM/config/db_connect_pg.php'
];

$dbConnected = false;

foreach ($possiblePaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        if (class_exists('Database')) {
            $dbConnected = true;
            break;
        }
    }
}

// ============================================
// GET ADDITIONAL USER DATA FROM POSTGRESQL
// ============================================
$user_college = 'College of Information Technology';
$user_course = 'BS Information Technology';
$user_year_level = '3rd Year';
$user_student_id = '2026-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
$user_mobile = '';
$user_birthdate = '';

$pdo = null; // FIX: make sure $pdo is always defined

if ($dbConnected) {
    try {
        if (class_exists('Database')) {
            $database = new Database();
            $pdo = $database->getConnection();
        }
        
        if ($pdo) {
            // Get user data from PostgreSQL
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = $1");
            $stmt->execute([$user_id]);
            $userData = $stmt->fetch();
            
            if ($userData) {
                $user_fullname = $userData['full_name'] ?? $user_fullname;
                $user_email = $userData['email'] ?? $user_email;
                $user_mobile = $userData['mobile'] ?? '';
                $user_birthdate = $userData['birthdate'] ?? '';
                $user_role = $userData['role'] ?? 'student';
                
                // Update initials
                $nameParts = explode(' ', $user_fullname);
                if (count($nameParts) >= 2) {
                    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } else {
                    $user_initials = strtoupper(substr($user_fullname, 0, 2));
                }
            }
            
            // Get student data
            $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = $1");
            $stmt->execute([$user_id]);
            $studentData = $stmt->fetch();
            
            if ($studentData) {
                $user_student_id = $studentData['student_id'] ?? $user_student_id;
                $user_college = $studentData['college'] ?? $user_college;
                $user_course = $studentData['course'] ?? $user_course;
                // FIX: ?? doesn't work on a concatenation, so check explicitly
                $user_year_level = isset($studentData['year_level']) ? $studentData['year_level'] . ' Year' : $user_year_level;
            }
        }
    } catch (PDOException $e) {
        error_log("PostgreSQL Error in Announcements: " . $e->getMessage());
    }
}

// ============================================
// WHO CAN MANAGE ANNOUNCEMENTS (uses DB role)
// Adjust the role names to match your users table
// ============================================
$can_manage_announcements = in_array(strtolower($user_role), ['admin', 'registrar', 'staff']);

// ============================================
// GET ANNOUNCEMENTS FROM DATABASE
// ============================================
$announcements = [];

if ($dbConnected && $pdo) {
    try {
        // FIXED: column is "content" (not "body") + only show active announcements
        $stmt = $pdo->prepare("
            SELECT 
                id,
                title,
                content AS body,
                priority,
                created_at,
                updated_at
            FROM announcements 
            WHERE is_active = TRUE
            ORDER BY 
                CASE 
                    WHEN priority = 'urgent' THEN 1
                    WHEN priority = 'normal' THEN 2
                    WHEN priority = 'info' THEN 3
                    ELSE 4
                END,
                created_at DESC
            LIMIT 20
        ");
        $stmt->execute();
        $announcements = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching announcements: " . $e->getMessage());
    }
}

// ============================================
// ANNOUNCEMENT COUNTS (for stats strip + sidebar)
// ============================================
$count_total = count($announcements);
$count_urgent = 0; $count_normal = 0; $count_info = 0;
foreach ($announcements as $a) {
    $p = strtolower($a['priority'] ?? 'normal');
    if ($p === 'urgent') $count_urgent++;
    elseif ($p === 'info') $count_info++;
    else $count_normal++;
}

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
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Announcements</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <!-- Google Fonts -->
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
            /* status colors */
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

        /* ========== DARK MODE (header / nav only — body content uses variables) ========== */
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
        body.dark-mode .side-nav-divider { background: rgba(255,255,255,0.08); }
        body.dark-mode .dropdown-menu { background: #0d1117; border-color: rgba(255,255,255,0.08); }
        body.dark-mode .dd-header { border-bottom-color: rgba(255,255,255,0.08); }
        body.dark-mode .dd-item:hover { background: #3a3a55; }
        body.dark-mode .dd-item.danger { color: #ef4444; }
        body.dark-mode .dd-item.danger:hover { background: rgba(220,38,38,0.15); }
        body.dark-mode .dd-divider { background: rgba(255,255,255,0.08); }
        body.dark-mode .avatar-btn { background: rgba(255,215,0,0.08); border-color: rgba(255,215,0,0.12); }
        body.dark-mode .avatar-btn:hover { background: rgba(255,215,0,0.15); }
        body.dark-mode .avatar-circle { background: #ffd700; color: #0d1117; }
        body.dark-mode .avatar-name { color: #e6edf3; }
        body.dark-mode .hamburger-btn { color: rgba(255,255,255,0.8); }
        body.dark-mode .hamburger-btn:hover { background: rgba(255,215,0,0.12); }
        body.dark-mode .dropdown-popup { background: #1c2128; border-color: rgba(255,255,255,0.08); box-shadow: 0 12px 40px rgba(0,0,0,0.5); }
        body.dark-mode .dropdown-popup .popup-item { color: #e6edf3; }
        body.dark-mode .dropdown-popup .popup-item:hover { background: rgba(255,215,0,0.08); }
        body.dark-mode .dropdown-popup .popup-item .item-desc { color: #8a8aaa; }
        body.dark-mode .dropdown-popup .popup-divider { background: rgba(255,255,255,0.06); }
        body.dark-mode .dropdown-popup::before { border-bottom-color: #1c2128; }
        body.dark-mode .icon-btn { color: rgba(255,255,255,0.6); }
        body.dark-mode .icon-btn:hover { color: #fff; background: rgba(255,215,0,0.12); }
        body.dark-mode .notif-dot { border-color: #161b22; }
        body.dark-mode .nav-overlay,
        body.dark-mode .offcanvas-overlay,
        body.dark-mode .service-modal-backdrop { background: rgba(0,0,0,0.6); }
        body.dark-mode footer { border-top-color: rgba(255,255,255,0.08) !important; }
        body.dark-mode .side-nav-dark-toggle { color: #e6edf3; }
        body.dark-mode .side-nav-dark-toggle:hover { background: #3a3a55; }
        body.dark-mode .side-nav-dark-toggle .toggle-track { background: #ffd700; }
        body.dark-mode .side-nav-dark-toggle .toggle-track .toggle-thumb { transform: translateX(18px); background: #0d1117; }

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
        .nav-dropdown { position: relative; display: inline-block; }
        .header-nav-link { color: rgba(255,255,255,0.6); padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.7rem; text-decoration: none; transition: all 0.2s ease; white-space: nowrap; background: transparent !important; border: none !important; cursor: pointer; font-weight: 400; letter-spacing: 0.02em; }
        .header-nav-link:hover { color: rgba(255,255,255,0.95); background: rgba(255,215,0,0.08) !important; }
        .header-nav-link.active { color: #ffffff !important; background: rgba(255,215,0,0.12) !important; font-weight: 500; }

        .dropdown-popup { position: absolute; top: calc(100% + 6px); left: 50%; transform: translateX(-50%) scale(0.95) translateY(-8px); min-width: 200px; background: #ffffff; box-shadow: 0 12px 40px rgba(0,0,0,0.12), 0 4px 12px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.06); padding: 0.5rem 0; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), visibility 0.2s ease; z-index: 120; }
        .nav-dropdown:hover .dropdown-popup { opacity: 1; visibility: visible; transform: translateX(-50%) scale(1) translateY(0); pointer-events: auto; }
        .dropdown-popup .popup-item { display: block; padding: 0.6rem 1.2rem; font-size: 0.75rem; color: #1a2a3a; text-decoration: none; transition: background 0.15s ease; cursor: pointer; border: none; background: transparent; width: 100%; text-align: left; font-weight: 400; letter-spacing: 0.01em; }
        .dropdown-popup .popup-item:hover { background: rgba(0,51,102,0.06); }
        .dropdown-popup .popup-item .item-desc { display: block; font-size: 0.6rem; color: #8a9aaa; font-weight: 400; margin-top: 1px; }
        .dropdown-popup .popup-divider { height: 1px; background: rgba(0,0,0,0.06); margin: 0.25rem 0.5rem; }
        .dropdown-popup::before { content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); border: 6px solid transparent; border-bottom-color: #ffffff; }

        .nav-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; margin-left: 0.5rem; }
        .icon-btn { width: 36px; height: 36px; border: none; background: transparent; cursor: pointer; color: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; transition: color 0.15s, background 0.15s; position: relative; }
        .icon-btn:hover { color: #fff; background: rgba(255,215,0,0.15); }
        .icon-btn svg { width: 18px; height: 18px; }
        .notif-dot { position: absolute; top: 5px; right: 5px; width: 7px; height: 7px; background: #ef4444; border-radius: 50%; border: 1.5px solid var(--primary); }
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

        /* Dark mode toggle in side nav */
        .side-nav-dark-toggle { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.9rem; font-size: 0.85rem; font-weight: 500; color: var(--text-2); transition: background 0.12s, color 0.12s; margin-bottom: 2px; cursor: pointer; }
        .side-nav-dark-toggle:hover { background: var(--surface-2); color: var(--text-1); }
        .side-nav-dark-toggle .toggle-track { width: 40px; height: 22px; background: #ccc; border-radius: 12px; position: relative; transition: background 0.3s ease; flex-shrink: 0; }
        .side-nav-dark-toggle .toggle-track .toggle-thumb { width: 18px; height: 18px; background: white; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.3s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }

        /* ========== PAGE ========== */
        .page { max-width: 1280px; margin: 0 auto; padding: 1.75rem 1.5rem 3rem; }
        /* Soft ombre header glow (light, airy, brand-tinted) */
        .dashboard-canvas {
            min-height: calc(100vh - 68px);
            background:
                radial-gradient(900px 260px at 18% 0%, rgba(120,170,225,0.55) 0%, rgba(120,170,225,0) 70%),
                radial-gradient(700px 240px at 85% 0%, rgba(255,215,0,0.22) 0%, rgba(255,215,0,0) 70%),
                linear-gradient(180deg, #cddff2 0%, #e3edf8 170px, #f4f7fb 380px, #ffffff 100%);
            background-repeat: no-repeat;
        }
        body.dark-mode .dashboard-canvas { background: linear-gradient(180deg, #0b1626 0%, #0d1117 320px); }

        .content-layout { display: grid; grid-template-columns: 1fr 290px; gap: 1.75rem; align-items: start; }
        .main-content { min-width: 0; }

        .page-head { padding: 0.75rem 0 0.5rem; margin-bottom: 1.5rem; display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .page-title { font-family: 'Inter', system-ui, sans-serif; font-size: 2rem; font-weight: 700; color: var(--accent-text); letter-spacing: -0.015em; }
        .page-sub { font-size: 0.85rem; color: var(--text-2); margin-top: 4px; }
        body.dark-mode .page-title { color: var(--text-1); }

        /* ========== CARD BASE ========== */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--shadow-sm); transition: background 0.3s ease, border-color 0.3s ease; }
        .card-head { display: flex; align-items: center; justify-content: space-between; padding: 1.125rem 1.375rem 0; }
        .card-title { display: flex; align-items: center; gap: 8px; font-size: 0.9375rem; font-weight: 600; color: var(--text-1); }
        .card-body { padding: 1rem 1.375rem 1.375rem; }

        /* ========== BUTTONS ========== */
        .btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; font-size: 0.84rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.15s; background: var(--btn); color: #fff; border: 1px solid var(--btn); text-decoration: none; }
        .btn-primary:hover { background: var(--btn-hover); }

        /* ========== STATS STRIP ========== */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); margin-bottom: 1.1rem; }
        .stat-box { padding: 0.8rem 1.25rem; display: flex; align-items: baseline; gap: 10px; border-right: 1px solid var(--border); }
        .stat-box:last-child { border-right: none; }
        .stat-box .stat-value { font-size: 1.35rem; font-weight: 700; color: var(--accent-text); line-height: 1; }
        .stat-box .stat-label { font-size: 0.76rem; color: var(--text-3); }

        /* ========== TOOLBAR (search + filters) ========== */
        .toolbar { padding: 0.9rem; margin-bottom: 1.25rem; }
        .pup-search-form { display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: center; }
        .search-field { position: relative; }
        .search-field svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: var(--text-3); fill: none; stroke-width: 2; pointer-events: none; }
        .pup-search-form input[type=text] { width: 100%; padding: 9px 12px 9px 36px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-1); font-size: 0.85rem; border-radius: 8px; transition: border-color 0.15s, box-shadow 0.15s; }
        .pup-search-form input:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(0,51,102,0.10); }
        .pup-search-actions { display: flex; gap: 0.5rem; }
        .pup-clear-btn { padding: 9px 16px; background: transparent; color: var(--text-2); font-size: 0.82rem; font-weight: 600; border: 1px solid var(--border-md); border-radius: 8px; cursor: pointer; transition: background 0.15s; }
        .pup-clear-btn:hover { background: var(--surface-3); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 0.8rem; }
        .chip { padding: 5px 12px; font-size: 0.76rem; font-weight: 500; color: var(--text-2); background: var(--surface); border: 1px solid var(--border-md); border-radius: 999px; cursor: pointer; transition: all 0.15s; }
        .chip:hover { border-color: var(--primary-light); color: var(--text-1); }
        .chip.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        body.dark-mode .chip.active { background: var(--secondary); border-color: var(--secondary); color: #0d1117; }

        .empty-state { padding: 2.5rem 1rem; text-align: center; color: var(--text-3); font-size: 0.85rem; display: none; }
        .empty-state.show { display: block; }

        /* ========== ANNOUNCEMENTS ========== */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin: 0 0 0.75rem; flex-wrap: wrap; gap: 0.75rem; }
        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 18px; background: var(--secondary); border-radius: 2px; }

        .announcement-item { display: flex; gap: 14px; padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--border); border-left: 3px solid transparent; transition: background 0.12s; }
        .announcement-item:hover { background: var(--surface-2); }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-item.is-hidden { display: none; }
        .announcement-item[data-priority="urgent"] { border-left-color: #dc2626; }
        .announcement-item[data-priority="normal"] { border-left-color: var(--secondary); }
        .announcement-item[data-priority="info"]   { border-left-color: var(--primary-light); }
        .announcement-item .ann-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--surface-3); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; color: var(--text-3); }
        .announcement-item .ann-content { flex: 1; min-width: 0; }
        .announcement-item .ann-title { font-size: 0.95rem; font-weight: 600; color: var(--text-1); margin-bottom: 4px; }
        .announcement-item .ann-body { font-size: 0.85rem; color: var(--text-2); line-height: 1.6; }
        .announcement-item .ann-meta { display: flex; align-items: center; gap: 12px; margin-top: 8px; font-size: 0.72rem; color: var(--text-3); }
        .announcement-item .ann-priority { font-size: 0.66rem; font-weight: 600; padding: 3px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; }
        .ann-priority.urgent { background: var(--st-bad-bg); color: var(--st-bad-fg); }
        .ann-priority.normal { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .ann-priority.info   { background: var(--st-info-bg); color: var(--st-info-fg); }

        /* ========== SIDEBAR ========== */
        .sidebar { display: flex; flex-direction: column; gap: 1.75rem; position: sticky; top: 90px; }
        .sidebar-box.card { background: transparent; border: none; box-shadow: none; padding: 0; }
        .sidebar-title { font-size: 0.82rem; font-weight: 500; letter-spacing: 0.04em; color: var(--accent-text); padding-bottom: 0.7rem; margin-bottom: 0.6rem; position: relative; }
        .sidebar-title::after { content: ''; position: absolute; left: 0; bottom: 0; width: 48px; height: 2px; background: var(--secondary); }
        body.dark-mode .sidebar-title { color: var(--text-1); }
        .sidebar-list { list-style: none; }
        .sidebar-list li { padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.8rem; }
        .sidebar-list li:last-child { border-bottom: none; }
        .sidebar-list li a { color: var(--text-2); text-decoration: none; transition: color 0.15s; display: flex; align-items: center; gap: 8px; }
        .sidebar-list li a:hover { color: var(--accent-text); }
        .sidebar-list li a::before { content: ''; width: 4px; height: 4px; border-radius: 50%; background: var(--text-3); flex-shrink: 0; }
        .sidebar-list li a .cat-count { margin-left: auto; font-size: 0.66rem; color: var(--accent-text); background: var(--accent-soft); padding: 1px 7px; border-radius: 999px; font-weight: 600; }

        /* ========== MODALS ========== */
        .service-modal-backdrop, .modal-backdrop { position: fixed; inset: 0; background: rgba(10,20,40,0.45); z-index: 500; display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-backdrop { z-index: 400; }
        .service-modal-backdrop.open, .modal-backdrop.open { display: flex; }
        .service-modal, .modal { background: var(--surface); border-radius: 12px; box-shadow: var(--shadow-lg); width: 100%; max-width: 420px; max-height: 85vh; overflow: auto; animation: modalIn 0.2s ease; border: 1px solid var(--border); }
        .modal { max-width: 440px; }
        @keyframes modalIn { from { opacity:0; transform: scale(0.94) translateY(-12px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .service-modal-head, .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem 0.5rem; }
        .service-modal-title, .modal-title { font-family: 'Libre Baskerville', Georgia, serif; font-size: 1rem; font-weight: 700; color: var(--text-1); }
        .service-modal-close, .modal-close { width: 30px; height: 30px; border: none; background: var(--surface-3); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-2); border-radius: 6px; margin-left: auto; }
        .service-modal-close:hover, .modal-close:hover { background: var(--border-md); color: var(--text-1); }
        .service-modal-close svg, .modal-close svg { width: 15px; height: 15px; }
        .service-modal-body, .modal-body { padding: 0.5rem 1.5rem 1.5rem; }
        .service-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; cursor: pointer; transition: background 0.15s, color 0.15s; color: var(--text-2); font-size: 0.85rem; border: 1px solid transparent; border-radius: 8px; margin-bottom: 4px; }
        .service-item:hover { background: var(--surface-2); border-color: var(--border); color: var(--text-1); }
        .service-item .label { font-weight: 500; }
        .service-item .desc { font-size: 0.7rem; color: var(--text-3); margin-left: auto; }
        .service-divider { height: 1px; background: var(--border); margin: 6px 0; }
        body.dark-mode .service-modal-close, body.dark-mode .modal-close { background: #3a3a55; color: #b0b0c8; }

        /* ========== OFF-CANVAS (profile) ========== */
        .offcanvas { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.92); width: 460px; max-width: 90vw; max-height: 80vh; background: var(--surface); box-shadow: 0 20px 60px rgba(0,0,0,0.2); z-index: 250; transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1), opacity 0.3s ease; display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; opacity: 0; visibility: hidden; pointer-events: none; }
        .offcanvas.open { transform: translate(-50%, -50%) scale(1); opacity: 1; visibility: visible; pointer-events: auto; }
        .offcanvas-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); background: var(--surface); border-radius: 12px 12px 0 0; flex-shrink: 0; }
        .offcanvas-header h3 { font-size: 1rem; font-weight: 600; color: var(--text-1); margin: 0; display: flex; align-items: center; gap: 8px; }
        .offcanvas-close { width: 30px; height: 30px; border: none; background: var(--surface-3); color: var(--text-2); cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 6px; }
        .offcanvas-close:hover { background: var(--border-md); color: var(--text-1); }
        body.dark-mode .offcanvas-close { background: #3a3a55; color: #b0b0c8; }
        .offcanvas-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
        .offcanvas-section { margin-bottom: 1.5rem; }
        .offcanvas-section:last-child { margin-bottom: 0; }
        .offcanvas-section-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); margin-bottom: 0.75rem; }
        .offcanvas-overlay { position: fixed; inset: 0; background: rgba(10,20,40,0.5); backdrop-filter: blur(4px); z-index: 249; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .offcanvas-overlay.open { opacity: 1; visibility: visible; }

        .profile-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem 1rem; font-size: 0.85rem; }
        .profile-info-grid .info-label { color: var(--text-3); }
        .profile-info-grid .info-value { color: var(--text-1); font-weight: 500; word-break: break-word; }
        .profile-info-grid .info-full { grid-column: 1 / -1; }
        .profile-header-card { display: flex; align-items: center; gap: 1.25rem; padding: 0.5rem 0 1.25rem; border-bottom: 1px solid var(--border); margin-bottom: 1.25rem; }
        .profile-header-card .profile-avatar-large { width: 60px; height: 60px; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; color: var(--primary); flex-shrink: 0; border-radius: 50%; }
        .profile-header-card .profile-name { font-size: 1.05rem; font-weight: 600; color: var(--text-1); }
        .profile-header-card .profile-role { font-size: 0.8rem; color: var(--text-3); text-transform: capitalize; }
        .profile-edit-btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 0.5rem; padding: 0.5rem 1.25rem; border: 1px solid var(--border-md); border-radius: 8px; background: transparent; font-size: 0.8125rem; font-weight: 500; color: var(--text-2); cursor: pointer; transition: all 0.15s; }
        .profile-edit-btn:hover { background: var(--surface-3); }

        /* ========== FAB ========== */
        .fab { position: fixed; bottom: 28px; right: 28px; width: 52px; height: 52px; background: var(--primary); border: 2px solid var(--secondary); box-shadow: 0 6px 18px rgba(0,51,102,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 150; border-radius: 12px; }
        .fab:hover { transform: scale(1.05); background: var(--primary-light); }
        .fab svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }
        body.dark-mode .fab { background: #1a4d7a; border-color: #ffd700; }

        /* ========== FADE SCROLL ========== */
        .fade-scroll { opacity: 0; transform: translateY(30px); transition: opacity 0.8s cubic-bezier(0.2, 0.9, 0.4, 1), transform 0.8s cubic-bezier(0.2, 0.9, 0.4, 1); }
        .fade-scroll.visible { opacity: 1; transform: translateY(0); }

        /* ========== RESPONSIVE ========== */
        @media(max-width:1000px){
            .content-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; order: 2; }
            .main-content { order: 1; }
        }
        @media(max-width:768px){
            .offcanvas { width: 85vw; max-height: 85vh; }
            .header-nav-links { gap: 0.05rem; }
            .header-nav-link { font-size: 0.6rem; padding: 0.25rem 0.4rem; }
            .profile-info-grid { grid-template-columns: 1fr; gap: 0.5rem; }
            .profile-header-card { flex-direction: column; text-align: center; }
            .page-title { font-size: 1.3rem; }
            .stat-box { flex-direction: column; gap: 4px; padding: 0.7rem 0.9rem; }
            .announcement-item { padding: 1rem; }
        }
        @media(max-width:480px){
            .header-nav-links { display: none !important; }
            .page { padding: 1rem; }
            .offcanvas { width: 92vw; max-height: 90vh; }
        }
    </style>
    <link rel="stylesheet" href="../../includes/side_nav.css">
</head>
<body>

<!-- ========== SIDE NAV ========== -->
<?php include '../../includes/side_nav.php'; ?>
<!-- ========== HEADER ========== -->
<?php include '../../includes/user_header.php'; ?>

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
                <div class="profile-role"><?php echo htmlspecialchars($user_role); ?></div>
            </div>
        </div>
        <div class="offcanvas-section">
            <div class="offcanvas-section-title">Student Information</div>
            <div class="profile-info-grid">
                <span class="info-label">Student ID</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['student_id'] ?? '2026-XXXXX'); ?></span>
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($user_fullname); ?></span>
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
                <span class="info-label">College</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['college'] ?? 'College of Information Technology'); ?></span>
                <span class="info-label">Course</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['course'] ?? 'BS Information Technology'); ?></span>
                <span class="info-label">Year Level</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['year_level'] ?? '3rd Year'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ========== SERVICE MODAL ========== -->
<div class="service-modal-backdrop" id="serviceModalBackdrop" onclick="if(event.target===this) closeServiceModal()">
    <div class="service-modal" id="serviceModalBox">
        <div class="service-modal-head">
            
            <button class="service-modal-close" onclick="closeServiceModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="service-modal-body" id="serviceModalBody"></div>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="dashboard-canvas">
<main class="page">

    <div class="page-head">
        <div>
            <h1 class="page-title">Announcements</h1>
            <p class="page-sub">Stay updated with the latest news and notices from the Registrar's Office.</p>
        </div>
        <?php if ($can_manage_announcements): ?>
        <button class="btn-primary" onclick="alert('Create new announcement')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
            New Announcement
        </button>
        <?php endif; ?>
    </div>

    <div class="content-layout">

        <!-- MAIN -->
        <div class="main-content">

            <!-- Stats strip -->
            <div class="card stats-grid">
                <div class="stat-box"><div class="stat-value"><?php echo $count_total; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-box"><div class="stat-value"><?php echo $count_urgent; ?></div><div class="stat-label">Urgent</div></div>
                <div class="stat-box"><div class="stat-value"><?php echo $count_normal + $count_info; ?></div><div class="stat-label">Other Notices</div></div>
            </div>

            <!-- Search + filter -->
            <div class="card toolbar">
                <form class="pup-search-form" onsubmit="event.preventDefault(); filterAnnouncements();">
                    <div class="search-field">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                        <input type="text" id="annSearch" placeholder="Search announcements..." onkeyup="filterAnnouncements()">
                    </div>
                    <div class="pup-search-actions">
                        <button type="button" class="pup-clear-btn" onclick="clearAnnFilters()">Clear</button>
                    </div>
                </form>
                <div class="chips" id="annChips">
                    <button type="button" class="chip active" data-priority="all" onclick="setAnnPriority('all')">All</button>
                    <button type="button" class="chip" data-priority="urgent" onclick="setAnnPriority('urgent')">Urgent</button>
                    <button type="button" class="chip" data-priority="normal" onclick="setAnnPriority('normal')">Normal</button>
                    <button type="button" class="chip" data-priority="info" onclick="setAnnPriority('info')">Info</button>
                </div>
            </div>

            <div class="section-header">
                <h2 class="section-title">Latest Announcements</h2>
            </div>

            <div class="card fade-scroll">
                <div class="card-body" style="padding:0;">
                    <?php if (empty($announcements)): ?>
                        <div style="padding:2.5rem 1rem;text-align:center;color:var(--text-3);">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            No announcements available.
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="announcement-item"
                                 data-priority="<?php echo htmlspecialchars(strtolower($ann['priority'] ?? 'normal')); ?>"
                                 data-title="<?php echo htmlspecialchars(strtolower($ann['title'])); ?>"
                                 data-body="<?php echo htmlspecialchars(strtolower($ann['body'])); ?>">
                                <div class="ann-icon">
                                    <?php 
                                        $icon = '📢';
                                        if ($ann['priority'] === 'urgent') $icon = '🚨';
                                        elseif ($ann['priority'] === 'info') $icon = 'ℹ️';
                                        echo $icon;
                                    ?>
                                </div>
                                <div class="ann-content">
                                    <div class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                    <div class="ann-body"><?php echo htmlspecialchars($ann['body']); ?></div>
                                    <div class="ann-meta">
                                        <span class="ann-priority <?php echo htmlspecialchars($ann['priority'] ?? 'normal'); ?>">
                                            <?php echo htmlspecialchars($ann['priority'] ?? 'normal'); ?>
                                        </span>
                                        <span><?php echo date('M d, Y g:i A', strtotime($ann['created_at'] ?? 'now')); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="empty-state" id="annEmpty">No announcements match your search. Try a different keyword or clear the filters.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="card sidebar-box">
                <div class="sidebar-title">Announcements</div>
                <ul class="sidebar-list">
                    <li><a href="#" onclick="setAnnPriority('all'); return false;">All Announcements <span class="cat-count"><?php echo $count_total; ?></span></a></li>
                    <li><a href="#" onclick="setAnnPriority('urgent'); return false;">Urgent <span class="cat-count"><?php echo $count_urgent; ?></span></a></li>
                    <li><a href="#" onclick="setAnnPriority('normal'); return false;">Normal <span class="cat-count"><?php echo $count_normal; ?></span></a></li>
                    <li><a href="#" onclick="setAnnPriority('info'); return false;">Info <span class="cat-count"><?php echo $count_info; ?></span></a></li>
                </ul>
            </div>

            <div class="card sidebar-box">
                <div class="sidebar-title">Other Information</div>
                <ul class="sidebar-list">
                    <li><a href="#">Registrar Announcements</a></li>
                    <li><a href="#">News and Articles</a></li>
                    <li><a href="#">Events</a></li>
                    <li><a href="#">Frequently Asked Questions</a></li>
                    <li><a href="#">Contact Registrar</a></li>
                </ul>
            </div>

            <div class="card sidebar-box">
                <div class="sidebar-title">Quick Links</div>
                <ul class="sidebar-list">
                    <li><a href="academic_records.php">Academic Records</a></li>
                    <li><a href="requests.php">My Requests</a></li>
                    <li><a href="clearances.php">Clearances</a></li>
                    <li><a href="help_center.php">Help Center</a></li>
                </ul>
            </div>
        </aside>
    </div>
</main>
</div>

<footer style="border-top:1px solid var(--border);padding:1.25rem;text-align:center;transition:border-color 0.3s ease;margin-top:1.5rem;">
    <p style="font-size:.75rem;color:var(--text-3);transition:color 0.3s ease;">
        &copy; 2026 University of Makati · Registrar's Office · All rights reserved.
    </p>
</footer>

<button class="fab" onclick="openOffcanvas()" aria-label="Open profile panel">
    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
</button>

<script>
// ========== DARK MODE ==========
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
}

const savedDarkMode = localStorage.getItem('darkMode');
if (savedDarkMode === 'true') {
    document.body.classList.add('dark-mode');
}

function toggleDarkModeFromSideNav() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
}

// ========== SIDE NAV ==========
function openSideNav() {
    document.getElementById('sideNav').classList.add('open');
    document.getElementById('sideNavOverlay').classList.add('open');
    closeDropdown();
}
function closeSideNav() {
    document.getElementById('sideNav').classList.remove('open');
    document.getElementById('sideNavOverlay').classList.remove('open');
}

// ========== DROPDOWN ==========
function toggleDropdown() {
    document.getElementById('profileDropdown').classList.toggle('open');
}
function closeDropdown() {
    document.getElementById('profileDropdown').classList.remove('open');
}
document.addEventListener('click', function(e) {
    if (!document.getElementById('profileWrap').contains(e.target)) closeDropdown();
});

// ========== SERVICE MODAL ==========
var serviceData = {
    enrollment: { title: 'Enrollment Services', services: [{ label: 'Enlist Subjects', desc: 'Add subjects' }, { label: 'Assessment', desc: 'View fees' }, { label: 'Add/Drop', desc: 'Change subjects' }, { label: 'Confirm Enrollment', desc: 'Finalize' }] },
    grades: { title: 'Grades Services', services: [{ label: 'Encode Grades', desc: 'Input grades' }, { label: 'View Grades', desc: 'Student grades' }, { label: 'Grade Revision', desc: 'Correct grades' }, { label: "Dean's List", desc: 'Honor students' }] },
    tor: { title: 'TOR Services', services: [{ label: 'Request TOR', desc: 'New request' }, { label: 'Processing', desc: 'In-progress' }, { label: 'For Release', desc: 'Ready to claim' }, { label: 'History', desc: 'Previous requests' }] },
    certificates: { title: 'Certificates Services', services: [{ label: 'Certificate of Enrollment', desc: 'COE' }, { label: 'Good Moral', desc: 'Character' }, { label: 'Certificate of Graduation', desc: 'COG' }, { label: 'Authentication', desc: 'Verify docs' }] },
    records: { title: 'Student Records', services: [{ label: 'Search Student', desc: 'Find student' }, { label: 'Masterlist', desc: 'All students' }, { label: 'Statistics', desc: 'Demographics' }, { label: 'Update Status', desc: 'Active/Inactive' }] },
    reports: { title: 'Reports Services', services: [{ label: 'By College', desc: 'Per college' }, { label: 'By Course', desc: 'Per course' }, { label: 'By Year Level', desc: 'Per year' }, { label: 'Statistics', desc: 'Enrollment data' }] },
    clearance: { title: 'Clearance Services', services: [{ label: 'Graduation Clearance', desc: 'For graduation' }, { label: 'Exit Clearance', desc: 'For leaving' }, { label: 'Document Clearance', desc: 'Doc release' }, { label: 'Final Evaluation', desc: 'Check status' }] }
};

function openServiceModal(type) {
    var data = serviceData[type];
    if (!data) return;
    closeDropdown();
    var title = document.getElementById('serviceModalTitle');
    var body = document.getElementById('serviceModalBody');
    title.textContent = data.title;
    var html = '';
    data.services.forEach(function(service, index) {
        html += '<div class="service-item" onclick="handleServiceAction(\'' + type + '\', \'' + service.label + '\')"><span class="label">' + service.label + '</span><span class="desc">' + service.desc + '</span></div>';
        if (index < data.services.length - 1) html += '<div class="service-divider"></div>';
    });
    body.innerHTML = html;
    document.getElementById('serviceModalBackdrop').classList.add('open');
}

function closeServiceModal() {
    document.getElementById('serviceModalBackdrop').classList.remove('open');
}

function handleServiceAction(category, action) {
    alert('📌 ' + category.toUpperCase() + ' → ' + action + '\n\nThis will open the ' + action + ' module.');
    closeServiceModal();
}

function handlePopupAction(category, action) {
    alert('📌 ' + category.toUpperCase() + ' → ' + action + '\n\nThis will open the ' + action + ' module.');
}

// ========== OFF-CANVAS ==========
function openOffcanvas() {
    document.getElementById('offcanvasPanel').classList.add('open');
    document.getElementById('offcanvasOverlay').classList.add('open');
    closeDropdown();
}
function closeOffcanvas() {
    document.getElementById('offcanvasPanel').classList.remove('open');
    document.getElementById('offcanvasOverlay').classList.remove('open');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeOffcanvas(); closeServiceModal(); }
});

// ========== SIGN OUT ==========
function doSignOut() {
    if (confirm('Are you sure you want to sign out?')) {
        window.location.href = '../../includes/logout.php';  // 2 LEVELS UP!
    }
}

// ========== SEARCH + FILTER (announcements) ==========
var activeAnnPriority = 'all';
function setAnnPriority(p) {
    activeAnnPriority = p;
    document.querySelectorAll('#annChips .chip').forEach(function(c) {
        c.classList.toggle('active', c.getAttribute('data-priority') === p);
    });
    filterAnnouncements();
}
function filterAnnouncements() {
    var q = document.getElementById('annSearch').value.toLowerCase().trim();
    var any = false;
    document.querySelectorAll('.announcement-item').forEach(function(item) {
        var okQ = q === '' || item.getAttribute('data-title').indexOf(q) !== -1 || item.getAttribute('data-body').indexOf(q) !== -1;
        var okP = activeAnnPriority === 'all' || item.getAttribute('data-priority') === activeAnnPriority;
        item.classList.toggle('is-hidden', !(okQ && okP));
        if (okQ && okP) any = true;
    });
    var empty = document.getElementById('annEmpty');
    if (empty) empty.classList.toggle('show', !any);
}
function clearAnnFilters() {
    document.getElementById('annSearch').value = '';
    setAnnPriority('all');
}

// ========== FADE SCROLL REVEAL ==========
var fadeObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.15, rootMargin: "0px 0px -30px 0px" });

document.querySelectorAll('.fade-scroll').forEach(function(el) { fadeObserver.observe(el); });

// ========== HEADER NAV ACTIVE STATE ==========
document.querySelectorAll('.header-nav-link').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.header-nav-link').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
    });
});
</script>
</body>
</html>