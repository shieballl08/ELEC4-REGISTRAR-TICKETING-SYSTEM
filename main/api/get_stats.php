<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect_pg.php';

// 🧪 TEMPORARY BYPASS FOR TESTING
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin Test';
    $_SESSION['email'] = 'admin@umak.edu.ph';
    $_SESSION['role'] = 'admin';
    $_SESSION['logged_in'] = true;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $counts = ['success'=>true, 'pending'=>0, 'processing'=>0, 'ready'=>0, 'completed'=>0];
    $stmt = $pdo->query("SELECT status, COUNT(*) c FROM requests GROUP BY status");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $s = strtoupper($row['status']);
        if ($s === 'PENDING') $counts['pending'] = (int)$row['c'];
        elseif (in_array($s, ['PROCESSING','ACCEPTED'])) $counts['processing'] += (int)$row['c'];
        elseif ($s === 'READY') $counts['ready'] = (int)$row['c'];
        elseif (in_array($s, ['COMPLETED','CLAIMED'])) $counts['completed'] += (int)$row['c'];
    }
    echo json_encode($counts);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}