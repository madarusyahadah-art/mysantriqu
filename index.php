<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Default App — mads.guru.darsya.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0f141c;
      --card: #161e2a;
      --card-border: #232e3f;
      --text: #f0f4f8;
      --muted: #8b9bb4;
      --accent: #10b981;
      --accent-soft: rgba(16, 185, 129, 0.12);
      --brand: #3b82f6;
      --brand-soft: rgba(59, 130, 246, 0.12);
      --code-bg: #0a0d14;
      --code-border: #1e2636;
      --gold: #f59e0b;
      --gold-soft: rgba(245, 158, 11, 0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Plus Jakarta Sans", system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 32px 16px;
      line-height: 1.6;
    }

    .container {
      width: 100%;
      max-width: 680px;
      margin: 0 auto;
    }

    /* Main Card */
    .card {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 32px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .badge-status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 4px 12px;
      border-radius: 999px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 0.8rem;
      font-weight: 600;
      margin-bottom: 16px;
    }
    .badge-status::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 10px var(--accent);
    }

    h1 {
      font-size: 1.75rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin-bottom: 12px;
      color: #fff;
    }

    .desc {
      color: var(--muted);
      font-size: 0.95rem;
      margin-bottom: 24px;
    }

    /* Alert / Notice Box */
    .notice-box {
      background: var(--brand-soft);
      border: 1px solid rgba(59, 130, 246, 0.25);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
      display: flex;
      gap: 12px;
      align-items: flex-start;
      font-size: 0.9rem;
    }
    .notice-icon {
      font-size: 1.25rem;
      line-height: 1;
    }
    .notice-content b {
      color: #fff;
      display: block;
      margin-bottom: 4px;
    }
    .notice-content code {
      font-family: "JetBrains Mono", monospace;
      background: rgba(0, 0, 0, 0.35);
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.82rem;
      color: #93c5fd;
    }

    /* Tree View Box */
    .tree-box {
      background: var(--code-bg);
      border: 1px solid var(--code-border);
      border-radius: 14px;
      padding: 18px 20px;
      margin-bottom: 18px;
      font-family: "JetBrains Mono", monospace;
      font-size: 0.84rem;
    }
    .tree-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 12px;
      margin-bottom: 12px;
      border-bottom: 1px solid var(--code-border);
      color: var(--muted);
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .tree-content {
      line-height: 1.75;
      color: #cbd5e1;
    }
    .tree-folder {
      color: var(--gold);
      font-weight: 600;
    }
    .tree-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tree-item a {
      color: #93c5fd;
      text-decoration: none;
      transition: color .15s ease;
    }
    .tree-item a:hover {
      color: #60a5fa;
      text-decoration: underline;
    }
    .tree-meta {
      color: #64748b;
      font-size: 0.76rem;
      margin-left: auto;
    }

    /* Small Demo Buttons / Pills */
    .demo-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      padding: 6px 0;
    }
    .btn-demo-primary {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--accent);
      color: #052e16;
      font-size: 0.82rem;
      font-weight: 700;
      padding: 7px 14px;
      border-radius: 999px;
      text-decoration: none;
      transition: all .15s ease;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }
    .btn-demo-primary:hover {
      background: #34d399;
      transform: translateY(-1px);
    }
    .btn-demo-sub {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: var(--card-border);
      color: #cbd5e1;
      font-size: 0.78rem;
      font-weight: 500;
      padding: 6px 11px;
      border-radius: 999px;
      text-decoration: none;
      border: 1px solid rgba(255, 255, 255, 0.05);
      transition: all .15s ease;
    }
    .btn-demo-sub:hover {
      background: #2a374a;
      color: #fff;
      border-color: var(--brand);
    }

    .vscode-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #93c5fd;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      margin-top: 8px;
    }
    .vscode-btn:hover {
      text-decoration: underline;
    }

    /* Footer */
    footer {
      margin-top: 24px;
      text-align: center;
      font-size: 0.8rem;
      color: #475569;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="card">
    <div class="badge-status">Server Aktif & Siap Digunakan</div>
    <h1>Halaman Default</h1>
    <p class="desc">
      Hapus atau ganti file <code>index.php</code> ini melalui VS Code Editor agar halaman/aplikasi Anda tampil di sini.
    </p>

    <!-- Notice -->
    <div class="notice-box">
      <div class="notice-icon">💡</div>
      <div class="notice-content">
        <b>Cara Mengganti Halaman Ini:</b>
        Buka VS Code di browser, lalu hapus atau ubah berkas <code>index.php</code> di root directory (<code>/srv/mysantriqu/src/index.php</code>). File proyek demo telah dipindahkan ke folder <code>demo/</code>.
        <div>
          <a href="https://mysantriqu-code.vm1.ds.darsya.com" target="_blank" class="vscode-btn">
            ➔ Buka VS Code Online (code-server)
          </a>
        </div>
      </div>
    </div>

    <!-- Tree List Box -->
    <div class="tree-box">
      <div class="tree-header">
        <span>📁 Tree list files demo/</span>
        <span>Directory Tree</span>
      </div>
      <div class="tree-content">
        <div class="tree-folder">📂 demo/</div>
        <div class="tree-item">├── 📄 <a href="demo/index.php">index.php</a> <span class="tree-meta">Landing Page</span></div>
        <div class="tree-item">├── 📊 <a href="demo/dashboard.html">dashboard.html</a> <span class="tree-meta">Dashboard</span></div>
        <div class="tree-item">├── 👥 <a href="demo/santri.html">santri.html</a> <span class="tree-meta">Data Santri</span></div>
        <div class="tree-item">├── 📝 <a href="demo/absensi.html">absensi.html</a> <span class="tree-meta">Absensi</span></div>
        <div class="tree-item">├── 📅 <a href="demo/kegiatan.html">kegiatan.html</a> <span class="tree-meta">Kegiatan</span></div>
        <div class="tree-item">├── ⚖️ <a href="demo/pelanggaran.html">pelanggaran.html</a> <span class="tree-meta">Pelanggaran</span></div>
        <div class="tree-item">├── 📈 <a href="demo/laporan.html">laporan.html</a> <span class="tree-meta">Rekap Cetak</span></div>
        <div class="tree-item">├── 👤 <a href="demo/admin-users.html">admin-users.html</a> <span class="tree-meta">Hak Akses</span></div>
        <div class="tree-item">├── 📁 css/ <span class="tree-meta">(app.css)</span></div>
        <div class="tree-item">└── 📁 js/ <span class="tree-meta">(app.js)</span></div>
      </div>
    </div>

    <!-- Demo Button Links -->
    <div class="demo-bar">
      <a href="demo/index.php" class="btn-demo-primary">
        🚀 Buka Demo MySantriQu
      </a>
      <a href="demo/dashboard.html" class="btn-demo-sub">Dashboard</a>
      <a href="demo/santri.html" class="btn-demo-sub">Santri</a>
      <a href="demo/absensi.html" class="btn-demo-sub">Absensi</a>
      <a href="demo/kegiatan.html" class="btn-demo-sub">Kegiatan</a>
      <a href="demo/pelanggaran.html" class="btn-demo-sub">Pelanggaran</a>
      <a href="demo/laporan.html" class="btn-demo-sub">Laporan</a>
      <a href="demo/admin-users.html" class="btn-demo-sub">Pengguna</a>
    </div>
  </div>

  <footer>
    mads.guru.darsya.com &bull; PHP <?= phpversion(); ?> on Apache (vm1)
  </footer>
</div>

</body>
</html>
