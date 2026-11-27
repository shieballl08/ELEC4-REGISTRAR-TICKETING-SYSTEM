<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    session_start();

    if(isset($_GET['reset']) && $_GET['reset'] == 'success') {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        session_start();
        $success_message = "✅ Password reset successful! Please login with your new password.";
    }

    if (isset($_SESSION['pending_otp']) && $_SESSION['pending_otp'] === true) {
        // Nasa OTP stage pa
    }
    elseif (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $role = $_SESSION['role'] ?? 'student';
        switch($role) {
            case 'student': header('Location: ../main/User_Dashboard.php'); break;
            case 'faculty': header('Location: ../main/Faculty_Dashboard.php'); break;
            case 'admin':   header('Location: ../main/admin/Admin_Dashboard.php'); break;
            default:        header('Location: ../main/User_Dashboard.php');
        }
        exit();
    }

    $dbConnected = false;
    $pdo = null;
    require_once __DIR__ . '/../config/db_connect_pg.php';

    if (class_exists('Database')) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            if ($pdo) $dbConnected = true;
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            $dbConnected = false;
        }
    }

    $totalStudents = 0; $graduationRate = 0; $totalPrograms = 0; $yearsOfExcellence = 0;

    if ($dbConnected && $pdo) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
            $result = $stmt->fetch();
            if ($result) $totalStudents = (int)$result['total'];
            
            $stmt = $pdo->query("SELECT COUNT(DISTINCT program) as total FROM users WHERE program IS NOT NULL AND program != ''");
            $result = $stmt->fetch();
            if ($result) $totalPrograms = (int)$result['total'];
            
            $stmt = $pdo->query("SELECT ROUND((SELECT COUNT(*) FROM users WHERE role = 'student' AND graduation_year IS NOT NULL) * 100.0 / NULLIF((SELECT COUNT(*) FROM users WHERE role = 'student'), 0)) as rate");
            $result = $stmt->fetch();
            if ($result && $result['rate'] > 0) $graduationRate = (int)$result['rate'];
            
            $stmt = $pdo->query("SELECT MIN(EXTRACT(YEAR FROM created_at)) as start_year FROM users");
            $result = $stmt->fetch();
            if ($result && $result['start_year']) $yearsOfExcellence = date('Y') - (int)$result['start_year'];
            
            if ($totalStudents == 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
                $result = $stmt->fetch();
                if ($result) $totalStudents = (int)$result['total'];
            }
            if ($totalPrograms == 0) {
                $stmt = $pdo->query("SELECT COUNT(DISTINCT course) as total FROM students WHERE course IS NOT NULL AND course != ''");
                $result = $stmt->fetch();
                if ($result) $totalPrograms = (int)$result['total'];
            }
            if ($yearsOfExcellence == 0) $yearsOfExcellence = date('Y') - 1972;
            if ($graduationRate == 0) $graduationRate = 95;
        } catch (PDOException $e) {
            error_log("PostgreSQL Query error: " . $e->getMessage());
            $totalStudents = 15000; $graduationRate = 95; $totalPrograms = 45; $yearsOfExcellence = date('Y') - 1972;
        }
    } else {
        $totalStudents = 15000; $graduationRate = 95; $totalPrograms = 45; $yearsOfExcellence = date('Y') - 1972;
    }

    function formatNumber($num) {
        if ($num >= 1000000) return number_format($num / 1000000, 1) . 'M';
        elseif ($num >= 1000) return number_format($num / 1000, 1) . 'K';
        return $num;
    }

    $formattedStudents = formatNumber($totalStudents);
    ?>

        <!DOCTYPE html>
        <html lang="en" class="h-full">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
            <title>University of Makati · Registrar's Office</title>
            <script src="https://cdn.tailwindcss.com/3.4.17"></script>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Condensed:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body { height: 100%; margin: 0; padding: 0; }
                body { font-family: 'Fira Sans Condensed', sans-serif; background: #faf9f6; color: #1a2a3a; }
                :root { --primary: #003366; --primary-dark: #002244; --primary-light: #1a4d7a; --secondary: #FFD700; --secondary-light: #FFE44D; --secondary-dark: #E6C200; }
                * { font-family: 'Fira Sans Condensed', sans-serif; }
                h1, h2, h3, h4, h5, h6, .section-title, .brand-name, .nav-link-custom, .btn, .stat-value { font-family: 'Fira Sans Condensed', sans-serif; letter-spacing: 0.01em; }
                .nav-link-custom { color: rgba(255,255,255,0.9); font-weight: 400; font-size: 0.95rem; transition: all 0.2s; cursor: pointer; padding: 0.5rem 0.75rem; border-radius: 0px; white-space: nowrap; text-decoration: none; display: inline-block; }
                .nav-link-custom:hover { color: var(--secondary); background: rgba(255,215,0,0.15); }
                .login-btn { border: 2px solid var(--secondary); border-radius: 0px; padding: 0.5rem 1.8rem; transition: all 0.2s ease; background: transparent; color: white !important; }
                .login-btn:hover { background: var(--secondary); color: var(--primary) !important; border-color: var(--secondary); transform: translateY(-2px); }
                .btn-outline-light { border: 2px solid rgba(255,255,255,0.4); color: white; font-weight: 400; padding: 0.6rem 1.8rem; border-radius: 0px; transition: all 0.2s; font-size: 0.95rem; background: transparent; }
                .btn-outline-light:hover { background: var(--secondary); color: var(--primary); border-color: var(--secondary); transform: translateY(-2px); box-shadow: 0 10px 20px -8px rgba(0,0,0,0.25); }
                .btn-primary-gold { background: var(--secondary); color: var(--primary); font-weight: 400; padding: 0.6rem 2rem; border-radius: 0px; transition: all 0.2s; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(255,215,0,0.35); border: none; }
                .btn-primary-gold:hover { background: var(--secondary-light); transform: translateY(-2px); box-shadow: 0 20px 30px -8px rgba(255,215,0,0.4); }
                .hero-overlay { background: transparent; }
                .hero-section { position: relative; width: 100%; height: 100vh; min-height: 100vh; display: flex; align-items: center; overflow: hidden; background-color: var(--primary); }
                .hero-slideshow { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
                .slide-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.8s ease-in-out; filter: brightness(0.55); }
                .slide-image.active { opacity: 1; z-index: 1; }
                .hero-content-wrapper { position: relative; z-index: 30; width: 100%; min-height: 100%; display: flex; align-items: center; padding: 5rem 2rem 3rem; }
                @media (min-width: 768px) { .hero-content-wrapper { padding: 6rem 2.5rem 4rem; } }
                @media (min-width: 1024px) { .hero-content-wrapper { padding: 7rem 3rem 5rem; } }
                .hero-text-content { display: flex; flex-direction: column; align-items: flex-start; justify-content: center; }
                .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; text-align: center; }
                .stat-item { text-align: center; padding: 1.5rem 1rem; transition: all 0.3s ease; background: white; border-radius: 0px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 2px solid rgba(255,215,0,0.2); }
                .stat-item:hover { transform: translateY(-6px); border-color: var(--secondary); box-shadow: 0 12px 35px rgba(255,215,0,0.2); }
                .stat-value { font-size: 3rem; font-weight: 700; color: var(--primary); line-height: 1.2; }
                @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; } .stat-value { font-size: 2rem; } }
                @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; gap: 1rem; } }
                .social-icon { transition: all 0.2s; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(4px); width: 2.8rem; height: 2.8rem; border-radius: 0px; display: flex; align-items: center; justify-content: center; }
                .social-icon:hover { transform: translateY(-3px); background: var(--secondary); }
                .social-icon:hover svg { color: var(--primary); }
                .card { background: white; border-radius: 0px; padding: 1.8rem; border: 2px solid rgba(255,215,0,0.15); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
                .card:hover { transform: translateY(-8px); border-color: var(--secondary); box-shadow: 0 30px 45px -15px rgba(255,215,0,0.2); }
                .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--secondary), var(--secondary-light)); transform: scaleX(0); transition: transform 0.3s ease; }
                .card:hover::before { transform: scaleX(1); }
                .contact-card { background: white; border-radius: 0px; padding: 2rem; border: 2px solid rgba(255,215,0,0.15); transition: all 0.3s; position: relative; overflow: hidden; }
                .contact-card:hover { border-color: var(--secondary); transform: translateY(-4px); box-shadow: 0 25px 35px -12px rgba(255,215,0,0.2); }
                .contact-icon { width: 3.2rem; height: 3.2rem; background: var(--primary); border-radius: 0px; display: flex; align-items: center; justify-content: center; color: var(--secondary); flex-shrink: 0; box-shadow: 0 10px 20px -5px rgba(0,51,102,0.25); }
                .input-corporate { border: 2px solid #e0ddd6; padding: 0.8rem 1.2rem; border-radius: 0px; width: 100%; transition: all 0.2s; background: white; font-size: 0.95rem; }
                .input-corporate:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 5px rgba(255,215,0,0.2); transform: scale(1.02); }
                .input-corporate:read-only { background-color: #f3f1ec; cursor: not-allowed; }
                .input-corporate.input-error { border-color: #ef4444; background-color: #fef2f2; }
                .input-corporate.input-success { border-color: #10b981; }
                .field-error { font-size: 0.75rem; margin-top: 0.25rem; display: block; }
                .section-tag { font-size: 0.85rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--primary); display: inline-block; margin-bottom: 0.5rem; background: rgba(255,215,0,0.15); padding: 0.3rem 1.2rem; border-radius: 0px; border: 2px solid rgba(255,215,0,0.3); }
                .section-title { font-family: 'Fira Sans Condensed', sans-serif; font-size: 2.6rem; font-weight: 700; color: var(--primary-dark); line-height: 1.2; margin-bottom: 1rem; }
                .modal { transition: opacity 0.2s ease; }
                .value-prop-card { background: white; border-radius: 0px; padding: 2rem; border: 2px solid rgba(255,215,0,0.15); transition: all 0.3s; position: relative; overflow: hidden; }
                .value-prop-card:hover { transform: translateY(-8px); border-color: var(--secondary); box-shadow: 0 30px 45px -15px rgba(255,215,0,0.2); }
                .value-prop-card:hover .icon-wrapper { background: var(--secondary); color: var(--primary); }
                .icon-wrapper { width: 3.8rem; height: 3.8rem; background: rgba(255,215,0,0.15); border-radius: 0px; display: flex; align-items: center; justify-content: center; transition: all 0.3s; margin-bottom: 1.5rem; color: var(--primary); }
                .section-divider { height: 3px; background: linear-gradient(90deg, transparent, var(--secondary), transparent); width: 100%; max-width: 250px; margin: 2rem auto; border-radius: 0px; }
                .spinner { border: 3px solid #f3f3f3; border-top: 3px solid var(--primary); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                .hidden { display: none; }
                .scroll-reveal { opacity: 0; transform: translateY(30px); transition: all 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1); }
                .scroll-reveal.visible { opacity: 1; transform: translateY(0); }
                .reveal-delay-1 { transition-delay: 0.05s; }
                .reveal-delay-2 { transition-delay: 0.1s; }
                .reveal-delay-3 { transition-delay: 0.15s; }
                .reveal-delay-4 { transition-delay: 0.2s; }
                .reveal-delay-5 { transition-delay: 0.25s; }
                ::-webkit-scrollbar { width: 8px; height: 8px; }
                ::-webkit-scrollbar-track { background: #f1efe8; }
                ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 0px; }
                ::-webkit-scrollbar-thumb:hover { background: var(--secondary-dark); }
                .core-value-card { background: white; border-radius: 0px; padding: 1.8rem; border: 2px solid rgba(255,215,0,0.15); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; height: 100%; }
                .core-value-card:hover { transform: translateY(-8px); border-color: var(--secondary); box-shadow: 0 30px 45px -15px rgba(255,215,0,0.2); }
                .core-value-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--secondary), var(--secondary-light)); transform: scaleX(0); transition: transform 0.3s ease; }
                .core-value-card:hover::before { transform: scaleX(1); }
                .core-value-card h4 { font-family: 'Fira Sans Condensed', sans-serif; font-weight: 700; font-size: 1.4rem; margin-bottom: 0.75rem; color: var(--primary); }
                .core-value-card p { color: #4a4a4a; line-height: 1.6; }
                .umak-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,215,0,0.12); border: 2px solid rgba(255,215,0,0.3); padding: 0.3rem 1.2rem; border-radius: 0px; color: white; font-size: 0.8rem; letter-spacing: 0.08em; text-transform: uppercase; }
                .umak-badge .dot { width: 8px; height: 8px; background: var(--secondary); border-radius: 50%; display: inline-block; animation: pulse-dot 2s infinite; }
                @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
                .hero-text-content .umak-badge { margin-bottom: 0.75rem; }
                .hero-text-content h1 { margin-bottom: 0.75rem; }
                .hero-text-content p { margin-bottom: 1.25rem; }
                .hero-text-content .flex-wrap { margin-bottom: 0.75rem; }
                .hero-text-content .border-l-4 { margin-bottom: 0.75rem; }
                .text-center { text-align: center; }
                .text-left { text-align: left; }
                .section-padding { padding-top: 4rem; padding-bottom: 4rem; }
                @media (min-width: 768px) { .section-padding { padding-top: 5rem; padding-bottom: 5rem; } }
                .bg-white.rounded-2xl, .rounded-2xl, .rounded-lg, .rounded-full, .rounded-xl, .rounded-3xl { border-radius: 0px !important; }
                .contact-icon, .input-corporate { border-radius: 0px !important; }
                [class*="rounded-"] { border-radius: 0px !important; }
                .max-w-7xl { max-width: 100% !important; padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                .px-6, .lg\:px-8, .px-4, .px-5, .px-8 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                section .max-w-7xl, footer .max-w-7xl, header .max-w-7xl { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                .hero-content-wrapper .max-w-7xl { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                .max-w-2xl.mx-auto { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                #statsGrid { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                .modal .max-w-md { max-width: 28rem !important; }
                .modal .bg-white { padding: 2rem !important; max-width: 28rem !important; }
                #loginModal .bg-white, #otpModal .bg-white, #registerModal .bg-white { max-width: 28rem !important; }
                footer .max-w-7xl { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                .w-full.bg-\[\#faf9f6\] { padding-left: 0 !important; padding-right: 0 !important; }
                .card, .contact-card, .value-prop-card, .core-value-card { padding-left: 1.5rem; padding-right: 1.5rem; }
                #mobile-menu { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
                .stat-item { padding-left: 0.5rem; padding-right: 0.5rem; }
                .modal .absolute { position: absolute; }
                .btn-primary-gold, .btn-outline-light, .login-btn, button[type="submit"], button[id*="Btn"], a[id*="-btn"], a[id*="Btn"] { border-radius: 10px !important; }
                .login-btn { border-radius: 999px !important; padding: 0.5rem 1.6rem !important; }
                #mobileNavLoginBtn { border-radius: 999px !important; }
                #contactForm button[type="submit"] { border-radius: 10px !important; }
                .social-icon { border-radius: 50% !important; }
                #closeLoginModal, #closeOtpModal, #closeRegisterModal { border-radius: 50% !important; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; line-height: 1; }
                .input-corporate { border-radius: 8px !important; }
                .card, .contact-card, .value-prop-card, .core-value-card, .stat-item { border-radius: 12px !important; }
                .icon-wrapper, .contact-icon { border-radius: 12px !important; }
                .section-tag, .umak-badge, .section-divider { border-radius: 999px !important; }
                #loginModal > div, #otpModal > div, #registerModal > div > div { border-radius: 14px !important; }
                #loginModal > div > div[style*="background: #dcfce7"] { border-radius: 10px !important; }
                #registerForm .bg-gray-50 { border-radius: 10px !important; }
                ::-webkit-scrollbar-thumb { border-radius: 8px !important; }
                .portal-section { background: #faf9f6; position: relative; scroll-margin-top: 90px; }
                .portal-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
                .portal-card { background: linear-gradient(135deg, rgba(0, 51, 102, 0.92) 0%, rgba(26, 77, 122, 0.88) 100%); backdrop-filter: blur(16px) saturate(180%); -webkit-backdrop-filter: blur(16px) saturate(180%); border: 1px solid rgba(255, 215, 0, 0.25); border-radius: 20px; padding: 2.2rem 1.5rem; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 1rem; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; box-shadow: 0 8px 32px rgba(0, 51, 102, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.1); cursor: pointer; }
                .portal-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #FFD700, #FFE44D, #FFD700); transform: scaleX(0); transform-origin: center; transition: transform 0.4s ease; }
                .portal-card::after { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 30% 20%, rgba(255, 215, 0, 0.08), transparent 60%); pointer-events: none; }
                .portal-card:hover { transform: translateY(-10px); border-color: #FFD700; box-shadow: 0 20px 50px rgba(0, 51, 102, 0.4), 0 0 0 1px rgba(255, 215, 0, 0.4); }
                .portal-card:hover::before { transform: scaleX(1); }
                .portal-icon-wrapper { width: 76px; height: 76px; background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(255, 215, 0, 0.05)); border: 1.5px solid rgba(255, 215, 0, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: #FFD700; margin-bottom: 0.5rem; transition: transform 0.4s ease, background 0.3s ease; }
                .portal-card:hover .portal-icon-wrapper { transform: scale(1.1) rotate(-3deg); background: linear-gradient(135deg, rgba(255, 215, 0, 0.25), rgba(255, 215, 0, 0.1)); }
                .portal-icon { width: 38px; height: 38px; }
                .portal-title { font-family: 'Fira Sans Condensed', sans-serif; font-size: 1.5rem; font-weight: 700; color: #FFFFFF; margin-bottom: 0.25rem; letter-spacing: 0.01em; }
                .portal-desc { font-size: 0.9rem; color: rgba(255, 255, 255, 0.8); line-height: 1.55; margin-bottom: 0.5rem; flex-grow: 1; }
                .portal-btn { display: inline-block; padding: 0.65rem 1.6rem; background: #FFD700; color: #003366; border-radius: 999px; font-size: 0.9rem; font-weight: 700; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3); }
                .portal-card:hover .portal-btn { background: #FFE44D; transform: scale(1.05); box-shadow: 0 6px 20px rgba(255, 215, 0, 0.5); }
                @media (max-width: 900px) { .portal-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 600px) { .portal-grid { grid-template-columns: 1fr; } }
                .conditional-field { transition: all 0.3s ease; }
            </style>
        </head>
        
        <body class="antialiased">
        <div class="w-full bg-[#faf9f6]">

            <header class="fixed top-0 left-0 w-full z-50 bg-[#003366]">
                <div class="max-w-7xl mx-auto px-6 lg:px-8">
                    <div class="flex items-center justify-between h-20">
                        <div class="flex flex-col leading-tight">
                            <div class="brand-name" style="font-family: 'Fira Sans Condensed', sans-serif; font-weight: 700; font-size: 1.6rem; line-height: 1.2; color: white; letter-spacing: 0.02em;">UMak<span style="color: #FFD700;">·Registrar</span></div>
                            <span class="text-white/70 text-xs font-medium tracking-wide">University of Makati · Official Records</span>
                        </div>
                        <div class="flex items-center space-x-8">
                            <nav class="flex items-center space-x-6">
                                <a href="#about" class="nav-link-custom">About</a>
                                <a href="#services" class="nav-link-custom">Services</a>
                                <a href="#contact" class="nav-link-custom">Contact</a>
                                <a href="#" id="navLoginBtn" class="nav-link-custom login-btn">Log in</a>
                            </nav>
                            <button id="mobile-menu-btn" class="md:hidden text-white p-2">
                                <svg width="24" height="24" fill="none" stroke="currentColor"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="mobile-menu" class="hidden bg-[#003366] border-t-2 border-[#FFD700]/30 px-6 py-4">
                    <a href="#about" class="block text-white/80 py-2 hover:text-[#FFD700]">About</a>
                    <a href="#services" class="block text-white/80 py-2 hover:text-[#FFD700]">Services</a>
                    <a href="#contact" class="block text-white/80 py-2 hover:text-[#FFD700]">Contact</a>
                    <a href="#" id="mobileNavLoginBtn" class="block text-white/80 py-2 hover:text-[#FFD700] mt-2 px-4 py-2 border-2 border-[#FFD700] rounded-full text-center hover:bg-[#FFD700] hover:text-[#003366] transition" style="border-radius:0px !important;">Log in</a>
                </div>
            </header>

            <section class="hero-section">
                <div class="hero-slideshow">
                    <img src="../images/umak_1.png" alt="University of Makati Aerial View" class="slide-image active" data-slide="0">
                    <img src="../images/umak_2.png" alt="UMak Main Facade" class="slide-image" data-slide="1">
                    <img src="../images/umak_3.png" alt="UMak Official Logo" class="slide-image" data-slide="2">
                    <img src="../images/umak_-4.png" alt="UMak Track and Field Stadium" class="slide-image" data-slide="3">
                </div>
                <div class="absolute inset-0 z-10 hero-overlay"></div>
                <div class="hero-content-wrapper">
                    <div class="max-w-7xl mx-auto px-6 lg:px-8 w-full">
                        <div class="grid lg:grid-cols-2 gap-12 items-start">
                            <div class="hero-text-content">
                                <span class="umak-badge"><span class="dot"></span>UNIVERSITY OF MAKATI · EST. 1972</span>
                                <h1 class="font-['Fira_Sans_Condensed'] text-5xl lg:text-6xl font-bold text-white leading-tight mb-3">Registrar's <span style="color: #FFD700;">Office</span></h1>
                                <p class="text-xl text-white/90 max-w-xl mb-4 leading-relaxed">Official academic records and enrollment services for the University of Makati community. Excellence in student records management.</p>
                                <div class="flex flex-wrap gap-3 mb-3">
                                    <a href="#" id="join-now-btn" class="btn-primary-gold">Get Started</a>
                                    <a href="#services" class="btn-outline-light">Our Services</a>
                                </div>
                                <p class="text-white/80 text-lg italic border-l-4 border-[#FFD700] pl-4 mb-3">"Serving the UMak community with integrity and excellence"</p>
                                <div class="flex items-center space-x-4 pt-2">
                                    <span class="text-sm text-white/60">Follow us:</span>
                                    <a href="#" class="social-icon hover:bg-[#1877f2]"><svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" /></svg></a>
                                    <a href="#" class="social-icon hover:bg-black"><svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.104c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 0021.688-4.096 13.87 13.87 0 001.302-6.655c0-.198-.01-.396-.03-.594a10.12 10.12 0 002.48-2.566z" /></svg></a>
                                    <a href="#" class="social-icon hover:bg-gradient-to-br hover:from-[#833AB4] hover:via-[#FD1D1D] hover:to-[#FCAF45]"><svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z" /></svg></a>
                                </div>
                            </div>
                            <div class="hidden lg:block"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16 bg-[#faf9f6]">
                <div class="max-w-7xl mx-auto px-6 lg:px-8">
                    <div class="stats-grid" id="statsGrid">
                        <div class="stat-item scroll-reveal reveal-delay-1"><div class="stat-value"><span class="counter" data-target="<?php echo $totalStudents; ?>" data-suffix="K+">0</span></div><div class="text-sm text-gray-500 mt-2">Students Enrolled</div></div>
                        <div class="stat-item scroll-reveal reveal-delay-2"><div class="stat-value"><span class="counter" data-target="<?php echo $graduationRate; ?>" data-suffix="%">0</span></div><div class="text-sm text-gray-500 mt-2">Graduation Rate</div></div>
                        <div class="stat-item scroll-reveal reveal-delay-3"><div class="stat-value"><span class="counter" data-target="<?php echo $totalPrograms; ?>" data-suffix="+">0</span></div><div class="text-sm text-gray-500 mt-2">Academic Programs</div></div>
                        <div class="stat-item scroll-reveal reveal-delay-4"><div class="stat-value"><span class="counter" data-target="<?php echo $yearsOfExcellence; ?>" data-suffix="+">0</span></div><div class="text-sm text-gray-500 mt-2">Years of Excellence</div></div>
                    </div>
                </div>
            </section>

            <section class="section-padding portal-section">
                <div class="max-w-7xl mx-auto px-6 lg:px-8">
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <span class="section-tag scroll-reveal reveal-delay-1">ACCESS YOUR PORTAL</span>
                        <h2 class="section-title scroll-reveal reveal-delay-2" style="color: #003366;">Choose Your Portal</h2>
                        <p class="text-gray-600 text-lg scroll-reveal reveal-delay-3">Select your portal to continue to your dashboard</p>
                        <div class="section-divider"></div>
                    </div>

                    <div class="portal-grid">
                        <a href="#" onclick="handlePortalClick(event, 'admin/Admin_Dashboard.php', 'Admin Panel'); return false;" class="portal-card scroll-reveal reveal-delay-1">
                            <div class="portal-icon-wrapper">
                                <svg class="portal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/></svg>
                            </div>
                            <h3 class="portal-title">Admin Panel</h3>
                            <p class="portal-desc">Issue certificates, upload documents, and manage student accounts.</p>
                            <span class="portal-btn">Enter Admin →</span>
                        </a>

                        <a href="#" onclick="handlePortalClick(event, 'Faculty_Dashboard.php', 'Faculty Panel'); return false;" class="portal-card scroll-reveal reveal-delay-2">
                            <div class="portal-icon-wrapper">
                                <svg class="portal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            </div>
                            <h3 class="portal-title">Faculty Panel</h3>
                            <p class="portal-desc">Access student records, manage grades, and submit academic reports.</p>
                            <span class="portal-btn">Enter Faculty →</span>
                        </a>

                        <a href="#" onclick="handlePortalClick(event, 'User_Dashboard.php', 'Student Portal'); return false;" class="portal-card scroll-reveal reveal-delay-3">
                            <div class="portal-icon-wrapper">
                                <svg class="portal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            </div>
                            <h3 class="portal-title">Student Portal</h3>
                            <p class="portal-desc">Log in to view, download, and share your officially issued certificates.</p>
                            <span class="portal-btn">Enter Student →</span>
                        </a>
                    </div>
                </div>
            </section>

            <section id="about" class="section-padding bg-[#faf9f6]">
                <div class="max-w-7xl mx-auto px-6 lg:px-8">
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <span class="section-tag scroll-reveal reveal-delay-1"> ABOUT UMak REGISTRAR </span>
                        <h2 class="section-title scroll-reveal reveal-delay-2">Excellence in Academic Records</h2>
                        <p class="text-gray-600 text-lg scroll-reveal reveal-delay-3">The University of Makati Registrar's Office is committed to providing accurate, timely, and efficient academic records management for all students.</p>
                        <div class="section-divider"></div>
                    </div>
                    <div class="grid lg:grid-cols-2 gap-8 mb-12">
                        <div class="card scroll-reveal reveal-delay-1 text-left"><h3 class="text-2xl font-semibold text-[#003366] mb-3">Our Mission</h3><p class="text-gray-600 leading-relaxed text-lg">To provide accurate, timely, and accessible academic records management while ensuring the highest standards of integrity and service excellence for the UMak community.</p></div>
                        <div class="card scroll-reveal reveal-delay-2 text-left"><h3 class="text-2xl font-semibold text-[#003366] mb-3">Our Vision</h3><p class="text-gray-600 leading-relaxed text-lg">To be the model of excellence in academic records management, recognized for innovation, reliability, and student-centered service in the Philippines.</p></div>
                    </div>
                    <div class="text-center mb-10"><span class="inline-block bg-[#FFD700]/15 text-[#003366] text-sm font-semibold tracking-wider uppercase px-5 py-2 rounded-full border-2 border-[#FFD700]/30 scroll-reveal reveal-delay-1" style="border-radius:0px !important;">Core Values</span></div>
                    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="core-value-card scroll-reveal reveal-delay-1 text-left"><h4 class="text-xl font-bold text-[#003366] mb-2">Integrity</h4><p class="text-gray-600">Upholding the highest ethical standards in records management and student services.</p></div>
                        <div class="core-value-card scroll-reveal reveal-delay-2 text-left"><h4 class="text-xl font-bold text-[#003366] mb-2">Excellence</h4><p class="text-gray-600">Striving for excellence in every transaction, from enrollment to graduation.</p></div>
                        <div class="core-value-card scroll-reveal reveal-delay-3 text-left"><h4 class="text-xl font-bold text-[#003366] mb-2">Innovation</h4><p class="text-gray-600">Continuously improving processes to serve the UMak community better.</p></div>
                        <div class="core-value-card scroll-reveal reveal-delay-4 text-left"><h4 class="text-xl font-bold text-[#003366] mb-2">Service</h4><p class="text-gray-600">Committed to compassionate and professional assistance for every student.</p></div>
                    </div>
                </div>
            </section>

            <section id="services" class="section-padding bg-white">
                <div class="max-w-7xl mx-auto px-6 lg:px-8">
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <span class="section-tag scroll-reveal reveal-delay-1"> OUR SERVICES </span>
                        <h2 class="section-title scroll-reveal reveal-delay-2">What We Offer</h2>
                        <p class="text-gray-600 text-lg scroll-reveal reveal-delay-3">Comprehensive academic services designed to support your educational journey at UMak.</p>
                        <div class="section-divider"></div>
                    </div>
                    <div class="grid md:grid-cols-3 gap-8">
                        <div class="value-prop-card scroll-reveal reveal-delay-1 text-left"><div class="icon-wrapper"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></div><h3 class="text-xl font-bold text-[#003366] mb-3">Enrollment Services</h3><p class="text-gray-500">New student registration, course changes, and program validation for all UMak students.</p></div>
                        <div class="value-prop-card scroll-reveal reveal-delay-2 text-left"><div class="icon-wrapper"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div><h3 class="text-xl font-bold text-[#003366] mb-3">Transcripts &amp; Records</h3><p class="text-gray-500">Official transcripts, diploma requests, and academic verification letters for alumni and current students.</p></div>
                        <div class="value-prop-card scroll-reveal reveal-delay-3 text-left"><div class="icon-wrapper"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div><h3 class="text-xl font-bold text-[#003366] mb-3">Graduation Services</h3><p class="text-gray-500">Graduation applications, clearance processing, and diploma issuance for UMak graduates.</p></div>
                    </div>
                </div>
            </section>

            <section id="contact" class="section-padding bg-[#faf9f6]">
                <div class="max-w-7xl mx-auto px-6 lg:px-8">
                    <div class="text-center max-w-2xl mx-auto mb-16">
                        <span class="section-tag scroll-reveal reveal-delay-1"> REACH US </span>
                        <h2 class="section-title scroll-reveal reveal-delay-2">Get in Touch</h2>
                        <p class="text-gray-600 text-lg scroll-reveal reveal-delay-3">Have questions about enrollment, records, or our services at UMak?</p>
                        <div class="section-divider"></div>
                    </div>
                    <div class="grid lg:grid-cols-3 gap-8 mb-16">
                        <div class="contact-card scroll-reveal reveal-delay-1 text-left"><div class="flex items-start gap-4"><div class="contact-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div><div><h4 class="font-semibold text-[#003366] text-lg mb-2">UMak Campus</h4><p class="text-gray-600 text-sm leading-relaxed">J.P. Rizal Extension, West Rembo<br>Makati City, Metro Manila</p></div></div></div>
                        <div class="contact-card scroll-reveal reveal-delay-2 text-left"><div class="flex items-start gap-4"><div class="contact-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div><div><h4 class="font-semibold text-[#003366] text-lg mb-2">Contact</h4><p class="text-gray-600 text-sm leading-relaxed">(02) 8123-4567<br>+63 917 123 4567</p></div></div></div>
                        <div class="contact-card scroll-reveal reveal-delay-3 text-left"><div class="flex items-start gap-4"><div class="contact-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div><div><h4 class="font-semibold text-[#003366] text-lg mb-2">Email</h4><p class="text-gray-600 text-sm leading-relaxed">registrar@umak.edu.ph<br>admissions@umak.edu.ph</p></div></div></div>
                    </div>
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-2xl shadow-xl border-2 border-[#FFD700]/20 p-8 scroll-reveal reveal-delay-4 text-left" style="border-radius:0px !important;">
                            <h3 class="text-2xl font-bold text-[#003366] mb-6 text-center">Send Us a Message</h3>
                            <form id="contactForm" class="space-y-4">
                                <div class="grid md:grid-cols-2 gap-4"><input type="text" name="first_name" placeholder="First name" class="input-corporate"><input type="text" name="last_name" placeholder="Last name" class="input-corporate"></div>
                                <input type="email" name="email" placeholder="Email address" class="input-corporate">
                                <input type="text" name="subject" placeholder="Subject" class="input-corporate">
                                <textarea rows="4" name="message" placeholder="Your message" class="input-corporate"></textarea>
                                <button type="submit" class="w-full bg-[#003366] hover:bg-[#1a4d7a] text-white font-semibold py-3 px-6 rounded-lg transition transform hover:scale-[1.02]" style="border-radius:0px !important;">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="bg-[#003366] py-12 border-t-4 border-[#FFD700]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                        <div class="col-span-1 md:col-span-2"><span class="text-white font-bold text-2xl tracking-tight">UMak<span style="color: #FFD700;">·Registrar</span></span><p class="text-white/40 text-sm mt-2 max-w-md">Official Records Management · University of Makati</p></div>
                        <div><h4 class="text-white font-semibold mb-4">Quick Links</h4><ul class="space-y-2 text-sm"><li><a href="#about" class="text-white/40 hover:text-[#FFD700] transition">About</a></li><li><a href="#services" class="text-white/40 hover:text-[#FFD700] transition">Services</a></li><li><a href="#contact" class="text-white/40 hover:text-[#FFD700] transition">Contact</a></li></ul></div>
                        <div><h4 class="text-white font-semibold mb-4">Follow Us</h4><ul class="space-y-2 text-sm"><li><a href="#" class="text-white/40 hover:text-[#FFD700] transition">Facebook</a></li><li><a href="#" class="text-white/40 hover:text-[#FFD700] transition">Twitter</a></li><li><a href="#" class="text-white/40 hover:text-[#FFD700] transition">Instagram</a></li></ul></div>
                    </div>
                    <div class="flex flex-col md:flex-row justify-between items-center text-sm pt-8 border-t border-white/10"><span class="text-white/40">© 2026 University of Makati · Registrar's Office · All rights reserved.</span></div>
                </div>
            </footer>
        </div>

        <div id="loginModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4 hidden modal">
            <div class="bg-white max-w-md w-full p-8 relative transform transition-all scale-95 hover:scale-100 border-2 border-[#FFD700]/30" style="border-radius:0px !important;">
                <button id="closeLoginModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                
                <?php if(isset($success_message)): ?>
                    <div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 2px solid #86efac; text-align: center;"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <h3 id="loginModalTitle" class="text-2xl font-bold text-[#003366] mb-6 text-center">Log in to UMak Registrar</h3>

                <form id="loginForm" novalidate>
                    <div class="space-y-4">
                        <input type="text" name="email" id="loginEmailInput" placeholder="Email, Username, Student ID, or Full Name" class="input-corporate" required>
                        <input type="password" name="password" placeholder="Password" class="input-corporate" required>
                        <div class="text-right mb-2">
                            <a href="/Forgot_Password/forgot_password.php" id="forgotPasswordLink" class="text-xs text-[#003366] hover:underline">Forgot Password?</a>
                            <span class="text-gray-300 mx-1" id="unlockSeparator" style="display: none;">|</span>
                            <a href="/ELEC_4_SYSTEM/includes/unlock_page.php" id="unlockAccountLink" class="text-xs text-[#003366] hover:underline" style="display: none;">Unlock Account</a>
                        </div>
                        <div id="loginError" class="text-red-500 text-sm hidden"></div>
                        <button type="submit" id="loginSubmitBtn" class="w-full bg-[#003366] hover:bg-[#1a4d7a] text-white font-semibold py-3 transition" style="border-radius:0px !important;">Log in</button>
                        <p id="registerPromptText" class="text-center text-sm text-gray-500">Don't have an account? <a href="#" id="switchToRegister" class="text-[#003366] font-semibold hover:underline">Get Started</a></p>
                    </div>
                </form>
            </div>
        </div>

        <div id="otpModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4 hidden modal">
            <div class="bg-white max-w-md w-full p-8 relative transform transition-all scale-95 hover:scale-100 border-2 border-[#FFD700]/30" style="border-radius:0px !important;">
                <button id="closeOtpModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                <h3 class="text-2xl font-bold text-[#003366] mb-4 text-center">Verification Code</h3>
                <p id="otpInfoText" class="text-sm text-gray-600 mb-4">We sent a 6-digit code to your email. Enter it below to continue.</p>
                <div id="otpError" class="text-red-500 text-sm hidden mb-3"></div>
                <div id="otpSuccess" class="text-green-600 text-sm hidden mb-3"></div>
                <form id="otpForm">
                    <input type="text" name="otp_code" id="otp_code" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit code" class="input-corporate w-full text-center tracking-[0.35em] text-lg" required>
                    <button type="submit" id="otpSubmitBtn" class="w-full bg-[#003366] hover:bg-[#1a4d7a] text-white font-semibold py-3 transition mt-4" style="border-radius:0px !important;">Verify Code</button>
                </form>
                <div class="mt-4 text-center text-sm text-gray-600">Didn't receive the code? <button id="resendOtpBtn" class="text-[#003366] font-semibold hover:underline">Resend</button></div>
                <p class="text-center text-sm text-gray-500 mt-3"><a href="#" id="otpBackToLogin" class="text-[#003366] hover:underline">Back to login</a></p>
            </div>
        </div>

        <div id="registerModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] hidden modal overflow-y-auto" style="padding: 1rem;">
            <div class="min-h-screen flex items-center justify-center">
                <div class="bg-white max-w-md w-full p-6 relative my-8 transform transition-all scale-95 hover:scale-100 border-2 border-[#FFD700]/30" style="border-radius:0px !important;">
                    <button id="closeRegisterModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl z-10">&times;</button>
                    <div class="mb-6 text-center"><h3 class="text-2xl font-bold text-[#003366]">Create Account</h3><p class="text-sm text-gray-500 mt-1">Sign up to get started at UMak</p></div>
                    <form id="registerForm" action="../includes/register.php" method="POST" class="space-y-4" novalidate>
                        <div><label for="full_name" class="text-sm font-medium text-gray-700 block mb-1">Full Name <span class="text-red-500">*</span></label><input type="text" name="full_name" id="full_name" class="input-corporate w-full" required><div class="field-error text-red-500 text-xs hidden" id="full_name_error"></div></div>
                        <div><label for="birthdate" class="text-sm font-medium text-gray-700 block mb-1">Date of Birth <span class="text-red-500">*</span></label><input type="date" name="birthdate" id="birthdate" class="input-corporate w-full" required><div class="field-error text-red-500 text-xs hidden" id="birthdate_error"></div></div>
                        <div><label for="register_email" class="text-sm font-medium text-gray-700 block mb-1">Email Address <span class="text-red-500">*</span></label><input type="email" name="email" id="register_email" placeholder="name@umak.edu.ph" class="input-corporate w-full" required><div class="field-error text-red-500 text-xs hidden" id="email_error"></div></div>
                        <div><label for="mobile" class="text-sm font-medium text-gray-700 block mb-1">Mobile Number <span class="text-red-500">*</span></label><input type="tel" name="mobile" id="mobile" placeholder="09XXXXXXXXX" class="input-corporate w-full" required><div class="field-error text-red-500 text-xs hidden" id="mobile_error"></div></div>
                        <div><label for="user_id_number" class="text-sm font-medium text-gray-700 block mb-1">Student ID <span class="text-red-500">*</span></label><input type="text" name="user_id_number" id="user_id_number" placeholder="e.g., K11832292" class="input-corporate w-full" required maxlength="9"><div class="field-error text-red-500 text-xs hidden" id="user_id_number_error"></div></div>
                        <div><label for="user_role" class="text-sm font-medium text-gray-700 block mb-1">User Type <span class="text-red-500">*</span></label><select name="role" id="user_role" class="input-corporate w-full" required><option value="">Select student type...</option><option value="student">Current Student</option><option value="alumni">Alumni</option></select><div class="field-error text-red-500 text-xs hidden" id="user_role_error"></div></div>

                        <!-- NEW FIELDS: College, Course, Year Level / Graduation Year, Section -->
                        <div>
                            <label for="college" class="text-sm font-medium text-gray-700 block mb-1">College / Institute <span class="text-red-500">*</span></label>
                            <select name="college" id="college" class="input-corporate w-full" required>
                                <option value="">Select college/institute...</option>
                                <option value="College of Continuing, Advanced and Professional Studies (CCAPS)">College of Continuing, Advanced and Professional Studies (CCAPS)</option>
                                <option value="Institute of Arts and Design (IAD)">Institute of Arts and Design (IAD)</option>
                                <option value="College of Business Administration (CBA)">College of Business Administration (CBA)</option>
                                <option value="Institute of Accountancy and Finance (IOA)">Institute of Accountancy and Finance (IOA)</option>
                                <option value="College of Computing and Information Sciences (CCIS)">College of Computing and Information Sciences (CCIS)</option>
                                <option value="College of Human Kinetics (CHK)">College of Human Kinetics (CHK)</option>
                                <option value="College of Governance and Public Policy (CGPP)">College of Governance and Public Policy (CGPP)</option>
                                <option value="Institute of Nursing (ION)">Institute of Nursing (ION)</option>
                                <option value="Institute of Pharmacy (IOP)">Institute of Pharmacy (IOP)</option>
                                <option value="Institute of Imaging Health Sciences (IIHS)">Institute of Imaging Health Sciences (IIHS)</option>
                                <option value="College of Innovative Teacher Education (CITE)">College of Innovative Teacher Education (CITE)</option>
                                <option value="College of Science (COS)">College of Science (COS)</option>
                                <option value="College of Tourism and Hospitality Management (CTHM)">College of Tourism and Hospitality Management (CTHM)</option>
                                <option value="College of Engineering and Technology (CET)">College of Engineering and Technology (CET)</option>
                                <option value="School of Law (SOL)">School of Law (SOL)</option>
                                <option value="College of Innovative Teacher Education – Higher School ng UMak (CITE-HSU)">College of Innovative Teacher Education – Higher School ng UMak (CITE-HSU)</option>
                                <option value="Institute for Disaster and Emergency Management (IDEM)">Institute for Disaster and Emergency Management (IDEM)</option>
                                <option value="Institute for Social Development and Nation Building (ISDNB)">Institute for Social Development and Nation Building (ISDNB)</option>
                                <option value="Institute for Technical Education and Skills Training (ITEST)">Institute for Technical Education and Skills Training (ITEST)</option>
                            </select>
                            <div class="field-error text-red-500 text-xs hidden" id="college_error"></div>
                        </div>

                        <div>
                            <label for="course" class="text-sm font-medium text-gray-700 block mb-1">Course / Program <span class="text-red-500">*</span></label>
                            <select name="course" id="course" class="input-corporate w-full" required>
                                <option value="">Select course/program...</option>

                                <optgroup label="College of Continuing, Advanced and Professional Studies (CCAPS)">
                                    <option value="Bachelor of Arts in Political Science Major in Local Government Administration">Bachelor of Arts in Political Science Major in Local Government Administration</option>
                                    <option value="Bachelor in Automotive Technology">Bachelor in Automotive Technology</option>
                                    <option value="Bachelor in Industrial Facilities Technology Management">Bachelor in Industrial Facilities Technology Management</option>
                                    <option value="Bachelor of Science in Business Administration Major in Human Resource Development Management">Bachelor of Science in Business Administration Major in Human Resource Development Management</option>
                                    <option value="Bachelor of Science in Entrepreneurship">Bachelor of Science in Entrepreneurship</option>
                                    <option value="Master of Arts in Education Major in Administration and Supervision">Master of Arts in Education Major in Administration and Supervision</option>
                                    <option value="Master of Arts in Education Major in Guidance and Counselling">Master of Arts in Education Major in Guidance and Counselling</option>
                                    <option value="Master of Arts in Nursing">Master of Arts in Nursing</option>
                                    <option value="Master in Business Administration">Master in Business Administration</option>
                                    <option value="Master in Public Administration">Master in Public Administration</option>
                                    <option value="Doctor of Education">Doctor of Education</option>
                                    <option value="Doctor of Philosophy in Leadership">Doctor of Philosophy in Leadership</option>
                                    <option value="Doctor of Public Administration">Doctor of Public Administration</option>
                                </optgroup>

                                <optgroup label="Institute of Arts and Design (IAD)">
                                    <option value="Bachelor in Multimedia Arts major in Animation">Bachelor in Multimedia Arts major in Animation</option>
                                    <option value="Bachelor in Multimedia Arts major in Film Production">Bachelor in Multimedia Arts major in Film Production</option>
                                    <option value="Associate in Customer Service Communication">Associate in Customer Service Communication</option>
                                </optgroup>

                                <optgroup label="College of Business Administration (CBA)">
                                    <option value="Bachelor of Science in Office Administration">Bachelor of Science in Office Administration</option>
                                    <option value="Bachelor of Science in Entrepreneurial Management">Bachelor of Science in Entrepreneurial Management</option>
                                    <option value="Bachelor of Science in Business Administration major in Marketing Management">Bachelor of Science in Business Administration major in Marketing Management</option>
                                    <option value="Bachelor of Science in Business Administration major in Human Resource Development Management">Bachelor of Science in Business Administration major in Human Resource Development Management</option>
                                    <option value="Bachelor of Science in Business Administration major in Supply Management">Bachelor of Science in Business Administration major in Supply Management</option>
                                    <option value="Bachelor of Science in Business Administration major in Building and Property Management">Bachelor of Science in Business Administration major in Building and Property Management</option>
                                    <option value="Associate in Sales Management">Associate in Sales Management</option>
                                    <option value="Associate in Office Management Technology">Associate in Office Management Technology</option>
                                    <option value="Associate in Entrepreneurship">Associate in Entrepreneurship</option>
                                    <option value="Associate in Supply Management">Associate in Supply Management</option>
                                    <option value="Associate in Building and Property Management">Associate in Building and Property Management</option>
                                </optgroup>

                                <optgroup label="Institute of Accountancy and Finance (IOA)">
                                    <option value="Bachelor of Science in Accountancy (BSA)">Bachelor of Science in Accountancy (BSA)</option>
                                    <option value="Bachelor of Science in Management Accounting (BSMA)">Bachelor of Science in Management Accounting (BSMA)</option>
                                </optgroup>

                                <optgroup label="College of Computing and Information Sciences (CCIS)">
                                    <option value="Bachelor of Science in Computer Science (Application Development Elective Track)">Bachelor of Science in Computer Science (Application Development Elective Track)</option>
                                    <option value="Bachelor of Science in Information Technology (Information and Network Security Elective Track)">Bachelor of Science in Information Technology (Information and Network Security Elective Track)</option>
                                    <option value="Diploma in Application Development">Diploma in Application Development</option>
                                    <option value="Diploma in Computer Network Administration">Diploma in Computer Network Administration</option>
                                </optgroup>

                                <optgroup label="College of Human Kinetics (CHK)">
                                    <option value="Bachelor of Science in Exercise and Sports Science major in Fitness and Sports Management">Bachelor of Science in Exercise and Sports Science major in Fitness and Sports Management</option>
                                </optgroup>

                                <optgroup label="College of Governance and Public Policy (CGPP)">
                                    <option value="Bachelor of Arts in Political Science major in Paralegal Studies">Bachelor of Arts in Political Science major in Paralegal Studies</option>
                                    <option value="Bachelor of Arts in Political Science major in Policy Management">Bachelor of Arts in Political Science major in Policy Management</option>
                                    <option value="Bachelor of Arts in Political Science major in Local Government Administration">Bachelor of Arts in Political Science major in Local Government Administration</option>
                                </optgroup>

                                <optgroup label="Institute of Nursing (ION)">
                                    <option value="Bachelor of Science in Nursing">Bachelor of Science in Nursing</option>
                                    <option value="Master of Arts in Nursing">Master of Arts in Nursing</option>
                                </optgroup>

                                <optgroup label="Institute of Pharmacy (IOP)">
                                    <option value="Bachelor of Science in Pharmacy">Bachelor of Science in Pharmacy</option>
                                    <option value="Associate in Applied Science in Pharmacy Technology">Associate in Applied Science in Pharmacy Technology</option>
                                </optgroup>

                                <optgroup label="Institute of Imaging Health Sciences (IIHS)">
                                    <option value="Bachelor of Science in Radiologic Technology">Bachelor of Science in Radiologic Technology</option>
                                    <option value="Master of Science in Radiologic Technology">Master of Science in Radiologic Technology</option>
                                </optgroup>

                                <optgroup label="College of Innovative Teacher Education (CITE)">
                                    <option value="Bachelor of Elementary Education">Bachelor of Elementary Education</option>
                                    <option value="Bachelor of Secondary Education Major in English">Bachelor of Secondary Education Major in English</option>
                                    <option value="Bachelor of Secondary Education Major in Mathematics">Bachelor of Secondary Education Major in Mathematics</option>
                                    <option value="Bachelor of Secondary Education Major in Social Studies">Bachelor of Secondary Education Major in Social Studies</option>
                                </optgroup>

                                <optgroup label="College of Science (COS)">
                                    <option value="Bachelor of Science in Psychology">Bachelor of Science in Psychology</option>
                                </optgroup>

                                <optgroup label="College of Tourism and Hospitality Management (CTHM)">
                                    <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                    <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                    <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                </optgroup>

                                <optgroup label="College of Engineering and Technology (CET)">
                                    <option value="Bachelor of Science in Civil Engineering (Construction Engineering and Management)">Bachelor of Science in Civil Engineering (Construction Engineering and Management)</option>
                                    <option value="Bachelor of Science in Engineering Technology major in Electrical Technology">Bachelor of Science in Engineering Technology major in Electrical Technology</option>
                                    <option value="Bachelor of Science in Engineering Technology major in Electronics Technology">Bachelor of Science in Engineering Technology major in Electronics Technology</option>
                                    <option value="Diploma in Electrical Technology">Diploma in Electrical Technology</option>
                                    <option value="Diploma in Industrial Facilities Technology">Diploma in Industrial Facilities Technology</option>
                                    <option value="Diploma in Industrial Facilities Technology major in Service Mechanics">Diploma in Industrial Facilities Technology major in Service Mechanics</option>
                                    <option value="Associate in Electronics Technology">Associate in Electronics Technology</option>
                                </optgroup>

                                <optgroup label="Institute for Disaster and Emergency Management (IDEM)">
                                    <option value="Bachelor of Science in Social Work (BSSW)">Bachelor of Science in Social Work (BSSW)</option>
                                    <option value="Bachelor of Science in Disaster Risk Management (BSDRM)">Bachelor of Science in Disaster Risk Management (BSDRM)</option>
                                </optgroup>

                                <optgroup label="School of Law (SOL)">
                                    <option value="Juris Doctor with Thesis">Juris Doctor with Thesis</option>
                                </optgroup>

                            </select>
                            <div class="field-error text-red-500 text-xs hidden" id="course_error"></div>
                        </div>

                        <div id="year_level_wrapper" class="conditional-field">
                            <label for="year_level" class="text-sm font-medium text-gray-700 block mb-1">Year Level <span class="text-red-500">*</span></label>
                            <select name="year_level" id="year_level" class="input-corporate w-full">
                                <option value="">Select year level...</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                            <div class="field-error text-red-500 text-xs hidden" id="year_level_error"></div>
                        </div>

                        <div id="section_wrapper" class="conditional-field">
                            <label for="section" class="text-sm font-medium text-gray-700 block mb-1">Section</label>
                            <input type="text" name="section" id="section" placeholder="e.g., A, B, C" class="input-corporate w-full">
                            <div class="field-error text-red-500 text-xs hidden" id="section_error"></div>
                        </div>

                        <div id="graduation_year_wrapper" class="conditional-field" style="display: none;">
                            <label for="graduation_year" class="text-sm font-medium text-gray-700 block mb-1">Graduation Year <span class="text-red-500">*</span></label>
                            <input type="text" name="graduation_year" id="graduation_year" placeholder="e.g., 2024" class="input-corporate w-full">
                            <div class="field-error text-red-500 text-xs hidden" id="graduation_year_error"></div>
                        </div>

                        <div><label for="password" class="text-sm font-medium text-gray-700 block mb-1">Password <span class="text-red-500">*</span></label><input type="password" name="password" id="password" placeholder="Enter your password" class="input-corporate w-full" required><div class="field-error text-red-500 text-xs hidden" id="password_error"></div></div>
                        <div><label for="confirm_password" class="text-sm font-medium text-gray-700 block mb-1">Confirm Password <span class="text-red-500">*</span></label><input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter your password" class="input-corporate w-full" required><div class="field-error text-red-500 text-xs hidden" id="confirm_password_error"></div></div>
                        <div class="bg-gray-50 p-3 border border-gray-200"><p class="text-xs text-gray-600 font-medium mb-1">Password must contain:</p><ul class="text-xs text-gray-500 space-y-0.5 list-disc list-inside"><li>8+ characters</li><li>At least 1 uppercase letter</li><li>At least 1 lowercase letter</li><li>At least 1 number</li><li>At least 1 special character (!@#$%^&*)</li></ul></div>
                        <div class="flex items-start gap-2"><input type="checkbox" id="terms" name="terms" class="mt-1" required><label for="terms" class="text-sm text-gray-600">I agree to the <a href="#" id="termsLink" class="text-[#003366] font-semibold hover:underline">Terms and Conditions</a> <span class="text-red-500">*</span></label></div>
                        <div id="registerError" class="text-red-500 text-sm hidden"></div>
                        <div id="registerSuccess" class="text-green-500 text-sm hidden"></div>
                        <button type="submit" id="registerSubmitBtn" class="w-full bg-[#003366] hover:bg-[#1a4d7a] text-white font-semibold py-3 transition transform hover:scale-[1.02]" style="border-radius:0px !important;">Sign Up</button>
                        <p class="text-center text-sm text-gray-500">Already have an account? <a href="#" id="switchToLogin" class="text-[#003366] font-semibold hover:underline">Log in</a></p>
                    </form>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const menuBtn = document.getElementById('mobile-menu-btn');
                const mobileMenu = document.getElementById('mobile-menu');
                if (menuBtn) menuBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));

                const loginModal = document.getElementById('loginModal');
                const otpModal = document.getElementById('otpModal');
                const registerModal = document.getElementById('registerModal');
                const closeLogin = document.getElementById('closeLoginModal');
                const closeOtp = document.getElementById('closeOtpModal');
                const closeRegister = document.getElementById('closeRegisterModal');
                const switchToRegister = document.getElementById('switchToRegister');
                const switchToLogin = document.getElementById('switchToLogin');
                const forgotPasswordLink = document.getElementById('forgotPasswordLink');

                window.openLoginModal = function() {
                    if (loginModal) loginModal.classList.remove('hidden');
                    if (otpModal) otpModal.classList.add('hidden');
                    if (registerModal) registerModal.classList.add('hidden');
                    const loginError = document.getElementById('loginError');
                    if (loginError) loginError.classList.add('hidden');
                    const loginBtn = document.getElementById('loginSubmitBtn');
                    if (loginBtn) { loginBtn.disabled = false; loginBtn.innerHTML = 'Log in'; }

                    const target = window.pendingPortalTarget || '';
                    const isAdmin = target.includes('Admin');
                    const isFaculty = target.includes('Faculty');

                    // Show "Get Started" by default maliban kung Admin ang target
                    const registerPrompt = document.getElementById('registerPromptText');
                    if (registerPrompt) {
                        registerPrompt.style.display = isAdmin ? 'none' : 'block';
                    }

                    // Placeholder: "Email" lang para sa Admin at Faculty, mahabang text para sa Student
                    const emailInput = document.getElementById('loginEmailInput');
                    if (emailInput) {
                        emailInput.placeholder = (isAdmin || isFaculty)
                            ? 'Email'
                            : 'Email, Username, Student ID, or Full Name';
                    }
                }

                window.handlePortalClick = function(event, targetUrl, portalName) {
                    event.preventDefault();
                    window.pendingPortalTarget = targetUrl;

                    const titleEl = document.getElementById('loginModalTitle');
                    if (titleEl) titleEl.textContent = 'Log in to ' + (portalName || 'UMak Registrar');

                    const isAdmin = targetUrl && targetUrl.includes('Admin');
                    const isFaculty = targetUrl && targetUrl.includes('Faculty');

                    // Hide "Get Started" kapag Admin Panel (hindî pwedeng mag-register ng admin)
                    const registerPrompt = document.getElementById('registerPromptText');
                    if (registerPrompt) {
                        registerPrompt.style.display = isAdmin ? 'none' : 'block';
                    }

                    // Placeholder: "Email" lang para sa Admin at Faculty
                    const emailInput = document.getElementById('loginEmailInput');
                    if (emailInput) {
                        emailInput.placeholder = (isAdmin || isFaculty)
                            ? 'Email'
                            : 'Email, Username, Student ID, or Full Name';
                    }

                    openLoginModal();
                }

                function openOtpModal(destinationEmail = null) {
                    if (!otpModal) return;
                    otpModal.classList.remove('hidden');
                    if (loginModal) loginModal.classList.add('hidden');
                    if (registerModal) registerModal.classList.add('hidden');
                    const otpError = document.getElementById('otpError');
                    const otpSuccess = document.getElementById('otpSuccess');
                    const otpInfoText = document.getElementById('otpInfoText');
                    const otpSubmitBtn = document.getElementById('otpSubmitBtn');
                    if (otpError) otpError.classList.add('hidden');
                    if (otpSuccess) otpSuccess.classList.add('hidden');
                    if (otpSubmitBtn) { otpSubmitBtn.disabled = false; otpSubmitBtn.innerHTML = 'Verify Code'; }
                    if (otpInfoText) otpInfoText.textContent = destinationEmail ? `A 6-digit code was sent to ${destinationEmail}. Enter it below to continue.` : 'A 6-digit code was sent to your email. Enter it below to continue.';
                    const otpInput = document.getElementById('otp_code');
                    if (otpInput) otpInput.value = '';
                }

                function closeOtpModal() { if (otpModal) otpModal.classList.add('hidden'); }

                function openRegisterModal() {
                    if (registerModal) registerModal.classList.remove('hidden');
                    if (loginModal) loginModal.classList.add('hidden');
                    if (otpModal) otpModal.classList.add('hidden');
                    const registerError = document.getElementById('registerError');
                    const registerSuccess = document.getElementById('registerSuccess');
                    if (registerError) registerError.classList.add('hidden');
                    if (registerSuccess) registerSuccess.classList.add('hidden');
                    const form = document.getElementById('registerForm');
                    if (form) form.reset();
                    toggleStudentTypeFields('student');
                }

                window.closeLoginModal = function() { 
                    if (loginModal) loginModal.classList.add('hidden'); 
                    window.pendingPortalTarget = null;
                    const registerPrompt = document.getElementById('registerPromptText');
                    if (registerPrompt) registerPrompt.style.display = 'block';
                    const emailInput = document.getElementById('loginEmailInput');
                    if (emailInput) emailInput.placeholder = 'Email, Username, Student ID, or Full Name';
                }
                window.closeRegisterModal = function() { if (registerModal) registerModal.classList.add('hidden'); }
                window.closeOtpModal = closeOtpModal;

                if (forgotPasswordLink) {
                    forgotPasswordLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        closeLoginModal();
                        window.location.href = '/ELEC_4_SYSTEM/Forgot_Password/forgot_password.php';
                    });
                }

                const unlockAccountLink = document.getElementById('unlockAccountLink');
                if (unlockAccountLink) {
                    unlockAccountLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        closeLoginModal();
                        window.location.href = '/ELEC_4_SYSTEM/includes/unlock_page.php';
                    });
                }

                function scrollToPortalSection() {
                    const portalSection = document.querySelector('.portal-section');
                    if (portalSection) {
                        const headerOffset = 80;
                        const elementPosition = portalSection.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                        
                        setTimeout(() => {
                            const portalCards = document.querySelectorAll('.portal-card');
                            portalCards.forEach((card, i) => {
                                setTimeout(() => {
                                    card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                                    card.style.transform = 'translateY(-10px) scale(1.03)';
                                    card.style.borderColor = '#FFD700';
                                    card.style.boxShadow = '0 20px 50px rgba(0, 51, 102, 0.4), 0 0 0 2px rgba(255, 215, 0, 0.5)';
                                    
                                    setTimeout(() => {
                                        card.style.transform = '';
                                        card.style.borderColor = '';
                                        card.style.boxShadow = '';
                                    }, 800);
                                }, i * 150);
                            });
                        }, 600);
                    }
                }

                const navLoginBtn = document.getElementById('navLoginBtn');
                const mobileNavLoginBtn = document.getElementById('mobileNavLoginBtn');
                
                if (navLoginBtn) {
                    navLoginBtn.addEventListener('click', (e) => { 
                        e.preventDefault(); 
                        scrollToPortalSection(); 
                    });
                }
                if (mobileNavLoginBtn) {
                    mobileNavLoginBtn.addEventListener('click', (e) => { 
                        e.preventDefault(); 
                        scrollToPortalSection(); 
                    });
                }

                const joinNowBtn = document.getElementById('join-now-btn');
                if (joinNowBtn) joinNowBtn.addEventListener('click', (e) => { e.preventDefault(); openRegisterModal(); });

                if (switchToRegister) switchToRegister.addEventListener('click', (e) => { e.preventDefault(); openRegisterModal(); });
                if (switchToLogin) switchToLogin.addEventListener('click', (e) => { e.preventDefault(); openLoginModal(); });

                if (closeLogin) closeLogin.addEventListener('click', closeLoginModal);
                if (closeOtp) closeOtp.addEventListener('click', closeOtpModal);
                if (closeRegister) closeRegister.addEventListener('click', closeRegisterModal);

                const otpBackToLogin = document.getElementById('otpBackToLogin');
                if (otpBackToLogin) otpBackToLogin.addEventListener('click', (e) => { e.preventDefault(); closeOtpModal(); openLoginModal(); });

                if (loginModal) loginModal.addEventListener('click', (e) => { if (e.target === loginModal) closeLoginModal(); });
                if (otpModal) otpModal.addEventListener('click', (e) => { if (e.target === otpModal) closeOtpModal(); });
                if (registerModal) registerModal.addEventListener('click', (e) => { if (e.target === registerModal) closeRegisterModal(); });

                function showFieldError(fieldId, message) {
                    const errorDiv = document.getElementById(fieldId + '_error');
                    if (errorDiv) { errorDiv.textContent = message; errorDiv.classList.remove('hidden'); }
                    const field = document.getElementById(fieldId);
                    if (field) { field.classList.add('input-error'); field.classList.remove('input-success'); }
                }
                
                function hideFieldError(fieldId) {
                    const errorDiv = document.getElementById(fieldId + '_error');
                    if (errorDiv) errorDiv.classList.add('hidden');
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.classList.remove('input-error');
                        field.classList.add('input-success');
                        setTimeout(() => field.classList.remove('input-success'), 2000);
                    }
                }
                
                function validateFullName(value) {
                    if (!value || value.trim() === '') { showFieldError('full_name', 'Full name is required'); return false; }
                    if (!/^[a-zA-Z\s\-']+$/.test(value)) { showFieldError('full_name', 'Name can only contain letters, spaces, hyphens, and apostrophes'); return false; }
                    if (value.length < 2) { showFieldError('full_name', 'Name must be at least 2 characters'); return false; }
                    if (value.length > 50) { showFieldError('full_name', 'Name cannot exceed 50 characters'); return false; }
                    hideFieldError('full_name'); return true;
                }
                
                function validateBirthdate(value) {
                    if (!value) { showFieldError('birthdate', 'Birthdate is required'); return false; }
                    const birthDate = new Date(value); const today = new Date();
                    let age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
                    if (age < 18) { showFieldError('birthdate', 'You must be at least 18 years old'); return false; }
                    if (age > 100) { showFieldError('birthdate', 'Please enter a valid birthdate'); return false; }
                    hideFieldError('birthdate'); return true;
                }
                
                function validateEmail(email) {
                    if (!email || email.trim() === '') { showFieldError('register_email', 'Email is required'); return false; }
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showFieldError('register_email', 'Invalid email format'); return false; }
                    hideFieldError('register_email'); return true;
                }
                
                function validateMobile(phone) {
                    if (!phone || phone.trim() === '') { showFieldError('mobile', 'Mobile number is required'); return false; }
                    let cleaned = phone.replace(/[\s\-+]/g, '');
                    if (!/^(09|\+639|639)\d{9}$/.test(cleaned)) { showFieldError('mobile', 'Invalid mobile number. Must be a valid Philippine number (e.g., 09171234567)'); return false; }
                    hideFieldError('mobile'); return true;
                }
                
                function validatePassword(password) {
                    if (!password) { showFieldError('password', 'Password is required'); return false; }
                    if (password.length < 8) { showFieldError('password', 'Password must be at least 8 characters'); return false; }
                    if (!/[A-Z]/.test(password)) { showFieldError('password', 'Password must contain at least one uppercase letter'); return false; }
                    if (!/[a-z]/.test(password)) { showFieldError('password', 'Password must contain at least one lowercase letter'); return false; }
                    if (!/[0-9]/.test(password)) { showFieldError('password', 'Password must contain at least one number'); return false; }
                    if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) { showFieldError('password', 'Password must contain at least one special character (!@#$%^&*)'); return false; }
                    hideFieldError('password'); return true;
                }
                
                function validateConfirmPassword(password, confirm) {
                    if (password !== confirm) { showFieldError('confirm_password', 'Passwords do not match'); return false; }
                    hideFieldError('confirm_password'); return true;
                }

                function validateUserId(value) {
                    if (!value || value.trim() === '') { showFieldError('user_id_number', 'Student ID is required'); return false; }
                    if (value.length < 5) { showFieldError('user_id_number', 'Student ID must be at least 5 characters'); return false; }
                    if (!/^[Kk]\d{8}$/.test(value.trim())) { showFieldError('user_id_number', 'Student ID must be in format K + 8 digits (e.g., K11832292)'); return false; }
                    hideFieldError('user_id_number'); return true;
                }

                function validateCourse(value) {
                    if (!value || value.trim() === '') { showFieldError('course', 'Course/Program is required'); return false; }
                    hideFieldError('course'); return true;
                }

                function validateRole(value) {
                    if (!value || value === '') { showFieldError('user_role', 'Please select your user type'); return false; }
                    hideFieldError('user_role'); return true;
                }

                function toggleStudentTypeFields(type) {
                    const yearWrap = document.getElementById('year_level_wrapper');
                    const sectionWrap = document.getElementById('section_wrapper');
                    const gradWrap = document.getElementById('graduation_year_wrapper');

                    if (type === 'alumni') {
                        if (yearWrap) yearWrap.style.display = 'none';
                        if (sectionWrap) sectionWrap.style.display = 'none';
                        if (gradWrap) gradWrap.style.display = 'block';
                    } else {
                        if (yearWrap) yearWrap.style.display = 'block';
                        if (sectionWrap) sectionWrap.style.display = 'block';
                        if (gradWrap) gradWrap.style.display = 'none';
                    }
                }

                const roleSelectEl = document.getElementById('user_role');
                if (roleSelectEl) {
                    roleSelectEl.addEventListener('change', function() {
                        validateRole(this.value);
                        if (this.value === 'alumni') {
                            toggleStudentTypeFields('alumni');
                        } else {
                            toggleStudentTypeFields('student');
                        }
                    });
                }

                const fullNameInput = document.getElementById('full_name');
                if (fullNameInput) fullNameInput.addEventListener('blur', function() { validateFullName(this.value); });
                const birthdateInput = document.getElementById('birthdate');
                if (birthdateInput) birthdateInput.addEventListener('blur', function() { validateBirthdate(this.value); });
                const emailInput = document.getElementById('register_email');
                if (emailInput) emailInput.addEventListener('blur', function() { validateEmail(this.value); });
                const mobileInput = document.getElementById('mobile');
                if (mobileInput) mobileInput.addEventListener('blur', function() { validateMobile(this.value); });
                const passwordInput = document.getElementById('password');
                if (passwordInput) {
                    passwordInput.addEventListener('blur', function() {
                        validatePassword(this.value);
                        const confirmInput = document.getElementById('confirm_password');
                        if (confirmInput && confirmInput.value) validateConfirmPassword(this.value, confirmInput.value);
                    });
                }
                const confirmPasswordInput = document.getElementById('confirm_password');
                if (confirmPasswordInput) {
                    confirmPasswordInput.addEventListener('blur', function() {
                        const password = document.getElementById('password').value;
                        validateConfirmPassword(password, this.value);
                    });
                }

                const userIdInput = document.getElementById('user_id_number');
                if (userIdInput) userIdInput.addEventListener('blur', function() { validateUserId(this.value); });

                const courseSelect = document.getElementById('course');
                if (courseSelect) courseSelect.addEventListener('change', function() { validateCourse(this.value); });

                const termsLink = document.getElementById('termsLink');
                if (termsLink) {
                    termsLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        alert('Terms and Conditions:\n\n1. You must be a legitimate UMak student, faculty, or staff member.\n2. You agree to provide accurate and truthful information.\n3. You are responsible for maintaining the confidentiality of your account.\n4. You agree to use the system only for legitimate academic purposes.\n5. Any misuse of the system may result in disciplinary action.\n6. The Registrar\'s Office reserves the right to verify all information provided.');
                    });
                }

                let countdownInterval = null;
                function startCountdown(seconds, button, errorDiv) {
                    let remaining = seconds;
                    button.disabled = true;
                    errorDiv.classList.remove('hidden');
                    if (countdownInterval) clearInterval(countdownInterval);
                    countdownInterval = setInterval(() => {
                        remaining--;
                        if (remaining <= 0) {   
                            clearInterval(countdownInterval);
                            countdownInterval = null;
                            button.disabled = false;
                            button.innerHTML = 'Log in';
                            errorDiv.textContent = 'You can now try again.';
                            setTimeout(() => errorDiv.classList.add('hidden'), 3000);
                        } else {
                            button.innerHTML = `Locked (${remaining}s)`;
                            errorDiv.textContent = `Too many failed attempts. Please wait ${remaining} seconds before trying again.`;
                        }
                    }, 1000);
                }

                const loginForm = document.getElementById('loginForm');
                const loginError = document.getElementById('loginError');
                const loginSubmitBtn = document.getElementById('loginSubmitBtn');
                const unlockSeparator = document.getElementById('unlockSeparator');

                if (unlockSeparator) unlockSeparator.style.display = 'none';

                if (loginForm) {
                    loginForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        if (loginError) loginError.classList.add('hidden');
                        if (loginSubmitBtn) { loginSubmitBtn.disabled = true; loginSubmitBtn.innerHTML = 'Logging in...'; }
                        
                        const formData = new FormData(loginForm);
                        const payload = Object.fromEntries(formData.entries());
                        
                        if (window.pendingPortalTarget) {
                            if (window.pendingPortalTarget.includes('Admin')) {
                                payload.portal = 'admin';
                            } else if (window.pendingPortalTarget.includes('Faculty')) {
                                payload.portal = 'faculty';
                            } else if (window.pendingPortalTarget.includes('User')) {
                                payload.portal = 'student';
                            }
                        } else {
                            payload.portal = 'student';
                        }
                        
                        fetch('../includes/login.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success && result.requires_otp) {
                                if (loginError) { loginError.textContent = result.message || 'Enter the OTP sent to your email.'; loginError.classList.remove('hidden'); }
                                openOtpModal(formData.get('email'));
                                if (loginSubmitBtn) { loginSubmitBtn.disabled = false; loginSubmitBtn.innerHTML = 'Log in'; }
                            } else if (result.success && result.redirect) {
                                window.location.href = result.redirect;
                            } else {
                                if (result.message && result.message.includes('locked')) {
                                    if (unlockSeparator) unlockSeparator.style.display = 'inline';
                                    if (unlockAccountLink) unlockAccountLink.style.display = 'inline';
                                } else {
                                    if (unlockSeparator) unlockSeparator.style.display = 'none';
                                    if (unlockAccountLink) unlockAccountLink.style.display = 'none';
                                }
                                if (loginError) { loginError.textContent = result.message || 'Login failed. Please try again.'; loginError.classList.remove('hidden'); }
                                if (loginSubmitBtn) { loginSubmitBtn.disabled = false; loginSubmitBtn.innerHTML = 'Log in'; }
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            if (loginError) { loginError.textContent = 'Connection error. Please try again.'; loginError.classList.remove('hidden'); }
                            if (loginSubmitBtn) { loginSubmitBtn.disabled = false; loginSubmitBtn.innerHTML = 'Log in'; }
                        });
                    });
                }
                
                const registerForm = document.getElementById('registerForm');
                const registerError = document.getElementById('registerError');
                const registerSuccess = document.getElementById('registerSuccess');
                const registerSubmitBtn = document.getElementById('registerSubmitBtn');

                if (registerForm) {
                    registerForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        if (registerError) registerError.classList.add('hidden');
                        if (registerSuccess) registerSuccess.classList.add('hidden');

                        // Validate course bago mag-submit
                        const courseVal = document.getElementById('course').value;
                        if (!validateCourse(courseVal)) {
                            return;
                        }

                        const formData = new FormData(this);
                        if (registerSubmitBtn) { registerSubmitBtn.disabled = true; registerSubmitBtn.innerHTML = '<span class="spinner"></span> Creating account...'; }
                        
                        try {
                            const response = await fetch('../includes/register.php', { method: 'POST', body: formData });
                            const text = await response.text();
                            let result;
                            try { result = JSON.parse(text); } 
                            catch (parseError) { throw new Error('Server returned invalid JSON: ' + text.substring(0, 100)); }
                            
                            if (result.success) {
                                if (registerSuccess) { registerSuccess.textContent = result.message || 'Account created successfully!'; registerSuccess.classList.remove('hidden'); }
                                this.reset();
                                setTimeout(() => { if (typeof closeRegisterModal === 'function') closeRegisterModal(); if (typeof openLoginModal === 'function') openLoginModal(); }, 2000);
                            } else {
                                if (registerError) { registerError.textContent = result.message || 'Registration failed. Please try again.'; registerError.classList.remove('hidden'); }
                                if (registerSubmitBtn) { registerSubmitBtn.disabled = false; registerSubmitBtn.innerHTML = 'Sign Up'; }
                            }
                        } catch (error) {
                            if (registerError) { registerError.textContent = 'Error: ' + error.message; registerError.classList.remove('hidden'); }
                            if (registerSubmitBtn) { registerSubmitBtn.disabled = false; registerSubmitBtn.innerHTML = 'Sign Up'; }
                        }
                    });
                }

                const otpForm = document.getElementById('otpForm');
                const otpError = document.getElementById('otpError');
                const otpSuccess = document.getElementById('otpSuccess');
                const otpSubmitBtn = document.getElementById('otpSubmitBtn');
                const resendOtpBtn = document.getElementById('resendOtpBtn');

                if (otpForm) {
                    otpForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        if (otpError) otpError.classList.add('hidden');
                        if (otpSuccess) otpSuccess.classList.add('hidden');
                        if (otpSubmitBtn) { otpSubmitBtn.disabled = true; otpSubmitBtn.innerHTML = '<span class="spinner"></span> Verifying...'; }

                        const formData = new FormData(otpForm);
                        try {
                            const response = await fetch('../includes/verify_otp.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(Object.fromEntries(formData.entries()))
                            });
                            const result = await response.json();
                            if (result.success) {
                                if (otpSuccess) { otpSuccess.textContent = result.message || 'OTP verified.'; otpSuccess.classList.remove('hidden'); }
                                const finalTarget = window.pendingPortalTarget || result.redirect || 'User_Dashboard.php';
                                window.location.href = finalTarget;
                            } else {
                                if (otpError) { otpError.textContent = result.message; otpError.classList.remove('hidden'); }
                                if (otpSubmitBtn) { otpSubmitBtn.disabled = false; otpSubmitBtn.innerHTML = 'Verify Code'; }
                            }
                        } catch (error) {
                            if (otpError) { otpError.textContent = 'Connection error. Please try again.'; otpError.classList.remove('hidden'); }
                            if (otpSubmitBtn) { otpSubmitBtn.disabled = false; otpSubmitBtn.innerHTML = 'Verify Code'; }
                        }
                    });
                }

                if (resendOtpBtn) {
                    resendOtpBtn.addEventListener('click', async (e) => {
                        e.preventDefault();
                        if (otpError) otpError.classList.add('hidden');
                        if (otpSuccess) otpSuccess.classList.add('hidden');
                        if (resendOtpBtn) resendOtpBtn.disabled = true;
                        try {
                            const response = await fetch('../includes/resend_otp.php', { method: 'POST' });
                            const result = await response.json();
                            if (result.success) {
                                if (otpSuccess) { otpSuccess.textContent = result.message || 'OTP resent successfully.'; otpSuccess.classList.remove('hidden'); }
                            } else {
                                if (otpError) { otpError.textContent = result.message; otpError.classList.remove('hidden'); }
                            }
                        } catch (error) {
                            if (otpError) { otpError.textContent = 'Unable to resend OTP. Please try again.'; otpError.classList.remove('hidden'); }
                        } finally {
                            if (resendOtpBtn) resendOtpBtn.disabled = false;
                        }
                    });
                }

                const contactForm = document.getElementById('contactForm');
                if (contactForm) {
                    contactForm.addEventListener('submit', (e) => { e.preventDefault(); alert('Thank you for your message! We will get back to you soon.'); contactForm.reset(); });
                }

                let isAnimating = false;
                function resetCountersToZero() {
                    document.querySelectorAll('.counter').forEach(counter => {
                        const suffix = counter.getAttribute('data-suffix') || '';
                        counter.textContent = '0' + suffix;
                    });
                }
                function animateNumbers() {
                    if (isAnimating) return;
                    isAnimating = true;
                    document.querySelectorAll('.counter').forEach(counter => {
                        if (counter.animationFrameId) cancelAnimationFrame(counter.animationFrameId);
                        const target = parseInt(counter.getAttribute('data-target'));
                        const suffix = counter.getAttribute('data-suffix') || '';
                        let current = 0;
                        const duration = 1500, stepTime = 20, steps = duration / stepTime, increment = target / steps;
                        let currentStep = 0;
                        const updateCounter = () => {
                            currentStep++;
                            current += increment;
                            if (currentStep < steps) {
                                counter.textContent = Math.floor(current) + suffix;
                                counter.animationFrameId = requestAnimationFrame(updateCounter);
                            } else {
                                counter.textContent = target + suffix;
                                counter.animationFrameId = null;
                            }
                        };
                        counter.animationFrameId = requestAnimationFrame(updateCounter);
                    });
                    setTimeout(() => { isAnimating = false; }, 1600);
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const statsGrid = document.getElementById('statsGrid');
                    if (statsGrid) {
                        const statsObserver = new IntersectionObserver((entries) => {
                            entries.forEach(entry => { if (entry.isIntersecting) { resetCountersToZero(); setTimeout(() => animateNumbers(), 100); } });
                        }, { threshold: 0.3 });
                        statsObserver.observe(statsGrid);
                        const rect = statsGrid.getBoundingClientRect();
                        if (rect.top < window.innerHeight && rect.bottom > 50) setTimeout(() => animateNumbers(), 500);
                    }
                    
                    document.querySelectorAll('.scroll-reveal').forEach(el => {
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
                        }, { threshold: 0.15 });
                        observer.observe(el);
                    });
                    
                    function setHeroHeight() {
                        const hero = document.querySelector('.hero-section');
                        if (hero) hero.style.height = window.innerHeight + 'px';
                    }
                    setHeroHeight();
                    window.addEventListener('resize', setHeroHeight);
                });
            })();

            (function() {
                const slides = document.querySelectorAll('.slide-image');
                if (!slides.length) return;
                let currentIndex = 0, slideInterval;
                const INTERVAL_TIME = 5000;
                function showSlide(index) {
                    slides.forEach((slide, i) => slide.classList.remove('active'));
                    if (slides[index]) slides[index].classList.add('active');
                }
                function nextSlide() { currentIndex = (currentIndex + 1) % slides.length; showSlide(currentIndex); }
                function startSlideshow() { if (slideInterval) clearInterval(slideInterval); slideInterval = setInterval(nextSlide, INTERVAL_TIME); }
                showSlide(0);
                startSlideshow();
                const heroSection = document.querySelector('.hero-section');
                if (heroSection) {
                    heroSection.addEventListener('mouseenter', () => { if (slideInterval) { clearInterval(slideInterval); slideInterval = null; } });
                    heroSection.addEventListener('mouseleave', () => { if (!slideInterval) startSlideshow(); });
                }
            })();
        </script>
        </body>
        </html>