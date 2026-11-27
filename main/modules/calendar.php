<?php
// ============================================
// ENABLE ERROR REPORTING FOR DEBUGGING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// GET ACADEMIC EVENTS FROM DATABASE
// ============================================
$events = [];

try {
    if (isset($pdo) && $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM academic_events ORDER BY event_date ASC LIMIT 20");
        $stmt->execute();
        $events = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching academic events: " . $e->getMessage());
}

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
                $user_mobile = $userData['mobile'] ?? '';
                $user_birthdate = $userData['birthdate'] ?? '';
                $user_role = $userData['role'] ?? 'student';
                
                $nameParts = explode(' ', $user_fullname);
                if (count($nameParts) >= 2) {
                    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } else {
                    $user_initials = strtoupper(substr($user_fullname, 0, 2));
                }
            }
            
            $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = $1");
            $stmt->execute([$user_id]);
            $studentData = $stmt->fetch();
            
            if ($studentData) {
                $user_student_id = $studentData['student_id'] ?? $user_student_id;
                $user_college = $studentData['college'] ?? $user_college;
                $user_course = $studentData['course'] ?? $user_course;
                $user_year_level = $studentData['year_level'] . ' Year' ?? $user_year_level;
            }
        }
    } catch (PDOException $e) {
        error_log("PostgreSQL Error in Calendar: " . $e->getMessage());
    }
}

// ============================================
// GET ACADEMIC EVENTS FROM DATABASE (after $pdo is set)
// ============================================
$events = [];

if ($dbConnected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM academic_events ORDER BY event_date ASC LIMIT 20");
        $stmt->execute();
        $events = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching academic events: " . $e->getMessage());
    }
}

// ============================================
// UNIVERSITY CALENDAR A.Y. 2026-2027 (BOR Resolution No. 2026-010, July 2, 2026)
// Used ONLY when the academic_events table returns no rows.
// Format: [start date, end date, event name]
// ============================================
$umak_calendar_2026_2027 = [
    // ---- JULY 2026 ----
    ['2026-07-06', '2026-07-10', "Freshmen Registration Period, First Semester AY 2026-2027"],
    ['2026-07-06', '2026-07-06', "Deadline for Filing of Application for Psychometrician Licensure Examination"],
    ['2026-07-09', '2026-07-09', "Linkages and Placement Workshop (First Semester, 2026-2027)"],
    ['2026-07-13', '2026-07-17', "Registration Period, First Semester AY 2026-2027 (Old and Continuing Students/Graduate Programs)"],
    ['2026-07-15', '2026-07-15', "Freshmen and Transferees' Orientation"],
    ['2026-07-15', '2026-07-15', "Deadline for Filing of Application for Nurse Licensure Examination"],
    ['2026-07-16', '2026-07-16', "Internship Orientation, First Semester"],
    ['2026-07-20', '2026-07-21', "Encoding of SUMMER TERM 2026 Final Grades"],
    ['2026-07-22', '2026-07-22', "Releasing of SUMMER TERM 2026 Report of Grades"],
    ['2026-07-22', '2026-07-23', "Registration Period, First Semester: SUMMER TERM 2026 Enrollees, Irregular Students, ITEST, Transferees and Graduate Programs"],
    ['2026-07-23', '2026-07-23', "Incoming Grade 11 Students' Orientation"],
    ['2026-07-23', '2026-07-23', "Deadline for Filing of Application for Professional Teachers Examination"],
    ['2026-07-24', '2026-07-24', "Last Day of Admission and Enrollment for Graduate Programs, First Semester"],
    ['2026-07-27', '2026-07-27', "First Day of Classes for the First Semester"],
    ['2026-07-27', '2026-07-27', "HSU Start of Classes/Orientation"],
    ['2026-07-27', '2026-07-27', "Internship Fair"],
    ['2026-07-27', '2026-07-29', "Change of Matriculation Period (CMAT)"],
    ['2026-07-30', '2026-07-31', "IPE/Allied Health Sciences Interprofessional Education Cluster (AHSIPEC) Research Conference"],
    // ---- AUGUST 2026 ----
    ['2026-08-03', '2026-08-03', "HSU Start of Term 1 Instruction"],
    ['2026-08-05', '2026-08-05', "Graduation Ceremonies - College"],
    ['2026-08-06', '2026-08-06', "Deadline for Filing of Application for Registered Electrical Engineers and Registered Master Electrician Licensure Examination"],
    ['2026-08-10', '2026-08-14', "Filing of Application for Graduation for First Semester at the Deans' Offices"],
    ['2026-08-12', '2026-08-12', "Deadline for Filing of Application for Civil Engineers Licensure Examination"],
    ['2026-08-17', '2026-08-20', "Submission of the Lists of Applicants for Graduation for the First Semester to the Office of the University Registrar"],
    ['2026-08-19', '2026-08-19', "University Council Meeting"],
    ['2026-08-19', '2026-08-20', "Psychometrician Licensure Examination"],
    ['2026-08-21', '2026-08-21', "Ninoy Aquino Day (Special Non-Working Holiday)"],
    ['2026-08-29', '2026-08-30', "Nurse Licensure Examination"],
    ['2026-08-31', '2026-08-31', "National Heroes Day (Regular Holiday)"],
    // ---- SEPTEMBER 2026 ----
    ['2026-09-02', '2026-09-02', "Career Forum, First Semester"],
    ['2026-09-05', '2026-09-06', "Electrical Engineering Licensure Examination"],
    ['2026-09-07', '2026-09-07', "Registered Master Electrician Licensure Examination"],
    ['2026-09-09', '2026-09-09', "Deadline for Filing of Application for Certified Public Accountant Licensure Examination"],
    ['2026-09-20', '2026-09-20', "Professional Teachers Examination"],
    ['2026-09-21', '2026-09-26', "Midterm Examination Period"],
    ['2026-09-26', '2026-09-27', "Civil Engineers Licensure Examination"],
    ['2026-09-29', '2026-09-29', "Deadline for Filing of Application for Pharmacist Licensure Examination"],
    // ---- OCTOBER 2026 ----
    ['2026-10-01', '2026-10-30', "College of Liberal Arts and Social Sciences Month"],
    ['2026-10-02', '2026-10-02', "Last Day of Dropping of Course(s)"],
    ['2026-10-05', '2026-10-10', "Submission of Midterm Grades (College and HSU) to the Dean's Offices"],
    ['2026-10-05', '2026-10-30', "Faculty Evaluation Period by Students, First Semester AY 2026-2027"],
    ['2026-10-05', '2026-10-05', "World Teacher's Day Celebration"],
    ['2026-10-05', '2026-10-09', "Psychology Week and Mental Health Week"],
    ['2026-10-06', '2026-10-06', "HSU End of Instructional Block"],
    ['2026-10-07', '2026-10-09', "HSU Term 1 Examination Period"],
    ['2026-10-12', '2026-10-12', "HSU End-of-Term Processing/Remediation Start"],
    ['2026-10-16', '2026-10-16', "HSU End of Term 1"],
    ['2026-10-19', '2026-10-19', "HSU Start of Term 2 Instruction"],
    ['2026-10-19', '2026-10-23', "Survey of Courses to be Offered for the Second Semester"],
    ['2026-10-24', '2026-10-26', "Pharmacist Licensure Examination"],
    ['2026-10-31', '2026-10-31', "All Saints' Day Eve (Special Non-Working Holiday)"],
    // ---- NOVEMBER 2026 ----
    ['2026-11-01', '2026-11-01', "All Saints' Day (Regular Holiday)"],
    ['2026-11-02', '2026-11-02', "All Souls' Day (Special Non-Working Holiday)"],
    ['2026-11-10', '2026-11-10', "Deadline for Filing of Application for Radiologic Technology Licensure Examination"],
    ['2026-11-13', '2026-11-13', "Last Day of Application for Leave of Absence (LOA) for the First Semester AY 2026-2027"],
    ['2026-11-23', '2026-11-28', "Final Examination Period"],
    ['2026-11-29', '2026-11-29', "Last Day of the First Semester AY 2026-2027"],
    ['2026-11-30', '2026-11-30', "Bonifacio Day (Regular Holiday)"],
    // ---- DECEMBER 2026 ----
    ['2026-12-01', '2027-01-08', "Semestral Break and Holiday Break"],
    ['2026-12-03', '2026-12-06', "Encoding of Final Grades"],
    ['2026-12-07', '2026-12-11', "Releasing of Report of Grades"],
    ['2026-12-08', '2026-12-08', "Feast of the Immaculate Conception of Mary"],
    ['2026-12-10', '2026-12-11', "Radiologic Technology Licensure Examination"],
    ['2026-12-14', '2026-12-18', "Registration Period, Second Semester"],
    ['2026-12-14', '2026-12-18', "ITEST Registration Period, Second Semester"],
    ['2026-12-15', '2026-12-15', "Linkages and Placement (Second Semester 2026-2027)"],
    ['2026-12-16', '2026-12-16', "Internship Orientation, Second Semester"],
    ['2026-12-23', '2026-12-23', "Submission of Final Grades to the Dean's Offices"],
    ['2026-12-24', '2026-12-24', "Christmas Eve (Special Non-Working Holiday)"],
    ['2026-12-25', '2026-12-25', "Christmas Day (Regular Holiday)"],
    ['2026-12-30', '2026-12-30', "Rizal Day (Regular Holiday)"],
    ['2026-12-31', '2026-12-31', "Last Day of the Year"],
    // ---- JANUARY 2027 ----
    ['2027-01-01', '2027-01-01', "New Year's Day (Regular Holiday)"],
    ['2027-01-04', '2027-01-04', "First Day of Classes for the Second Semester"],
    ['2027-01-04', '2027-01-04', "Resume of Term 2 Classes for HSU"],
    ['2027-01-04', '2027-01-04', "Start of Online Application for Incoming Grade 11 Students (AY 2027-2028)"],
    ['2027-01-04', '2027-01-04', "Internship Fair for Second Semester"],
    ['2027-01-04', '2027-01-06', "Change of Matriculation Period (CMAT) Regular Program"],
    ['2027-01-08', '2027-01-08', "University Council Meeting"],
    ['2027-01-18', '2027-01-22', "Filing of Application for Graduation for Second Semester at the Deans' Offices"],
    ['2027-01-25', '2027-01-29', "Submission of the Lists of Applicants for Graduation for Second Semester to the Office of the University Registrar"],
    // ---- FEBRUARY 2027 ----
    ['2027-02-01', '2027-02-28', "College of Engineering and Technology Month"],
    ['2027-02-02', '2027-02-02', "HSU End of Instructional Block"],
    ['2027-02-03', '2027-02-05', "HSU Term 2 Examination Period"],
    ['2027-02-06', '2027-02-06', "Chinese New Year"],
    ['2027-02-08', '2027-02-08', "HSU End-of-Term Processing/Start of Remediation"],
    ['2027-02-12', '2027-02-12', "HSU End of Term 2"],
    ['2027-02-15', '2027-02-15', "HSU Start of Term 3 Instruction"],
    ['2027-02-25', '2027-02-25', "EDSA People Power Revolution Anniversary"],
    ['2027-02-25', '2027-02-25', "55th Foundation Celebration"],
    // ---- MARCH 2027 ----
    ['2027-03-01', '2027-03-06', "Midterm Examination Period"],
    ['2027-03-09', '2027-03-09', "Eid-ul-Fitr (tentative)"],
    ['2027-03-15', '2027-03-15', "Start of Online Application for Transferees and Second Coursers"],
    ['2027-03-15', '2027-03-19', "Survey of Courses to be Offered for the First Semester"],
    ['2027-03-19', '2027-03-19', "Last Day of Dropping of Course(s)"],
    ['2027-03-19', '2027-03-19', "Submission of Midterm Grades to the Deans' Offices"],
    ['2027-03-22', '2027-03-28', "Paschal Break"],
    ['2027-03-25', '2027-03-25', "Maundy Thursday"],
    ['2027-03-26', '2027-03-26', "Good Friday"],
    ['2027-03-27', '2027-03-27', "Black Saturday"],
    ['2027-03-28', '2027-03-28', "Easter Sunday"],
    ['2027-03-31', '2027-03-31', "University Career Forum"],
    // ---- APRIL 2027 ----
    ['2027-04-06', '2027-04-30', "Faculty Evaluation Period by Students, Second Semester"],
    ['2027-04-09', '2027-04-09', "Araw ng Kagitingan (Regular Holiday)"],
    ['2027-04-17', '2027-04-18', "College Entrance Examination Schedule"],
    ['2027-04-24', '2027-04-25', "College Entrance Examination Schedule"],
    ['2027-04-26', '2027-04-30', "HSU PBL Week"],
    ['2027-04-30', '2027-04-30', "Last Day for Application for Leave of Absence (LOA)"],
    ['2027-04-30', '2027-04-30', "University Mega Job Fair"],
    // ---- MAY 2027 ----
    ['2027-05-01', '2027-05-01', "Labor Day (Regular Holiday)"],
    ['2027-05-03', '2027-05-08', "Final Examination Period for Graduating Students"],
    ['2027-05-05', '2027-05-07', "HSU Term 3 Examination Period"],
    ['2027-05-07', '2027-05-07', "Encoding of Final Grades for Graduating Students (College)"],
    ['2027-05-10', '2027-05-10', "HSU End-of-Term Processing/Completion"],
    ['2027-05-10', '2027-05-15', "Final Examination Period for Non-Graduating Students"],
    ['2027-05-13', '2027-05-14', "Releasing of Report of Grades (Graduating - College)"],
    ['2027-05-14', '2027-05-14', "HSU End of Term 3"],
    ['2027-05-14', '2027-05-14', "HSU Report Card Distribution"],
    ['2027-05-16', '2027-05-16', "Last Day of the Second Semester AY 2026-2027"],
    ['2027-05-17', '2027-05-17', "Eid al-Adha (tentative)"],
    ['2027-05-19', '2027-05-21', "Encoding of Final Grades for Non-Graduating Students"],
    ['2027-05-22', '2027-05-23', "Releasing of Report of Grades for Non-Graduating Students"],
    ['2027-05-24', '2027-05-26', "Registration for SUMMER TERM 2027"],
    ['2027-05-31', '2027-05-31', "First Day of Classes, SUMMER TERM 2027"],
    ['2027-05-31', '2027-07-09', "SUMMER TERM 2027"],
    // ---- JUNE 2027 ----
    ['2027-06-01', '2027-06-30', "Admission for Graduate Programs"],
    ['2027-06-09', '2027-06-09', "University Council Meeting"],
    ['2027-06-12', '2027-06-12', "Independence Day (Regular Holiday)"],
    ['2027-06-16', '2027-06-16', "Grade 12 Ecumenical Services"],
    ['2027-06-18', '2027-06-18', "HSU Graduation Ceremonies"],
    ['2027-06-21', '2027-06-24', "Registration Period, First Semester AY 2027-2028"],
    ['2027-06-28', '2027-06-29', "Registration Period, First Semester AY 2027-2028"],
    // ---- JULY 2027 ----
    ['2027-07-09', '2027-07-09', "Last Day of Classes, SUMMER TERM 2027"],
    ['2027-07-10', '2027-07-10', "Linkages and Placement Workshop for First Semester AY 2027-2028"],
    ['2027-07-12', '2027-07-13', "Encoding of SUMMER TERM 2027 Final Grades"],
    ['2027-07-14', '2027-07-14', "Releasing of SUMMER TERM 2027 Report of Grades"],
    ['2027-07-15', '2027-07-16', "Registration Period, First Semester AY 2027-2028: SUMMER TERM 2027 Enrollees, Irregular Students and ITEST"],
    ['2027-07-17', '2027-07-17', "Internship Orientation, First Semester AY 2027-2028"],
    ['2027-07-19', '2027-07-19', "First Day of Classes for the First Semester AY 2027-2028"],
    ['2027-07-19', '2027-07-19', "Internship Fair for First Semester AY 2027-2028"],
    ['2027-07-21', '2027-07-21', "Graduation Ceremonies - College"],
];

// Formats a single date or a date range for display
function format_event_date($start, $end = null) {
    $s = strtotime($start);
    if (!$end || $end === $start) return date('F j, Y', $s);
    $e = strtotime($end);
    if (date('Y-m', $s) === date('Y-m', $e)) return date('F j', $s) . '–' . date('j, Y', $e);
    if (date('Y', $s) === date('Y', $e))     return date('F j', $s) . ' – ' . date('F j, Y', $e);
    return date('F j, Y', $s) . ' – ' . date('F j, Y', $e);
}

// If the database has no events yet, show the official calendar above
if (empty($events)) {
    $today = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    foreach ($umak_calendar_2026_2027 as $row) {
        if ($row[1] < $today)       $row_status = 'completed';
        elseif ($row[0] <= $today)  $row_status = 'ongoing';
        else                        $row_status = 'upcoming';
        $events[] = [
            'event_date'     => $row[0],
            'event_end_date' => $row[1],
            'event_name'     => $row[2],
            'status'         => $row_status,
        ];
    }
}

// Month list for the month filter
$event_months = [];
foreach ($events as $ev) {
    $mk = date('Y-m', strtotime($ev['event_date']));
    if (!isset($event_months[$mk])) $event_months[$mk] = date('F Y', strtotime($ev['event_date']));
}

// Data for the month-grid view (read-only, rendered by JS)
$cal_json = [];
foreach ($events as $ev) {
    $cal_json[] = [
        's'  => date('Y-m-d', strtotime($ev['event_date'])),
        'e'  => date('Y-m-d', strtotime($ev['event_end_date'] ?? $ev['event_date'])),
        'n'  => $ev['event_name'],
        'st' => strtolower($ev['status']),
    ];
}
$cal_today_month = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m');
$cal_default_month = isset($event_months[$cal_today_month]) ? $cal_today_month : (array_key_first($event_months) ?? $cal_today_month);

// ============================================
// EVENT COUNTS (for stats strip)
// ============================================
$count_total = count($events);
$count_ongoing = 0; $count_upcoming = 0; $count_completed = 0; $count_cancelled = 0;
foreach ($events as $ev) {
    $s = strtolower($ev['status'] ?? '');
    if ($s === 'ongoing') $count_ongoing++;
    elseif ($s === 'upcoming') $count_upcoming++;
    elseif ($s === 'completed') $count_completed++;
    elseif ($s === 'cancelled') $count_cancelled++;
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

// Background image (same as User_Dashboard)
$bg_image_path = '../../images/dashboard_bg.jpg';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Academic Calendar</title>

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
        body.dark-mode .dropdown-popup { background: #1c2128; border-color: rgba(255,255,255,0.08); }
        body.dark-mode .dropdown-popup .popup-item { color: #e6edf3; }
        body.dark-mode .dropdown-popup .popup-item:hover { background: rgba(255,215,0,0.08); }
        body.dark-mode .dropdown-popup::before { border-bottom-color: #1c2128; }
        body.dark-mode .nav-overlay,
        body.dark-mode .offcanvas-overlay,
        body.dark-mode .modal-backdrop { background: rgba(0,0,0,0.6); }
        body.dark-mode footer { border-top-color: rgba(255,255,255,0.08) !important; }
        body.dark-mode .fab { background: #1a4d7a; border-color: #ffd700; }
        body.dark-mode .offcanvas-close, body.dark-mode .modal-close { background: #3a3a55; color: #b0b0c8; }

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
        .dropdown-popup { position: absolute; top: calc(100% + 6px); left: 50%; transform: translateX(-50%) scale(0.95) translateY(-8px); min-width: 200px; background: #ffffff; box-shadow: 0 12px 40px rgba(0,0,0,0.12); border: 1px solid rgba(0,0,0,0.06); padding: 0.5rem 0; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), visibility 0.2s ease; z-index: 120; }
        .nav-dropdown:hover .dropdown-popup { opacity: 1; visibility: visible; transform: translateX(-50%) scale(1) translateY(0); pointer-events: auto; }
        .dropdown-popup .popup-item { display: block; padding: 0.6rem 1.2rem; font-size: 0.75rem; color: #1a2a3a; text-decoration: none; transition: background 0.15s ease; cursor: pointer; border: none; background: transparent; width: 100%; text-align: left; font-weight: 400; }
        .dropdown-popup .popup-item:hover { background: rgba(0,51,102,0.06); }
        .dropdown-popup .popup-item .item-desc { display: block; font-size: 0.6rem; color: #8a9aaa; font-weight: 400; margin-top: 1px; }
        .dropdown-popup::before { content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); border: 6px solid transparent; border-bottom-color: #ffffff; }
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
        .umak-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-soft); border: 1px solid var(--border-md); padding: 0.35rem 1rem; color: var(--accent-text); font-size: 0.74rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 999px; }

        /* ========== CARD ========== */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--shadow-sm); transition: background 0.3s ease, border-color 0.3s ease; }
        .card-body { padding: 1.375rem; }

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
        .pup-search-form { display: grid; grid-template-columns: 1fr 200px auto; gap: 0.5rem; align-items: center; }
        .pup-search-form select { width: 100%; padding: 9px 12px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-1); font-size: 0.85rem; border-radius: 8px; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
        .pup-search-form select:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(0,51,102,0.10); }
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

        /* ========== SECTION ========== */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin: 0 0 0.75rem; flex-wrap: wrap; gap: 0.75rem; }
        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 18px; background: var(--secondary); border-radius: 2px; flex-shrink: 0; }

        /* ========== SCROLL REVEAL (continuous) ========== */
        .scroll-reveal { opacity: 0; transform: translateY(30px); transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1); will-change: opacity, transform; }
        .scroll-reveal.visible { opacity: 1; transform: translateY(0); }
        .scroll-reveal:not(.visible) { transition-delay: 0s !important; }
        .reveal-delay-1 { transition-delay: 0.05s; }
        .reveal-delay-2 { transition-delay: 0.1s; }
        .reveal-delay-3 { transition-delay: 0.15s; }

        /* ========== CALENDAR TABLE ========== */
        .table-wrap { overflow-x: auto; }
        .calendar-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 520px; }
        .calendar-table th { text-align: left; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-3); padding: 10px 16px; background: var(--surface-2); border-bottom: 1px solid var(--border); }
        .calendar-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); color: var(--text-1); vertical-align: middle; }
        .calendar-table tr:last-child td { border-bottom: none; }
        .calendar-table tbody tr { transition: background 0.12s; }
        .calendar-table tbody tr:hover td { background: var(--surface-2); }
        .event-row.is-hidden { display: none; }
        .date-cell { color: var(--text-2); font-size: 0.8rem; white-space: nowrap; font-weight: 500; }
        .event-name { font-weight: 600; }

        .status-ongoing, .status-upcoming, .status-completed, .status-cancelled, .status-default { font-size: 0.72rem; font-weight: 600; padding: 3px 12px; border-radius: 999px; display: inline-block; white-space: nowrap; }
        .status-ongoing   { background: var(--st-info-bg);    color: var(--st-info-fg); }
        .status-upcoming  { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .status-completed { background: var(--st-ok-bg);      color: var(--st-ok-fg); }
        .status-cancelled { background: var(--st-bad-bg);     color: var(--st-bad-fg); }
        .status-default   { background: var(--surface-3);     color: var(--text-2); }

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
        .service-modal-backdrop, .modal-backdrop { position: fixed; inset: 0; background: rgba(10,20,40,0.45); backdrop-filter: blur(3px); z-index: 500; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .modal-backdrop { z-index: 400; }
        .service-modal-backdrop.open, .modal-backdrop.open { display: flex; }
        .service-modal, .modal { background: var(--surface); border-radius: 12px; box-shadow: var(--shadow-lg); width: 100%; max-width: 420px; max-height: 85vh; overflow: auto; animation: modalIn 0.2s ease; border: 1px solid var(--border); }
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
        .profile-edit-btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 0.5rem; padding: 0.5rem 1.25rem; border: 1px solid var(--border-md); background: transparent; font-size: 0.8125rem; font-weight: 500; color: var(--text-2); cursor: pointer; transition: all 0.15s; border-radius: 8px; }
        .profile-edit-btn:hover { background: var(--surface-3); }

        /* ========== FAB ========== */
        .fab { position: fixed; bottom: 28px; right: 28px; width: 52px; height: 52px; background: var(--primary); border: 2px solid var(--secondary); box-shadow: 0 6px 18px rgba(0,51,102,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 150; border-radius: 12px; }
        .fab:hover { transform: scale(1.05); background: var(--primary-light); }
        .fab svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }

        /* ========== VIEW TOGGLE + MONTH GRID ========== */
        .view-bar { margin-top: 0.8rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; }
        .view-toggle { display: inline-flex; border: 1px solid var(--border-md); border-radius: 8px; overflow: hidden; }
        .view-toggle button { padding: 6px 14px; font-size: 0.78rem; font-weight: 600; background: var(--surface); color: var(--text-2); border: none; cursor: pointer; transition: all 0.15s; }
        .view-toggle button + button { border-left: 1px solid var(--border-md); }
        .view-toggle button.active { background: var(--primary); color: #fff; }
        body.dark-mode .view-toggle button.active { background: var(--secondary); color: #0d1117; }

        .cal-card { padding: 1rem 1.1rem 1.1rem; display: none; }
        .cal-card.show { display: block; }
        .cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.8rem; gap: 0.5rem; }
        .cal-title { font-size: 1.05rem; font-weight: 700; color: var(--text-1); }
        .cal-nav { display: flex; gap: 6px; }
        .cal-nav button { width: 32px; height: 32px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-2); border-radius: 8px; cursor: pointer; font-size: 1rem; line-height: 1; transition: background 0.15s; }
        .cal-nav button:hover { background: var(--surface-3); }
        .cal-long { display: flex; flex-direction: column; gap: 4px; margin-bottom: 0.8rem; }
        .cal-long span { font-size: 0.74rem; padding: 4px 10px; border-radius: 6px; background: var(--accent-soft); color: var(--accent-text); font-weight: 600; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-top: 1px solid var(--border); border-left: 1px solid var(--border); }
        .cal-dow { padding: 6px 4px; text-align: center; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-3); background: var(--surface-2); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .cal-cell { min-height: 88px; padding: 4px 5px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); background: var(--surface); text-align: left; display: flex; flex-direction: column; gap: 3px; cursor: pointer; transition: background 0.12s; overflow: hidden; }
        .cal-cell.empty { background: var(--surface-2); cursor: default; }
        .cal-cell:not(.empty):hover { background: var(--surface-2); }
        .cal-cell.today .cal-day { background: var(--primary); color: #fff; }
        body.dark-mode .cal-cell.today .cal-day { background: var(--secondary); color: #0d1117; }
        .cal-cell.selected { box-shadow: inset 0 0 0 2px var(--primary-light); }
        .cal-day { font-size: 0.72rem; font-weight: 600; color: var(--text-2); width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
        .cal-pill { font-size: 0.64rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cal-pill.st-ongoing   { background: var(--st-info-bg);    color: var(--st-info-fg); }
        .cal-pill.st-upcoming  { background: var(--st-pending-bg); color: var(--st-pending-fg); }
        .cal-pill.st-completed { background: var(--st-ok-bg);      color: var(--st-ok-fg); }
        .cal-pill.st-cancelled { background: var(--st-bad-bg);     color: var(--st-bad-fg); }
        .cal-pill.st-default   { background: var(--surface-3);     color: var(--text-2); }
        .cal-more { font-size: 0.64rem; color: var(--text-3); padding-left: 4px; }
        .cal-detail { margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.9rem; font-size: 0.82rem; color: var(--text-2); }
        .cal-detail h4 { font-size: 0.85rem; font-weight: 700; color: var(--text-1); margin-bottom: 0.5rem; }
        .cal-detail ul { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .cal-detail li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid var(--border); }
        .cal-detail li:last-child { border-bottom: none; }
        @media(max-width:768px){ .cal-cell { min-height: 56px; } .cal-pill { display: none; } .cal-more { display: none; } .cal-cell .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary-light); display: inline-block; } }
        @media(min-width:769px){ .cal-cell .dot { display: none; } }

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
            .pup-search-form { grid-template-columns: 1fr; }
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
            <h1 class="page-title">Academic Calendar</h1>
            <p class="page-sub">View the schedule of academic events for the school year.</p>
        </div>
        <span class="umak-badge">A.Y. 2026-2027</span>
    </div>

    <div class="content-layout">

        <!-- MAIN -->
        <div class="main-content">

            <!-- Stats strip -->
            <div class="card stats-grid">
                <div class="stat-box"><div class="stat-value"><?php echo $count_total; ?></div><div class="stat-label">Total Events</div></div>
                <div class="stat-box"><div class="stat-value"><?php echo $count_upcoming + $count_ongoing; ?></div><div class="stat-label">Upcoming / Ongoing</div></div>
                <div class="stat-box"><div class="stat-value"><?php echo $count_completed; ?></div><div class="stat-label">Completed</div></div>
            </div>

            <!-- Search + filter -->
            <div class="card toolbar">
                <form class="pup-search-form" onsubmit="event.preventDefault(); filterEvents();">
                    <div class="search-field">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                        <input type="text" id="eventSearch" placeholder="Search event name or date..." onkeyup="filterEvents()">
                    </div>
                    <select id="eventMonth" onchange="filterEvents()" aria-label="Filter by month">
                        <option value="all">All months</option>
                        <?php foreach ($event_months as $mk => $ml): ?>
                            <option value="<?php echo htmlspecialchars($mk); ?>"><?php echo htmlspecialchars($ml); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pup-search-actions">
                        <button type="button" class="pup-clear-btn" onclick="clearEventFilters()">Clear</button>
                    </div>
                </form>
                <div class="chips" id="eventChips">
                    <button type="button" class="chip active" data-status="all" onclick="setEventStatus('all')">All</button>
                    <?php if ($count_ongoing > 0): ?><button type="button" class="chip" data-status="ongoing" onclick="setEventStatus('ongoing')">Ongoing</button><?php endif; ?>
                    <?php if ($count_upcoming > 0): ?><button type="button" class="chip" data-status="upcoming" onclick="setEventStatus('upcoming')">Upcoming</button><?php endif; ?>
                    <?php if ($count_completed > 0): ?><button type="button" class="chip" data-status="completed" onclick="setEventStatus('completed')">Completed</button><?php endif; ?>
                    <?php if ($count_cancelled > 0): ?><button type="button" class="chip" data-status="cancelled" onclick="setEventStatus('cancelled')">Cancelled</button><?php endif; ?>
                </div>
                <div class="view-bar">
                    <div class="view-toggle" role="group" aria-label="View">
                        <button type="button" id="viewBtnList" class="active" onclick="setView('list')">List</button>
                        <button type="button" id="viewBtnCal" onclick="setView('cal')">Calendar</button>
                    </div>
                </div>
            </div>

            <div class="card scroll-reveal reveal-delay-1" id="listView">
                <div class="table-wrap">
                    <table class="calendar-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Event</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($events)): ?>
                                <tr>
                                    <td colspan="2" style="text-align:center;padding:2rem 0;color:var(--text-3);">
                                        No academic events scheduled yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <tr class="event-row scroll-reveal"
                                        data-status="<?php echo htmlspecialchars(strtolower($event['status'])); ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower($event['event_name'])); ?>"
                                        data-month="<?php echo htmlspecialchars(date('Y-m', strtotime($event['event_date']))); ?>"
                                        data-date="<?php echo htmlspecialchars(strtolower(format_event_date($event['event_date'], $event['event_end_date'] ?? null))); ?>">
                                        <td class="date-cell"><?php echo htmlspecialchars(format_event_date($event['event_date'], $event['event_end_date'] ?? null)); ?></td>
                                        <td class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($events)): ?>
                    <div class="empty-state" id="eventEmpty">No events match your search. Try a different keyword or clear the filters.</div>
                <?php endif; ?>
            </div>

            <!-- Month-grid view (read-only) -->
            <div class="card cal-card" id="calView">
                <div class="cal-head">
                    <div class="cal-title" id="calTitle"></div>
                    <div class="cal-nav">
                        <button type="button" onclick="calNav(-1)" aria-label="Previous month">&#8249;</button>
                        <button type="button" onclick="calNav(1)" aria-label="Next month">&#8250;</button>
                    </div>
                </div>
                <div class="cal-long" id="calLong"></div>
                <div class="cal-grid" id="calGrid"></div>
                <div class="cal-detail" id="calDetail">Click a day to see its events.</div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <aside class="sidebar">
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
        window.location.href = '../../includes/logout.php';
    }
}

// ========== SEARCH + FILTER (events) ==========
var activeEventStatus = 'all';
function setEventStatus(s) {
    activeEventStatus = s;
    document.querySelectorAll('#eventChips .chip').forEach(function(c) {
        c.classList.toggle('active', c.getAttribute('data-status') === s);
    });
    filterEvents();
}
function filterEvents() {
    var q = document.getElementById('eventSearch').value.toLowerCase().trim();
    var month = document.getElementById('eventMonth').value;
    if (month !== 'all') calYM = month;
    var any = false;
    document.querySelectorAll('.event-row').forEach(function(row) {
        var okQ = q === '' || row.getAttribute('data-name').indexOf(q) !== -1 || row.getAttribute('data-date').indexOf(q) !== -1;
        var okS = activeEventStatus === 'all' || row.getAttribute('data-status') === activeEventStatus;
        var okM = month === 'all' || row.getAttribute('data-month') === month;
        row.classList.toggle('is-hidden', !(okQ && okS && okM));
        if (okQ && okS && okM) any = true;
    });
    var empty = document.getElementById('eventEmpty');
    if (empty) empty.classList.toggle('show', !any);
    // re-run scroll reveal so rows that just appeared become visible
    window.dispatchEvent(new Event('resize'));
    if (calActive) renderCal();
}

// ========== MONTH-GRID VIEW (read-only) ==========
var CAL_EVENTS = <?php echo json_encode($cal_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
var calYM = <?php echo json_encode($cal_default_month); ?>;
var calActive = false;
var calSelected = null;
var MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function pad2(n) { return (n < 10 ? '0' : '') + n; }
function stClass(st) { return ['ongoing','upcoming','completed','cancelled'].indexOf(st) !== -1 ? 'st-' + st : 'st-default'; }
function calFiltered() {
    var q = document.getElementById('eventSearch').value.toLowerCase().trim();
    return CAL_EVENTS.filter(function(ev) {
        var okQ = q === '' || ev.n.toLowerCase().indexOf(q) !== -1;
        var okS = activeEventStatus === 'all' || ev.st === activeEventStatus;
        return okQ && okS;
    });
}
function dayDiff(a, b) { return Math.round((new Date(b) - new Date(a)) / 86400000); }

function setView(v) {
    calActive = (v === 'cal');
    document.getElementById('listView').style.display = calActive ? 'none' : '';
    document.getElementById('calView').classList.toggle('show', calActive);
    document.getElementById('viewBtnList').classList.toggle('active', !calActive);
    document.getElementById('viewBtnCal').classList.toggle('active', calActive);
    if (calActive) renderCal();
    else window.dispatchEvent(new Event('resize'));
}
function calNav(delta) {
    var p = calYM.split('-'), d = new Date(+p[0], +p[1] - 1 + delta, 1);
    calYM = d.getFullYear() + '-' + pad2(d.getMonth() + 1);
    calSelected = null;
    var sel = document.getElementById('eventMonth');
    sel.value = Array.prototype.some.call(sel.options, function(o) { return o.value === calYM; }) ? calYM : 'all';
    renderCal();
}
function renderCal() {
    var p = calYM.split('-'), y = +p[0], m = +p[1] - 1;
    var startDow = new Date(y, m, 1).getDay();
    var days = new Date(y, m + 1, 0).getDate();
    var todayIso = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
    var evs = calFiltered();
    var firstIso = y + '-' + pad2(m + 1) + '-01', lastIso = y + '-' + pad2(m + 1) + '-' + pad2(days);

    document.getElementById('calTitle').textContent = MONTH_NAMES[m] + ' ' + y;

    // month-long events go in a strip above the grid
    var longHtml = '';
    evs.forEach(function(ev) {
        if (dayDiff(ev.s, ev.e) > 14 && ev.s <= lastIso && ev.e >= firstIso) longHtml += '<span>' + esc(ev.n) + '</span>';
    });
    document.getElementById('calLong').innerHTML = longHtml;

    var html = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(function(d) { return '<div class="cal-dow">' + d + '</div>'; }).join('');
    for (var i = 0; i < startDow; i++) html += '<div class="cal-cell empty"></div>';
    for (var d = 1; d <= days; d++) {
        var iso = y + '-' + pad2(m + 1) + '-' + pad2(d);
        var dayEvs = evs.filter(function(ev) { return ev.s <= iso && ev.e >= iso && dayDiff(ev.s, ev.e) <= 14; });
        var cls = 'cal-cell' + (iso === todayIso ? ' today' : '') + (iso === calSelected ? ' selected' : '');
        html += '<div class="' + cls + '" onclick="selectDay(\'' + iso + '\')"><span class="cal-day">' + d + '</span>';
        dayEvs.slice(0, 2).forEach(function(ev) { html += '<span class="cal-pill ' + stClass(ev.st) + '" title="' + esc(ev.n) + '">' + esc(ev.n) + '</span>'; });
        if (dayEvs.length > 2) html += '<span class="cal-more">+' + (dayEvs.length - 2) + ' more</span>';
        if (dayEvs.length) html += '<span class="dot"></span>';
        html += '</div>';
    }
    document.getElementById('calGrid').innerHTML = html;
    if (calSelected) showDay(calSelected); else document.getElementById('calDetail').textContent = 'Click a day to see its events.';
}
function selectDay(iso) { calSelected = iso; renderCal(); }
function showDay(iso) {
    var evs = calFiltered().filter(function(ev) { return ev.s <= iso && ev.e >= iso; });
    var p = iso.split('-');
    var html = '<h4>' + MONTH_NAMES[+p[1] - 1] + ' ' + (+p[2]) + ', ' + p[0] + '</h4>';
    if (!evs.length) html += 'No events on this day.';
    else {
        html += '<ul>';
        evs.forEach(function(ev) {
            html += '<li><span>' + esc(ev.n) + '</span><span class="status-' + (stClass(ev.st) === 'st-default' ? 'default' : ev.st) + '">' + esc(ev.st.charAt(0).toUpperCase() + ev.st.slice(1)) + '</span></li>';
        });
        html += '</ul>';
    }
    document.getElementById('calDetail').innerHTML = html;
}
function clearEventFilters() {
    document.getElementById('eventSearch').value = '';
    document.getElementById('eventMonth').value = 'all';
    setEventStatus('all');
}

// ========== CONTINUOUS SCROLL REVEAL ==========
(function () {
    function initReveal() {
        var items = Array.prototype.slice.call(document.querySelectorAll('.scroll-reveal'));
        var ticking = false;

        function update() {
            var vh = window.innerHeight || document.documentElement.clientHeight;
            items.forEach(function (el) {
                var r = el.getBoundingClientRect();
                var inView = r.top < vh - 60 && r.bottom > 0;
                if (inView) { el.classList.add('visible'); } else { el.classList.remove('visible'); }
            });
            ticking = false;
        }

        function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }

        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onScroll);
        window.addEventListener('load', update);

        requestAnimationFrame(function () { requestAnimationFrame(update); });
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