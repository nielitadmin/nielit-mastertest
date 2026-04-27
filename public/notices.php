<?php
// Start a clean session for the public landing pages
session_name('NIELIT_LANDING');
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Notices — NIELIT Bhubaneswar</title>
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
            --danger: #DC2626;
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
        
        /* Dropdown */
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
            color: white; text-align: center; padding: 50px 20px 80px; position: relative;
        }
        .page-banner h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; letter-spacing: -0.5px;}
        .page-banner p { font-size: 15px; font-weight: 500; opacity: 0.9; }

        /* --- 4. NOTICES CONTAINER --- */
        .notices-wrapper {
            max-width: 1000px;
            margin: -40px auto 60px;
            padding: 0 20px;
            width: 100%;
            position: relative;
            z-index: 10;
        }

        .notices-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }

        /* --- 5. FILTER CONTROLS --- */
        .filter-controls {
            display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;
            background: var(--bg-body); padding: 15px; border-radius: 12px; border: 1px solid var(--border);
        }
        .search-box { flex: 2; position: relative; min-width: 250px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input {
            width: 100%; padding: 12px 15px 12px 40px; border-radius: 8px; border: 1px solid var(--border);
            font-family: inherit; font-size: 14px; outline: none; transition: 0.3s;
        }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
        
        .category-select {
            flex: 1; padding: 12px 15px; border-radius: 8px; border: 1px solid var(--border);
            font-family: inherit; font-size: 14px; outline: none; cursor: pointer; min-width: 180px;
            color: var(--text-dark); font-weight: 600;
        }
        .category-select:focus { border-color: var(--primary); }

        /* --- 6. NOTICE LIST ITEMS --- */
        .notice-list { display: flex; flex-direction: column; gap: 15px; }
        
        .notice-item {
            display: flex; align-items: center; gap: 20px; padding: 20px;
            border: 1px solid var(--border); border-radius: 12px;
            transition: all 0.3s ease; text-decoration: none; color: inherit;
        }
        .notice-item:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-sm);
            transform: translateY(-2px);
            background: #FAFAFA;
        }

        /* Date Calendar Block */
        .notice-date {
            background: var(--primary-bg); color: var(--primary); text-align: center;
            border-radius: 10px; padding: 10px 15px; min-width: 75px; flex-shrink: 0;
            border: 1px solid #DBEAFE;
        }
        .notice-date span { display: block; font-size: 26px; font-weight: 800; line-height: 1; margin-bottom: 2px;}
        .notice-date small { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Notice Content */
        .notice-content { flex: 1; }
        .notice-title { font-size: 16px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; line-height: 1.4; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;}
        
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-exam { background: #E0E7FF; color: #4338CA; }
        .badge-general { background: #F1F5F9; color: #475569; }
        .badge-result { background: #ECFDF5; color: #059669; }
        .badge-tp { background: #CCFBF1; color: #0D9488; }
        
        .badge-new { background: var(--danger); color: white; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

        .notice-meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 15px; align-items: center; font-weight: 500;}
        .notice-meta i { color: var(--primary-light); }

        /* Action Button */
        .btn-download {
            background: white; color: var(--primary); border: 1px solid var(--primary-light);
            padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 13px;
            transition: 0.3s; display: flex; align-items: center; gap: 8px; white-space: nowrap; flex-shrink: 0;
        }
        .notice-item:hover .btn-download { background: var(--primary); color: white; }

        /* Empty State */
        .no-results { text-align: center; padding: 40px; color: var(--text-muted); display: none; font-weight: 500;}
        .no-results i { font-size: 40px; color: #CBD5E1; margin-bottom: 15px; }

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
        @media (max-width: 768px) {
            .header-container { flex-direction: column; gap: 15px; text-align: center; padding: 15px 20px; }
            .header-left, .header-right { flex-direction: column; align-items: center; justify-content: center; text-align: center;}
            .ministry-text { text-align: center; }
            
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

            .notices-wrapper { margin-top: -20px; }
            .notices-card { padding: 20px; }
            .filter-controls { flex-direction: column; }
            
            .notice-item { flex-direction: column; align-items: flex-start; gap: 15px; padding: 15px;}
            .notice-date { display: flex; align-items: baseline; gap: 8px; padding: 8px 12px; width: auto; border-radius: 6px;}
            .notice-date span { font-size: 16px; margin-bottom: 0;}
            .btn-download { width: 100%; justify-content: center; }
            
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
                <a href="notices.php" class="nav-link active">Notices</a>
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

    <div class="page-banner">
        <h1>Official Notices & Announcements</h1>
        <p>Stay updated with the latest circulars, exam schedules, and institute notifications.</p>
    </div>

    <main class="notices-wrapper">
        <div class="notices-card">
            
            <div class="filter-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search notices by keyword or Ref No..." onkeyup="filterNotices()">
                </div>
                <select id="categoryFilter" class="category-select" onchange="filterNotices()">
                    <option value="all">All Categories</option>
                    <option value="exam">Examinations</option>
                    <option value="result">Results</option>
                    <option value="tp">Training Partners</option>
                    <option value="general">General Updates</option>
                </select>
            </div>

            <div class="notice-list" id="noticeList">
                
                <a href="#" class="notice-item" data-category="exam">
                    <div class="notice-date">
                        <span>18</span>
                        <small>Nov 2024</small>
                    </div>
                    <div class="notice-content">
                        <div class="notice-title">
                            Release of Admit Cards for O-Level Computer Based Test (July Session)
                            <span class="badge badge-new">NEW</span>
                        </div>
                        <div class="notice-meta">
                            <span class="badge badge-exam">Examinations</span>
                            <span><i class="fas fa-file-alt"></i> Ref: NIELIT/BBSR/EXAM/2024-89</span>
                            <span><i class="fas fa-file-pdf"></i> 1.2 MB</span>
                        </div>
                    </div>
                    <div class="btn-download">
                        <i class="fas fa-download"></i> View PDF
                    </div>
                </a>

                <a href="#" class="notice-item" data-category="tp">
                    <div class="notice-date">
                        <span>12</span>
                        <small>Nov 2024</small>
                    </div>
                    <div class="notice-content">
                        <div class="notice-title">
                            Extension of Deadline for Training Partner Batch Upload and Fee Payment
                            <span class="badge badge-new">NEW</span>
                        </div>
                        <div class="notice-meta">
                            <span class="badge badge-tp">Training Partners</span>
                            <span><i class="fas fa-file-alt"></i> Ref: NIELIT/BBSR/TP/2024-42</span>
                            <span><i class="fas fa-file-pdf"></i> 850 KB</span>
                        </div>
                    </div>
                    <div class="btn-download">
                        <i class="fas fa-download"></i> View PDF
                    </div>
                </a>

                <a href="#" class="notice-item" data-category="result">
                    <div class="notice-date">
                        <span>05</span>
                        <small>Nov 2024</small>
                    </div>
                    <div class="notice-content">
                        <div class="notice-title">
                            Declaration of Digital Scorecards for January 2024 Assessment Cycle
                        </div>
                        <div class="notice-meta">
                            <span class="badge badge-result">Results</span>
                            <span><i class="fas fa-file-alt"></i> Ref: NIELIT/BBSR/RES/2024-11</span>
                            <span><i class="fas fa-file-pdf"></i> 2.1 MB</span>
                        </div>
                    </div>
                    <div class="btn-download">
                        <i class="fas fa-download"></i> View PDF
                    </div>
                </a>

                <a href="#" class="notice-item" data-category="general">
                    <div class="notice-date">
                        <span>28</span>
                        <small>Oct 2024</small>
                    </div>
                    <div class="notice-content">
                        <div class="notice-title">
                            Revised Operating Hours for NIELIT Bhubaneswar Helpdesk
                        </div>
                        <div class="notice-meta">
                            <span class="badge badge-general">General Updates</span>
                            <span><i class="fas fa-file-alt"></i> Ref: NIELIT/BBSR/ADMIN/2024-05</span>
                            <span><i class="fas fa-file-pdf"></i> 400 KB</span>
                        </div>
                    </div>
                    <div class="btn-download">
                        <i class="fas fa-download"></i> View PDF
                    </div>
                </a>
                
                <a href="#" class="notice-item" data-category="exam">
                    <div class="notice-date">
                        <span>15</span>
                        <small>Oct 2024</small>
                    </div>
                    <div class="notice-content">
                        <div class="notice-title">
                            Candidate Instructions & Guidelines for Computer Based Test (CBT) Environment
                        </div>
                        <div class="notice-meta">
                            <span class="badge badge-exam">Examinations</span>
                            <span><i class="fas fa-file-alt"></i> Ref: NIELIT/BBSR/EXAM/2024-77</span>
                            <span><i class="fas fa-file-pdf"></i> 3.4 MB</span>
                        </div>
                    </div>
                    <div class="btn-download">
                        <i class="fas fa-download"></i> View PDF
                    </div>
                </a>

            </div>

            <div id="noResults" class="no-results">
                <i class="fas fa-search-minus"></i>
                <p>No notices found matching your search criteria.</p>
            </div>

        </div>
    </main>

    <footer class="footer">
        <p>© <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.</p>
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Use</a>
            <a href="#">Helpdesk: 0674-2960354</a>
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }

        // Real-time Search & Category Filter
        function filterNotices() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            
            const noticeItems = document.querySelectorAll('.notice-item');
            let visibleCount = 0;

            noticeItems.forEach(item => {
                const textContent = item.textContent.toLowerCase();
                const itemCategory = item.getAttribute('data-category');
                
                const matchesSearch = textContent.includes(searchInput);
                const matchesCategory = (categoryFilter === 'all' || itemCategory === categoryFilter);
                
                if (matchesSearch && matchesCategory) {
                    item.style.display = "flex";
                    visibleCount++;
                } else {
                    item.style.display = "none";
                }
            });

            // Show or hide empty state message
            const noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                noResults.style.display = "block";
            } else {
                noResults.style.display = "none";
            }
        }
    </script>
</body>
</html>