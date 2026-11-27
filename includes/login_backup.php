<?php
// includes/login.php - LAST WORKING (Email/Username/Student ID/Full Name + OTP + LOCKOUT)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/email_config.php';

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
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // CHECK 1: Kung naka-lock ba yung account
    if ($user['is_locked'] && strtotime($user['locked_until']) > time()) {
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
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

        // Kung umabot na sa max attempts, i-lock yung account
        if ($new_attempts >= $MAX_ATTEMPTS) {
            $lock_until = date('Y-m-d H:i:s', time() + ($LOCK_DURATION * 60));
            $update = $pdo->prepare("UPDATE users SET login_attempts = ?, is_locked = TRUE, locked_until = ? WHERE id = ?");
            $update->execute([$new_attempts, $lock_until, $user['id']]);

            // SEND EMAIL NOTIFICATION
            $subject = "Account Locked - UMak Registrar Portal";
            $message = "Hi {$user['full_name']},\n\nYour account has been locked due to multiple failed login attempts.\n\nPlease wait $LOCK_DURATION minutes.\n\nIf this wasn't you, please contact the Registrar's Office immediately.";
            @mail($user['email'], $subject, $message);

            echo json_encode(['success' => false, 'message' => "Account locked due to $MAX_ATTEMPTS failed attempts. Please try again in $LOCK_DURATION minutes."]);
            exit;
        } else {
            $update = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
            $update->execute([$new_attempts, $user['id']]);

            echo json_encode(['success' => false, 'message' => "Invalid password. Attempt $new_attempts of $MAX_ATTEMPTS."]);
            exit;
        }
    }

    // TAMA ANG PASSWORD
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

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'] ?? 'student';
    $_SESSION['logged_in'] = true;
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_expiry'] = time() + 1800;
    $_SESSION['pending_otp'] = true;

    $role = $user['role'] ?? 'student';
    $redirect_url = '../main/User_Dashboard.php';
    
    switch($role) {
        case 'student': $redirect_url = '../main/User_Dashboard.php'; break;
        case 'faculty': $redirect_url = '../main/Faculty_Dashboard.php'; break;
        case 'staff': $redirect_url = '../main/Staff_Dashboard.php'; break;
        case 'admin': $redirect_url = '../main/Admin_Dashboard.php'; break;
        default: $redirect_url = '../main/User_Dashboard.php';
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