<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // In a real application, you would verify against a database
    $valid_username = 'admin';
    $valid_password = 'U-h0^)s;yIV?|^oQ'; // Change this to a secure password
    
    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TXPLAY - Admin Login</title>
    <style>
        /* Use the same styling as your main site */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
            background: linear-gradient(180deg, #000000 0%, #1a0b2e 50%, #000000 100%);
            color: white;
            min-height: 100vh;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Animated Background */
        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }

        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .bg-orb:nth-child(1) {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, #a855f7, #ec4899);
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .bg-orb:nth-child(2) {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #ec4899, #3b82f6);
            top: 40%;
            right: 15%;
            animation-delay: -2s;
        }

        .bg-orb:nth-child(3) {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, #3b82f6, #a855f7);
            bottom: 20%;
            left: 50%;
            animation-delay: -4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-20px) scale(1.1); }
        }

        .login-container {
            position: relative;
            z-index: 10;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 16px;
            padding: 48px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.8s ease;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo img {
            width: 120px;
            height: auto;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #06b6d4, #f97316, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
            text-align: center;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.875rem;
            color: #9ca3af;
        }

        .form-control {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 8px;
            padding: 12px 16px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.3);
        }

        .btn {
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ec4899, #a855f7);
            color: white;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
            background: linear-gradient(135deg, #f472b6, #c084fc);
        }

        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            text-align: center;
            margin-top: 8px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 32px;
                margin: 16px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="background-overlay">
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-logo">
            <img src="../images/MB TXPlays-logo-emblem.png" alt="TXPLAY Logo">
        </div>
        
        <h1 class="login-title">Admin Dashboard</h1>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                Login
            </button>
        </form>
    </div>
</body>
</html>