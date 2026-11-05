"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const message = document.getElementById("dashboard-message");
  const palette = ["#4b9568", "#79ae7b", "#b8c88a", "#d4ad67", "#7d9d9a", "#8d85ab"];

  function showMessage(text, state) {
    message.textContent = text;
    message.dataset.state = state || "error";
    message.hidden = !text;
  }

  function formatNumber(value) {
    return new Intl.NumberFormat("id-ID").format(Number(value) || 0);
  }

  function setText(id, value) {
    document.getElementById(id).textContent = String(value == null ? "" : value);
  }

  function initials(name) {
    return String(name || "").trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase() || "?";
  }

  function formatShortDate(value) {
    const date = new Date(value + "T12:00:00");
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short" }).format(date);
  }

  function showEmpty(id, shouldShow) {
    document.getElementById(id).hidden = !shouldShow;
  }

  function renderTrend(rows) {
    const data = Array.isArray(rows) ? rows : [];
    const values = data.map(function (row) { return Number(row.total) || 0; });
    const max = Math.max(1, ...values);
    const width = 700;
    const top = 14;
    const bottom = 181;
    const step = data.length > 1 ? width / (data.length - 1) : width;
    const points = values.map(function (value, index) {
      return {
        x: data.length === 1 ? width / 2 : index * step,
        y: bottom - (value / max) * (bottom - top),
        value: value,
        date: data[index].date,
      };
    });

    document.getElementById("trend-line").setAttribute("d", points.map(function (point, index) {
      return (index === 0 ? "M" : "L") + point.x.toFixed(2) + " " + point.y.toFixed(2);
    }).join(" "));
    document.getElementById("trend-area").setAttribute("d", points.length
      ? "M" + points[0].x.toFixed(2) + " " + bottom + " " +
        points.map(function (point) { return "L" + point.x.toFixed(2) + " " + point.y.toFixed(2); }).join(" ") +
        " L" + points[points.length - 1].x.toFixed(2) + " " + bottom + " Z"
      : "");

    const pointGroup = document.getElementById("trend-points");
    pointGroup.replaceChildren();
    points.forEach(function (point, index) {
      if (index % 5 !== 0 && index !== points.length - 1) return;
      const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      circle.setAttribute("class", "chart-point");
      circle.setAttribute("cx", point.x.toFixed(2));
      circle.setAttribute("cy", point.y.toFixed(2));
      circle.setAttribute("r", "3.5");
      const title = document.createElementNS("http://www.w3.org/2000/svg", "title");
      title.textContent = formatShortDate(point.date) + ": " + formatNumber(point.value) + " pelanggaran";
      circle.appendChild(title);
      pointGroup.appendChild(circle);
    });

    const labels = document.getElementById("trend-labels");
    labels.replaceChildren();
    [data[0], data[Math.floor((data.length - 1) / 2)], data[data.length - 1]].filter(Boolean).forEach(function (row) {
      const label = document.createElement("span");
      label.textContent = formatShortDate(row.date);
      labels.appendChild(label);
    });
    document.querySelector(".chart-y-labels span").textContent = formatNumber(max);
    document.querySelectorAll(".chart-y-labels span")[1].textContent = formatNumber(Math.ceil(max / 2));
    showEmpty("trend-empty", values.every(function (value) { return value === 0; }));
  }

  function renderCategories(rows) {
    const data = Array.isArray(rows) ? rows : [];
    const total = data.reduce(function (sum, row) { return sum + (Number(row.total) || 0); }, 0);
    setText("category-total", formatNumber(total));
    const legend = document.getElementById("category-legend");
    legend.replaceChildren();
    let cursor = 0;
    const segments = data.map(function (row, index) {
      const value = Number(row.total) || 0;
      const start = total ? (cursor / total) * 360 : 0;
      cursor += value;
      const end = total ? (cursor / total) * 360 : 0;
      return palette[index % palette.length] + " " + start.toFixed(2) + "deg " + end.toFixed(2) + "deg";
    });
    document.getElementById("category-chart").style.background = total
      ? "conic-gradient(" + segments.join(", ") + ")"
      : "conic-gradient(#e9efea 0deg 360deg)";

    data.forEach(function (row, index) {
      const item = document.createElement("li");
      const swatch = document.createElement("span");
      swatch.className = "legend-swatch";
      swatch.style.backgroundColor = palette[index % palette.length];
      const name = document.createElement("span");
      name.className = "legend-name";
      name.textContent = String(row.name || "");
      const value = document.createElement("span");
      value.className = "legend-value";
      value.textContent = formatNumber(row.total);
      item.append(swatch, name, value);
      legend.appendChild(item);
    });
    showEmpty("category-empty", data.length === 0);
  }

  function renderStudents(rows) {
    const data = Array.isArray(rows) ? rows : [];
    const container = document.getElementById("top-students");
    container.replaceChildren();
    data.forEach(function (row, index) {
      const item = document.createElement("div");
      item.className = "rank-row";
      const number = document.createElement("span");
      number.className = "rank-number";
      number.textContent = String(index + 1).padStart(2, "0");
      const name = document.createElement("span");
      name.className = "rank-name";
      name.textContent = String(row.name || "");
      const count = document.createElement("span");
      count.className = "rank-count";
      count.textContent = formatNumber(row.total) + " kasus";
      item.append(number, name, count);
      container.appendChild(item);
    });
    showEmpty("students-empty", data.length === 0);
  }

  function renderCohorts(rows) {
    const data = Array.isArray(rows) ? rows : [];
    const container = document.getElementById("cohort-list");
    container.replaceChildren();
    const max = Math.max(1, ...data.map(function (row) { return Number(row.total) || 0; }));
    data.forEach(function (row) {
      const item = document.createElement("div");
      item.className = "cohort-row";
      const name = document.createElement("span");
      name.className = "cohort-name";
      name.textContent = String(row.name || "");
      const track = document.createElement("span");
      track.className = "cohort-track";
      const fill = document.createElement("span");
      fill.className = "cohort-fill";
      fill.style.display = "block";
      fill.style.width = Math.max(3, ((Number(row.total) || 0) / max) * 100) + "%";
      track.appendChild(fill);
      const count = document.createElement("span");
      count.className = "cohort-count";
      count.textContent = formatNumber(row.total);
      item.append(name, track, count);
      container.appendChild(item);
    });
    showEmpty("cohort-empty", data.length === 0);
  }

  function renderRecent(rows) {
    const data = Array.isArray(rows) ? rows : [];
    const container = document.getElementById("recent-list");
    container.replaceChildren();
    data.forEach(function (row) {
      const item = document.createElement("div");
      item.className = "recent-row";
      const date = document.createElement("span");
      date.className = "recent-date";
      date.textContent = formatShortDate(row.date);
      const student = document.createElement("span");
      student.className = "recent-student";
      student.textContent = String(row.student || "");
      const type = document.createElement("span");
      type.className = "recent-type";
      type.textContent = String(row.type || "");
      const points = document.createElement("span");
      points.className = "point-badge";
      points.textContent = formatNumber(row.points) + " poin";
      item.append(date, student, type, points);
      container.appendChild(item);
    });
    showEmpty("recent-empty", data.length === 0);
  }

  function renderDashboard(data) {
    const stats = data.stats || {};
    setText("stat-total", formatNumber(stats.total));
    setText("stat-today", formatNumber(stats.today));
    setText("stat-week", formatNumber(stats.week));
    setText("stat-month", formatNumber(stats.month));
    renderTrend(data.trend);
    renderCategories(data.categories);
    renderStudents(data.top_students);
    renderCohorts(data.cohorts);
    renderRecent(data.recent);
  }

  function formatStudentDate(value, options) {
    const date = new Date(String(value) + "T12:00:00");
    if (Number.isNaN(date.getTime())) return String(value || "");
    return new Intl.DateTimeFormat("id-ID", options || {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    }).format(date);
  }

  function formatStudentTime(value) {
    return value ? String(value).slice(0, 5) : "";
  }

  function renderNextEvent(event) {
    const container = document.getElementById("student-next-event");
    container.replaceChildren();
    if (!event) {
      const empty = document.createElement("p");
      empty.className = "student-next-event-empty";
      empty.textContent = "Belum ada agenda terdekat yang diterbitkan.";
      container.appendChild(empty);
      return;
    }

    const title = document.createElement("h2");
    title.className = "student-next-event-title";
    title.textContent = String(event.nama_kegiatan || "Kegiatan");
    const detail = document.createElement("p");
    detail.className = "student-next-event-detail";
    const startDate = formatStudentDate(event.tanggal_mulai);
    const endDate = event.tanggal_selesai !== event.tanggal_mulai
      ? " – " + formatStudentDate(event.tanggal_selesai, { day: "numeric", month: "long", year: "numeric" })
      : "";
    const startTime = formatStudentTime(event.jam_mulai);
    const endTime = formatStudentTime(event.jam_selesai);
    const timeLabel = startTime
      ? " · " + startTime + (endTime ? "–" + endTime : "")
      : " · Waktu belum ditentukan";
    detail.textContent = startDate + endDate + timeLabel;

    const footer = document.createElement("div");
    footer.className = "student-next-event-footer";
    const category = document.createElement("span");
    category.className = "student-next-event-category";
    category.textContent = String(event.kategori || "Lainnya");
    const link = document.createElement("a");
    link.className = "student-next-event-link";
    link.href = "kalender-akademik.html";
    link.textContent = "Lihat kalender ›";
    footer.append(category, link);
    container.append(title, detail, footer);
  }

  function renderStudentViolations(data, monthLabel) {
    const rows = Array.isArray(data.daily) ? data.daily : [];
    const container = document.getElementById("student-violation-chart");
    container.replaceChildren();
    const max = Math.max(1, ...rows.map(function (row) { return Number(row.total) || 0; }));
    rows.forEach(function (row) {
      const total = Number(row.total) || 0;
      const bar = document.createElement("span");
      bar.className = "student-violation-bar" + (total ? " has-count" : "");
      bar.style.height = (total ? Math.max(12, (total / max) * 100) : 3) + "%";
      bar.title = formatStudentDate(row.date, { day: "numeric", month: "long" })
        + ": " + formatNumber(total) + " catatan";
      bar.setAttribute("aria-label", bar.title);
      container.appendChild(bar);
    });
    const total = Number(data.total) || 0;
    setText("violation-total", formatNumber(total));
    showEmpty("violation-empty", rows.length > 0 && total === 0);
    document.getElementById("student-violation-chart").setAttribute(
      "aria-label",
      "Grafik catatan pelanggaran harian, total " + formatNumber(total) + " pada " + monthLabel
    );
    if (rows.length) {
      setText("violation-chart-end-label", formatStudentDate(rows[rows.length - 1].date, { day: "numeric", month: "short" }));
    }
  }

  function renderMahadEvents(events) {
    const container = document.getElementById("student-mahad-events");
    container.replaceChildren();
    if (!Array.isArray(events) || !events.length) {
      const empty = document.createElement("p");
      empty.className = "student-mahad-empty";
      empty.textContent = "Belum ada kegiatan Ma’had mendatang di kalender.";
      container.appendChild(empty);
      return;
    }

    events.forEach(function (event) {
      const item = document.createElement("article");
      item.className = "student-mahad-event";
      const copy = document.createElement("div");
      copy.className = "student-mahad-event-copy";
      const name = document.createElement("strong");
      name.textContent = String(event.nama_kegiatan || "Kegiatan");
      const time = document.createElement("span");
      const startTime = formatStudentTime(event.jam_mulai);
      const endTime = formatStudentTime(event.jam_selesai);
      time.textContent = startTime ? startTime + (endTime ? "–" + endTime : "") : "Waktu belum ditentukan";
      const date = document.createElement("span");
      date.className = "student-mahad-event-date";
      date.textContent = formatStudentDate(event.tanggal_mulai, { day: "numeric", month: "short" });
      copy.append(name, time);
      item.append(copy, date);
      container.appendChild(item);
    });
  }

  function renderStudentUpdate(update) {
    const section = document.getElementById("student-home-update");
    if (!update) {
      section.hidden = true;
      return;
    }
    const value = update.new_value || update.old_value || {};
    const eventName = value.nama_kegiatan ? " · " + value.nama_kegiatan : "";
    setText("student-update-copy", String(update.reason || "Ada pembaruan pada kalender akademik.") + eventName);
    setText("student-update-date", "Diperbarui " + formatStudentDate(String(update.created_at).slice(0, 10), {
      day: "numeric",
      month: "long",
      year: "numeric",
    }));
    section.hidden = false;
  }

  function renderStudentDashboard(data) {
    const student = data.student || {};
    const name = String(student.name || "Santri");
    const monthValue = String(data.month || "");
    const monthDate = new Date(monthValue + "-01T12:00:00");
    setText("student-home-name", name);
    setText("student-home-meta", "Santri · Semester " + formatNumber(student.semester || 0)
      + (student.cohort ? " · Angkatan " + student.cohort : ""));
    setText("student-home-avatar", initials(name));
    document.getElementById("student-home-avatar").setAttribute("aria-label", "Buka profil " + name);
    document.getElementById("student-dashboard-month").value = monthValue;
    const formattedMonth = Number.isNaN(monthDate.getTime())
      ? "Bulan terpilih"
      : new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(monthDate);
    setText("violation-month-label", formattedMonth);

    const attendance = data.attendance || {};
    const percentage = attendance.percentage === null || attendance.percentage === undefined
      ? null
      : Math.max(0, Math.min(100, Number(attendance.percentage) || 0));
    setText("attendance-percent", percentage === null ? "—" : percentage + "%");
    setText("attendance-count", formatNumber(attendance.attended) + " / " + formatNumber(attendance.recorded));
    setText("attendance-note", attendance.recorded
      ? "Persentase dihitung dari pertemuan yang sudah tercatat."
      : "Belum ada catatan absensi pada bulan ini.");
    document.getElementById("attendance-ring").style.background = percentage === null
      ? "conic-gradient(#dfe8e1 0deg 360deg)"
      : "conic-gradient(#4c9568 " + percentage * 3.6 + "deg, #e6eee8 " + percentage * 3.6 + "deg 360deg)";
    document.getElementById("attendance-ring").setAttribute("aria-label",
      percentage === null ? "Belum ada catatan absensi" : "Persentase kehadiran " + percentage + " persen");

    renderStudentViolations(data.violations || {}, formattedMonth);
    renderNextEvent(data.next_event);
    renderMahadEvents(data.mahad_events);
    renderStudentUpdate(data.latest_update);
  }

  function loadStudentDashboard(month) {
    const query = new URLSearchParams({ bulan: month });
    return fetch("api/student-dashboard.php?" + query.toString(), { credentials: "same-origin" })
      .then(async function (response) {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || "Beranda santri gagal dimuat.");
        return data;
      })
      .then(renderStudentDashboard);
  }

  function closeMenu() {
    document.getElementById("sidebar").classList.remove("is-open");
    document.getElementById("sidebar-scrim").classList.remove("is-visible");
    document.getElementById("menu-button").setAttribute("aria-expanded", "false");
  }

  document.getElementById("menu-button").addEventListener("click", function () {
    document.getElementById("sidebar").classList.add("is-open");
    document.getElementById("sidebar-scrim").classList.add("is-visible");
    this.setAttribute("aria-expanded", "true");
  });
  document.getElementById("sidebar-scrim").addEventListener("click", closeMenu);
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeMenu();
  });

  async function logout(button) {
    button.disabled = true;
    try {
      const response = await fetch("api/logout.php", { method: "POST", credentials: "same-origin" });
      if (!response.ok) throw new Error("Sesi belum dapat diakhiri.");
      window.location.replace("index.html");
    } catch (error) {
      showMessage(error.message || "Gagal keluar. Silakan coba lagi.", "error");
      button.disabled = false;
    }
  }

  document.getElementById("logout-button").addEventListener("click", function () { logout(this); });
  document.getElementById("student-logout-button").addEventListener("click", function () { logout(this); });

  document.getElementById("student-dashboard-month").addEventListener("change", function () {
    if (!this.value) return;
    loadStudentDashboard(this.value).catch(function (error) {
      showMessage(error.message || "Ringkasan bulan tersebut gagal dimuat.", "error");
    });
  });

  const now = new Date();
  setText("current-year", now.getFullYear());
  setText("today-label", new Intl.DateTimeFormat("id-ID", { weekday: "long", day: "numeric", month: "long" }).format(now));
  setText("date-range", "30 hari terakhir");

  fetch("api/session.php", { credentials: "same-origin" })
    .then(async function (response) {
      const data = await response.json();
      if (!response.ok || !data.success || !data.user) {
        window.location.replace("index.html");
        throw new Error("Sesi tidak ditemukan.");
      }
      return data.user;
    })
    .then(function (user) {
      const name = user.name || user.email || "Pengguna";
      setText("sidebar-name", name);
      setText("sidebar-role", user.role || "Akun");
      setText("topbar-name", name);
      setText("sidebar-avatar", initials(name));
      setText("top-avatar", initials(name));
      const isAdmin = String(user.role || "").toLowerCase() === "admin";
      const role = String(user.role || "").toLowerCase();
      const canWriteAttendance = isAdmin || role === "musyrif" || role === "mas'ul";
      document.querySelectorAll(".admin-nav").forEach(function (item) {
        item.hidden = !isAdmin;
      });
      document.querySelectorAll(".attendance-nav").forEach(function (item) {
        item.hidden = !canWriteAttendance;
      });
      if (role === "santri" || role === "mahasantri") {
        document.body.classList.add("student-home-active");
        document.getElementById("student-home").hidden = false;
        document.getElementById("legacy-dashboard").hidden = true;
        const currentDate = new Date();
        return loadStudentDashboard(currentDate.getFullYear() + "-" + String(currentDate.getMonth() + 1).padStart(2, "0"));
      }
      document.getElementById("legacy-dashboard").hidden = false;
      return fetch("api/dashboard-overview.php", { credentials: "same-origin" }).then(async function (response) {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || "Ringkasan dashboard gagal dimuat.");
        return data;
      });
    })
    .then(function (data) {
      if (data) renderDashboard(data);
    })
    .catch(function (error) {
      if (error.message !== "Sesi tidak ditemukan.") {
        showMessage(error.message || "Dashboard belum dapat dimuat. Coba muat ulang halaman.", "error");
      }
    });
});