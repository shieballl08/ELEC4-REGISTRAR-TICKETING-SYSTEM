<?php
// ============================================
// USER HEADER - UNIFIED FOR ALL STUDENT PAGES
// ============================================

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);

// ============================================
// BASE PATH DETECTION
// ============================================
$is_in_modules  = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false);
$base_path      = $is_in_modules ? '' : 'modules/';
$dashboard_path = $is_in_modules ? '../User_Dashboard.php' : 'User_Dashboard.php';
$assets_path    = $is_in_modules ? '../../images/' : '../images/';

// ============================================
// PROFILE PICTURE PATH
// ============================================
// Kailangan ng absolute URL from browser's perspective
// If in modules/ → ../../uploads/profile_pictures/
// If outside (main/) → ../uploads/profile_pictures/
$uploads_path = $is_in_modules ? '../../uploads/profile_pictures/' : '../uploads/profile_pictures/';

// User data from session
$user_fullname  = $_SESSION['full_name'] ?? 'User';
$user_email     = $_SESSION['email'] ?? 'user@umak.edu.ph';
$user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;

// Initials
$nameParts = explode(' ', $user_fullname);
if (count($nameParts) >= 2) {
    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $user_initials = strtoupper(substr($user_fullname, 0, 2));
}

// Profile picture
$user_picture = $_SESSION['profile_picture'] ?? null;
$user_picture_url = $user_picture ? $uploads_path . $user_picture : '';
?>

<!-- ========== HEADER FONT (Modern Sans-Serif) ========== -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- ========== HEADER CSS ========== -->
<style>
    .header {
        background: var(--primary, #003366);
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .header-inner {
        max-width: 100%;
        padding: 0 1.5rem;
        height: 72px;
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .header .brand {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        flex-shrink: 0;
    }
    .header .brand-logo {
        width: 44px;
        height: 44px;
        object-fit: contain;
        flex-shrink: 0;
        display: block;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    }
    .header .brand-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .header .brand-name {
        font-family: 'Inter', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        color: #ffffff;
        line-height: 1;
        letter-spacing: -0.02em;
    }
    .header .brand-sub {
        font-family: 'Inter', sans-serif;
        font-size: 0.6rem;
        color: rgba(255,255,255,0.55);
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        line-height: 1;
    }
    .header-nav-links {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        flex: 1;
    }
    .header-nav-link {
        font-family: 'Inter', sans-serif;
        color: rgba(255,255,255,0.75);
        padding: 0.55rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
        background: transparent;
        border: none;
        cursor: pointer;
        letter-spacing: -0.005em;
    }
    .header-nav-link:hover {
        color: #ffffff;
        background: rgba(255,215,0,0.12);
    }
    .header-nav-link.active {
        color: #ffffff;
        background: rgba(255,215,0,0.18);
        font-weight: 700;
    }
    .header-nav-divider {
        width: 1px;
        height: 24px;
        background: rgba(255,255,255,0.15);
        margin: 0 0.5rem;
        flex-shrink: 0;
    }
    .header .nav-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .header .icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: none;
        background: transparent;
        cursor: pointer;
        color: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
        position: relative;
        text-decoration: none;
    }
    .header .icon-btn:hover {
        background: rgba(255,215,0,0.15);
        color: #ffffff;
    }
    .header .icon-btn svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
    }
    .header .notif-dot {
        position: absolute;
        top: 9px;
        right: 9px;
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        border: 2px solid var(--primary, #003366);
    }
    .header .avatar-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255,215,0,0.12);
        border: 1px solid rgba(255,215,0,0.25);
        border-radius: 10px;
        padding: 5px 12px 5px 5px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .header .avatar-btn:hover { 
        background: rgba(255,215,0,0.22); 
        border-color: rgba(255,215,0,0.4);
    }
    .header .avatar-circle {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: var(--secondary, #FFD700);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Inter', sans-serif;
        font-size: 0.72rem;
        font-weight: 800;
        color: var(--primary, #003366);
        letter-spacing: -0.02em;
        overflow: hidden;
        flex-shrink: 0;
    }
    .header .avatar-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .header .avatar-name {
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        color: rgba(255,255,255,0.95);
        font-weight: 600;
        letter-spacing: -0.005em;
        text-transform: uppercase;
    }
    .header .nav-dropdown { position: relative; display: inline-block; }
    .header .dropdown-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 10px);
        width: 260px;
        background: #ffffff;
        border: 1px solid var(--border, rgba(0,51,102,0.10));
        border-radius: 10px;
        box-shadow: 0 8px 32px rgba(0,51,102,0.12);
        z-index: 200;
        display: none;
        overflow: hidden;
        font-family: 'Inter', sans-serif;
    }
    .header .dropdown-menu.open { display: block; animation: headerDropIn 0.15s ease; }
    @keyframes headerDropIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .header .dd-header {
        padding: 14px 16px 12px;
        border-bottom: 1px solid var(--border, rgba(0,51,102,0.10));
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .header .dd-avatar {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--secondary, #FFD700);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Inter', sans-serif;
        font-size: 1rem;
        font-weight: 800;
        color: var(--primary, #003366);
        letter-spacing: -0.02em;
        overflow: hidden;
        flex-shrink: 0;
    }
    .header .dd-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .header .dd-info { min-width: 0; flex: 1; }
    .header .dd-name {
        font-family: 'Inter', sans-serif;
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--text-1, #1a2a3a);
        letter-spacing: -0.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .header .dd-email {
        font-family: 'Inter', sans-serif;
        font-size: 0.75rem;
        color: var(--text-3, #8a9aaa);
        margin-top: 2px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .header .dd-section { padding: 6px 6px; }
    .header .dd-label {
        font-family: 'Inter', sans-serif;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-3, #8a9aaa);
        padding: 6px 10px 4px;
    }
    .header .dd-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 10px;
        border-radius: 6px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--text-2, #4a5a6a);
        cursor: pointer;
        transition: background 0.12s;
        text-decoration: none;
        letter-spacing: -0.005em;
    }
    .header .dd-item:hover { background: var(--surface-2, #f8f7f4); }
    .header .dd-item svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        opacity: 0.75;
    }
    .header .dd-item.danger { color: #dc2626; }
    .header .dd-item.danger:hover { background: #fef2f2; }
    .header .dd-divider {
        height: 1px;
        background: var(--border, rgba(0,51,102,0.10));
        margin: 4px 8px;
    }
    @media (max-width: 768px) {
        .header-inner { padding: 0 1rem; gap: 0.75rem; height: 68px; }
        .header-nav-links { display: none; }
        .header .brand-sub { display: none; }
        .header .avatar-name { display: none; }
        .header .avatar-btn { padding: 4px; }
        .header .brand-logo { width: 38px; height: 38px; }
    }
</style>

<header class="header">
    <div class="header-inner">
        <!-- Brand -->
        <a href="<?php echo $dashboard_path; ?>" class="brand">
            <img src="<?php echo $assets_path; ?>registrar_logo.png"
                 alt="University of Makati Registrar Logo"
                 class="brand-logo">
            <div class="brand-text">
                <span class="brand-name">UMaK<span style="color:#FFD700;">·</span>Registrar</span>
                <span class="brand-sub">University of Makati</span>
            </div>
        </a>

        <!-- Main Navigation -->
        <nav class="header-nav-links">
            <a href="<?php echo $dashboard_path; ?>"
               class="header-nav-link <?php echo ($current_page == 'User_Dashboard.php') ? 'active' : ''; ?>">
                Home
            </a>

            <span class="header-nav-divider"></span>

            <a href="<?php echo $base_path; ?>academic_records.php"
               class="header-nav-link <?php echo ($current_page == 'academic_records.php') ? 'active' : ''; ?>">
                Academic Records
            </a>

            <a href="<?php echo $base_path; ?>certifications.php"
               class="header-nav-link <?php echo ($current_page == 'certifications.php') ? 'active' : ''; ?>">
                Certifications
            </a>

            <a href="<?php echo $base_path; ?>clearance.php"
               class="header-nav-link <?php echo ($current_page == 'clearance.php') ? 'active' : ''; ?>">
                Clearances
            </a>

            <span class="header-nav-divider"></span>

            <a href="<?php echo $base_path; ?>help_center.php"
               class="header-nav-link <?php echo ($current_page == 'help_center.php') ? 'active' : ''; ?>">
                Help
            </a>
        </nav>

        <!-- Right Actions -->
        <div class="nav-actions">
            <!-- Notifications -->
            <a href="<?php echo $base_path; ?>notifications.php" class="icon-btn" aria-label="Notifications">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="notif-dot"></span>
            </a>

            <!-- Profile Dropdown -->
            <div class="nav-dropdown" id="profileWrap">
                <button class="avatar-btn" onclick="toggleDropdown()">
                    <span class="avatar-circle">
                        <?php if (!empty($user_picture_url)): ?>
                            <img src="<?php echo htmlspecialchars($user_picture_url); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo $user_initials; ?>
                        <?php endif; ?>
                    </span>
                    <span class="avatar-name"><?php echo htmlspecialchars($user_firstname); ?></span>
                </button>

                <div class="dropdown-menu" id="profileDropdown">
                    <div class="dd-header">
                        <div class="dd-avatar">
                            <?php if (!empty($user_picture_url)): ?>
                                <img src="<?php echo htmlspecialchars($user_picture_url); ?>" alt="Profile">
                            <?php else: ?>
                                <?php echo $user_initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="dd-info">
                            <div class="dd-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                            <div class="dd-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                    </div>
                    <div class="dd-section">
                        <div class="dd-label">Account</div>
                        <a class="dd-item" href="<?php echo $base_path; ?>profile_settings.php">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Profile Settings
                        </a>
                        <a class="dd-item" href="#" onclick="openModal('preferences'); return false;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Preferences
                        </a>
                        <a class="dd-item" href="<?php echo $base_path; ?>billing.php">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Billing &amp; Payment
                        </a>
                    </div>
                    <div class="dd-divider"></div>
                    <div class="dd-section">
                        <a class="dd-item danger" href="#" onclick="doSignOut(event); return false;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleDropdown() {
    document.getElementById('profileDropdown').classList.toggle('open');
}
function closeDropdown() {
    const el = document.getElementById('profileDropdown');
    if (el) el.classList.remove('open');
}

document.addEventListener('click', function(e) {
    const profileWrap = document.getElementById('profileWrap');
    if (profileWrap && !profileWrap.contains(e.target)) {
        closeDropdown();
    }
});

function doSignOut(e) {
    if (e) e.preventDefault();
    if (confirm('Are you sure you want to sign out?')) {
        var currentPath = window.location.pathname;
        var isInModules = currentPath.includes('/modules/');
        var logoutPath = isInModules ? '../../includes/logout.php' : '../includes/logout.php';
        window.location.href = logoutPath;
    }
}
</script>