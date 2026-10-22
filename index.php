<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>mads.guru.darsya.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #070a13;
      --card-bg: #0b1120;
      --card-border: #1e293b;
      --lime: #a3e635;
      --lime-dark: #14532d;
      --cyan: #38bdf8;
      --purple: #c084fc;
      --pink: #f472b6;
      --blue: #60a5fa;
      --text: #f1f5f9;
      --text-muted: #94a3b8;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Plus Jakarta Sans", system-ui, sans-serif;
      background-color: var(--bg);
      background-image: 
        radial-gradient(circle at 80% 20%, rgba(56, 189, 248, 0.08) 0%, transparent 40%),
        radial-gradient(circle at 20% 80%, rgba(192, 132, 252, 0.06) 0%, transparent 50%),
        radial-gradient(2px 2px at 20px 30px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(2px 2px at 100px 150px, rgba(255,255,255,0.7), rgba(0,0,0,0)),
        radial-gradient(1.5px 1.5px at 240px 80px, rgba(255,255,255,0.8), rgba(0,0,0,0)),
        radial-gradient(2px 2px at 380px 220px, rgba(56,189,248,0.7), rgba(0,0,0,0)),
        radial-gradient(1.5px 1.5px at 520px 110px, rgba(255,255,255,0.6), rgba(0,0,0,0)),
        radial-gradient(2px 2px at 700px 290px, rgba(163,230,53,0.6), rgba(0,0,0,0)),
        radial-gradient(1.5px 1.5px at 850px 170px, rgba(255,255,255,0.5), rgba(0,0,0,0)),
        radial-gradient(2px 2px at 980px 340px, rgba(244,114,182,0.6), rgba(0,0,0,0));
      background-repeat: repeat;
      background-size: 100% 100%, 100% 100%, 550px 450px, 600px 500px, 450px 350px, 500px 400px, 650px 550px, 700px 600px, 800px 700px, 750px 650px;
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      position: relative;
      overflow-x: hidden;
    }

    /* Ambient Wireframe Web Background */
    .bg-wireframe {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      opacity: 0.18;
    }

    .wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 720px;
      margin: 0 auto;
    }

    /* Top Row */
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .badge-pill {
      background: var(--lime);
      color: #0d2806;
      font-family: "JetBrains Mono", monospace;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      padding: 6px 14px;
      border-radius: 4px;
      display: inline-block;
    }

    .badge-number {
      font-family: "JetBrains Mono", monospace;
      font-size: 3rem;
      font-weight: 800;
      line-height: 1;
      color: transparent;
      -webkit-text-stroke: 1.5px rgba(148, 163, 184, 0.35);
      letter-spacing: -0.05em;
      user-select: none;
    }

    /* Headline */
    h1.hero-title {
      font-size: clamp(2.3rem, 5.5vw, 3.4rem);
      font-weight: 800;
      line-height: 1.12;
      letter-spacing: -0.03em;
      color: #ffffff;
      margin-bottom: 32px;
    }

    .gradient-text {
      background: linear-gradient(90deg, var(--cyan) 0%, var(--blue) 35%, var(--purple) 70%, var(--pink) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    /* Code Terminal Box */
    .code-box {
      background: rgba(11, 17, 32, 0.88);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(99, 102, 241, 0.22);
      border-radius: 18px;
      box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.65), 0 0 40px rgba(56, 189, 248, 0.05);
      overflow: hidden;
      margin-bottom: 24px;
    }

    .box-header {
      padding: 14px 18px;
      background: rgba(15, 23, 42, 0.6);
      border-bottom: 1px solid rgba(99, 102, 241, 0.15);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .window-dots {
      display: flex;
      gap: 7px;
      align-items: center;
    }
    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }
    .dot.red { background: #ef4444; }
    .dot.yellow { background: #f59e0b; }
    .dot.green { background: #10b981; }

    .box-title {
      font-family: "JetBrains Mono", monospace;
      font-size: 0.8rem;
      color: #94a3b8;
      letter-spacing: 0.02em;
    }

    .box-body {
      padding: 26px 24px;
      font-family: "JetBrains Mono", monospace;
      font-size: 0.95rem;
      line-height: 1.85;
      color: #cbd5e1;
    }

    /* Syntax highlight colors */
    .c-green { color: var(--lime); font-weight: 600; }
    .c-cyan { color: var(--cyan); font-weight: 600; }
    .c-purple { color: var(--purple); font-weight: 600; }
    .c-pink { color: var(--pink); font-weight: 600; }

    /* Bottom Bar */
    .bottom-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
      padding-top: 4px;
    }

    .bottom-slogan {
      font-weight: 700;
      font-size: 1.05rem;
      color: #ffffff;
      letter-spacing: -0.01em;
    }

    .bottom-action {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Single Demo Link Button */
    .btn-demo-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--lime);
      color: #0b2306;
      font-family: "JetBrains Mono", monospace;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      transition: all 0.2s ease;
      box-shadow: 0 4px 14px rgba(163, 230, 53, 0.25);
    }
    .btn-demo-link:hover {
      background: #bef264;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(163, 230, 53, 0.4);
    }

    @media (max-width: 640px) {
      .box-body { font-size: 0.85rem; padding: 20px 16px; }
      .badge-number { font-size: 2.2rem; }
      .bottom-bar { flex-direction: column; align-items: flex-start; }
      .btn-demo-link { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<!-- Ambient Wireframe Polygon Lines -->
<svg class="bg-wireframe" viewBox="0 0 1000 1000" preserveAspectRatio="none">
  <defs>
    <linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.6"/>
      <stop offset="50%" stop-color="#818cf8" stop-opacity="0.4"/>
      <stop offset="100%" stop-color="#f472b6" stop-opacity="0.3"/>
    </linearGradient>
  </defs>
  <polygon points="750,80 920,250 820,480 650,420 620,180" fill="none" stroke="url(#lineGrad)" stroke-width="1.2"/>
  <polygon points="620,180 820,480 700,680 500,520 650,420" fill="none" stroke="url(#lineGrad)" stroke-width="0.8"/>
  <polygon points="120,600 280,720 220,900 60,820" fill="none" stroke="url(#lineGrad)" stroke-width="0.9"/>
  <line x1="750" y1="80" x2="820" y2="480" stroke="url(#lineGrad)" stroke-width="0.8"/>
  <line x1="920" y1="250" x2="650" y2="420" stroke="url(#lineGrad)" stroke-width="0.8"/>
  <line x1="120" y1="600" x2="220" y2="900" stroke="url(#lineGrad)" stroke-width="0.8"/>
</svg>

<div class="wrapper">
  <!-- Top Bar -->
  <div class="top-bar">
    <div class="badge-pill">STARTER TEMPLATE</div>
    <div class="badge-number">#01</div>
  </div>

  <!-- Headline -->
  <h1 class="hero-title">
    Hapus halaman ini<br>
    agar web <span class="gradient-text">tampil di sini</span>
  </h1>

  <!-- Code / Terminal Card -->
  <div class="code-box">
    <div class="box-header">
      <div class="window-dots">
        <span class="dot red"></span>
        <span class="dot yellow"></span>
        <span class="dot green"></span>
      </div>
      <div class="box-title">starter-guide.txt</div>
    </div>
    <div class="box-body">
      &ldquo;Hapus atau ganti file <span class="c-green">index.php</span> ini di <span class="c-cyan">VS Code</span> agar aplikasi web buatan Anda langsung <span class="c-purple">tampil di sini</span>.<br><br>
      Seluruh file aplikasi sebelumnya telah dipindahkan ke folder <span class="c-pink">demo/</span>.&rdquo;
    </div>
  </div>

  <!-- Bottom Bar: Only 1 Demo Link, no vscode link, no full path -->
  <div class="bottom-bar">
    <div class="bottom-slogan">Save it. Paste it. Ship it. &rarr;</div>
    <div class="bottom-action">
      <a href="demo/" class="btn-demo-link">Demo &rarr;</a>
    </div>
  </div>
</div>

</body>
</html>
