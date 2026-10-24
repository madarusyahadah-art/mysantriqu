(function () {
  // --- Loading overlay (muncul sampai halaman siap) ---
  function mountLoadingOverlay() {
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

  function hideLoadingOverlay() {
    const overlay = document.getElementById("msq-loading-overlay");
    if (!overlay) return;
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

  window.msqApplyTheme = applyTheme;
  window.msqGetTheme = function () { return theme; };

  applyTheme(theme);
  setTimeout(function () { if (window.msqHideLoading) window.msqHideLoading(); }, 6000);
  const isLoginPage = /index\.html|login\.html/i.test(window.location.pathname);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      mountThemeToggle();
      mountAppInstall();
      if (isLoginPage) {
        setTimeout(function () { if (window.msqHideLoading) window.msqHideLoading(); }, 300);
      } else {
        const settingsScript = document.createElement("script");
        settingsScript.src = "js/settings.js?v=20261023-10";
        document.head.appendChild(settingsScript);
      }
    }, { once: true });
  } else {
    mountAppInstall();
    if (isLoginPage) {
      setTimeout(function () { if (window.msqHideLoading) window.msqHideLoading(); }, 300);
    } else {
      const settingsScript = document.createElement("script");
      settingsScript.src = "js/settings.js?v=20261023-10";
      document.head.appendChild(settingsScript);
    }
  }
})();

