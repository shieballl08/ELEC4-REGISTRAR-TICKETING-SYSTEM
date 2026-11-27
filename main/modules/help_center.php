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
// COMMENT OUT OR REMOVE THESE LINES AFTER TESTING
// These lines simulate a logged-in user without database
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Set fake session data for testing
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
// help_center.php is inside htdocs/modules folder
// config folder is outside htdocs (one level up from htdocs)
$configPath = dirname(__DIR__, 2) . '/config/db_connect.php';

if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Fallback: try relative path from modules
    $fallbackPath = '../../config/db_connect.php';
    if (file_exists($fallbackPath)) {
        require_once $fallbackPath;
    } else {
        // If still not found, show error
        die('Database connection file not found. Please check your folder structure.');
    }
}

// ============================================
// CHECK IF USER IS LOGGED IN (COMMENTED OUT FOR TESTING)
// ============================================
// Uncomment these lines to enable login check
/*
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to front page if not logged in
    header('Location: ../Front_Page.php');
    exit();
}
*/

// ============================================
// GET USER DATA FROM SESSION
// ============================================
$user_id = $_SESSION['user_id'] ?? 1;
$user_fullname = $_SESSION['full_name'] ?? 'Juan Dela Cruz';
$user_email = $_SESSION['email'] ?? 'juan.delacruz@umak.edu.ph';
$user_role = $_SESSION['role'] ?? 'student';

// Get initials for avatar
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
            // Get user details
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
    
    // Get college and course from session or database
    $user_college = $_SESSION['college'] ?? 'College of Information Technology';
    $user_course = $_SESSION['course'] ?? 'BS Information Technology';
    $user_year_level = $_SESSION['year_level'] ?? '3rd Year';
    $user_student_id = $_SESSION['student_id'] ?? '2026-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
    
} catch (PDOException $e) {
    // If database error, use default values
    error_log("Database error in help_center.php: " . $e->getMessage());
    $user_college = 'College of Information Technology';
    $user_course = 'BS Information Technology';
    $user_year_level = '3rd Year';
    $user_student_id = '2026-' . str_pad($user_id ?? 1, 5, '0', STR_PAD_LEFT);
}

// ============================================
// FAQ DATA - DEFINED OUTSIDE TRY-CATCH
// ============================================
$faq_categories = [
    'general' => [
        'label' => 'General Questions',
        'icon' => '',
        'faqs' => [
            [
                'question' => 'What is the Registrar Portal?',
                'answer' => 'The UMak Registrar Portal is an online platform that allows students to access academic records, request documents, and manage registrar-related services.'
            ],
            [
                'question' => 'How do I log in to the portal?',
                'answer' => 'You can log in using your UMak student email (username@umak.edu.ph) and your password. If you have forgotten your password, please contact the IT Help Desk.'
            ],
            [
                'question' => 'What services are available on the portal?',
                'answer' => 'The portal offers enrollment services, grade viewing, transcript requests, certificate requests, clearance management, and more.'
            ]
        ]
    ],
    'enrollment' => [
        'label' => 'Enrollment',
        'icon' => '',
        'faqs' => [
            [
                'question' => 'How do I enroll for the semester?',
                'answer' => 'Go to the Enrollment section, select your subjects, and confirm your enrollment. Make sure to complete all requirements before the enrollment deadline.'
            ],
            [
                'question' => 'When is the enrollment period?',
                'answer' => 'Enrollment periods are announced at the beginning of each semester. Check the Announcements section for specific dates and schedules.'
            ],
            [
                'question' => 'How do I add/drop subjects?',
                'answer' => 'You can add or drop subjects during the add/drop period through the Enrollment section. Changes are subject to approval by the Registrar\'s Office.'
            ]
        ]
    ],
    'grades' => [
        'label' => 'Grades & Academic Records',
        'icon' => '',
        'faqs' => [
            [
                'question' => 'How do I view my grades?',
                'answer' => 'Go to the Grades section to view your current and previous semester grades. Grades are usually posted after the end of each semester.'
            ],
            [
                'question' => 'How do I request a grade revision?',
                'answer' => 'If you believe there is an error in your grade, you may request a grade revision through the Grades section. Your request will be forwarded to your instructor for review.'
            ],
            [
                'question' => 'What is the Dean\'s List?',
                'answer' => 'The Dean\'s List is a recognition given to students who achieve a GWA of 1.75 or higher with no failing grades. Check the Grades section for the current Dean\'s List.'
            ]
        ]
    ],
    'documents' => [
        'label' => 'Documents & Certificates',
        'icon' => '',
        'faqs' => [
            [
                'question' => 'How do I request a Transcript of Records (TOR)?',
                'answer' => 'Go to the TOR section and submit a request. Processing time is 5-7 working days. A fee of PHP 250.00 applies per copy.'
            ],
            [
                'question' => 'How do I request a Certificate of Enrollment?',
                'answer' => 'Go to the Certificates section and select Certificate of Enrollment. Processing time is 1-2 working days with a fee of PHP 150.00.'
            ],
            [
                'question' => 'How do I get a Good Moral Certificate?',
                'answer' => 'You can request a Good Moral Certificate through the Certificates section. Processing time is 1-2 working days with a fee of PHP 100.00.'
            ]
        ]
    ],
    'clearance' => [
        'label' => 'Clearance',
        'icon' => '',
        'faqs' => [
            [
                'question' => 'What is a graduation clearance?',
                'answer' => 'Graduation clearance is required for graduating students. It ensures all academic and financial obligations are settled before graduation.'
            ],
            [
                'question' => 'How do I check my clearance status?',
                'answer' => 'Go to the Clearance section to view your clearance checklist and track your progress. Each department will update your status as you complete requirements.'
            ],
            [
                'question' => 'What happens if I have pending requirements?',
                'answer' => 'You will not be able to complete your clearance until all requirements are satisfied. Contact the respective offices to clear any pending items.'
            ]
        ]
    ],
    'payments' => [
        'label' => 'Payments & Billing',
        'icon' => '',
        'faqs' => [
            [
                'question' => 'How do I view my payment history?',
                'answer' => 'Go to the Billing & Payment History section to view all your payments and outstanding balances.'
            ],
            [
                'question' => 'What payment methods are accepted?',
                'answer' => 'We accept cash payments at the Registrar\'s Office. Online payments via GCash and Maya are coming soon.'
            ],
            [
                'question' => 'What happens if I have an outstanding balance?',
                'answer' => 'Outstanding balances must be settled before you can enroll, request documents, or complete clearance. Please visit the Accounting Office for assistance.'
            ]
        ]
    ]
];

// Search functionality
$search_query = '';
$search_results = [];
$is_searching = false;

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $is_searching = true;
    $search_query = trim($_GET['search']);
    $search_terms = explode(' ', strtolower($search_query));
    
    foreach ($faq_categories as $cat_key => $category) {
        foreach ($category['faqs'] as $faq) {
            $question_lower = strtolower($faq['question']);
            $answer_lower = strtolower($faq['answer']);
            $match_count = 0;
            
            foreach ($search_terms as $term) {
                if (strpos($question_lower, $term) !== false || strpos($answer_lower, $term) !== false) {
                    $match_count++;
                }
            }
            
            if ($match_count > 0) {
                $search_results[] = [
                    'category' => $cat_key,
                    'category_label' => $category['label'],
                    'category_icon' => $category['icon'],
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'match_count' => $match_count
                ];
            }
        }
    }
    
    // Sort by match count (highest first)
    usort($search_results, function($a, $b) {
        return $b['match_count'] - $a['match_count'];
    });
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

// Get active category from URL
$active_category = $_GET['category'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Help Center</title>

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
        /* Soft ombre header glow (reference style: light, airy, brand-tinted) */
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

        .page-head { margin-bottom: 1.25rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; color: var(--text-1); letter-spacing: -0.01em; }
        .page-sub { font-size: 0.85rem; color: var(--text-3); margin-top: 4px; }

        /* ========== CARD BASE ========== */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--shadow-sm); }

        /* ========== STATS STRIP ========== */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); margin-bottom: 1.1rem; }
        .stat-box { padding: 0.8rem 1.25rem; display: flex; align-items: baseline; gap: 10px; border-right: 1px solid var(--border); }
        .stat-box:last-child { border-right: none; }
        .stat-box .stat-value { font-size: 1.35rem; font-weight: 700; color: var(--accent-text); line-height: 1; }
        .stat-box .stat-label { font-size: 0.76rem; color: var(--text-3); }

        /* ========== TOOLBAR (search + filters) ========== */
        .toolbar { padding: 0.9rem; margin-bottom: 1.25rem; }
        .pup-search-form { display: grid; grid-template-columns: 1fr 240px auto; gap: 0.5rem; align-items: center; }
        .search-field { position: relative; }
        .search-field svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: var(--text-3); fill: none; stroke-width: 2; pointer-events: none; }
        .pup-search-form input[type=text],
        .pup-search-form select {
            width: 100%; padding: 9px 12px; border: 1px solid var(--border-md); background: var(--surface);
            color: var(--text-1); font-size: 0.85rem; border-radius: 8px; transition: border-color 0.15s, box-shadow 0.15s;
        }
        .search-field input { padding-left: 36px !important; }
        .pup-search-form input:focus, .pup-search-form select:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(0,51,102,0.10); }
        .pup-search-actions { display: flex; gap: 0.5rem; }
        .pup-search-btn { display: none; }
        .pup-clear-btn { padding: 9px 16px; background: transparent; color: var(--text-2); font-size: 0.82rem; font-weight: 600; border: 1px solid var(--border-md); border-radius: 8px; cursor: pointer; transition: background 0.15s; }
        .pup-clear-btn:hover { background: var(--surface-3); }

        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 0.8rem; }
        .chip { padding: 5px 12px; font-size: 0.76rem; font-weight: 500; color: var(--text-2); background: var(--surface); border: 1px solid var(--border-md); border-radius: 999px; cursor: pointer; transition: all 0.15s; }
        .chip:hover { border-color: var(--primary-light); color: var(--text-1); }
        .chip.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        body.dark-mode .chip.active { background: var(--secondary); border-color: var(--secondary); color: #0d1117; }

        /* ========== MOST REQUESTED ========== */
        .block-title { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--text-3); margin-bottom: 0.6rem; }
        .popular-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
        .popular-card { padding: 0.9rem 1rem; text-align: left; cursor: pointer; border-top: 3px solid var(--secondary); transition: transform 0.15s, box-shadow 0.15s; display: flex; flex-direction: column; gap: 4px; color: inherit; }
        .popular-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16,24,40,0.10); }
        .popular-code { font-size: 0.66rem; font-weight: 700; letter-spacing: 0.06em; color: var(--accent-text); }
        .popular-name { font-size: 0.85rem; font-weight: 600; color: var(--text-1); line-height: 1.3; }
        .popular-desc { font-size: 0.74rem; color: var(--text-3); line-height: 1.35; }

        /* ========== GROUPS (accordion) ========== */
        .category-group { margin-bottom: 0.75rem; overflow: hidden; }
        .group-header { display: flex; align-items: center; gap: 12px; padding: 14px 18px; width: 100%; background: var(--surface); border: none; cursor: pointer; text-align: left; color: inherit; }
        .group-header:hover { background: var(--surface-2); }
        .group-letter { width: 32px; height: 32px; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 700; border-radius: 8px; flex-shrink: 0; font-family: 'Libre Baskerville', serif; }
        body.dark-mode .group-letter { background: var(--secondary); color: var(--primary); }
        .group-info { flex: 1; min-width: 0; }
        .group-name { font-size: 0.95rem; font-weight: 600; color: var(--text-1); }
        .group-desc { font-size: 0.76rem; color: var(--text-3); margin-top: 1px; }
        .group-count { font-size: 0.7rem; font-weight: 600; color: var(--accent-text); background: var(--accent-soft); padding: 3px 10px; border-radius: 999px; flex-shrink: 0; }
        .group-chevron { width: 18px; height: 18px; stroke: var(--text-3); fill: none; stroke-width: 2.2; transition: transform 0.2s; flex-shrink: 0; }
        .category-group:not(.collapsed) .group-chevron,
        .category-group.force-open .group-chevron { transform: rotate(180deg); }
        .group-body { border-top: 1px solid var(--border); }
        .category-group.collapsed:not(.force-open) .group-body { display: none; }

        .subsection { padding: 0.9rem 1.25rem 0.5rem; border-bottom: 1px solid var(--border); }
        .subsection:last-child { border-bottom: none; }
        .subsection-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-3); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 8px; }
        .subsection-title .count { font-size: 0.66rem; color: var(--accent-text); background: var(--accent-soft); padding: 1px 8px; border-radius: 999px; }

        .record-list { list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 0 1.5rem; }
        .record-list li { padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .record-item.is-hidden { display: none !important; }
        .record-name { font-size: 0.83rem; color: var(--text-1); font-weight: 500; flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .record-code { font-size: 0.62rem; font-family: ui-monospace, 'Courier New', monospace; color: var(--text-3); font-weight: 600; letter-spacing: 0.05em; padding: 1px 6px; background: var(--surface-3); border-radius: 4px; flex-shrink: 0; }
        .record-btn { padding: 5px 12px; background: transparent; color: var(--accent-text); font-size: 0.72rem; font-weight: 600; border: 1px solid var(--border-md); border-radius: 6px; cursor: pointer; transition: all 0.15s; flex-shrink: 0; white-space: nowrap; }
        .record-btn:hover { background: var(--primary); border-color: var(--primary); color: #fff; }
        body.dark-mode .record-btn:hover { background: var(--secondary); border-color: var(--secondary); color: #0d1117; }

        .empty-state { padding: 2.5rem 1rem; text-align: center; color: var(--text-3); font-size: 0.85rem; display: none; }
        .empty-state.show { display: block; }

        /* ========== SECTION HEADER ========== */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin: 2rem 0 0.75rem; flex-wrap: wrap; gap: 0.75rem; }
        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 18px; background: var(--secondary); border-radius: 2px; }

        /* ========== SIDEBAR ========== */
        .sidebar { display: flex; flex-direction: column; gap: 1rem; position: sticky; top: 90px; }
        .sidebar-box { padding: 1rem 1.1rem 0.4rem; }
        .sidebar-title { font-size: 0.7rem; font-weight: 700; color: var(--text-2); letter-spacing: 0.08em; text-transform: uppercase; padding-bottom: 0.6rem; border-bottom: 2px solid var(--secondary); margin-bottom: 0.4rem; }
        .sidebar-list { list-style: none; }
        .sidebar-list li { padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.8rem; }
        .sidebar-list li:last-child { border-bottom: none; }
        .sidebar-list li a { color: var(--text-2); text-decoration: none; transition: color 0.15s; display: flex; align-items: center; gap: 8px; }
        .sidebar-list li a:hover { color: var(--accent-text); }
        .sidebar-list li a::before { content: ''; width: 4px; height: 4px; border-radius: 50%; background: var(--text-3); flex-shrink: 0; }
        .sidebar-list li a .cat-count { margin-left: auto; font-size: 0.66rem; color: var(--accent-text); background: var(--accent-soft); padding: 1px 7px; border-radius: 999px; font-weight: 600; }

        /* ========== STATUS BADGES ========== */
        .status-badge { display: inline-block; padding: 3px 12px; font-weight: 600; font-size: 0.72rem; border-radius: 999px; white-space: nowrap; background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-badge.pending { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-badge.processing, .status-badge.accepted, .status-badge.verified { background: var(--st-info-bg); color: var(--st-info-fg); }
        .status-badge.completed, .status-badge.claimed, .status-badge.ready { background: var(--st-ok-bg); color: var(--st-ok-fg); }
        .status-badge.rejected { background: var(--st-bad-bg); color: var(--st-bad-fg); }

        /* ========== HISTORY TABLE ========== */
        .history-card { overflow: hidden; }
        .table-wrap { overflow-x: auto; }
        .hist-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 640px; }
        .hist-table th { text-align: left; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-3); padding: 10px 16px; background: var(--surface-2); border-bottom: 1px solid var(--border); }
        .hist-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); color: var(--text-1); vertical-align: middle; }
        .hist-row { cursor: pointer; transition: background 0.12s; }
        .hist-row:hover { background: var(--surface-2); }
        .hist-table tr:last-child td { border-bottom: none; }
        .doc-cell { display: flex; flex-direction: column; gap: 2px; }
        .doc-name { font-weight: 600; }
        .doc-purpose { font-size: 0.74rem; color: var(--text-3); max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ticket { font-size: 0.74rem; font-weight: 700; color: var(--accent-text); font-family: ui-monospace, 'Courier New', monospace; }
        .ticket.none { color: var(--st-pending-fg); font-family: inherit; font-weight: 500; }
        .date-cell { color: var(--text-2); font-size: 0.78rem; white-space: nowrap; }
        .row-chevron { width: 14px; height: 14px; stroke: var(--text-3); fill: none; stroke-width: 2.4; transition: transform 0.2s; }
        .hist-row.open .row-chevron { transform: rotate(180deg); }
        .tl-row { display: none; }
        .tl-row.open { display: table-row; }
        .tl-row td { background: var(--surface-2); padding: 1.1rem 1.5rem 1.3rem; }

        .timeline { display: flex; align-items: flex-start; list-style: none; max-width: 640px; }
        .timeline li { flex: 1; position: relative; text-align: center; font-size: 0.7rem; color: var(--text-3); }
        .timeline li::before { content: ''; display: block; width: 14px; height: 14px; margin: 0 auto 6px; border-radius: 50%; background: var(--surface); border: 2px solid var(--border-md); position: relative; z-index: 1; }
        .timeline li::after { content: ''; position: absolute; top: 6px; left: -50%; width: 100%; height: 2px; background: var(--border-md); z-index: 0; }
        .timeline li:first-child::after { display: none; }
        .timeline li.done { color: var(--text-1); font-weight: 600; }
        .timeline li.done::before { background: var(--primary); border-color: var(--primary); }
        .timeline li.done::after { background: var(--primary); }
        body.dark-mode .timeline li.done::before { background: var(--secondary); border-color: var(--secondary); }
        body.dark-mode .timeline li.done::after { background: var(--secondary); }
        .timeline li.current::before { box-shadow: 0 0 0 4px rgba(0,51,102,0.15); }
        .tl-rejected { font-size: 0.8rem; color: var(--st-bad-fg); font-weight: 600; }

        /* ========== FORM ========== */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
        .form-input { width: 100%; padding: 9px 12px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-1); font-size: 0.88rem; border-radius: 8px; }
        .form-input:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(0,51,102,0.10); }
        .form-input:disabled { background: var(--surface-3); color: var(--text-2); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .field-error { display: none; font-size: 0.74rem; color: var(--st-bad-fg); margin-top: 5px; }
        .field-error.show { display: block; }

        .btn-primary, .btn-secondary { padding: 9px 18px; font-size: 0.84rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.15s; }
        .btn-primary { background: var(--btn); color: #fff; border: 1px solid var(--btn); }
        .btn-primary:hover { background: var(--btn-hover); }
        .btn-secondary { background: transparent; color: var(--text-2); border: 1px solid var(--border-md); }
        .btn-secondary:hover { background: var(--surface-3); }

        /* ========== ALERT ========== */
        .alert { padding: 12px 16px; margin-bottom: 1rem; font-size: 0.85rem; border: 1px solid; border-radius: 8px; }
        .alert-success { background: var(--st-ok-bg); border-color: var(--st-ok-fg); color: var(--st-ok-fg); }
        .alert-error { background: var(--st-bad-bg); border-color: var(--st-bad-fg); color: var(--st-bad-fg); }

        /* ========== REQUEST DRAWER (replaces modal; same ids) ========== */
        .modal-backdrop { position: fixed; inset: 0; background: rgba(10,20,40,0.45); z-index: 400; display: flex; justify-content: flex-end; opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; }
        .modal-backdrop.open { opacity: 1; visibility: visible; }
        .drawer { width: 440px; max-width: 100vw; height: 100%; background: var(--surface); box-shadow: var(--shadow-lg); display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.28s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .modal-backdrop.open .drawer { transform: translateX(0); }
        .drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
        .modal-title { font-size: 0.98rem; font-weight: 700; color: var(--text-1); line-height: 1.35; font-family: 'Libre Baskerville', Georgia, serif; }
        .modal-close { width: 30px; height: 30px; border: none; background: var(--surface-3); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-2); border-radius: 6px; flex-shrink: 0; }
        .modal-close:hover { background: var(--border-md); }
        .stepper { display: flex; padding: 0.9rem 1.4rem; gap: 6px; border-bottom: 1px solid var(--border); }
        .step-ind { flex: 1; font-size: 0.72rem; font-weight: 600; color: var(--text-3); padding-top: 8px; border-top: 3px solid var(--border); }
        .step-ind.active { color: var(--accent-text); border-top-color: var(--secondary); }
        .step-ind.done { color: var(--text-2); border-top-color: var(--primary); }
        .drawer-form { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.4rem; }
        .drawer-foot { display: flex; justify-content: flex-end; gap: 0.6rem; padding: 0.9rem 1.4rem; border-top: 1px solid var(--border); background: var(--surface-2); }
        .step-pane { display: none; }
        .step-pane.active { display: block; }
        .review-list { display: grid; grid-template-columns: 110px 1fr; gap: 12px 12px; font-size: 0.85rem; padding: 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); }
        .review-list dt { color: var(--text-3); }
        .review-list dd { color: var(--text-1); font-weight: 500; word-break: break-word; }
        .review-note { font-size: 0.76rem; color: var(--text-3); margin-top: 0.9rem; line-height: 1.5; }

        /* ========== OFF-CANVAS (profile) ========== */
        .offcanvas { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.92); width: 460px; max-width: 90vw; max-height: 80vh; background: var(--surface); box-shadow: 0 20px 60px rgba(0,0,0,0.2); z-index: 250; transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1), opacity 0.3s ease; display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; opacity: 0; visibility: hidden; pointer-events: none; }
        .offcanvas.open { transform: translate(-50%, -50%) scale(1); opacity: 1; visibility: visible; pointer-events: auto; }
        .offcanvas-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); background: var(--surface); border-radius: 12px 12px 0 0; flex-shrink: 0; }
        .offcanvas-header h3 { font-size: 1rem; font-weight: 600; color: var(--text-1); margin: 0; display: flex; align-items: center; gap: 8px; }
        .offcanvas-close { width: 30px; height: 30px; border: none; background: var(--surface-3); color: var(--text-2); cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 6px; }
        .offcanvas-close:hover { background: var(--border-md); color: var(--text-1); }
        .offcanvas-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
        .offcanvas-section { margin-bottom: 1.5rem; }
        .offcanvas-section:last-child { margin-bottom: 0; }
        .offcanvas-section-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); margin-bottom: 0.75rem; }
        .offcanvas-overlay { position: fixed; inset: 0; background: rgba(10,20,40,0.5); backdrop-filter: blur(4px); z-index: 249; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .offcanvas-overlay.open { opacity: 1; visibility: visible; }

        .profile-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem 1rem; font-size: 0.85rem; }
        .profile-info-grid .info-label { color: var(--text-3); }
        .profile-info-grid .info-value { color: var(--text-1); font-weight: 500; word-break: break-word; }
        .profile-header-card { display: flex; align-items: center; gap: 1.25rem; padding: 0.5rem 0 1.25rem; border-bottom: 1px solid var(--border); margin-bottom: 1.25rem; }
        .profile-header-card .profile-avatar-large { width: 60px; height: 60px; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; color: var(--primary); flex-shrink: 0; border-radius: 50%; }
        .profile-header-card .profile-name { font-size: 1.05rem; font-weight: 600; color: var(--text-1); }
        .profile-header-card .profile-role { font-size: 0.8rem; color: var(--text-3); text-transform: capitalize; }

        /* ========== FAB ========== */
        .fab { position: fixed; bottom: 28px; right: 28px; width: 52px; height: 52px; background: var(--primary); border: 2px solid var(--secondary); box-shadow: 0 6px 18px rgba(0,51,102,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 150; border-radius: 12px; }
        .fab:hover { transform: scale(1.05); background: var(--primary-light); }
        .fab svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }

        /* ===== Reference-style header + flat sidebar (placed late so it wins) ===== */
        .page-head { padding: 0.75rem 0 0.5rem; margin-bottom: 1.5rem; }
        .page-title { font-family: 'Inter', system-ui, sans-serif; font-size: 2rem; font-weight: 700; color: var(--accent-text); letter-spacing: -0.015em; }
        .page-sub { color: var(--text-2); }
        body.dark-mode .page-title { color: var(--text-1); }

        .sidebar-box.card { background: transparent; border: none; box-shadow: none; padding: 0; }
        .sidebar-title { font-size: 0.82rem; font-weight: 500; letter-spacing: 0.04em; color: var(--accent-text); border-bottom: none; padding-bottom: 0.7rem; margin-bottom: 0.6rem; position: relative; }
        .sidebar-title::after { content: ''; position: absolute; left: 0; bottom: 0; width: 48px; height: 2px; background: var(--secondary); }
        body.dark-mode .sidebar-title { color: var(--text-1); }
        .sidebar { gap: 1.75rem; }

        /* ========== RESPONSIVE ========== */
        @media(max-width:1000px){
            .content-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; order: 2; }
            .main-content { order: 1; }
            .popular-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width:768px){
            .offcanvas { width: 85vw; max-height: 85vh; }
            .header-nav-links { gap: 0.05rem; }
            .header-nav-link { font-size: 0.6rem; padding: 0.25rem 0.4rem; }
            .profile-info-grid { grid-template-columns: 1fr; gap: 0.5rem; }
            .profile-header-card { flex-direction: column; text-align: center; }
            .page-title { font-size: 1.3rem; }
            .pup-search-form { grid-template-columns: 1fr; }
            .record-list { grid-template-columns: 1fr; }
            .stat-box { flex-direction: column; gap: 4px; padding: 0.7rem 0.9rem; }
            .group-header { padding: 12px 14px; }
            .subsection { padding: 0.85rem 1rem 0.4rem; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media(max-width:480px){
            .header-nav-links { display: none !important; }
            .page { padding: 1rem; }
            .popular-grid { grid-template-columns: 1fr; }
            .offcanvas { width: 92vw; max-height: 90vh; }
        }

        /* ========== HELP CENTER ========== */
        .breadcrumb { display:flex; align-items:center; gap:8px; font-size:0.8rem; color:var(--text-3); }
        .breadcrumb a { color:var(--text-2); text-decoration:none; }
        .breadcrumb a:hover { color:var(--accent-text); }
        .breadcrumb .here { color:var(--text-1); font-weight:500; }
        .help-title { font-family:'Inter', system-ui, sans-serif; font-size:2rem; font-weight:700; color:var(--accent-text); letter-spacing:-0.015em; margin-top:6px; }
        body.dark-mode .help-title { color:var(--text-1); }
        .help-sub { color:var(--text-2); font-size:0.9rem; margin-top:4px; }

        /* search */
        .search-box { display:flex; gap:8px; margin-bottom:1.5rem; }
        .search-box input { flex:1; padding:11px 14px; border:1px solid var(--border-md); border-radius:8px; font-size:0.9rem; background:var(--surface); color:var(--text-1); transition:border-color 0.15s, box-shadow 0.15s; }
        .search-box input:focus { outline:none; border-color:var(--primary-light); box-shadow:0 0 0 3px rgba(0,51,102,0.10); }
        .btn-search { padding:10px 20px; border:none; border-radius:8px; background:var(--btn); color:#fff; font-size:0.86rem; font-weight:600; cursor:pointer; transition:background 0.15s; text-decoration:none; display:inline-flex; align-items:center; }
        .btn-search:hover { background:var(--btn-hover); }

        /* category chips */
        .faq-category-nav { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:1.25rem; }
        .faq-category-nav .cat-btn { padding:6px 14px; font-size:0.78rem; font-weight:500; color:var(--text-2); background:var(--surface); border:1px solid var(--border-md); border-radius:999px; cursor:pointer; transition:all 0.15s; text-decoration:none; }
        .faq-category-nav .cat-btn:hover { border-color:var(--primary-light); color:var(--text-1); }
        .faq-category-nav .cat-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        body.dark-mode .faq-category-nav .cat-btn.active { background:var(--secondary); border-color:var(--secondary); color:#0d1117; }

        /* card pieces (help page uses .card-head/.card-body) */
        .card-head { display:flex; align-items:center; justify-content:space-between; padding:1.1rem 1.4rem 0; }
        .card-title { display:flex; align-items:center; gap:8px; font-size:1rem; font-weight:700; color:var(--text-1); font-family:'Libre Baskerville', Georgia, serif; }
        .card-title::before { content:''; width:4px; height:18px; background:var(--secondary); border-radius:2px; }
        .card-body { padding:0.75rem 1.4rem 1.3rem; }
        .help-card-wrap { margin-bottom:1.5rem; }

        /* faq accordion */
        .faq-item { padding:14px 0; border-bottom:1px solid var(--border); cursor:pointer; }
        .faq-item:last-child { border-bottom:none; }
        .faq-item .faq-question { display:flex; align-items:center; justify-content:space-between; gap:12px; font-weight:600; color:var(--text-1); font-size:0.9rem; }
        .faq-item .faq-question .faq-toggle { font-size:0.7rem; transition:transform 0.2s ease; color:var(--text-3); flex-shrink:0; }
        .faq-item.open .faq-question .faq-toggle { transform:rotate(180deg); }
        .faq-item.open .faq-question { color:var(--accent-text); }
        body.dark-mode .faq-item.open .faq-question { color:var(--text-1); }
        .faq-item .faq-answer { max-height:0; overflow:hidden; transition:max-height 0.3s ease, padding 0.3s ease; color:var(--text-2); font-size:0.85rem; line-height:1.65; }
        .faq-item.open .faq-answer { max-height:500px; padding-top:10px; }

        /* search results */
        .section-header { display:flex; align-items:center; justify-content:space-between; margin:0 0 0.75rem; flex-wrap:wrap; gap:0.75rem; }
        .section-title { font-size:1.05rem; font-weight:700; color:var(--text-1); display:flex; align-items:center; gap:8px; }
        .section-title::before { content:''; width:4px; height:18px; background:var(--secondary); border-radius:2px; }
        .search-result-item { padding:12px 0; border-bottom:1px solid var(--border); }
        .search-result-item:last-child { border-bottom:none; }
        .search-result-item .result-category { font-size:0.68rem; font-weight:700; color:var(--accent-text); text-transform:uppercase; letter-spacing:0.06em; }
        .search-result-item .result-question { font-weight:600; color:var(--text-1); font-size:0.9rem; margin:3px 0; }
        .search-result-item .result-answer { color:var(--text-2); font-size:0.85rem; line-height:1.6; }

        /* still need help */
        .help-contact-card { display:flex; align-items:center; gap:16px; padding:14px 16px; border:1px solid var(--border); border-radius:10px; background:var(--surface-2); flex-wrap:wrap; }
        .btn-primary { display:inline-flex; align-items:center; gap:5px; text-decoration:none; }

        /* footer (inline footer kept as-is) */
        footer { border-top:1px solid var(--border); background:var(--surface); }

        /* soft reveal (same fade-scroll hooks, gentler) */
        .fade-scroll { opacity:0; transform:translateY(16px); transition:opacity 0.5s ease, transform 0.5s ease; }
        .fade-scroll.visible { opacity:1; transform:translateY(0); }

        /* ===== legacy modals (centered) — same ids/classes the JS uses ===== */
        .modal-backdrop { align-items:center; justify-content:center; }
        .modal { background:var(--surface); border-radius:14px; box-shadow:var(--shadow-lg); width:100%; max-width:440px; max-height:90vh; overflow:auto; border:1px solid var(--border); animation:modalIn 0.18s ease; }
        @keyframes modalIn { from { opacity:0; transform:scale(0.96) translateY(-8px); } to { opacity:1; transform:scale(1) translateY(0); } }
        .modal-head { display:flex; align-items:center; justify-content:space-between; padding:1.15rem 1.5rem 0; }
        .modal-body { padding:1rem 1.5rem 1.5rem; }
        .modal-close svg { width:15px; height:15px; }

        .service-modal-backdrop { position:fixed; inset:0; background:rgba(10,20,40,0.45); z-index:500; display:none; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
        .service-modal-backdrop.open { display:flex; }
        .service-modal { background:var(--surface); border-radius:14px; box-shadow:var(--shadow-lg); width:100%; max-width:420px; max-height:85vh; overflow:auto; border:1px solid var(--border); animation:modalIn 0.2s ease; }
        .service-modal-head { display:flex; align-items:center; justify-content:space-between; padding:1.2rem 1.5rem 0.5rem; }
        .service-modal-title { font-size:1rem; font-weight:700; color:var(--text-1); font-family:'Libre Baskerville', Georgia, serif; }
        .service-modal-close { width:30px; height:30px; border:none; background:var(--surface-3); cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-2); border-radius:6px; }
        .service-modal-close:hover { background:var(--border-md); }
        .service-modal-close svg { width:15px; height:15px; }
        .service-modal-body { padding:0.5rem 1.5rem 1.5rem; }
        .service-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:8px; cursor:pointer; transition:background 0.15s; color:var(--text-2); font-size:0.85rem; border:1px solid transparent; margin-bottom:4px; }
        .service-item:hover { background:var(--surface-2); border-color:var(--border); color:var(--text-1); }
        .service-item .label { font-weight:500; }
        .service-item .desc { font-size:0.7rem; color:var(--text-3); margin-left:auto; }
        .service-divider { height:1px; background:var(--border); margin:6px 0; }

        /* profile settings modal */
        .profile-modal .modal { max-width:460px; }
        .profile-modal .modal-body { padding:0.25rem 1.5rem 1.5rem; }
        .profile-avatar-section { display:flex; align-items:center; gap:1.25rem; padding:0.75rem 0 0.5rem; border-bottom:1px solid var(--border); margin-bottom:1.25rem; }
        .profile-avatar { width:56px; height:56px; border-radius:50%; background:var(--secondary); display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:700; color:var(--primary); flex-shrink:0; }
        .profile-avatar-actions { display:flex; flex-direction:column; gap:2px; }
        .profile-avatar-actions .change-photo-btn { background:transparent; border:1px solid var(--border-md); border-radius:6px; padding:4px 12px; font-size:0.75rem; font-weight:500; color:var(--text-2); cursor:pointer; transition:all 0.15s; display:inline-flex; align-items:center; gap:6px; width:fit-content; }
        .profile-avatar-actions .change-photo-btn:hover { background:var(--surface-2); }
        .profile-avatar-actions .helper-text { font-size:0.65rem; color:var(--text-3); }
        .profile-form-group { display:flex; flex-direction:column; gap:4px; margin-bottom:14px; }
        .profile-form-group label { font-size:0.75rem; font-weight:600; color:var(--text-2); letter-spacing:0.02em; }
        .profile-form-group input, .profile-form-group select { padding:8px 12px; border:1px solid var(--border-md); border-radius:8px; font-size:0.875rem; background:var(--surface); color:var(--text-1); width:100%; }
        .profile-form-group input:focus, .profile-form-group select:focus { outline:none; border-color:var(--primary-light); box-shadow:0 0 0 3px rgba(0,51,102,0.10); }
        .profile-form-group input:disabled, .profile-form-group select:disabled { background:var(--surface-3); color:var(--text-3); cursor:not-allowed; }
        .profile-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .profile-footer { border-top:1px solid var(--border); padding-top:1.25rem; margin-top:0.5rem; display:flex; justify-content:flex-end; gap:12px; }
        .profile-footer .btn-secondary { padding:7px 20px; font-size:0.8125rem; }
        .profile-footer .btn-save { background:var(--btn); border:none; border-radius:8px; padding:7px 24px; font-size:0.8125rem; font-weight:600; color:#fff; cursor:pointer; transition:background 0.15s; }
        .profile-footer .btn-save:hover { background:var(--btn-hover); }
        .profile-error { color:var(--st-bad-fg); font-size:0.7rem; margin-top:2px; display:none; }
        .profile-error.show { display:block; }
        .profile-success { color:var(--st-ok-fg); font-size:0.75rem; text-align:center; padding:6px 0; display:none; }
        .profile-success.show { display:block; }
        .profile-edit-btn { display:inline-flex; align-items:center; gap:6px; margin-top:0.5rem; padding:0.5rem 1.25rem; border:1px solid var(--border-md); border-radius:8px; background:transparent; font-size:0.8125rem; font-weight:500; color:var(--text-2); cursor:pointer; transition:all 0.15s; }
        .profile-edit-btn:hover { background:var(--surface-2); }

        /* dark mode toggle in side nav (kept) */
        .side-nav-dark-toggle { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 0.9rem; font-size:0.85rem; font-weight:500; color:var(--text-2); transition:background 0.12s, color 0.12s; margin-bottom:2px; cursor:pointer; }
        .side-nav-dark-toggle:hover { background:var(--surface-2); color:var(--text-1); }
        .side-nav-dark-toggle .toggle-track { width:40px; height:22px; background:#ccc; border-radius:12px; position:relative; transition:background 0.3s ease; flex-shrink:0; }
        .side-nav-dark-toggle .toggle-track .toggle-thumb { width:18px; height:18px; background:white; border-radius:50%; position:absolute; top:2px; left:2px; transition:transform 0.3s ease; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
        body.dark-mode .side-nav-dark-toggle .toggle-track { background:#ffd700; }
        body.dark-mode .side-nav-dark-toggle .toggle-track .toggle-thumb { transform:translateX(18px); background:#0d1117; }

        @media(max-width:640px){
            .search-box { flex-direction:column; }
            .faq-category-nav .cat-btn { font-size:0.72rem; padding:5px 11px; }
            .profile-form-row { grid-template-columns:1fr; gap:0; }
            .help-title { font-size:1.5rem; }
        }
    </style>
    <link rel="stylesheet" href="../../includes/side_nav.css">
</head>
<body>

<!-- ========== SIDE NAV ========== -->
<?php include '../../includes/side_nav.php'; ?>

<!-- ========== HEADER ========== -->
<?php include '../../includes/user_header.php'; ?>

<!-- ========== SERVICE MODAL ========== -->
<div class="service-modal-backdrop" id="serviceModalBackdrop" onclick="if(event.target===this) closeServiceModal()">
    <div class="service-modal" id="serviceModalBox">
        <div class="service-modal-head">
            <span class="service-modal-title" id="serviceModalTitle">Services</span>
            <button class="service-modal-close" onclick="closeServiceModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="service-modal-body" id="serviceModalBody"></div>
    </div>
</div>

<!-- ========== PROFILE SETTINGS MODAL ========== -->
<div class="modal-backdrop profile-modal" id="profileModalBackdrop" onclick="if(event.target===this) closeProfileModal()">
    <div class="modal" id="profileModalBox">
        <div class="modal-head">
            <span class="modal-title">Profile Settings</span>
            <button class="modal-close" onclick="closeProfileModal()" aria-label="Close profile settings">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="profile-avatar-section">
                <div class="profile-avatar" id="profileAvatar"><?php echo $user_initials; ?></div>
                <div class="profile-avatar-actions">
                    <button class="change-photo-btn" id="changePhotoBtn" aria-label="Change photo">
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

<!-- ========== MODAL BACKDROP ========== -->
<div class="modal-backdrop" id="modalBackdrop" onclick="if(event.target===this) closeModal()">
    <div class="modal" id="modalBox">
        <div class="modal-head">
            <span class="modal-title" id="modalTitle"></span>
            <button class="modal-close" onclick="closeModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- ========== OFF-CANVAS OVERLAY ========== -->
<div class="offcanvas-overlay" id="offcanvasOverlay" onclick="closeOffcanvas()"></div>

<!-- ========== OFF-CANVAS PANEL - CENTERED ========== -->
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
    <!-- Page Header / Breadcrumb -->
    <div class="fade-scroll" style="margin-bottom:1.5rem;">
        <div class="breadcrumb">
            <a href="../User_Dashboard.php">Dashboard</a>
            <span>/</span>
            <span class="here">Help Center</span>
        </div>
        <h1 class="help-title">Help Center &amp; FAQs</h1>
        <p class="help-sub">Find answers to common questions about the Registrar Portal</p>
    </div>

    <!-- Search Bar -->
    <div class="fade-scroll">
        <form method="GET" action="" class="search-box">
            <input type="text" name="search" placeholder="Search for answers..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="btn-search"> Search</button>
            <?php if ($is_searching): ?>
                <a href="help_center.php" class="btn-search" style="background:var(--text-3);">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($is_searching): ?>
        <!-- Search Results -->
        <section class="help-card-wrap">
            <div class="section-header">
                <h2 class="section-title">Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
                <span style="font-size:0.75rem;color:var(--text-3);"><?php echo count($search_results); ?> results found</span>
            </div>
            <div class="card fade-scroll">
                <div class="card-body">
                    <?php if (empty($search_results)): ?>
                        <div style="padding:1.5rem 0;text-align:center;color:var(--text-3);">
                            <div style="font-size:2rem;margin-bottom:8px;">🔍</div>
                            No results found for "<?php echo htmlspecialchars($search_query); ?>".<br>
                            <span style="font-size:0.8rem;">Try using different keywords or browse the categories below.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($search_results as $result): ?>
                            <div class="search-result-item">
                                <div class="result-category"><?php echo $result['category_icon']; ?> <?php echo htmlspecialchars($result['category_label']); ?></div>
                                <div class="result-question"><?php echo htmlspecialchars($result['question']); ?></div>
                                <div class="result-answer"><?php echo htmlspecialchars($result['answer']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php else: ?>
        <!-- Category Navigation -->
        <div class="faq-category-nav fade-scroll">
            <?php foreach ($faq_categories as $cat_key => $category): ?>
                <a href="?category=<?php echo $cat_key; ?>" class="cat-btn <?php echo $active_category === $cat_key ? 'active' : ''; ?>">
                    <?php echo $category['icon']; ?> <?php echo $category['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- FAQ Display -->
        <section class="help-card-wrap">
            <div class="card fade-scroll">
                <div class="card-head">
                    <h3 class="card-title">
                        <?php 
                            $current_cat = $faq_categories[$active_category] ?? $faq_categories['general'];
                            echo $current_cat['icon'] . ' ' . $current_cat['label'];
                        ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php 
                        $faqs_to_display = $faq_categories[$active_category]['faqs'] ?? $faq_categories['general']['faqs'];
                    ?>
                    <?php foreach ($faqs_to_display as $index => $faq): ?>
                        <div class="faq-item <?php echo $index === 0 ? 'open' : ''; ?>" onclick="toggleFaq(this)">
                            <div class="faq-question">
                                <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer"><?php echo htmlspecialchars($faq['answer']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Still Need Help? -->
    <section>
        <div class="card fade-scroll">
            <div class="card-head">
                <h3 class="card-title"> Still Need Help?</h3>
            </div>
            <div class="card-body">
                <div class="help-contact-card">
                    <div style="font-size:1.5rem;"> </div>
                    <div style="flex:1;min-width:200px;">
                        <div style="font-weight:600;color:var(--text-1);font-size:0.95rem;">Can't find what you're looking for?</div>
                        <div style="color:var(--text-3);font-size:0.85rem;">Our team is here to help you with any questions or concerns.</div>
                    </div>
                    <a href="../modules/contact_registrar.php" class="btn-primary" style="white-space:nowrap;">Contact Us</a>
                </div>
            </div>
        </div>
    </section>
</main>
</div>

<footer style="border-top:1px solid var(--border);padding:1.25rem;text-align:center;transition:border-color 0.3s ease;margin-top:0;">
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

function handleSideNavAction(action) {
    if (action === 'My Profile') {
        openOffcanvas();
        closeSideNav();
        return;
    }
    alert('📌 ' + action + '\n\nThis will open the ' + action + ' section.');
    closeSideNav();
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

// ========== POPUP ACTIONS ==========
function handlePopupAction(category, action) {
    alert('📌 ' + category.toUpperCase() + ' → ' + action + '\n\nThis will open the ' + action + ' module.');
}

// ========== SERVICE MODAL ==========
var serviceData = {
    help: { 
        title: 'Help Services', 
        services: [
            { label: 'FAQs', desc: 'Browse frequently asked questions' },
            { label: 'Search', desc: 'Search for answers' },
            { label: 'Contact Support', desc: 'Get help from our team' },
            { label: 'Feedback', desc: 'Send us your feedback' }
        ] 
    }
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

// ========== PROFILE SETTINGS MODAL ==========
function openProfileModal() {
    closeDropdown();
    document.getElementById('profileSuccess').classList.remove('show');
    document.getElementById('fullNameError').classList.remove('show');
    document.getElementById('profileForm').reset();
    document.getElementById('fullName').value = '<?php echo addslashes($user_fullname); ?>';
    document.getElementById('email').value = '<?php echo addslashes($user_email); ?>';
    document.getElementById('studentId').value = '<?php echo addslashes($user_student_id ?? '2026-12345'); ?>';
    document.getElementById('college').value = '<?php echo addslashes($user_college ?? 'College of Information Technology'); ?>';
    document.getElementById('course').value = '<?php echo addslashes($user_course ?? 'BS Information Technology'); ?>';
    document.getElementById('yearLevel').value = '<?php echo addslashes($user_year_level ?? '3rd'); ?>';
    document.getElementById('profileAvatar').textContent = '<?php echo $user_initials; ?>';
    document.getElementById('profileModalBackdrop').classList.add('open');
}

function closeProfileModal() {
    document.getElementById('profileModalBackdrop').classList.remove('open');
    document.getElementById('fullNameError').classList.remove('show');
    document.getElementById('profileSuccess').classList.remove('show');
}

document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fullName = document.getElementById('fullName').value.trim();
    var errorEl = document.getElementById('fullNameError');
    var successEl = document.getElementById('profileSuccess');
    if (!fullName) {
        errorEl.classList.add('show');
        successEl.classList.remove('show');
        return;
    }
    errorEl.classList.remove('show');
    successEl.classList.add('show');
    var nameParts = fullName.split(' ');
    var initials = '';
    if (nameParts.length >= 2) {
        initials = nameParts[0].charAt(0).toUpperCase() + nameParts[1].charAt(0).toUpperCase();
    } else if (nameParts.length === 1) {
        initials = nameParts[0].charAt(0).toUpperCase();
    } else {
        initials = 'JD';
    }
    document.getElementById('profileAvatar').textContent = initials || 'JD';
    setTimeout(function() {
        closeProfileModal();
        alert('✅ Profile updated successfully!');
    }, 800);
});

document.getElementById('changePhotoBtn').addEventListener('click', function() {
    document.getElementById('photoInput').click();
});

document.getElementById('photoInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        alert('Please select a JPG or PNG image.');
        this.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('File size exceeds 2MB. Please select a smaller image.');
        this.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(ev) {
        var avatar = document.getElementById('profileAvatar');
        avatar.textContent = '📷';
        setTimeout(function() {
            avatar.textContent = '<?php echo $user_initials; ?>';
        }, 800);
        alert('✅ Photo updated successfully! (demo)');
    };
    reader.readAsDataURL(file);
    this.value = '';
});

// ========== MODAL ==========
function openModal(type) {
    closeDropdown();
    var title = document.getElementById('modalTitle');
    var body = document.getElementById('modalBody');
    var map = {
        profile: {
            t: 'Profile Settings',
            b: '<div style="display:flex;flex-direction:column;gap:10px;font-size:.875rem;color:var(--text-2)"><div style="display:grid;grid-template-columns:110px 1fr;gap:8px 12px;padding-top:4px"><span style="color:var(--text-3)">Full name</span><span style="color:var(--text-1);font-weight:500"><?php echo htmlspecialchars($user_fullname); ?></span><span style="color:var(--text-3)">Email</span><span><?php echo htmlspecialchars($user_email); ?></span><span style="color:var(--text-3)">Student ID</span><span><?php echo htmlspecialchars($user_student_id ?? '2026-12345'); ?></span><span style="color:var(--text-3)">College</span><span><?php echo htmlspecialchars($user_college ?? 'College of Information Technology'); ?></span><span style="color:var(--text-3)">Course</span><span><?php echo htmlspecialchars($user_course ?? 'BS Information Technology'); ?></span><span style="color:var(--text-3)">Year Level</span><span><?php echo htmlspecialchars($user_year_level ?? '3rd Year'); ?></span></div><div style="display:flex;justify-content:flex-end;margin-top:8px"><button class="btn-primary" onclick="closeModal()">Close</button></div></div>'
        },
        preferences: {  
            t: 'Preferences',
            b: '<div style="display:flex;flex-direction:column;gap:12px"><label style="display:flex;align-items:center;gap:10px;font-size:.875rem;cursor:pointer"><input type="checkbox" id="darkModePref" onchange="toggleDarkModeFromPref()"> Dark mode</label><label style="display:flex;align-items:center;gap:10px;font-size:.875rem;cursor:pointer"><input type="checkbox"> Compact view</label><label style="display:flex;align-items:center;gap:10px;font-size:.875rem;cursor:pointer"><input type="checkbox" checked> Email notifications</label><div style="display:flex;justify-content:flex-end;margin-top:6px"><button class="btn-primary" onclick="closeModal()">Save</button></div></div>'
        }
    };
    var m = map[type];
    if(!m) return;
    title.textContent = m.t;
    body.innerHTML = m.b;
    document.getElementById('modalBackdrop').classList.add('open');
    if (type === 'preferences') {
        setTimeout(function() {
            var checkbox = document.getElementById('darkModePref');
            if (checkbox) checkbox.checked = document.body.classList.contains('dark-mode');
        }, 50);
    }
}

function toggleDarkModeFromPref() {
    var checkbox = document.getElementById('darkModePref');
    if (checkbox && checkbox.checked) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'true');
    } else if (checkbox) {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'false');
    }
}

function closeModal() { document.getElementById('modalBackdrop').classList.remove('open'); }
// ========== SIGN OUT ==========
function doSignOut() {
    if (confirm('Are you sure you want to sign out?')) {
        window.location.href = '../../includes/logout.php';  // 2 LEVELS UP!
    }
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

// ========== FAQ TOGGLE ==========
function toggleFaq(element) {
    var isOpen = element.classList.contains('open');
    // Close all other FAQs in the same card
    var parent = element.parentElement;
    if (parent) {
        var allFaqs = parent.querySelectorAll('.faq-item');
        allFaqs.forEach(function(faq) {
            faq.classList.remove('open');
        });
    }
    if (!isOpen) {
        element.classList.add('open');
    }
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