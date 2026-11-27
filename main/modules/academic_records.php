<?php
// ============================================
// ERROR REPORTING (FIX 3: huwag ipakita sa user, i-log na lang)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ============================================
// START SESSION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CHECK LOGIN
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === null) {
    // Walang user_id — redirect sa login
    // Kung may testing session ka pa, i-uncomment yung 2 lines sa ibaba para may temporary user
    header('Location: ../../login.php');
    exit;
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
// FETCH USER DATA FROM DB (dynamic)
// ============================================
$user_id         = $_SESSION['user_id'];
$user_fullname   = 'N/A';
$user_email      = 'N/A';
$user_role       = 'student';
$user_student_id = 'N/A';
$user_college    = 'N/A';
$user_course     = 'N/A';
$user_year_level = 'N/A';
$user_program    = 'N/A';
$user_grad_year  = null;
$user_mobile     = 'N/A';
$user_birthdate  = 'N/A';
$user_initials   = 'NA';

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, role, student_id, college, course, year_level, program, graduation_year, mobile, birthdate FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dbUser) {
            $user_fullname   = !empty($dbUser['full_name'])   ? $dbUser['full_name']   : 'N/A';
            $user_email      = !empty($dbUser['email'])       ? $dbUser['email']       : 'N/A';
            $user_role       = !empty($dbUser['role'])        ? $dbUser['role']        : 'student';
            $user_student_id = !empty($dbUser['student_id'])  ? $dbUser['student_id']  : 'N/A';
            $user_college    = !empty($dbUser['college'])     ? $dbUser['college']     : 'N/A';
            $user_course     = !empty($dbUser['course'])      ? $dbUser['course']      : 'N/A';
            $user_year_level = !empty($dbUser['year_level'])  ? $dbUser['year_level']  : 'N/A';
            $user_program    = !empty($dbUser['program'])     ? $dbUser['program']     : 'N/A';
            $user_grad_year  = $dbUser['graduation_year'];
            $user_mobile     = !empty($dbUser['mobile'])      ? $dbUser['mobile']      : 'N/A';
            $user_birthdate  = !empty($dbUser['birthdate'])   ? $dbUser['birthdate']   : 'N/A';

            // Compute initials from DB name
            $nameParts = preg_split('/\s+/', trim($user_fullname));
            if (count($nameParts) >= 2) {
                $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
            } else {
                $user_initials = strtoupper(substr($user_fullname, 0, 2));
            }
        }
    } catch (PDOException $e) {
        error_log("User fetch error: " . $e->getMessage());
    }
}

// ============================================
// DOCUMENT RULES
// ============================================
require_once __DIR__ . '/../../config/document_rules.php';

// ============================================
// ACADEMIC RECORDS — GROUPED BY MAJOR CATEGORY
// ============================================
$academic_records_catalog = [
    [
        'group'    => 'Student Information',
        'letter'   => 'A',
        'icon'     => 'user',
        'desc'     => 'Personal, academic, and status records',
        'sections' => [
            [
                'category' => 'Student Records',
                'items'    => [
                    ['name' => 'Student Permanent Record',           'code' => 'SPR'],
                    ['name' => 'Student Academic Profile',           'code' => 'SAP'],
                    ['name' => 'Student Information Record',         'code' => 'SIR'],
                    ['name' => 'Student Number / ID Record',         'code' => 'SID'],
                    ['name' => 'Program / Course Enrollment Record', 'code' => 'PCE'],
                    ['name' => 'Curriculum Record',                  'code' => 'CUR'],
                    ['name' => 'Academic History',                   'code' => 'AHX'],
                    ['name' => 'Previous School Records',            'code' => 'PSR'],
                    ['name' => 'Transfer Credentials',               'code' => 'TRC'],
                    ['name' => 'Student Status Record',              'code' => 'SSR'],
                    ['name' => 'Year Level Record',                  'code' => 'YLR'],
                    ['name' => 'Major / Minor Record',               'code' => 'MMR'],
                ],
            ],
            [
                'category' => 'Student Status Records',
                'items'    => [
                    ['name' => 'Regular Student',           'code' => 'REG'],
                    ['name' => 'Irregular Student',         'code' => 'IRR'],
                    ['name' => 'Probationary Status',       'code' => 'PRB'],
                    ['name' => 'Academic Warning',          'code' => 'AWG'],
                    ['name' => 'Suspended Academic Status', 'code' => 'SUS'],
                    ['name' => 'Leave of Absence',          'code' => 'LOA'],
                    ['name' => 'Withdrawn',                 'code' => 'WTH'],
                    ['name' => 'Dropped',                   'code' => 'DRP'],
                    ['name' => 'Transferred',               'code' => 'TRF'],
                    ['name' => 'Graduated',                 'code' => 'GRD'],
                    ['name' => 'Dismissed',                 'code' => 'DSM'],
                    ['name' => 'Returning Student',         'code' => 'RTN'],
                ],
            ],
        ],
    ],
    [
        'group'    => 'Academic Performance',
        'letter'   => 'B',
        'icon'     => 'chart',
        'desc'     => 'Grades, GWA, and academic standing',
        'sections' => [
            [
                'category' => 'Grades and Performance Records',
                'items'    => [
                    ['name' => 'Grades per Subject',           'code' => 'GPS'],
                    ['name' => 'Final Grades',                 'code' => 'FGR'],
                    ['name' => 'Midterm Grades',               'code' => 'MGR'],
                    ['name' => 'Semester Grades',              'code' => 'SGR'],
                    ['name' => 'Grade Change Records',         'code' => 'GCR'],
                    ['name' => 'Grade Completion Records',     'code' => 'GCM'],
                    ['name' => 'Incomplete (INC) Records',     'code' => 'INC'],
                    ['name' => 'Failed Subject Records',       'code' => 'FSR'],
                    ['name' => 'Re-examination Records',       'code' => 'REX'],
                    ['name' => 'Grade Sheets',                 'code' => 'GRS'],
                    ['name' => 'Class Records',                'code' => 'CLR'],
                    ['name' => 'General Weighted Average',     'code' => 'GWA'],
                    ['name' => 'Academic Standing',            'code' => 'AST'],
                    ['name' => "Dean's List / Honor Records",  'code' => 'DLH'],
                ],
            ],
        ],
    ],
    [
        'group'    => 'Enrollment',
        'letter'   => 'C',
        'icon'     => 'clipboard',
        'desc'     => 'Registration, schedules, and enrollment transactions',
        'sections' => [
            [
                'category' => 'Enrollment Records',
                'items'    => [
                    ['name' => 'Enrollment Registration Record',      'code' => 'ERR'],
                    ['name' => 'Certificate of Registration (COR)',   'code' => 'COR'],
                    ['name' => 'Enrollment Assessment',               'code' => 'EAS'],
                    ['name' => 'Subject Enrollment',                  'code' => 'SEN'],
                    ['name' => 'Add / Drop Records',                  'code' => 'ADR'],
                    ['name' => 'Change of Schedule Records',          'code' => 'CSR'],
                    ['name' => 'Cross-Enrollment Records',            'code' => 'CER'],
                    ['name' => 'Overload / Underload Records',        'code' => 'OUR'],
                    ['name' => 'Late Enrollment Records',             'code' => 'LER'],
                    ['name' => 'Enrollment Cancellation / Withdrawal','code' => 'ECW'],
                    ['name' => 'Leave of Absence Records',            'code' => 'LAR'],
                    ['name' => 'Return-to-School Records',            'code' => 'RTS'],
                ],
            ],
        ],
    ],
    [
        'group'    => 'Certifications & Graduation',
        'letter'   => 'D',
        'icon'     => 'award',
        'desc'     => 'Transcripts, certifications, and graduation records',
        'sections' => [
            [
                'category' => 'Transcript and Certification Records',
                'items'    => [
                    ['name' => 'Official Transcript of Records (TOR)',     'code' => 'TOR'],
                    ['name' => 'Certified True Copy of Grades',            'code' => 'CTC'],
                    ['name' => 'Certificate of Enrollment',                'code' => 'COE'],
                    ['name' => 'Certificate of Registration',              'code' => 'CRG'],
                    ['name' => 'Certificate of Graduation',                'code' => 'COG'],
                    ['name' => 'Certificate of Completion',                'code' => 'CCP'],
                    ['name' => 'Certificate of Units Earned',              'code' => 'CUE'],
                    ['name' => 'Certificate of Good Moral Character',      'code' => 'GMC'],
                    ['name' => 'Certificate of Academic Standing',         'code' => 'CAS'],
                    ['name' => 'Certificate of General Weighted Average',  'code' => 'CGW'],
                    ['name' => 'Certificate of No Record / No Enrollment', 'code' => 'CNR'],
                    ['name' => 'Authentication / Certification Requests',  'code' => 'AUT'],
                ],
            ],
            [
                'category' => 'Graduation Records',
                'items'    => [
                    ['name' => 'Graduation Application',              'code' => 'GAP'],
                    ['name' => 'Graduation Evaluation',               'code' => 'GEV'],
                    ['name' => 'Graduation Clearance',                'code' => 'GCL'],
                    ['name' => 'Candidate for Graduation List',       'code' => 'CGL'],
                    ['name' => 'Graduates List',                      'code' => 'GRL'],
                    ['name' => 'Graduation Requirements Checklist',   'code' => 'GRC'],
                    ['name' => 'Graduation Status',                   'code' => 'GST'],
                    ['name' => 'Degree Awarded',                      'code' => 'DEG'],
                    ['name' => 'Date of Graduation',                  'code' => 'DOG'],
                    ['name' => 'Latin Honors / Academic Honors',      'code' => 'LAH'],
                    ['name' => 'Diploma Record',                      'code' => 'DIP'],
                    ['name' => 'Diploma Release Record',              'code' => 'DRR'],
                    ['name' => 'Commencement Records',                'code' => 'CMR'],
                ],
            ],
        ],
    ],
    [
        'group'    => 'Transfer & Curriculum',
        'letter'   => 'E',
        'icon'     => 'transfer',
        'desc'     => 'Transfer, admission, subjects, and curriculum records',
        'sections' => [
            [
                'category' => 'Transfer and Admission-Related Records',
                'items'    => [
                    ['name' => 'Admission Credentials',                     'code' => 'ADC'],
                    ['name' => 'Previous Academic Records',                 'code' => 'PAR'],
                    ['name' => 'Transfer Student Records',                  'code' => 'TSR'],
                    ['name' => 'Transfer Evaluation',                       'code' => 'TEV'],
                    ['name' => 'Credited Subjects',                         'code' => 'CRS'],
                    ['name' => 'Validated Subjects',                        'code' => 'VAS'],
                    ['name' => 'Advanced Placement Records',                'code' => 'APR'],
                    ['name' => 'Transferee Evaluation',                     'code' => 'TFE'],
                    ['name' => 'Transfer Credential / Honorable Dismissal','code' => 'TCD'],
                    ['name' => 'Student Withdrawal Records',                'code' => 'SWR'],
                ],
            ],
            [
                'category' => 'Subject and Curriculum Records',
                'items'    => [
                    ['name' => 'Course Catalog',                  'code' => 'CAT'],
                    ['name' => 'Subject / Course Information',    'code' => 'SCI'],
                    ['name' => 'Course Code',                     'code' => 'CDC'],
                    ['name' => 'Course Description',              'code' => 'CDS'],
                    ['name' => 'Units / Credit Hours',            'code' => 'UCH'],
                    ['name' => 'Prerequisites',                   'code' => 'PRQ'],
                    ['name' => 'Curriculum Checklist',            'code' => 'CCH'],
                    ['name' => 'Curriculum Version',              'code' => 'CVR'],
                    ['name' => 'Academic Program Record',         'code' => 'APG'],
                    ['name' => 'Program / Course Offerings',      'code' => 'PCO'],
                    ['name' => 'Subject Equivalency Records',     'code' => 'SEQ'],
                ],
            ],
        ],
    ],
    [
        'group'    => 'Requests & Inquiries',
        'letter'   => 'F',
        'icon'     => 'message',
        'desc'     => 'Common registrar requests and concerns',
        'sections' => [
            [
                'category' => 'Academic Request Records',
                'items'    => [
                    ['name' => 'Transcript Request',              'code' => 'TRQ'],
                    ['name' => 'Certificate Request',             'code' => 'CRQ'],
                    ['name' => 'Grade Inquiry',                   'code' => 'GIQ'],
                    ['name' => 'Grade Correction Request',        'code' => 'GCQ'],
                    ['name' => 'Enrollment Verification Request', 'code' => 'EVQ'],
                    ['name' => 'Diploma Request',                 'code' => 'DPQ'],
                    ['name' => 'Authentication Request',          'code' => 'AUQ'],
                    ['name' => 'Academic Record Correction',      'code' => 'ARC'],
                    ['name' => 'Subject / Curriculum Inquiry',    'code' => 'SCQ'],
                    ['name' => 'Graduation Evaluation Request',   'code' => 'GEQ'],
                    ['name' => 'Transfer Credential Request',     'code' => 'TCQ'],
                    ['name' => 'Document Follow-up',              'code' => 'DFQ'],
                    ['name' => 'Registrar Complaint / Concern',   'code' => 'RCC'],
                    ['name' => 'General Registrar Inquiry',       'code' => 'GRI'],
                ],
            ],
        ],
    ],
];

// ============================================
// FLATTEN FOR VALIDATION
// ============================================
$all_records_flat = [];
foreach ($academic_records_catalog as $grp) {
    foreach ($grp['sections'] as $sec) {
        foreach ($sec['items'] as $item) {
            $all_records_flat[] = [
                'code'     => $item['code'],
                'name'     => $item['name'],
                'category' => $sec['category'],
                'group'    => $grp['group'],
            ];
        }
    }
}

// Lookup map (code => name/category)
$code_meta = [];
foreach ($all_records_flat as $rec) {
    if (!isset($code_meta[$rec['code']])) {
        $code_meta[$rec['code']] = ['name' => $rec['name'], 'category' => $rec['category']];
    }
}

// Most requested
$popular_records = [
    ['code' => 'TOR', 'desc' => 'For transfer, board exam, or abroad'],
    ['code' => 'COE', 'desc' => 'Proof that you are currently enrolled'],
    ['code' => 'GMC', 'desc' => 'For employment and scholarships'],
    ['code' => 'COR', 'desc' => 'Copy of your registration form'],
];

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
// HANDLE FORM SUBMISSION
// ============================================
// FIX 2: kunin ang flash message mula sa session (galing sa redirect pagkatapos mag-submit)
$success_message = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_academic_record') {
    
    $record_code  = trim($_POST['record_code'] ?? '');
    $record_name  = trim($_POST['record_name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $details      = trim($_POST['details'] ?? '');
    $priority     = trim($_POST['priority'] ?? 'Normal');
    $copies       = max(1, min(10, (int)($_POST['copies'] ?? 1)));
    $release      = in_array(($_POST['release'] ?? 'Pickup'), ['Pickup', 'Email'], true) ? $_POST['release'] : 'Pickup';

    if (!in_array($priority, ['Normal', 'Urgent'])) {
        $priority = 'Normal';
    }
    
    $valid_code = false;
    foreach ($all_records_flat as $rec) {
        if ($rec['code'] === $record_code) {
            $valid_code = true;
            $record_name = $rec['name'];
            $category = $rec['category'];
            break;
        }
    }
    
    if (!$valid_code) {
        $error_message = 'Invalid academic record selected.';
    } elseif (empty($details)) {
        $error_message = 'Please provide a purpose for your request.';
    } elseif (strlen($details) < 5) {
        $error_message = 'Purpose must be at least 5 characters.';
    } elseif (!$pdo) {
        $error_message = 'Database connection failed. Please try again later.';
    } else {
        try {
            $sql = "INSERT INTO requests (user_id, request_type, details, status, priority, created_at, updated_at)
                    VALUES (:user_id, :request_type, :details, 'PENDING', :priority, NOW(), NOW())
                    RETURNING id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'      => $user_id,
                ':request_type' => $record_code,
                ':details'      => "[$category] $record_name — " . $details . " (Copies: $copies · Release: $release)",
                ':priority'     => $priority
            ]);
            
            // FIX 2: Post/Redirect/Get — para hindi madoble ang request kapag nag-F5
            $_SESSION['flash_success'] = "Your request for \"$record_name\" has been submitted. Please wait for the admin to verify.";
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
            
        } catch (PDOException $e) {
            error_log("Academic Record Request Error: " . $e->getMessage());
            $error_message = 'Database error: Unable to save your request.';
        }
    }
}

// ============================================
// FETCH USER'S ACADEMIC RECORD REQUESTS
// ============================================
$record_requests = [];
$count_total = 0;
$count_pending = 0;
$count_completed = 0;

$all_codes = [];
foreach ($all_records_flat as $rec) {
    $all_codes[] = $rec['code'];
}
$all_codes = array_unique($all_codes);

if ($pdo && !empty($all_codes)) {
    try {
        $placeholders = implode(',', array_fill(0, count($all_codes), '?'));
        $sql = "SELECT id, request_type, details, status, priority, ticket_number, created_at
                FROM requests
                WHERE user_id = ? AND request_type IN ($placeholders)
                ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$user_id], $all_codes));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $record_requests[] = [
                'id'            => $row['id'],
                'ticket_number' => $row['ticket_number'],
                'code'          => $row['request_type'],
                'purpose'       => $row['details'],
                'status'        => $row['status'],
                'request_date'  => $row['created_at'],
                'priority'      => $row['priority'],
            ];
            
            $count_total++;
            if (strtoupper($row['status']) === 'PENDING') $count_pending++;
            if (in_array(strtoupper($row['status']), ['COMPLETED', 'CLAIMED', 'READY'])) $count_completed++;
        }
    } catch (PDOException $e) {
        error_log("Academic Records Fetch Error: " . $e->getMessage());
    }
}

// Sync session with DB values (para consistent ang header/side_nav)
$_SESSION['full_name'] = $user_fullname;
$_SESSION['email']     = $user_email;
$_SESSION['role']      = $user_role;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Academic Records</title>

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

        .page { max-width: 1280px; margin: 0 auto; padding: 1.75rem 1.5rem 3rem; }
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

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--shadow-sm); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); margin-bottom: 1.1rem; }
        .stat-box { padding: 0.8rem 1.25rem; display: flex; align-items: baseline; gap: 10px; border-right: 1px solid var(--border); }
        .stat-box:last-child { border-right: none; }
        .stat-box .stat-value { font-size: 1.35rem; font-weight: 700; color: var(--accent-text); line-height: 1; }
        .stat-box .stat-label { font-size: 0.76rem; color: var(--text-3); }

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

        .block-title { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--text-3); margin-bottom: 0.6rem; }
        .popular-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
        .popular-card { padding: 0.9rem 1rem; text-align: left; cursor: pointer; border-top: 3px solid var(--secondary); transition: transform 0.15s, box-shadow 0.15s; display: flex; flex-direction: column; gap: 4px; color: inherit; }
        .popular-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16,24,40,0.10); }
        .popular-code { font-size: 0.66rem; font-weight: 700; letter-spacing: 0.06em; color: var(--accent-text); }
        .popular-name { font-size: 0.85rem; font-weight: 600; color: var(--text-1); line-height: 1.3; }
        .popular-desc { font-size: 0.74rem; color: var(--text-3); line-height: 1.35; }

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

        .section-header { display: flex; align-items: center; justify-content: space-between; margin: 2rem 0 0.75rem; flex-wrap: wrap; gap: 0.75rem; }
        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 18px; background: var(--secondary); border-radius: 2px; }

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

        .status-badge { display: inline-block; padding: 3px 12px; font-weight: 600; font-size: 0.72rem; border-radius: 999px; white-space: nowrap; background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-badge.pending { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-badge.processing, .status-badge.accepted, .status-badge.verified { background: var(--st-info-bg); color: var(--st-info-fg); }
        .status-badge.completed, .status-badge.claimed, .status-badge.ready { background: var(--st-ok-bg); color: var(--st-ok-fg); }
        .status-badge.rejected { background: var(--st-bad-bg); color: var(--st-bad-fg); }

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

        .alert { padding: 12px 16px; margin-bottom: 1rem; font-size: 0.85rem; border: 1px solid; border-radius: 8px; }
        .alert-success { background: var(--st-ok-bg); border-color: var(--st-ok-fg); color: var(--st-ok-fg); }
        .alert-error { background: var(--st-bad-bg); border-color: var(--st-bad-fg); color: var(--st-bad-fg); }

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

        .fab { position: fixed; bottom: 28px; right: 28px; width: 52px; height: 52px; background: var(--primary); border: 2px solid var(--secondary); box-shadow: 0 6px 18px rgba(0,51,102,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 150; border-radius: 12px; }
        .fab:hover { transform: scale(1.05); background: var(--primary-light); }
        .fab svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }

        .page-head { padding: 0.75rem 0 0.5rem; margin-bottom: 1.5rem; }
        .page-title { font-family: 'Inter', system-ui, sans-serif; font-size: 2rem; font-weight: 700; color: var(--accent-text); letter-spacing: -0.015em; }
        .page-sub { color: var(--text-2); }
        body.dark-mode .page-title { color: var(--text-1); }

        .sidebar-box.card { background: transparent; border: none; box-shadow: none; padding: 0; }
        .sidebar-title { font-size: 0.82rem; font-weight: 500; letter-spacing: 0.04em; color: var(--accent-text); border-bottom: none; padding-bottom: 0.7rem; margin-bottom: 0.6rem; position: relative; }
        .sidebar-title::after { content: ''; position: absolute; left: 0; bottom: 0; width: 48px; height: 2px; background: var(--secondary); }
        body.dark-mode .sidebar-title { color: var(--text-1); }
        .sidebar { gap: 1.75rem; }

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
    </style>
    <link rel="stylesheet" href="../../includes/side_nav.css">
</head>
<body>

<?php include '../../includes/side_nav.php'; ?>
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
            <div class="profile-avatar-large"><?php echo htmlspecialchars($user_initials); ?></div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                <div class="profile-role"><?php echo ucfirst(htmlspecialchars($user_role)); ?></div>
            </div>
        </div>
        <div class="offcanvas-section">
            <div class="offcanvas-section-title">
                <?php echo ($user_role === 'alumni') ? 'Alumni Information' : 'Student Information'; ?>
            </div>
            <div class="profile-info-grid">
                <span class="info-label"><?php echo ($user_role === 'alumni') ? 'Alumni ID' : 'Student ID'; ?></span>
                <span class="info-value"><?php echo htmlspecialchars($user_student_id); ?></span>

                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($user_fullname); ?></span>

                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>

                <span class="info-label">College</span>
                <span class="info-value"><?php echo htmlspecialchars($user_college); ?></span>

                <span class="info-label">Course</span>
                <span class="info-value"><?php echo htmlspecialchars($user_course); ?></span>

                <?php if ($user_role === 'alumni'): ?>
                    <span class="info-label">Graduated</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_grad_year ?: 'N/A'); ?></span>
                <?php else: ?>
                    <span class="info-label">Year Level</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_year_level); ?></span>
                <?php endif; ?>

                <span class="info-label">Mobile</span>
                <span class="info-value"><?php echo htmlspecialchars($user_mobile); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ========== REQUEST DRAWER ========== -->
<div class="modal-backdrop" id="requestModalBackdrop" onclick="if(event.target===this) closeRequestModal()">
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="modalRecordTitle">
        <div class="drawer-head">
            <span class="modal-title" id="modalRecordTitle">Request Academic Record</span>
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
            <input type="hidden" name="action" value="request_academic_record">
            <input type="hidden" name="record_code" id="modalRecordCode" value="">
            <input type="hidden" name="record_name" id="modalRecordName" value="">
            <input type="hidden" name="category" id="modalRecordCategory" value="">

            <div class="drawer-body">
                <div class="step-pane active" id="stepPane1">
                    <div class="form-group">
                        <label class="form-label">Record Type</label>
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

                <div class="step-pane" id="stepPane2">
                    <dl class="review-list">
                        <dt>Record</dt>   <dd id="rvRecord"></dd>
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
        <h1 class="page-title">Academic Records</h1>
        <p class="page-sub">Request registrar documents and records. Pick a record below to get started.</p>
    </div>

    <div class="content-layout">
        
        <div class="main-content">

            <div class="card toolbar">
                <form class="pup-search-form" onsubmit="event.preventDefault(); filterRecords();">
                    <div class="search-field">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                        <input type="text" id="recordSearch" placeholder="Search by record name or code..." onkeyup="filterRecords()">
                    </div>
                    <select id="categoryFilter" onchange="filterRecords()">
                        <option value="all">All categories</option>
                        <?php foreach ($academic_records_catalog as $grp): ?>
                            <optgroup label="<?php echo htmlspecialchars($grp['group']); ?>">
                                <?php foreach ($grp['sections'] as $sec): ?>
                                    <option value="<?php echo htmlspecialchars(strtolower($sec['category']), ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($sec['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div class="pup-search-actions">
                        <button type="submit" class="pup-search-btn">Search</button>
                        <button type="button" class="pup-clear-btn" onclick="clearFilters()">Clear</button>
                    </div>
                </form>
                <div class="chips" id="groupChips">
                    <button type="button" class="chip active" data-group="all" onclick="setGroupFilter('all')">All</button>
                    <?php foreach ($academic_records_catalog as $grp): ?>
                        <button type="button" class="chip" data-group="<?php echo htmlspecialchars(strtolower($grp['group']), ENT_QUOTES); ?>"
                                onclick="setGroupFilter(this.getAttribute('data-group'))">
                            <?php echo htmlspecialchars($grp['group']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="popularBlock">
                <div class="block-title">Most requested</div>
                <div class="popular-grid">
                    <?php foreach ($popular_records as $pop):
                        if (!isset($code_meta[$pop['code']])) continue;
                        $pm = $code_meta[$pop['code']]; ?>
                        <?php /* FIX 1: json_encode para hindi masira ng apostrophe (hal. Dean's List) ang onclick */ ?>
                        <button type="button" class="card popular-card"
                                onclick="openRecordModal(<?php echo htmlspecialchars(json_encode($pop['code']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($pm['name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($pm['category']), ENT_QUOTES); ?>)">
                            <span class="popular-code"><?php echo htmlspecialchars($pop['code']); ?></span>
                            <span class="popular-name"><?php echo htmlspecialchars($pm['name']); ?></span>
                            <span class="popular-desc"><?php echo htmlspecialchars($pop['desc']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="block-title">All records</div>
            <div id="groupsContainer">
                <?php foreach ($academic_records_catalog as $gi => $grp): 
                    $groupTotal = 0;
                    foreach ($grp['sections'] as $sec) $groupTotal += count($sec['items']);
                    $groupKey = htmlspecialchars(strtolower($grp['group']), ENT_QUOTES);
                ?>
                    <div class="card category-group<?php echo $gi === 0 ? '' : ' collapsed'; ?>" data-group="<?php echo $groupKey; ?>">
                        
                        <button type="button" class="group-header" onclick="toggleGroup(this.parentNode)">
                            <div class="group-letter"><?php echo $grp['letter']; ?></div>
                            <div class="group-info">
                                <div class="group-name"><?php echo htmlspecialchars($grp['group']); ?></div>
                                <div class="group-desc"><?php echo htmlspecialchars($grp['desc']); ?></div>
                            </div>
                            <div class="group-count"><?php echo $groupTotal; ?> records</div>
                            <svg class="group-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="group-body">
                            <?php foreach ($grp['sections'] as $sec): ?>
                                <div class="subsection" data-category="<?php echo htmlspecialchars(strtolower($sec['category']), ENT_QUOTES); ?>">
                                    <div class="subsection-title">
                                        <span><?php echo htmlspecialchars($sec['category']); ?></span>
                                        <span class="count"><?php echo count($sec['items']); ?></span>
                                    </div>
                                    <ul class="record-list">
                                        <?php foreach ($sec['items'] as $item): ?>
                                            <li class="record-item"
                                                data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>"
                                                data-code="<?php echo htmlspecialchars(strtolower($item['code'])); ?>"
                                                data-group="<?php echo $groupKey; ?>"
                                                data-category="<?php echo htmlspecialchars(strtolower($sec['category'])); ?>">
                                                <span class="record-name">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                    <span class="record-code"><?php echo htmlspecialchars($item['code']); ?></span>
                                                </span>
                                                <?php /* FIX 1: json_encode din dito */ ?>
                                                <button type="button" class="record-btn"
                                                        onclick="openRecordModal(<?php echo htmlspecialchars(json_encode($item['code']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($item['name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($sec['category']), ENT_QUOTES); ?>)">
                                                    Request
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="empty-state" id="emptyState">
                No records match your search. Try a different keyword or clear the filters.
            </div>

            <div class="section-header" id="requestHistory">
                <h2 class="section-title">Request History</h2>
            </div>
            <div class="card history-card">
                <?php if (empty($record_requests)): ?>
                    <div style="padding:2rem 1rem;text-align:center;">
                        <div style="font-size:0.88rem;font-weight:600;color:var(--text-2);margin-bottom:0.25rem;">No requests yet</div>
                        <div style="font-size:0.78rem;color:var(--text-3);">Click any record above to submit a request.</div>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                    <table class="hist-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Document</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="width:32px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($record_requests as $req):
                            $rid = (int)$req['id'];
                            $stepIdx = status_step_index($req['status']);
                            $isRejected = strtoupper($req['status']) === 'REJECTED';
                            $docName = $code_meta[$req['code']]['name'] ?? $req['code'];
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

        <aside class="sidebar">
            <div class="card sidebar-box">
                <div class="sidebar-title">Categories</div>
                <ul class="sidebar-list">
                    <?php foreach ($academic_records_catalog as $grp): ?>
                        <li>
                            <a href="#group-<?php echo htmlspecialchars(strtolower($grp['group']), ENT_QUOTES); ?>"
                               onclick="jumpToGroup('<?php echo htmlspecialchars(strtolower($grp['group']), ENT_QUOTES); ?>'); return false;">
                                <?php echo htmlspecialchars($grp['group']); ?>
                                <span class="cat-count">
                                    <?php 
                                        $ct = 0;
                                        foreach ($grp['sections'] as $sec) $ct += count($sec['items']);
                                        echo $ct;
                                    ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
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
                    <li><a href="requests.php">My Requests</a></li>
                    <li><a href="certifications.php">Certifications</a></li>
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
if (savedDarkMode === 'true') {
    document.body.classList.add('dark-mode');
}

function openSideNav() {
    document.getElementById('sideNav').classList.add('open');
    document.getElementById('sideNavOverlay').classList.add('open');
    closeDropdown();
}
function closeSideNav() {
    document.getElementById('sideNav').classList.remove('open');
    document.getElementById('sideNavOverlay').classList.remove('open');
}

function toggleDropdown() {
    document.getElementById('profileDropdown').classList.toggle('open');
}
function closeDropdown() {
    document.getElementById('profileDropdown').classList.remove('open');
}
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
function closeRequestModal() {
    document.getElementById('requestModalBackdrop').classList.remove('open');
}

function goToReview() {
    var details = document.getElementById('details').value.trim();
    var err = document.getElementById('detailsError');
    if (details.length < 5) {
        err.classList.add('show');
        document.getElementById('details').focus();
        return;
    }
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

function toggleGroup(el) {
    el.classList.toggle('collapsed');
}

var activeGroup = 'all';

function setGroupFilter(group) {
    activeGroup = group;
    document.querySelectorAll('#groupChips .chip').forEach(function(c) {
        c.classList.toggle('active', c.getAttribute('data-group') === group);
    });
    filterRecords();
}

function filterRecords() {
    var query = document.getElementById('recordSearch').value.toLowerCase().trim();
    var category = document.getElementById('categoryFilter').value;
    var items = document.querySelectorAll('.record-item');
    var anyVisible = false;
    var filtering = (query !== '' || category !== 'all' || activeGroup !== 'all');
    
    items.forEach(function(item) {
        var name = item.getAttribute('data-name') || '';
        var code = (item.getAttribute('data-code') || '').toLowerCase();
        var itemCat = (item.getAttribute('data-category') || '').toLowerCase();
        var itemGroup = (item.getAttribute('data-group') || '').toLowerCase();
        
        var matchQuery = (query === '') || (name.indexOf(query) !== -1 || code.indexOf(query) !== -1);
        var matchCat = (category === 'all') || (itemCat === category);
        var matchGroup = (activeGroup === 'all') || (itemGroup === activeGroup);
        
        var visible = matchQuery && matchCat && matchGroup;
        item.classList.toggle('is-hidden', !visible);
        if (visible) anyVisible = true;
    });
    
    document.querySelectorAll('.subsection').forEach(function(sub) {
        var vis = sub.querySelectorAll('.record-item:not(.is-hidden)').length;
        sub.style.display = vis > 0 ? '' : 'none';
    });
    
    document.querySelectorAll('.category-group').forEach(function(grp) {
        var vis = grp.querySelectorAll('.record-item:not(.is-hidden)').length;
        grp.style.display = vis > 0 ? '' : 'none';
        grp.classList.toggle('force-open', filtering && vis > 0);
    });

    document.getElementById('popularBlock').style.display = filtering ? 'none' : '';
    document.getElementById('emptyState').classList.toggle('show', !anyVisible);
}

function clearFilters() {
    document.getElementById('recordSearch').value = '';
    document.getElementById('categoryFilter').value = 'all';
    setGroupFilter('all');
}

function jumpToGroup(group) {
    document.getElementById('recordSearch').value = '';
    document.getElementById('categoryFilter').value = 'all';
    setGroupFilter('all');
    
    var el = document.querySelector('.category-group[data-group="' + group + '"]');
    if (el) {
        el.classList.remove('collapsed');
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function toggleTimeline(id) {
    document.getElementById('row-' + id).classList.toggle('open');
    document.getElementById('tl-' + id).classList.toggle('open');
}

document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('academicRequestForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        var code = document.getElementById('modalRecordCode').value;
        var details = document.getElementById('details').value.trim();
        
        if (!code) {
            e.preventDefault();
            alert('Please select an academic record first.');
            return false;
        }
        
        if (details.length < 5) {
            e.preventDefault();
            setStep(1);
            document.getElementById('detailsError').classList.add('show');
            return false;
        }
    });
});

function doSignOut() {
    if (confirm('Are you sure you want to sign out?')) {
        window.location.href = '../../includes/logout.php';
    }
}
</script>
</body>
</html>