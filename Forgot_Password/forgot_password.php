    <?php
    // Forgot_Password/forgot_password.php
    session_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Forgot Password - UMak Registrar</title>
        
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
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                position: relative;
                overflow: hidden;
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
                background: #ffffff; /* SOLID WHITE */
                padding: 3.5rem;
                max-width: 420px;
                width: 100%;
                border: 1px solid rgba(255, 215, 0, 0.35);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
                border-radius: 2px;
                
                /* ====== INTRO ANIMATION ====== */
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
        <div class="card" id="forgotCard">
            <!-- Brand -->
            <div class="text-center mb-8">
                <div class="brand-name">UMak<span>·Registrar</span></div>
                <p class="subtitle">Reset your password</p>
            </div>

            <!-- Error/Success Messages -->
            <div id="errorMessage" class="message-error"></div>
            <div id="successMessage" class="message-success"></div>

            <!-- Step 1: Enter Email -->
            <form id="forgotPasswordForm">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1" style="font-family: 'Fira Sans Condensed', sans-serif; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.7rem;">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="Enter your UMak email" class="input-corporate" required>
                    <p class="text-xs text-gray-400 mt-1" style="font-family: 'Fira Sans Condensed', sans-serif;">We'll send a verification code to this email</p>
                </div>
                <button type="submit" id="submitBtn" class="btn-primary">Send Verification Code</button>
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
                // Remove active from current
                bgSlides[currentSlide].classList.remove('active');
                
                // Move to next slide
                currentSlide = (currentSlide + 1) % bgSlides.length;
                
                // Add active to new slide
                bgSlides[currentSlide].classList.add('active');
            }
            
            // Change background every 1.5 seconds (as requested)
            setInterval(nextSlide, 1500);

            // ==================== CARD OUTRO ANIMATION ====================
            const forgotCard = document.getElementById('forgotCard');
            const backLink = document.querySelector('.back-link');
            
            // Back to login button na may fade-out effect
            if (backLink) {
                backLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Fade out card
                    forgotCard.classList.add('fade-out');
                    
                    // Wait for animation to finish, then redirect
                    setTimeout(() => {
                        window.location.href = '/ELEC_4_SYSTEM/main/Front_Page.php';
                    }, 800);
                });
            }

            // ==================== FORM (HINDI BINAGO - WORKING CODE) ====================
            document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email').value;
                const submitBtn = document.getElementById('submitBtn');
                const errorDiv = document.getElementById('errorMessage');
                const successDiv = document.getElementById('successMessage');
                
                // Hide previous messages
                errorDiv.style.display = 'none';
                successDiv.style.display = 'none';
                
                // Validate email
                if (!email || !email.includes('@')) {
                    errorDiv.textContent = 'Please enter a valid email address.';
                    errorDiv.style.display = 'block';
                    return;
                }
                
                // Disable button
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending...';
                
                try {
                    const response = await fetch('process_forgot_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        forgotCard.classList.add('fade-out');
                        
                        successDiv.textContent = result.message || 'Verification code sent to your email!';
                        successDiv.style.display = 'block';
                        
                        // Wait for fade-out, then redirect to reset password page
                        setTimeout(() => {
                            window.location.href = 'reset_password.php?email=' + encodeURIComponent(email);
                        }, 800);
                    } else {
                        errorDiv.textContent = result.message || 'Something went wrong. Please try again.';
                        errorDiv.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Send Verification Code';
                    }
                } catch (error) {
                    errorDiv.textContent = 'Connection error. Please try again.';
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Verification Code';
                }
            });
        </script>
        
    </body>
    </html>