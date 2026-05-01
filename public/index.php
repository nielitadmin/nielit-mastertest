<?php
// Start a clean session
session_name('NIELIT_LANDING');
session_start();

// Clear any existing sessions when coming to the landing page
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Mock Assessment Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Premium Color Palette */
            --primary: #155E75;        /* Official NIELIT Blue */
            --primary-light: #0284C7;  
            --primary-bg: #EFF6FF;     
            --candidate: #059669;      
            --candidate-bg: #ECFDF5;
            --tp: #0D9488;
            --tp-bg: #CCFBF1;
            --text-dark: #0F172A;
            --text-muted: #475569;
            --bg-body: #F8FAFC;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --gold: #D97706;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-body);
            min-height: 100vh; 
            display: flex;
            flex-direction: column;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }

        /* --- 1. OFFICIAL TOP HEADER (WHITE) --- */
        .top-header {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border);
            z-index: 100;
            position: relative;
            width: 100%;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1380px; 
            margin: 0 auto;
            padding: 12px 40px;
            width: 100%;
        }
        
        .header-left { display: flex; align-items: center; gap: 15px; }
        .nielit-logo { height: 50px; width: auto; object-fit: contain; }
        
        .header-titles { display: flex; flex-direction: column; }
        .hindi-title { font-family: 'Noto Sans Devanagari', sans-serif; font-size: 15px; color: var(--primary); font-weight: 700; }
        .eng-title { font-size: 13px; font-weight: 600; color: var(--text-dark); }

        .header-right { display: flex; align-items: center; gap: 15px; text-align: right; }
        .ministry-text { display: flex; flex-direction: column; font-size: 11px; color: var(--text-muted); font-weight: 600; }
        .ministry-text strong { font-size: 12px; color: var(--text-dark); }
        .emblem { height: 50px; width: auto; object-fit: contain; margin-left: 5px; }

        /* --- 2. OFFICIAL NAVIGATION BAR (BLUE) --- */
        .main-nav {
            background: var(--primary);
            box-shadow: var(--shadow-sm);
            z-index: 99;
            position: relative;
            width: 100%;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1380px; 
            margin: 0 auto;
            padding: 0 40px;
            width: 100%;
            flex-wrap: wrap; /* Allows wrapping on mobile */
        }

        .nav-home-btn {
            color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 15px;
            display: flex; align-items: center; gap: 8px; padding: 15px 0;
            transition: color 0.3s;
        }
        .nav-home-btn:hover { color: #E0F2FE;}
        .nav-custom-icon { height: 18px; width: auto; object-fit: contain; filter: brightness(0) invert(1); }

        /* Mobile Menu Button (Hidden on Desktop) */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #FFFFFF;
            font-size: 24px;
            cursor: pointer;
            padding: 10px 0;
        }

        .nav-links { display: flex; height: 100%; align-items: center; }
        .nav-link {
            color: #E0F2FE; text-decoration: none; font-weight: 600; font-size: 14px;
            padding: 16px 20px; transition: 0.3s; display: flex; align-items: center; gap: 6px;
        }
        .nav-link:hover { color: #FFFFFF; background: rgba(255, 255, 255, 0.1); }

        /* Dropdown specific styles */
        .dropdown { position: relative; display: inline-block; height: 100%; }
        .dropbtn {
            background: transparent; color: #E0F2FE; border: none; font-family: inherit;
            font-weight: 600; font-size: 14px; padding: 16px 20px; cursor: pointer;
            display: flex; align-items: center; gap: 6px; transition: 0.3s; height: 100%;
            outline: none;
        }
        .dropdown:hover .dropbtn { color: #FFFFFF; background: rgba(255, 255, 255, 0.1); }
        
        .dropdown-content {
            display: none; position: absolute; background-color: #FFFFFF; min-width: 250px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 200; top: 100%; right: 0;
            overflow: hidden; border: 1px solid var(--border);
            border-top: 3px solid var(--primary);
            border-radius: 0 0 12px 12px;
            padding: 10px 0;
        }
        .dropdown:hover .dropdown-content { display: block; animation: dropFade 0.2s ease forwards; }
        
        @keyframes dropFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .dropdown-content a {
            color: var(--text-dark); padding: 12px 20px; text-decoration: none; display: flex;
            align-items: flex-start; gap: 10px; font-size: 13.5px; font-weight: 600; transition: 0.2s;
            border-bottom: 1px solid #F1F5F9;
        }
        .dropdown-content a:last-child { border-bottom: none; }
        .dropdown-content a:hover { background-color: var(--primary-bg); color: var(--primary); padding-left: 25px;}
        
        .dropdown-content a i {
            color: var(--primary-light); width: 18px; text-align: center; font-size: 15px; margin-top: 2px;
        }
        .dropdown-content .ac-text { flex: 1; display: flex; flex-direction: column; }
        .dropdown-content .ac-desc { font-weight: 500; font-size: 11px; color: var(--text-muted); margin-top: 2px; line-height: 1.4; }

        /* --- 3. TICKER --- */
        .ticker-wrap {
            background: var(--text-dark); color: white; padding: 6px 0; overflow: hidden; 
            position: relative; z-index: 10; font-size: 12px; font-weight: 600; display: flex; align-items: center;
        }
        .ticker-label { background: var(--gold); color: white; padding: 2px 10px; border-radius: 4px; font-weight: 800; margin: 0 15px; position: relative; z-index: 2; white-space: nowrap; font-size: 11px; letter-spacing: 0.5px;}
        .ticker-move { display: inline-block; white-space: nowrap; animation: ticker 35s linear infinite; }
        @keyframes ticker { 0% { transform: translateX(100vw); } 100% { transform: translateX(-100%); } }

        /* --- 4. 3D AMBIENT BACKGROUND --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #E0F2FE 0%, #F8FAFC 60%);
            perspective: 1000px;
        }
        .shape {
            position: absolute; background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.2));
            backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 15px 35px rgba(21, 94, 117, 0.05), inset 0 0 20px rgba(255, 255, 255, 0.5);
            animation: float-3d 25s infinite linear;
        }
        .cube { width: 160px; height: 160px; border-radius: 32px; top: 20%; left: 5%; animation-duration: 30s; }
        .ring { width: 240px; height: 240px; border-radius: 50%; border: 40px solid rgba(255,255,255,0.4); top: 50%; right: 2%; animation-duration: 35s; animation-direction: reverse; background: transparent; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }

        /* --- 5. SPLIT SCREEN LAYOUT --- */
        .wrapper {
            display: flex; align-items: center; justify-content: space-between;
            max-width: 1300px; margin: 0 auto; width: 100%; padding: 40px 40px 20px 40px; gap: 40px;
            z-index: 10;
        }

        .hero { flex: 1.2; animation: fadeRight 0.8s ease both; max-width: 580px;}
        .hero-title { font-size: 42px; font-weight: 800; color: var(--text-dark); letter-spacing: -1px; line-height: 1.1; margin-bottom: 15px;}
        .hero-title span { color: var(--primary); }
        .hero-sub { font-size: 15px; color: var(--text-muted); font-weight: 500; line-height: 1.6; margin-bottom: 25px;}
        
        .system-badge {
            display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 800;
            color: var(--candidate); box-shadow: var(--shadow-sm); margin-bottom: 30px;
        }
        .live-dot { width: 8px; height: 8px; background: var(--candidate); border-radius: 50%; box-shadow: 0 0 12px var(--candidate); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }

        .stats-row { display: flex; gap: 15px; flex-wrap: wrap;}
        .stat { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); padding: 15px 20px; border-radius: 16px; border: 1px solid white; flex: 1; min-width: 120px; box-shadow: var(--shadow-sm);}
        .stat-num { font-size: 24px; font-weight: 800; color: var(--text-dark); line-height: 1;}
        .stat-num span { color: var(--primary); }
        .stat-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; margin-top: 6px;}

        .login-section { 
            flex: 1; display: flex; flex-direction: column; gap: 20px; 
            max-width: 500px; animation: fadeLeft 0.8s ease both; animation-delay: 0.2s;
        }

        .action-card {
            display: flex; align-items: center; gap: 20px; padding: 25px;
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px);
            border: 1px solid var(--border); border-radius: 20px;
            text-decoration: none; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-md); position: relative; overflow: hidden;
        }
        .action-card:hover { transform: translateY(-4px); background: #FFFFFF; border-color: transparent;}
        .action-card.candidate:hover { box-shadow: 0 20px 40px -5px rgba(5, 150, 105, 0.2); }
        .action-card.tp:hover { box-shadow: 0 20px 40px -5px rgba(13, 148, 136, 0.2); }

        .ac-icon {
            width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
            font-size: 26px; flex-shrink: 0; transition: transform 0.3s;
        }
        .action-card.candidate .ac-icon { background: var(--candidate-bg); color: var(--candidate); }
        .action-card.tp .ac-icon { background: var(--tp-bg); color: var(--tp); }
        .action-card:hover .ac-icon { transform: scale(1.1) rotate(-5deg); }

        .ac-text { flex: 1; }
        .ac-text h2 { font-size: 20px; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; }
        .ac-text p { font-size: 13px; color: var(--text-muted); font-weight: 500; line-height: 1.5; margin: 0;}

        /* --- 6. PLATFORM DETAILS (NEW) --- */
        .platform-details {
            max-width: 1300px; margin: 0 auto; width: 100%; padding: 20px 40px 60px 40px;
            z-index: 10; position: relative; animation: fadeUp 0.8s ease both; animation-delay: 0.4s;
        }
        .section-title { font-size: 28px; font-weight: 800; text-align: center; margin-bottom: 30px; letter-spacing: -0.5px; }
        
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 40px;
        }
        .feature-box {
            background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(15px); border: 1px solid white;
            padding: 25px; border-radius: 20px; box-shadow: var(--shadow-sm); display: flex; gap: 15px;
            transition: 0.3s;
        }
        .feature-box:hover { background: #FFFFFF; transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .f-icon {
            width: 45px; height: 45px; border-radius: 12px; background: var(--primary-bg); color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
        }
        .f-content h3 { font-size: 16px; font-weight: 800; margin-bottom: 8px; color: var(--text-dark); }
        .f-content ul { list-style: none; padding: 0; margin: 0;}
        .f-content ul li { font-size: 13px; color: var(--text-muted); margin-bottom: 6px; display: flex; align-items: flex-start; gap: 6px; }
        .f-content ul li::before { content: "•"; color: var(--primary-light); font-weight: bold; }

        .benefits-card {
            background: linear-gradient(135deg, var(--primary), #1E3A8A); color: white; border-radius: 24px;
            padding: 35px 40px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 20px 40px -10px rgba(21, 94, 117, 0.3);
        }
        .benefits-card h3 { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .benefits-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .benefit-item { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 500; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px;}
        .benefit-item i { color: #34D399; font-size: 18px; }

        /* --- 7. FOOTER --- */
        .footer { 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
            padding: 15px 40px; background: white; border-top: 1px solid var(--border);
            font-size: 12px; font-weight: 600; color: var(--text-muted); z-index: 10; margin-top: auto;
        }
        .footer-left { display: flex; flex-direction: column; gap: 4px; }
        .credit-text { font-size: 12px; color: var(--primary-light); font-weight: 700; display: flex; align-items: center; gap: 6px; }
        
        .footer-links { display: flex; gap: 20px; flex-wrap: wrap;}
        .footer-links a { color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .footer-links a:hover { color: var(--primary); }

        /* ANIMATIONS */
        @keyframes fadeRight { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeLeft { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        /* ==========================================================================
           RESPONSIVE MEDIA QUERIES
           ========================================================================== */

        /* Tablets & Small Laptops */
        @media (max-width: 1024px) {
            .wrapper { flex-direction: column; padding: 40px 20px 20px 20px; gap: 40px;}
            .hero { max-width: 100%; text-align: center; }
            .hero-title { font-size: 38px; }
            .stats-row { justify-content: center; }
            .login-section { max-width: 100%; width: 100%; flex-direction: row; } 
            .platform-details { padding: 20px; }
        }

        /* Large Mobile & Portrait Tablets */
        @media (max-width: 768px) {
            /* Fix Header Stacking */
            .header-container { flex-direction: column; gap: 15px; text-align: center; padding: 15px 20px; }
            .header-left, .header-right { flex-direction: column; align-items: center; justify-content: center; text-align: center;}
            .ministry-text { text-align: center; }
            .hindi-title { font-size: 13px; }
            .eng-title { font-size: 12px; }
            
            /* Fix Navbar for Mobile (Hamburger Menu) */
            .nav-container { padding: 10px 20px; }
            .mobile-menu-btn { display: block; }
            .nav-links { 
                display: none; width: 100%; flex-direction: column; align-items: flex-start; padding-bottom: 15px;
            }
            .nav-links.active { display: flex; }
            .nav-link { width: 100%; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
            
            /* Fix Dropdown on Mobile */
            .dropdown { width: 100%; }
            .dropbtn { width: 100%; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); justify-content: space-between;}
            .dropdown-content { 
                position: static; box-shadow: none; border: none; border-radius: 8px; 
                background: rgba(0,0,0,0.1); width: 100%; margin-top: 5px;
            }
            .dropdown-content a { color: #E0F2FE; }
            .dropdown-content a:hover { background: rgba(255,255,255,0.1); color: #FFFFFF; }

            /* Fix Body Content */
            .login-section { flex-direction: column; }
            .action-card { padding: 20px; }
            .footer { flex-direction: column; gap: 12px; text-align: center; justify-content: center; }
            .footer-left { align-items: center; }
            .footer-links { justify-content: center; }
        }

        /* Small Mobile Phones */
        @media (max-width: 480px) {
            .hero-title { font-size: 32px; }
            .stat { min-width: 45%; padding: 10px; } 
            .stat-num { font-size: 20px; }
            .action-card { flex-direction: column; text-align: center; gap: 10px;}
            .action-card .fa-chevron-right { display: none; }
            .benefits-card { padding: 25px 20px; }
        }
    </style>
</head>
<body>
    
    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
    </div>

    <header class="top-header">
        <div class="header-container">
            <div class="header-left">
                <img src="assets/images/RR.png" alt="NIELIT Logo" class="nielit-logo">
                <div class="header-titles">
                    <span class="hindi-title">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</span>
                    <span class="eng-title">National Institute of Electronics & Information Technology, Bhubaneswar</span>
                </div>
            </div>
            <div class="header-right">
                <div class="ministry-text">
                    <strong>Ministry of Electronics & IT</strong>
                    Government of India
                </div>
                <img src="assets/images/image_7c2b82.png" alt="Government of India Emblem" class="emblem">
            </div>
        </div>
    </header>

    <nav class="main-nav">
        <div class="nav-container">
            <a href="#" class="nav-home-btn">
                <img src="assets/images/image_86242d.png" alt="Home" class="nav-custom-icon" onerror="this.outerHTML='<i class=\'fas fa-home\'></i>'"> 
                NIELIT
            </a>
            
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-links" id="navLinks">
                <a href="index.php" class="nav-link">Home</a>
                <a href="notices.php" class="nav-link">Notices</a>
                <a href="#" class="nav-link">Student Zone</a>
                
                <div class="dropdown">
                    <button class="dropbtn">Administration <i class="fas fa-caret-down"></i></button>
                    <div class="dropdown-content">
                        <a href="/admin/admin-login.php">
                            <i class="fas fa-shield-alt"></i> 
                            <div class="ac-text">
                                Master Superadmin Console
                                <span class="ac-desc">Manage system-wide provisioning, audit logs, and user roles.</span>
                            </div>
                        </a>
                        <a href="/finance/finance-login.php">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <div class="ac-text">
                                Finance Dept Dashboard
                                <span class="ac-desc">Institute payment verification and fee reconciliation.</span>
                            </div>
                        </a>
                        <a href="/coordinator/coordinator-login.php">
                            <i class="fas fa-calendar-check"></i>
                            <div class="ac-text">
                                Assessment Coordinator Portal
                                <span class="ac-desc">Schedule exam slots, assign centers, and monitor live metrics.</span>
                            </div>
                        </a>
                    </div>
                </div>
                
                <a href="contact.php" class="nav-link">Contact</a>
            </div>
        </div>
    </nav>

    <div class="ticker-wrap">
        <div class="ticker-label">LIVE UPDATES</div>
        <div class="ticker-move">
            &bull; Registration for O-Level Computer Based Test (July Session) is now live. 
            &nbsp;&nbsp;&nbsp;&bull; Download your Admit Cards directly from the Candidate Portal.
            &nbsp;&nbsp;&nbsp;&bull; Institute Partners can now schedule batches via the TP Portal.
        </div>
    </div>

    <main class="wrapper">
        
        <div class="hero">
            <div class="system-badge">
                <span class="live-dot"></span> System Online
            </div>
            
            <h1 class="hero-title">NIELIT Mock<br><span>Assessment Platform</span></h1>
            <p class="hero-sub">A secure, scalable, and transparent mock assessment platform designed exclusively for candidates of National Institute of Electronics & Information Technology (NIELIT) and its authorized Training Partners. This platform enables pre-assessment preparation for NSQF-aligned and short-term courses, ensuring candidates are fully equipped before appearing in official examinations.</p>

            <div class="stats-row">
                <div class="stat">
                    <div class="stat-num">50<span>K+</span></div>
                    <div class="stat-label">Candidates</div>
                </div>
                <div class="stat">
                    <div class="stat-num">200<span>+</span></div>
                    <div class="stat-label">Exam Sessions</div>
                </div>
                <div class="stat">
                    <div class="stat-num">99<span>.9%</span></div>
                    <div class="stat-label">System Uptime</div>
                </div>
            </div>
        </div>

        <div class="login-section">
            <a href="/candidate/candidate-login.php" class="action-card candidate">
                <div class="ac-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="ac-text">
                    <h2>Candidate Portal</h2>
                    <p>Secure login for students to download admit cards, take live exams, and view official digital scorecards.</p>
                </div>
                <i class="fas fa-chevron-right" style="color: var(--border); font-size: 20px;"></i>
            </a>
            
            <a href="/tp/tp-login.php" class="action-card tp">
                <div class="ac-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="ac-text">
                    <h2>Training Partner Portal</h2>
                    <p>Dedicated dashboard for registered institutes to onboard batches and securely pay exam slot fees.</p>
                </div>
                <i class="fas fa-chevron-right" style="color: var(--border); font-size: 20px;"></i>
            </a>
        </div>

    </main>

    <section class="platform-details">
        <h2 class="section-title">Platform Features</h2>
        
        <div class="features-grid">
            <div class="feature-box">
                <div class="f-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="f-content">
                    <h3>Secure Examination Environment</h3>
                    <ul>
                        <li>End-to-end encryption for data protection</li>
                        <li>AI-based proctoring and identity verification</li>
                        <li>Browser lockdown to prevent malpractice</li>
                    </ul>
                </div>
            </div>
            
            <div class="feature-box">
                <div class="f-icon"><i class="fas fa-server"></i></div>
                <div class="f-content">
                    <h3>Scalable Infrastructure</h3>
                    <ul>
                        <li>Cloud-based system supporting thousands of users</li>
                        <li>Load balancing for uninterrupted delivery</li>
                        <li>Optimized performance across all devices</li>
                    </ul>
                </div>
            </div>

            <div class="feature-box">
                <div class="f-icon"><i class="fas fa-check-circle"></i></div>
                <div class="f-content">
                    <h3>Transparent Evaluation</h3>
                    <ul>
                        <li>Automated, unbiased assessment with instant results</li>
                        <li>Detailed performance analytics and breakdowns</li>
                        <li>Complete audit logs for full traceability</li>
                    </ul>
                </div>
            </div>

            <div class="feature-box">
                <div class="f-icon"><i class="fas fa-users-cog"></i></div>
                <div class="f-content">
                    <h3>Role-Based Access</h3>
                    <ul>
                        <li>Dedicated dashboards for all user roles</li>
                        <li>Training Partners can manage mock tests</li>
                        <li>Admin controls for monitoring and compliance</li>
                    </ul>
                </div>
            </div>

            <div class="feature-box">
                <div class="f-icon"><i class="fas fa-book-open"></i></div>
                <div class="f-content">
                    <h3>NSQF-Aligned Assessments</h3>
                    <ul>
                        <li>Structured as per National Skills Qualifications framework</li>
                        <li>Question banks mapped to curriculum levels</li>
                        <li>Real exam simulation with timed sessions</li>
                    </ul>
                </div>
            </div>

            <div class="feature-box">
                <div class="f-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="f-content">
                    <h3>Reporting & Insights</h3>
                    <ul>
                        <li>Individual and batch-wise performance tracking</li>
                        <li>Weak-area identification for improvement</li>
                        <li>Exportable reports for institutional use</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="benefits-card">
            <h3><i class="fas fa-star"></i> Core Benefits</h3>
            <div class="benefits-grid">
                <div class="benefit-item"><i class="fas fa-check-circle"></i> Enhances candidate confidence and exam readiness.</div>
                <div class="benefit-item"><i class="fas fa-check-circle"></i> Improves pass rates in NIELIT certification exams.</div>
                <div class="benefit-item"><i class="fas fa-check-circle"></i> Ensures absolute fairness, reliability, and accountability.</div>
                <div class="benefit-item"><i class="fas fa-check-circle"></i> Supports Training Partners in delivering quality education.</div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-left">
            <p>&copy; <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.</p>
            <div class="credit-text"><i class="fas fa-code"></i> Designed & Developed by Software Development Team-NIELIT Bhuabaneswar</div>
        </div>
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Use</a>
            <a href="#">Helpdesk: 0674-2960354</a>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }
    </script>
</body>
</html>
