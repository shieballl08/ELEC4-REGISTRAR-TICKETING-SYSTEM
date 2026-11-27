<?php
// ============================================
// USER FOOTER - UNIFIED FOR ALL STUDENT PAGES
// UMaK Registrar's Office
// ============================================

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);

// ============================================
// BASE PATH DETECTION
// ============================================
$is_in_modules  = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false);

$assets_path      = $is_in_modules ? '../../images/' : '../images/';
$help_center_path = $is_in_modules ? '../help_center.php' : 'modules/help_center.php';
$contact_path     = $is_in_modules ? 'contact_registrar.php' : 'modules/contact_registrar.php';
?>

<!-- ========== FOOTER CSS ========== -->
<style>
    .footer {
        flex-shrink: 0;
        /* Glassmorphism Dark Blue Background */
        background: linear-gradient(135deg, rgba(0, 43, 107, 0.92) 0%, rgba(0, 20, 50, 0.95) 100%);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        color: #d0d0d0;
        padding: 0;
        margin-top: 0;
        width: 100%;
        position: relative;
        z-index: 3;
        font-family: 'Libre Baskerville', serif;
        border-top: 1px solid rgba(255, 193, 7, 0.15);
        box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.2);
    }

    .footer-inner {
        max-width: 1280px;
        margin: 0 auto;
        padding: 3rem 2rem 2.5rem;
        display: grid;
        grid-template-columns: 1.4fr 1fr 1.2fr 0.8fr;
        gap: 2.5rem;
        align-items: start;
    }

    .footer-col h4 {
        font-size: 1.35rem;
        font-weight: 700;
        color: #FFC107; /* Bright Yellow */
        margin-bottom: 1.25rem;
        letter-spacing: 0.01em;
        font-family: 'Libre Baskerville', serif;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .footer-col p {
        font-size: 0.85rem;
        line-height: 1.7;
        color: #b0b8c4; /* Soft blue-gray for readability */
    }

    .footer-col ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-col ul li {
        margin-bottom: 0.65rem;
    }

    .footer-col ul li a {
        color: #FFC107; /* Bright Yellow */
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s ease, text-decoration 0.2s ease, padding-left 0.2s ease;
    }

    .footer-col ul li a:hover {
        text-decoration: underline;
        color: #FFD54F;
        padding-left: 4px;
    }

    .footer-contact-item {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 0.85rem;
        line-height: 1.5;
        color: #b0b8c4;
        margin-bottom: 0.75rem;
    }

    .footer-contact-item svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        margin-top: 2px;
        stroke: #FFC107; /* Yellow icons */
        fill: none;
    }

    .footer-contact-item a {
        color: #FFC107; /* Bright Yellow */
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .footer-contact-item a:hover {
        text-decoration: underline;
        color: #FFD54F;
    }

    /* Glassmorphism Social Button */
    .footer-social {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 193, 7, 0.3);
        border-radius: 10px;
        transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .footer-social:hover {
        transform: translateY(-2px);
        background: rgba(255, 193, 7, 0.2);
        border-color: rgba(255, 193, 7, 0.6);
        box-shadow: 0 6px 20px rgba(255, 193, 7, 0.25);
    }

    .footer-social svg {
        width: 24px;
        height: 24px;
        fill: #FFC107; /* Yellow Facebook icon */
        stroke: none;
    }

    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        padding: 1.1rem 2rem;
        text-align: center;
        background: rgba(0, 15, 35, 0.4); /* Darker glass layer */
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .footer-bottom p {
        font-size: 0.78rem;
        color: #8a9aaa;
        margin: 0;
    }

    .footer-bottom a {
        color: #FFC107;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .footer-bottom a:hover {
        text-decoration: underline;
        color: #FFD54F;
    }

    @media (max-width: 1024px) {
        .footer-inner { grid-template-columns: 1fr 1fr; gap: 2rem; padding: 2.5rem 1.5rem 2rem; }
    }

    @media (max-width: 768px) {
        .footer-inner { grid-template-columns: 1fr; gap: 1.75rem; padding: 2rem 1.25rem 1.75rem; }
        .footer-col h4 { font-size: 1.2rem; }
    }
</style>

<!-- ========== FOOTER HTML ========== -->
<footer class="footer">
    <div class="footer-inner">

        <!-- About -->
        <div class="footer-col">
            <h4>About UMaK Registrar</h4>
            <p>
                The Office of the University Registrar (OUR) of the University of Makati manages student academic records, enrollment, graduation, and the issuance of official documents such as Transcript of Records, Certificates, and Clearances. We are committed to delivering timely, accurate, and student-centered registrar services.
            </p>
        </div>

        <!-- Links -->
        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="https://www.umak.edu.ph/" target="_blank" rel="noopener noreferrer">University of Makati</a></li>
                <li><a href="https://www.umak.edu.ph/library/" target="_blank" rel="noopener noreferrer">UMaK Online Library</a></li>
                <li><a href="https://www.umak.edu.ph/student/onlineportals/" target="_blank" rel="noopener noreferrer">Student Portal</a></li>
                <li><a href="<?php echo $contact_path; ?>">Registrar Support</a></li>
            </ul>
        </div>

        <!-- Contact -->
        <div class="footer-col">
            <h4>Contact Us</h4>
            <div class="footer-contact-item">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s-6-5.686-6-10a6 6 0 1112 0c0 4.314-6 10-6 10z"/>
                    <circle cx="12" cy="11" r="2"/>
                </svg>
                <span>J.P. Rizal Extension, West Rembo, 1644 City of Taguig, Metro Manila, Philippines</span>
            </div>
            <div class="footer-contact-item">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                    <path d="M3 7l9 6 9-6"/>
                </svg>
                <span>Email: <a href="https://mail.google.com/mail/?view=cm&fs=1&to=registrar@umak.edu.ph" target="_blank" rel="noopener noreferrer">registrar@umak.edu.ph</a></span>
            </div>
        </div>

        <!-- Follow -->
        <div class="footer-col">
            <h4>Follow Us</h4>
            <a href="https://www.facebook.com/UMakRegistrar" target="_blank" rel="noopener noreferrer" class="footer-social" aria-label="Facebook">
                <svg viewBox="0 0 24 24">
                    <path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/>
                </svg>
            </a>
        </div>

    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <p>Copyright © 2026 · University of Makati — Office of the University Registrar. All rights reserved.</p>
    </div>
</footer>

<!-- Back to Top Button -->
<a href="#" class="back-to-top" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="18 15 12 9 6 15"/>
    </svg>
</a>

<!-- Help Button -->
<a href="<?php echo $help_center_path; ?>" class="help-btn" aria-label="Help">?</a>

<!-- Back to top & Help button CSS (Glassmorphism) -->
<style>
    .back-to-top {
        position: fixed;
        right: 24px;
        bottom: 90px;
        width: 44px;
        height: 44px;
        background: rgba(0, 43, 107, 0.6);
        backdrop-filter: blur(12px) saturate(180%);
        -webkit-backdrop-filter: blur(12px) saturate(180%);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        z-index: 90;
        text-decoration: none;
    }
    .back-to-top:hover {
        background: rgba(0, 71, 171, 0.9);
        border-color: rgba(255, 193, 7, 0.5);
        transform: translateY(-2px);
    }
    .back-to-top svg {
        width: 20px;
        height: 20px;
        stroke: #FFC107;
        stroke-width: 2.5;
        fill: none;
    }

    .help-btn {
        position: fixed;
        right: 24px;
        bottom: 24px;
        width: 44px;
        height: 44px;
        background: rgba(255, 193, 7, 0.85);
        backdrop-filter: blur(12px) saturate(180%);
        -webkit-backdrop-filter: blur(12px) saturate(180%);
        color: #002B6B; /* Dark Blue text for contrast */
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 32px rgba(255, 193, 7, 0.35);
        transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        z-index: 90;
        font-size: 1.25rem;
        font-weight: 700;
        font-family: 'Libre Baskerville', serif;
        text-decoration: none;
    }
    .help-btn:hover {
        background: rgba(255, 213, 79, 0.95);
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(255, 193, 7, 0.5);
    }

    @media (max-width: 600px) {
        .back-to-top, .help-btn { right: 16px; width: 38px; height: 38px; }
    }
</style>