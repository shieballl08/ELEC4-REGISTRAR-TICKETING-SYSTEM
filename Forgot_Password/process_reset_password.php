<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// IMPORTANT: Start session
session_start();
header('Content-Type: application/json');

// ============================================
// CHECK SESSION
// ============================================
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_pending']) || $_SESSION['reset_pending'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
    exit;
}

$email = $_SESSION['reset_email'];

// ============================================
// DATABASE CONNECTION
// ============================================
require_once __DIR__ . '/../config/db_connect_pg.php';

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// ============================================
// GET POST DATA
// ============================================
$input = json_decode(file_get_contents('php://input'), true);
$otp_code = trim($input['otp_code'] ?? '');
$new_password = $input['new_password'] ?? '';

if (empty($otp_code)) {
    echo json_encode(['success' => false, 'message' => 'OTP code is required']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

// ============================================
// VALIDATE PASSWORD
// ============================================
if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}
if (!preg_match('/[A-Z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain an uppercase letter']);
    exit;
}
if (!preg_match('/[a-z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain a lowercase letter']);
    exit;
}
if (!preg_match('/[0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain a number']);
    exit;
}
if (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character']);
    exit;
}

// ============================================
// VERIFY OTP
// ============================================
try {
    // Get user with OTP
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND reset_otp = ?");
    $stmt->execute([$email, $otp_code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please try again.']);
        exit;
    }
    
    // Check if OTP is expired
    $now = new DateTime();
    $expires = new DateTime($user['reset_otp_expires']);
    
    if ($now > $expires) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }
    
    // ============================================
    // HASH NEW PASSWORD AND UPDATE
    // ============================================
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_otp = NULL, reset_otp_expires = NULL WHERE id = ?");
    $stmt->execute([$hashed_password, $user['id']]);
    
    // ============================================
    // FORCE CLEAR ALL SESSIONS - CRITICAL PART
    // ============================================
    
    // 1. Unset all session variables
    $_SESSION = array();
    
    // 2. Delete the session cookie from browser
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    // 3. Destroy the session
    session_destroy();
    
    // 4. Start a completely new session
    session_start();
    
    // 5. DO NOT SET any session variables here - keep it clean!
    // Wag mag-set ng kahit anong session variable
    
    // ============================================
    // RETURN SUCCESS RESPONSE
    // ============================================
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully! You can now login with your new password.',
        'redirect' => '../main/Front_Page.php?reset=success'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>