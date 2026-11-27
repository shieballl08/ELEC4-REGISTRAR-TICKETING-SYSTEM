<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: Front_Page.php');
    exit();
}

// ✅ TAMA NA ITO! Admin at Faculty lang ang pwedeng makapasok
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'faculty' && $_SESSION['role'] !== 'admin')) {
    header('Location: Front_Page.php');
    exit();
}

// Get user data from session
$user_id = $_SESSION['user_id'] ?? 1;
$user_fullname = $_SESSION['full_name'] ?? 'Faculty';
$user_email = $_SESSION['email'] ?? 'faculty@umak.edu.ph';
$user_role = $_SESSION['role'] ?? 'faculty';

// Get first name for welcome message
$user_firstname = explode(' ', trim($user_fullname))[0] ?? $user_fullname;

// Get initials for avatar
$nameParts = explode(' ', $user_fullname);
$user_initials = '';
if (count($nameParts) >= 2) {
    $user_initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $user_initials = strtoupper(substr($user_fullname, 0, 2));
}

// Faculty specific data - Will be fetched from database later
$faculty_classes = 0;
$total_students = 0;
$pending_grades = 0;
$attendance_today = '0%';
$faculty_college = '';
$faculty_department = '';
$faculty_employee_id = '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Faculty Dashboard - UMak Registrar</title>

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

        /* ===== DROPDOWN CONTAINER ===== */
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

        /* ===== DROPDOWN MENU - FULL POPUP LIKE EXAMPLE ===== */
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

        /* Triangle arrow pointing up to the button */
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
        /* ===== end dropdown ===== */

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

        .section-gap { margin-bottom: 1.5rem; }

        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .section-title { font-family: 'Noto Sans Osage', sans-serif; font-size: 1rem; font-weight: 600; color: var(--text-1); display: flex; align-items: center; gap: 8px; transition: color 0.3s ease; }

        .service-modal-backdrop {
            position: fixed; inset: 0; background: rgba(10,20,40,0.4);
            z-index: 500; display: none; align-items: center; justify-content: center;
            backdrop-filter: blur(3px);
            transition: background 0.3s ease;
        }
        .service-modal-backdrop.open { display: flex; }
        .service-modal {
            background: var(--surface);
            border-radius: 0px;
            box-shadow: var(--shadow-lg); width: 100%; max-width: 420px; max-height: 85vh; overflow: auto;
            animation: modalIn 0.2s ease;
            border: 2px solid rgba(255,215,0,0.2);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        @keyframes modalIn { from { opacity:0; transform: scale(0.94) translateY(-12px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .service-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem 0.5rem; }
        .service-modal-title { font-family: 'Noto Sans Osage', sans-serif; font-size: 1rem; font-weight: 700; color: var(--text-1); transition: color 0.3s ease; }
        .service-modal-close { width: 30px; height: 30px; border-radius: 0px; border: none; background: var(--surface-3); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-2); transition: background 0.15s, color 0.3s ease; }
        .service-modal-close:hover { background: var(--surface-2); color: var(--text-1); }
        .service-modal-close svg { width: 15px; height: 15px; }
        .service-modal-body { padding: 0.5rem 1.5rem 1.5rem; }
        .service-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px;
            border-radius: 0px;
            cursor: pointer; transition: background 0.15s, color 0.15s;
            color: var(--text-2); font-size: 0.85rem;
            border: 1px solid transparent;
            margin-bottom: 4px;
            font-family: 'Noto Sans Osage', sans-serif;
        }
        .service-item:hover { background: var(--surface-2); border-color: var(--border); color: var(--text-1); }
        .service-item .label { font-weight: 500; }
        .service-item .desc { font-size: 0.7rem; color: var(--text-3); margin-left: auto; }
        .service-divider { height: 1px; background: var(--border); margin: 6px 0; }

        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(10,20,40,0.4);
            z-index: 400; display: none; align-items: center; justify-content: center;
            transition: background 0.3s ease;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            background: var(--surface);
            border-radius: 0px;
            box-shadow: var(--shadow-lg); width: 100%; max-width: 440px; max-height: 90vh; overflow: auto;
            animation: modalIn 0.15s ease;
            border: 2px solid rgba(255,215,0,0.2);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        @keyframes modalIn { from { opacity:0; transform: scale(0.96) translateY(-8px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem 0; }
        .modal-title { font-family: 'Noto Sans Osage', sans-serif; font-size: 1rem; font-weight: 600; color: var(--text-1); transition: color 0.3s ease; }
        .modal-close { width: 30px; height: 30px; border-radius: 0px; border: none; background: var(--surface-3); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-2); transition: background 0.15s, color 0.3s ease; }
        .modal-close:hover { background: var(--surface-2); color: var(--text-1); }
        .modal-close svg { width: 15px; height: 15px; }
        .modal-body { padding: 1rem 1.5rem 1.5rem; }

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

        .status-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 0px; cursor: pointer; transition: background 0.12s; }
        .status-row:hover { background: var(--surface-2); }
        .status-row span { font-size: 0.8125rem; color: var(--text-2); transition: color 0.3s ease; }
        .status-row .check { margin-left: auto; font-size: 0.75rem; display: none; }
        .status-row.selected .check { display: inline; color: var(--secondary); }

        /* ===== OFF-CANVAS - CENTERED ===== */
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

        /* ===== MY PROFILE OFF-CANVAS STYLES ===== */
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
        .profile-info-grid .info-full {
            grid-column: 1 / -1;
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
        body.dark-mode .profile-header-card .profile-name {
            color: #e6edf3;
        }
        body.dark-mode .profile-header-card .profile-role {
            color: #8a8aaa;
        }
        body.dark-mode .profile-info-grid .info-value {
            color: #e6edf3;
        }
        body.dark-mode .profile-info-grid .info-label {
            color: #8a8aaa;
        }
        .profile-edit-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.5rem;
            padding: 0.5rem 1.25rem;
            border: 1px solid var(--border-md);
            border-radius: 0px;
            background: transparent;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-2);
            cursor: pointer;
            transition: all 0.15s;
            font-family: 'Noto Sans Osage', sans-serif;
        }
        .profile-edit-btn:hover {
            background: var(--surface-2);
            border-color: var(--text-3);
        }
        body.dark-mode .profile-edit-btn {
            color: #b0b0c8;
            border-color: rgba(255,255,255,0.12);
        }
        body.dark-mode .profile-edit-btn:hover {
            background: #3a3a55;
            border-color: rgba(255,255,255,0.2);
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

        .reveal,
        .reveal-right,
        .reveal-children > * {
            opacity: 0;
            transform: translateY(35px);
            transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1),
                        transform 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .reveal-right {
            transform: translateX(28px);
        }
        .reveal-children > * {
            transform: translateY(20px);
            transition: opacity 0.55s cubic-bezier(0.2, 0.9, 0.4, 1.1),
                        transform 0.55s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .reveal.visible,
        .reveal-right.visible,
        .reveal-children.visible > * {
            opacity: 1;
            transform: none;
        }

        /* Full width card */
        .full-width-card {
            width: 100%;
        }

        @media(max-width:1024px){
            .services-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media(max-width:768px){
            .services-grid { grid-template-columns: 1fr; }
            .hero-overlay { flex-direction: column; align-items: flex-start; }
            .offcanvas { width: 85vw; max-height: 85vh; }
            .hero-greeting h1 { font-size: 1.4rem; }
            .header-nav-links { gap: 0.05rem; }
            .header-nav-link { font-size: 0.6rem; padding: 0.25rem 0.4rem; }
            .dropdown-popup { min-width: 160px; }
            .dropdown-popup .popup-item { font-size: 0.65rem; padding: 0.4rem 0.8rem; }
            .profile-info-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            .profile-info-grid .info-full {
                grid-column: 1;
            }
            .profile-header-card {
                flex-direction: column;
                text-align: center;
            }
        }
        @media(max-width:480px){
            .services-grid { grid-template-columns: 1fr; }
            .hero-overlay { padding: 1.25rem; }
            .header-inner { padding: 0 0.5rem; }
            .page { padding: 1rem; }
            .header-nav-links { display: none !important; }
            .offcanvas { width: 92vw; max-height: 90vh; }
        }

        /* ===== DASHBOARD STYLES ===== */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }

        .service-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0px;
            padding: 1.25rem 1.25rem 1rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: var(--text-1);
            min-height: 130px;
        }
        .service-card:hover {
            border-color: var(--secondary-dark);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .service-card .service-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary);
            transition: color 0.3s ease;
            margin-bottom: 4px;
        }
        .service-card .service-desc {
            font-size: 0.75rem;
            color: var(--text-3);
            line-height: 1.5;
            flex: 1;
        }
        .service-card .arrow-indicator {
            font-size: 0.7rem;
            color: var(--text-3);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
            transition: color 0.2s;
            border-top: 1px solid var(--border);
            padding-top: 8px;
        }
        .service-card:hover .arrow-indicator {
            color: var(--primary);
        }

        .request-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 6px 0;
        }
        .request-item:last-child {
            border-bottom: none;
        }
        .request-item .req-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .request-item .req-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-1);
        }
        .request-item .req-date {
            font-size: 0.7rem;
            color: var(--text-3);
        }
        .request-item .req-status {
            font-size: 0.7rem;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 0px;
            background: var(--surface-3);
            color: var(--text-2);
            white-space: nowrap;
        }
        .request-item .req-status.status-pending {
            background: #fef9e7;
            color: #b8860b;
        }
        .request-item .req-status.status-processing {
            background: #e8f0fe;
            color: #1a4d7a;
        }
        .request-item .req-status.status-ready {
            background: #e6f7e6;
            color: #1e7e34;
        }
        .request-item .req-status.status-completed {
            background: #e6f7e6;
            color: #1e7e34;
        }
        .request-item .req-status.status-rejected {
            background: #fde8e8;
            color: #b91c1c;
        }
        body.dark-mode .request-item .req-status.status-pending {
            background: rgba(184,134,11,0.2);
            color: #f5b342;
        }
        body.dark-mode .request-item .req-status.status-processing {
            background: rgba(26,77,122,0.25);
            color: #7aa9d9;
        }
        body.dark-mode .request-item .req-status.status-ready {
            background: rgba(30,126,52,0.2);
            color: #5cb85c;
        }
        body.dark-mode .request-item .req-status.status-completed {
            background: rgba(30,126,52,0.2);
            color: #5cb85c;
        }
        body.dark-mode .request-item .req-status.status-rejected {
            background: rgba(185,28,28,0.2);
            color: #ef5350;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.55rem 0;
            border-bottom: 1px solid var(--border);
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .act-icon {
            width: 28px;
            height: 28px;
            border-radius: 0px;
            background: var(--surface-3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-3);
            flex-shrink: 0;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .activity-item .act-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        .activity-item .act-text {
            font-size: 0.8rem;
            color: var(--text-1);
        }
        .activity-item .act-time {
            font-size: 0.65rem;
            color: var(--text-3);
        }

        /* ===== HERO/WELCOME ===== */
        .hero-section {
            position: relative;
            left: 50%;
            right: 50%;
            margin-left: -50vw;
            margin-right: -50vw;
            width: 100vw;
            margin-top: -1.75rem;
            margin-bottom: 2.25rem;
            padding: 8rem 3rem;
            min-height: 500px;
            overflow: hidden;
            background: var(--surface);
            transition: background 0.3s ease;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('../images/dashboard_bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 1;
            transition: opacity 0.3s ease;
            z-index: 0;
            filter: brightness(0.9);
            transform: scale(1.02);
        }

        body.dark-mode .hero-section::before {
            opacity: 1;
            filter: brightness(0.65);
            transform: scale(1.02);
        }

        .hero-section::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.06);
            z-index: 0;
        }

        body.dark-mode .hero-section::after {
            background: rgba(0, 0, 0, 0.3);
        }

        .hero-section > * {
            position: relative;
            z-index: 1;
        }

        .hero-content {
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            padding: 0.5rem 0;
        }

        .hero-content h1 {
            font-size: 3rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.85rem;
            transition: color 0.3s ease;
            letter-spacing: 0.02em;
            text-shadow: 
                0 4px 20px rgba(0,0,0,0.8),
                0 2px 8px rgba(0,0,0,0.6),
                0 8px 32px rgba(0,0,0,0.5);
        }

        .hero-content h1 span {
            color: #FFD700;
            text-shadow: 
                0 4px 20px rgba(0,0,0,0.8),
                0 2px 8px rgba(0,0,0,0.6),
                0 0 40px rgba(255,215,0,0.2);
        }

        .hero-content .sub {
            font-size: 1.35rem;
            color: rgba(255,255,255,0.95);
            font-weight: 400;
            margin-bottom: 0.65rem;
            transition: color 0.3s ease;
            text-shadow: 
                0 2px 12px rgba(0,0,0,0.7),
                0 4px 24px rgba(0,0,0,0.4);
        }

        .hero-content .desc {
            font-size: 1.05rem;
            color: rgba(255,255,255,0.9);
            max-width: 720px;
            line-height: 1.8;
            margin: 0 auto;
            transition: color 0.3s ease;
            text-shadow: 
                0 2px 12px rgba(0,0,0,0.6),
                0 4px 24px rgba(0,0,0,0.3);
        }

        body.dark-mode .hero-content h1 {
            color: #ffffff;
            text-shadow: 
                0 4px 20px rgba(0,0,0,0.9),
                0 2px 8px rgba(0,0,0,0.7),
                0 8px 32px rgba(0,0,0,0.6);
        }

        body.dark-mode .hero-content h1 span {
            color: #FFD700;
            text-shadow: 
                0 4px 20px rgba(0,0,0,0.9),
                0 2px 8px rgba(0,0,0,0.7),
                0 0 40px rgba(255,215,0,0.25);
        }

        body.dark-mode .hero-content .sub {
            color: rgba(255,255,255,0.95);
            text-shadow: 
                0 2px 12px rgba(0,0,0,0.8),
                0 4px 24px rgba(0,0,0,0.5);
        }

        body.dark-mode .hero-content .desc {
            color: rgba(255,255,255,0.9);
            text-shadow: 
                0 2px 12px rgba(0,0,0,0.7),
                0 4px 24px rgba(0,0,0,0.4);
        }

        @media(max-width:768px) {
            .hero-section {
                padding: 4.5rem 1.5rem;
                min-height: 360px;
            }
            .hero-content h1 {
                font-size: 2.1rem;
            }
            .hero-content .sub {
                font-size: 1.1rem;
            }
            .hero-content .desc {
                font-size: 0.95rem;
            }
        }

        @media(max-width:480px) {
            .hero-section {
                padding: 3rem 1rem;
                min-height: 300px;
            }
            .hero-content h1 {
                font-size: 1.6rem;
            }
            .hero-content .sub {
                font-size: 1rem;
            }
            .hero-content .desc {
                font-size: 0.88rem;
            }
        }

        @media(max-width:1024px){
            .services-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media(max-width:640px){
            .services-grid { grid-template-columns: 1fr; }
        }

        /* ===== FADE ANIMATION ON SCROLL ===== */
        .fade-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.2, 0.9, 0.4, 1), transform 0.8s cubic-bezier(0.2, 0.9, 0.4, 1);
        }
        .fade-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== DARK MODE TOGGLE IN SIDE NAV ===== */
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

        /* ===== PROFILE SETTINGS MODAL ===== */
        .profile-modal .modal {
            max-width: 460px;
        }
        .profile-modal .modal-body {
            padding: 0.25rem 1.5rem 1.5rem;
        }
        .profile-avatar-section {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 0.75rem 0 0.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.25rem;
        }
        .profile-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            flex-shrink: 0;
        }
        .profile-avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .profile-avatar-actions .change-photo-btn {
            background: transparent;
            border: 1px solid var(--border-md);
            border-radius: var(--radius-sm);
            padding: 4px 12px;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-2);
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            font-family: 'Noto Sans Osage', sans-serif;
        }
        .profile-avatar-actions .change-photo-btn:hover {
            background: var(--surface-2);
            border-color: var(--text-3);
        }
        .profile-avatar-actions .helper-text {
            font-size: 0.65rem;
            color: var(--text-3);
        }
        .profile-form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 14px;
        }
        .profile-form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-2);
            letter-spacing: 0.02em;
        }
        .profile-form-group input,
        .profile-form-group select {
            padding: 8px 12px;
            border: 1px solid var(--border-md);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: 'Noto Sans Osage', sans-serif;
            background: var(--surface);
            color: var(--text-1);
            transition: border-color 0.15s, background 0.15s, color 0.15s;
            width: 100%;
        }
        .profile-form-group input:focus,
        .profile-form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,51,102,0.08);
        }
        .profile-form-group input:disabled,
        .profile-form-group select:disabled {
            background: var(--surface-2);
            color: var(--text-3);
            cursor: not-allowed;
        }
        body.dark-mode .profile-form-group input:disabled,
        body.dark-mode .profile-form-group select:disabled {
            background: #1c2128;
            color: #6a7a8a;
        }
        .profile-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .profile-footer {
            border-top: 1px solid var(--border);
            padding-top: 1.25rem;
            margin-top: 0.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .profile-footer .btn-secondary {
            background: transparent;
            border: 1px solid var(--border-md);
            border-radius: var(--radius-sm);
            padding: 7px 20px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-2);
            cursor: pointer;
            transition: all 0.15s;
            font-family: 'Noto Sans Osage', sans-serif;
        }
        .profile-footer .btn-secondary:hover {
            background: var(--surface-2);
            border-color: var(--text-3);
        }
        .profile-footer .btn-save {
            background: var(--primary);
            border: none;
            border-radius: var(--radius-sm);
            padding: 7px 24px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: background 0.15s;
            font-family: 'Noto Sans Osage', sans-serif;
        }
        .profile-footer .btn-save:hover {
            background: var(--primary-light);
        }
        body.dark-mode .profile-footer .btn-secondary {
            color: #b0b0c8;
            border-color: rgba(255,255,255,0.12);
        }
        body.dark-mode .profile-footer .btn-secondary:hover {
            background: #3a3a55;
            border-color: rgba(255,255,255,0.2);
        }
        .profile-error {
            color: #dc2626;
            font-size: 0.7rem;
            margin-top: 2px;
            display: none;
        }
        .profile-error.show {
            display: block;
        }
        .profile-success {
            color: #16a34a;
            font-size: 0.75rem;
            text-align: center;
            padding: 6px 0;
            display: none;
        }
        .profile-success.show {
            display: block;
        }
        @media(max-width:480px) {
            .profile-form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .profile-avatar-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .profile-avatar-actions .change-photo-btn {
                margin: 0 auto;
            }
        }
    </style>
    <link rel="stylesheet" href="../includes/side_nav.css">
</head>
<body>

<!-- ========== SIDE NAV OVERLAY ========== -->
<div class="nav-overlay" id="sideNavOverlay" onclick="closeSideNav()"></div>

<!-- ========== SIDE NAV ========== -->
<div class="side-nav" id="sideNav">
    <div class="side-nav-header">
        <a href="#" class="side-nav-brand">UMak<span>·Registrar</span></a>
        <button class="side-nav-close" onclick="closeSideNav()">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="side-nav-body">
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('My Profile')">
            <svg viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            My Profile
        </a>
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('My Classes')">
            <svg viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            My Classes
        </a>
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('Grade Management')">
            <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Grade Management
        </a>
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('Student List')">
            <svg viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-4c0-.65-.138-1.274-.386-1.834M7 20H2v-2a3 3 0 015.356-1.857M7 20v-4c0-.65.138-1.274.386-1.834M16 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Student List
        </a>
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('Reports')">
            <svg viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Reports
        </a>
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('Announcements')">
            <svg viewBox="0 0 24 24"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            Announcements
        </a>
        <a href="#" class="side-nav-link" onclick="handleSideNavAction('Help Center')">
            <svg viewBox="0 0 24 24"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Help Center / FAQs
        </a>
        <div class="side-nav-divider"></div>
        <div class="side-nav-dark-toggle" onclick="toggleDarkModeFromSideNav()">
            <span style="display:flex;align-items:center;gap:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                Dark Mode
            </span>
            <span class="toggle-track">
                <span class="toggle-thumb"></span>
            </span>
        </div>
    </div>
    <div class="side-nav-footer">&copy; 2026 UMak · Registrar</div>
</div>

<!-- ========== HEADER ========== -->
<header class="header">
    <div class="header-inner">
        <button class="hamburger-btn" onclick="openSideNav()" aria-label="Open menu">
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
        <a class="brand" href="#">
            <span class="brand-name">UMak<span style="color:#FFD700;">·Registrar</span></span>
            <span class="brand-sub">University of Makati · Faculty Portal</span>
        </a>
        <div style="flex:1;"></div>
        <nav class="header-nav-links" id="headerNavLinks">
            <div class="nav-dropdown">
                <button class="header-nav-link active" onclick="openServiceModal('dashboard')">Dashboard</button>
                <div class="dropdown-popup">
                    <a href="#" class="popup-item" style="text-decoration:none;">View Statistics<span class="item-desc">Overall summary</span></a>
                    <a href="#" class="popup-item" style="text-decoration:none;">Academic Calendar<span class="item-desc">View schedule</span></a>
                    <a href="#" class="popup-item" style="text-decoration:none;">Notifications<span class="item-desc">View updates</span></a>
                    <a href="#" class="popup-item" style="text-decoration:none;">Quick Tasks<span class="item-desc">Pending actions</span></a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="header-nav-link" onclick="openServiceModal('classes')">Classes</button>
                <div class="dropdown-popup">
                    <button class="popup-item" onclick="handlePopupAction('classes', 'My Schedule')">My Schedule<span class="item-desc">View schedule</span></button>
                    <button class="popup-item" onclick="handlePopupAction('classes', 'Class Roster')">Class Roster<span class="item-desc">Student list</span></button>
                    <button class="popup-item" onclick="handlePopupAction('classes', 'Attendance')">Attendance<span class="item-desc">Record attendance</span></button>
                    <button class="popup-item" onclick="handlePopupAction('classes', 'Class Materials')">Class Materials<span class="item-desc">Upload materials</span></button>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="header-nav-link" onclick="openServiceModal('grades')">Grades</button>
                <div class="dropdown-popup">
                    <button class="popup-item" onclick="handlePopupAction('grades', 'Encode Grades')">Encode Grades<span class="item-desc">Input grades</span></button>
                    <button class="popup-item" onclick="handlePopupAction('grades', 'View Grades')">View Grades<span class="item-desc">Student grades</span></button>
                    <button class="popup-item" onclick="handlePopupAction('grades', 'Grade Revision')">Grade Revision<span class="item-desc">Correct grades</span></button>
                    <button class="popup-item" onclick="handlePopupAction('grades', 'Deans List')">Dean's List<span class="item-desc">Honor students</span></button>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="header-nav-link" onclick="openServiceModal('advisory')">Advisory</button>
                <div class="dropdown-popup">
                    <button class="popup-item" onclick="handlePopupAction('advisory', 'Student Advising')">Student Advising<span class="item-desc">Advise students</span></button>
                    <button class="popup-item" onclick="handlePopupAction('advisory', 'Schedule Consultation')">Schedule Consultation<span class="item-desc">Set appointment</span></button>
                    <button class="popup-item" onclick="handlePopupAction('advisory', 'Academic Concerns')">Academic Concerns<span class="item-desc">View concerns</span></button>
                    <button class="popup-item" onclick="handlePopupAction('advisory', 'Recommendations')">Recommendations<span class="item-desc">Student recommendations</span></button>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="header-nav-link" onclick="openServiceModal('reports')">Reports</button>
                <div class="dropdown-popup">
                    <button class="popup-item" onclick="handlePopupAction('reports', 'Class Performance')">Class Performance<span class="item-desc">Performance metrics</span></button>
                    <button class="popup-item" onclick="handlePopupAction('reports', 'Grade Summary')">Grade Summary<span class="item-desc">Grade reports</span></button>
                    <button class="popup-item" onclick="handlePopupAction('reports', 'Attendance Report')">Attendance Report<span class="item-desc">Attendance summary</span></button>
                    <button class="popup-item" onclick="handlePopupAction('reports', 'Statistical Data')">Statistical Data<span class="item-desc">Statistics</span></button>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="header-nav-link" onclick="openServiceModal('records')">Records</button>
                <div class="dropdown-popup">
                    <button class="popup-item" onclick="handlePopupAction('records', 'Student Records')">Student Records<span class="item-desc">View records</span></button>
                    <button class="popup-item" onclick="handlePopupAction('records', 'Academic History')">Academic History<span class="item-desc">Student history</span></button>
                    <button class="popup-item" onclick="handlePopupAction('records', 'Clearance')">Clearance<span class="item-desc">Clearance status</span></button>
                    <button class="popup-item" onclick="handlePopupAction('records', 'Transcripts')">Transcripts<span class="item-desc">View transcripts</span></button>
                </div>
            </div>
        </nav>
        <div class="nav-actions">
            <div style="position:relative;" id="profileWrap">
                <button class="avatar-btn" onclick="toggleDropdown()">
                    <div class="avatar-circle"><?php echo $user_initials; ?></div>
                    <span class="avatar-name"><?php echo htmlspecialchars(explode(' ', $user_fullname)[0]); ?></span>
                    <svg style="width:12px;height:12px;color:rgba(255,255,255,0.5);margin-left:1px" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="dropdown-menu" id="profileDropdown">
                    <div class="dd-header">
                        <div class="dd-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                        <div class="dd-email"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                    <div class="dd-divider"></div>
                    <div class="dd-section">
                        <div class="dd-label">Account</div>
                        <div class="dd-item" onclick="openProfileModal()">Profile Settings</div>
                        <div class="dd-divider"></div>
                        <div class="dd-item danger" onclick="doSignOut()">Sign Out</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ========== SERVICE MODAL ========== -->
<div class="service-modal-backdrop" id="serviceModalBackdrop" onclick="if(event.target===this) closeServiceModal()">
    <div class="service-modal" id="serviceModalBox">
        <div class="service-modal-head">
            <span class="service-modal-title" id="serviceModalTitle">Services</span>
            <button class="service-modal-close" onclick="closeServiceModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="service-modal-body" id="serviceModalBody"></div>
    </div>
</div>

<!-- ========== PROFILE SETTINGS MODAL ========== -->
<div class="modal-backdrop profile-modal" id="profileModalBackdrop" onclick="if(event.target===this) closeProfileModal()">
    <div class="modal" id="profileModalBox">
        <div class="modal-head">
            <span class="modal-title">Profile Settings</span>
            <button class="modal-close" onclick="closeProfileModal()" aria-label="Close profile settings">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="profile-avatar-section">
                <div class="profile-avatar" id="profileAvatar"><?php echo $user_initials; ?></div>
                <div class="profile-avatar-actions">
                    <button class="change-photo-btn" id="changePhotoBtn" aria-label="Change photo">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Change Photo
                    </button>
                    <input type="file" id="photoInput" accept="image/jpeg,image/png" style="display:none">
                    <span class="helper-text">JPG or PNG, max 2MB</span>
                </div>
            </div>
            <form id="profileForm" novalidate>
                <div class="profile-form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" value="<?php echo htmlspecialchars($user_fullname); ?>" required>
                    <div class="profile-error" id="fullNameError">Full name is required.</div>
                </div>
                <div class="profile-form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                </div>
                <div class="profile-form-row">
                    <div class="profile-form-group">
                        <label for="employeeId">Employee ID</label>
                        <input type="text" id="employeeId" value="<?php echo htmlspecialchars($faculty_employee_id); ?>" disabled>
                    </div>
                    <div class="profile-form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" value="<?php echo htmlspecialchars($faculty_department); ?>" disabled>
                    </div>
                </div>
                <div class="profile-form-group">
                    <label for="college">College</label>
                    <input type="text" id="college" value="<?php echo htmlspecialchars($faculty_college); ?>" disabled>
                </div>
                <div class="profile-success" id="profileSuccess">Profile updated successfully!</div>
                <div class="profile-footer">
                    <button type="button" class="btn-secondary" onclick="closeProfileModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODAL BACKDROP ========== -->
<div class="modal-backdrop" id="modalBackdrop" onclick="if(event.target===this) closeModal()">
    <div class="modal" id="modalBox">
        <div class="modal-head">
            <span class="modal-title" id="modalTitle"></span>
            <button class="modal-close" onclick="closeModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- ========== OFF-CANVAS OVERLAY ========== -->
<div class="offcanvas-overlay" id="offcanvasOverlay" onclick="closeOffcanvas()"></div>

<!-- ========== OFF-CANVAS PANEL - CENTERED ========== -->
<div class="offcanvas" id="offcanvasPanel">
    <div class="offcanvas-header">
        <h3><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> My Profile</h3>
        <button class="offcanvas-close" onclick="closeOffcanvas()">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="offcanvas-body">
        <!-- Profile Header -->
        <div class="profile-header-card">
            <div class="profile-avatar-large"><?php echo $user_initials; ?></div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($user_role); ?> · <?php echo htmlspecialchars($faculty_department); ?></div>
            </div>
        </div>

        <!-- Faculty Information Section -->
        <div class="offcanvas-section">
            <div class="offcanvas-section-title">Faculty Information</div>
            <div class="profile-info-grid">
                <span class="info-label">Employee ID</span>
                <span class="info-value"><?php echo htmlspecialchars($faculty_employee_id); ?></span>
                
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($user_fullname); ?></span>
                
                <span class="info-label">Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
                
                <span class="info-label">College</span>
                <span class="info-value"><?php echo htmlspecialchars($faculty_college); ?></span>
                
                <span class="info-label">Department</span>
                <span class="info-value"><?php echo htmlspecialchars($faculty_department); ?></span>
                
                <span class="info-label">Academic Year</span>
                <span class="info-value">2026–2027</span>
            </div>
        </div>

        <button class="profile-edit-btn" onclick="closeOffcanvas(); openProfileModal();">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Edit Profile
        </button>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<main class="page">
    <section class="hero-section fade-scroll" id="heroSection">
        <div class="hero-content">
            <h1>Welcome to UMaK Faculty Portal, <span><?php echo htmlspecialchars($user_firstname); ?></span></h1>
            <p class="sub">Manage your classes, students, and academic records in one place.</p>
            <p class="desc">Access class schedules, student records, grade management, and other faculty services through the Registrar Portal.</p>
        </div>
    </section>

    <!-- ===== STATS CARDS ===== -->
    <section style="margin-bottom:2.25rem;">
        <div class="section-header">
            <h2 class="section-title">Overview</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            <div class="bg-white p-6 shadow-md border-l-4 border-gold fade-scroll">
                <h3 class="text-gray-500 text-sm">My Classes</h3>
                <p class="text-3xl font-bold text-primary"><?php echo $faculty_classes; ?></p>
            </div>
            <div class="bg-white p-6 shadow-md border-l-4 border-gold fade-scroll">
                <h3 class="text-gray-500 text-sm">Total Students</h3>
                <p class="text-3xl font-bold text-primary"><?php echo $total_students; ?></p>
            </div>
            <div class="bg-white p-6 shadow-md border-l-4 border-gold fade-scroll">
                <h3 class="text-gray-500 text-sm">Pending Grades</h3>
                <p class="text-3xl font-bold text-primary"><?php echo $pending_grades; ?></p>
            </div>
            <div class="bg-white p-6 shadow-md border-l-4 border-gold fade-scroll">
                <h3 class="text-gray-500 text-sm">Attendance Today</h3>
                <p class="text-3xl font-bold text-primary"><?php echo $attendance_today; ?></p>
            </div>
        </div>
    </section>

    <!-- ===== QUICK ACTIONS ===== -->
    <section style="margin-bottom:2.25rem;">
        <div class="section-header">
            <h2 class="section-title">Quick Actions</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 shadow-md hover:shadow-lg transition fade-scroll">
                <h3 class="text-xl font-bold text-primary mb-2">My Classes</h3>
                <p class="text-gray-600 mb-4">View and manage your classes</p>
                <a href="#" class="bg-primary text-white px-4 py-2 inline-block hover:bg-primary/90 transition">View Classes →</a>
            </div>
            
            <div class="bg-white p-6 shadow-md hover:shadow-lg transition fade-scroll">
                <h3 class="text-xl font-bold text-primary mb-2">Grade Management</h3>
                <p class="text-gray-600 mb-4">Enter and manage student grades</p>
                <a href="#" class="bg-primary text-white px-4 py-2 inline-block hover:bg-primary/90 transition">Manage Grades →</a>
            </div>
            
            <div class="bg-white p-6 shadow-md hover:shadow-lg transition fade-scroll">
                <h3 class="text-xl font-bold text-primary mb-2">Reports</h3>
                <p class="text-gray-600 mb-4">Generate class reports and analytics</p>
                <a href="#" class="bg-primary text-white px-4 py-2 inline-block hover:bg-primary/90 transition">View Reports →</a>
            </div>
        </div>
    </section>

    <!-- ===== RECENT ACTIVITY ===== -->
    <section>
        <div class="section-header">
            <h2 class="section-title">Recent Activity</h2>
        </div>
        <div class="card fade-scroll full-width-card" id="recentActivityCard">
            <div class="card-body" style="padding:0.5rem 1.375rem 0.75rem;">
                <div class="activity-item">
                    <div class="act-icon">📝</div>
                    <div class="act-content">
                        <span class="act-text">No recent activity</span>
                        <span class="act-time">-</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer style="border-top:1px solid var(--border);padding:1.25rem;text-align:center;transition:border-color 0.3s ease;margin-top:1.5rem;">
    <p style="font-size:.75rem;color:var(--text-3);transition:color 0.3s ease;">
        &copy; 2026 University of Makati · Registrar's Office · Faculty Portal · All rights reserved.
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

function handleSideNavAction(action) {
    if (action === 'My Profile') {
        openOffcanvas();
        closeSideNav();
        return;
    }
    alert('📌 ' + action + '\n\nThis will open the ' + action + ' section.');
    closeSideNav();
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

// ========== POPUP ACTIONS ==========
function handlePopupAction(category, action) {
    alert('📌 ' + category.toUpperCase() + ' → ' + action + '\n\nThis will open the ' + action + ' module.');
}

// ========== SERVICE MODAL ==========
var serviceData = {
    dashboard: { title: 'Dashboard Services', services: [{ label: 'View Statistics', desc: 'Overall summary' }, { label: 'Academic Calendar', desc: 'View schedule' }, { label: 'Notifications', desc: 'View updates' }, { label: 'Quick Tasks', desc: 'Pending actions' }] },
    classes: { title: 'Class Management', services: [{ label: 'My Schedule', desc: 'View schedule' }, { label: 'Class Roster', desc: 'Student list' }, { label: 'Attendance', desc: 'Record attendance' }, { label: 'Class Materials', desc: 'Upload materials' }] },
    grades: { title: 'Grade Management', services: [{ label: 'Encode Grades', desc: 'Input grades' }, { label: 'View Grades', desc: 'Student grades' }, { label: 'Grade Revision', desc: 'Correct grades' }, { label: "Dean's List", desc: 'Honor students' }] },
    advisory: { title: 'Advisory Services', services: [{ label: 'Student Advising', desc: 'Advise students' }, { label: 'Schedule Consultation', desc: 'Set appointment' }, { label: 'Academic Concerns', desc: 'View concerns' }, { label: 'Recommendations', desc: 'Student recommendations' }] },
    reports: { title: 'Reports Services', services: [{ label: 'Class Performance', desc: 'Performance metrics' }, { label: 'Grade Summary', desc: 'Grade reports' }, { label: 'Attendance Report', desc: 'Attendance summary' }, { label: 'Statistical Data', desc: 'Statistics' }] },
    records: { title: 'Records Services', services: [{ label: 'Student Records', desc: 'View records' }, { label: 'Academic History', desc: 'Student history' }, { label: 'Clearance', desc: 'Clearance status' }, { label: 'Transcripts', desc: 'View transcripts' }] }
};

function openServiceModal(type) {
    var data = serviceData[type];
    if (!data) return;
    closeDropdown();
    var title = document.getElementById('serviceModalTitle');
    var body = document.getElementById('serviceModalBody');
    title.textContent = data.title;
    var html = '';
    data.services.forEach(function(service, index) {
        html += '<div class="service-item" onclick="handleServiceAction(\'' + type + '\', \'' + service.label + '\')"><span class="label">' + service.label + '</span><span class="desc">' + service.desc + '</span></div>';
        if (index < data.services.length - 1) html += '<div class="service-divider"></div>';
    });
    body.innerHTML = html;
    document.getElementById('serviceModalBackdrop').classList.add('open');
}

function closeServiceModal() {
    document.getElementById('serviceModalBackdrop').classList.remove('open');
}

function handleServiceAction(category, action) {
    alert('📌 ' + category.toUpperCase() + ' → ' + action + '\n\nThis will open the ' + action + ' module.');
    closeServiceModal();
}

// ========== PROFILE SETTINGS MODAL ==========
function openProfileModal() {
    closeDropdown();
    document.getElementById('profileSuccess').classList.remove('show');
    document.getElementById('fullNameError').classList.remove('show');
    document.getElementById('profileForm').reset();
    document.getElementById('fullName').value = '<?php echo addslashes($user_fullname); ?>';
    document.getElementById('email').value = '<?php echo addslashes($user_email); ?>';
    document.getElementById('employeeId').value = '<?php echo addslashes($faculty_employee_id); ?>';
    document.getElementById('college').value = '<?php echo addslashes($faculty_college); ?>';
    document.getElementById('department').value = '<?php echo addslashes($faculty_department); ?>';
    document.getElementById('profileAvatar').textContent = '<?php echo $user_initials; ?>';
    document.getElementById('profileModalBackdrop').classList.add('open');
}

function closeProfileModal() {
    document.getElementById('profileModalBackdrop').classList.remove('open');
    document.getElementById('fullNameError').classList.remove('show');
    document.getElementById('profileSuccess').classList.remove('show');
}

document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fullName = document.getElementById('fullName').value.trim();
    var errorEl = document.getElementById('fullNameError');
    var successEl = document.getElementById('profileSuccess');
    if (!fullName) {
        errorEl.classList.add('show');
        successEl.classList.remove('show');
        return;
    }
    errorEl.classList.remove('show');
    successEl.classList.add('show');
    var nameParts = fullName.split(' ');
    var initials = '';
    if (nameParts.length >= 2) {
        initials = nameParts[0].charAt(0).toUpperCase() + nameParts[1].charAt(0).toUpperCase();
    } else if (nameParts.length === 1) {
        initials = nameParts[0].charAt(0).toUpperCase();
    } else {
        initials = 'JD';
    }
    document.getElementById('profileAvatar').textContent = initials || 'JD';
    setTimeout(function() {
        closeProfileModal();
        alert('✅ Profile updated successfully!');
    }, 800);
});

document.getElementById('changePhotoBtn').addEventListener('click', function() {
    document.getElementById('photoInput').click();
});

document.getElementById('photoInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        alert('Please select a JPG or PNG image.');
        this.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('File size exceeds 2MB. Please select a smaller image.');
        this.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(ev) {
        var avatar = document.getElementById('profileAvatar');
        avatar.textContent = '📷';
        setTimeout(function() {
            avatar.textContent = '<?php echo $user_initials; ?>';
        }, 800);
        alert('✅ Photo updated successfully! (demo)');
    };
    reader.readAsDataURL(file);
    this.value = '';
});

// ========== MODAL ==========
function openModal(type) {
    closeDropdown();
    var title = document.getElementById('modalTitle');
    var body = document.getElementById('modalBody');
    var map = {
        profile: {
            t: 'Profile Settings',
            b: '<div style="display:flex;flex-direction:column;gap:10px;font-size:.875rem;color:var(--text-2)"><div style="display:grid;grid-template-columns:110px 1fr;gap:8px 12px;padding-top:4px"><span style="color:var(--text-3)">Full name</span><span style="color:var(--text-1);font-weight:500"><?php echo htmlspecialchars($user_fullname); ?></span><span style="color:var(--text-3)">Email</span><span><?php echo htmlspecialchars($user_email); ?></span><span style="color:var(--text-3)">Employee ID</span><span><?php echo htmlspecialchars($faculty_employee_id); ?></span><span style="color:var(--text-3)">College</span><span><?php echo htmlspecialchars($faculty_college); ?></span><span style="color:var(--text-3)">Department</span><span><?php echo htmlspecialchars($faculty_department); ?></span></div><div style="display:flex;justify-content:flex-end;margin-top:8px"><button class="btn-primary" onclick="closeModal()">Close</button></div></div>'
        },
        preferences: {  
            t: 'Preferences',
            b: '<div style="display:flex;flex-direction:column;gap:12px"><label style="display:flex;align-items:center;gap:10px;font-size:.875rem;cursor:pointer"><input type="checkbox" id="darkModePref" onchange="toggleDarkModeFromPref()"> Dark mode</label><label style="display:flex;align-items:center;gap:10px;font-size:.875rem;cursor:pointer"><input type="checkbox"> Compact view</label><label style="display:flex;align-items:center;gap:10px;font-size:.875rem;cursor:pointer"><input type="checkbox" checked> Email notifications</label><div style="display:flex;justify-content:flex-end;margin-top:6px"><button class="btn-primary" onclick="closeModal()">Save</button></div></div>'
        }
    };
    var m = map[type];
    if(!m) return;
    title.textContent = m.t;
    body.innerHTML = m.b;
    document.getElementById('modalBackdrop').classList.add('open');
    if (type === 'preferences') {
        setTimeout(function() {
            var checkbox = document.getElementById('darkModePref');
            if (checkbox) checkbox.checked = document.body.classList.contains('dark-mode');
        }, 50);
    }
}

function toggleDarkModeFromPref() {
    var checkbox = document.getElementById('darkModePref');
    if (checkbox && checkbox.checked) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'true');
    } else if (checkbox) {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'false');
    }
}

function closeModal() { document.getElementById('modalBackdrop').classList.remove('open'); }

function doSignOut() {
    if (confirm('Are you sure you want to sign out?')) {
        window.location.href = '../includes/logout.php';
    }
}

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