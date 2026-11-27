<?php
// includes/login.php - LAST WORKING (Email/Username/Student ID/Full Name + OTP + LOCKOUT + PORTAL CHECK)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/activity_logger.php';

// Database connection
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=umak_registrar", "postgres", "Umak_Registrar2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB Connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$login_input = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Which portal modal was used (sent by frontend)
$portal = strtolower(trim($input['portal'] ?? 'student'));
if (!in_array($portal, ['student', 'faculty', 'admin'], true)) {
    $portal = 'student';
}

if (empty($login_input) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email/Username/Student ID/Full Name and password are required']);
    exit;
}

// Lockout settings
$MAX_ATTEMPTS = 5;
$LOCK_DURATION = 15; // 15 minutes

try {
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE email = ? 
           OR username = ? 
           OR student_id = ?
           OR full_name = ?
        LIMIT 1
    ");
    $stmt->execute([$login_input, $login_input, $login_input, $login_input]);
    $user = $stmt->fetch();

        if (!$user) {
        log_activity($pdo, null, 'LOGIN_FAILED', 
            'Failed login attempt for: ' . $login_input . ' (user not found)', 'FAILED');
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

        // CHECK 1: Kung naka-lock ba yung account
    if ($user['is_locked'] && strtotime($user['locked_until']) > time()) {
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
        log_activity($pdo, $user['id'], 'LOGIN_FAILED',
            'Login attempt on locked account: ' . $user['email'], 'FAILED');
        echo json_encode(['success' => false, 'message' => "Account locked. Please try again in $remaining minutes."]);
        exit;
    }

    // CHECK 2: Kung naka-lock na pero expired na yung lock (auto-unlock)
    if ($user['is_locked'] && strtotime($user['locked_until']) <= time()) {
        $update = $pdo->prepare("UPDATE users SET login_attempts = 0, is_locked = FALSE, locked_until = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $user['login_attempts'] = 0;
        $user['is_locked'] = false;
        $user['locked_until'] = null;
    }

    // CHECK 3: I-verify yung password
    if (!password_verify($password, $user['password_hash'])) {
        // MALI ANG PASSWORD
        // I-increment yung login_attempts
        $new_attempts = $user['login_attempts'] + 1;

        if ($new_attempts >= $MAX_ATTEMPTS) {
    $lock_until = date('Y-m-d H:i:s', time() + ($LOCK_DURATION * 60));
    $update = $pdo->prepare("UPDATE users SET login_attempts = ?, is_locked = TRUE, locked_until = ? WHERE id = ?");
    $update->execute([$new_attempts, $lock_until, $user['id']]);

    // SEND EMAIL NOTIFICATION
    $subject = "Account Locked - UMak Registrar Portal";
    $message = "Hi {$user['full_name']},\n\nYour account has been locked due to multiple failed login attempts.\n\nPlease wait $LOCK_DURATION minutes.\n\nIf this wasn't you, please contact the Registrar's Office immediately.";
    @mail($user['email'], $subject, $message);

    log_activity($pdo, $user['id'], 'ACCOUNT_LOCKED',
        'Account locked due to ' . $MAX_ATTEMPTS . ' failed attempts', 'FAILED');
       echo json_encode(['success' => false, 'message' => "Account locked due to $MAX_ATTEMPTS failed attempts. Please try again in $LOCK_DURATION minutes."]);
    exit;
}

        // MALI ANG PASSWORD pero hindi pa naka-lock
        $update = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
        $update->execute([$new_attempts, $user['id']]);

        log_activity($pdo, $user['id'], 'LOGIN_FAILED',
            'Wrong password for: ' . $user['email'], 'FAILED');

        $left = $MAX_ATTEMPTS - $new_attempts;
        echo json_encode(['success' => false, 'message' => "Invalid credentials. $left attempt(s) left."]);
        exit;
    }
    // TAMA ANG PASSWORD

       // CHECK 4: Dapat tugma ang role ng account sa portal na ginamit
    $userRole = $user['role'] ?? 'student';

    // Both 'student' and 'alumni' can access the Student Portal
    $isAllowed = false;
    if ($portal === 'student') {
        $isAllowed = in_array($userRole, ['student', 'alumni'], true);
    } elseif ($portal === 'faculty') {
        $isAllowed = ($userRole === 'faculty');
    } elseif ($portal === 'admin') {
        $isAllowed = ($userRole === 'admin');
    }

    if (!$isAllowed) {
        $portalLabels = [
            'student' => 'Student Portal',
            'faculty' => 'Faculty Panel',
            'admin'   => 'Admin Panel'
        ];
        echo json_encode([
            'success' => false,
            'message' => 'This account is not allowed to log in to the ' . $portalLabels[$portal] . '. Please use the correct portal.'
        ]);
        exit;
    }

    // Reset attempts
    $update = $pdo->prepare("UPDATE users SET login_attempts = 0, is_locked = FALSE, locked_until = NULL, last_login = NOW() WHERE id = ?");
    $update->execute([$user['id']]);

    // Generate OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_used BOOLEAN DEFAULT FALSE
        )");
    } catch (Exception $e) {}

    $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = true WHERE email = ? AND is_used = false");
    $stmt->execute([$user['email']]);

    $stmt = $pdo->prepare("INSERT INTO otp_codes (email, otp_code, expires_at) VALUES (?, ?, NOW() + INTERVAL '30 minutes')");
    $stmt->execute([$user['email'], $otp]);

    $emailResult = sendOTPEmail($user['email'], $user['full_name'], $otp);

    if (!$emailResult['success']) {
        error_log("OTP email send failed: " . $emailResult['message']);
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP email']);
        exit;
    }

    // PENDING session lang - hindi pa logged in
    $_SESSION['pending_user_id']   = $user['id'];
    $_SESSION['pending_email']     = $user['email'];
    $_SESSION['pending_full_name'] = $user['full_name'];
    $_SESSION['pending_role']      = $user['role'] ?? 'student';
    $_SESSION['otp_code']          = $otp;
    $_SESSION['otp_expiry']        = time() + 1800;
    $_SESSION['pending_otp']       = true;

        // Log the login success (OTP sent)
    log_activity($pdo, $user['id'], 'LOGIN',
        'Login successful — OTP sent to email', 'SUCCESS');



    // HINDI PA NAKA-SET:
    // $_SESSION['user_id']
    // $_SESSION['logged_in']
    // $_SESSION['role']

    // ============================================
    // ROLE-BASED REDIRECT — Student + Faculty + Admin
    // ============================================
    $role = $user['role'] ?? 'student';
    $redirect_url = '../main/User_Dashboard.php';

        switch ($role) {
        case 'student':
            $redirect_url = '../main/User_Dashboard.php';
            break;
        case 'alumni':
            // Alumni also go to the Student Dashboard
            $redirect_url = '../main/User_Dashboard.php';
            break;
        case 'faculty':
            $redirect_url = '../main/Faculty_Dashboard.php';
            break;
        case 'admin':
            $redirect_url = '../main/admin/Admin_Dashboard.php';
            break;
        default:
            // Unknown role — block
            echo json_encode([
                'success' => false,
                'message' => 'This role is not supported. Please contact the administrator.'
            ]);
            exit();
    }

    echo json_encode([
        'success' => true,
        'requires_otp' => true,
        'message' => 'OTP sent to your email',
        'redirect' => $redirect_url,
        'user' => [
            'id' => $user['id'],
            'name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'student'
        ]
    ]);

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error']);
}
?>