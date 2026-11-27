<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// ============================================
// DATABASE CONNECTION - GAMIT ANG Database CLASS
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
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// ============================================
// CHECK IF EMAIL EXISTS
// ============================================
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found. Please check your email address.']);
        exit;
    }
    
    // ============================================
    // GENERATE OTP
    // ============================================
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // ============================================
    // SAVE OTP TO DATABASE
    // ============================================
    $stmt = $pdo->prepare("UPDATE users SET reset_otp = NULL, reset_otp_expires = NULL WHERE email = ?");
    $stmt->execute([$email]);
    
    $stmt = $pdo->prepare("UPDATE users SET reset_otp = ?, reset_otp_expires = ? WHERE email = ?");
    $stmt->execute([$otp, $expires_at, $email]);
    
    // ============================================
    // STORE IN SESSION
    // ============================================
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_otp_expiry'] = time() + 600;
    $_SESSION['reset_pending'] = true;
    
    // ============================================
    // SEND OTP EMAIL (ACTUAL EMAIL SENDING)
    // ============================================
    require_once __DIR__ . '/../config/email_config.php';
    
    $emailResult = sendOTPEmail($email, $user['full_name'], $otp);
    
    if (!$emailResult['success']) {
        error_log("Reset OTP email failed: " . $emailResult['message']);
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP email: ' . $emailResult['message']]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email! Please check your inbox.'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>