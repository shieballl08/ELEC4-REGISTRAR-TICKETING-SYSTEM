<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect_pg.php';

// ============================================
// AUTH CHECK
// ============================================
if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

$request_id = (int)($_GET['id'] ?? 0);

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Missing request ID.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ============================================
    // MAIN REQUEST + STUDENT INFO
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.user_id,
            r.request_type,
            r.details,
            r.status,
            r.priority,
            r.ticket_number,
            r.processed_by,
            r.processed_at,
            r.released_at,
            r.created_at,
            r.updated_at,
            u.full_name  AS student_name,
            u.email      AS student_email,
            u.student_id AS student_id,
            u.program    AS student_program,
            u.mobile     AS student_mobile,
            a.full_name  AS processed_by_name
        FROM requests r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN users a ON a.id = r.processed_by
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    // ============================================
    // PARSE DETAILS
    // Format: "[Category] Document — Purpose (Copies: N · Release: X)"
    // ============================================
    $details = $request['details'] ?? '';
    $parsed = [
        'category' => '',
        'document' => '',
        'purpose'  => $details,
        'copies'   => '',
        'release'  => '',
        'raw'      => $details,
    ];

    if (preg_match('/^\[([^\]]+)\]\s*(.+?)\s+—\s+(.+?)\s+\(Copies:\s*(\d+)\s*·\s*Release:\s*([^)]+)\)\s*$/u', $details, $m)) {
        $parsed['category'] = trim($m[1]);
        $parsed['document'] = trim($m[2]);
        $parsed['purpose']  = trim($m[3]);
        $parsed['copies']   = trim($m[4]);
        $parsed['release']  = trim($m[5]);
    } elseif (preg_match('/^\[([^\]]+)\]\s*(.+?)\s+—\s+(.+)$/u', $details, $m)) {
        $parsed['category'] = trim($m[1]);
        $parsed['document'] = trim($m[2]);
        $parsed['purpose']  = trim($m[3]);
    }

    $request['parsed_details'] = $parsed;

    // ============================================
    // STATUS HISTORY (timeline)
    // ============================================
    $histStmt = $pdo->prepare("
        SELECT 
            h.id,
            h.old_status,
            h.new_status,
            h.remarks,
            h.changed_at,
            u.full_name AS changed_by_name
        FROM request_status_history h
        LEFT JOIN users u ON u.id = h.changed_by
        WHERE h.request_id = :rid
        ORDER BY h.changed_at ASC
    ");
    $histStmt->execute([':rid' => $request_id]);
    $request['history'] = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'request' => $request,
    ]);

} catch (PDOException $e) {
    error_log("get_request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}