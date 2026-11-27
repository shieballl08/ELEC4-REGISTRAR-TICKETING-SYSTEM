<?php
    // ============================================
    // ERROR REPORTING
    // ============================================
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // START SESSION
    session_start();

    // CHECK LOGIN
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: Front_Page.php');
        exit();
    }

    // RBAC
require_once __DIR__ . '/../config/rbac.php';
checkRole(['student', 'alumni', 'admin', 'faculty']);

    // ============================================
    // USER DATA
    // ============================================
    $user_id = $_SESSION['user_id'] ?? 1;
    $user_fullname = $_SESSION['full_name'] ?? 'User';
    $user_email = $_SESSION['email'] ?? 'user@umak.edu.ph';
    $user_role = $_SESSION['role'] ?? 'student';
    $user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;

    // Initials
    $nameParts = explode(' ', $user_fullname);
    $user_initials = '';
    if (count($nameParts) >= 2) {
        $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $user_initials = strtoupper(substr($user_fullname, 0, 2));
    }

    // ============================================
    // DB CONNECTION
    // ============================================
    $possiblePaths = [
        __DIR__ . '/../config/db_connect_pg.php',
        __DIR__ . '/config/db_connect_pg.php',
        'C:/xampp/htdocs/ELEC_4_SYSTEM/config/db_connect_pg.php'
    ];

    $dbConnected = false;
    $pdo = null;

    foreach ($possiblePaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            if (class_exists('Database')) {
                $dbConnected = true;
                break;
            }
        }
    }

    // Default values
    $user_college = 'College of Information Technology';
    $user_course = 'BS Information Technology';
    $user_year_level = '3rd Year';
    $user_student_id = '2026-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);

    // ============================================
    // USER DATA FETCH
    // ============================================
    if ($dbConnected) {
        try {
            if (class_exists('Database')) {
                $database = new Database();
                $pdo = $database->getConnection();
            }

            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = $1");
                $stmt->execute([$user_id]);
                $userData = $stmt->fetch();

                if ($userData) {
                    $user_fullname = $userData['full_name'] ?? $user_fullname;
                    $user_email = $userData['email'] ?? $user_email;
                    $user_role = $userData['role'] ?? 'student';

                    $nameParts = explode(' ', $user_fullname);
                    if (count($nameParts) >= 2) {
                        $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                    } else {
                        $user_initials = strtoupper(substr($user_fullname, 0, 2));
                    }
                    $user_firstname = explode(' ', trim($user_fullname))[0];
                }

                $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = $1");
                $stmt->execute([$user_id]);
                $studentData = $stmt->fetch();

                if ($studentData) {
                    $user_student_id = $studentData['student_id'] ?? $user_student_id;
                    $user_college = $studentData['college'] ?? $user_college;
                    $user_course = $studentData['course'] ?? $user_course;
                    if (!empty($studentData['year_level'])) {
                        $user_year_level = $studentData['year_level'] . ' Year';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
        }
    }

    $_SESSION['full_name'] = $user_fullname;
    $_SESSION['email'] = $user_email;
    $_SESSION['role'] = $user_role;

    // ============================================
    // IMAGE PATHS
    // ============================================
    $bg_image_path = '../images/dashboard_bg.jpg';
    $img_base = '../images/';

    // ============================================
    // MODULES PATH
    // ============================================
    $modules_path = 'modules/';

    // ============================================
    // DOCUMENT RULES (for one-time docs)
    // ============================================
    require_once __DIR__ . '/../config/document_rules.php';
    $documentRules = getDocumentRules();

    // Check kung pwede pa mag-request ng one-time docs
    $torStatus     = ($pdo) ? canUserRequestDocument($pdo, $user_id, 'tor')        : ['allowed' => true, 'existing' => null];
    $goodMoralStat = ($pdo) ? canUserRequestDocument($pdo, $user_id, 'good_moral') : ['allowed' => true, 'existing' => null];
    $diplomaStat   = ($pdo) ? canUserRequestDocument($pdo, $user_id, 'diploma')    : ['allowed' => true, 'existing' => null];

    // ============================================
    // MY REQUESTS (summary for the dashboard) — display only
    // ============================================
    $recent_requests = [];
    $req_counts = ['pending' => 0, 'processing' => 0, 'ready' => 0, 'completed' => 0];
    if ($pdo) {
        try {
            // counts by status
            $stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM requests WHERE user_id = ? GROUP BY status");
            $stmt->execute([$user_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $s = strtoupper(trim($row['status'] ?? '')); $c = (int)$row['c'];
                if ($s === 'PENDING') $req_counts['pending'] += $c;
                elseif (in_array($s, ['PROCESSING', 'ACCEPTED', 'VERIFIED'])) $req_counts['processing'] += $c;
                elseif (in_array($s, ['READY', 'READY FOR RELEASE'])) $req_counts['ready'] += $c;
                elseif (in_array($s, ['COMPLETED', 'CLAIMED'])) $req_counts['completed'] += $c;
            }

            // latest 5 requests
            $stmt = $pdo->prepare("SELECT id, request_type, details, status, created_at FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$user_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                // details looks like "[Category] Document name — purpose": pull out the document name when present
                $name = $row['request_type'] ?? ('Request #' . $row['id']);
                if (!empty($row['details']) && preg_match('/^\[[^\]]*\]\s*(.+?)\s+—\s/u', $row['details'], $m)) {
                    $name = $m[1];
                }
                $st = trim($row['status'] ?? 'Pending');
                $recent_requests[] = [
                    'name'   => $name,
                    'date'   => date('M d, Y', strtotime($row['created_at'])),
                    'status' => $st,
                    'class'  => str_replace([' for release', ' '], ['', '-'], strtolower($st)),
                ];
            }
        } catch (PDOException $e) {
            error_log("Dashboard requests error: " . $e->getMessage());
        }
    }

    // ============================================
    // LATEST ANNOUNCEMENTS (preview for dashboard card)
    // Galing sa announcements table — para may pasilip sa dashboard
    // ============================================
    $dashboard_announcements = [];
    if ($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT id, title, content, priority, created_at
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
                LIMIT 3
            ");
            $dashboard_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Dashboard announcements error: " . $e->getMessage());
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · UMaK Registrar</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #003366;
            --primary-light: #1a4d7a;
            --primary-dark: #002244;
            --secondary: #FFD700;
            --secondary-dark: #E6C200;
            --surface: #ffffff;
            --surface-2: #f8f7f4;
            --surface-3: #f0eee9;
            --border: rgba(0,51,102,0.10);
            --border-md: rgba(0,51,102,0.18);
            --text-1: #1a2a3a;
            --text-2: #4a5a6a;
            --text-3: #8a9aaa;
            --shadow-sm: 0 1px 3px rgba(0,51,102,0.06);
            --shadow-md: 0 4px 16px rgba(0,51,102,0.08);
            --shadow-lg: 0 8px 32px rgba(0,51,102,0.12);

            --card-top: #02213b;
            --card-bottom: #011528;

            /* Glassmorphism variables */
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-bg-strong: rgba(255, 255, 255, 0.35);
            --glass-border: rgba(255, 255, 255, 0.4);
            --glass-shadow: 0 8px 32px rgba(0, 51, 102, 0.15);
            --glass-blur: blur(16px) saturate(180%);
        }

        /* ========== SINGLE SCROLLBAR FIX ========== */
        html { min-height: 100%; margin: 0; padding: 0; overflow-y: auto; overflow-x: hidden; }

        body {
            font-family: 'Libre Baskerville', sans-serif;
            background: var(--surface-2);
            color: var(--text-1);
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100%;
            margin: 0;
            padding: 0;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* ============================================
        SCROLL REVEAL — Fade-in + Slide-up
        (same style as Front_Page: 30px rise, 0.7s ease)
        Replays every time the element enters the viewport
        ============================================ */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1),
                        transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            will-change: opacity, transform;
        }
        .scroll-reveal.visible { opacity: 1; transform: translateY(0); }

        /* Delays only apply when appearing, so hiding is instant and
           the animation is ready to replay on the next scroll */
        .scroll-reveal:not(.visible) { transition-delay: 0s !important; }

        /* Staggered delays */
        .reveal-delay-1 { transition-delay: 0.05s; }
        .reveal-delay-2 { transition-delay: 0.1s; }
        .reveal-delay-3 { transition-delay: 0.15s; }
        .reveal-delay-4 { transition-delay: 0.2s; }
        .reveal-delay-5 { transition-delay: 0.25s; }

        /* Staggered delays for list items inside the info cards */
        .announcement-item.scroll-reveal:nth-child(1) { transition-delay: 0.05s; }
        .announcement-item.scroll-reveal:nth-child(2) { transition-delay: 0.15s; }
        .announcement-item.scroll-reveal:nth-child(3) { transition-delay: 0.25s; }

        .calendar-item.scroll-reveal:nth-child(1) { transition-delay: 0.05s; }
        .calendar-item.scroll-reveal:nth-child(2) { transition-delay: 0.12s; }
        .calendar-item.scroll-reveal:nth-child(3) { transition-delay: 0.19s; }
        .calendar-item.scroll-reveal:nth-child(4) { transition-delay: 0.26s; }

        .contact-item.scroll-reveal:nth-child(1) { transition-delay: 0.05s; }
        .contact-item.scroll-reveal:nth-child(2) { transition-delay: 0.12s; }
        .contact-item.scroll-reveal:nth-child(3) { transition-delay: 0.19s; }
        .contact-item.scroll-reveal:nth-child(4) { transition-delay: 0.26s; }

        .request-mini.scroll-reveal:nth-child(1) { transition-delay: 0.05s; }
        .request-mini.scroll-reveal:nth-child(2) { transition-delay: 0.12s; }
        .request-mini.scroll-reveal:nth-child(3) { transition-delay: 0.19s; }
        .request-mini.scroll-reveal:nth-child(4) { transition-delay: 0.26s; }
        .request-mini.scroll-reveal:nth-child(5) { transition-delay: 0.33s; }

        /* ========== HEADER ========== */
        .header { background: var(--primary); position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
        .header-inner { max-width: 100%; padding: 0 1.25rem; height: 68px; display: flex; align-items: center; gap: 1.5rem; }
        .brand { display: flex; flex-direction: column; gap: 1px; text-decoration: none; flex-shrink: 0; }
        .brand-name { font-size: 1.15rem; font-weight: 700; color: #ffffff; line-height: 1; letter-spacing: -0.01em; }
        .brand-sub { font-size: 0.55rem; color: rgba(255,255,255,0.5); font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; }
        .header-nav-links { display: flex; align-items: center; gap: 0.15rem; flex: 1; }
        .header-nav-link { color: rgba(255,255,255,0.7); padding: 0.5rem 0.85rem; border-radius: 6px; font-size: 0.8rem; text-decoration: none; transition: all 0.2s ease; white-space: nowrap; background: transparent; border: none; cursor: pointer; font-family: 'Noto Sans Osage', sans-serif; font-weight: 500; letter-spacing: 0.01em; }
        .header-nav-link:hover { color: #ffffff; background: rgba(255,215,0,0.1); }
        .header-nav-link.active { color: #ffffff; background: rgba(255,215,0,0.15); font-weight: 600; }
        .nav-dropdown { position: relative; display: inline-block; }
        .dropdown-popup { position: absolute; top: calc(100% + 8px); left: 0; min-width: 240px; background: #ffffff; box-shadow: 0 12px 40px rgba(0,0,0,0.12), 0 4px 12px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.06); padding: 0.5rem 0; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease; transform: translateY(-8px); z-index: 120; border-radius: 8px; }
        .nav-dropdown:hover .dropdown-popup { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
        .dropdown-popup .popup-item { display: block; padding: 0.65rem 1.2rem; font-size: 0.8rem; color: var(--text-1); text-decoration: none; transition: background 0.15s ease; cursor: pointer; }
        .dropdown-popup .popup-item:hover { background: rgba(0,51,102,0.05); }
        .dropdown-popup .popup-item .item-desc { display: block; font-size: 0.68rem; color: var(--text-3); margin-top: 2px; }
        .nav-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .icon-btn { width: 38px; height: 38px; border-radius: 6px; border: none; background: transparent; cursor: pointer; color: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; transition: all 0.15s; position: relative; text-decoration: none; }
        .icon-btn:hover { background: rgba(255,215,0,0.15); color: #ffffff; }
        .icon-btn svg { width: 19px; height: 19px; fill: none; stroke: currentColor; }
        .notif-dot { position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid var(--primary); }
        .avatar-btn { display: flex; align-items: center; gap: 8px; background: rgba(255,215,0,0.12); border: 1px solid rgba(255,215,0,0.2); border-radius: 6px; padding: 4px 10px 4px 4px; cursor: pointer; transition: background 0.15s; }
        .avatar-btn:hover { background: rgba(255,215,0,0.2); }
        .avatar-circle { width: 28px; height: 28px; border-radius: 4px; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; color: var(--primary); }
        .avatar-name { font-size: 0.78rem; color: rgba(255,255,255,0.9); font-weight: 500; }
        .dropdown-menu { position: absolute; right: 0; top: calc(100% + 10px); width: 260px; background: #ffffff; border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow-lg); z-index: 200; display: none; overflow: hidden; }
        .dropdown-menu.open { display: block; animation: dropIn 0.15s ease; }
        @keyframes dropIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .dd-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); }
        .dd-name { font-size: 0.875rem; font-weight: 600; color: var(--text-1); }
        .dd-email { font-size: 0.75rem; color: var(--text-3); margin-top: 2px; }
        .dd-section { padding: 6px 6px; }
        .dd-label { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-3); padding: 6px 10px 4px; }
        .dd-item { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 5px; font-size: 0.82rem; color: var(--text-2); cursor: pointer; transition: background 0.12s; text-decoration: none; }
        .dd-item:hover { background: var(--surface-2); }
        .dd-item svg { width: 16px; height: 16px; flex-shrink: 0; opacity: 0.75; }
        .dd-item.danger { color: #dc2626; }
        .dd-item.danger:hover { background: #fef2f2; }
        .dd-divider { height: 1px; background: var(--border); margin: 4px 8px; }

        /* ========== DASHBOARD CANVAS ========== */
        .dashboard-canvas { position: relative; overflow: visible; padding: 2rem 1.5rem 1.5rem; flex: 1 0 auto; }
        .dashboard-canvas::before {
            content: '';
            position: absolute;
            inset: -30px;
            background-image: url('<?php echo $bg_image_path; ?>');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            filter: blur(10px) brightness(1.15) saturate(0.8);
            z-index: 0;
            transform: scale(1.1);
        }
        .dashboard-canvas::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0.85) 0%,
                rgba(255, 255, 255, 0.70) 30%,
                rgba(255, 255, 255, 0.55) 60%,
                rgba(255, 255, 255, 0.45) 100%
            );
            z-index: 1;
        }
        .page { position: relative; z-index: 2; max-width: 1280px; margin: 0 auto; }

        /* ========== HERO ========== */
        .hero { text-align: center; padding: 2.5rem 1rem 3rem; }
        .hero h1 { font-size: 2rem; font-weight: 700; color: var(--text-1); margin-bottom: 0.6rem; letter-spacing: -0.01em; }
        .hero h1 span { color: var(--primary); }
        .hero p { font-size: 1rem; color: var(--text-2); max-width: 620px; margin: 0 auto; line-height: 1.6; }

        /* ========== SECTION ========== */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .section-title { font-size: 1.15rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 10px; }
        .section-title::before { content: ''; width: 4px; height: 20px; background: var(--secondary); border-radius: 2px; }

        /* ========== SERVICE CARDS GRID ========== */
        .service-cards-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; margin-bottom: 3rem; }
        .service-card-large { position: relative; height: 320px; display: flex; flex-direction: column; justify-content: flex-end; text-decoration: none; overflow: hidden; background: linear-gradient(180deg, var(--card-top) 0%, var(--card-bottom) 100%); background-size: cover; background-position: center; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .service-card-large::before { content: ''; position: absolute; inset: 0; background: rgba(2, 33, 59, 0.55); transition: background 0.3s ease; z-index: 1; }
        .service-card-large:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(2, 33, 59, 0.45); }
        .service-card-large:hover::before { background: rgba(2, 33, 59, 0.35); }
        .service-card-title { position: relative; z-index: 2; padding: 1.1rem 1rem; background: rgba(1, 21, 40, 0.92); font-size: 0.95rem; font-weight: 600; color: #ffffff; letter-spacing: 0.01em; line-height: 1.3; text-align: center; transition: background 0.3s ease; min-height: 68px; display: flex; align-items: center; justify-content: center; }
        .service-card-large:hover .service-card-title { background: rgba(1, 21, 40, 0.98); }

        /* ===== SERVICE CARD HOVER: description slides in, title lifts ===== */
        .service-card-title { flex-direction: column; gap: 0; }
        .service-card-name { display: block; transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
        .service-card-desc { display: block; max-height: 0; opacity: 0; overflow: hidden; font-size: 0.72rem; font-weight: 400; line-height: 1.45; color: rgba(255, 255, 255, 0.85); transform: translateY(8px); transition: max-height 0.4s ease, opacity 0.35s ease 0.05s, transform 0.4s ease, margin-top 0.4s ease; }
        .service-card-large:hover .service-card-desc,
        .service-card-large:focus-visible .service-card-desc { max-height: 5em; opacity: 1; transform: translateY(0); margin-top: 0.4rem; }
        @media (max-width: 600px) { .service-card-desc { font-size: 0.66rem; } }

        /* ============================================
        INFO SECTIONS (Announcements + Calendar + Contact)
        GLASSMORPHISM
        ============================================ */
        .info-sections-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.25rem; margin-bottom: 0.5rem; }
        .info-card { background: var(--glass-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); border-radius: 16px; padding: 1.5rem; box-shadow: var(--glass-shadow); display: flex; flex-direction: column; transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease; position: relative; overflow: hidden; }

        /* Top highlight line for glass effect */
        .info-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7), transparent); z-index: 1; }
        .info-card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0, 51, 102, 0.20); background: var(--glass-bg-strong); }
        .info-card .info-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255, 255, 255, 0.35); }
        .info-card .info-card-header svg { width: 20px; height: 20px; stroke: var(--primary); stroke-width: 2; fill: none; flex-shrink: 0; }
        .info-card .info-card-title { font-size: 1rem; font-weight: 700; color: var(--primary); letter-spacing: 0.01em; }

        /* ============================================
        ANNOUNCEMENTS TITLE — PLAIN TEXT LINK (no border, no button)
        ============================================ */
        a.info-card-title-link {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 0.01em;
            text-decoration: none;
            transition: color 0.15s ease;
            cursor: pointer;
        }
        a.info-card-title-link:hover {
            color: var(--secondary-dark);
            text-decoration: underline;
        }

        /* ===== ANNOUNCEMENTS LIST ===== */
        .announcement-list { list-style: none; display: flex; flex-direction: column; gap: 1rem; }
        .announcement-item { display: flex; gap: 12px; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.25); transition: transform 0.2s ease, opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
        .announcement-item:last-child { border-bottom: none; padding-bottom: 0; }
        .announcement-item:hover { transform: translateX(4px); }
        .announcement-badge { width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; color: var(--primary); line-height: 1; transition: transform 0.2s ease; box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3); }
        .announcement-item:hover .announcement-badge { transform: scale(1.08); }
        .announcement-badge .badge-month { font-size: 0.55rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .announcement-badge .badge-day { font-size: 1rem; font-weight: 800; margin-top: 1px; }
        .announcement-content { flex: 1; min-width: 0; }
        .announcement-title { font-size: 0.85rem; font-weight: 600; color: var(--text-1); margin-bottom: 3px; line-height: 1.35; }
        .announcement-desc { font-size: 0.75rem; color: var(--text-2); line-height: 1.5; }

        /* ===== CALENDAR LIST ===== */
        .calendar-list { list-style: none; display: flex; flex-direction: column; gap: 0.75rem; }
        .calendar-item { display: flex; align-items: flex-start; gap: 10px; padding: 0.6rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.25); transition: transform 0.2s ease, opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
        .calendar-item:last-child { border-bottom: none; }
        .calendar-item:hover { transform: translateX(4px); }
        .calendar-date { flex-shrink: 0; width: 42px; text-align: center; background: rgba(255, 255, 255, 0.4); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; padding: 4px 0; transition: background 0.2s ease; }
        .calendar-item:hover .calendar-date { background: rgba(255, 255, 255, 0.6); }
        .calendar-date .date-day { display: block; font-size: 0.95rem; font-weight: 700; color: var(--primary); line-height: 1; }
        .calendar-date .date-month { display: block; font-size: 0.55rem; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }
        .calendar-content { flex: 1; min-width: 0; }
        .calendar-event { font-size: 0.78rem; font-weight: 600; color: var(--text-1); line-height: 1.3; margin-bottom: 2px; }
        .calendar-note { font-size: 0.65rem; color: var(--text-2); }

        /* ===== CONTACT ===== */
        .contact-list { list-style: none; display: flex; flex-direction: column; gap: 0.9rem; }
        .contact-item { display: flex; align-items: flex-start; gap: 10px; transition: transform 0.2s ease, opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
        .contact-item:hover { transform: translateX(4px); }
        .contact-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.4); border: 1px solid rgba(255, 255, 255, 0.3); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.2s ease; }
        .contact-item:hover .contact-icon { background: rgba(255, 255, 255, 0.6); }
        .contact-icon svg { width: 15px; height: 15px; stroke: var(--primary); stroke-width: 2; fill: none; }
        .contact-detail { flex: 1; min-width: 0; }
        .contact-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--primary); margin-bottom: 1px; }
        .contact-value { font-size: 0.78rem; color: var(--text-1); line-height: 1.4; font-weight: 500; word-break: break-word; }
        .contact-value a { color: var(--primary); text-decoration: none; transition: color 0.15s; font-weight: 600; }
        .contact-value a:hover { color: var(--secondary-dark); text-decoration: underline; }

        /* ============================================
        MY REQUESTS (dashboard summary) — NEW
        Uses the same glass .info-card style
        ============================================ */
        .requests-section { margin-bottom: 1.25rem; }
        .info-card .view-all { margin-left: auto; font-size: 0.75rem; font-weight: 700; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: color 0.15s, transform 0.15s; }
        .info-card .view-all:hover { color: var(--secondary-dark); transform: translateX(2px); }
        .req-summary { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .req-chip { font-size: 0.7rem; font-weight: 600; padding: 4px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.5); border: 1px solid rgba(255, 255, 255, 0.4); color: var(--text-2); }
        .req-chip b { color: var(--primary); font-weight: 700; margin-right: 3px; }
        .request-list { list-style: none; display: flex; flex-direction: column; }
        .request-mini { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0.7rem 0.25rem; border-bottom: 1px solid rgba(255, 255, 255, 0.3); transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), background 0.2s ease; }
        .request-mini:last-child { border-bottom: none; }
        .request-mini:hover { background: rgba(255, 255, 255, 0.35); }
        .request-mini .req-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .request-mini .req-name { font-size: 0.82rem; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .request-mini .req-date { font-size: 0.68rem; color: var(--text-2); }
        .req-pill { font-size: 0.68rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; white-space: nowrap; background: #fef6dd; color: #8a6100; }
        .req-pill.processing, .req-pill.accepted, .req-pill.verified { background: #e6effa; color: #1a4d7a; }
        .req-pill.ready, .req-pill.completed, .req-pill.claimed { background: #e3f4e8; color: #1e7e34; }
        .req-pill.rejected { background: #fde8e8; color: #b91c1c; }
        .req-empty { padding: 1.25rem 0 0.5rem; text-align: center; font-size: 0.8rem; color: var(--text-2); }
        .req-empty a { color: var(--primary); font-weight: 700; text-decoration: none; }
        .req-empty a:hover { text-decoration: underline; }

        /* ============================================
        SCROLL-REVEAL TRANSITION FIX
        ============================================ */
        .service-card-large.scroll-reveal { transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), box-shadow 0.3s ease; }
        .info-card.scroll-reveal { transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), box-shadow 0.3s ease, background 0.3s ease; }
        .announcement-item.scroll-reveal,
        .calendar-item.scroll-reveal,
        .contact-item.scroll-reveal { transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1); }
        .service-card-large.scroll-reveal:hover,
        .info-card.scroll-reveal:hover { transition-delay: 0s; }
        /* Hover lift keeps working once revealed */
        .service-card-large.scroll-reveal.visible:hover { transform: translateY(-4px); }
        .info-card.scroll-reveal.visible:hover { transform: translateY(-2px); }
        .announcement-item.scroll-reveal.visible:hover,
        .calendar-item.scroll-reveal.visible:hover,
        .contact-item.scroll-reveal.visible:hover { transform: translateX(4px); }

        /* ============================================
        CLICKABLE INFO CARD (For Upcoming Dates)
        ============================================ */
        a.clickable-card { text-decoration: none; color: inherit; display: flex; cursor: pointer; }
        a.clickable-card:hover { text-decoration: none; }

        /* ============================================
        FOOTER — GLASSMORPHISM
        ============================================ */
        .footer { flex-shrink: 0; background: rgba(30, 30, 35, 0.85); backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); color: #d0d0d0; padding: 0; margin-top: 0; width: 100%; position: relative; z-index: 3; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .footer-inner { max-width: 1280px; margin: 0 auto; padding: 3rem 2rem 2.5rem; display: grid; grid-template-columns: 1.4fr 1fr 1.2fr 0.8fr; gap: 2.5rem; align-items: start; }
        .footer-col h4 { font-size: 1.35rem; font-weight: 700; color: #e8781e; margin-bottom: 1.25rem; letter-spacing: 0.01em; font-family: 'Libre Baskerville', serif; }
        .footer-col p { font-size: 0.85rem; line-height: 1.7; color: #d0d0d0; }
        .footer-col ul { list-style: none; padding: 0; margin: 0; }
        .footer-col ul li { margin-bottom: 0.65rem; }
        .footer-col ul li a { color: #e8781e; text-decoration: none; font-size: 0.9rem; transition: color 0.2s ease, text-decoration 0.2s ease; }
        .footer-col ul li a:hover { text-decoration: underline; color: #ffa04d; }
        .footer-contact-item { display: flex; align-items: flex-start; gap: 8px; font-size: 0.85rem; line-height: 1.5; color: #d0d0d0; margin-bottom: 0.75rem; }
        .footer-contact-item svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px; stroke: #d0d0d0; fill: none; }
        .footer-contact-item a { color: #e8781e; text-decoration: none; }
        .footer-contact-item a:hover { text-decoration: underline; }
        .footer-social { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; background: rgba(24, 119, 242, 0.9); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 10px; transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease; text-decoration: none; box-shadow: 0 4px 12px rgba(24, 119, 242, 0.3); }
        .footer-social:hover { transform: translateY(-2px); background: #0d65d9; box-shadow: 0 6px 20px rgba(24, 119, 242, 0.5); }
        .footer-social svg { width: 24px; height: 24px; fill: #ffffff; stroke: none; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.08); padding: 1.1rem 2rem; text-align: center; background: rgba(20, 20, 25, 0.5); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .footer-bottom p { font-size: 0.78rem; color: #a0a0a0; margin: 0; }
        .footer-bottom a { color: #4a90e2; text-decoration: none; }
        .footer-bottom a:hover { text-decoration: underline; }

        /* Back to top button — GLASS */
        .back-to-top { position: fixed; right: 24px; bottom: 90px; width: 44px; height: 44px; background: rgba(30, 58, 95, 0.7); backdrop-filter: blur(12px) saturate(180%); -webkit-backdrop-filter: blur(12px) saturate(180%); border: 1px solid rgba(255, 255, 255, 0.2); color: #ffffff; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25); transition: background 0.2s ease, transform 0.2s ease; z-index: 90; text-decoration: none; }
        .back-to-top:hover { background: rgba(42, 77, 122, 0.9); transform: translateY(-2px); }
        .back-to-top svg { width: 20px; height: 20px; stroke: #ffffff; stroke-width: 2.5; fill: none; }

        /* Help button — GLASS */
        .help-btn { position: fixed; right: 24px; bottom: 24px; width: 44px; height: 44px; background: rgba(232, 120, 30, 0.85); backdrop-filter: blur(12px) saturate(180%); -webkit-backdrop-filter: blur(12px) saturate(180%); border: 1px solid rgba(255, 255, 255, 0.25); color: #ffffff; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(232, 120, 30, 0.35); transition: background 0.2s ease, transform 0.2s ease; z-index: 90; font-size: 1.25rem; font-weight: 700; font-family: 'Libre Baskerville', serif; text-decoration: none; }
        .help-btn:hover { background: rgba(255, 160, 77, 0.95); transform: translateY(-2px); }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) { .service-card-large { height: 280px; } }
        @media (max-width: 1024px) {
            .service-cards-grid { gap: 0.4rem; }
            .service-card-large { height: 260px; }
            .service-card-title { font-size: 0.85rem; padding: 0.9rem 0.75rem; min-height: 60px; }
            .info-sections-grid { grid-template-columns: 1fr 1fr; }
            .info-card.announcements-card { grid-column: 1 / -1; }
            .footer-inner { grid-template-columns: 1fr 1fr; gap: 2rem; padding: 2.5rem 1.5rem 2rem; }
        }
        @media (max-width: 900px) {
            .service-cards-grid { grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
            .service-card-large { height: 240px; }
        }
        @media (max-width: 768px) {
            .header-inner { padding: 0 0.75rem; gap: 0.75rem; }
            .header-nav-links { display: none; }
            .brand-sub { display: none; }
            .avatar-name { display: none; }
            .avatar-btn { padding: 4px; }
            .dashboard-canvas { padding: 1.25rem 1rem 1.25rem; }
            .hero { padding: 1.5rem 0.5rem 2rem; }
            .hero h1 { font-size: 1.5rem; }
            .hero p { font-size: 0.9rem; }
            .info-sections-grid { grid-template-columns: 1fr; }
            .info-card.announcements-card { grid-column: auto; }
            .footer-inner { grid-template-columns: 1fr; gap: 1.75rem; padding: 2rem 1.25rem 1.75rem; }
            .footer-col h4 { font-size: 1.2rem; }
        }
        @media (max-width: 600px) {
            .service-cards-grid { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            .service-card-large { height: 200px; }
            .service-card-title { font-size: 0.8rem; padding: 0.75rem 0.5rem; min-height: 52px; }
            .back-to-top, .help-btn { right: 16px; width: 38px; height: 38px; }
        }
        @media (max-width: 480px) {
            .service-cards-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .service-card-large { height: 180px; }
        }
    </style>
</head>
<body>

<?php include '../includes/user_header.php'; ?>

<div class="dashboard-canvas">

    <main class="page">

        <!-- ========== HERO ========== -->
        <section class="hero">
            <h1 class="scroll-reveal">
                Welcome, <span><?php echo htmlspecialchars($user_firstname); ?>!</span>
            </h1>
            <p class="scroll-reveal reveal-delay-1">
                Manage your academic records and registrar services in one place. Access enrollment information, transcripts, certificates, and more.
            </p>
        </section>

        <!-- ========== SERVICE CARDS ========== -->
        <section>
            <div class="section-header">
                <h2 class="section-title scroll-reveal">Quick Services</h2>
            </div>

            <div class="service-cards-grid">

                <!-- 1. ACADEMIC RECORDS  -->
                <a href="<?php echo $modules_path; ?>academic_records.php" class="service-card-large scroll-reveal reveal-delay-1"
                style="background-image: url('<?php echo $img_base; ?>tor.jpg');">
                    <div class="service-card-title"><span class="service-card-name"> Academic Records</span><span class="service-card-desc">Request your official transcript for school, work, or abroad.</span></div>
                </a>

                <!-- 2. CERTIFICATES -->
                <a href="<?php echo $modules_path; ?>certifications.php" class="service-card-large scroll-reveal reveal-delay-2"
                style="background-image: url('<?php echo $img_base; ?>certificates.jpg');">
                    <div class="service-card-title"><span class="service-card-name">Certificates</span><span class="service-card-desc">Apply for enrollment, grades, and good moral certifications.</span></div>
                </a>

                <!-- 3. CLEARANCE -->
                <a href="<?php echo $modules_path; ?>clearance.php" class="service-card-large scroll-reveal reveal-delay-3"
                style="background-image: url('<?php echo $img_base; ?>clearance.jpg');">
                    <div class="service-card-title"><span class="service-card-name">Clearance</span><span class="service-card-desc">Process your clearance for graduation or transfer.</span></div>
                </a>

                <!-- 4. ENROLLMENT -->
                <a href="<?php echo $modules_path; ?>enrollment.php" class="service-card-large scroll-reveal reveal-delay-4"
                style="background-image: url('<?php echo $img_base; ?>enrollment.jpg');">
                    <div class="service-card-title"><span class="service-card-name">Enrollment</span><span class="service-card-desc">View your enrollment details and manage your subjects.</span></div>
                </a>

                <!-- 5. CONTACT REGISTRAR -->
                <a href="<?php echo $modules_path; ?>contact_registrar.php" class="service-card-large scroll-reveal reveal-delay-5"
                style="background-image: url('<?php echo $img_base; ?>personal.jpg');">
                    <div class="service-card-title"><span class="service-card-name">Contact Registrar</span><span class="service-card-desc">Send your concerns and inquiries to the Registrar's Office.</span></div>
                </a>

            </div>
        </section>

        <!-- ============================================
            MY REQUESTS (NEW) — latest requests + status summary
            ============================================ -->
        <section class="requests-section">
            <div class="info-card scroll-reveal">
                <div class="info-card-header">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="info-card-title">My Requests</h3>
                    <a href="<?php echo $modules_path; ?>requests.php" class="view-all">View all &rarr;</a>
                </div>

                <div class="req-summary">
                    <span class="req-chip"><b><?php echo $req_counts['pending']; ?></b>Pending</span>
                    <span class="req-chip"><b><?php echo $req_counts['processing']; ?></b>Processing</span>
                    <span class="req-chip"><b><?php echo $req_counts['ready']; ?></b>Ready</span>
                    <span class="req-chip"><b><?php echo $req_counts['completed']; ?></b>Completed</span>
                </div>

                <?php if (empty($recent_requests)): ?>
                    <div class="req-empty">
                        No requests yet. Choose a service above to get started, or
                        <a href="<?php echo $modules_path; ?>requests.php">open My Requests</a>.
                    </div>
                <?php else: ?>
                    <ul class="request-list">
                        <?php foreach ($recent_requests as $rq): ?>
                            <li class="request-mini scroll-reveal">
                                <div class="req-info">
                                    <span class="req-name"><?php echo htmlspecialchars($rq['name']); ?></span>
                                    <span class="req-date"><?php echo htmlspecialchars($rq['date']); ?></span>
                                </div>
                                <span class="req-pill <?php echo htmlspecialchars($rq['class']); ?>"><?php echo htmlspecialchars($rq['status']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============================================
            INFO SECTIONS: ANNOUNCEMENTS + CALENDAR + CONTACT
            ============================================ -->
        <div class="info-sections-grid">

            <!-- ===== ANNOUNCEMENTS ===== -->
            <div class="info-card announcements-card scroll-reveal">
                <div class="info-card-header">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                    <!-- ✅ ANNOUNCEMENTS TITLE — PLAIN TEXT LINK (no border, no button) -->
                    <a href="<?php echo $modules_path; ?>announcements.php" class="info-card-title-link">Announcements</a>
                </div>

                                <ul class="announcement-list">
                    <?php if (empty($dashboard_announcements)): ?>
                        <li style="padding:1.5rem 0;text-align:center;font-size:0.8rem;color:var(--text-2);">
                            No announcements yet.
                        </li>
                    <?php else: ?>
                        <?php foreach ($dashboard_announcements as $ann): ?>
                            <?php
                                $ts = strtotime($ann['created_at'] ?? 'now');
                                $month = date('M', $ts);
                                $day = date('d', $ts);
                                $priority = strtolower($ann['priority'] ?? 'normal');
                                $preview = mb_strlen($ann['content'] ?? '') > 90
                                    ? mb_substr($ann['content'], 0, 90) . '…'
                                    : ($ann['content'] ?? '');
                            ?>
                            <li class="announcement-item scroll-reveal">
                                <div class="announcement-badge">
                                    <span class="badge-month"><?php echo $month; ?></span>
                                    <span class="badge-day"><?php echo $day; ?></span>
                                </div>
                                <div class="announcement-content">
                                    <div class="announcement-title">
                                        <?php echo htmlspecialchars($ann['title']); ?>
                                        <?php if ($priority === 'urgent'): ?>
                                            <span style="display:inline-block;font-size:0.6rem;font-weight:700;color:#b91c1c;background:#fde8e8;padding:1px 7px;border-radius:999px;margin-left:4px;vertical-align:middle;">URGENT</span>
                                        <?php elseif ($priority === 'info'): ?>
                                            <span style="display:inline-block;font-size:0.6rem;font-weight:700;color:#1a4d7a;background:#e6effa;padding:1px 7px;border-radius:999px;margin-left:4px;vertical-align:middle;">INFO</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="announcement-desc"><?php echo htmlspecialchars($preview); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <li style="padding-top:0.75rem;text-align:right;">
                            <a href="<?php echo $modules_path; ?>announcements.php"
                               style="font-size:0.72rem;font-weight:700;color:var(--primary);text-decoration:none;">
                                View all &rarr;
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- ===== CALENDAR (CLICKABLE) ===== -->
            <a href="<?php echo $modules_path; ?>calendar.php" class="info-card clickable-card scroll-reveal reveal-delay-1">
                <div class="info-card-header">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <h3 class="info-card-title">Upcoming Dates</h3>
                </div>

                <ul class="calendar-list">
                    <!-- Walang sample data -->
                </ul>
            </a>

            <!-- ===== CONTACT ===== -->
            <div class="info-card scroll-reveal reveal-delay-2">
                <div class="info-card-header">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="info-card-title">Contact Us</h3>
                </div>

                <ul class="contact-list">
                    <li class="contact-item scroll-reveal">
                        <div class="contact-icon">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="contact-detail">
                            <div class="contact-label">Address</div>
                            <div class="contact-value">
                                J.P. Rizal Extension<br>
                                West Rembo, Makati City
                            </div>
                        </div>
                    </li>

                    <li class="contact-item scroll-reveal">
                        <div class="contact-icon">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <div class="contact-detail">
                            <div class="contact-label">Phone</div>
                            <div class="contact-value">
                                <a href="tel:+63281234567">(02) 8123-4567</a>
                            </div>
                        </div>
                    </li>

                    <li class="contact-item scroll-reveal">
                        <div class="contact-icon">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="contact-detail">
                            <div class="contact-label">Email</div>
                            <div class="contact-value">
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=registrar@umak.edu.ph">registrar@umak.edu.ph</a>
                            </div>
                        </div>
                    </li>

                    <li class="contact-item scroll-reveal">
                        <div class="contact-icon">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="contact-detail">
                            <div class="contact-label">Office Hours</div>
                            <div class="contact-value">
                                Mon-Fri · 8:00 AM - 5:00 PM
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

        </div>

    </main>

</div>

<!-- ============================================
     FOOTER — Included from includes/footer.php
     (Consistent across all pages: About / Quick Links /
      Contact Us / Follow Us + Back-to-top + Help button)
     ============================================ -->
<?php include '../includes/footer.php'; ?>

<!-- ============================================
    CONTINUOUS SCROLL-TRIGGERED REVEAL ANIMATION
    Replays every time an element enters the viewport
    (scrolling down OR up) — no page refresh needed.
    ============================================ -->
<script>
    (function () {
        function initReveal() {
            var items = Array.prototype.slice.call(document.querySelectorAll('.scroll-reveal'));
            var ticking = false;

            function update() {
                var vh = window.innerHeight || document.documentElement.clientHeight;
                items.forEach(function (el) {
                    var r = el.getBoundingClientRect();
                    // visible kapag nasa loob ng screen (may 60px margin sa baba)
                    var inView = r.top < vh - 60 && r.bottom > 0;
                    if (inView) {
                        el.classList.add('visible');
                    } else {
                        el.classList.remove('visible');
                    }
                });
                ticking = false;
            }

            function onScroll() {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(update);
                }
            }

            // capture = true para gumana kahit nasa loob ng ibang scroll container
            window.addEventListener('scroll', onScroll, true);
            window.addEventListener('resize', onScroll);
            window.addEventListener('load', update);

            // unang check: i-play ang animation pagka-load
            requestAnimationFrame(function () {
                requestAnimationFrame(update);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initReveal);
        } else {
            initReveal();
        }
    })();
</script>

</body>
</html>