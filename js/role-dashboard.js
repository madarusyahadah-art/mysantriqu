(function () {
  const statusLabels = {
    hadir: "Hadir",
    izin: "Izin",
    sakit: "Sakit",
    alpha: "Alpha",
    izin_menginap: "Izin menginap",
    lainnya: "Lainnya",
  };
  const activityIcons = [
    "book-outline", "moon-outline", "school-outline", "people-outline",
    "fitness-outline", "sparkles-outline", "calendar-outline",
  ];
  let currentUser = null;
  let currentData = null;
  let selectedStudentId = "";
  let selectedDate = "";
  let selectedStatus = "nonhadir";
  let selectedActivityId = "";
  let startMonth = "";
  let endMonth = "";

  function escapePhotoUrl(photo) {
    if (!photo) return "assets/Logo.png";
    try {
      const url = new URL(photo, window.location.href);
      return ["http:", "https:"].includes(url.protocol) ? url.href : "assets/Logo.png";
    } catch (error) {
      return "assets/Logo.png";
    }
  }

  function monthBounds(month) {
    const [year, monthNumber] = month.split("-").map(Number);
    const lastDay = new Date(year, monthNumber, 0).getDate();
    return { year, monthNumber, lastDay };
  }

  function monthInputValue(date) {
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0");
  }

  async function fetchDashboard(monthStart, monthEnd, studentId) {
    const params = new URLSearchParams({
      bulan_mulai: monthStart,
      bulan_selesai: monthEnd,
    });
    if (studentId) params.set("santri_id", studentId);
    if (currentUser.role === "kajur") {
      params.set("cari", document.getElementById("kajur-search").value.trim());
      params.set("angkatan", document.getElementById("kajur-angkatan").value);
      params.set("urutan", document.getElementById("kajur-order").value);
    }
    const response = await fetch("api/role-dashboard.php?" + params.toString(), { credentials: "same-origin" });
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || "Data dashboard role gagal dimuat.");
    }
    return data;
  }

  function renderProfile(data) {
    const section = document.getElementById("mahasantri-dashboard");
    section.hidden = currentUser.role !== "mahasantri";
    if (section.hidden) return;

    const profile = data.santri;
    document.getElementById("role-profile-name").textContent = profile ? profile.nama : (data.user.name || "Mahasantri");
    document.getElementById("role-profile-email").textContent = data.user.email || "";
    document.getElementById("role-profile-photo").src = escapePhotoUrl(data.user.photo);
    document.getElementById("profile-photo-form").hidden = false;

    const facts = document.getElementById("profile-facts");
    facts.replaceChildren();
    const values = profile
      ? [
          ["No. KTM", profile.no_ktm || "Belum diisi"],
          ["Asal", profile.asal || "Belum diisi"],
          ["Semester", "Semester " + (profile.semester_aktif || 1)],
          ["Angkatan", profile.angkatan || "Belum diisi"],
        ]
      : [["Status", "Profil belum dihubungkan oleh admin"]];
    values.forEach(([label, value]) => {
      const fact = document.createElement("span");
      fact.textContent = label + ": " + value;
      facts.appendChild(fact);
    });
    document.getElementById("activities-intro").textContent = profile
      ? "Pilih kegiatan untuk melihat laporan absensi dan pelanggaran terkait."
      : "Kegiatan aktif tampil di sini. Hubungi admin untuk menghubungkan profil agar laporan pribadi tersedia.";
  }

  function renderActivities(data) {
    const section = document.getElementById("activities-card");
    section.hidden = currentUser.role !== "mahasantri";
    if (section.hidden) return;

    const container = document.getElementById("activity-tiles");
    container.replaceChildren();
    const activities = data.kegiatan || [];
    activities.forEach((activity, index) => {
      const tile = document.createElement("a");
      tile.className = "activity-tile" + (index >= 7 ? " more-activity" : "");
      tile.href = "#student-report-card";
      tile.hidden = index >= 7;
      tile.dataset.activityId = activity.id;
      const icon = document.createElement("ion-icon");
      icon.setAttribute("name", activityIcons[index % activityIcons.length]);
      icon.setAttribute("aria-hidden", "true");
      const label = document.createElement("strong");
      label.textContent = activity.nama_kegiatan;
      const category = document.createElement("small");
      category.textContent = activity.kategori || activity.frekuensi || "Kegiatan";
      tile.append(icon, label, category);
      tile.addEventListener("click", () => {
        selectedActivityId = String(activity.id);
        selectedDate = "";
        selectedStatus = "nonhadir";
        renderReport(currentData);
      });
      container.appendChild(tile);
    });

    const allTile = document.createElement("button");
    allTile.type = "button";
    allTile.className = "activity-tile";
    const allIcon = document.createElement("ion-icon");
    allIcon.setAttribute("name", "apps-outline");
    const allLabel = document.createElement("strong");
    allLabel.textContent = "Semua kegiatan & fitur";
    allTile.append(allIcon, allLabel);
    allTile.addEventListener("click", () => {
      container.querySelectorAll(".more-activity").forEach((tile) => { tile.hidden = false; });
      container.querySelectorAll(".future-feature").forEach((tile) => { tile.hidden = false; });
      selectedActivityId = "";
      selectedDate = "";
      selectedStatus = "nonhadir";
      renderReport(currentData);
      document.getElementById("student-report-card").scrollIntoView({ behavior: "smooth" });
    });
    container.appendChild(allTile);

    [
      ["book-outline", "Jurnal & munazarah"],
      ["document-text-outline", "Pengumpulan skripsi"],
      ["school-outline", "KHS pribadi"],
    ].forEach(([iconName, title]) => {
      const tile = document.createElement("div");
      tile.className = "activity-tile is-future future-feature";
      tile.hidden = true;
      tile.setAttribute("aria-disabled", "true");
      const icon = document.createElement("ion-icon");
      icon.setAttribute("name", iconName);
      const label = document.createElement("strong");
      label.textContent = title;
      const status = document.createElement("small");
      status.textContent = "Segera hadir";
      tile.append(icon, label, status);
      container.appendChild(tile);
    });
  }

  function attendanceMatches(record) {
    const isAbsent = record.status !== "hadir";
    if (selectedStatus === "nonhadir" && !isAbsent) return false;
    if (selectedStatus !== "nonhadir" && selectedStatus !== "semua" && record.status !== selectedStatus) return false;
    if (selectedActivityId && String(record.kegiatan_id) !== selectedActivityId) return false;
    if (selectedDate && record.tanggal !== selectedDate) return false;
    return true;
  }

  function renderSummary(data) {
    const container = document.getElementById("report-summary");
    container.replaceChildren();
    const summaries = data.ringkasan || {};
    [["nonhadir", "Semua ketidakhadiran", (summaries.izin || 0) + (summaries.sakit || 0) + (summaries.alpha || 0) + (summaries.izin_menginap || 0) + (summaries.lainnya || 0)],
      ...Object.entries(statusLabels).map(([status, label]) => [status, label, summaries[status] || 0])]
      .forEach(([status, label, count]) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "filter-chip";
        button.setAttribute("aria-pressed", String(selectedStatus === status));
        button.textContent = label + ": " + count;
        button.addEventListener("click", () => {
          selectedStatus = status;
          selectedDate = "";
          renderReport(currentData);
        });
        container.appendChild(button);
      });
    const allButton = document.createElement("button");
    allButton.type = "button";
    allButton.className = "filter-chip";
    allButton.setAttribute("aria-pressed", String(selectedStatus === "semua"));
    allButton.textContent = "Semua catatan: " + (data.absensi || []).length;
    allButton.addEventListener("click", () => {
      selectedStatus = "semua";
      selectedDate = "";
      renderReport(currentData);
    });
    container.appendChild(allButton);
  }

  function renderCalendar(data) {
    const calendar = document.getElementById("attendance-calendar");
    calendar.replaceChildren();
    const month = currentUser.role === "kajur" ? endMonth : startMonth;
    const { year, monthNumber, lastDay } = monthBounds(month);
    const heading = document.createElement("div");
    heading.className = "calendar-heading";
    heading.textContent = new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(new Date(year, monthNumber - 1, 1));
    calendar.appendChild(heading);

    ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"].forEach((weekday) => {
      const day = document.createElement("span");
      day.className = "calendar-weekday";
      day.textContent = weekday;
      calendar.appendChild(day);
    });

    const firstWeekday = (new Date(year, monthNumber - 1, 1).getDay() + 6) % 7;
    const totalCells = Math.ceil((firstWeekday + lastDay) / 7) * 7;
    const recordsByDate = new Map();
    (data.absensi || []).forEach((record) => {
      if (!recordsByDate.has(record.tanggal)) recordsByDate.set(record.tanggal, []);
      recordsByDate.get(record.tanggal).push(record);
    });

    for (let index = 0; index < totalCells; index++) {
      const dateNumber = index - firstWeekday + 1;
      const dayButton = document.createElement("button");
      dayButton.type = "button";
      dayButton.className = "calendar-day";
      if (dateNumber < 1 || dateNumber > lastDay) {
        dayButton.classList.add("outside");
        dayButton.disabled = true;
        dayButton.textContent = String(dateNumber < 1
          ? new Date(year, monthNumber - 1, dateNumber).getDate()
          : new Date(year, monthNumber - 1, dateNumber).getDate());
      } else {
        const dayDate = year + "-" + String(monthNumber).padStart(2, "0") + "-" + String(dateNumber).padStart(2, "0");
        const records = recordsByDate.get(dayDate) || [];
        const marked = records.filter((record) => record.status !== "hadir" &&
          (selectedActivityId === "" || String(record.kegiatan_id) === selectedActivityId) &&
          (selectedStatus === "semua" || selectedStatus === "nonhadir" || record.status === selectedStatus));
        dayButton.textContent = String(dateNumber);
        if (marked.length) {
          dayButton.classList.add("has-record", "status-" + marked[0].status);
          dayButton.title = marked.map((record) => (statusLabels[record.status] || record.status) + " · " + (record.nama_kegiatan || "Kegiatan")).join("\n");
          dayButton.addEventListener("click", () => {
            selectedDate = selectedDate === dayDate ? "" : dayDate;
            renderReport(currentData);
          });
        }
        if (selectedDate === dayDate) dayButton.classList.add("selected");
      }
      calendar.appendChild(dayButton);
    }
  }

  function renderAttendanceRecords(data) {
    const container = document.getElementById("attendance-records");
    container.replaceChildren();
    const records = (data.absensi || []).filter(attendanceMatches);
    if (!records.length) {
      const empty = document.createElement("p");
      empty.className = "role-empty";
      empty.textContent = "Tidak ada catatan absensi untuk filter yang dipilih.";
      container.appendChild(empty);
      return;
    }
    records.forEach((record) => {
      const item = document.createElement("article");
      item.className = "record-item";
      const title = document.createElement("strong");
      title.textContent = record.tanggal + " · " + (record.nama_kegiatan || "Kegiatan") + " · " + (statusLabels[record.status] || record.status);
      const detail = document.createElement("p");
      detail.textContent = [
        record.keterangan ? "Keterangan: " + record.keterangan : "",
        record.tindak_lanjut ? "Tindak lanjut: " + record.tindak_lanjut : "",
        record.pencatat ? "Dicatat oleh: " + record.pencatat : "",
      ].filter(Boolean).join("\n") || "Tidak ada keterangan tambahan.";
      item.append(title, detail);
      container.appendChild(item);
    });
  }

  function renderViolations(data) {
    const container = document.getElementById("violation-records");
    container.replaceChildren();
    const records = data.pelanggaran || [];
    if (!records.length) {
      const empty = document.createElement("p");
      empty.className = "role-empty";
      empty.textContent = "Tidak ada catatan pelanggaran pada rentang ini.";
      container.appendChild(empty);
      return;
    }
    records.forEach((record) => {
      const item = document.createElement("article");
      item.className = "record-item";
      const title = document.createElement("strong");
      title.textContent = record.tanggal + " · " + record.jenis_pelanggaran + " · " + record.poin + " poin";
      const detail = document.createElement("p");
      detail.textContent = [
        record.keterangan ? "Keterangan: " + record.keterangan : "",
        record.catatan_bk ? "Tindak lanjut/catatan: " + record.catatan_bk : "",
        record.status_tindak_lanjut ? "Status: " + record.status_tindak_lanjut : "",
        record.pencatat ? "Dicatat oleh: " + record.pencatat : "",
      ].filter(Boolean).join("\n") || "Tidak ada rincian tambahan.";
      item.append(title, detail);
      container.appendChild(item);
    });
  }

  function renderReport(data) {
    const section = document.getElementById("student-report-card");
    section.hidden = !data || !data.santri;
    if (section.hidden) return;
    const name = currentUser.role === "kajur" ? data.santri.nama : "Laporan bulanan saya";
    document.getElementById("report-heading").textContent = name + " · " + data.rentang.mulai + " – " + data.rentang.selesai;
    renderSummary(data);
    renderCalendar(data);
    renderAttendanceRecords(data);
    renderViolations(data);
  }

  function renderKajur(data) {
    const section = document.getElementById("kajur-dashboard");
    section.hidden = currentUser.role !== "kajur";
    if (section.hidden) return;

    const cohort = document.getElementById("kajur-angkatan");
    const selectedCohort = cohort.value;
    const cohorts = Array.from(new Set((data.daftar_mahasantri || []).map((student) => student.angkatan).filter(Boolean))).sort();
    cohort.replaceChildren(new Option("Semua angkatan", ""));
    cohorts.forEach((value) => cohort.add(new Option(value, value)));
    cohort.value = cohorts.includes(selectedCohort) ? selectedCohort : "";

    const list = document.getElementById("kajur-student-list");
    list.replaceChildren();
    if (!(data.daftar_mahasantri || []).length) {
      const empty = document.createElement("p");
      empty.className = "role-empty";
      empty.textContent = "Tidak ada Mahasantri yang cocok dengan filter.";
      list.appendChild(empty);
      return;
    }
    data.daftar_mahasantri.forEach((student) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "student-row";
      button.setAttribute("aria-pressed", String(String(student.id) === selectedStudentId));
      const name = document.createElement("span");
      name.textContent = student.nama;
      const cohortLabel = document.createElement("small");
      cohortLabel.textContent = student.angkatan || "Angkatan -";
      button.append(name, cohortLabel);
      button.addEventListener("click", () => {
        selectedStudentId = String(student.id);
        loadData(student.id).catch(showDashboardError);
      });
      list.appendChild(button);
    });

    const detail = document.getElementById("kajur-student-detail");
    if (!data.santri) {
      detail.className = "role-empty";
      detail.textContent = "Pilih Mahasantri untuk melihat absensi, pelanggaran, dan tindak lanjut pada rentang bulan yang dipilih.";
      return;
    }
    detail.className = "";
    const heading = document.createElement("h3");
    heading.textContent = data.santri.nama;
    heading.style.marginBottom = "0.8rem";
    const identity = document.createElement("div");
    identity.className = "profile-facts";
    [
      ["Nama", data.santri.nama],
      ["No. KTM", data.santri.no_ktm || "-"],
      ["Angkatan", data.santri.angkatan || "-"],
      ["Semester", data.santri.semester_aktif || "-"],
    ].forEach(([label, value]) => {
      const item = document.createElement("span");
      item.textContent = label + ": " + value;
      identity.appendChild(item);
    });
    detail.replaceChildren(heading, identity);
  }

  function populateMonths() {
    const now = new Date();
    startMonth = monthInputValue(now);
    endMonth = startMonth;
    document.getElementById("student-report-month").value = startMonth;
    document.getElementById("report-start").value = startMonth;
    document.getElementById("report-end").value = endMonth;
  }

  function showDashboardError(error) {
    const target = document.getElementById("dashboard-error");
    target.textContent = error.message || String(error);
    target.style.display = "block";
  }

  async function loadData(studentId) {
    const data = await fetchDashboard(startMonth, endMonth, studentId || "");
    currentData = data;
    document.getElementById("role-student-count").textContent = Number(data.total_santri || 0).toLocaleString("id-ID");
    renderProfile(data);
    renderActivities(data);
    renderKajur(data);
    renderReport(data);
    return data;
  }

  async function initialize(user) {
    currentUser = user;
    const role = user.role === "kepala" ? "kajur" : user.role;
    user.role = role;
    document.getElementById("role-dashboard").setAttribute("aria-label", role === "kajur" ? "Dashboard Kajur" : "Dashboard Mahasantri");
    document.getElementById("student-month-controls").hidden = role !== "mahasantri";
    document.getElementById("report-start").closest("label").hidden = role !== "kajur";
    document.getElementById("report-end").closest("label").hidden = role !== "kajur";
    document.getElementById("kajur-dashboard").hidden = role !== "kajur";
    document.getElementById("role-academic-calendar-link").hidden = !["mahasantri", "kajur"].includes(role);
    document.getElementById("activities-card").hidden = role !== "mahasantri";
    document.getElementById("mahasantri-dashboard").hidden = role !== "mahasantri";
    document.getElementById("student-report-card").hidden = true;
    document.getElementById("profile-photo-form").hidden = role !== "mahasantri";
    populateMonths();
    await loadData(role === "mahasantri" ? "" : selectedStudentId);
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("load-student-month").addEventListener("click", () => {
      startMonth = document.getElementById("student-report-month").value;
      endMonth = startMonth;
      selectedDate = "";
      loadData().catch(showDashboardError);
    });
    document.getElementById("apply-kajur-filters").addEventListener("click", () => {
      startMonth = document.getElementById("report-start").value;
      endMonth = document.getElementById("report-end").value;
      selectedDate = "";
      selectedStudentId = "";
      loadData().catch(showDashboardError);
    });
    document.getElementById("profile-photo-form").addEventListener("submit", async (event) => {
      event.preventDefault();
      const input = document.getElementById("profile-photo-input");
      if (!input.files || !input.files[0]) return;
      const message = document.getElementById("photo-message");
      const form = new FormData();
      form.append("photo", input.files[0]);
      message.textContent = "Mengunggah foto...";
      try {
        const response = await fetch("api/update-own-photo.php", { method: "POST", credentials: "same-origin", body: form });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Foto gagal diperbarui.");
        document.getElementById("role-profile-photo").src = escapePhotoUrl(result.photo) + "?v=" + Date.now();
        document.getElementById("user-photo").src = escapePhotoUrl(result.photo) + "?v=" + Date.now();
        currentUser.photo = result.photo;
        message.textContent = "Foto profil berhasil diperbarui.";
        input.value = "";
      } catch (error) {
        message.textContent = error.message;
      }
    });
    document.getElementById("kajur-search").addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        selectedStudentId = "";
        loadData().catch(showDashboardError);
      }
    });
  });

  window.initializeRoleDashboard = initialize;
})();
