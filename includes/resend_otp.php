<?php
// includes/resend_otp.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// ============================================
// DATABASE CONNECTION
// ============================================
$configPath = __DIR__ . '/../config/db_connect_pg.php';

if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Config not found']);
    exit;
}

require_once $configPath;

$db = new Database();
$pdo = $db->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $email = $_SESSION['user_email'] ?? '';
    $full_name = $_SESSION['full_name'] ?? 'User';

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'No email found in session']);
        exit;
    }

    // Generate new OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Mark old OTPs as used
    $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = true WHERE email = ? AND is_used = false");
    $stmt->execute([$email]);

    // Store new OTP
    $stmt = $pdo->prepare("INSERT INTO otp_codes (email, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $otp, $expires_at]);

    // Send email
    $subject = "🔐 New OTP Code for UMak Login";
    $message = "
    <html>
    <head><style>body{font-family:Arial;padding:20px}.container{max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;border-top:5px solid #003366}.otp-code{font-size:36px;font-weight:bold;color:#003366;background:#f0f0f0;padding:15px 30px;border-radius:8px;letter-spacing:8px;margin:20px 0;text-align:center}</style></head>
    <body>
        <div class='container'>
            <h2 style='color:#003366;'>🔄 New OTP Request</h2>
            <p>Hello <strong>" . htmlspecialchars($full_name) . "</strong>,</p>
            <p>You requested a new OTP code for login.</p>
            <div class='otp-code'>" . $otp . "</div>
            <p style='color:#666;'>This OTP is valid for <strong>5 minutes</strong>.</p>
            <p style='color:#999;font-size:12px;'>If you didn't request this, please ignore.</p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: UMak Registrar <noreply@umak.edu.ph>" . "\r\n";

    $mail_sent = mail($email, $subject, $message, $headers);

    echo json_encode([
        'success' => true,
        'otp' => $otp,
        'message' => $mail_sent ? 'New OTP sent to your email.' : 'OTP generated but email sending failed.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>