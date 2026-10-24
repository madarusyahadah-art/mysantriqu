(function () {
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

  applyTheme(theme);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      mountThemeToggle();
      mountAppInstall();
    }, { once: true });
  } else {
    mountThemeToggle();
    mountAppInstall();
  }
})();
