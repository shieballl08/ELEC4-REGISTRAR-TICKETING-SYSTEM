<?php
// modules/billing.php - SIMPLIFIED VERSION (NO SERVICE MODAL POPUPS)
// ============================================
// ENABLE ERROR REPORTING FOR DEBUGGING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// START SESSION - MUST BE FIRST!
// ============================================
session_start();

// ============================================
// CHECK IF USER IS LOGGED IN
// ============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../Front_Page.php');
    exit();
}

// ============================================
// GET USER DATA FROM SESSION
// ============================================
$user_id = $_SESSION['user_id'] ?? 1;
$user_fullname = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? 'user@umak.edu.ph';
$user_role = $_SESSION['role'] ?? 'student';

// Get first name for welcome message
$user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;

// ============================================
// GET INITIALS FOR AVATAR
// ============================================
$nameParts = explode(' ', $user_fullname);
$user_initials = '';
if (count($nameParts) >= 2) {
    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $user_initials = strtoupper(substr($user_fullname, 0, 2));
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>UMak · Registrar Portal · Billing</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <!-- Google Fonts: Noto Sans Osage -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Osage&display=swap" rel="stylesheet">
    
    <style>
        /* ========== VISUAL DESIGN ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Noto Sans Osage', sans-serif;
            background: #f8f7f4;
            color: #1a2a3a;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.dark-mode {
            background: #0d1117;
            color: #e6edf3;
        }

        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode h3,
        body.dark-mode h4,
        body.dark-mode h5,
        body.dark-mode h6,
        body.dark-mode p,
        body.dark-mode span,
        body.dark-mode div,
        body.dark-mode a,
        body.dark-mode label,
        body.dark-mode .card-title,
        body.dark-mode .section-title,
        body.dark-mode .dl-label,
        body.dark-mode .dl-sub,
        body.dark-mode .act-text,
        body.dark-mode .act-time,
        body.dark-mode .ann-title,
        body.dark-mode .ann-body,
        body.dark-mode .ann-by,
        body.dark-mode .ann-time,
        body.dark-mode .link-sm,
        body.dark-mode .offcanvas-header h3,
        body.dark-mode .offcanvas-section-title,
        body.dark-mode .dd-name,
        body.dark-mode .dd-email,
        body.dark-mode .dd-item,
        body.dark-mode .dd-label,
        body.dark-mode .status-row span,
        body.dark-mode .modal-title,
        body.dark-mode .brand-sub,
        body.dark-mode footer p,
        body.dark-mode .service-name,
        body.dark-mode .service-desc {
            color: #e6edf3 !important;
        }

        body.dark-mode .brand-name {
            color: #ffffff !important;
        }

        body.dark-mode .text-1,
        body.dark-mode .text-2,
        body.dark-mode .text-3 {
            color: #e6edf3 !important;
        }

        body.dark-mode .header {
            background: #161b22;
            border-bottom-color: #ffd700;
        }

        body.dark-mode .card {
            background: #161b22;
            border-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .side-nav {
            background: #0d1117;
            border-right-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .side-nav-header {
            background: #0d1117;
            border-bottom-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .side-nav-brand {
            color: #ffffff !important;
        }

        body.dark-mode .side-nav-link {
            color: #b0b0c8 !important;
        }

        body.dark-mode .side-nav-link:hover {
            background: #3a3a55;
            color: #e6edf3 !important;
        }

        body.dark-mode .side-nav-link.active {
            background: rgba(255,215,0,0.12);
            color: #ffd700 !important;
        }

        body.dark-mode .side-nav-close {
            background: #3a3a55;
            color: #b0b0c8;
        }

        body.dark-mode .side-nav-footer {
            border-top-color: rgba(255,255,255,0.08);
            color: #8a8aaa !important;
        }

        body.dark-mode .offcanvas {
            background: #0d1117;
            border-color: rgba(255,215,0,0.15);
        }

        body.dark-mode .offcanvas-header {
            background: #0d1117;
            border-bottom-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .offcanvas-close {
            background: #3a3a55;
            color: #b0b0c8;
        }

        body.dark-mode .dropdown-menu {
            background: #0d1117;
            border-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .dd-header {
            border-bottom-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .dd-item:hover {
            background: #3a3a55;
        }

        body.dark-mode .status-row:hover {
            background: #3a3a55;
        }

        body.dark-mode .modal {
            background: #0d1117;
            border-color: rgba(255,215,0,0.15);
        }

        body.dark-mode .modal-close {
            background: #3a3a55;
            color: #b0b0c8;
        }

        body.dark-mode .modal-close:hover {
            background: #4a4a6a;
            color: #e6edf3;
        }

        body.dark-mode .btn-primary {
            background: #1a4d7a;
            color: #ffffff !important;
        }

        body.dark-mode .btn-primary:hover {
            background: #2a6a9a;
        }

        body.dark-mode .fab {
            background: #1a4d7a;
            border-color: #ffd700;
        }

        body.dark-mode .fab:hover {
            background: #2a6a9a;
        }

        body.dark-mode .nav-overlay {
            background: rgba(0,0,0,0.6);
        }

        body.dark-mode .offcanvas-overlay {
            background: rgba(0,0,0,0.6);
        }

        body.dark-mode .modal-backdrop {
            background: rgba(0,0,0,0.6);
        }

        body.dark-mode footer {
            border-top-color: rgba(255,255,255,0.08) !important;
        }

        body.dark-mode .avatar-btn {
            background: rgba(255,215,0,0.08);
            border-color: rgba(255,215,0,0.12);
        }

        body.dark-mode .avatar-btn:hover {
            background: rgba(255,215,0,0.15);
        }

        body.dark-mode .avatar-circle {
            background: #ffd700;
            color: #0d1117 !important;
        }

        body.dark-mode .avatar-name {
            color: #e6edf3 !important;
        }

        body.dark-mode .icon-btn {
            color: rgba(255,255,255,0.6);
        }

        body.dark-mode .icon-btn:hover {
            color: #fff;
            background: rgba(255,215,0,0.12);
        }

        body.dark-mode .hamburger-btn {
            color: rgba(255,255,255,0.8);
        }

        body.dark-mode .hamburger-btn:hover {
            background: rgba(255,215,0,0.12);
        }

        body.dark-mode .hero-greeting h1 {
            color: #ffffff !important;
        }

        body.dark-mode .hero-greeting .sub {
            color: rgba(255,215,0,0.8) !important;
        }

        body.dark-mode .hero-greeting .desc {
            color: rgba(255,255,255,0.6) !important;
        }

        body.dark-mode .id-pill {
            background: rgba(255,215,0,0.12);
            border-color: rgba(255,215,0,0.15);
            color: rgba(255,215,0,0.8) !important;
        }

        body.dark-mode .college-pill {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.7) !important;
        }

        body.dark-mode .hero-overlay {
            background: linear-gradient(105deg, rgba(13,27,42,0.95) 0%, rgba(13,27,42,0.8) 55%, rgba(13,27,42,0.6) 100%);
            border-color: rgba(255,215,0,0.15);
        }

        body.dark-mode .dl-urgent {
            background: rgba(220,38,38,0.2);
            color: #ef4444 !important;
        }

        body.dark-mode .dl-warn {
            background: rgba(133,79,11,0.2);
            color: #f59e0b !important;
        }

        body.dark-mode .dl-ok {
            background: rgba(5,150,105,0.2);
            color: #34d399 !important;
        }

        body.dark-mode .ann-priority.urgent {
            background: rgba(220,38,38,0.2);
            color: #ef4444 !important;
        }

        body.dark-mode .ann-priority.normal {
            background: rgba(255,215,0,0.15);
            color: #ffd700 !important;
        }

        body.dark-mode .ann-priority.info {
            background: rgba(5,150,105,0.2);
            color: #34d399 !important;
        }

        body.dark-mode .ann-icon {
            background: #3a3a55;
            color: #b0b0c8 !important;
        }

        body.dark-mode .service-card {
            background: #161b22;
            border-color: rgba(255,255,255,0.08);
        }

        body.dark-mode .service-card:hover {
            border-color: #ffd700;
        }

        body.dark-mode .service-name {
            color: #ffd700 !important;
        }

        body.dark-mode .service-desc {
            color: #b0b0c8 !important;
        }

        body.dark-mode .card-body a[style*="background:var(--surface-2)"] {
            background: #3a3a55 !important;
            color: #b0b0c8 !important;
        }

        body.dark-mode .card-body a[style*="background:var(--surface-2)"]:hover {
            background: rgba(255,215,0,0.15) !important;
        }

        body.dark-mode .offcanvas-section div[style*="background:var(--surface-2)"] {
            background: #3a3a55 !important;
        }

        body.dark-mode .offcanvas-section div[style*="background:var(--surface-2)"] div {
            color: #e6edf3 !important;
        }

        body.dark-mode .offcanvas-section div[style*="background:var(--surface-2)"] div:last-child {
            color: #8a8aaa !important;
        }

        body.dark-mode .notif-dot {
            border-color: #161b22;
        }

        body.dark-mode .dd-item.danger {
            color: #ef4444 !important;
        }

        body.dark-mode .dd-item.danger:hover {
            background: rgba(220,38,38,0.15);
        }

        body.dark-mode .side-nav-divider {
            background: rgba(255,255,255,0.08);
        }

        body.dark-mode .dd-divider {
            background: rgba(255,255,255,0.08);
        }

        body.dark-mode .service-modal {
            background: #0d1117;
            border-color: rgba(255,215,0,0.15);
        }

        body.dark-mode .service-modal-title {
            color: #e6edf3 !important;
        }

        body.dark-mode .service-modal-close {
            background: #3a3a55;
            color: #b0b0c8;
        }

        body.dark-mode .service-modal-close:hover {
            background: #4a4a6a;
            color: #e6edf3;
        }

        body.dark-mode .service-item {
            color: #b0b0c8 !important;
            border-color: transparent;
        }

        body.dark-mode .service-item:hover {
            background: #3a3a55 !important;
            border-color: rgba(255,255,255,0.08);
            color: #e6edf3 !important;
        }

        body.dark-mode .service-divider {
            background: rgba(255,255,255,0.08);
        }

        body.dark-mode .service-modal-backdrop {
            background: rgba(0,0,0,0.6);
        }

        body.dark-mode .service-item .desc {
            color: #8a8aaa !important;
        }

        body.dark-mode .bill-status.paid {
            background: rgba(5,150,105,0.2);
            color: #34d399 !important;
        }
        body.dark-mode .bill-status.unpaid {
            background: rgba(220,38,38,0.2);
            color: #ef4444 !important;
        }
        body.dark-mode .bill-status.partial {
            background: rgba(184,134,11,0.2);
            color: #f5b342 !important;
        }
        body.dark-mode .stat-box {
            background: #161b22;
            border-color: rgba(255,255,255,0.08);
        }
        body.dark-mode .stat-box .stat-value {
            color: #e6edf3 !important;
        }
        body.dark-mode .stat-box .stat-label {
            color: #8a8aaa !important;
        }

        :root {
            --primary: #003366;
            --primary-dark: #002244;
            --primary-light: #1a4d7a;
            --secondary: #FFD700;
            --secondary-light: #FFE44D;
            --secondary-dark: #E6C200;
            --surface: #ffffff;
            --surface-2: #f8f7f4;
            --surface-3: #f0eee9;
            --border: rgba(0,51,102,0.10);
            --border-md: rgba(0,51,102,0.18);
            --text-1: #1a2a3a;
            --text-2: #4a5a6a;
            --text-3: #8a9aaa;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 22px;
            --shadow-sm: 0 1px 3px rgba(0,51,102,0.06), 0 1px 2px rgba(0,51,102,0.04);
            --shadow-md: 0 4px 16px rgba(0,51,102,0.08), 0 1px 4px rgba(0,51,102,0.05);
            --shadow-lg: 0 8px 32px rgba(0,51,102,0.12);
        }

        * {
            font-family: 'Noto Sans Osage', sans-serif;
        }

        h1, h2, h3, h4, h5, h6, .section-title, .brand-name, .nav-link, .btn, .stat-value {
            font-family: 'Noto Sans Osage', sans-serif;
            letter-spacing: 0.01em;
        }

        /* ========== HEADER ========== */
        .header {
            background: var(--primary);
            position: sticky; top: 0; z-index: 100;
            transition: background 0.3s ease;
            width: 100%;
            padding: 0;
        }
        .header-inner {
            max-width: 100%;
            margin: 0;
            padding: 0 0.75rem;
            height: 68px;
            display: flex; align-items: center; gap: 1rem;
        }
        .brand { display: flex; flex-direction: column; gap: 1px; min-width: 140px; text-decoration: none; }
        .brand-name { font-family: 'Noto Sans Osage', sans-serif; font-size: 1.2rem; font-weight: 700; color: #fff; letter-spacing: 0.01em; line-height: 1; }
        .brand-sub  { font-size: 0.55rem; color: rgba(255,255,255,0.5); font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; }

        .hamburger-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.85);
            cursor: pointer;
            padding: 4px;
            margin-right: 2px;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
        }
        .hamburger-btn:hover {
            background: rgba(255,215,0,0.12);
        }
        .hamburger-btn svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            stroke-width: 2.2;
            fill: none;
        }

        .header-nav-links {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            margin-left: auto;
            flex-wrap: nowrap;
        }

        .nav-dropdown {
            position: relative;
            display: inline-block;
        }

        .header-nav-link {
            color: rgba(255,255,255,0.6);
            padding: 0.35rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
            background: transparent !important;
            border: none !important;
            cursor: pointer;
            font-family: 'Noto Sans Osage', sans-serif;
            font-weight: 400;
            letter-spacing: 0.02em;
        }
        .header-nav-link:hover {
            color: rgba(255,255,255,0.95);
            background: rgba(255,215,0,0.08) !important;
        }
        .header-nav-link.active {
            color: #ffffff !important;
            background: rgba(255,215,0,0.12) !important;
            font-weight: 500;
        }

        .dropdown-popup {
            position: absolute;
            top: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%) scale(0.95) translateY(-8px);
            min-width: 200px;
            background: #ffffff;
            border-radius: 0px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12), 0 4px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.06);
            padding: 0.5rem 0;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), visibility 0.2s ease;
            z-index: 120;
        }

        .nav-dropdown:hover .dropdown-popup {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) scale(1) translateY(0);
            pointer-events: auto;
        }

        body.dark-mode .dropdown-popup {
            background: #1c2128;
            border-color: rgba(255,255,255,0.08);
            box-shadow: 0 12px 40px rgba(0,0,0,0.5);
        }

        .dropdown-popup .popup-item {
            display: block;
            padding: 0.6rem 1.2rem;
            font-size: 0.75rem;
            color: #1a2a3a;
            text-decoration: none;
            transition: background 0.15s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            font-family: 'Noto Sans Osage', sans-serif;
            font-weight: 400;
            letter-spacing: 0.01em;
        }

        body.dark-mode .dropdown-popup .popup-item {
            color: #e6edf3;
        }

        .dropdown-popup .popup-item:hover {
            background: rgba(0,51,102,0.06);
        }

        body.dark-mode .dropdown-popup .popup-item:hover {
            background: rgba(255,215,0,0.08);
        }

        .dropdown-popup .popup-item .item-desc {
            display: block;
            font-size: 0.6rem;
            color: #8a9aaa;
            font-weight: 400;
            margin-top: 1px;
        }

        body.dark-mode .dropdown-popup .popup-item .item-desc {
            color: #8a8aaa;
        }

        .dropdown-popup .popup-divider {
            height: 1px;
            background: rgba(0,0,0,0.06);
            margin: 0.25rem 0.5rem;
        }

        body.dark-mode .dropdown-popup .popup-divider {
            background: rgba(255,255,255,0.06);
        }

        .dropdown-popup::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-bottom-color: #ffffff;
        }

        body.dark-mode .dropdown-popup::before {
            border-bottom-color: #1c2128;
        }

        .side-nav {
            position: fixed;
            top: 0;
            left: -320px;
            width: 300px;
            max-width: 80vw;
            height: 100vh;
            background: var(--surface);
            box-shadow: 4px 0 24px rgba(0,0,0,0.06);
            z-index: 300;
            transition: left 0.28s cubic-bezier(0.2, 0.9, 0.4, 1.1), background 0.3s ease;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            overflow-y: auto;
        }
        .side-nav.open {
            left: 0;
        }
        .side-nav-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.25rem 0.75rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .side-nav-brand {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        .side-nav-brand span {
            color: var(--secondary-dark);
        }
        .side-nav-close {
            background: var(--surface-3);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 0px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-2);
            transition: background 0.15s;
        }
        .side-nav-close:hover {
            background: var(--surface-2);
            color: var(--text-1);
        }
        .side-nav-close svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2.5;
            fill: none;
        }
        .side-nav-body {
            padding: 0.75rem 1rem 1rem;
            flex: 1;
        }
        .side-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 0.9rem;
            border-radius: 0px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-2);
            text-decoration: none;
            transition: background 0.12s, color 0.12s;
            margin-bottom: 2px;
            cursor: pointer;
        }
        .side-nav-link:hover {
            background: var(--surface-2);
            color: var(--text-1);
        }
        .side-nav-link.active {
            background: rgba(0,51,102,0.06);
            color: var(--primary);
            font-weight: 600;
        }
        .side-nav-link svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            flex-shrink: 0;
        }
        .side-nav-divider {
            height: 1px;
            background: var(--border);
            margin: 0.5rem 0.5rem;
        }
        .side-nav-footer {
            padding: 0.75rem 1rem 1.5rem;
            border-top: 1px solid var(--border);
            font-size: 0.75rem;
            color: var(--text-3);
        }

        .nav-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10,20,40,0.35);
            backdrop-filter: blur(2px);
            z-index: 299;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .nav-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
            margin-left: 0.5rem;
        }
        .icon-btn {
            width: 36px; height: 36px;
            border-radius: 0px;
            border: none; background: transparent; cursor: pointer;
            color: rgba(255,255,255,0.7);
            display: flex; align-items: center; justify-content: center;
            transition: color 0.15s, background 0.15s; position: relative;
        }
        .icon-btn:hover { color: #fff; background: rgba(255,215,0,0.15); }
        .icon-btn svg { width: 18px; height: 18px; }
        .notif-dot {
            position: absolute; top: 5px; right: 5px;
            width: 7px; height: 7px;
            background: #ef4444; border-radius: 50%;
            border: 1.5px solid var(--primary);
        }

        .avatar-btn {
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,215,0,0.12);
            border: 1px solid rgba(255,215,0,0.2);
            border-radius: 0px;
            padding: 3px 8px 3px 3px;
            cursor: pointer; transition: background 0.15s;
        }
        .avatar-btn:hover { background: rgba(255,215,0,0.2); }
        .avatar-circle {
            width: 26px; height: 26px; border-radius: 0px;
            background: var(--secondary);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Noto Sans Osage', sans-serif;
            font-size: 0.7rem; font-weight: 700;
            color: var(--primary);
        }
        .avatar-name { font-size: 0.75rem; color: rgba(255,255,255,0.85); font-weight: 400; }

        .page {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.75rem 1.5rem 3rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0px;
            box-shadow: var(--shadow-sm);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.125rem 1.375rem 0;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Noto Sans Osage', sans-serif;
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-1);
            transition: color 0.3s ease;
        }
        .card-body { padding: 1rem 1.375rem 1.375rem; }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 16px;
            border-radius: 0px;
            background: var(--primary); color: #fff;
            font-family: 'Noto Sans Osage', sans-serif;
            font-size: 0.8125rem; font-weight: 600;
            border: none; cursor: pointer; text-decoration: none;
            transition: background 0.15s;
        }
        .btn-primary:hover { background: var(--primary-light); }

        .btn-success {
            background: #16a34a;
            color: #fff;
        }
        .btn-success:hover { background: #15803d; }

        .section-gap { margin-bottom: 1.5rem; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .section-title {
            font-family: 'Noto Sans Osage', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-1);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .fade-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.2, 0.9, 0.4, 1), transform 0.8s cubic-bezier(0.2, 0.9, 0.4, 1);
        }
        .fade-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px);
            width: 260px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0px;
            box-shadow: var(--shadow-lg);
            z-index: 200;
            display: none;
            overflow: hidden;
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .dropdown-menu.open { display: block; animation: dropIn 0.14s ease; }
        @keyframes dropIn { from { opacity:0; transform: translateY(-6px); } to { opacity:1; transform: translateY(0); } }
        .dd-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); transition: border-color 0.3s ease; }
        .dd-name { font-size: 0.875rem; font-weight: 600; color: var(--text-1); transition: color 0.3s ease; }
        .dd-email { font-size: 0.75rem; color: var(--text-3); margin-top: 2px; transition: color 0.3s ease; }
        .dd-section { padding: 8px 8px; }
        .dd-label { font-size: 0.625rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-3); padding: 4px 8px 6px; transition: color 0.3s ease; }
        .dd-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px;
            border-radius: 0px;
            font-size: 0.8125rem; color: var(--text-2); cursor: pointer;
            transition: background 0.12s, color 0.3s ease;
        }
        .dd-item:hover { background: var(--surface-2); }
        .dd-item.danger { color: #dc2626; }
        .dd-item.danger:hover { background: #fef2f2; }
        .dd-divider { height: 1px; background: var(--border); margin: 4px 8px; transition: background 0.3s ease; }

        .offcanvas {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.92);
            width: 460px;
            max-width: 90vw;
            max-height: 80vh;
            background: var(--surface);
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            z-index: 250;
            border-radius: 0px;
            transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1), 
                        opacity 0.3s ease,
                        background 0.3s ease,
                        box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            border: 2px solid rgba(255,215,0,0.15);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .offcanvas.open {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .offcanvas-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            transition: background 0.3s ease, border-color 0.3s ease;
            flex-shrink: 0;
            border-radius: 0px 0px 0 0;
        }
        .offcanvas-header h3 {
            font-family: 'Noto Sans Osage', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-1);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }
        .offcanvas-close {
            width: 30px;
            height: 30px;
            border-radius: 0px;
            border: none;
            background: var(--surface-3);
            color: var(--text-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, color 0.3s ease;
        }
        .offcanvas-close:hover {
            background: var(--surface-2);
            color: var(--text-1);
        }
        .offcanvas-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 1.5rem;
        }
        .offcanvas-section {
            margin-bottom: 1.5rem;
        }
        .offcanvas-section:last-child {
            margin-bottom: 0;
        }
        .offcanvas-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-3);
            margin-bottom: 0.75rem;
            transition: color 0.3s ease;
        }
        .offcanvas-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10,20,40,0.5);
            backdrop-filter: blur(4px);
            z-index: 249;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease, background 0.3s ease;
        }
        .offcanvas-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
            font-size: 0.85rem;
        }
        .profile-info-grid .info-label {
            color: var(--text-3);
            font-weight: 400;
        }
        .profile-info-grid .info-value {
            color: var(--text-1);
            font-weight: 500;
            word-break: break-word;
        }
        .profile-header-card {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 0.75rem 0 1.25rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.25rem;
        }
        .profile-header-card .profile-avatar-large {
            width: 64px;
            height: 64px;
            border-radius: 0px;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            flex-shrink: 0;
        }
        .profile-header-card .profile-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-1);
        }
        .profile-header-card .profile-role {
            font-size: 0.8rem;
            color: var(--text-3);
        }

        .fab {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 56px;
            height: 56px;
            border-radius: 0px;
            background: var(--primary);
            border: 2px solid var(--secondary);
            box-shadow: 0 6px 18px rgba(0,51,102,0.25);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease, background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            z-index: 150;
        }
        .fab:hover {
            transform: scale(1.05);
            background: var(--primary-light);
            box-shadow: 0 8px 24px rgba(0,51,102,0.35);
        }
        .fab svg {
            width: 26px;
            height: 26px;
            stroke: #fff;
            stroke-width: 2;
            fill: none;
        }

        .side-nav-dark-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.9rem;
            border-radius: 0px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-2);
            transition: background 0.12s, color 0.12s;
            margin-bottom: 2px;
            cursor: pointer;
        }
        .side-nav-dark-toggle:hover {
            background: var(--surface-2);
            color: var(--text-1);
        }
        .side-nav-dark-toggle .toggle-track {
            width: 40px;
            height: 22px;
            background: #ccc;
            border-radius: 12px;
            position: relative;
            transition: background 0.3s ease;
            flex-shrink: 0;
        }
        .side-nav-dark-toggle .toggle-track .toggle-thumb {
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        body.dark-mode .side-nav-dark-toggle .toggle-track {
            background: #ffd700;
        }
        body.dark-mode .side-nav-dark-toggle .toggle-track .toggle-thumb {
            transform: translateX(18px);
            background: #0d1117;
        }
        body.dark-mode .side-nav-dark-toggle {
            color: #e6edf3;
        }
        body.dark-mode .side-nav-dark-toggle:hover {
            background: #3a3a55;
        }

        /* Billing Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-3);
        }
        .empty-state svg {
            margin: 0 auto 1rem;
            display: block;
            color: var(--text-3);
            opacity: 0.5;
        }
        .empty-state p {
            font-size: 0.95rem;
        }

        @media(max-width:768px){
            .offcanvas { width: 85vw; max-height: 85vh; }
            .header-nav-links { gap: 0.05rem; }
            .header-nav-link { font-size: 0.6rem; padding: 0.25rem 0.4rem; }
            .profile-info-grid { grid-template-columns: 1fr; gap: 0.5rem; }
            .profile-header-card { flex-direction: column; text-align: center; }
        }
        @media(max-width:480px){
            .header-nav-links { display: none !important; }
            .page { padding: 1rem; }
            .offcanvas { width: 92vw; max-height: 90vh; }
        }
    </style>
    <link rel="stylesheet" href="../../includes/side_nav.css">
</head>
<body>

<!-- ========== SIDE NAV ========== -->
<?php include '../../includes/side_nav.php'; ?>

<!-- ========== HEADER ========== -->
<?php include '../../includes/user_header.php'; ?>

<!-- ========== OFF-CANVAS OVERLAY ========== -->
<div class="offcanvas-overlay" id="offcanvasOverlay" onclick="closeOffcanvas()"></div>

<!-- ========== OFF-CANVAS PANEL ========== -->
<div class="offcanvas" id="offcanvasPanel">
    <div class="offcanvas-header">
        <h3><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> My Profile</h3>
        <button class="offcanvas-close" onclick="closeOffcanvas()">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="offcanvas-body">
        <div class="profile-header-card">
            <div class="profile-avatar-large"><?php echo $user_initials; ?></div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($user_role); ?></div>
            </div>
        </div>
        <div class="offcanvas-section">
            <div class="offcanvas-section-title">Student Information</div>
            <div class="profile-info-grid">
                <span class="info-label">Student ID</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['student_id'] ?? '2026-XXXXX'); ?></span>
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($user_fullname); ?></span>
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
                <span class="info-label">College</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['college'] ?? 'College of Information Technology'); ?></span>
                <span class="info-label">Course</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['course'] ?? 'BS Information Technology'); ?></span>
                <span class="info-label">Year Level</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['year_level'] ?? '3rd Year'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<main class="page">
    <div class="section-header">
        <h2 class="section-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Billing &amp; Payment History
        </h2>
    </div>

    <!-- Empty State - No Billing Records -->
    <div class="card fade-scroll">
        <div class="card-body">
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p>No billing records found.</p>
                <p style="font-size:0.85rem;color:var(--text-3);margin-top:0.5rem;">Your billing history will appear here once you have transactions.</p>
            </div>
        </div>
    </div>
</main>

<footer style="border-top:1px solid var(--border);padding:1.25rem;text-align:center;transition:border-color 0.3s ease;margin-top:1.5rem;">
    <p style="font-size:.75rem;color:var(--text-3);transition:color 0.3s ease;">
        &copy; 2026 University of Makati · Registrar's Office · All rights reserved.
    </p>
</footer>

<button class="fab" onclick="openOffcanvas()" aria-label="Open profile panel">
    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
</button>

<script>
// ========== DARK MODE ==========
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
}

const savedDarkMode = localStorage.getItem('darkMode');
if (savedDarkMode === 'true') {
    document.body.classList.add('dark-mode');
}

function toggleDarkModeFromSideNav() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
}

// ========== SIDE NAV ==========
function openSideNav() {
    document.getElementById('sideNav').classList.add('open');
    document.getElementById('sideNavOverlay').classList.add('open');
    closeDropdown();
}
function closeSideNav() {
    document.getElementById('sideNav').classList.remove('open');
    document.getElementById('sideNavOverlay').classList.remove('open');
}

// ========== DROPDOWN ==========
function toggleDropdown() {
    document.getElementById('profileDropdown').classList.toggle('open');
}
function closeDropdown() {
    document.getElementById('profileDropdown').classList.remove('open');
}
document.addEventListener('click', function(e) {
    if (!document.getElementById('profileWrap').contains(e.target)) closeDropdown();
});

// ========== OFF-CANVAS ==========
function openOffcanvas() {
    document.getElementById('offcanvasPanel').classList.add('open');
    document.getElementById('offcanvasOverlay').classList.add('open');
    closeDropdown();
}
function closeOffcanvas() {
    document.getElementById('offcanvasPanel').classList.remove('open');
    document.getElementById('offcanvasOverlay').classList.remove('open');
}

// ========== SIGN OUT ==========
function doSignOut() {
    if (confirm('Are you sure you want to sign out?')) {
        window.location.href = '../../includes/logout.php';  // 2 LEVELS UP!
    }
}

// ========== FADE SCROLL REVEAL ==========
var fadeObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.15, rootMargin: "0px 0px -30px 0px" });

document.querySelectorAll('.fade-scroll').forEach(function(el) { fadeObserver.observe(el); });

// ========== HEADER NAV ACTIVE STATE ==========
document.querySelectorAll('.header-nav-link').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.header-nav-link').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
    });
});
</script>
</body>
</html>