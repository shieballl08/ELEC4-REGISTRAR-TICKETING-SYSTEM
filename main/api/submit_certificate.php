<?php
// ============================================
// SUBMIT CERTIFICATE REQUEST
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

// CHECK LOGIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

// DB CONNECTION (with fallbacks)
$configPath = __DIR__ . '/../../config/db_connect.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    $fallbackPath = __DIR__ . '/../../../config/db_connect.php';
    if (file_exists($fallbackPath)) {
        require_once $fallbackPath;
    } else {
        $fallbackPath2 = __DIR__ . '/../../config/db_connect_pg.php';
        if (file_exists($fallbackPath2)) {
            require_once $fallbackPath2;
        } else {
            echo json_encode(['success' => false, 'message' => 'DB config not found.']);
            exit();
        }
    }
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$cert_type = trim($input['cert_type'] ?? '');
$purpose = trim($input['purpose'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($cert_type)) {
    echo json_encode(['success' => false, 'message' => 'Certificate type is required.']);
    exit();
}

try {
    if (!function_exists('getDBConnection')) {
        echo json_encode(['success' => false, 'message' => 'DB function not found.']);
        exit();
    }
    
    $pdo = getDBConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Cannot connect to database.']);
        exit();
    }
    
    // Generate ticket number
    $ticket_number = 'CERT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insert new request (using your exact table columns)
    $stmt = $pdo->prepare("
        INSERT INTO requests (
            user_id, 
            request_type, 
            details, 
            status, 
            priority, 
            ticket_number, 
            created_at, 
            updated_at
        )
        VALUES (?, ?, ?, 'PENDING', 'Normal', ?, NOW(), NOW())
    ");
    $stmt->execute([$user_id, $cert_type, $purpose, $ticket_number]);
    
    $new_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Certificate request submitted successfully!',
        'request_id' => $new_id,
        'ticket_number' => $ticket_number
    ]);
    
} catch (PDOException $e) {
    error_log("Submit cert error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}