<?php
session_start();
require_once __DIR__ . '/../config/email_config.php';

function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_otp') {
        $userId = $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;
        $email = $_POST['email'] ?? $_SESSION['email'] ?? null;
        $fullName = $_POST['full_name'] ?? $_SESSION['full_name'] ?? 'User';
        
        if (!$userId || !$email) {
            echo json_encode(['success' => false, 'message' => 'User ID and email required']);
            exit;
        }
        
        $otpCode = generateOTP();
        $_SESSION['otp_code'] = $otpCode;
        $_SESSION['otp_user_id'] = $userId;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_expiry'] = time() + 600; // 10 minutes
        $_SESSION['otp_verified'] = false;
        
        $result = sendOTPEmail($email, $fullName, $otpCode);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'verify_otp') {
        $otpInput = $_POST['otp_code'] ?? '';
        $storedOtp = $_SESSION['otp_code'] ?? '';
        $expiry = $_SESSION['otp_expiry'] ?? 0;
        
        if (empty($storedOtp)) {
            echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
            exit;
        }
        
        if (time() > $expiry) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            unset($_SESSION['otp_code']);
            exit;
        }
        
        if ($otpInput === $storedOtp) {
            $_SESSION['otp_verified'] = true;
            unset($_SESSION['otp_code']);
            echo json_encode(['success' => true, 'message' => 'OTP verified successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        }
        exit;
    }
}
?>