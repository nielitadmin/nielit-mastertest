<?php
// Start session for consistency
session_name('NIELIT_LANDING');
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — NIELIT Bhubaneswar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Premium Color Palette (Matched with index) */
            --primary: #155E75;        
            --primary-light: #0284C7;  
            --primary-bg: #EFF6FF;     
            --text-dark: #0F172A;
            --text-muted: #475569;
            --bg-body: #F8FAFC;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-body);
            min-height: 100vh; 
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* --- 1. OFFICIAL TOP HEADER (WHITE) --- */
        .top-header { background: #FFFFFF; border-bottom: 1px solid var(--border); z-index: 100; position: relative; width: 100%; }
        .header-container { display: flex; justify-content: space-between; align-items: center; max-width: 1380px; margin: 0 auto; padding: 12px 40px; width: 100%; }
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
        .main-nav { background: var(--primary); box-shadow: var(--shadow-sm); z-index: 99; position: relative; width: 100%; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1380px; margin: 0 auto; padding: 0 40px; width: 100%; flex-wrap: wrap; }
        .nav-home-btn { color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; padding: 15px 0; transition: color 0.3s; }
        .nav-home-btn:hover { color: #E0F2FE;}
        .nav-custom-icon { height: 18px; width: auto; object-fit: contain; filter: brightness(0) invert(1); }
        .mobile-menu-btn { display: none; background: none; border: none; color: #FFFFFF; font-size: 24px; cursor: pointer; padding: 10px 0; }
        .nav-links { display: flex; height: 100%; align-items: center; }
        .nav-link { color: #E0F2FE; text-decoration: none; font-weight: 600; font-size: 14px; padding: 16px 20px; transition: 0.3s; display: flex; align-items: center; gap: 6px; }
        .nav-link:hover, .nav-link.active { color: #FFFFFF; background: rgba(255, 255, 255, 0.1); }
        
        /* Dropdown specific styles */
        .dropdown { position: relative; display: inline-block; height: 100%; }
        .dropbtn { background: transparent; color: #E0F2FE; border: none; font-family: inherit; font-weight: 600; font-size: 14px; padding: 16px 20px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.3s; height: 100%; outline: none; }
        .dropdown:hover .dropbtn { color: #FFFFFF; background: rgba(255, 255, 255, 0.1); }
        .dropdown-content { display: none; position: absolute; background-color: #FFFFFF; min-width: 250px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 200; top: 100%; right: 0; overflow: hidden; border: 1px solid var(--border); border-top: 3px solid var(--primary); border-radius: 0 0 12px 12px; padding: 10px 0; }
        .dropdown:hover .dropdown-content { display: block; animation: dropFade 0.2s ease forwards; }
        @keyframes dropFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-content a { color: var(--text-dark); padding: 12px 20px; text-decoration: none; display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; font-weight: 600; transition: 0.2s; border-bottom: 1px solid #F1F5F9; }
        .dropdown-content a:last-child { border-bottom: none; }
        .dropdown-content a:hover { background-color: var(--primary-bg); color: var(--primary); padding-left: 25px;}
        .dropdown-content a i { color: var(--primary-light); width: 18px; text-align: center; font-size: 15px; margin-top: 2px; }
        .dropdown-content .ac-text { flex: 1; display: flex; flex-direction: column; }
        .dropdown-content .ac-desc { font-weight: 500; font-size: 11px; color: var(--text-muted); margin-top: 2px; line-height: 1.4; }

        /* --- 3. PAGE BANNER --- */
        .page-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-align: center;
            padding: 50px 20px 80px; /* Extra padding at bottom for card overlap */
            position: relative;
        }
        .page-banner h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; letter-spacing: -0.5px;}
        .page-banner p { font-size: 15px; font-weight: 500; opacity: 0.9; }

        /* --- 4. CONTACT CONTAINER (Split Layout) --- */
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1200px;
            margin: -40px auto 60px; /* Pull up to overlap banner */
            padding: 0 40px;
            width: 100%;
            position: relative;
            z-index: 10;
        }

        /* --- 5. CONTACT INFO CARD --- */
        .contact-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            height: 100%;
        }

        .contact-header {
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--primary-light);
            margin-bottom: 40px;
        }
        .contact-header i { font-size: 26px; }
        .contact-header h2 { font-size: 24px; font-weight: 700; color: var(--primary-light); margin: 0;}

        .info-row {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #F1F5F9;
        }
        .info-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        
        .info-icon {
            width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
            background: var(--primary-bg); color: var(--primary-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; margin-top: 2px;
        }

        .info-details h3 { font-size: 16px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
        .info-details p { font-size: 14px; font-weight: 500; color: var(--text-muted); line-height: 1.6; margin: 0; }
        .info-details a { color: var(--primary-light); text-decoration: none; transition: 0.3s; }
        .info-details a:hover { text-decoration: underline; color: var(--primary); }

        /* --- 6. MAP CARD --- */
        .map-card {
            background: #FFFFFF;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            height: 100%;
            min-height: 450px;
            position: relative;
        }

        .map-card iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        /* --- 7. FOOTER --- */
        .footer { 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
            padding: 15px 40px; background: white; border-top: 1px solid var(--border);
            font-size: 12px; font-weight: 600; color: var(--text-muted); margin-top: auto;
        }
        .footer-links { display: flex; gap: 20px; flex-wrap: wrap;}
        .footer-links a { color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .footer-links a:hover { color: var(--primary); }

        /* --- RESPONSIVE MEDIA QUERIES --- */
        @media (max-width: 1024px) {
            .contact-container { padding: 0 20px; }
        }

        @media (max-width: 768px) {
            /* Header Fixes */
            .header-container { flex-direction: column; gap: 15px; text-align: center; padding: 15px 20px; }
            .header-left, .header-right { flex-direction: column; align-items: center; justify-content: center; text-align: center;}
            .ministry-text { text-align: center; }
            
            /* Navbar Fixes */
            .nav-container { padding: 10px 20px; }
            .mobile-menu-btn { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; align-items: flex-start; padding-bottom: 15px; }
            .nav-links.active { display: flex; }
            .nav-link { width: 100%; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
            
            .dropdown { width: 100%; }
            .dropbtn { width: 100%; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); justify-content: space-between;}
            .dropdown-content { position: static; box-shadow: none; border: none; border-radius: 8px; background: rgba(0,0,0,0.1); width: 100%; margin-top: 5px; }
            .dropdown-content a { color: #E0F2FE; }
            .dropdown-content a:hover { background: rgba(255,255,255,0.1); color: #FFFFFF; }

            /* Layout Fixes */
            .contact-container { grid-template-columns: 1fr; margin-top: -20px; gap: 20px;}
            .contact-card { padding: 30px; }
            .map-card { min-height: 350px; }
            .footer { flex-direction: column; gap: 10px; text-align: center; justify-content: center; }
            .footer-links { justify-content: center; }
        }
    </style>
</head>
<body>

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
            <a href="index.php" class="nav-home-btn">
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
                        <a href="/nielit-bbsr-mock/public/admin/admin-login.php">
                            <i class="fas fa-shield-alt"></i> 
                            <div class="ac-text">
                                Master Superadmin Console
                                <span class="ac-desc">Manage system-wide provisioning, audit logs, and user roles.</span>
                            </div>
                        </a>
                        <a href="/nielit-bbsr-mock/public/finance/finance-login.php">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <div class="ac-text">
                                Finance Dept Dashboard
                                <span class="ac-desc">Institute payment verification and fee reconciliation.</span>
                            </div>
                        </a>
                        <a href="/nielit-bbsr-mock/public/coordinator/coordinator-login.php">
                            <i class="fas fa-calendar-check"></i>
                            <div class="ac-text">
                                Assessment Coordinator Portal
                                <span class="ac-desc">Schedule exam slots, assign centers, and monitor live metrics.</span>
                            </div>
                        </a>
                    </div>
                </div>
                
                <a href="contact.php" class="nav-link active">Contact</a>
            </div>
        </div>
    </nav>

    <div class="page-banner">
        <h1>Get in touch with NIELIT Bhubaneswar</h1>
        <p>We are here to help you with any inquiries regarding examinations or logistics.</p>
    </div>

    <main class="contact-container">
        
        <div class="contact-card">
            <div class="contact-header">
                <i class="fas fa-info-circle"></i>
                <h2>Contact Information</h2>
            </div>

            <div class="info-row">
                <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="info-details">
                    <h3>Address</h3>
                    <p>3rd Floor, OCAC Tower<br>Acharya Vihar<br>Bhubaneswar - 751013<br>Odisha, India</p>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="info-details">
                    <h3>Phone</h3>
                    <p><a href="tel:0674-2960354">0674-2960354</a></p>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                <div class="info-details">
                    <h3>Email</h3>
                    <p><a href="mailto:dir-bbsr@nielit.gov.in">dir-bbsr@nielit.gov.in</a></p>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon"><i class="fas fa-clock"></i></div>
                <div class="info-details">
                    <h3>Working Hours</h3>
                    <p>Monday - Friday: 09:00 AM - 05:30 PM<br><span style="color: #DC2626;">Saturday & Sunday: Closed</span></p>
                </div>
            </div>
        </div>

        <div class="map-card">
            <iframe 
                src="https://maps.google.com/maps?q=NIELIT%20Bhubaneswar,%20OCAC%20Tower,%20Acharya%20Vihar&t=&z=16&ie=UTF8&iwloc=&output=embed" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.</p>
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