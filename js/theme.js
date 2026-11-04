(function () {
  // --- Loading overlay (muncul sampai halaman siap) ---
  function mountLoadingOverlay() {
    if (document.body && document.body.dataset.dashboardReady === "true") return;
    if (document.getElementById("msq-loading-overlay")) return;
    const style = document.createElement("style");
    style.id = "msq-loading-style";
    style.textContent = [
      "#msq-loading-overlay { position: fixed; inset: 0; z-index: 9999; background: linear-gradient(135deg, #061d12, #0a2b1a); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; transition: opacity 0.35s ease; }",
      "html[data-theme=\"light\"] #msq-loading-overlay { background: linear-gradient(135deg, #e7f8ed, #f4fff7); }",
      "#msq-loading-overlay.msq-loading-hidden { opacity: 0; pointer-events: none; }",
      ".msq-loading-spinner { width: 48px; height: 48px; border: 4px solid rgba(154,230,180,0.25); border-top-color: #2dd36f; border-radius: 50%; animation: msq-spin 0.8s linear infinite; }",
      "@keyframes msq-spin { to { transform: rotate(360deg); } }",
      ".msq-loading-text { color: #effff3; font-weight: 600; font-size: 0.95rem; letter-spacing: 0.05em; }",
      "html[data-theme=\"light\"] .msq-loading-text { color: #153728; }",
    ].join("\n");
    document.head.appendChild(style);

    const overlay = document.createElement("div");
    overlay.id = "msq-loading-overlay";
    overlay.innerHTML = '<div class="msq-loading-spinner"></div><div class="msq-loading-text">Memuat halaman...</div>';
    document.body.appendChild(overlay);
    document.documentElement.classList.add("msq-loading-active");
  }

  function hideLoadingOverlay(force) {
    if (document.body &&
        document.body.dataset.loadingMode === "dashboard" &&
        document.body.dataset.dashboardReady !== "true" &&
        force !== true) return;
    const overlay = document.getElementById("msq-loading-overlay");
    if (!overlay || overlay.classList.contains("msq-loading-hidden")) return;
    overlay.classList.add("msq-loading-hidden");
    document.documentElement.classList.remove("msq-loading-active");
    setTimeout(function () {
      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
      const st = document.getElementById("msq-loading-style");
      if (st && st.parentNode) st.parentNode.removeChild(st);
    }, 400);
  }

  window.msqHideLoading = hideLoadingOverlay;

  // Tampilkan segera
  if (document.body) mountLoadingOverlay();
  else document.addEventListener("DOMContentLoaded", mountLoadingOverlay, { once: true });

  const storageKey = "mysantriqu_theme";
  let theme = "dark";
  try {
    const saved = localStorage.getItem(storageKey);
    if (saved === "light" || saved === "dark") theme = saved;
  } catch (error) {
    console.error("Preferensi tema tidak dapat dibaca:", error);
  }

  function applyTheme(nextTheme) {
    theme = nextTheme;
    document.documentElement.dataset.theme = theme;
    const button = document.getElementById("theme-toggle");
    if (button) {
      const isLight = theme === "light";
      button.innerHTML = isLight
        ? '<ion-icon name="moon-outline" aria-hidden="true"></ion-icon> Tema gelap'
        : '<ion-icon name="sunny-outline" aria-hidden="true"></ion-icon> Tema terang';
      button.setAttribute("aria-label", isLight ? "Aktifkan tema gelap" : "Aktifkan tema terang");
      button.setAttribute("aria-pressed", String(isLight));
    }
  }

  function mountThemeToggle() {
    if (document.getElementById("theme-toggle")) return;
    const button = document.createElement("button");
    button.type = "button";
    button.id = "theme-toggle";
    button.className = "theme-toggle";
    button.addEventListener("click", function () {
      const nextTheme = theme === "dark" ? "light" : "dark";
      try {
        localStorage.setItem(storageKey, nextTheme);
      } catch (error) {
        console.error("Preferensi tema tidak dapat disimpan:", error);
      }
      applyTheme(nextTheme);
    });
    document.body.appendChild(button);
    applyTheme(theme);
  }

  function mountAppInstall() {
    if (!("serviceWorker" in navigator)) return;
    navigator.serviceWorker.register("./sw.js").catch(function (error) {
      console.error("Service worker tidak dapat didaftarkan:", error);
    });

    let installPrompt = null;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "pwa-install-button";
    button.textContent = "Pasang aplikasi";
    button.setAttribute("aria-label", "Pasang MySantriQu di perangkat ini");
    Object.assign(button.style, {
      position: "fixed",
      right: "1rem",
      bottom: "4.75rem",
      zIndex: "1200",
      minHeight: "44px",
      padding: "0.65rem 0.9rem",
      border: "1px solid rgba(154, 230, 180, 0.48)",
      borderRadius: "999px",
      color: "#effff3",
      background: "rgba(22, 115, 68, 0.94)",
      boxShadow: "0 10px 30px rgba(5, 32, 17, 0.24)",
      fontWeight: "700",
      cursor: "pointer",
      display: "none",
    });
    button.addEventListener("click", async function () {
      if (!installPrompt) return;
      installPrompt.prompt();
      await installPrompt.userChoice;
      installPrompt = null;
      button.style.display = "none";
    });
    document.body.appendChild(button);

    window.addEventListener("beforeinstallprompt", function (event) {
      event.preventDefault();
      installPrompt = event;
      button.style.display = "inline-flex";
    });
    window.addEventListener("appinstalled", function () {
      installPrompt = null;
      button.style.display = "none";
    });
  }

  function mountMobileNavigation(user) {
    if (!user || !user.role || document.getElementById("msq-mobile-nav")) return;
    if (/index\.html|login\.html/i.test(window.location.pathname)) return;

    const role = String(user.role).toLowerCase();
    const isSupervisor = ["musyrif", "mas'ul", "supervisor"].includes(role);
    let items = [{ label: "Dashboard", href: "dashboard.html", icon: "home-outline" }];

    if (role === "admin") {
      items = items.concat([
        { label: "Absensi", href: "absensi-input.html", icon: "clipboard-outline" },
        { label: "Pelanggaran", href: "pelanggaran.html", icon: "alert-circle-outline" },
        { label: "Laporan", href: "laporan.html", icon: "stats-chart-outline" },
        { label: "Data Santri", href: "santri.html", icon: "people-outline" },
      ]);
    } else if (isSupervisor) {
      items = items.concat([
        { label: "Absensi", href: "absensi-input.html", icon: "clipboard-outline" },
        { label: "Pelanggaran", href: "pelanggaran.html", icon: "alert-circle-outline" },
        { label: "Laporan", href: "laporan.html", icon: "stats-chart-outline" },
      ]);
    } else if (role === "kajur") {
      items = items.concat([
        { label: "Laporan", href: "laporan.html", icon: "stats-chart-outline" },
        { label: "Kalender", href: "kalender-akademik.html", icon: "calendar-outline" },
      ]);
    } else if (role === "mahasantri") {
      items = items.concat([
        { label: "Laporan", href: "laporan.html", icon: "stats-chart-outline" },
      ]);
      if (user.santri_id) {
        items.push({ label: "Profil", href: "profil-santri.html", icon: "person-circle-outline" });
      }
    } else {
      return;
    }

    if (!document.getElementById("msq-mobile-nav-style")) {
      const style = document.createElement("style");
      style.id = "msq-mobile-nav-style";
      style.textContent = [
        "#msq-mobile-nav { display: none; }",
        "@media (max-width: 760px) {",
        "  body.msq-has-mobile-nav { padding-bottom: calc(76px + env(safe-area-inset-bottom)); }",
        "  #msq-mobile-nav { position: fixed; inset: auto 0 0; z-index: 1100; display: grid; grid-template-columns: repeat(var(--msq-nav-count), minmax(0, 1fr)); padding: .35rem .45rem calc(.35rem + env(safe-area-inset-bottom)); border-top: 1px solid rgba(154,230,180,.2); background: rgba(6,29,18,.96); box-shadow: 0 -8px 26px rgba(0,0,0,.22); backdrop-filter: blur(16px); }",
        "  #msq-mobile-nav a { display: flex; min-width: 0; min-height: 52px; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; border-radius: 12px; color: #cfe8d8; font: 600 10px/1.2 'Segoe UI', sans-serif; text-align: center; text-decoration: none; -webkit-tap-highlight-color: transparent; }",
        "  #msq-mobile-nav a ion-icon { font-size: 1.35rem; }",
        "  #msq-mobile-nav a[aria-current='page'] { color: #9ae6b4; background: rgba(45,211,111,.14); }",
        "  #msq-mobile-nav a:focus-visible { outline: 2px solid #9ae6b4; outline-offset: -2px; }",
        "  .theme-toggle { display: none !important; }",
        "  .pwa-install-button { bottom: calc(5.5rem + env(safe-area-inset-bottom)) !important; }",
        "}",
      ].join("\n");
      document.head.appendChild(style);
    }

    const nav = document.createElement("nav");
    nav.id = "msq-mobile-nav";
    nav.setAttribute("aria-label", "Navigasi utama");
    nav.style.setProperty("--msq-nav-count", String(items.length));
    const currentPage = window.location.pathname.split("/").pop() || "index.html";

    items.forEach(function (item) {
      const link = document.createElement("a");
      link.href = item.href;
      if (currentPage === item.href) link.setAttribute("aria-current", "page");

      const icon = document.createElement("ion-icon");
      icon.setAttribute("name", item.icon);
      icon.setAttribute("aria-hidden", "true");

      const label = document.createElement("span");
      label.textContent = item.label;
      link.appendChild(icon);
      link.appendChild(label);
      nav.appendChild(link);
    });

    document.body.classList.add("msq-has-mobile-nav");
    document.body.appendChild(nav);
  }

  window.msqApplyTheme = applyTheme;
  window.msqGetTheme = function () { return theme; };
  window.addEventListener("verified-session", function (event) {
    mountMobileNavigation(event.detail);
  });
  if (window.currentSessionUser) mountMobileNavigation(window.currentSessionUser);

  applyTheme(theme);
  setTimeout(function () { hideLoadingOverlay(true); }, 6000);
  const isLoginPage = /index\.html|login\.html/i.test(window.location.pathname);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      mountThemeToggle();
      mountAppInstall();
      if (isLoginPage) {
        setTimeout(function () { if (window.msqHideLoading) window.msqHideLoading(); }, 300);
      } else {
        const settingsScript = document.createElement("script");
        settingsScript.src = "js/settings.js?v=20261102-2";
        document.head.appendChild(settingsScript);
      }
    }, { once: true });
  } else {
    mountAppInstall();
    if (isLoginPage) {
      setTimeout(function () { if (window.msqHideLoading) window.msqHideLoading(); }, 300);
    } else {
      const settingsScript = document.createElement("script");
      settingsScript.src = "js/settings.js?v=20261102-2";
      document.head.appendChild(settingsScript);
    }
  }
})();
