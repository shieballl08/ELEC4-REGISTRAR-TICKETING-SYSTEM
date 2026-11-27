<?php
// includes/unlock_account.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/email_config.php'; // <-- ITO YUNG KULANG! GAMITIN MO YUNG FUNCTION!

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
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email required']);
    exit;
}

try {
    // Check if account is locked
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }

    // Check if account is actually locked
    if (!$user['is_locked']) {
        echo json_encode(['success' => false, 'message' => 'Account is not locked']);
        exit;
    }

    // Generate OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // I-save yung OTP sa unlock_otp column
    $stmt = $pdo->prepare("UPDATE users SET unlock_otp = ?, unlock_otp_expires = ? WHERE id = ?");
    $stmt->execute([$otp, $expires_at, $user['id']]);

    // Send email gamit yung sendOTPEmail() function
    $emailResult = sendOTPEmail($email, $user['full_name'], $otp);

    if (!$emailResult['success']) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP email: ' . $emailResult['message']]);
        exit;
    }

    $_SESSION['unlock_email'] = $email;
    $_SESSION['unlock_otp'] = $otp;
    $_SESSION['unlock_expiry'] = time() + 600;

    echo json_encode([
        'success' => true,
        'message' => 'Unlock OTP sent to your email. Please check your inbox.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>