<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect_pg.php';

function out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

// --- Auth: dapat naka-login at admin (sa DB mismo i-check) ---
if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in'])) out(false, 'Not logged in.');

$database = new Database();
$pdo = $database->getConnection();
if (!$pdo) out(false, 'Database connection failed.');

$adminId = (int)$_SESSION['user_id'];

$st = $pdo->prepare("SELECT role FROM users WHERE id = :id");
$st->execute([':id' => $adminId]);
if (strtolower(trim($st->fetchColumn() ?: '')) !== 'admin') out(false, 'Forbidden.');

// --- CSRF ---
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) out(false, 'Invalid CSRF token.');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';

function logAct($pdo, $adminId, $desc, $result = 'SUCCESS') {
    try {
        $s = $pdo->prepare("INSERT INTO user_activities
            (user_id, activity_type, description, division, result, ip_address, user_agent)
            VALUES (:u, 'ADMIN_USER_MGMT', :d, 'Admin', :r, :ip, :ua)");
        $s->execute([
            ':u'  => $adminId,
            ':d'  => $desc,
            ':r'  => $result,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (PDOException $e) { error_log('logAct: ' . $e->getMessage()); }
}

try {
    switch ($action) {

        case 'create':
            $full  = trim($in['full_name'] ?? '');
            $email = strtolower(trim($in['email'] ?? ''));
            $user  = trim($in['username'] ?? '');
            $role  = in_array($in['role'] ?? '', ['admin', 'faculty', 'student', 'alumni'], true) ? $in['role'] : 'student';
            $sid   = trim($in['student_id'] ?? '') ?: null;

            if ($full === '' || $user === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
                out(false, 'Full name, username, and a valid email are required.');

            $dup = $pdo->prepare("SELECT 1 FROM users WHERE LOWER(email) = :e OR LOWER(username) = LOWER(:u)");
            $dup->execute([':e' => $email, ':u' => $user]);
            if ($dup->fetch()) out(false, 'Email or username already exists.');

            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            $ins = $pdo->prepare("INSERT INTO users (username, email, full_name, student_id, role, password_hash, is_active, is_verified, created_at)
                                  VALUES (:u, :e, :f, :s, :r, :p, TRUE, TRUE, NOW())");
            $ins->execute([':u'=>$user, ':e'=>$email, ':f'=>$full, ':s'=>$sid, ':r'=>$role,
                           ':p'=>password_hash($temp, PASSWORD_DEFAULT)]);
            logAct($pdo, $adminId, "Created user $user ($role)");
            out(true, 'User created.', ['temp_password' => $temp]);

        case 'update':
            $id    = (int)($in['id'] ?? 0);
            $full  = trim($in['full_name'] ?? '');
            $email = strtolower(trim($in['email'] ?? ''));
            if (!$id || $full === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'Invalid data.');

            $dup = $pdo->prepare("SELECT 1 FROM users WHERE LOWER(email) = :e AND id <> :id");
            $dup->execute([':e' => $email, ':id' => $id]);
            if ($dup->fetch()) out(false, 'Email already used by another account.');

            $up = $pdo->prepare("UPDATE users SET full_name = :f, email = :e WHERE id = :id");
            $up->execute([':f'=>$full, ':e'=>$email, ':id'=>$id]);
            logAct($pdo, $adminId, "Updated user #$id");
            out(true, 'User updated.');

        case 'toggle_active':
            $id = (int)($in['id'] ?? 0);
            if (!$id) out(false, 'Invalid user.');
            if ($id === $adminId) out(false, 'You cannot deactivate your own account.');
            $up = $pdo->prepare("UPDATE users SET is_active = NOT COALESCE(is_active, FALSE) WHERE id = :id RETURNING is_active");
            $up->execute([':id' => $id]);
            $active = $up->fetchColumn();
            if ($active === false) out(false, 'User not found.');
            logAct($pdo, $adminId, ($active ? 'Activated' : 'Deactivated') . " user #$id");
            out(true, $active ? 'User activated.' : 'User deactivated.');

        case 'reset_password':
            $id = (int)($in['id'] ?? 0);
            if (!$id) out(false, 'Invalid user.');
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            $up = $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
            $up->execute([':p' => password_hash($temp, PASSWORD_DEFAULT), ':id' => $id]);
            logAct($pdo, $adminId, "Reset password for user #$id");
            out(true, 'Password reset.', ['temp_password' => $temp]);

                case 'change_role':
            $id      = (int)($in['id'] ?? 0);
            $newRole = strtolower(trim($in['role'] ?? ''));
            if (!$id || !in_array($newRole, ['admin', 'faculty', 'student', 'alumni'], true)) out(false, 'Invalid data.');
            if ($id === $adminId) out(false, 'You cannot change your own role.');

            $cur = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $cur->execute([':id' => $id]);
            $oldRole = $cur->fetchColumn();
            if ($oldRole === false) out(false, 'User not found.');

            $up = $pdo->prepare("UPDATE users SET role = :r WHERE id = :id");
            $up->execute([':r' => $newRole, ':id' => $id]);
            logAct($pdo, $adminId, "Changed role of user #$id from $oldRole to $newRole");
            out(true, "Role changed to $newRole.");

        default:
            out(false, 'Unknown action.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    out(false, 'Database error.');
}