<?php
// ============================================
// ENABLE ERROR REPORTING FOR DEBUGGING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// START SESSION
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
// DB CONNECTION
// ============================================
$possiblePaths = [
    __DIR__ . '/../../config/db_connect_pg.php',
    __DIR__ . '/../../../config/db_connect_pg.php',
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

if ($dbConnected) {
    $database = new Database();
    $pdo = $database->getConnection();
}


// ============================================
// CERTIFICATES CATALOG (codes match the student Academic Records + admin Services)
// ============================================
$cert_catalog = [
    ['code' => 'COE', 'name' => 'Certificate of Enrollment',               'cat' => 'enrollment', 'desc' => 'Proof that you are officially enrolled this term.'],
    ['code' => 'CRG', 'name' => 'Certificate of Registration',             'cat' => 'enrollment', 'desc' => 'Official copy of your registration for the term.'],
    ['code' => 'CNR', 'name' => 'Certificate of No Record / No Enrollment','cat' => 'enrollment', 'desc' => 'States that there is no enrollment record on file.'],
    ['code' => 'CTC', 'name' => 'Certified True Copy of Grades',           'cat' => 'grades',     'desc' => 'Registrar-certified copy of your grades.'],
    ['code' => 'CGW', 'name' => 'Certificate of General Weighted Average', 'cat' => 'grades',     'desc' => 'Certifies your computed GWA.'],
    ['code' => 'CAS', 'name' => 'Certificate of Academic Standing',        'cat' => 'grades',     'desc' => 'Certifies your current academic standing.'],
    ['code' => 'CUE', 'name' => 'Certificate of Units Earned',             'cat' => 'grades',     'desc' => 'Total units you have earned so far.'],
    ['code' => 'GMC', 'name' => 'Certificate of Good Moral Character',     'cat' => 'goodmoral',  'desc' => 'For employment, scholarships, and transfers.'],
    ['code' => 'COG', 'name' => 'Certificate of Graduation',               'cat' => 'graduation', 'desc' => 'Certifies that you have completed your degree.'],
    ['code' => 'CCP', 'name' => 'Certificate of Completion',               'cat' => 'graduation', 'desc' => 'Certifies completion of program requirements.'],
];
$cert_cats = [
    'enrollment' => 'Enrollment',
    'grades'     => 'Grades',
    'goodmoral'  => 'Good Moral',
    'graduation' => 'Graduation',
];
$cert_meta = [];
foreach ($cert_catalog as $c) { $cert_meta[$c['code']] = $c; }

// Third-party verification uses the existing Authentication Request code
$verify_code = 'AUQ';
$verify_targets = [
    'Enrollment status', 'Grades / GWA', 'Graduation and degree', 'Good moral standing', 'Other (specify in purpose)',
];

// code → display name (history table)
$code_names = [];
foreach ($cert_catalog as $c) { $code_names[$c['code']] = $c['name']; }
$code_names['AUQ'] = 'Third-party Verification';
$code_names['AUT'] = 'Authentication / Certification';

// Status → timeline step
$timeline_steps = ['Submitted', 'Verified', 'Processing', 'Ready for Pickup', 'Released'];
function status_step_index($status) {
    switch (strtoupper($status)) {
        case 'PENDING':                       return 0;
        case 'ACCEPTED': case 'VERIFIED':     return 1;
        case 'PROCESSING':                    return 2;
        case 'READY':                         return 3;
        case 'COMPLETED': case 'CLAIMED':     return 4;
        default:                              return 0;
    }
}

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================
$success_message = '';
$error_message = '';
$active_tab = 'certificates';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ---- A) Request a certificate ----
    if ($_POST['action'] === 'request_certificate') {
        $record_code = trim($_POST['record_code'] ?? '');
        $details     = trim($_POST['details'] ?? '');
        $priority    = trim($_POST['priority'] ?? 'Normal');
        $copies      = max(1, min(10, (int)($_POST['copies'] ?? 1)));
        $release     = in_array(($_POST['release'] ?? 'Pickup'), ['Pickup', 'Email'], true) ? $_POST['release'] : 'Pickup';
        if (!in_array($priority, ['Normal', 'Urgent'])) $priority = 'Normal';

        if (!isset($cert_meta[$record_code])) {
            $error_message = 'Invalid certificate selected.';
        } elseif (strlen($details) < 5) {
            $error_message = 'Please provide a purpose (at least 5 characters).';
        } elseif (!$pdo) {
            $error_message = 'Database connection failed. Please try again later.';
        } else {
            $record_name = $cert_meta[$record_code]['name'];
            try {
                $stmt = $pdo->prepare("INSERT INTO requests (user_id, request_type, details, status, priority, created_at, updated_at)
                        VALUES (:user_id, :request_type, :details, 'PENDING', :priority, NOW(), NOW()) RETURNING id");
                $stmt->execute([
                    ':user_id'      => $user_id,
                    ':request_type' => $record_code,
                    ':details'      => "[Certificate] $record_name — " . $details . " (Copies: $copies · Release: $release)",
                    ':priority'     => $priority,
                ]);
                $success_message = "Your request for \"$record_name\" has been submitted. Please wait for the admin to verify.";
            } catch (PDOException $e) {
                error_log("Certificate Request Error: " . $e->getMessage());
                $error_message = 'Database error: Unable to save your request.';
            }
        }
    }

    // ---- B) Third-party verification ----
    if ($_POST['action'] === 'request_verification') {
        $active_tab = 'verification';
        $org     = trim($_POST['org_name'] ?? '');
        $org_email = trim($_POST['org_email'] ?? '');
        $target  = trim($_POST['verify_target'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $consent = isset($_POST['consent']);

        if ($org === '' || strlen($org) < 2) {
            $error_message = 'Please enter the name of the organization or person who will verify your record.';
        } elseif (!filter_var($org_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address for the verifying party.';
        } elseif (!in_array($target, $verify_targets, true)) {
            $error_message = 'Please choose what record needs to be verified.';
        } elseif (strlen($purpose) < 5) {
            $error_message = 'Please provide a purpose (at least 5 characters).';
        } elseif (!$consent) {
            $error_message = 'You must authorize the release of this information to continue.';
        } elseif (!$pdo) {
            $error_message = 'Database connection failed. Please try again later.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO requests (user_id, request_type, details, status, priority, created_at, updated_at)
                        VALUES (:user_id, :request_type, :details, 'PENDING', 'Normal', NOW(), NOW()) RETURNING id");
                $stmt->execute([
                    ':user_id'      => $user_id,
                    ':request_type' => $verify_code,
                    ':details'      => "[Verification] Third party: $org <$org_email> — Verify: $target — Purpose: $purpose",
                ]);
                $success_message = "Your verification request for \"$org\" has been submitted. The registrar will review it before releasing any information.";
                $active_tab = 'verification';
            } catch (PDOException $e) {
                error_log("Verification Request Error: " . $e->getMessage());
                $error_message = 'Database error: Unable to save your request.';
            }
        }
    }
}

// ============================================
// FETCH USER'S CERTIFICATION / VERIFICATION REQUESTS
// ============================================
$record_requests = [];
$count_total = 0; $count_pending = 0; $count_completed = 0;
$all_codes = array_keys($code_names);

if ($pdo) {
    try {
        $placeholders = implode(',', array_fill(0, count($all_codes), '?'));
        $stmt = $pdo->prepare("SELECT id, request_type, details, status, priority, ticket_number, created_at
                FROM requests WHERE user_id = ? AND request_type IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute(array_merge([$user_id], $all_codes));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $record_requests[] = [
                'id' => $row['id'], 'ticket_number' => $row['ticket_number'], 'code' => $row['request_type'],
                'purpose' => $row['details'], 'status' => $row['status'], 'request_date' => $row['created_at'],
            ];
            $count_total++;
            if (strtoupper($row['status']) === 'PENDING') $count_pending++;
            if (in_array(strtoupper($row['status']), ['COMPLETED', 'CLAIMED', 'READY'])) $count_completed++;
        }
    } catch (PDOException $e) {
        error_log("Certifications Fetch Error: " . $e->getMessage());
    }
}

$_SESSION['full_name'] = $user_fullname;
$_SESSION['email'] = $user_email;
$_SESSION['role'] = $user_role;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Certifications</title>

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

        /* ========== CERTIFICATIONS PAGE ========== */
        .tabs { display:flex; gap:4px; border-bottom:1px solid rgba(0,51,102,0.15); margin-bottom:1.25rem; }
        .tab-btn { padding:10px 18px; font-size:0.88rem; font-weight:600; color:var(--text-2); background:transparent; border:none; border-bottom:3px solid transparent; cursor:pointer; margin-bottom:-1px; transition:all 0.15s; display:flex; flex-direction:column; align-items:flex-start; gap:2px; text-align:left; }
        .tab-btn small { font-size:0.7rem; font-weight:400; color:var(--text-3); }
        .tab-btn:hover { color:var(--text-1); }
        .tab-btn.active { color:var(--accent-text); border-bottom-color:var(--secondary); }
        body.dark-mode .tab-btn.active { color:var(--text-1); }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }

        .cert-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.9rem; margin-bottom:1.5rem; }
        .cert-card { padding:1.05rem 1.1rem; display:flex; flex-direction:column; gap:0.5rem; border-top:3px solid var(--secondary); transition:transform 0.15s, box-shadow 0.15s; }
        .cert-card:hover { transform:translateY(-2px); box-shadow:0 8px 18px rgba(16,24,40,0.10); }
        .cert-card.is-hidden { display:none; }
        .cert-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .cert-cat { font-size:0.64rem; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--accent-text); }
        .cert-name { font-size:0.95rem; font-weight:700; color:var(--text-1); line-height:1.3; font-family:'Libre Baskerville', Georgia, serif; }
        .cert-desc { font-size:0.78rem; color:var(--text-3); line-height:1.45; flex:1; }
        .cert-foot { display:flex; align-items:center; justify-content:flex-end; margin-top:0.35rem; }

        .verify-layout { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start; margin-bottom:1.5rem; }
        .verify-form { padding:1.3rem 1.4rem; }
        .verify-form h3 { font-size:1rem; margin-bottom:0.25rem; color:var(--text-1); }
        .verify-form .lead { font-size:0.8rem; color:var(--text-3); margin-bottom:1.1rem; }
        .consent { display:flex; gap:10px; align-items:flex-start; font-size:0.78rem; color:var(--text-2); line-height:1.45; margin-bottom:1rem; }
        .consent input { margin-top:3px; flex-shrink:0; }
        .how-card { padding:1.3rem 1.4rem; }
        .how-card h3 { font-size:1rem; margin-bottom:0.9rem; color:var(--text-1); }
        .how-steps { list-style:none; display:flex; flex-direction:column; gap:1rem; counter-reset:how; }
        .how-steps li { display:flex; gap:12px; counter-increment:how; }
        .how-steps li::before { content:counter(how); width:26px; height:26px; flex-shrink:0; border-radius:50%; background:var(--primary); color:#fff; font-size:0.78rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
        body.dark-mode .how-steps li::before { background:var(--secondary); color:#0d1117; }
        .how-steps strong { display:block; font-size:0.85rem; color:var(--text-1); margin-bottom:2px; }
        .how-steps span { font-size:0.78rem; color:var(--text-3); line-height:1.45; }
        .note-box { margin-top:1.1rem; padding:0.8rem 1rem; background:var(--st-pending-bg); color:var(--st-pending-fg); border-radius:8px; font-size:0.76rem; line-height:1.5; }

        @media(max-width:1000px){ .cert-grid { grid-template-columns:repeat(2,1fr); } .verify-layout { grid-template-columns:1fr; } }
        @media(max-width:560px){ .cert-grid { grid-template-columns:1fr; } .tab-btn { padding:9px 12px; } }
    </style>
    <link rel="stylesheet" href="../../includes/side_nav.css">
</head>
<body>

<!-- ========== SIDE NAV (INCLUDE) ========== -->
<?php include '../../includes/side_nav.php'; ?>

<!-- ========== HEADER (INCLUDE) ========== -->
<?php include '../../includes/user_header.php'; ?>

<!-- ========== OFF-CANVAS (PROFILE) ========== -->
<div class="offcanvas-overlay" id="offcanvasOverlay" onclick="closeOffcanvas()"></div>
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

<!-- ========== REQUEST DRAWER (2-step: Details → Review → Submit) ========== -->
<div class="modal-backdrop" id="requestModalBackdrop" onclick="if(event.target===this) closeRequestModal()">
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="modalRecordTitle">
        <div class="drawer-head">
            <span class="modal-title" id="modalRecordTitle">Request Certificate</span>
            <button type="button" class="modal-close" onclick="closeRequestModal()" aria-label="Close">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="stepper">
            <div class="step-ind active" id="stepInd1">1 · Details</div>
            <div class="step-ind" id="stepInd2">2 · Review</div>
            <div class="step-ind" id="stepInd3">3 · Submit</div>
        </div>

        <form method="POST" action="" id="academicRequestForm" class="drawer-form">
            <input type="hidden" name="action" value="request_certificate">
            <input type="hidden" name="record_code" id="modalRecordCode" value="">
            <input type="hidden" name="record_name" id="modalRecordName" value="">
            <input type="hidden" name="category" id="modalRecordCategory" value="">

            <div class="drawer-body">
                <!-- STEP 1 -->
                <div class="step-pane active" id="stepPane1">
                    <div class="form-group">
                        <label class="form-label">Certificate</label>
                        <input type="text" class="form-input" id="modalRecordDisplay" value="" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="details">Purpose *</label>
                        <textarea name="details" id="details" class="form-input" rows="3" required placeholder="e.g., For employment application, For graduate school application"></textarea>
                        <div class="field-error" id="detailsError">Please provide a purpose (at least 5 characters).</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="copies">Number of copies</label>
                            <select name="copies" id="copies" class="form-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="release">Release</label>
                            <select name="release" id="release" class="form-input">
                                <option value="Pickup" selected>Pickup at Registrar</option>
                                <option value="Email">Send via email</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select name="priority" id="priority" class="form-input">
                            <option value="Normal" selected>Normal</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- STEP 2 -->
                <div class="step-pane" id="stepPane2">
                    <dl class="review-list">
                        <dt>Certificate</dt>   <dd id="rvRecord"></dd>
                        <dt>Category</dt> <dd id="rvCategory"></dd>
                        <dt>Purpose</dt>  <dd id="rvPurpose"></dd>
                        <dt>Copies</dt>   <dd id="rvCopies"></dd>
                        <dt>Release</dt>  <dd id="rvRelease"></dd>
                        <dt>Priority</dt> <dd id="rvPriority"></dd>
                    </dl>
                    <p class="review-note">Please check your details. After you submit, the registrar will verify your request and you can track it under <strong>Request History</strong>.</p>
                </div>
            </div>

            <div class="drawer-foot">
                <button type="button" class="btn-secondary" id="btnBack" onclick="closeRequestModal()">Cancel</button>
                <button type="button" class="btn-primary" id="btnNext" onclick="goToReview()">Next: Review</button>
                <button type="submit" class="btn-primary" id="btnSubmit" style="display:none;">Submit Request</button>
            </div>
        </form>
    </aside>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="dashboard-canvas">
<main class="page">

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="page-head">
        <h1 class="page-title">Certifications</h1>
        <p class="page-sub">Request official certificates or have a third party verify your records.</p>
    </div>

    <div class="content-layout">

        <!-- MAIN -->
        <div class="main-content">

            <!-- Tabs -->
            <div class="tabs" role="tablist">
                <button type="button" class="tab-btn<?php echo $active_tab === 'certificates' ? ' active' : ''; ?>" id="tabBtn-certificates" onclick="showTab('certificates')">
                    Certificates <small>Enrollment, Grades, Good Moral</small>
                </button>
                <button type="button" class="tab-btn<?php echo $active_tab === 'verification' ? ' active' : ''; ?>" id="tabBtn-verification" onclick="showTab('verification')">
                    Verification <small>Third-party record verification</small>
                </button>
            </div>

            <!-- ======== TAB 1: CERTIFICATES ======== -->
            <div class="tab-pane<?php echo $active_tab === 'certificates' ? ' active' : ''; ?>" id="tab-certificates">

                <div class="card toolbar">
                    <form class="pup-search-form" style="grid-template-columns:1fr auto;" onsubmit="event.preventDefault(); filterCerts();">
                        <div class="search-field">
                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            <input type="text" id="certSearch" placeholder="Search certificate name or code..." onkeyup="filterCerts()">
                        </div>
                        <div class="pup-search-actions">
                            <button type="button" class="pup-clear-btn" onclick="clearCertFilters()">Clear</button>
                        </div>
                    </form>
                    <div class="chips" id="certChips">
                        <button type="button" class="chip active" data-cat="all" onclick="setCertCat('all')">All</button>
                        <?php foreach ($cert_cats as $ck => $cl): ?>
                            <button type="button" class="chip" data-cat="<?php echo $ck; ?>" onclick="setCertCat('<?php echo $ck; ?>')"><?php echo htmlspecialchars($cl); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cert-grid" id="certGrid">
                    <?php foreach ($cert_catalog as $c): ?>
                        <div class="card cert-card" data-cat="<?php echo $c['cat']; ?>"
                             data-name="<?php echo htmlspecialchars(strtolower($c['name'])); ?>"
                             data-code="<?php echo htmlspecialchars(strtolower($c['code'])); ?>">
                            <div class="cert-top">
                                <span class="cert-cat"><?php echo htmlspecialchars($cert_cats[$c['cat']]); ?></span>
                                <span class="record-code"><?php echo htmlspecialchars($c['code']); ?></span>
                            </div>
                            <div class="cert-name"><?php echo htmlspecialchars($c['name']); ?></div>
                            <div class="cert-desc"><?php echo htmlspecialchars($c['desc']); ?></div>
                            <div class="cert-foot">
                                <button type="button" class="record-btn"
                                        onclick="openRecordModal(
                                            '<?php echo htmlspecialchars($c['code'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($cert_cats[$c['cat']], ENT_QUOTES); ?>'
                                        )">Request</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="empty-state" id="certEmpty">No certificates match your search. Try a different keyword or clear the filters.</div>
            </div>

            <!-- ======== TAB 2: VERIFICATION ======== -->
            <div class="tab-pane<?php echo $active_tab === 'verification' ? ' active' : ''; ?>" id="tab-verification">
                <div class="verify-layout">

                    <form method="POST" action="" class="card verify-form" id="verifyForm">
                        <input type="hidden" name="action" value="request_verification">
                        <h3>Request third-party verification</h3>
                        <p class="lead">Let the Registrar confirm your records directly to an employer, school, or agency.</p>

                        <div class="form-group">
                            <label class="form-label" for="org_name">Organization / person to verify *</label>
                            <input type="text" class="form-input" id="org_name" name="org_name" required placeholder="e.g., ABC Corporation HR Department"
                                   value="<?php echo htmlspecialchars($_POST['org_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="org_email">Their email address *</label>
                            <input type="email" class="form-input" id="org_email" name="org_email" required placeholder="hr@company.com"
                                   value="<?php echo htmlspecialchars($_POST['org_email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="verify_target">Record to verify *</label>
                            <select class="form-input" id="verify_target" name="verify_target" required>
                                <option value="">— Select —</option>
                                <?php foreach ($verify_targets as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>"<?php echo (($_POST['verify_target'] ?? '') === $t) ? ' selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="purpose">Purpose *</label>
                            <textarea class="form-input" id="purpose" name="purpose" rows="3" required placeholder="e.g., Background check for employment"><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                        </div>
                        <label class="consent">
                            <input type="checkbox" name="consent" value="1" required>
                            <span>I authorize the University Registrar to release the selected information to the party named above.</span>
                        </label>
                        <button type="submit" class="btn-primary">Submit Verification Request</button>
                    </form>

                    <div class="card how-card">
                        <h3>How it works</h3>
                        <ol class="how-steps">
                            <li><div><strong>Submit your request</strong><span>Tell us who needs to verify your record and what they need confirmed.</span></div></li>
                            <li><div><strong>Registrar reviews</strong><span>Your request is checked and verified against your official records.</span></div></li>
                            <li><div><strong>Record is verified</strong><span>The verifying party is contacted directly. Track progress under Request History.</span></div></li>
                        </ol>
                        <div class="note-box">Only the information you select is released, and only to the party you name.</div>
                    </div>
                </div>
            </div>

            <!-- Request History -->
            <div class="section-header" id="requestHistory">
                <h2 class="section-title">Request History</h2>
            </div>
            <div class="card history-card">
                <?php if (empty($record_requests)): ?>
                    <div style="padding:2rem 1rem;text-align:center;">
                        <div style="font-size:0.88rem;font-weight:600;color:var(--text-2);margin-bottom:0.25rem;">No requests yet</div>
                        <div style="font-size:0.78rem;color:var(--text-3);">Request a certificate or a verification above.</div>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                    <table class="hist-table">
                        <thead>
                            <tr><th>Ticket</th><th>Document</th><th>Date</th><th>Status</th><th style="width:32px;"></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($record_requests as $req):
                            $rid = (int)$req['id'];
                            $stepIdx = status_step_index($req['status']);
                            $isRejected = strtoupper($req['status']) === 'REJECTED';
                            $docName = $code_names[$req['code']] ?? $req['code'];
                        ?>
                            <tr class="hist-row" id="row-<?php echo $rid; ?>" onclick="toggleTimeline(<?php echo $rid; ?>)">
                                <td>
                                    <?php if ($req['ticket_number']): ?>
                                        <span class="ticket"><?php echo htmlspecialchars($req['ticket_number']); ?></span>
                                    <?php else: ?>
                                        <span class="ticket none">No ticket yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="doc-cell">
                                        <span class="doc-name"><?php echo htmlspecialchars($docName); ?> <span class="record-code"><?php echo htmlspecialchars($req['code']); ?></span></span>
                                        <span class="doc-purpose" title="<?php echo htmlspecialchars($req['purpose'] ?? ''); ?>"><?php echo htmlspecialchars($req['purpose'] ?? 'Not specified'); ?></span>
                                    </div>
                                </td>
                                <td class="date-cell"><?php echo date('M d, Y · g:i A', strtotime($req['request_date'])); ?></td>
                                <td><span class="status-badge <?php echo htmlspecialchars(strtolower($req['status'])); ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
                                <td><svg class="row-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></td>
                            </tr>
                            <tr class="tl-row" id="tl-<?php echo $rid; ?>">
                                <td colspan="5">
                                    <?php if ($isRejected): ?>
                                        <div class="tl-rejected">This request was rejected. Please contact the Registrar for details.</div>
                                    <?php else: ?>
                                        <ol class="timeline">
                                            <?php foreach ($timeline_steps as $si => $label): ?>
                                                <li class="<?php echo $si <= $stepIdx ? 'done' : ''; ?><?php echo $si === $stepIdx ? ' current' : ''; ?>"><?php echo htmlspecialchars($label); ?></li>
                                            <?php endforeach; ?>
                                        </ol>
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

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="card sidebar-box">
                <div class="sidebar-title">Certifications</div>
                <ul class="sidebar-list">
                    <li><a href="#" onclick="showTab('certificates'); return false;">Certificates <span class="cat-count"><?php echo count($cert_catalog); ?></span></a></li>
                    <li><a href="#" onclick="showTab('verification'); return false;">Verification</a></li>
                    <li><a href="#requestHistory">Request History <span class="cat-count"><?php echo $count_total; ?></span></a></li>
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

<?php include '../../includes/footer.php'; ?>

<button class="fab" onclick="openOffcanvas()" aria-label="Open profile panel">
    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
</button>

<script>
const savedDarkMode = localStorage.getItem('darkMode');
if (savedDarkMode === 'true') { document.body.classList.add('dark-mode'); }

function openSideNav() {
    document.getElementById('sideNav').classList.add('open');
    document.getElementById('sideNavOverlay').classList.add('open');
    closeDropdown();
}
function closeSideNav() {
    document.getElementById('sideNav').classList.remove('open');
    document.getElementById('sideNavOverlay').classList.remove('open');
}
function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('open'); }
function closeDropdown() { document.getElementById('profileDropdown').classList.remove('open'); }
document.addEventListener('click', function(e) {
    if (!document.getElementById('profileWrap').contains(e.target)) closeDropdown();
});
function openOffcanvas() {
    document.getElementById('offcanvasPanel').classList.add('open');
    document.getElementById('offcanvasOverlay').classList.add('open');
    closeDropdown();
}
function closeOffcanvas() {
    document.getElementById('offcanvasPanel').classList.remove('open');
    document.getElementById('offcanvasOverlay').classList.remove('open');
}
function doSignOut() {
    if (confirm('Are you sure you want to sign out?')) { window.location.href = '../../includes/logout.php'; }
}

// ========== TABS ==========
function showTab(name) {
    ['certificates', 'verification'].forEach(function(t) {
        document.getElementById('tab-' + t).classList.toggle('active', t === name);
        document.getElementById('tabBtn-' + t).classList.toggle('active', t === name);
    });
    if (history.replaceState) history.replaceState(null, '', '#' + name);
}
(function() {
    var h = (location.hash || '').replace('#', '');
    if (h === 'verification' || h === 'certificates') {
        // a server-side result (success/error) keeps its own tab
        if (!document.querySelector('.alert')) showTab(h);
    }
})();

// ========== REQUEST DRAWER ==========
function setStep(n) {
    document.getElementById('stepPane1').classList.toggle('active', n === 1);
    document.getElementById('stepPane2').classList.toggle('active', n === 2);
    document.getElementById('stepInd1').className = 'step-ind ' + (n === 1 ? 'active' : 'done');
    document.getElementById('stepInd2').className = 'step-ind ' + (n === 2 ? 'active' : '');
    document.getElementById('stepInd3').className = 'step-ind';
    document.getElementById('btnNext').style.display = (n === 1) ? '' : 'none';
    document.getElementById('btnSubmit').style.display = (n === 2) ? '' : 'none';
    var back = document.getElementById('btnBack');
    back.textContent = (n === 1) ? 'Cancel' : 'Back';
    back.onclick = (n === 1) ? closeRequestModal : function() { setStep(1); };
}
function openRecordModal(code, name, category) {
    document.getElementById('academicRequestForm').reset();
    document.getElementById('detailsError').classList.remove('show');
    document.getElementById('modalRecordCode').value = code;
    document.getElementById('modalRecordName').value = name;
    document.getElementById('modalRecordCategory').value = category;
    document.getElementById('modalRecordDisplay').value = name + ' (' + code + ')';
    document.getElementById('modalRecordTitle').textContent = 'Request: ' + name;
    setStep(1);
    document.getElementById('requestModalBackdrop').classList.add('open');
}
function closeRequestModal() { document.getElementById('requestModalBackdrop').classList.remove('open'); }
function goToReview() {
    var details = document.getElementById('details').value.trim();
    var err = document.getElementById('detailsError');
    if (details.length < 5) { err.classList.add('show'); document.getElementById('details').focus(); return; }
    err.classList.remove('show');
    var rel = document.getElementById('release');
    document.getElementById('rvRecord').textContent = document.getElementById('modalRecordDisplay').value;
    document.getElementById('rvCategory').textContent = document.getElementById('modalRecordCategory').value;
    document.getElementById('rvPurpose').textContent = details;
    document.getElementById('rvCopies').textContent = document.getElementById('copies').value;
    document.getElementById('rvRelease').textContent = rel.options[rel.selectedIndex].text;
    document.getElementById('rvPriority').textContent = document.getElementById('priority').value;
    setStep(2);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeRequestModal(); closeOffcanvas(); }
});
document.getElementById('academicRequestForm').addEventListener('submit', function(e) {
    if (!document.getElementById('modalRecordCode').value) { e.preventDefault(); alert('Please select a certificate first.'); return; }
    if (document.getElementById('details').value.trim().length < 5) {
        e.preventDefault(); setStep(1); document.getElementById('detailsError').classList.add('show');
    }
});

// ========== SEARCH + FILTER (certificates) ==========
var activeCertCat = 'all';
function setCertCat(cat) {
    activeCertCat = cat;
    document.querySelectorAll('#certChips .chip').forEach(function(c) {
        c.classList.toggle('active', c.getAttribute('data-cat') === cat);
    });
    filterCerts();
}
function filterCerts() {
    var q = document.getElementById('certSearch').value.toLowerCase().trim();
    var any = false;
    document.querySelectorAll('.cert-card').forEach(function(card) {
        var okQ = q === '' || card.getAttribute('data-name').indexOf(q) !== -1 || card.getAttribute('data-code').indexOf(q) !== -1;
        var okC = activeCertCat === 'all' || card.getAttribute('data-cat') === activeCertCat;
        card.classList.toggle('is-hidden', !(okQ && okC));
        if (okQ && okC) any = true;
    });
    document.getElementById('certEmpty').classList.toggle('show', !any);
}
function clearCertFilters() {
    document.getElementById('certSearch').value = '';
    setCertCat('all');
}

// ========== HISTORY TIMELINE ==========
function toggleTimeline(id) {
    document.getElementById('row-' + id).classList.toggle('open');
    document.getElementById('tl-' + id).classList.toggle('open');
}
</script>
</body>
</html>