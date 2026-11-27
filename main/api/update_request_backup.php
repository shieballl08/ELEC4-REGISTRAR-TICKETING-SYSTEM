<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect_pg.php';

// 🧪 TEMPORARY BYPASS FOR TESTING — PUWERSADONG ADMIN
$_SESSION['user_id'] = 1;
$_SESSION['full_name'] = 'Admin Test';
$_SESSION['email'] = 'admin@umak.edu.ph';
$_SESSION['role'] = 'admin';           // ⚠️ PINUPUWERSA
$_SESSION['logged_in'] = true;

// Check kung admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
        'debug' => [
            'session_id' => session_id(),
            'role'       => $_SESSION['role'] ?? 'NOT SET',
            'user_id'    => $_SESSION['user_id'] ?? 'NOT SET',
            'logged_in'  => $_SESSION['logged_in'] ?? 'NOT SET',
        ]
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$request_id = (int)($input['request_id'] ?? 0);
$action     = $input['action'] ?? '';
$admin_id   = $_SESSION['user_id'] ?? 0;

if (!$request_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$statusMap = [
    'accept'   => 'PROCESSING',
    'reject'   => 'REJECTED',
    'ready'    => 'READY',
    'complete' => 'COMPLETED',
    'claim'    => 'COMPLETED',
];

if (!isset($statusMap[$action])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$new_status = $statusMap[$action];

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $check = $pdo->prepare("SELECT status, ticket_number FROM requests WHERE id = :id");
    $check->execute([':id' => $request_id]);
    $current = $check->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    $validTransitions = [
        'PENDING'    => ['PROCESSING', 'REJECTED'],
        'PROCESSING' => ['READY', 'REJECTED'],
        'ACCEPTED'   => ['READY', 'REJECTED'],
        'READY'      => ['COMPLETED'],
        'COMPLETED'  => [],
        'REJECTED'   => [],
    ];

    $old_status = strtoupper($current['status']);
    if (!in_array($new_status, $validTransitions[$old_status] ?? [])) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot change from $old_status to $new_status"
        ]);
        exit;
    }

    $ticket = null;

    if ($action === 'accept') {
        $ticket = $current['ticket_number']
            ?: 'UMK-' . date('Ymd') . '-' . str_pad($request_id, 4, '0', STR_PAD_LEFT);

        $sql = "UPDATE requests 
                SET status = :status,
                    ticket_number = :ticket,
                    processed_by = :admin_id,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status'   => $new_status,
            ':ticket'   => $ticket,
            ':admin_id' => $admin_id,
            ':id'       => $request_id,
        ]);
    } 
    elseif ($action === 'ready' || $action === 'complete' || $action === 'claim') {
        $sql = "UPDATE requests 
                SET status = :status,
                    released_at = CASE WHEN :status2 IN ('COMPLETED') THEN NOW() ELSE released_at END,
                    updated_at = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status'  => $new_status,
            ':status2' => $new_status,
            ':id'      => $request_id,
        ]);
    }
    else {
        $sql = "UPDATE requests 
                SET status = :status,
                    processed_by = :admin_id,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status'   => $new_status,
            ':admin_id' => $admin_id,
            ':id'       => $request_id,
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => "Request #$request_id updated to $new_status",
        'status'  => $new_status,
        'ticket'  => $current['ticket_number'] ?? $ticket,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}