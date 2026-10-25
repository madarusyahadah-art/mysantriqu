(function () {
  const storageTheme = "mysantriqu_theme";
  const storageBg = "mysantriqu_bg_color";
  const storageElement = "mysantriqu_element_color";

  function hexToRgb(hex) {
    const c = String(hex || "#2dd36f").replace("#", "");
    const n = parseInt(c.length === 3 ? c.split("").map(function (x) { return x + x; }).join("") : c, 16);
    return isNaN(n) ? { r: 45, g: 211, b: 111 } : { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
  }

  function rgbToHex(r, g, b) {
    return "#" + [r, g, b].map(function (x) { return Math.round(x).toString(16).padStart(2, "0"); }).join("");
  }

  function rgbToHsv(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h = 0, s = 0, v = max;
    const d = max - min;
    s = max === 0 ? 0 : d / max;
    if (max !== min) {
      switch (max) {
        case r: h = (g - b) / d + (g < b ? 6 : 0); break;
        case g: h = (b - r) / d + 2; break;
        case b: h = (r - g) / d + 4; break;
      }
      h /= 6;
    }
    return { h: h * 360, s: s * 100, v: v * 100 };
  }

  function hsvToRgb(h, s, v) {
    h /= 360; s /= 100; v /= 100;
    const i = Math.floor(h * 6), f = h * 6 - i;
    const p = v * (1 - s), q = v * (1 - f * s), t = v * (1 - (1 - f) * s);
    switch (i % 6) {
      case 0: return { r: v, g: t, b: p };
      case 1: return { r: q, g: v, b: p };
      case 2: return { r: p, g: v, b: t };
      case 3: return { r: t, g: p, b: v };
      case 4: return { r: v, g: p, b: t };
      case 5: return { r: p, g: t, b: v };
    }
  }

  function applyTheme(nextTheme) {
    try { localStorage.setItem(storageTheme, nextTheme); } catch (e) {}
    document.documentElement.dataset.theme = nextTheme;
    const btn = document.getElementById("theme-toggle");
    if (btn) {
      const isLight = nextTheme === "light";
      btn.innerHTML = isLight ? '<ion-icon name="moon-outline" aria-hidden="true"></ion-icon> Tema gelap' : '<ion-icon name="sunny-outline" aria-hidden="true"></ion-icon> Tema terang';
    }
    if (nextTheme !== "custom") {
      document.documentElement.style.removeProperty("--msq-bg-custom");
      document.documentElement.style.removeProperty("--msq-element-custom");
      try { localStorage.removeItem(storageBg); localStorage.removeItem(storageElement); } catch (e) {}
    }
    syncThemeUI(nextTheme);
  }

  function syncThemeUI(themeName) {
    document.querySelectorAll(".msq-theme-choice").forEach(function (b) {
      b.classList.toggle("active", b.dataset.theme === themeName);
    });
    const customSection = document.getElementById("msq-theme-custom-section");
    if (customSection) customSection.hidden = themeName !== "custom";
  }

  function applyCustomColor(type, color) {
    if (!color) return;
    const root = document.documentElement;
    const prop = type === "bg" ? "--msq-bg-custom" : "--msq-element-custom";
    root.style.setProperty(prop, color);
    try { localStorage.setItem(type === "bg" ? storageBg : storageElement, color); } catch (e) {}
    const input = document.getElementById("msq-hex-" + type);
    if (input) input.value = color;
    updateThemeOverride();
  }

  function updateThemeOverride() {
    let style = document.getElementById("msq-theme-override");
    if (!style) { style = document.createElement("style"); style.id = "msq-theme-override"; document.head.appendChild(style); }
    const bg = document.documentElement.style.getPropertyValue("--msq-bg-custom") || "";
    const el = document.documentElement.style.getPropertyValue("--msq-element-custom") || "";
    let css = "";
    if (bg) {
      const rgb = hexToRgb(bg);
      css += "body { background: radial-gradient(circle at top left, rgba(" + rgb.r + "," + rgb.g + "," + rgb.b + ",0.16), transparent 30%), radial-gradient(circle at bottom right, rgba(" + rgb.r + "," + rgb.g + "," + rgb.b + ",0.12), transparent 30%), url('assets/background.png') center/cover no-repeat fixed !important; }\n";
    }
    if (el) {
      css += ".card, .login-box, .modal-content, .role-card, .kegiatan-section, .search-results, .info-box, .navbar, .msq-settings-panel { border-color: " + el + " !important; }\n";
    }
    style.textContent = css;
  }

  function buildColorPicker(type, labelText, currentColor) {
    const hsv = rgbToHsv(...Object.values(hexToRgb(currentColor)));
    const group = document.createElement("div");
    group.className = "msq-color-picker-group";
    group.innerHTML =
      '<label>' + labelText + '</label>' +
      '<div class="msq-sv-box" id="msq-sv-' + type + '" style="background:linear-gradient(to bottom,hsl(' + hsv.h + ',100%,50%),black),linear-gradient(to right,white,transparent)">' +
      '  <div class="msq-sv-overlay"></div>' +
      '  <div class="msq-sv-handle" id="msq-handle-' + type + '" style="left:' + hsv.s + '%;top:' + (100 - hsv.v) + '%"></div>' +
      '</div>' +
      '<input type="range" id="msq-hue-' + type + '" min="0" max="360" value="' + hsv.h + '">' +
      '<input type="text" id="msq-hex-' + type + '" maxlength="7" value="' + currentColor + '">';
    return { el: group, h: hsv.h, s: hsv.s, v: hsv.v, type: type };
  }

  function initColorPicker(picker) {
    const sv = document.getElementById("msq-sv-" + picker.type);
    const handle = document.getElementById("msq-handle-" + picker.type);
    const hue = document.getElementById("msq-hue-" + picker.type);
    const hex = document.getElementById("msq-hex-" + picker.type);
    let dragging = false;

    function updateFromSV(x, y) {
      const rect = sv.getBoundingClientRect();
      let sx = (x - rect.left) / rect.width;
      let sy = (y - rect.top) / rect.height;
      sx = Math.max(0, Math.min(1, sx)); sy = Math.max(0, Math.min(1, sy));
      picker.s = sx * 100; picker.v = (1 - sy) * 100;
      applyPicker(picker);
    }

    function applyPicker(p) {
      const rgb = hsvToRgb(p.h, p.s, p.v);
      const color = rgbToHex(rgb.r, rgb.g, rgb.b);
      sv.style.background = "linear-gradient(to bottom,hsl(" + p.h + ",100%,50%),black),linear-gradient(to right,white,transparent)";
      handle.style.left = p.s + "%"; handle.style.top = (100 - p.v) + "%";
      hex.value = color;
      applyCustomColor(p.type, color);
    }

    sv.addEventListener("mousedown", function (e) { dragging = true; updateFromSV(e.clientX, e.clientY); });
    handle.addEventListener("mousedown", function (e) { e.stopPropagation(); dragging = true; });
    window.addEventListener("mousemove", function (e) { if (dragging) updateFromSV(e.clientX, e.clientY); });
    window.addEventListener("mouseup", function () { dragging = false; });
    hue.addEventListener("input", function () { picker.h = parseInt(this.value) || 0; applyPicker(picker); });
    hex.addEventListener("change", function () {
      const v = this.value;
      if (/^#[0-9A-Fa-f]{6}$/.test(v)) {
        const hsv2 = rgbToHsv(...Object.values(hexToRgb(v)));
        picker.h = hsv2.h; picker.s = hsv2.s; picker.v = hsv2.v; applyPicker(picker);
      }
    });
    applyPicker(picker);
  }

  function injectStyles() {
    if (document.getElementById("msq-settings-style")) return;
    const style = document.createElement("style");
    style.id = "msq-settings-style";
    style.textContent = [
      ".msq-settings-button { position: fixed; top: 0.75rem; right: 0.75rem; z-index: 1300; width: 42px; height: 42px; border-radius: 50%; border: 1px solid rgba(154, 230, 180, 0.35); background: rgba(22, 115, 68, 0.92); color: #effff3; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; cursor: pointer; box-shadow: 0 8px 24px rgba(5, 32, 17, 0.3); backdrop-filter: blur(10px); transition: transform 0.2s ease; }",
      ".msq-settings-button:hover { transform: rotate(30deg); background: rgba(45, 211, 111, 0.96); }",
      ".msq-settings-overlay { position: fixed; inset: 0; z-index: 1400; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }",
      ".msq-settings-overlay[hidden] { display: none; }",
      ".msq-settings-panel { width: min(92vw, 420px); max-height: 88vh; overflow-y: auto; background: linear-gradient(180deg, rgba(8,23,18,0.96), rgba(9,31,25,0.94)); border: 1px solid rgba(154,230,180,0.25); border-radius: 22px; padding: 1.5rem; color: #edfef0; box-shadow: 0 24px 60px rgba(4,18,13,0.5); position: relative; }",
      "html[data-theme=\"light\"] .msq-settings-panel { background: linear-gradient(180deg, rgba(255,255,255,0.97), rgba(231,248,237,0.97)); color: #153728; border-color: rgba(29,127,82,0.25); }",
      ".msq-close { position: absolute; top: 0.6rem; right: 0.9rem; background: none; border: none; color: inherit; font-size: 1.6rem; cursor: pointer; line-height: 1; }",
      ".msq-settings-panel h2 { margin-bottom: 1rem; font-size: 1.3rem; }",
      ".msq-settings-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.85rem; }",
      ".msq-settings-item { background: rgba(255,255,255,0.04); border: 1px solid rgba(154,230,180,0.18); border-radius: 14px; padding: 0.9rem; }",
      "html[data-theme=\"light\"] .msq-settings-item { background: rgba(29,127,82,0.05); border-color: rgba(29,127,82,0.18); }",
      ".msq-settings-item h3 { margin: 0 0 0.65rem; font-size: 0.95rem; color: var(--msq-accent, #2dd36f); }",
      ".msq-profile { display: flex; align-items: center; gap: 0.9rem; }",
      ".msq-profile img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(154,230,180,0.3); }",
      ".msq-profile-meta { display: flex; flex-direction: column; gap: 0.25rem; }",
      ".msq-profile-name { font-weight: 700; }",
      ".msq-profile-email { font-size: 0.85rem; color: #cfeee0; }",
      "html[data-theme=\"light\"] .msq-profile-email { color: #456657; }",
      ".msq-role-badge { display: inline-block; padding: 0.4rem 0.8rem; border-radius: 999px; background: rgba(45,211,111,0.18); border: 1px solid rgba(154,230,180,0.3); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; align-self: flex-start; margin-top: 0.35rem; }",
      ".msq-theme-choices { display: grid; grid-template-columns: 1fr; gap: 0.6rem; }",
      ".msq-theme-choice { padding: 0.8rem; border-radius: 12px; border: 1px solid rgba(154,230,180,0.25); background: rgba(255,255,255,0.04); color: inherit; cursor: pointer; font-weight: 600; text-align: left; }",
      ".msq-theme-choice.active { background: var(--msq-accent, #2dd36f); color: #062816; border-color: transparent; }",
      ".msq-color-picker-group { margin-top: 0.75rem; }",
      ".msq-color-picker-group label { display: block; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--msq-accent, #2dd36f); }",
      ".msq-sv-box { width: 100%; height: 140px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); position: relative; cursor: crosshair; margin-bottom: 0.5rem; }",
      ".msq-sv-overlay { position: absolute; inset: 0; border-radius: 10px; pointer-events: none; }",
      ".msq-sv-handle { width: 20px; height: 20px; border: 2px solid #fff; border-radius: 50%; background: transparent; position: absolute; transform: translate(-50%,-50%); box-shadow: 0 0 0 1px rgba(0,0,0,0.2); pointer-events: none; }",
      "input[type=\"range\"] { width: 100%; accent-color: var(--msq-accent, #2dd36f); margin-bottom: 0.5rem; }",
      "input[type=\"text\"] { width: 100%; padding: 0.5rem; border-radius: 8px; border: 1px solid rgba(154,230,180,0.25); background: rgba(255,255,255,0.04); color: inherit; }",
      ".msq-back { background: none; border: none; color: inherit; font-weight: 600; cursor: pointer; margin-bottom: 1rem; text-align: left; padding: 0; font-size: 1rem; }",
      ".msq-logout { width: 100%; padding: 0.7rem; border: none; border-radius: 12px; background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; font-weight: 700; cursor: pointer; margin-top: 0.5rem; }",
      ".msq-logout:hover { transform: translateY(-2px); }"
    ].join("\n");
    document.head.appendChild(style);
  }

  function mountSettings() {
    if (document.getElementById("msq-settings-button")) return;
    injectStyles();

    const btn = document.createElement("button");
    btn.id = "msq-settings-button";
    btn.className = "msq-settings-button";
    btn.type = "button";
    btn.innerHTML = '<ion-icon name="settings-outline" aria-hidden="true"></ion-icon>';
    btn.setAttribute("aria-label", "Pengaturan");
    document.body.appendChild(btn);

    const overlay = document.createElement("div");
    overlay.id = "msq-settings-overlay";
    overlay.className = "msq-settings-overlay";
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="msq-settings-panel" role="dialog" aria-modal="true">' +
      '<button class="msq-close" id="msq-settings-close" aria-label="Tutup">&times;</button>' +
      '<div id="msq-settings-view-main"></div>' +
      '<div id="msq-settings-view-theme" hidden></div>' +
      '</div>';
    document.body.appendChild(overlay);

    const closeBtn = document.getElementById("msq-settings-close");
    const viewMain = document.getElementById("msq-settings-view-main");
    const viewTheme = document.getElementById("msq-settings-view-theme");

    function renderMain() {
      viewMain.innerHTML =
        '<h2>Pengaturan</h2>' +
        '<ul class="msq-settings-list">' +
        '<li class="msq-settings-item msq-profile-item">' +
        '<h3>Profil Akun</h3>' +
        '<div class="msq-profile">' +
        '<img id="msq-user-photo" src="assets/Logo.png" alt="Foto profil" />' +
        '<div class="msq-profile-meta">' +
        '<div class="msq-profile-name" id="msq-user-name">Memuat...</div>' +
        '<div class="msq-profile-email" id="msq-user-email">-</div>' +
        '<span class="msq-role-badge" id="msq-user-role">-</span>' +
        '</div>' +
        '</div>' +
        '</li>' +
        '<li class="msq-settings-item">' +
        '<h3>Tema Halaman</h3>' +
        '<button class="msq-theme-choice msq-theme-open" type="button">Buka Pengaturan Tema</button>' +
        '</li>' +
        '<li class="msq-settings-item">' +
        '<h3>Akun</h3>' +
        '<button class="msq-logout" id="msq-logout" type="button">Logout</button>' +
        '</li>' +
        '</ul>';
      viewMain.querySelector(".msq-theme-open").addEventListener("click", function () { renderTheme(); viewMain.hidden = true; viewTheme.hidden = false; });
    }

    function renderTheme() {
      viewTheme.innerHTML =
        '<button class="msq-back" id="msq-theme-back">&larr; Kembali</button>' +
        '<h2>Tema Halaman</h2>' +
        '<div class="msq-theme-choices">' +
        '<button class="msq-theme-choice" data-theme="light" type="button">Tema Terang</button>' +
        '<button class="msq-theme-choice" data-theme="dark" type="button">Tema Gelap</button>' +
        '<button class="msq-theme-choice" data-theme="custom" type="button">Custom Warna</button>' +
        '</div>' +
        '<div id="msq-theme-custom-section" hidden>' +
        '</div>';
      viewTheme.querySelector("#msq-theme-back").addEventListener("click", function () { viewTheme.hidden = true; viewMain.hidden = false; });
      viewTheme.querySelectorAll(".msq-theme-choice").forEach(function (b) {
        b.addEventListener("click", function () {
          applyTheme(b.dataset.theme);
          const section = document.getElementById("msq-theme-custom-section");
          if (b.dataset.theme === "custom") {
            section.hidden = false;
            initCustomPickers();
          } else {
            section.hidden = true;
          }
        });
      });
      try {
        const savedTheme = localStorage.getItem(storageTheme);
        if (savedTheme === "custom") {
          document.getElementById("msq-theme-custom-section").hidden = false;
          initCustomPickers();
        }
      } catch (e) {}
      syncThemeUI(localStorage.getItem(storageTheme) || "dark");
    }

    function initCustomPickers() {
      const section = document.getElementById("msq-theme-custom-section");
      if (section.dataset.initialized === "true") return;
      section.innerHTML = "";
      const bg = localStorage.getItem(storageBg) || "#061d12";
      const el = localStorage.getItem(storageElement) || "#2dd36f";
      const p1 = buildColorPicker("bg", "Warna Background", bg);
      const p2 = buildColorPicker("el", "Warna Element / Card", el);
      section.appendChild(p1.el);
      section.appendChild(p2.el);
      initColorPicker(p1);
      initColorPicker(p2);
      section.dataset.initialized = "true";
    }

    btn.addEventListener("click", function () {
      renderMain();
      overlay.hidden = false;
    });
    closeBtn.addEventListener("click", function () { overlay.hidden = true; });
    overlay.addEventListener("click", function (e) { if (e.target === overlay) overlay.hidden = true; });

    const logoutBtn = document.getElementById("msq-logout");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        if (window.logoutUser) window.logoutUser();
        else { try { localStorage.removeItem("user_data"); localStorage.removeItem("user_id"); localStorage.removeItem("user_role"); } catch (e) {} window.location.href = "index.html"; }
      });
    }

    fetch("api/session.php", { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success && data.user) {
          const u = data.user;
          const nameEl = document.getElementById("msq-user-name");
          const emailEl = document.getElementById("msq-user-email");
          const photoEl = document.getElementById("msq-user-photo");
          const roleEl = document.getElementById("msq-user-role");
          if (nameEl) nameEl.textContent = u.name || "-";
          if (emailEl) emailEl.textContent = u.email || "-";
          if (photoEl) photoEl.src = u.photo || "assets/Logo.png";
          if (roleEl) roleEl.textContent = u.role || "-";
        }
      }).catch(function () {});
  }

  function removeHeaders() {
    const btnLogout = document.getElementById("btn-logout");
    if (btnLogout && btnLogout.closest("nav.navbar")) {
      document.body.appendChild(btnLogout);
      btnLogout.style.position = "fixed";
      btnLogout.style.bottom = "1rem";
      btnLogout.style.right = "1rem";
      btnLogout.style.zIndex = "2000";
      btnLogout.style.display = "inline-block";
    }
    document.querySelectorAll("nav.navbar").forEach(function (n) { n.remove(); });
    if (!document.getElementById("btn-logout")) {
      const hiddenLogout = document.createElement("button");
      hiddenLogout.id = "btn-logout";
      hiddenLogout.type = "button";
      hiddenLogout.style.display = "none";
      document.body.appendChild(hiddenLogout);
    }
  }

  function init() {
    injectStyles();
    if (!document.body) return;
    if (/index\.html|login\.html/i.test(window.location.pathname)) return;
    removeHeaders();
    mountSettings();
    try {
      const savedTheme = localStorage.getItem(storageTheme);
      if (savedTheme) applyTheme(savedTheme);
      const savedBg = localStorage.getItem(storageBg);
      const savedEl = localStorage.getItem(storageElement);
      if (savedBg) document.documentElement.style.setProperty("--msq-bg-custom", savedBg);
      if (savedEl) document.documentElement.style.setProperty("--msq-element-custom", savedEl);
      if (savedBg || savedEl) updateThemeOverride();
    } catch (e) {
      console.error("Gagal memuat preferensi tema:", e);
    }
    if (window.msqHideLoading) window.msqHideLoading();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();

﻿
