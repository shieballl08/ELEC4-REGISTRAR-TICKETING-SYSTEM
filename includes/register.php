<?php
// includes/register.php - UPDATED WITH RBAC + SESSION + REDIRECT + STUDENTS TABLE
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Clear any previous output
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');

// Function to safely return JSON
function sendJsonResponse($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

try {
    session_start();
    
    // ============================================
    // DIRECT DATABASE CONNECTION
    // ============================================
    try {
        $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=umak_registrar", "postgres", "Umak_Registrar2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    }
    
    // ============================================
    // GET FORM DATA
    // ============================================
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $student_id = trim($_POST['user_id_number'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // ⬇️ BAGONG FIELDS MULA SA REGISTRATION FORM ⬇️
    $college         = trim($_POST['college'] ?? '');
    $course          = trim($_POST['course'] ?? '');
    $year_level      = trim($_POST['year_level'] ?? '');
    $section         = trim($_POST['section'] ?? '');
    $graduation_year = trim($_POST['graduation_year'] ?? '');
    
    // ============================================
    // SECURITY: Role Validation (Whitelist)
    // ============================================
    $allowed_roles = ['student', 'alumni'];  // Students at Alumni lang pwedeng mag-register
    
    $role = trim($_POST['role'] ?? 'student');
    
    if (!in_array($role, $allowed_roles)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type. Admin accounts are created by the administrator only.'
        ]);
        exit();
    }
    
    // ============================================
    // AUTO-GENERATE USERNAME FROM FULL NAME
    // ============================================
    $name_parts = explode(' ', $full_name);
    $first_name = $name_parts[0] ?? '';
    $username = strtolower($first_name);
    
    // ============================================
    // VALIDATION
    // ============================================
    if (empty($full_name) || empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Full name, email, and password are required.']);
    }
    
    if ($password !== $confirm_password) {
        sendJsonResponse(['success' => false, 'message' => 'Passwords do not match.']);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid email format.']);
    }
    
    // Validate mobile if provided
    if (!empty($mobile)) {
        $mobile_clean = preg_replace('/[\s\-+]/', '', $mobile);
        if (!preg_match('/^(09|\+639|639)\d{9}$/', $mobile_clean)) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid mobile number format.']);
        }
        $mobile = $mobile_clean;
    }
    
    // ⬇️ VALIDATE NEW FIELDS ⬇️
    if (empty($college)) {
        sendJsonResponse(['success' => false, 'message' => 'Please select your college/institute.']);
    }
    if (empty($course)) {
        sendJsonResponse(['success' => false, 'message' => 'Please enter your course/program.']);
    }
    if ($role === 'student' && empty($year_level)) {
        sendJsonResponse(['success' => false, 'message' => 'Please select your year level.']);
    }
    if ($role === 'alumni' && empty($graduation_year)) {
        sendJsonResponse(['success' => false, 'message' => 'Please enter your graduation year.']);
    }
    
    // Password requirements
    if (strlen($password) < 8) {
        sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        sendJsonResponse(['success' => false, 'message' => 'Password must contain an uppercase letter.']);
    }
    if (!preg_match('/[a-z]/', $password)) {
        sendJsonResponse(['success' => false, 'message' => 'Password must contain a lowercase letter.']);
    }
    if (!preg_match('/[0-9]/', $password)) {
        sendJsonResponse(['success' => false, 'message' => 'Password must contain a number.']);
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        sendJsonResponse(['success' => false, 'message' => 'Password must contain a special character.']);
    }
    
    // ============================================
    // CHECK IF EMAIL EXISTS
    // ============================================
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        sendJsonResponse(['success' => false, 'message' => 'Email already registered.']);
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $username = strtolower($first_name) . rand(100, 999);
    }
    
    // Check if student_id exists
    if (!empty($student_id)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetchColumn() > 0) {
            sendJsonResponse(['success' => false, 'message' => 'Student ID already registered.']);
        }
    }
    
    // ============================================
    // HASH PASSWORD
    // ============================================
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // ============================================
    // ⬇️ BAGO: START TRANSACTION (Atomic insert) ⬇️
    // ============================================
    $pdo->beginTransaction();
    
    try {
        // ============================================
        // INSERT SA USERS TABLE
        // ============================================
        $sql = "INSERT INTO users (
                    full_name, 
                    username,
                    email, 
                    password_hash, 
                    role, 
                    program, 
                    graduation_year,
                    student_id, 
                    mobile, 
                    birthdate, 
                    created_at,
                    is_verified,
                    is_active
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), false, true
                )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $full_name,
            $username,
            $email,
            $hashed_password,
            $role,
            $course,                                         // ← program = course
            ($role === 'alumni' ? (int)$graduation_year : null),  // ← graduation_year only for alumni
            $student_id,
            $mobile,
            $birthdate
        ]);
        
        if (!$result) {
            throw new Exception('Failed to create user account.');
        }
        
        // Get the new user ID
        $user_id = $pdo->lastInsertId();
        
        // ============================================
        // ⬇️ BAGO: INSERT SA STUDENTS TABLE ⬇️
        // Para tugma yung profile info sa DB (auto-fetch sa User_Dashboard)
        // ============================================
        $student_sql = "INSERT INTO students (
                            user_id,
                            student_id,
                            college,
                            course,
                            year_level,
                            section,
                            contact_number,
                            created_at,
                            updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                        )";
        
        $student_stmt = $pdo->prepare($student_sql);
        $student_result = $student_stmt->execute([
            $user_id,
            $student_id,
            $college,
            $course,
            ($role === 'student' && !empty($year_level)) ? (int)$year_level : null,  // year_level only for current students
            ($role === 'student') ? $section : null,                                  // section only for current students
            $mobile                                                                   // contact_number = mobile
        ]);
        
        if (!$student_result) {
            throw new Exception('Failed to create student profile.');
        }
        
        // ============================================
        // COMMIT — kung lahat successful
        // ============================================
        $pdo->commit();
        
        // ============================================
        // SET SESSION - AUTO LOGIN
        // ============================================
        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['student_id'] = $student_id;
        $_SESSION['college'] = $college;
        $_SESSION['course'] = $course;
        $_SESSION['year_level'] = $year_level;
        
        // ============================================
        // DETERMINE REDIRECT URL BASED ON ROLE
        // ============================================
        $redirect_url = '../main/User_Dashboard.php';
        
        switch($role) {
            case 'student':
            case 'alumni':
                $redirect_url = '../main/User_Dashboard.php';
                break;
            case 'faculty':
                $redirect_url = '../main/Faculty_Dashboard.php';
                break;
            case 'staff':
                $redirect_url = '../main/Staff_Dashboard.php';
                break;
            case 'admin':
                $redirect_url = '../main/Admin_Dashboard.php';
                break;
            default:
                $redirect_url = '../main/User_Dashboard.php';
        }
        
        // ============================================
        // RETURN WITH REDIRECT
        // ============================================
        sendJsonResponse([
            'success' => true, 
            'message' => 'Account created successfully! You are now logged in.',
            'username' => $username,
            'role' => $role,
            'redirect' => $redirect_url
        ]);
        
    } catch (Exception $innerEx) {
        // ⬇️ ROLLBACK kung may error sa loob ng transaction ⬇️
        $pdo->rollBack();
        error_log("Transaction rolled back: " . $innerEx->getMessage());
        sendJsonResponse(['success' => false, 'message' => 'Registration failed: ' . $innerEx->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Fallback
sendJsonResponse(['success' => false, 'message' => 'Unknown error occurred.']);
?>