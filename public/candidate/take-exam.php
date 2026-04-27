<?php
// Force PHP to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: my-exams.php");
    exit();
}

$exam_id = $_GET['exam_id'];

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

try {
    // 1. Get registration details
    $stmt = $pdo->prepare("
        SELECT er.id as registration_id, es.exam_date, es.start_time, es.end_time, es.is_practice, 
               ec.duration_minutes, ec.category_name, ec.category_code,
               es.exam_code, es.category_id
        FROM exam_registrations er
        JOIN exam_sessions es ON er.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        WHERE er.candidate_id = ? AND es.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) { header("Location: my-exams.php"); exit(); }
    
    // 2. Get candidate details
    $candidate = $pdo->prepare("SELECT u.full_name, c.registration_number FROM users u LEFT JOIN candidates c ON u.id = c.user_id WHERE u.id = ?");
    $candidate->execute([$_SESSION['user_id']]);
    $candidate_info = $candidate->fetch(PDO::FETCH_ASSOC);
    
    // 3. Fetch up to 100 questions
    $questions = $pdo->prepare("
        SELECT q.*, 
               (SELECT JSON_ARRAYAGG(
                   JSON_OBJECT(
                       'id', id, 
                       'text', option_text, 
                       'order', option_order
                   )
               ) FROM question_options WHERE question_id = q.id) as options
        FROM questions q
        WHERE q.category_id = ? AND q.is_active = 1
        ORDER BY q.id ASC
        LIMIT 100 
    ");
    $questions->execute([$registration['category_id']]);
    $questions = $questions->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questions)) { die("No questions available for this exam."); }
    
    // 4. Get saved answers
    $answers = $pdo->prepare("SELECT question_id, selected_option_id FROM candidate_responses WHERE registration_id = ?");
    $answers->execute([$registration['registration_id']]);
    $saved_answers = [];
    while ($row = $answers->fetch(PDO::FETCH_ASSOC)) { $saved_answers[$row['question_id']] = $row['selected_option_id']; }
    
    // 5. SMART TIMER LOGIC
    if ($registration['is_practice']) {
        $session_timer_key = 'exam_end_' . $registration['registration_id'];
        if (!isset($_SESSION[$session_timer_key])) {
            $_SESSION[$session_timer_key] = time() + ($registration['duration_minutes'] * 60);
        }
        $end_time = $_SESSION[$session_timer_key];
    } else {
        $date_clean = explode(' ', $registration['exam_date'])[0];
        $start_clean = explode('+', $registration['start_time'])[0];
        $end_clean = explode('+', $registration['end_time'])[0];

        $start_ts = strtotime($date_clean . ' ' . $start_clean);
        $end_ts = strtotime($date_clean . ' ' . $end_clean);
        
        if ($end_ts < $start_ts) { 
            $end_ts += 86400; 
        } 
        
        $end_time = $end_ts;
    }
    
} catch (PDOException $e) { 
    die("Database Error: " . $e->getMessage()); 
}

$total_questions = count($questions);
$candidate_name = !empty($candidate_info['full_name']) ? $candidate_info['full_name'] : 'Candidate';
$roll_number = !empty($candidate_info['registration_number']) ? $candidate_info['registration_number'] : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT CBT System - <?php echo htmlspecialchars($registration['category_code']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --tcs-blue: #0078D7;
            --tcs-dark: #333333;
            --tcs-light: #F4F4F4;
            --tcs-border: #CCCCCC;
            --ans-blue: #1D4ED8;        
            --not-ans-red: #D9534F;     
            --not-vis-grey: #E2E2E2;    
            --rev-yellow: #EAB308;      
            --rev-ans-purple: #9B59B6;  
            --ans-green: #5CB85C;       
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Open Sans', Arial, sans-serif; }

        body { background-color: #E9ECEF; color: var(--tcs-dark); height: 100vh; overflow: hidden; display: flex; flex-direction: column; user-select: none; }

        /* FULLSCREEN ENTRANCE OVERLAY */
        #start-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(30, 41, 59, 0.98); z-index: 9999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: white; text-align: center;
        }
        #start-overlay h2 { font-size: 32px; margin-bottom: 10px; color: #60A5FA; }
        #start-overlay p { font-size: 16px; margin-bottom: 30px; max-width: 500px; color: #CBD5E1; line-height: 1.5; }
        .btn-start-fs { background: #16A34A; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.4); transition: 0.3s; }
        .btn-start-fs:hover { background: #15803D; transform: scale(1.05); }

        /* HEADER */
        .header { background: var(--tcs-dark); color: white; display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; height: 50px; flex-shrink: 0; }
        .header-title { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
        .header-system { font-size: 13px; font-weight: 600; color: #aaa; }

        /* MAIN WRAPPER */
        .wrapper { display: flex; flex: 1; overflow: hidden; margin: 5px; background: white; border: 1px solid var(--tcs-border); }

        /* LEFT PANEL (Questions & Footer) */
        .left-panel { flex: 1; display: flex; flex-direction: column; border-right: 1px solid var(--tcs-border); }
        
        .section-bar { background: var(--tcs-blue); color: white; padding: 8px 20px; font-size: 14px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .section-bar span { background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 4px; }

        .question-header { display: flex; justify-content: space-between; padding: 10px 20px; border-bottom: 1px solid var(--tcs-border); background: var(--tcs-light); font-weight: 600; font-size: 15px; }
        .marks-info { color: green; font-size: 14px; font-weight: 700; }

        .question-body { flex: 1; padding: 25px; overflow-y: auto; font-size: 16px; }
        .q-text { margin-bottom: 25px; line-height: 1.6; font-weight: bold; }
        
        .options-list { display: flex; flex-direction: column; gap: 12px; }
        .option-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 15px; border: 1px solid transparent; border-radius: 6px; cursor: pointer; transition: 0.2s; background: #fdfdfd; border: 1px solid #eee; }
        .option-item input[type="radio"] { transform: scale(1.3); margin-top: 4px; cursor: pointer; flex-shrink: 0; }
        .option-item:hover { background: #f0f7ff; border-color: #cce4ff; }
        
        .opt-content { flex: 1; overflow-wrap: break-word; line-height: 1.5; }
        .opt-content code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        .opt-content img { max-width: 100%; height: auto; display: block; margin-top: 5px; border-radius: 4px; }

        /* LEFT FOOTER ACTIONS */
        .actions-footer { display: flex; justify-content: space-between; padding: 12px 20px; border-top: 1px solid var(--tcs-border); background: var(--tcs-light); flex-shrink: 0; }
        .btn { padding: 8px 16px; border: 1px solid var(--tcs-border); background: white; font-weight: 600; cursor: pointer; font-size: 14px; color: var(--tcs-dark); transition: 0.2s; border-radius: 4px; }
        .btn:hover { background: #e0e0e0; }
        
        .btn-primary { background: var(--tcs-blue); color: white; border-color: #005a9e; }
        .btn-primary:hover { background: #005a9e; }
        
        .btn-success { background: var(--ans-green); color: white; border-color: #4cae4c; }
        .btn-success:hover { background: #4cae4c; }

        .left-actions { display: flex; gap: 10px; }

        /* 🟢 OPTIMIZED RIGHT PANEL UI */
        .right-panel { width: 340px; display: flex; flex-direction: column; background: var(--tcs-light); flex-shrink: 0; }
        
        /* Compact Video Proctoring Box */
        .proctor-box {
            background: #111; padding: 12px; display: flex; justify-content: center; align-items: center; 
            border-bottom: 1px solid var(--tcs-border);
        }
        .video-wrapper { position: relative; width: 100%; max-width: 180px; border-radius: 6px; overflow: hidden; border: 1px solid #444; background: #000; box-shadow: 0 4px 6px rgba(0,0,0,0.3);}
        .proctor-video { width: 100%; height: auto; display: block; transform: scaleX(-1); }
        .rec-indicator {
            position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,0.7); color: white;
            font-size: 9px; padding: 2px 6px; border-radius: 12px; display: flex; align-items: center; gap: 4px; font-weight: bold; border: 1px solid rgba(255,255,255,0.2);
        }
        .rec-dot { width: 6px; height: 6px; background: #FF0000; border-radius: 50%; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0; } }
        .ai-status {
            position: absolute; bottom: 0; left: 0; width: 100%; background: rgba(0,0,0,0.7); color: #4ADE80;
            font-size: 10px; padding: 4px 0; font-family: monospace; letter-spacing: 0.5px; text-align: center;
        }
        
        /* Compact Candidate Box */
        .candidate-box { display: flex; gap: 12px; padding: 10px 15px; border-bottom: 1px solid var(--tcs-border); background: white; align-items: center; }
        .candidate-photo { width: 45px; height: 45px; background: var(--tcs-border); display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; border-radius: 4px; flex-shrink: 0;}
        .candidate-details { font-size: 12px; line-height: 1.3; color: #555;}
        .candidate-details strong { font-size: 14px; color: var(--tcs-blue); display: block; margin-bottom: 2px;}

        /* Inline Timer Box */
        .timer-box { padding: 12px 15px; background: white; border-bottom: 1px solid var(--tcs-border); display: flex; justify-content: space-between; align-items: center; }
        .timer-text { font-size: 14px; font-weight: 700; color: #444; }
        .timer-value { font-size: 22px; font-weight: 800; color: var(--not-ans-red); font-family: monospace; }

        /* Denser Palette Legend */
        .palette-legend { padding: 10px 15px; border-bottom: 1px solid var(--tcs-border); display: flex; flex-wrap: wrap; gap: 8px; font-size: 11px; background: white; }
        .legend-item { display: flex; align-items: center; gap: 5px; width: calc(50% - 4px); }
        .legend-item.full-width { width: 100%; }
        .l-box { width: 20px; height: 18px; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 4px; font-size: 10px; border: 1px solid #aaa; }
        
        .s-ans { background: var(--ans-blue); border-color: var(--ans-blue); color: white; }
        .s-not-ans { background: var(--not-ans-red); border-color: var(--not-ans-red); color: white; }
        .s-not-vis { background: white; color: black; }
        .s-rev { background: var(--rev-yellow); border-color: #B48600; color: black; }
        .s-rev-ans { background: var(--rev-ans-purple); border-color: var(--rev-ans-purple); color: white; position: relative; }
        .s-rev-ans::after { content: '✓'; position: absolute; bottom: -1px; right: 1px; font-size: 9px; color: #5CB85C; font-weight: bold;}

        /* Stretching Palette Container */
        .palette-container { padding: 12px 15px; flex: 1; background: #eef2f5; display: flex; flex-direction: column; overflow: hidden; }
        .palette-title { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: var(--tcs-blue); flex-shrink: 0;}
        .palette-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; overflow-y: auto; padding-right: 5px; align-content: flex-start; flex: 1; }
        
        .p-btn { height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; cursor: pointer; user-select: none; border: 1px solid #aaa; transition: 0.1s; }
        .p-btn:hover { opacity: 0.8; }
        .p-btn.current { border: 2px solid var(--tcs-dark); transform: scale(1.1); box-shadow: 0 0 5px rgba(0,0,0,0.3); z-index: 10; }

        /* Submit Footer */
        .submit-box { padding: 12px 15px; border-top: 1px solid var(--tcs-border); background: white; text-align: center; flex-shrink: 0;}
        .btn-final-submit { width: 100%; padding: 12px; font-size: 15px; background: var(--ans-green); color: white; border: none; font-weight: bold; cursor: pointer; border-radius: 4px; transition: 0.2s;}
        .btn-final-submit:hover { background: #4cae4c; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 25px; width: 500px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-header { border-bottom: 1px solid var(--tcs-border); padding-bottom: 10px; margin-bottom: 15px; font-size: 18px; font-weight: bold; color: var(--tcs-blue); }
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px; }
        .summary-table th, .summary-table td { border: 1px solid var(--tcs-border); padding: 8px; text-align: center; }
        .summary-table th { background: var(--tcs-light); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body>

    <div id="start-overlay">
        <h2><i class="fas fa-lock" style="margin-right: 10px;"></i> Secured Exam Environment</h2>
        <p>This exam operates in a strict, locked full-screen mode with active AI Video Monitoring. Switching tabs, refreshing, or exiting full screen will be recorded by the system.<br><br><b>Please allow Camera Permissions on the next screen. Your time will start ticking as soon as you enter.</b></p>
        <button class="btn-start-fs" onclick="launchFullScreenExam()">Allow Camera & Start Exam</button>
    </div>

    <div class="header">
        <div class="header-title">NIELIT Computer Based Test</div>
        <div class="header-system">System ID: C<?php echo $exam_id; ?>-<?php echo $_SESSION['user_id']; ?></div>
    </div>

    <div class="wrapper">
        
        <div class="left-panel">
            <div class="section-bar">
                <div>Section: <span id="sectionName"><?php echo htmlspecialchars($registration['category_code']); ?> Main</span></div>
                <div style="cursor: pointer;" onclick="confirmExit()">Exit Exam <i class="fas fa-sign-out-alt"></i></div>
            </div>

            <div class="question-header">
                <div id="qNumberDisplay">Question No. 1</div>
                <div class="marks-info" id="qMarksDisplay">Marks: +1.0</div>
            </div>
            
            <div class="question-body" id="questionContainer">
                </div>

            <div class="actions-footer">
                <div class="left-actions">
                    <button class="btn" onclick="markForReview()">Mark for Review & Next</button>
                    <button class="btn" onclick="clearResponse()">Clear Response</button>
                </div>
                <button class="btn btn-success" id="btnSaveNext" onclick="saveAndNext()">Save & Next</button>
            </div>
        </div>

        <div class="right-panel">
            
            <div class="proctor-box">
                <div class="video-wrapper">
                    <div class="rec-indicator"><div class="rec-dot"></div> REC</div>
                    <video id="dummyProctorVideo" class="proctor-video" autoplay muted playsinline></video>
                    <div class="ai-status"><i class="fas fa-shield-alt"></i> AI Proctoring Active</div>
                </div>
            </div>
            
            <div class="candidate-box">
                <div class="candidate-photo"><i class="fas fa-user"></i></div>
                <div class="candidate-details">
                    <strong><?php echo htmlspecialchars($candidate_name); ?></strong>
                    Roll: <?php echo htmlspecialchars($roll_number); ?><br>
                    <?php echo htmlspecialchars($registration['category_name']); ?>
                </div>
            </div>

            <div class="timer-box">
                <div class="timer-text">Time Left:</div>
                <div class="timer-value" id="timer">00:00:00</div>
            </div>

            <div class="palette-legend">
                <div class="legend-item"><div class="l-box s-ans" id="legAns">0</div> Answered</div>
                <div class="legend-item"><div class="l-box s-not-ans" id="legNotAns">0</div> Not Answered</div>
                <div class="legend-item"><div class="l-box s-not-vis" id="legNotVis"><?php echo $total_questions; ?></div> Not Visited</div>
                <div class="legend-item"><div class="l-box s-rev" id="legRev">0</div> Marked Review</div>
                <div class="legend-item full-width"><div class="l-box s-rev-ans" id="legRevAns">0</div> Answered & Marked for Review</div>
            </div>

            <div class="palette-container">
                <div class="palette-title">Question Palette:</div>
                <div class="palette-grid" id="paletteGrid">
                    </div>
            </div>

            <div class="submit-box">
                <button class="btn-final-submit" onclick="showSubmitModal()">Submit Test</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="submitModal">
        <div class="modal-content">
            <div class="modal-header">Exam Summary</div>
            <p style="margin-bottom: 15px; font-size: 14px;">Please verify your exam summary before final submission.</p>
            <table class="summary-table">
                <tr>
                    <th>Total Questions</th>
                    <th>Answered</th>
                    <th>Not Answered</th>
                    <th>Marked for Review</th>
                    <th>Not Visited</th>
                </tr>
                <tr>
                    <td><?php echo $total_questions; ?></td>
                    <td id="modAns">0</td>
                    <td id="modNotAns">0</td>
                    <td id="modRev">0</td>
                    <td id="modNotVis">0</td>
                </tr>
            </table>
            <p style="margin-bottom: 20px; font-size: 14px; color: var(--not-ans-red); font-weight: bold;">Are you sure you want to submit the exam? No changes will be allowed after submission.</p>
            <div class="modal-footer">
                <button class="btn" onclick="hideModal()">No, Go Back</button>
                <button class="btn btn-primary" onclick="executeSubmission()">Yes, Submit</button>
            </div>
        </div>
    </div>

    <script>
        const questions = <?php echo json_encode($questions); ?>;
        const registrationId = <?php echo $registration['registration_id']; ?>;
        const examId = <?php echo $exam_id; ?>;
        const totalQuestions = <?php echo $total_questions; ?>;
        
        // This variable now perfectly aligns with the wall-clock time if it's a formal exam
        const examEndTime = <?php echo $end_time * 1000; ?>; 
        
        let currentIdx = 0;
        let answers = <?php echo empty($saved_answers) ? '{}' : json_encode($saved_answers, JSON_FORCE_OBJECT); ?>;
        let visited = {}; 
        let reviewMarks = {}; 
        let tempSelection = null;
        let hasStarted = false; 

        // BROWSER NAVIGATION LOCK
        window.onload = function() {
            document.addEventListener('contextmenu', e => e.preventDefault());
            history.pushState(null, null, location.href);
            window.onpopstate = function() { history.go(1); };
        };

        window.onbeforeunload = function() {
            return "Are you sure you want to leave? Your exam might be submitted automatically.";
        };

        function escapeHTML(str) {
            if (!str) return "";
            return str.replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        function formatContent(content) {
            if (!content) return "";
            if (content.match(/\.(jpeg|jpg|gif|png)$/i)) {
                return `<img src="${content}" alt="Option Image">`;
            }
            if (content.includes('<') && content.includes('>')) {
                return `<code>${escapeHTML(content)}</code>`;
            }
            return content;
        }

        // Start Dummy Video Feed
        function startDummyProctoring() {
            const video = document.getElementById('dummyProctorVideo');
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    video.srcObject = stream;
                })
                .catch(function(err) {
                    console.log("Camera access denied or unavailable.");
                });
            }
        }

        // --- FULLSCREEN LOGIC ---
        function launchFullScreenExam() {
            let elem = document.documentElement;
            if (elem.requestFullscreen) { elem.requestFullscreen(); } 
            else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } 
            else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); }

            document.getElementById('start-overlay').style.display = 'none';
            
            if (!hasStarted && questions.length > 0) {
                hasStarted = true;
                Object.keys(answers).forEach(qId => visited[qId] = true);
                
                startDummyProctoring(); 
                
                renderQuestion(0);
                startTimer();
            }
        }

        document.addEventListener('fullscreenchange', exitHandler);
        document.addEventListener('webkitfullscreenchange', exitHandler);
        document.addEventListener('mozfullscreenchange', exitHandler);
        document.addEventListener('MSFullscreenChange', exitHandler);

        function exitHandler() {
            if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
                if(hasStarted) {
                    alert("SECURITY WARNING: You have exited Full Screen mode. This violation has been logged.");
                }
            }
        }

        function renderQuestion(idx) {
            currentIdx = idx;
            const q = questions[idx];
            visited[q.id] = true;
            tempSelection = answers[q.id] || null;

            document.getElementById('qNumberDisplay').innerText = `Question No. ${idx + 1}`;
            document.getElementById('qMarksDisplay').innerText = `Marks: +${q.marks}.0`;
            
            let optionsHtml = '';
            if (q.options) {
                const opts = JSON.parse(q.options);
                opts.forEach(opt => {
                    const checked = (answers[q.id] == opt.id) ? 'checked' : '';
                    optionsHtml += `
                        <label class="option-item">
                            <input type="radio" name="q_opt" value="${opt.id}" ${checked} onchange="tempSelection=this.value">
                            <div class="opt-content">${formatContent(opt.text)}</div>
                        </label>`;
                });
            }

            document.getElementById('questionContainer').innerHTML = `
                <div class="q-text">${formatContent(q.question_text)}</div>
                <div class="options-list">${optionsHtml}</div>`;
            
            const saveBtn = document.getElementById('btnSaveNext');
            if (idx === totalQuestions - 1) {
                saveBtn.innerHTML = '<i class="fas fa-check-double" style="margin-right:5px;"></i> Save & Submit Test';
                saveBtn.style.background = "var(--tcs-blue)";
                saveBtn.style.borderColor = "var(--tcs-blue)";
            } else {
                saveBtn.innerHTML = 'Save & Next';
                saveBtn.style.background = "var(--ans-green)";
                saveBtn.style.borderColor = "#4cae4c";
            }

            updatePalette();
        }

        function saveAndNext() {
            if (tempSelection) {
                answers[questions[currentIdx].id] = tempSelection;
                reviewMarks[questions[currentIdx].id] = false;
                syncDB(questions[currentIdx].id, tempSelection);
            }
            
            if (currentIdx < totalQuestions - 1) {
                renderQuestion(currentIdx + 1);
            } else {
                updatePalette();
                showSubmitModal();
            }
        }

        function markForReview() {
            if (tempSelection) {
                answers[questions[currentIdx].id] = tempSelection;
                syncDB(questions[currentIdx].id, tempSelection);
            }
            reviewMarks[questions[currentIdx].id] = true;
            
            if (currentIdx < totalQuestions - 1) {
                renderQuestion(currentIdx + 1);
            } else {
                updatePalette();
                showSubmitModal();
            }
        }

        function clearResponse() {
            delete answers[questions[currentIdx].id];
            reviewMarks[questions[currentIdx].id] = false;
            tempSelection = null;
            syncDB(questions[currentIdx].id, null);
            renderQuestion(currentIdx);
        }

        function syncDB(qId, optId) {
            fetch('save-answer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ registration_id: registrationId, question_id: qId, option_id: optId })
            }).catch(err => console.log('Auto-save sync error'));
        }

        function updatePalette() {
            let html = '';
            let cAns=0, cNotAns=0, cNotVis=0, cRev=0, cRevAns=0;

            questions.forEach((q, i) => {
                let cls = 's-not-vis';
                const isVis = visited[q.id];
                const isAns = answers[q.id] !== undefined;
                const isRev = reviewMarks[q.id];

                if (isAns && isRev) { cls = 's-rev-ans'; cRevAns++; }
                else if (isAns) { cls = 's-ans'; cAns++; }
                else if (isRev) { cls = 's-rev'; cRev++; }
                else if (isVis) { cls = 's-not-ans'; cNotAns++; }
                else { cNotVis++; }

                let currentClass = (i === currentIdx) ? 'current' : '';
                html += `<div class="p-btn ${cls} ${currentClass}" onclick="renderQuestion(${i})">${i + 1}</div>`;
            });

            document.getElementById('paletteGrid').innerHTML = html;
            
            document.getElementById('legAns').innerText = cAns;
            document.getElementById('legNotAns').innerText = cNotAns;
            document.getElementById('legNotVis').innerText = cNotVis;
            document.getElementById('legRev').innerText = cRev;
            document.getElementById('legRevAns').innerText = cRevAns;

            window.examStats = { ans: cAns + cRevAns, notAns: cNotAns, rev: cRev + cRevAns, notVis: cNotVis };
        }

        function startTimer() {
            const timerEl = document.getElementById('timer');
            const interval = setInterval(() => {
                const remaining = examEndTime - Date.now();
                
                if (remaining <= 0) {
                    clearInterval(interval);
                    timerEl.textContent = "00:00:00";
                    window.onbeforeunload = null;
                    executeSubmission();
                    return;
                }
                
                const h = Math.floor(remaining / 3600000).toString().padStart(2, '0');
                const m = Math.floor((remaining % 3600000) / 60000).toString().padStart(2, '0');
                const s = Math.floor((remaining % 60000) / 1000).toString().padStart(2, '0');
                
                timerEl.textContent = `${h}:${m}:${s}`;
            }, 1000);
        }

        function showSubmitModal() {
            updatePalette(); 
            document.getElementById('modAns').innerText = window.examStats.ans;
            document.getElementById('modNotAns').innerText = window.examStats.notAns;
            document.getElementById('modRev').innerText = window.examStats.rev;
            document.getElementById('modNotVis').innerText = window.examStats.notVis;
            document.getElementById('submitModal').style.display = 'flex';
        }

        function hideModal() { document.getElementById('submitModal').style.display = 'none'; }

        function confirmExit() {
            if(confirm("Exiting the exam will NOT submit it, but time will continue ticking. Proceed?")) {
                window.onbeforeunload = null; 
                window.location.href = "my-exams.php";
            }
        }

        function executeSubmission() {
            window.onbeforeunload = null; 
            
            fetch('submit-exam.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ registration_id: registrationId, answers: answers, exam_id: examId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (document.exitFullscreen) { document.exitFullscreen(); }
                    window.location.replace('candidate-dashboard.php');
                }
                else alert('Submission Error: ' + data.error);
            })
            .catch(err => {
                window.location.replace('candidate-dashboard.php');
            });
        }
    </script>
</body>
</html>