<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect_pg.php';
require_once __DIR__ . '/../../config/email_config.php';

// 🧪 TEMPORARY BYPASS FOR TESTING — PUWERSADONG ADMIN
$_SESSION['user_id'] = 1;
$_SESSION['full_name'] = 'Admin Test';
$_SESSION['email'] = 'admin@umak.edu.ph';
$_SESSION['role'] = 'admin';
$_SESSION['logged_in'] = true;

// Kunin ang input
$input = json_decode(file_get_contents('php://input'), true);
$request_id = (int)($input['request_id'] ?? 0);

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Missing request_id']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Kunin ang request + user details
    $sql = "SELECT r.id, r.request_type, r.status, r.ticket_number, r.details,
                   u.full_name, u.email
            FROM requests r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    if (empty($req['email'])) {
        echo json_encode(['success' => false, 'message' => 'Student has no email on record']);
        exit;
    }

    // I-send ang email gamit ang function natin sa email_config.php
    $result = sendRequestNotification(
        $req['email'],
        $req['full_name'] ?? 'Student',
        $req['ticket_number'] ?? '',
        $req['request_type'] ?? 'Document',
        $req['status'] ?? 'PENDING'
    );

    echo json_encode($result);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}