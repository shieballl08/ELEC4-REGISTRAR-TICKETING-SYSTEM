<?php
// ============================================
// api/announcements.php
// API endpoint for Admin Dashboard: create/delete announcements
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();

// CHECK LOGIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

// CHECK ROLE
$user_role = strtolower($_SESSION['role'] ?? '');
if (!in_array($user_role, ['admin', 'registrar', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Admin access required.']);
    exit();
}

// CSRF TOKEN CHECK
$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrf_token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit();
}

// DATABASE CONNECTION (auto-detect)
$possiblePaths = [
    __DIR__ . '/../../config/db_connect_pg.php',
    __DIR__ . '/../config/db_connect_pg.php',
    'C:/xampp/htdocs/ELEC_4_SYSTEM/config/db_connect_pg.php'
];

$dbConnected = false;
foreach ($possiblePaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        if (class_exists('Database')) {
            $dbConnected = true;
            break;
        }
    }
}

if (!$dbConnected) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database config not found.']);
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// GET JSON INPUT
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit();
}

$action = $input['action'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 1);

try {
    if ($action === 'create') {
        $title    = trim($input['title'] ?? '');
        $body     = trim($input['body'] ?? '');
        $priority = trim($input['priority'] ?? 'normal');

        if (empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'message' => 'Title and message are required.']);
            exit();
        }

        if (strlen($title) > 255) {
            echo json_encode(['success' => false, 'message' => 'Title is too long (max 255 characters).']);
            exit();
        }

        if (!in_array($priority, ['normal', 'urgent', 'info'])) {
            $priority = 'normal';
        }

        // NOTE: column name is "content" (not "body") + may posted_by & is_active
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, priority, posted_by, is_active, created_at, updated_at)
            VALUES (:title, :content, :priority, :posted_by, TRUE, NOW(), NOW())
        ");
        $stmt->execute([
            ':title'     => $title,
            ':content'   => $body,
            ':priority'  => $priority,
            ':posted_by' => $user_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Announcement posted successfully!',
            'id'      => $pdo->lastInsertId()
        ]);

    } elseif ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid announcement ID.']);
            exit();
        }

        // Soft delete: set is_active = FALSE (para hindi mawala sa database history)
        // Kung gusto mong hard delete, palitan ng: DELETE FROM announcements WHERE id = :id
        $stmt = $pdo->prepare("UPDATE announcements SET is_active = FALSE, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Announcement not found.']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use "create" or "delete".']);
    }

} catch (PDOException $e) {
    error_log("Announcement API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>