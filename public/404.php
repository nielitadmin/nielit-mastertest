<?php
// public/404.php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020810;
            --blue: #0ea5e9;
            --cyan: #22d3ee;
            --purple: #818cf8;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--bg);
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: crosshair;
        }
        #particleCanvas {
            position: fixed; inset: 0; z-index: 0;
        }
        .grid-bg {
            position: fixed; inset: -100%;
            background-image:
                linear-gradient(rgba(14,165,233,0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(14,165,233,0.06) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 15s linear infinite;
            z-index: 1;
        }
        @keyframes gridMove {
            0%   { transform: perspective(600px) rotateX(20deg) translateY(0); }
            100% { transform: perspective(600px) rotateX(20deg) translateY(50px); }
        }
        .scanlines {
            position: fixed; inset: 0;
            background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0,0,0,0.18) 3px, rgba(0,0,0,0.18) 4px);
            z-index: 2; pointer-events: none;
            animation: scanFlicker 8s ease-in-out infinite;
        }
        @keyframes scanFlicker {
            0%,100%{opacity:1} 50%{opacity:0.92} 72%{opacity:0.85} 73%{opacity:1}
        }
        .vignette {
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, transparent 40%, rgba(2,8,16,0.92) 100%);
            z-index: 2; pointer-events: none;
        }
        /* HUD Corners */
        .hud-corner {
            position: fixed; width: 70px; height: 70px; z-index: 10;
        }
        .hud-corner::before, .hud-corner::after {
            content: ''; position: absolute; background: var(--blue);
            box-shadow: 0 0 10px var(--blue);
        }
        .hud-corner::before { width: 100%; height: 2px; top: 0; }
        .hud-corner::after  { width: 2px; height: 100%; top: 0; }
        .hud-tl { top: 20px; left: 20px; }
        .hud-tr { top: 20px; right: 20px; transform: scaleX(-1); }
        .hud-bl { bottom: 20px; left: 20px; transform: scaleY(-1); }
        .hud-br { bottom: 20px; right: 20px; transform: scale(-1); }
        .hud-label {
            position: fixed; font-family: 'Share Tech Mono', monospace;
            font-size: 10px; color: rgba(14,165,233,0.45);
            letter-spacing: 0.12em; z-index: 10;
        }
        .hud-label.tl { top: 28px; left: 100px; }
        .hud-label.tr { top: 28px; right: 100px; }
        .hud-label.bl { bottom: 28px; left: 100px; }
        .hud-label.br { bottom: 28px; right: 100px; }
        /* Rings */
        .ring {
            position: fixed; border-radius: 50%;
            border: 1px solid rgba(14,165,233,0.12);
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            z-index: 1;
            animation: expand 4s ease-out infinite;
        }
        .ring:nth-child(1) { width:300px; height:300px; animation-delay:0s; }
        .ring:nth-child(2) { width:550px; height:550px; animation-delay:1.3s; }
        .ring:nth-child(3) { width:800px; height:800px; animation-delay:2.6s; }
        @keyframes expand {
            0%   { transform:translate(-50%,-50%) scale(0.5); opacity:0.7; }
            100% { transform:translate(-50%,-50%) scale(1.5); opacity:0; }
        }
        /* Container */
        .container {
            position: relative; z-index: 5;
            text-align: center; padding: 40px 24px;
            max-width: 760px; width: 100%;
        }
        /* Terminal */
        .terminal-line {
            font-family: 'Share Tech Mono', monospace;
            font-size: 13px; color: rgba(34,211,238,0.7);
            letter-spacing: 0.08em; height: 22px;
            margin-bottom: 20px;
            animation: fadeUp 0.8s ease both;
        }
        #typed-text::after { content: '█'; animation: blink 1s step-end infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
        /* 404 */
        .error-code-wrap {
            position: relative; display: inline-block;
            margin-bottom: 8px;
            animation: fadeUp 0.8s ease 0.1s both;
        }
        .error-code {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(160px, 28vw, 260px);
            line-height: 0.85;
            color: transparent;
            -webkit-text-stroke: 1.5px rgba(14,165,233,0.2);
            display: block; user-select: none;
        }
        .error-code-fill {
            position: absolute; inset: 0;
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(160px, 28vw, 260px);
            line-height: 0.85;
            background: linear-gradient(135deg, #0284c7 0%, #22d3ee 45%, #818cf8 100%);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 30px rgba(14,165,233,0.5));
            animation: breathe 3s ease-in-out infinite;
        }
        @keyframes breathe {
            0%,100% { filter: drop-shadow(0 0 20px rgba(14,165,233,0.4)); }
            50%      { filter: drop-shadow(0 0 60px rgba(34,211,238,0.9)); }
        }
        .error-code-fill::before, .error-code-fill::after {
            content: '404'; position: absolute; inset: 0;
            background: inherit;
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .error-code-fill::before { animation: glitchTop 3.5s infinite; }
        .error-code-fill::after  { animation: glitchBot 3.5s infinite; }
        @keyframes glitchTop {
            0%,90%,100% { clip-path:none; transform:none; opacity:0; }
            91% { clip-path:inset(10% 0 70% 0); transform:translateX(-8px); opacity:1; }
            92% { clip-path:inset(30% 0 50% 0); transform:translateX(6px);  opacity:1; }
            93% { clip-path:none; transform:none; opacity:0; }
        }
        @keyframes glitchBot {
            0%,90%,100% { clip-path:none; transform:none; opacity:0; }
            91% { clip-path:inset(65% 0 5% 0);  transform:translateX(8px);  opacity:1; }
            92% { clip-path:inset(80% 0 0% 0);   transform:translateX(-6px); opacity:1; }
            93% { clip-path:none; transform:none; opacity:0; }
        }
        /* Divider */
        .divider {
            display:flex; align-items:center; gap:14px;
            max-width:400px; margin:0 auto 24px;
            animation: fadeUp 0.8s ease 0.2s both;
        }
        .divider-line {
            flex:1; height:1px;
            background: linear-gradient(90deg, transparent, rgba(14,165,233,0.5), transparent);
        }
        .divider-diamond {
            width:8px; height:8px; background:var(--blue);
            transform:rotate(45deg); box-shadow:0 0 12px var(--blue);
            animation: spinSlow 4s linear infinite;
        }
        @keyframes spinSlow { to { transform:rotate(225deg); } }
        /* Title */
        .error-title {
            font-size: clamp(24px, 4vw, 34px); font-weight:700;
            letter-spacing:-0.03em; margin-bottom:14px;
            animation: fadeUp 0.8s ease 0.25s both;
        }
        .error-title .highlight {
            background: linear-gradient(90deg, var(--blue), var(--cyan));
            -webkit-background-clip:text; background-clip:text;
            -webkit-text-fill-color:transparent;
        }
        /* Message */
        .error-msg {
            color:#475569; font-size:15px; line-height:1.8;
            max-width:440px; margin:0 auto 36px; font-weight:300;
            animation: fadeUp 0.8s ease 0.3s both;
        }
        /* Status */
        .status-grid {
            display:flex; justify-content:center; gap:10px;
            flex-wrap:wrap; margin-bottom:40px;
            animation: fadeUp 0.8s ease 0.35s both;
        }
        .status-card {
            background:rgba(14,165,233,0.04);
            border:1px solid rgba(14,165,233,0.12);
            border-radius:10px; padding:10px 18px;
            font-family:'Share Tech Mono', monospace;
            font-size:11px; letter-spacing:0.1em; color:#475569;
            display:flex; align-items:center; gap:8px;
            transition:border-color 0.3s, background 0.3s, color 0.3s;
        }
        .status-card:hover {
            border-color:rgba(14,165,233,0.4);
            background:rgba(14,165,233,0.1); color:var(--cyan);
        }
        .status-dot {
            width:7px; height:7px; border-radius:50%;
            animation: dotPulse 2s ease-in-out infinite;
        }
        .sd-red    { background:#ef4444; box-shadow:0 0 8px #ef4444; animation-delay:0s; }
        .sd-yellow { background:#f59e0b; box-shadow:0 0 8px #f59e0b; animation-delay:0.4s; }
        .sd-green  { background:#22c55e; box-shadow:0 0 8px #22c55e; animation-delay:0.8s; }
        @keyframes dotPulse {
            0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.7)}
        }
        /* Button */
        .btn-wrap { animation: fadeUp 0.8s ease 0.45s both; display:inline-block; }
        .btn-home {
            display:inline-flex; align-items:center; gap:12px;
            background:linear-gradient(135deg, #0369a1, #0ea5e9, #22d3ee);
            background-size:200%; background-position:0%;
            color:white; text-decoration:none;
            padding:16px 36px; border-radius:14px;
            font-weight:600; font-size:15px; letter-spacing:0.02em;
            position:relative; overflow:hidden;
            transition:background-position 0.5s, transform 0.3s, box-shadow 0.3s;
            box-shadow:0 10px 40px -8px rgba(14,165,233,0.55);
        }
        .btn-home::before {
            content:''; position:absolute; inset:1px; border-radius:13px;
            background:linear-gradient(135deg, rgba(255,255,255,0.12), transparent 60%);
            pointer-events:none;
        }
        .btn-home::after {
            content:''; position:absolute;
            top:-50%; left:-75%; width:50%; height:200%;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);
            transform:skewX(-20deg);
            animation:shimmer 3s ease-in-out infinite;
        }
        @keyframes shimmer { 0%{left:-75%} 60%,100%{left:150%} }
        .btn-home:hover {
            background-position:100%;
            transform:translateY(-4px) scale(1.02);
            box-shadow:0 20px 50px -10px rgba(14,165,233,0.8);
        }
        .btn-arrow { transition:transform 0.3s; }
        .btn-home:hover .btn-arrow { transform:translateX(5px); }
        @keyframes fadeUp {
            from{opacity:0;transform:translateY(20px)}
            to{opacity:1;transform:translateY(0)}
        }
    </style>
</head>
<body>

<canvas id="particleCanvas"></canvas>

<div class="ring"></div>
<div class="ring"></div>
<div class="ring"></div>

<div class="grid-bg" id="gridLayer"></div>
<div class="scanlines"></div>
<div class="vignette"></div>

<div class="hud-corner hud-tl"></div>
<div class="hud-corner hud-tr"></div>
<div class="hud-corner hud-bl"></div>
<div class="hud-corner hud-br"></div>
<div class="hud-label tl">NIELIT//BBSR</div>
<div class="hud-label tr" id="hud-clock">SYS_ONLINE</div>
<div class="hud-label bl">LAT:20.2961°N LNG:85.8245°E</div>
<div class="hud-label br">ERR:0x00000194</div>

<div class="container">

    <div class="terminal-line"><span id="typed-text"></span></div>

    <div class="error-code-wrap" id="codeLayer">
        <div class="error-code">404</div>
        <div class="error-code-fill">404</div>
    </div>

    <div class="divider">
        <div class="divider-line"></div>
        <div class="divider-diamond"></div>
        <div class="divider-line"></div>
    </div>

    <h1 class="error-title">Signal <span class="highlight">Lost</span> in the Void</h1>

    <p class="error-msg">
        The requested route terminated unexpectedly. Coordinates have been logged.
        Our systems remain operational — the destination does not.
    </p>

    <div class="status-grid">
        <div class="status-card"><div class="status-dot sd-red"></div> ROUTE_NOT_FOUND</div>
        <div class="status-card"><div class="status-dot sd-yellow"></div> HTTP_404</div>
        <div class="status-card"><div class="status-dot sd-green"></div> SERVER_NOMINAL</div>
        <div class="status-card"><div class="status-dot sd-green"></div> DB_CONNECTED</div>
    </div>

    <div class="btn-wrap">
        <a href="/index.php" class="btn-home">
            Return to Base
            <span class="btn-arrow">→</span>
        </a>
    </div>

</div>

<script>
// ── TYPEWRITER ──
const phrases = [
    '> SCANNING ROUTES... TERMINATED',
    '> ERROR CODE: 404 // PAGE_MISSING',
    '> ATTEMPTING RECOVERY... FAILED',
    '> REDIRECT TO BASE RECOMMENDED',
];
let pi = 0, ci = 0, deleting = false;
const tyEl = document.getElementById('typed-text');
function type() {
    const phrase = phrases[pi];
    if (!deleting) {
        tyEl.textContent = phrase.slice(0, ++ci);
        if (ci === phrase.length) { deleting = true; setTimeout(type, 2200); return; }
    } else {
        tyEl.textContent = phrase.slice(0, --ci);
        if (ci === 0) { deleting = false; pi = (pi + 1) % phrases.length; }
    }
    setTimeout(type, deleting ? 28 : 55);
}
type();

// ── LIVE CLOCK ──
setInterval(() => {
    document.getElementById('hud-clock').textContent =
        'T+' + new Date().toTimeString().slice(0,8);
}, 1000);

// ── CANVAS ──
const canvas = document.getElementById('particleCanvas');
const ctx = canvas.getContext('2d');
let W, H, mouse = { x: -9999, y: -9999 };

function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
resize();
window.addEventListener('resize', resize);

// Particles
class Particle {
    constructor(init) {
        this.reset(init);
    }
    reset(init = false) {
        this.x = Math.random() * W;
        this.y = init ? Math.random() * H : H + 10;
        this.size = Math.random() * 1.8 + 0.3;
        this.speedY = -(Math.random() * 0.6 + 0.15);
        this.speedX = (Math.random() - 0.5) * 0.3;
        this.baseOpacity = Math.random() * 0.5 + 0.2;
        this.opacity = this.baseOpacity;
        this.hue = Math.random() > 0.5 ? '14,165,233' : '34,211,238';
    }
    update() {
        this.x += this.speedX;
        this.y += this.speedY;
        const dx = this.x - mouse.x, dy = this.y - mouse.y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 120) {
            const f = (120 - dist) / 120;
            this.x += (dx / dist) * f * 2.5;
            this.y += (dy / dist) * f * 2.5;
            this.opacity = Math.min(1, this.baseOpacity + f * 0.5);
        } else {
            this.opacity = this.baseOpacity;
        }
        if (this.y < -10) this.reset();
    }
    draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI*2);
        ctx.fillStyle = `rgba(${this.hue},${this.opacity})`;
        ctx.fill();
    }
}

const particles = Array.from({ length: 140 }, (_, i) => new Particle(true));

// Data streams (katakana rain)
const CHAR_H = 18;
const streams = Array.from({ length: 20 }, () => {
    const s = {
        x: 0, y: 0, speed: 0, chars: [], opacity: 0,
        reset() {
            this.x = Math.random() * W;
            this.y = -(Math.floor(Math.random() * 15) + 5) * CHAR_H;
            this.speed = Math.random() * 1.4 + 0.5;
            this.chars = Array.from({ length: Math.floor(Math.random() * 14) + 5 },
                () => String.fromCharCode(0x30A0 + Math.floor(Math.random() * 96)));
            this.opacity = Math.random() * 0.25 + 0.05;
        }
    };
    s.reset();
    s.y = Math.random() * H; // scatter initially
    return s;
});

// Shooting stars
class Star {
    constructor() { this.reset(); }
    reset() {
        this.x = Math.random() * W;
        this.y = Math.random() * H * 0.5;
        this.len = Math.random() * 120 + 60;
        this.speed = Math.random() * 8 + 4;
        this.opacity = Math.random() * 0.5 + 0.3;
        this.active = Math.random() < 0.3;
        this.delay = Math.random() * 300;
    }
    update() {
        if (this.delay > 0) { this.delay--; return; }
        if (!this.active) return;
        this.x += this.speed;
        this.y += this.speed * 0.4;
        if (this.x > W + 50) { this.reset(); }
    }
    draw() {
        if (!this.active || this.delay > 0) return;
        const grad = ctx.createLinearGradient(this.x - this.len, this.y - this.len * 0.4, this.x, this.y);
        grad.addColorStop(0, `rgba(14,165,233,0)`);
        grad.addColorStop(1, `rgba(34,211,238,${this.opacity})`);
        ctx.beginPath();
        ctx.moveTo(this.x - this.len, this.y - this.len * 0.4);
        ctx.lineTo(this.x, this.y);
        ctx.strokeStyle = grad;
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }
}
const stars = Array.from({ length: 5 }, () => new Star());

function animFrame() {
    ctx.clearRect(0, 0, W, H);

    // Particles
    particles.forEach(p => { p.update(); p.draw(); });

    // Data streams
    ctx.font = `13px "Share Tech Mono", monospace`;
    streams.forEach(s => {
        s.y += s.speed;
        if (s.y > H + s.chars.length * CHAR_H) s.reset();
        s.chars.forEach((ch, i) => {
            const alpha = (1 - i / s.chars.length) * s.opacity;
            ctx.fillStyle = i === 0
                ? `rgba(200,245,255,${Math.min(1, alpha * 3)})`
                : `rgba(14,165,233,${alpha})`;
            ctx.fillText(ch, s.x, s.y - i * CHAR_H);
            if (Math.random() < 0.015)
                s.chars[i] = String.fromCharCode(0x30A0 + Math.floor(Math.random() * 96));
        });
    });

    // Shooting stars
    stars.forEach(s => { s.update(); s.draw(); });

    requestAnimationFrame(animFrame);
}
animFrame();

// ── MOUSE PARALLAX ──
window.addEventListener('mousemove', e => {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
    const cx = W / 2, cy = H / 2;
    const dx = (e.clientX - cx) / cx;
    const dy = (e.clientY - cy) / cy;
    document.getElementById('gridLayer').style.transform =
        `perspective(600px) rotateX(20deg) translateY(${dy * 14}px) translateX(${dx * 10}px)`;
    document.getElementById('codeLayer').style.transform =
        `translate(${dx * -20}px, ${dy * -14}px)`;
});

// ── CLICK RIPPLE ──
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `@keyframes rippleOut { to { transform:translate(-50%,-50%) scale(50); opacity:0; } }`;
document.head.appendChild(rippleStyle);

document.addEventListener('click', e => {
    const r = document.createElement('div');
    r.style.cssText = `position:fixed;left:${e.clientX}px;top:${e.clientY}px;
        width:6px;height:6px;border-radius:50%;
        border:2px solid rgba(14,165,233,0.9);
        transform:translate(-50%,-50%) scale(0);
        pointer-events:none;z-index:9999;
        animation:rippleOut 0.8s ease-out forwards;`;
    document.body.appendChild(r);
    setTimeout(() => r.remove(), 800);
});
</script>
</body>
</html>