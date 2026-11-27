<?php
// includes/unlock_page.php
session_start();

// Check if user is not logged in (should be unlocked from Front_Page)
if (isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true) {
    header('Location: ../main/User_Dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unlock Account - UMak Registrar</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    
    <!-- Google Fonts: Fira Sans Condensed -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Condensed:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <style>
        /* ==================== ANIMATED BACKGROUND ==================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Fira Sans Condensed', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: flex-start; /* Changed from center to flex-start */
            justify-content: center;
            padding: 2rem 1.5rem;
            position: relative;
            overflow-y: auto; /* Allow vertical scrolling */
            background-color: #2c3e50; /* Fallback dark color */
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
            background: rgba(0, 26, 51, 0.55);
            z-index: 1;
        }
        
        /* ==================== CARD (PUTI NA!) ==================== */
        .card {
            position: relative;
            z-index: 2;
            background: #ffffff;
            padding: 2.5rem;
            max-width: 420px;
            width: 100%;
            border: 1px solid rgba(255, 215, 0, 0.35);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            border-radius: 2px;
            margin: 2rem 0 4rem;
        }
        
        /* ====== INTRO ANIMATION ====== */
        .card {
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
        
        /* ====== OUTRO ANIMATION ====== */
        .card.fade-out {
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
            transition: all 0.8s ease-in-out;
        }
        
        /* ==================== UI ELEMENTS ==================== */
        .brand-name {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #003366;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .brand-name span { color: #FFD700; }
        
        .subtitle {
            color: #4a4a4a;
            font-size: 0.9rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-top: 0.5rem;
            font-weight: 400;
        }
        
        .input-corporate {
            border: 2px solid #e0ddd6;
            padding: 0.9rem 1.2rem;
            width: 100%;
            transition: all 0.3s;
            font-family: 'Fira Sans Condensed', sans-serif;
            font-size: 0.95rem;
            color: #1a2a3a;
            background: #faf9f6;
            border-radius: 0px;
        }
        .input-corporate::placeholder {
            color: #94a3b8;
        }
        .input-corporate:focus {
            border-color: #FFD700;
            outline: none;
            color: #1a2a3a;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(255, 215, 0, 0.1);
        }
        .input-corporate.input-error {
            border-color: #dc2626;
            background: #fef2f2;
        }
        
        .btn-primary {
            background: #003366;
            color: #ffffff;
            padding: 0.9rem 2rem;
            border: none;
            font-weight: 700;
            font-family: 'Fira Sans Condensed', sans-serif;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            transition: all 0.3s;
            cursor: pointer;
            width: 100%;
            border-radius: 0px;
            box-shadow: 0 8px 15px rgba(0, 51, 102, 0.3);
        }
        .btn-primary:hover {
            background: #1a4d7a;
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(0, 51, 102, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .message-error {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: none;
        }
        .message-success {
            color: #16a34a;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: none;
        }
        
        .back-link {
            color: #003366;
            text-decoration: none;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #FFD700;
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 2px solid #e0ddd6;
        }
        .divider span {
            padding: 0 0.75rem;
            color: #94a3b8;
            font-size: 0.7rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <!-- ==================== ANIMATED BACKGROUND SLIDES ==================== -->
    <div class="bg-slide active" style="background-image: url('../images/umak_1.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/umak_2.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/umak_3.png');"></div>
    <div class="bg-slide" style="background-image: url('../images/umak_-4.png');"></div>
    
    <!-- ==================== DARK OVERLAY ==================== -->
    <div class="dark-overlay"></div>

    <!-- ==================== CARD ==================== -->
    <div class="card" id="unlockCard">
        <!-- Brand -->
        <div class="text-center mb-8">
            <div class="brand-name">UMak<span>·Registrar</span></div>
            <p class="subtitle">Unlock Account</p>
        </div>

        <!-- Error/Success Messages -->
        <div id="errorMessage" class="message-error"></div>
        <div id="successMessage" class="message-success"></div>

        <!-- Unlock Form -->
        <form id="unlockForm">
            <!-- Email -->
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1" style="font-family: 'Fira Sans Condensed', sans-serif; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.7rem;">Email Address</label>
                <input type="email" name="email" id="email" placeholder="Enter your UMak email" class="input-corporate" required>
                <p class="text-xs text-gray-400 mt-1" style="font-family: 'Fira Sans Condensed', sans-serif;">We'll send an unlock code to this email</p>
            </div>
            
            <!-- Send OTP Button -->
            <button type="button" id="sendOtpBtn" class="btn-primary">Send Unlock Code</button>
            
            <!-- OTP Section (Hidden initial) -->
            <div id="otpSection" class="mt-4" style="display: none;">
                <div class="mb-4">
                    <label for="otp_code" class="block text-sm font-medium text-gray-700 mb-1" style="font-family: 'Fira Sans Condensed', sans-serif; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.7rem;">Verification Code</label>
                    <input type="text" name="otp_code" id="otp_code" maxlength="6" placeholder="Enter 6-digit code" class="input-corporate text-center tracking-[0.35em] text-lg" required>
                </div>
                
                <!-- Verify OTP Button -->
                <button type="submit" id="verifyOtpBtn" class="btn-primary">Verify and Unlock</button>
            </div>
        </form>

        <!-- Divider -->
        <div class="divider">
            <span>or</span>
        </div>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="/ELEC_4_SYSTEM/main/Front_Page.php" class="back-link">← Back to Login</a>
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
        
        setInterval(nextSlide, 1500);

        // ==================== CARD OUTRO ANIMATION ====================
        const unlockCard = document.getElementById('unlockCard');
        const backLink = document.querySelector('.back-link');
        
        if (backLink) {
            backLink.addEventListener('click', (e) => {
                e.preventDefault();
                unlockCard.classList.add('fade-out');
                setTimeout(() => {
                    window.location.href = '/ELEC_4_SYSTEM/main/Front_Page.php';
                }, 800);
            });
        }

        // ==================== UNLOCK FORM ====================
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const verifyOtpBtn = document.getElementById('verifyOtpBtn');
        const otpSection = document.getElementById('otpSection');
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');

        // Send OTP
        sendOtpBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            if (!email || !email.includes('@')) {
                errorDiv.textContent = 'Please enter a valid email address.';
                errorDiv.style.display = 'block';
                return;
            }
            
            // Disable button
            sendOtpBtn.disabled = true;
            sendOtpBtn.textContent = 'Sending...';
            
            try {
                const response = await fetch('unlock_account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    successDiv.textContent = result.message || 'Unlock code sent to your email!';
                    successDiv.style.display = 'block';
                    
                    // Show OTP section
                    otpSection.style.display = 'block';
                    
                    // Enable button
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.textContent = 'Resend Unlock Code';
                } else {
                    errorDiv.textContent = result.message || 'Failed to send unlock code. Please try again.';
                    errorDiv.style.display = 'block';
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.textContent = 'Send Unlock Code';
                }
            } catch (error) {
                errorDiv.textContent = 'Connection error. Please try again.';
                errorDiv.style.display = 'block';
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send Unlock Code';
            }
        });

        // Verify OTP and Unlock
        document.getElementById('unlockForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const otp = document.getElementById('otp_code').value;
            
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            if (!otp || otp.length !== 6) {
                errorDiv.textContent = 'Please enter a valid 6-digit OTP.';
                errorDiv.style.display = 'block';
                return;
            }
            
            // Disable button
            verifyOtpBtn.disabled = true;
            verifyOtpBtn.textContent = 'Verifying...';
            
            try {
                const response = await fetch('verify_unlock.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp: otp })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    successDiv.textContent = result.message || 'Account unlocked successfully!';
                    successDiv.style.display = 'block';
                    
                    // Fade out card and redirect
                    unlockCard.classList.add('fade-out');
                    setTimeout(() => {
                        window.location.href = result.redirect || '../main/Front_Page.php';
                    }, 800);
                } else {
                    errorDiv.textContent = result.message || 'Invalid OTP. Please try again.';
                    errorDiv.style.display = 'block';
                    verifyOtpBtn.disabled = false;
                    verifyOtpBtn.textContent = 'Verify and Unlock';
                }
            } catch (error) {
                errorDiv.textContent = 'Connection error. Please try again.';
                errorDiv.style.display = 'block';
                verifyOtpBtn.disabled = false;
                verifyOtpBtn.textContent = 'Verify and Unlock';
            }
        });
    </script>
    
</body>
</html>