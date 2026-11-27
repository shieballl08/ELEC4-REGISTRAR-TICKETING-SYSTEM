<?php
// includes/verify_unlock.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

// Database connection
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=umak_registrar", "postgres", "Umak_Registrar2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$otp = trim($input['otp'] ?? '');

if (empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'OTP required']);
    exit;
}

try {
    $email = $_SESSION['unlock_email'] ?? '';
    $stored_otp = $_SESSION['unlock_otp'] ?? '';
    $expiry = $_SESSION['unlock_expiry'] ?? 0;

    if (empty($email) || empty($stored_otp)) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
        exit;
    }

    if (time() > $expiry) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }

    if ($otp !== $stored_otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
        exit;
    }

    // Unlock account gamit yung TAMANG column
    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, is_locked = FALSE, locked_until = NULL, unlock_otp = NULL, unlock_otp_expires = NULL WHERE email = ?");
    $stmt->execute([$email]);

    // Clear session
    unset($_SESSION['unlock_email']);
    unset($_SESSION['unlock_otp']);
    unset($_SESSION['unlock_expiry']);

    echo json_encode([
        'success' => true,
        'message' => 'Account unlocked successfully! You can now login.',
        'redirect' => '../main/Front_Page.php'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>