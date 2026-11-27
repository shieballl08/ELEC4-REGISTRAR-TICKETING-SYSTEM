<?php
// includes/logout.php
// ============================================
// LOGOUT - Clear Session, Cookies, and Redirect
// ============================================

session_start();

// ============================================
// REQUIRE ACTIVITY LOGGER
// ============================================
require_once __DIR__ . '/../config/activity_logger.php';

// ============================================
// CLEAR REMEMBER ME COOKIES
// ============================================
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['remember_user_id'])) {
    setcookie('remember_user_id', '', time() - 3600, '/');
}

// ============================================
// CLEAR REMEMBER TOKEN IN DATABASE + LOG LOGOUT
// ============================================
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=umak_registrar", "postgres", "Umak_Registrar2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // LOG THE LOGOUT BEFORE CLEARING
        log_activity($pdo, $_SESSION['user_id'], 'LOGOUT', 
            'User logged out', 'SUCCESS');

        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Logout - Database error: " . $e->getMessage());
    }
}

// ============================================
// CLEAR SESSION
// ============================================
$_SESSION = array();

// Delete session cookie
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

// Destroy the session
session_destroy();

// ============================================
// REDIRECT TO LOGIN PAGE
// ============================================
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ELEC_4_SYSTEM/main/Front_Page.php';

header('Location: ' . $base_url);
exit();
?>