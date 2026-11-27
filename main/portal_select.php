<?php
// Portal_Select.php
session_start();

// ============================================
// AUTO-DETECT PATHS
// ============================================
// Kung nasa /main/ folder yung file, gagamit ng '../'
// Kung nasa root folder, walang '../'
$is_in_subfolder = (strpos($_SERVER['PHP_SELF'], '/main/') !== false) 
                || (strpos($_SERVER['PHP_SELF'], '/public/') !== false);

$img_path  = $is_in_subfolder ? '../images/' : 'images/';
$link_path = $is_in_subfolder ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Your Portal - UMak Registrar</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>

    <!-- Google Fonts: Libre Baskerville -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    <style>
        /* ==================== ANIMATED BACKGROUND ==================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Libre Baskerville', serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            background-color: #2c3e50;
        }

        /* ====== CROSSFADE BACKGROUND LAYERS ====== */
        .bg-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center center;
            opacity: 0;
            transition: opacity 2s ease-in-out;
            z-index: 0;
        }

        .bg-slide.active {
            opacity: 1;
        }

        /* ====== DARK OVERLAY ====== */
        .dark-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 26, 51, 0.65);
            z-index: 1;
        }

        /* ==================== CONTAINER ==================== */
        .portal-container {
            position: relative;
            z-index: 2;
            max-width: 1100px;
            width: 100%;

            opacity: 0;
            transform: translateY(30px);
            animation: cardIntro 1.2s ease-out forwards;
        }

        @keyframes cardIntro {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .portal-container.fade-out {
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
            transition: all 0.8s ease-in-out;
        }

        /* ==================== PAGE TITLE ==================== */
        .page-title {
            font-family: 'Libre Baskerville', serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: #ffffff;
            text-align: center;
            margin-bottom: 3rem;
            letter-spacing: 0;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        /* ==================== CARDS GRID ==================== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        /* ==================== PORTAL CARD — GLASSMORPHISM ==================== */
        .portal-card {
            position: relative;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            padding: 3rem 2.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 0 rgba(255, 255, 255, 0.4),
                inset 0 -1px 0 0 rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            text-decoration: none;
            color: #ffffff;
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            overflow: hidden;
            min-height: 280px;
        }

        /* Top accent bar — WHITE (walang yellow) */
        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0.2));
            transform: scaleX(0);
            transition: transform 0.4s ease;
            border-radius: 24px 24px 0 0;
        }

        .portal-card:hover::before {
            transform: scaleX(1);
        }

        .portal-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow:
                0 35px 60px -12px rgba(0, 0, 0, 0.7),
                inset 0 1px 0 0 rgba(255, 255, 255, 0.5),
                0 0 40px rgba(255, 255, 255, 0.15);
        }

        /* ==================== CARD TEXT ==================== */
        .portal-card-title {
            font-family: 'Libre Baskerville', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .portal-card-desc {
            font-family: 'Libre Baskerville', serif;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.7;
            flex: 1;
            max-width: 320px;
        }

        /* ==================== CARD BUTTON ==================== */
        .portal-card-btn {
            display: inline-block;
            padding: 0.9rem 2rem;
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-family: 'Libre Baskerville', serif;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            border-radius: 999px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .portal-card:hover .portal-card-btn {
            background: #ffffff;
            color: #003366;
            border-color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(255, 255, 255, 0.3);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .page-title { font-size: 1.65rem; margin-bottom: 2rem; }
            .cards-grid { grid-template-columns: 1fr; gap: 1rem; }
            .portal-card { padding: 2.5rem 1.75rem; min-height: 240px; }
            .portal-card-title { font-size: 1.25rem; }
            .portal-card-desc { font-size: 0.78rem; }
        }
    </style>
</head>
<body>

    <!-- ==================== ANIMATED BACKGROUND SLIDES ==================== -->
    <div class="bg-slide active" style="background-image: url('<?php echo $img_path; ?>umak_1.png');"></div>
    <div class="bg-slide" style="background-image: url('<?php echo $img_path; ?>umak_2.png');"></div>
    <div class="bg-slide" style="background-image: url('<?php echo $img_path; ?>umak_3.png');"></div>
    <div class="bg-slide" style="background-image: url('<?php echo $img_path; ?>umak_-4.png');"></div>

    <!-- ==================== DARK OVERLAY ==================== -->
    <div class="dark-overlay"></div>

    <!-- ==================== PORTAL CONTAINER ==================== -->
    <div class="portal-container" id="portalContainer">

        <h1 class="page-title">Access Your Portal</h1>

        <div class="cards-grid">

            <!-- ===== ADMIN PANEL ===== -->
            <a href="<?php echo $link_path; ?>main/Admin_Dashboard.php" class="portal-card">
                <div class="portal-card-title">Admin Panel</div>
                <div class="portal-card-desc">
                    Issue certificates, upload documents, and manage student accounts.
                </div>
                <div class="portal-card-btn">Enter Admin →</div>
            </a>

            <!-- ===== STUDENT PORTAL ===== -->
            <a href="<?php echo $link_path; ?>main/Front_Page.php" class="portal-card">
                <div class="portal-card-title">Student Portal</div>
                <div class="portal-card-desc">
                    Log in to view, download, and share your officially issued certificates.
                </div>
                <div class="portal-card-btn">Enter Student →</div>
            </a>

        </div>

    </div>

    <script>
        // ==================== BACKGROUND CROSSFADE ====================
        const bgSlides = document.querySelectorAll('.bg-slide');
        let currentSlide = 0;

        function nextSlide() {
            bgSlides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % bgSlides.length;
            bgSlides[currentSlide].classList.add('active');
        }

        setInterval(nextSlide, 2000);

        // ==================== CARD OUTRO ANIMATION ====================
        const portalContainer = document.getElementById('portalContainer');

        document.querySelectorAll('.portal-card').forEach(card => {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                const href = card.getAttribute('href');

                portalContainer.classList.add('fade-out');

                setTimeout(() => {
                    window.location.href = href;
                }, 800);
            });
        });
    </script>

</body>
</html>