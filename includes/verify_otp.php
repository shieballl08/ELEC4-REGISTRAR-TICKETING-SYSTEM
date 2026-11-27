<?php
// includes/verify_otp.php - LAST WORKING
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$otp_code = trim($input['otp_code'] ?? '');

if (empty($otp_code)) {
    echo json_encode(['success' => false, 'message' => 'OTP code is required']);
    exit;
}

$email = $_SESSION['pending_email'] ?? '';
$pending_otp = $_SESSION['pending_otp'] ?? false;

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

if (!$pending_otp) {
    echo json_encode(['success' => false, 'message' => 'No pending OTP verification. Please login again.']);
    exit;
}

try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=umak_registrar", "postgres", "Umak_Registrar2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB Connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM otp_codes 
        WHERE email = ? AND otp_code = ? AND is_used = false AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$email, $otp_code]);
    $otpRecord = $stmt->fetch();

    if ($otpRecord) {
        $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = true WHERE id = ?");
        $stmt->execute([$otpRecord['id']]);
        
                // DITO LANG i-set ang OFFICIAL logged-in session
        $_SESSION['user_id']    = $_SESSION['pending_user_id']   ?? null;
        $_SESSION['email']      = $_SESSION['pending_email']     ?? null;
        $_SESSION['full_name']  = $_SESSION['pending_full_name'] ?? null;
        $_SESSION['role']       = $_SESSION['pending_role']      ?? 'student';
        $_SESSION['logged_in']  = true;
        $_SESSION['otp_verified'] = true;

        // Linisin ang pending data
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['pending_full_name']);
        unset($_SESSION['pending_role']);
        unset($_SESSION['pending_otp']);
        unset($_SESSION['otp_code']);
        unset($_SESSION['otp_expiry']);

        $role = $_SESSION['role'];
        $redirect_url = '../main/User_Dashboard.php';

        switch($role) {
            case 'student': $redirect_url = '../main/User_Dashboard.php'; break;
            case 'alumni':  $redirect_url = '../main/User_Dashboard.php'; break;
            case 'faculty': $redirect_url = '../main/Faculty_Dashboard.php'; break;
            case 'staff': $redirect_url = '../main/Staff_Dashboard.php'; break;
            case 'admin': $redirect_url = '../main/admin/Admin_Dashboard.php'; break;
            default: $redirect_url = '../main/User_Dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'redirect' => $redirect_url,
            'message' => 'OTP verified successfully!'
        ]);
        exit;
        
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM otp_codes 
            WHERE email = ? AND otp_code = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $otp_code]);
        $existingOtp = $stmt->fetch();
        
        if ($existingOtp) {
            if ($existingOtp['is_used']) {
                echo json_encode(['success' => false, 'message' => 'This OTP has already been used. Please request a new one.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please try again.']);
        }
        exit;
    }

} catch (PDOException $e) {
    error_log("OTP verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>