<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect_pg.php';
require_once __DIR__ . '/../../config/activity_logger.php';

// ============================================
// AUTHENTICATION CHECK
// ============================================

// 1. Check kung naka-login
if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated. Please log in.',
    ]);
    exit;
}

// 2. Check kung admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin access required.',
    ]);
    exit;
}

// 3. Get admin_id from real session
$admin_id = (int)$_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$request_id = (int)($input['request_id'] ?? 0);
$action     = $input['action'] ?? '';

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

    $check = $pdo->prepare("SELECT status, ticket_number, request_type FROM requests WHERE id = :id");
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
        $type_prefix = strtoupper($current['request_type'] ?? 'DOC');

        $ticket = $current['ticket_number']
            ?: $type_prefix . '-' . date('Y') . '-' . str_pad($request_id, 4, '0', STR_PAD_LEFT);

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

    // ============================================
    // AUDIT TRAIL LOGGING
    // ============================================
    $logTypeMap = [
        'accept'   => 'REQUEST_ACCEPTED',
        'reject'   => 'REQUEST_REJECTED',
        'ready'    => 'REQUEST_READY',
        'complete' => 'REQUEST_COMPLETED',
        'claim'    => 'REQUEST_CLAIMED',
    ];
    $logDescMap = [
        'accept'   => 'Accepted request #' . $request_id,
        'reject'   => 'Rejected request #' . $request_id,
        'ready'    => 'Marked request #' . $request_id . ' as ready for pickup',
        'complete' => 'Completed request #' . $request_id,
        'claim'    => 'Marked request #' . $request_id . ' as claimed',
    ];

    $logType = $logTypeMap[$action] ?? 'REQUEST_UPDATED';
    $logDesc = $logDescMap[$action] ?? 'Updated request #' . $request_id;
    if ($ticket) {
        $logDesc .= ' (Ticket: ' . $ticket . ')';
    }
    log_activity($pdo, $admin_id, $logType, $logDesc, 'SUCCESS');

    // ============================================
    // LOG STATUS CHANGE SA HISTORY
    // ============================================
    try {
        $history = $pdo->prepare("
            INSERT INTO request_status_history 
                (request_id, old_status, new_status, changed_by, remarks)
            VALUES (:rid, :old, :new, :by, :remarks)
        ");
        $history->execute([
            ':rid'     => $request_id,
            ':old'     => $old_status,
            ':new'     => $new_status,
            ':by'      => $admin_id,
            ':remarks' => "Status changed via admin dashboard",
        ]);
    } catch (PDOException $histErr) {
        error_log("History log failed for request #$request_id: " . $histErr->getMessage());
    }

    // ============================================
    // AUTO EMAIL NOTIFICATION
    // ============================================
    try {
        require_once __DIR__ . '/../../config/email_config.php';

        $emailStmt = $pdo->prepare("
            SELECT u.email, u.full_name, r.ticket_number, r.request_type
            FROM requests r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.id = :rid
        ");
        $emailStmt->execute([':rid' => $request_id]);
        $emailInfo = $emailStmt->fetch(PDO::FETCH_ASSOC);

        if ($emailInfo && !empty($emailInfo['email'])) {
            $emailResult = sendRequestNotification(
                $emailInfo['email'],
                $emailInfo['full_name'] ?? 'Student',
                $emailInfo['ticket_number'] ?? '',
                $emailInfo['request_type'] ?? 'Document',
                $new_status
            );

            if (empty($emailResult['success'])) {
                error_log("Auto-email failed for request #$request_id: " . ($emailResult['message'] ?? 'unknown'));
            }
        }
    } catch (Exception $emailErr) {
        error_log("Auto-email exception for request #$request_id: " . $emailErr->getMessage());
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