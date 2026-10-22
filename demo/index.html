<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MySantriQu — Manajemen Santri</title>
<meta name="description" content="MySantriQu: absensi, kegiatan, pelanggaran, dan laporan santri dalam satu aplikasi.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f7f4ec; --surface:#fffdf8; --ink:#1b2a22; --muted:#5d6b62; --line:#e4dfd2;
  --brand:#0f6b4f; --brand-2:#14936b; --brand-soft:#e3f1ea; --gold:#c8963e; --gold-soft:#f6ecd8;
  --shadow:0 1px 2px rgba(20,40,30,.06),0 12px 32px -12px rgba(20,40,30,.18);
}
@media (prefers-color-scheme:dark){
  :root{--bg:#0e1612;--surface:#15201b;--ink:#eef3ef;--muted:#9fb0a6;--line:#24332b;
  --brand:#3fbf8f;--brand-2:#5fd6a8;--brand-soft:#173327;--gold:#e0b25e;--gold-soft:#2c2416;
  --shadow:0 1px 2px rgba(0,0,0,.3),0 16px 40px -16px rgba(0,0,0,.6)}
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:var(--bg);color:var(--ink);line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
.wrap{max-width:1160px;margin:0 auto;padding:0 20px}
h1,h2,h3{font-family:Fraunces,Georgia,serif;line-height:1.12;letter-spacing:-.01em}

/* nav */
header{position:sticky;top:0;z-index:10;background:color-mix(in srgb,var(--bg) 82%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
nav{display:flex;align-items:center;justify-content:space-between;height:68px}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.1rem}
.logo i{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--brand),var(--brand-2));display:grid;place-items:center;color:#fff;font-style:normal;font-family:Fraunces,serif}
.links{display:flex;gap:28px;font-size:.94rem;color:var(--muted)}
.links a:hover{color:var(--ink)}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:999px;font-weight:600;font-size:.94rem;transition:transform .15s,box-shadow .15s,background .15s}
.btn-primary{background:var(--brand);color:#fff;box-shadow:0 8px 20px -8px var(--brand)}
@media (prefers-color-scheme:dark){.btn-primary{color:#07130e}}
.btn-primary:hover{transform:translateY(-1px);background:var(--brand-2)}
.btn-ghost{border:1px solid var(--line);background:var(--surface)}
.btn-ghost:hover{border-color:var(--brand)}

/* hero */
.hero{padding:72px 0 56px;display:grid;grid-template-columns:1.05fr 1fr;gap:56px;align-items:center}
.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:var(--gold-soft);color:var(--gold);font-size:.82rem;font-weight:600}
.hero h1{font-size:clamp(2.4rem,5vw,3.8rem);margin:18px 0}
.hero h1 em{font-style:italic;color:var(--brand)}
.hero p{font-size:1.1rem;color:var(--muted);max-width:520px}
.cta{display:flex;gap:12px;margin-top:30px;flex-wrap:wrap}
.trust{display:flex;gap:28px;margin-top:40px;flex-wrap:wrap}
.trust b{display:block;font-family:Fraunces,serif;font-size:1.7rem}
.trust span{font-size:.85rem;color:var(--muted)}

/* mock dashboard */
.mock{background:var(--surface);border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);padding:20px;position:relative}
.mock-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.mock-top h3{font-size:1.05rem}
.chip{font-size:.75rem;padding:4px 10px;border-radius:999px;background:var(--brand-soft);color:var(--brand);font-weight:600}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.kpi{border:1px solid var(--line);border-radius:14px;padding:12px}
.kpi small{color:var(--muted);font-size:.74rem}
.kpi b{display:block;font-size:1.35rem;margin-top:2px}
.bars{display:flex;align-items:flex-end;gap:8px;height:110px;margin:18px 0 6px;padding:0 4px}
.bars div{flex:1;border-radius:6px 6px 2px 2px;background:var(--brand-soft);position:relative}
.bars div.on{background:linear-gradient(var(--brand-2),var(--brand))}
.days{display:flex;gap:8px;padding:0 4px;font-size:.7rem;color:var(--muted)}
.days span{flex:1;text-align:center}
.list{margin-top:14px;display:grid;gap:8px}
.row{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:12px;background:var(--bg);font-size:.85rem}
.av{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:.75rem;color:#fff;flex:none}
.row .st{margin-left:auto;font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:999px}
.ok{background:var(--brand-soft);color:var(--brand)} .warn{background:var(--gold-soft);color:var(--gold)}
.float{position:absolute;bottom:-22px;left:-22px;background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:12px 16px;box-shadow:var(--shadow);display:flex;gap:10px;align-items:center;font-size:.85rem}
.float i{width:36px;height:36px;border-radius:10px;background:var(--gold-soft);color:var(--gold);display:grid;place-items:center;font-style:normal;font-size:1.1rem}

/* features */
section{padding:84px 0}
.head{max-width:620px;margin-bottom:44px}
.head h2{font-size:clamp(1.9rem,3.6vw,2.7rem);margin:10px 0 12px}
.head p{color:var(--muted)}
.label{font-size:.8rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--brand)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:26px;transition:transform .2s,box-shadow .2s,border-color .2s}
.card:hover{transform:translateY(-4px);box-shadow:var(--shadow);border-color:color-mix(in srgb,var(--brand) 35%,var(--line))}
.ic{width:46px;height:46px;border-radius:13px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;margin-bottom:18px}
.ic svg{width:22px;height:22px}
.card h3{font-size:1.25rem;margin-bottom:8px}
.card p{color:var(--muted);font-size:.95rem}
.card .go{display:inline-block;margin-top:14px;font-weight:600;font-size:.88rem;color:var(--brand)}

/* steps */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;counter-reset:s}
.step{border-top:2px solid var(--line);padding-top:22px;counter-increment:s}
.step::before{content:"0" counter(s);font-family:Fraunces,serif;font-size:2rem;color:var(--gold)}
.step h3{font-size:1.2rem;margin:6px 0}
.step p{color:var(--muted);font-size:.95rem}

/* band */
.band{background:linear-gradient(135deg,var(--brand),#0a4a37);color:#fff;border-radius:28px;padding:56px;display:flex;justify-content:space-between;align-items:center;gap:30px;flex-wrap:wrap;position:relative;overflow:hidden}
.band::after{content:"";position:absolute;right:-80px;top:-80px;width:280px;height:280px;border-radius:50%;border:40px solid rgba(255,255,255,.06)}
.band h2{font-size:clamp(1.7rem,3vw,2.4rem);max-width:560px}
.band p{opacity:.8;margin-top:8px}
.band .btn{background:#fff;color:#0a4a37;position:relative;z-index:1}

footer{padding:40px 0;border-top:1px solid var(--line);margin-top:84px;color:var(--muted);font-size:.88rem}
footer .wrap{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}

.reveal{opacity:0;transform:translateY(18px);transition:opacity .7s ease,transform .7s ease}
.reveal.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none}}

@media (max-width:900px){
  .hero{grid-template-columns:1fr;padding-top:44px}
  .grid,.steps{grid-template-columns:1fr 1fr}
  .links{display:none}
}
@media (max-width:600px){
  .wrap{padding:0 16px}
  .grid,.steps{grid-template-columns:1fr}
  .band{padding:36px 24px}
  .float{left:12px;bottom:-26px}
  section{padding:64px 0}
}
</style>
</head>
<body>
<header>
  <div class="wrap"><nav>
    <a class="logo" href="#"><i>M</i>MySantriQu</a>
    <div class="links"><a href="#fitur">Fitur</a><a href="#cara">Cara Kerja</a><a href="laporan.html">Laporan</a></div>
    <a class="btn btn-primary" href="dashboard.html">Masuk</a>
  </nav></div>
</header>

<main class="wrap">
  <div class="hero">
    <div class="reveal">
      <span class="eyebrow">☾ Sistem Informasi Pesantren</span>
      <h1>Pantau santri dengan <em>tenang</em>, setiap hari.</h1>
      <p>Absensi, kegiatan, catatan pelanggaran, dan laporan perkembangan santri — rapi dalam satu tempat, bisa dibuka dari HP ustadz maupun komputer kantor.</p>
      <div class="cta">
        <a class="btn btn-primary" href="dashboard.html">Buka Dashboard →</a>
        <a class="btn btn-ghost" href="#fitur">Lihat Fitur</a>
      </div>
      <div class="trust">
        <div><b>1 klik</b><span>input absensi harian</span></div>
        <div><b>Real‑time</b><span>rekap per kelas</span></div>
        <div><b>PDF</b><span>laporan wali santri</span></div>
      </div>
    </div>

    <div class="mock reveal" aria-hidden="true">
      <div class="mock-top"><h3>Kehadiran Pekan Ini</h3><span class="chip">● Live</span></div>
      <div class="kpis">
        <div class="kpi"><small>Hadir</small><b>96%</b></div>
        <div class="kpi"><small>Izin</small><b>12</b></div>
        <div class="kpi"><small>Kegiatan</small><b>8</b></div>
      </div>
      <div class="bars"><div style="height:70%"></div><div style="height:82%"></div><div class="on" style="height:94%"></div><div style="height:88%"></div><div style="height:76%"></div><div style="height:91%"></div><div style="height:60%"></div></div>
      <div class="days"><span>Sab</span><span>Ahd</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span></div>
      <div class="list">
        <div class="row"><span class="av" style="background:#14936b">AF</span>Ahmad Fauzi · Kelas 2B<span class="st ok">Hadir</span></div>
        <div class="row"><span class="av" style="background:#c8963e">MR</span>M. Rizki · Kelas 3A<span class="st warn">Izin</span></div>
      </div>
      <div class="float"><i>✓</i><div><b>Laporan bulanan</b><br><span style="color:var(--muted)">siap diunduh</span></div></div>
    </div>
  </div>
</main>

<section id="fitur">
  <div class="wrap">
    <div class="head reveal">
      <span class="label">Fitur</span>
      <h2>Semua kebutuhan pengasuhan, tanpa tumpukan kertas.</h2>
      <p>Dirancang bersama pengurus pondok supaya mudah dipakai siapa saja.</p>
    </div>
    <div class="grid">
      <a class="card reveal" href="absensi.html"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg></div><h3>Absensi</h3><p>Catat kehadiran halaqah, sekolah, dan sholat berjamaah dalam hitungan detik.</p><span class="go">Buka absensi →</span></a>
      <a class="card reveal" href="kegiatan.html"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg></div><h3>Kegiatan</h3><p>Jadwal dan dokumentasi kegiatan harian, mingguan, hingga acara besar pondok.</p><span class="go">Lihat kegiatan →</span></a>
      <a class="card reveal" href="pelanggaran.html"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg></div><h3>Pelanggaran</h3><p>Catatan kedisiplinan yang adil dan terdokumentasi, lengkap dengan poin.</p><span class="go">Catat pelanggaran →</span></a>
      <a class="card reveal" href="santri.html"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0112 0v1M16 3.1a4 4 0 010 7.8M22 21v-1a6 6 0 00-4-5.6"/></svg></div><h3>Data Santri</h3><p>Profil, kamar, kelas, dan kontak wali — tersimpan aman dan mudah dicari.</p><span class="go">Daftar santri →</span></a>
      <a class="card reveal" href="laporan.html"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg></div><h3>Laporan</h3><p>Rekap otomatis per santri, kelas, atau bulan untuk musyawarah dan wali.</p><span class="go">Buat laporan →</span></a>
      <a class="card reveal" href="admin-users.html"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg></div><h3>Hak Akses</h3><p>Atur peran admin, ustadz, dan musyrif sehingga tiap orang melihat yang perlu saja.</p><span class="go">Kelola pengguna →</span></a>
    </div>
  </div>
</section>

<section id="cara" style="padding-top:20px">
  <div class="wrap">
    <div class="head reveal"><span class="label">Cara Kerja</span><h2>Mulai dalam tiga langkah.</h2></div>
    <div class="steps">
      <div class="step reveal"><h3>Masukkan data santri</h3><p>Impor daftar santri per kelas dan kamar, sekali saja.</p></div>
      <div class="step reveal"><h3>Catat setiap hari</h3><p>Ustadz mengisi absensi & kegiatan langsung dari HP.</p></div>
      <div class="step reveal"><h3>Pantau & laporkan</h3><p>Dashboard dan laporan terbentuk otomatis, siap dibagikan.</p></div>
    </div>
  </div>
</section>

<div class="wrap">
  <div class="band reveal">
    <div><h2>Siap merapikan administrasi pondok?</h2><p>Masuk ke dashboard dan mulai input absensi hari ini.</p></div>
    <a class="btn" href="dashboard.html">Masuk Sekarang →</a>
  </div>
</div>

<footer><div class="wrap"><span>© <span id="y"></span> MySantriQu</span><span>Dibuat dengan ♥ untuk pesantren</span></div></footer>

<script>
document.getElementById('y').textContent = new Date().getFullYear();
const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
document.querySelectorAll('.reveal').forEach((el, i) => { el.style.transitionDelay = (i % 3) * 80 + 'ms'; io.observe(el); });
</script>
</body>
</html>
