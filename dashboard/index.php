<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TXPLAY - Admin Dashboard</title>
    <style>
        /* Use the same styling as your main site with admin-specific additions */
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
        }

        /* Half Circle Image Container */
        .half-circle-image {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            opacity: 20%;
            max-width: 100%;
            height: 400px;
            overflow: hidden;
            z-index: 5;
        }

        .half-circle-image::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 200%;
            height: 800px;
            border-radius: 50%;
            background: url('background.png') center/cover no-repeat;
            clip-path: ellipse(100% 100% at 50% 0%);
        }

        /* Adjust main content positioning */
        .main-content {
            padding-top: 200px;
            position: relative;
            padding-bottom: 200px;
            z-index: 10;
        }

        @media (max-width: 768px) {
            .half-circle-image {
                height: 300px;
            }
            .half-circle-image::before {
                height: 300px;
            }
            .main-content {
                padding-top: 150px;
            }
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

        /* Container */
        .container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            margin-left: 72px;
        }

        /* Header with Logout Button */
        .header {
            position: fixed;
            top: 0;
            right: 0;
            padding: 24px;
            z-index: 100;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #f87171, #ef4444);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 72px;
            height: 100vh;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(148, 163, 184, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 0;
            z-index: 50;
        }

        .sidebar-logo {
            font-size: 18px;
            font-weight: 900;
            background: linear-gradient(135deg, #06b6d4, #f97316, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 32px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex-grow: 1;
        }

        .sidebar-icon {
            position: relative;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-icon:hover {
            background: rgba(51, 65, 85, 0.7);
        }

        .sidebar-icon.active {
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid rgba(168, 85, 247, 0.5);
        }

        .sidebar-icon svg {
            width: 20px;
            height: 20px;
            stroke: #9ca3af;
            transition: stroke 0.3s ease;
        }

        .sidebar-icon:hover svg,
        .sidebar-icon.active svg {
            stroke: white;
        }

        /* Tooltip */
        .tooltip {
            position: absolute;
            left: 60px;
            background: rgba(31, 41, 55, 0.95);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .sidebar-icon:hover .tooltip {
            opacity: 1;
        }

        .sidebar-expand {
            margin-top: auto;
        }

        /* Admin Dashboard Content */
        .admin-container {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .admin-title {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #06b6d4, #f97316, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-welcome {
            font-size: 1rem;
            color: #9ca3af;
        }

        .admin-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .admin-card {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .admin-card:hover {
            transform: translateY(-5px);
            border-color: rgba(168, 85, 247, 0.5);
            box-shadow: 0 20px 40px rgba(168, 85, 247, 0.15);
        }

        .admin-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-card-icon {
            width: 24px;
            height: 24px;
            color: #a855f7;
        }

        .admin-card-description {
            font-size: 0.875rem;
            color: #9ca3af;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .admin-container {
                padding: 24px;
            }

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .admin-cards {
                grid-template-columns: 1fr;
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

    <!-- Half Circle Image Container -->
    <div class="half-circle-image"></div>

    <!-- Header -->
    <header class="header">
        <button class="logout-btn" onclick="window.location.href='?logout=1'">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Logout
        </button>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../images/MB TXPlays-logo-emblem.png" alt="TXPLAY Logo" style="width:40px; height:auto; display:block; margin:0 auto;">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-icon active" onclick="navigateTo('dashboard')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <div class="tooltip">Dashboard</div>
            </div>
            <div class="sidebar-icon" onclick="navigateTo('leaderboard')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="14" width="5" height="6" rx="1" stroke-width="2"/>
                    <rect x="9.5" y="10" width="5" height="10" rx="1" stroke-width="2"/>
                    <rect x="17" y="17" width="5" height="3" rx="1" stroke-width="2"/>
                    <path d="M12 7V4M12 4l-1.5 1.5M12 4l1.5 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="tooltip">Leaderboard</div>
            </div>
            <div class="sidebar-icon" onclick="navigateTo('events')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div class="tooltip">Events</div>
            </div>
            <div class="sidebar-icon" onclick="navigateTo('bonuses')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2" />
                    <circle cx="12" cy="12" r="5" stroke-width="2" />
                    <line x1="12" y1="3" x2="12" y2="6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="12" y1="18" x2="12" y2="21" stroke-width="2" stroke-linecap="round"/>
                    <line x1="3" y1="12" x2="6" y2="12" stroke-width="2" stroke-linecap="round"/>
                    <line x1="18" y1="12" x2="21" y2="12" stroke-width="2" stroke-linecap="round"/>
                    <line x1="5.64" y1="5.64" x2="7.76" y2="7.76" stroke-width="2" stroke-linecap="round"/>
                    <line x1="16.24" y1="16.24" x2="18.36" y2="18.36" stroke-width="2" stroke-linecap="round"/>
                    <line x1="16.24" y1="7.76" x2="18.36" y2="5.64" stroke-width="2" stroke-linecap="round"/>
                    <line x1="5.64" y1="18.36" x2="7.76" y2="16.24" stroke-width="2" stroke-linecap="round"/>
                    <polygon points="12,8.5 13.09,11.26 16,11.27 13.97,13.02 14.58,15.72 12,14.2 9.42,15.72 10.03,13.02 8,11.27 10.91,11.26" fill="currentColor" stroke="none"/>
                </svg>
                <div class="tooltip">Bonuses</div>
            </div>
        </nav>
    </aside>

    <!-- Main Container -->
    <div class="container">
        <div class="admin-container">
            <div class="admin-header">
                <h1 class="admin-title">Admin Dashboard</h1>
                <p class="admin-welcome">Welcome back, Admin</p>
            </div>
            
            <div class="admin-cards">
                <div class="admin-card" onclick="window.location.href='leaderboard'">
                    <h3 class="admin-card-title">
                        <svg class="admin-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="2" y="14" width="5" height="6" rx="1" stroke-width="2"/>
                            <rect x="9.5" y="10" width="5" height="10" rx="1" stroke-width="2"/>
                            <rect x="17" y="17" width="5" height="3" rx="1" stroke-width="2"/>
                            <path d="M12 7V4M12 4l-1.5 1.5M12 4l1.5 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Leaderboard Management
                    </h3>
                    <p class="admin-card-description">
                        Edit prize amounts, view current standings, and manage the leaderboard settings.
                    </p>
                </div>
                
                <div class="admin-card" onclick="window.location.href='bonuses'">
                    <h3 class="admin-card-title">
                        <svg class="admin-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" stroke-width="2" />
                            <circle cx="12" cy="12" r="5" stroke-width="2" />
                            <polygon points="12,8.5 13.09,11.26 16,11.27 13.97,13.02 14.58,15.72 12,14.2 9.42,15.72 10.03,13.02 8,11.27 10.91,11.26" fill="currentColor" stroke="none"/>
                        </svg>
                        Bonus Management
                    </h3>
                    <p class="admin-card-description">
                        Update bonus information, edit terms, and manage featured bonuses.
                    </p>
                </div>
                
                <div class="admin-card">
                    <h3 class="admin-card-title">
                        <svg class="admin-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        User Management (SOON)
                    </h3>
                    <p class="admin-card-description">
                      
                    </p>
                </div>
                
                <div class="admin-card">
                    <h3 class="admin-card-title">
                        <svg class="admin-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Analytics (SOON)
                    </h3>
                    <p class="admin-card-description">
                     
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function navigateTo(page) {
            if (page === 'leaderboard') {
                window.location.href = 'leaderboard';
            } else if (page === 'bonuses') {
                window.location.href = 'bonuses';
            } else if (page === 'events') {
                window.location.href = 'events';
            }
            // Dashboard is already the active page
        }
    </script>
</body>
</html>