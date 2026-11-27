<?php
// ============================================
// PROFILE SETTINGS - EDIT PROFILE DETAILS
// PostgreSQL + PDO version + Profile Picture Upload
// ============================================

// ---------- SESSION ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['email'])) {
    header('Location: ../../login.php');
    exit();
}

// ---------- DB CONNECTION ----------
require_once __DIR__ . '/../../config/db_connect_pg.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die('Database connection failed. Please check config/db_connect_pg.php');
}

$user_id = $_SESSION['user_id'] ?? 0;

// Load profile_picture sa session (para sa header)
if (!array_key_exists('profile_picture', $_SESSION)) {
    try {
        $stmt_pp = $conn->prepare("SELECT profile_picture FROM users WHERE id = :id");
        $stmt_pp->execute([':id' => $user_id]);
        $row_pp = $stmt_pp->fetch();
        $_SESSION['profile_picture'] = $row_pp['profile_picture'] ?? null;
    } catch (PDOException $e) {
        $_SESSION['profile_picture'] = null;
    }
}

// KUNG WALA PA SA SESSION, I-LOAD FROM DB (para sa header)
if (!isset($_SESSION['profile_picture'])) {
    try {
        $stmt_pp = $conn->prepare("SELECT profile_picture FROM users WHERE id = :id");
        $stmt_pp->execute([':id' => $user_id]);
        $row_pp = $stmt_pp->fetch();
        $_SESSION['profile_picture'] = $row_pp['profile_picture'] ?? null;
    } catch (PDOException $e) {
        $_SESSION['profile_picture'] = null;
    }
}

// ============================================
// UPLOAD DIRECTORY
// ============================================
$upload_dir_fs  = __DIR__ . '/../../uploads/profile_pictures/';   // filesystem path
$upload_dir_url = '../../uploads/profile_pictures/';              // URL path (relative from modules/)

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- UPDATE PROFILE DETAILS ----------
    if (($_POST['action'] ?? '') === 'update_profile') {
        $full_name       = trim($_POST['full_name'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $mobile          = trim($_POST['mobile'] ?? '');
        $birthdate       = trim($_POST['birthdate'] ?? '');
        $student_id      = trim($_POST['student_id'] ?? '');
        $program         = trim($_POST['program'] ?? '');
        $college         = trim($_POST['college'] ?? '');
        $course          = trim($_POST['course'] ?? '');
        $year_level      = trim($_POST['year_level'] ?? '');
        $graduation_year = trim($_POST['graduation_year'] ?? '');

        if ($full_name === '' || $email === '') {
            $error_msg = 'Full name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Please enter a valid email address.';
        } else {
            try {
                $sql = "UPDATE users SET
                            full_name       = :full_name,
                            email           = :email,
                            mobile          = :mobile,
                            birthdate       = :birthdate,
                            student_id      = :student_id,
                            program         = :program,
                            college         = :college,
                            course          = :course,
                            year_level      = :year_level,
                            graduation_year = :graduation_year,
                            updated_at      = NOW()
                        WHERE id = :id";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':full_name'       => $full_name,
                    ':email'           => $email,
                    ':mobile'          => $mobile ?: null,
                    ':birthdate'       => $birthdate ?: null,
                    ':student_id'      => $student_id ?: null,
                    ':program'         => $program ?: null,
                    ':college'         => $college ?: null,
                    ':course'          => $course ?: null,
                    ':year_level'      => $year_level ?: null,
                    ':graduation_year' => $graduation_year !== '' ? (int)$graduation_year : null,
                    ':id'              => $user_id,
                ]);

                $_SESSION['full_name'] = $full_name;
                $_SESSION['email']     = $email;

                $success_msg = 'Profile updated successfully.';
            } catch (PDOException $e) {
                error_log('Profile update failed: ' . $e->getMessage());
                $error_msg = 'Failed to update profile. ' . $e->getMessage();
            }
        }
    }

    // ---------- UPLOAD PROFILE PICTURE ----------
    if (($_POST['action'] ?? '') === 'upload_picture') {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'Please select a valid image to upload.';
        } else {
            $file     = $_FILES['profile_picture'];
            $max_size = 2 * 1024 * 1024; // 2MB

            // Check size
            if ($file['size'] > $max_size) {
                $error_msg = 'Image is too large. Maximum size is 2MB.';
            } else {
                // Check mime type
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $finfo   = finfo_open(FILEINFO_MIME_TYPE);
                $mime    = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!isset($allowed[$mime])) {
                    $error_msg = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
                } else {
                    // Make sure upload folder exists
                    if (!is_dir($upload_dir_fs)) {
                        mkdir($upload_dir_fs, 0755, true);
                    }

                    // Generate unique filename
                    $ext      = $allowed[$mime];
                    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $dest     = $upload_dir_fs . $filename;

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        try {
                            // Get old picture to delete
                            $old = $conn->prepare("SELECT profile_picture FROM users WHERE id = :id");
                            $old->execute([':id' => $user_id]);
                            $oldRow = $old->fetch();

                            // Delete old file if exists
                            if (!empty($oldRow['profile_picture']) && file_exists($upload_dir_fs . $oldRow['profile_picture'])) {
                                @unlink($upload_dir_fs . $oldRow['profile_picture']);
                            }

                            // Update DB
                            $upd = $conn->prepare("UPDATE users SET profile_picture = :p, updated_at = NOW() WHERE id = :id");
                            $upd->execute([':p' => $filename, ':id' => $user_id]);

                            $_SESSION['profile_picture'] = $filename;
                        } catch (PDOException $e) {
                            error_log('Picture update failed: ' . $e->getMessage());
                            $error_msg = 'Failed to save picture. ' . $e->getMessage();
                            @unlink($dest);
                        }
                    } else {
                        $error_msg = 'Failed to upload image. Please try again.';
                    }
                }
            }
        }
    }

    // ---------- REMOVE PROFILE PICTURE ----------
    if (($_POST['action'] ?? '') === 'remove_picture') {
        try {
            $old = $conn->prepare("SELECT profile_picture FROM users WHERE id = :id");
            $old->execute([':id' => $user_id]);
            $oldRow = $old->fetch();

            if (!empty($oldRow['profile_picture']) && file_exists($upload_dir_fs . $oldRow['profile_picture'])) {
                @unlink($upload_dir_fs . $oldRow['profile_picture']);
            }

            $upd = $conn->prepare("UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE id = :id");
            $upd->execute([':id' => $user_id]);

            $_SESSION['profile_picture'] = null;
        } catch (PDOException $e) {
            error_log('Picture remove failed: ' . $e->getMessage());
            $error_msg = 'Failed to remove picture.';
        }
    }

    // ---------- CHANGE PASSWORD ----------
    if (($_POST['action'] ?? '') === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $error_msg = 'Please fill in all password fields.';
        } elseif ($new !== $confirm) {
            $error_msg = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $error_msg = 'New password must be at least 8 characters.';
        } else {
            try {
                $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :id");
                $stmt->execute([':id' => $user_id]);
                $row = $stmt->fetch();

                if ($row && password_verify($current, $row['password_hash'])) {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE users SET password_hash = :p, updated_at = NOW() WHERE id = :id");
                    $upd->execute([':p' => $hashed, ':id' => $user_id]);
                    $success_msg = 'Password changed successfully.';
                } else {
                    $error_msg = 'Current password is incorrect.';
                }
            } catch (PDOException $e) {
                error_log('Password change failed: ' . $e->getMessage());
                $error_msg = 'Failed to change password.';
            }
        }
    }
}

// ============================================
// FETCH USER DATA
// ============================================
try {
    $stmt = $conn->prepare("SELECT
        full_name, email, mobile, birthdate, student_id,
        program, college, course, year_level, graduation_year,
        username, profile_picture, created_at, last_login
        FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Fetch user failed: ' . $e->getMessage());
    $user = null;
}

if (!$user) {
    $user = [
        'full_name'       => $_SESSION['full_name'] ?? 'User',
        'email'           => $_SESSION['email'] ?? '',
        'mobile'          => '',
        'birthdate'       => '',
        'student_id'      => '',
        'program'         => '',
        'college'         => '',
        'course'          => '',
        'year_level'      => '',
        'graduation_year' => '',
        'username'        => '',
        'profile_picture' => null,
        'created_at'      => null,
        'last_login'      => null,
    ];
}

// Check if user has profile picture
$has_picture = !empty($user['profile_picture']) && file_exists($upload_dir_fs . $user['profile_picture']);
$picture_url = $has_picture ? $upload_dir_url . $user['profile_picture'] : '';

// ============================================
// INCLUDE HEADER
// ============================================
include __DIR__ . '/../../includes/user_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile Settings · UMaK Registrar</title>

<style>
    :root {
        --primary:    #003366;
        --secondary:  #FFD700;
        --text-1:     #1a2a3a;
        --text-2:     #4a5a6a;
        --text-3:     #8a9aaa;
        --border:     rgba(0,51,102,0.10);
        --surface-1:  #ffffff;
        --surface-2:  #f8f7f4;
        --surface-3:  #f2f1ee;
        --radius:     14px;
        --shadow:     0 2px 12px rgba(0,51,102,0.06);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--surface-2);
        color: var(--text-1);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }
    .ps-wrap {
        max-width: 1100px;
        margin: 0 auto;
        padding: 2.5rem 1.5rem 4rem;
    }
    .ps-head { margin-bottom: 2rem; }
    .ps-eyebrow {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--secondary);
        background: var(--primary);
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        margin-bottom: 0.75rem;
    }
    .ps-title {
        font-size: 1.85rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        color: var(--primary);
    }
    .ps-sub {
        font-size: 0.9rem;
        color: var(--text-3);
        margin-top: 0.35rem;
        font-weight: 500;
    }
    .ps-grid {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    .ps-side {
        background: var(--surface-1);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem;
        box-shadow: var(--shadow);
        position: sticky;
        top: 92px;
    }
    .ps-avatar-block {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1rem;
    }
    .ps-avatar {
        width: 84px;
        height: 84px;
        border-radius: 20px;
        background: var(--primary);
        color: var(--secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 0.85rem;
        box-shadow: 0 4px 14px rgba(0,51,102,0.18);
        overflow: hidden;
        position: relative;
        background-size: cover;
        background-position: center;
    }
    .ps-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .ps-avatar-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-1);
        line-height: 1.3;
    }
    .ps-avatar-email {
        font-size: 0.75rem;
        color: var(--text-3);
        margin-top: 3px;
        word-break: break-all;
        font-weight: 500;
    }

    /* Picture upload controls */
    .ps-pic-actions {
        display: flex;
        gap: 6px;
        margin-top: 10px;
        width: 100%;
    }
    .ps-pic-btn {
        flex: 1;
        padding: 0.5rem 0.6rem;
        border-radius: 7px;
        font-size: 0.72rem;
        font-weight: 700;
        font-family: inherit;
        border: none;
        cursor: pointer;
        transition: all 0.15s;
        letter-spacing: -0.005em;
    }
    .ps-pic-btn.upload {
        background: var(--primary);
        color: #fff;
    }
    .ps-pic-btn.upload:hover { background: #004488; }
    .ps-pic-btn.remove {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .ps-pic-btn.remove:hover { background: #fee2e2; }
    .ps-pic-hint {
        font-size: 0.65rem;
        color: var(--text-3);
        margin-top: 6px;
        text-align: center;
        font-weight: 500;
    }

    .ps-nav {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .ps-nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0.7rem 0.85rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-2);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        font-family: inherit;
    }
    .ps-nav-item:hover {
        background: var(--surface-3);
        color: var(--primary);
    }
    .ps-nav-item.active {
        background: var(--primary);
        color: #fff;
    }
    .ps-nav-item.active svg { stroke: var(--secondary); }
    .ps-nav-item svg { width: 17px; height: 17px; flex-shrink: 0; }
    .ps-main {
        background: var(--surface-1);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    .ps-panel { display: none; padding: 2rem; }
    .ps-panel.active { display: block; animation: psFadeIn 0.25s ease; }
    @keyframes psFadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .ps-panel-head {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }
    .ps-panel-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--primary);
        letter-spacing: -0.015em;
    }
    .ps-panel-desc {
        font-size: 0.82rem;
        color: var(--text-3);
        margin-top: 4px;
        font-weight: 500;
    }
    .ps-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .ps-field { display: flex; flex-direction: column; gap: 6px; }
    .ps-field.full { grid-column: 1 / -1; }
    .ps-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-2);
    }
    .ps-input, .ps-select {
        width: 100%;
        padding: 0.72rem 0.9rem;
        border: 1.5px solid var(--border);
        border-radius: 9px;
        font-size: 0.88rem;
        font-family: inherit;
        color: var(--text-1);
        background: var(--surface-2);
        transition: all 0.15s;
        font-weight: 500;
    }
    .ps-input:focus, .ps-select:focus {
        outline: none;
        border-color: var(--primary);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,51,102,0.08);
    }
    .ps-input:disabled { opacity: 0.6; cursor: not-allowed; }
    .ps-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 1.75rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--border);
    }
    .ps-btn {
        padding: 0.7rem 1.5rem;
        border-radius: 9px;
        font-size: 0.85rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        letter-spacing: -0.005em;
    }
    .ps-btn-primary { background: var(--primary); color: #fff; }
    .ps-btn-primary:hover {
        background: #004488;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,51,102,0.2);
    }
    .ps-btn-ghost { background: var(--surface-3); color: var(--text-2); }
    .ps-btn-ghost:hover { background: #e8e6e2; }
    .ps-alert {
        padding: 0.85rem 1rem;
        border-radius: 9px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ps-alert.success {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .ps-alert.error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .ps-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    .ps-info-item {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 1rem 1.1rem;
    }
    .ps-info-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-3);
        margin-bottom: 5px;
    }
    .ps-info-value {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--text-1);
        word-break: break-word;
    }
    @media (max-width: 820px) {
        .ps-grid { grid-template-columns: 1fr; }
        .ps-side { position: static; }
        .ps-form-row { grid-template-columns: 1fr; }
        .ps-info-grid { grid-template-columns: 1fr; }
        .ps-wrap { padding: 1.5rem 1rem 3rem; }
        .ps-title { font-size: 1.5rem; }
    }
</style>
</head>
<body>

<div class="ps-wrap">

    <div class="ps-head">
        <span class="ps-eyebrow">Account</span>
        <h1 class="ps-title">Profile Settings</h1>
        <p class="ps-sub">Manage your personal information and account security.</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="ps-alert success">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ps-alert error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
            </svg>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="ps-grid">

        <aside class="ps-side">
            <div class="ps-avatar-block">
                <!-- PROFILE PICTURE / INITIALS -->
                <div class="ps-avatar">
                    <?php if ($has_picture): ?>
                        <img src="<?php echo htmlspecialchars($picture_url); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php
                            $parts = explode(' ', trim($user['full_name']));
                            if (count($parts) >= 2) {
                                echo strtoupper(substr($parts[0],0,1) . substr($parts[1],0,1));
                            } else {
                                echo strtoupper(substr($user['full_name'],0,2));
                            }
                        ?>
                    <?php endif; ?>
                </div>

                <!-- UPLOAD / REMOVE BUTTONS -->
                <form method="POST" action="" enctype="multipart/form-data" id="picForm" style="width:100%;">
                    <input type="hidden" name="action" value="upload_picture">
                    <input type="file" name="profile_picture" id="picInput" accept="image/*" style="display:none;" onchange="document.getElementById('picForm').submit();">
                    <div class="ps-pic-actions">
                        <button type="button" class="ps-pic-btn upload" onclick="document.getElementById('picInput').click();">
                            <?php echo $has_picture ? 'Change Photo' : 'Upload Photo'; ?>
                        </button>
                        <?php if ($has_picture): ?>
                            <button type="button" class="ps-pic-btn remove" onclick="removePicture();">Remove</button>
                        <?php endif; ?>
                    </div>
                    <div class="ps-pic-hint">JPG, PNG, GIF, or WEBP · Max 2MB</div>
                </form>

                <div class="ps-avatar-name" style="margin-top:0.85rem;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="ps-avatar-email"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>

            <nav class="ps-nav">
                <button class="ps-nav-item active" onclick="showPanel('personal', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Personal Info
                </button>
                <button class="ps-nav-item" onclick="showPanel('password', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                    Password
                </button>
                <button class="ps-nav-item" onclick="showPanel('account', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 16v-4M12 8h.01"/>
                    </svg>
                    Account Info
                </button>
            </nav>
        </aside>

        <main class="ps-main">

            <!-- PERSONAL INFO -->
            <section id="panel-personal" class="ps-panel active">
                <div class="ps-panel-head">
                    <h2 class="ps-panel-title">Personal Information</h2>
                    <p class="ps-panel-desc">Update your personal details below.</p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="ps-form-row">
                        <div class="ps-field full">
                            <label class="ps-label">Full Name</label>
                            <input type="text" name="full_name" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                    </div>

                    <div class="ps-form-row">
                        <div class="ps-field">
                            <label class="ps-label">Email Address</label>
                            <input type="email" name="email" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="ps-field">
                            <label class="ps-label">Mobile Number</label>
                            <input type="text" name="mobile" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>"
                                   placeholder="+63 9XX XXX XXXX">
                        </div>
                    </div>

                    <div class="ps-form-row">
                        <div class="ps-field">
                            <label class="ps-label">Student ID</label>
                            <input type="text" name="student_id" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['student_id'] ?? ''); ?>"
                                   placeholder="e.g. 2023-00123">
                        </div>
                        <div class="ps-field">
                            <label class="ps-label">Birthdate</label>
                            <input type="date" name="birthdate" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="ps-form-row">
                        <div class="ps-field">
                            <label class="ps-label">College / Institute</label>
                            <select name="college" id="collegeSelect" class="ps-select" onchange="updateCourses()">
                                <?php
                                    $colleges = [
                                        '',
                                        'College of Business Administration (CBA)',
                                        'Institute of Accountancy (IOA)',
                                        'College of Computing and Information Sciences (CCIS)',
                                        'College of Human Kinetics (CHK)',
                                        'College of Governance and Public Policy (CGPP)',
                                        'Institute of Nursing (ION)',
                                        'Institute of Pharmacy (IOP)',
                                        'Institute of Imaging Health Sciences (IIHS)',
                                        'College of Innovative Teacher Education (CITE)',
                                        'College of Science (COS)',
                                        'College of Tourism and Hospitality Management (CTHM)',
                                        'College of Engineering and Technology (CET)',
                                        'Institute of Arts and Design (IAD)',
                                        'Institute for Social Development and Nation Building (ISDNB)',
                                        'Institute for Disaster and Emergency Management (IDEM)',
                                        'College of Continuing, Advanced and Professional Studies (CCAPS)',
                                        'School of Law (SOL)',
                                        'Higher School ng UMak (HSU)',
                                    ];
                                    $current_college = $user['college'] ?? '';
                                    foreach ($colleges as $c) {
                                        $sel = ($current_college === $c) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($c) . '" ' . $sel . '>' . ($c ?: '— Select College —') . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="ps-field">
                            <label class="ps-label">Program</label>
                            <input type="text" name="program" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['program'] ?? ''); ?>"
                                   placeholder="e.g. BS Computer Science">
                        </div>
                    </div>

                    <div class="ps-form-row">
                        <div class="ps-field">
                            <label class="ps-label">Course / Program</label>
                            <select name="course" id="courseSelect" class="ps-select">
                                <option value="">— Select College First —</option>
                            </select>
                        </div>
                        <div class="ps-field">
                            <label class="ps-label">Year Level</label>
                            <select name="year_level" class="ps-select">
                                <?php
                                    $years = ['', '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
                                    foreach ($years as $y) {
                                        $sel = (($user['year_level'] ?? '') === $y) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($y) . '" ' . $sel . '>' . ($y ?: '— Select —') . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="ps-form-row">
                        <div class="ps-field">
                            <label class="ps-label">Graduation Year</label>
                            <input type="number" name="graduation_year" class="ps-input"
                                   value="<?php echo htmlspecialchars($user['graduation_year'] ?? ''); ?>"
                                   min="1990" max="2100" placeholder="e.g. 2026">
                        </div>
                    </div>

                    <div class="ps-actions">
                        <button type="reset" class="ps-btn ps-btn-ghost">Reset</button>
                        <button type="submit" class="ps-btn ps-btn-primary">Save Changes</button>
                    </div>
                </form>
            </section>

            <!-- PASSWORD -->
            <section id="panel-password" class="ps-panel">
                <div class="ps-panel-head">
                    <h2 class="ps-panel-title">Change Password</h2>
                    <p class="ps-panel-desc">Use a strong password with at least 8 characters.</p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="ps-form-row">
                        <div class="ps-field full">
                            <label class="ps-label">Current Password</label>
                            <input type="password" name="current_password" class="ps-input" required>
                        </div>
                    </div>

                    <div class="ps-form-row">
                        <div class="ps-field">
                            <label class="ps-label">New Password</label>
                            <input type="password" name="new_password" class="ps-input" minlength="8" required>
                        </div>
                        <div class="ps-field">
                            <label class="ps-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="ps-input" minlength="8" required>
                        </div>
                    </div>

                    <div class="ps-actions">
                        <button type="reset" class="ps-btn ps-btn-ghost">Clear</button>
                        <button type="submit" class="ps-btn ps-btn-primary">Update Password</button>
                    </div>
                </form>
            </section>

            <!-- ACCOUNT INFO -->
            <section id="panel-account" class="ps-panel">
                <div class="ps-panel-head">
                    <h2 class="ps-panel-title">Account Information</h2>
                    <p class="ps-panel-desc">Overview of your account details.</p>
                </div>

                <div class="ps-info-grid">
                    <div class="ps-info-item">
                        <div class="ps-info-label">Username</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['username'] ?? '—'); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Student ID</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['student_id'] ?? '—'); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Full Name</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Email</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Mobile</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['mobile'] ?? '—'); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">College</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['college'] ?? '—'); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Program</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['program'] ?? '—'); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Year Level</div>
                        <div class="ps-info-value"><?php echo htmlspecialchars($user['year_level'] ?? '—'); ?></div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Member Since</div>
                        <div class="ps-info-value">
                            <?php echo !empty($user['created_at'])
                                ? date('F j, Y', strtotime($user['created_at']))
                                : '—'; ?>
                        </div>
                    </div>
                    <div class="ps-info-item">
                        <div class="ps-info-label">Last Login</div>
                        <div class="ps-info-value">
                            <?php echo !empty($user['last_login'])
                                ? date('F j, Y g:i A', strtotime($user['last_login']))
                                : '—'; ?>
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>
</div>

<!-- Hidden form for removing picture -->
<form method="POST" action="" id="removePicForm" style="display:none;">
    <input type="hidden" name="action" value="remove_picture">
</form>

<script>
// ============================================
// TAB SWITCHING
// ============================================
function showPanel(name, btn) {
    document.querySelectorAll('.ps-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ps-nav-item').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + name).classList.add('active');
    btn.classList.add('active');
}

// ============================================
// REMOVE PROFILE PICTURE
// ============================================
function removePicture() {
    if (confirm('Remove your profile picture? It will revert to your initials.')) {
        document.getElementById('removePicForm').submit();
    }
}

// ============================================
// DEPENDENT COLLEGE → COURSE DROPDOWN
// ============================================
const courseMap = {
    'College of Business Administration (CBA)': [
        'BS in Office Administration',
        'BS in Entrepreneurial Management',
        'BS in Business Administration major in Marketing Management',
        'BS in Business Administration major in Human Resource Development Management',
        'BS in Business Administration major in Supply Management',
        'BS in Business Administration major in Building and Property Management',
        'Associate in Sales Management',
        'Associate in Office Management Technology',
        'Associate in Entrepreneurship',
        'Associate in Supply Management',
        'Associate in Building and Property Management',
    ],
    'Institute of Accountancy (IOA)': [
        'BS in Accountancy (BSA)',
        'BS in Management Accounting (BSMA)',
    ],
    'College of Computing and Information Sciences (CCIS)': [
        'BS in Computer Science (Application Development Track)',
        'BS in Information Technology (Information and Network Security Track)',
        'Diploma in Application Development',
        'Diploma in Computer Network Administration',
    ],
    'College of Human Kinetics (CHK)': [
        'BS in Exercise and Sports Science major in Fitness and Sports Management',
    ],
    'College of Governance and Public Policy (CGPP)': [
        'BA in Political Science major in Paralegal Studies',
        'BA in Political Science major in Policy Management',
        'BA in Political Science major in Local Government Administration',
    ],
    'Institute of Nursing (ION)': [
        'BS in Nursing',
        'Master of Arts in Nursing',
    ],
    'Institute of Pharmacy (IOP)': [
        'BS in Pharmacy',
        'Associate in Applied Science in Pharmacy Technology',
    ],
    'Institute of Imaging Health Sciences (IIHS)': [
        'BS in Radiologic Technology',
        'MS in Radiologic Technology',
    ],
    'College of Innovative Teacher Education (CITE)': [
        'Bachelor of Elementary Education',
        'Bachelor of Secondary Education major in English',
        'Bachelor of Secondary Education major in Mathematics',
        'Bachelor of Secondary Education major in Social Studies',
    ],
    'College of Science (COS)': [
        'BS in Psychology',
    ],
    'College of Tourism and Hospitality Management (CTHM)': [
        'BS in Hospitality Management',
        'BS in Tourism Management',
        'Associate in Hospitality Management',
    ],
    'College of Engineering and Technology (CET)': [
        'BS in Civil Engineering (Construction Engineering and Management)',
        'BS in Engineering Technology major in Electrical Technology',
        'BS in Engineering Technology major in Electronics Technology',
        'Diploma in Electrical Technology',
        'Diploma in Industrial Facilities Technology',
        'Diploma in Industrial Facilities Technology major in Service Mechanics',
        'Associate in Electronics Technology',
    ],
    'Institute of Arts and Design (IAD)': [
        'Bachelor in Multimedia Arts: Animation',
        'Bachelor in Multimedia Arts: Film Production',
        'Associate in Customer Service Communication',
    ],
    'Institute for Social Development and Nation Building (ISDNB)': [
        'BS in Social Work (BSSW)',
    ],
    'Institute for Disaster and Emergency Management (IDEM)': [
        'BS in Disaster Risk Management (BSDRM)',
    ],
    'College of Continuing, Advanced and Professional Studies (CCAPS)': [
        'BA in Political Science major in Local Government Administration',
        'Bachelor in Automotive Technology',
        'Bachelor in Industrial Facilities Technology Management',
        'BS in Business Administration major in Human Resource Development Management',
        'BS in Entrepreneurship',
        'Certificate in Barangay Governance',
        'Certificate in Katarungang Pambarangay and Alternative Dispute Resolution',
        'Diploma in Development Management and Governance',
        'Master of Arts in Education',
        'Master of Arts in Innovative Education',
        'Master of Arts in Special Education',
        'Master in Business Administration',
        'Master in Development Management and Governance',
        'Master in Public Administration',
        'Master of Science in Radiologic Technology',
        'Doctor of Education',
        'Doctor of Philosophy in Special Education',
        'Doctor of Philosophy in Leadership',
        'Doctor of Public Administration',
        'Executive Doctorate in Leadership',
    ],
    'School of Law (SOL)': [
        'Juris Doctor with Thesis',
    ],
    'Higher School ng UMak (HSU)': [
        'Academic Track - BEAM Cluster',
        'Academic Track - ASSH Cluster',
        'Academic Track - STEM Cluster',
        'Technical Professional Pathway (TechPro)',
    ],
};

const currentCourse = <?php echo json_encode($user['course'] ?? ''); ?>;

function updateCourses() {
    const collegeSel = document.getElementById('collegeSelect');
    const courseSel  = document.getElementById('courseSelect');
    const selectedCollege = collegeSel.value;
    const courses = courseMap[selectedCollege] || [];

    courseSel.innerHTML = '';

    if (courses.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '— Select College First —';
        courseSel.appendChild(opt);
        return;
    }

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '— Select Course —';
    courseSel.appendChild(placeholder);

    courses.forEach(function(c) {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        if (c === currentCourse) opt.selected = true;
        courseSel.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateCourses();
});
</script>

</body>
</html>