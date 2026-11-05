"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const staffRoles = ["admin", "musyrif", "supervisor", "mas'ul", "kajur"];
  const requestedId = new URLSearchParams(window.location.search).get("santri_id");

  function showMessage(text, kind) {
    const message = document.getElementById("profile-message");
    message.textContent = text;
    message.dataset.state = kind || "error";
    message.hidden = !text;
  }

  function formatNumber(value) {
    return new Intl.NumberFormat("id-ID").format(Number(value) || 0);
  }

  function initials(name) {
    return String(name || "").trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase() || "?";
  }

  async function requestJson(url) {
    const response = await fetch(url, { credentials: "same-origin" });
    let data;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error("Respons server tidak dapat dibaca. Coba muat ulang.");
    }
    if (response.status === 401) {
      window.location.replace("index.html");
      throw new Error("Sesi login berakhir.");
    }
    if (!response.ok || !data.success) {
      throw new Error(data.error || "Profil santri tidak dapat dimuat.");
    }
    return data;
  }

  function formatMonth(value) {
    const date = new Date(String(value || "") + "-01T12:00:00");
    if (Number.isNaN(date.getTime())) return String(value || "—");
    return new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(date);
  }

  function renderHistory(rows) {
    const body = document.getElementById("profile-history");
    body.replaceChildren();
    if (!Array.isArray(rows) || !rows.length) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.className = "profile-table-state";
      cell.colSpan = 4;
      cell.textContent = "Belum ada riwayat rekap Excel untuk santri ini.";
      row.appendChild(cell);
      body.appendChild(row);
      return;
    }
    rows.forEach(function (item) {
      const row = document.createElement("tr");
      [formatMonth(item.bulan), item.activity_name, formatNumber(item.total), item.tanggal || "—"]
        .forEach(function (value) {
          const cell = document.createElement("td");
          cell.textContent = String(value);
          row.appendChild(cell);
        });
      body.appendChild(row);
    });
  }

  function renderProfile(data) {
    const student = data.santri || {};
    const summary = data.ringkasan || {};
    const name = String(student.nama || "Santri");
    document.getElementById("profile-card").hidden = false;
    document.getElementById("profile-avatar").textContent = initials(name);
    document.getElementById("profile-name").textContent = name;
    document.getElementById("profile-id").textContent = "ID Santri " + String(student.id || "—");
    document.getElementById("profile-ktm").textContent = student.no_ktm || "Belum diisi";
    document.getElementById("profile-origin").textContent = student.asal || "Belum diisi";
    document.getElementById("profile-cohort").textContent = student.angkatan || "Belum diisi";
    document.getElementById("profile-semester").textContent = student.semester_aktif
      ? "Semester " + student.semester_aktif
      : "Belum diisi";
    document.getElementById("profile-total").textContent = formatNumber(summary.total);
    document.getElementById("profile-months").textContent = formatNumber(summary.bulan);
    document.getElementById("profile-activities").textContent = formatNumber(summary.jenis_aktivitas);
    renderHistory(data.riwayat);
    if (!Number(summary.total)) {
      document.getElementById("profile-import-note").textContent =
        "Belum ada rekap aktivitas yang diimpor untuk profil ini. Admin dapat mengimpor file melalui halaman Data Santri.";
    }
  }

  function setSessionUser(user) {
    const name = user.name || user.email || "Pengguna";
    const role = String(user.role || "").toLowerCase();
    const avatar = initials(name);
    document.getElementById("sidebar-name").textContent = name;
    document.getElementById("sidebar-role").textContent = user.role || "Pengguna";
    document.getElementById("topbar-name").textContent = name;
    document.getElementById("sidebar-avatar").textContent = avatar;
    document.getElementById("top-avatar").textContent = avatar;
    if (role === "admin") {
      document.querySelectorAll(".admin-nav").forEach(function (item) { item.hidden = false; });
    }
    const isStaff = staffRoles.includes(role);
    const backLink = document.getElementById("profile-back-link");
    backLink.href = role === "admin" ? "santri.html" : (isStaff ? "laporan.html" : "dashboard.html");
    backLink.textContent = role === "admin" ? "Kembali ke Data Santri"
      : (isStaff ? "Kembali ke Laporan" : "Kembali ke Dashboard");
  }

  function setTableState(text) {
    const body = document.getElementById("profile-history");
    body.replaceChildren();
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.className = "profile-table-state";
    cell.colSpan = 4;
    cell.textContent = text;
    row.appendChild(cell);
    body.appendChild(row);
  }

  document.getElementById("menu-button").addEventListener("click", function () {
    document.getElementById("sidebar").classList.add("is-open");
    document.getElementById("sidebar-scrim").classList.add("is-visible");
    this.setAttribute("aria-expanded", "true");
  });
  function closeMenu() {
    document.getElementById("sidebar").classList.remove("is-open");
    document.getElementById("sidebar-scrim").classList.remove("is-visible");
    document.getElementById("menu-button").setAttribute("aria-expanded", "false");
  }
  document.getElementById("sidebar-scrim").addEventListener("click", closeMenu);
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeMenu();
  });
  document.getElementById("logout-button").addEventListener("click", async function () {
    this.disabled = true;
    try {
      const response = await fetch("api/logout.php", { method: "POST", credentials: "same-origin" });
      if (!response.ok) throw new Error("Sesi belum dapat diakhiri.");
      window.location.replace("index.html");
    } catch (error) {
      showMessage(error.message || "Gagal keluar. Silakan coba lagi.", "error");
      this.disabled = false;
    }
  });
  document.getElementById("current-year").textContent = String(new Date().getFullYear());
  document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
    weekday: "long", day: "numeric", month: "long",
  }).format(new Date());
  setTableState("Memuat riwayat aktivitas...");

  requestJson("api/session.php")
    .then(function (session) {
      if (!session.user) {
        window.location.replace("index.html");
        throw new Error("Sesi login berakhir.");
      }
      setSessionUser(session.user);
      const params = new URLSearchParams();
      if (requestedId) params.set("santri_id", requestedId);
      const query = params.toString();
      return requestJson("api/student-activity-profile.php" + (query ? "?" + query : ""));
    })
    .then(renderProfile)
    .catch(function (error) {
      setTableState(error.message || "Profil santri gagal dimuat.");
      showMessage(error.message || "Profil santri gagal dimuat.", "error");
    });
});
