<?php
// ============================================
// THIS IS THE SINGLE SOURCE OF YOUR SIDE NAVIGATION
// EDIT ONLY THIS FILE, ALL PAGES WILL UPDATE AUTOMATICALLY
// ============================================

// Get the current filename to determine which link is active
$current_page = basename($_SERVER['PHP_SELF']);

// Determine the correct path prefix for links
$base_path = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) ? '' : 'modules/';

// Determine the correct path for LOGOUT
$logout_path = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) ? '../../includes/logout.php' : '../includes/logout.php';

// ============================================
// MOCK DATA — Palitan mo ito ng totoong data galing DB
// ============================================
$notif_count = 3;  // Ilan ang unread notifications
?>

<!-- ========== SIDE NAV OVERLAY ========== -->
<div class="nav-overlay" id="sideNavOverlay" onclick="closeSideNav()"></div>

<!-- ========== SIDE NAV ========== -->
<div class="side-nav" id="sideNav">
    <div class="side-nav-header">
        <a href="<?php echo $base_path; ?>User_Dashboard.php" class="side-nav-brand">UMak<span>·Registrar</span></a>
        <button class="side-nav-close" onclick="closeSideNav()">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="side-nav-body">

        <!-- ========== DASHBOARD (Pinned sa taas) ========== -->
        <a href="<?php echo $base_path; ?>User_Dashboard.php" 
           class="side-nav-link <?php echo ($current_page == 'User_Dashboard.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <!-- ========== CORE (Priority) ========== -->
        <a href="<?php echo $base_path; ?>requests.php" 
           class="side-nav-link side-nav-link-primary <?php echo ($current_page == 'requests.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            My Requests
        </a>

        <a href="#" 
           class="side-nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" 
           onclick="handleSideNavAction('My Profile')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            My Profile
        </a>

        <a href="<?php echo $base_path; ?>billing.php" 
           class="side-nav-link <?php echo ($current_page == 'billing.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Billing &amp; Payment
        </a>

        <!-- ========== SECTION: INFORMATION ========== -->
        <div class="side-nav-section-label">Information</div>

        <a href="<?php echo $base_path; ?>notifications.php" 
           class="side-nav-link <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Notifications
            <?php if ($notif_count > 0): ?>
                <span class="side-nav-badge"><?php echo $notif_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="<?php echo $base_path; ?>announcements.php" 
           class="side-nav-link <?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
            Announcements
        </a>

        <a href="<?php echo $base_path; ?>calendar.php" 
           class="side-nav-link <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Academic Calendar
        </a>

        <!-- ========== SECTION: SUPPORT ========== -->
        <div class="side-nav-section-label">Support</div>

        <a href="<?php echo $base_path; ?>help_center.php" 
           class="side-nav-link <?php echo ($current_page == 'help_center.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Help Center / FAQs
        </a>

        <a href="<?php echo $base_path; ?>contact_registrar.php" 
           class="side-nav-link <?php echo ($current_page == 'contact_registrar.php') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Contact Registrar
        </a>

        <!-- ========== SECTION: SETTINGS ========== -->
        <div class="side-nav-section-label">Settings</div>

        <div class="side-nav-dark-toggle" onclick="toggleDarkModeFromSideNav()">
            <span style="display:flex;align-items:center;gap:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
                Dark Mode
            </span>
            <span class="toggle-track">
                <span class="toggle-thumb"></span>
            </span>
        </div>

        <!-- ========== SIGN OUT ========== -->
        <a href="#" class="side-nav-link side-nav-link-danger" onclick="doSignOut(event)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Sign Out
        </a>

    </div>
    <div class="side-nav-footer">&copy; 2026 UMak · Registrar</div>
</div>

<!-- ========================================== -->
<!-- UNIVERSAL SIGN OUT FUNCTION -->
<!-- ========================================== -->
<script>
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