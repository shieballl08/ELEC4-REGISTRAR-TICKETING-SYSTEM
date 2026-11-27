<?php
session_start();

// Check kung naka-login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../Front_Page.php');
    exit();
}

// Kunin ang role ng user mula sa session
$userRole = $_SESSION['role'] ?? 'student';
$userName = $_SESSION['full_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Your Portal · UMak Registrar</title>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Condensed:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Fira Sans Condensed', sans-serif; }
        
        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #003366, #1a4d7a);
            padding: 2rem;
        }

        .portal-container {
            text-align: center;
            width: 100%;
            max-width: 1200px;
        }

        .welcome-text {
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
        }

        h1 {
            color: white;
            font-size: 2.8rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 1.1rem;
            margin-bottom: 3rem;
        }

        .cards-wrapper {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .card {
            background: linear-gradient(135deg, #ff758c, #ff7eb3);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 300px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 45px rgba(0,0,0,0.3);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: #FFD700;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .icon-circle {
            background: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            backdrop-filter: blur(4px);
        }

        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .card p {
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            opacity: 0.95;
        }

        .btn {
            background: rgba(255, 255, 255, 0.25);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Fira Sans Condensed', sans-serif;
            text-decoration: none;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .logout-link {
            display: inline-block;
            margin-top: 2.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px solid transparent;
            transition: all 0.2s;
        }

        .logout-link:hover {
            color: #FFD700;
            border-bottom-color: #FFD700;
        }

        /* Disabled card style kung hindi role ng user */
        .card.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            filter: grayscale(0.6);
        }

        .card.disabled:hover {
            transform: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .card.disabled::before {
            transform: scaleX(0) !important;
        }

        .card.disabled .btn {
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="portal-container">
        <p class="welcome-text">Welcome, <?php echo htmlspecialchars($userName); ?>!</p>
        <h1>Access Your Portal</h1>
        <p class="subtitle">Select your role to continue</p>
        
        <div class="cards-wrapper">
            <!-- Admin Card -->
            <div class="card <?php echo ($userRole !== 'admin') ? 'disabled' : ''; ?>" 
                 onclick="<?php echo ($userRole === 'admin') ? 'selectRole(\'admin\')' : ''; ?>">
                <div class="icon-circle">
                    <i class="fas fa-university"></i>
                </div>
                <h2>Admin Panel</h2>
                <p>Issue certificates, upload documents, and manage student accounts.</p>
                <button class="btn">Enter Admin <i class="fas fa-arrow-right"></i></button>
            </div>

            <!-- Verifier Card -->
            <div class="card <?php echo ($userRole !== 'staff' && $userRole !== 'admin') ? 'disabled' : ''; ?>" 
                 onclick="<?php echo ($userRole === 'staff' || $userRole === 'admin') ? 'selectRole(\'verifier\')' : ''; ?>">
                <div class="icon-circle">
                    <i class="fas fa-search"></i>
                </div>
                <h2>Verifier</h2>
                <p>Verify the authenticity of a certificate using its cryptographic hash or QR code.</p>
                <button class="btn">Enter Verifier <i class="fas fa-arrow-right"></i></button>
            </div>

            <!-- Student Card -->
            <div class="card <?php echo ($userRole !== 'student' && $userRole !== 'faculty') ? 'disabled' : ''; ?>" 
                 onclick="<?php echo ($userRole === 'student' || $userRole === 'faculty') ? 'selectRole(\'student\')' : ''; ?>">
                <div class="icon-circle">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2>Student Portal</h2>
                <p>Log in to view, download, and share your officially issued certificates.</p>
                <button class="btn">Enter Student <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <a href="../includes/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <script>
        function selectRole(role) {
            // I-save ang role selection sa session
            fetch('set_active_portal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ portal: role })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect sa tamang dashboard
                    if (role === 'admin') {
                        window.location.href = '../main/Admin_Dashboard.php';
                    } else if (role === 'verifier') {
                        window.location.href = '../main/Verify_Certificate.php';
                    } else if (role === 'student') {
                        window.location.href = '../main/User_Dashboard.php';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Connection error. Please try again.');
            });
        }
    </script>

</body>
</html>