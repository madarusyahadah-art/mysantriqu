"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const allowedRoles = ["admin", "kajur", "musyrif", "supervisor", "mas'ul"];
  const labels = {
    hadir: "Hadir",
    izin: "Izin",
    sakit: "Sakit",
    alpa: "Alpa",
    belum: "Belum diproses",
    diproses: "Diproses",
    selesai: "Selesai",
  };
  const pageSize = 50;
  const state = {
    activeTab: "attendance",
    attendanceOffset: 0,
    attendanceTotal: 0,
    attendanceLoading: false,
    attendanceLoaded: false,
    attendanceSequence: 0,
    attendanceRows: [],
    violationOffset: 0,
    violationTotal: 0,
    violationLoading: false,
    violationLoaded: false,
    violationSequence: 0,
    violationRows: [],
  };
  let searchTimer = null;

  function localDateValue(date) {
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0")
      + "-" + String(date.getDate()).padStart(2, "0");
  }

  function showMessage(text, kind) {
    const message = document.getElementById("report-message");
    message.textContent = text;
    message.dataset.state = kind || "error";
    message.hidden = !text;
  }

  function formatNumber(value) {
    return new Intl.NumberFormat("id-ID").format(Number(value) || 0);
  }

  function formatDate(value) {
    const date = new Date(String(value || "") + "T12:00:00");
    if (Number.isNaN(date.getTime())) return String(value || "—");
    return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" }).format(date);
  }

  async function requestJson(url) {
    const response = await fetch(url, { credentials: "same-origin" });
    let data;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error("Respons server tidak dapat dibaca. Coba lagi.");
    }
    if (response.status === 401) {
      window.location.replace("index.html");
      throw new Error("Sesi login berakhir.");
    }
    if (!response.ok || !data.success) {
      throw new Error(data.error || "Permintaan tidak dapat diproses.");
    }
    return data;
  }

  function setTableState(id, message, columns) {
    const body = document.getElementById(id);
    body.replaceChildren();
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.className = "report-table-state";
    cell.colSpan = columns;
    cell.textContent = message;
    row.appendChild(cell);
    body.appendChild(row);
  }

  function appendCell(row, value) {
    const cell = document.createElement("td");
    cell.textContent = String(value == null || value === "" ? "—" : value);
    row.appendChild(cell);
    return cell;
  }

  function appendStudentProfileLink(cell, record) {
    const link = document.createElement("a");
    link.className = "report-student-link";
    link.href = "profil-santri.html?santri_id=" + encodeURIComponent(String(record.santri_id));
    link.textContent = String(record.nama_santri || "Santri");
    cell.replaceChildren(link);
  }

  function appendStatus(row, value) {
    const cell = document.createElement("td");
    const badge = document.createElement("span");
    badge.className = "report-status";
    badge.dataset.status = String(value || "");
    badge.textContent = labels[value] || String(value || "—");
    cell.appendChild(badge);
    row.appendChild(cell);
  }

  function fillOptions(selectId, options, valueField, labelField, placeholder, prefix) {
    const select = document.getElementById(selectId);
    const previous = select.value;
    select.replaceChildren();
    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = placeholder;
    select.appendChild(allOption);
    (Array.isArray(options) ? options : []).forEach(function (item) {
      const option = document.createElement("option");
      option.value = String(item[valueField] == null ? item : item[valueField]);
      option.textContent = (prefix || "") + String(item[labelField] == null ? item : item[labelField]);
      select.appendChild(option);
    });
    if (Array.from(select.options).some(function (option) { return option.value === previous; })) {
      select.value = previous;
    }
  }

  function attendanceParams(offset, limit) {
    const params = new URLSearchParams({ limit: String(limit || pageSize), offset: String(offset || 0) });
    const values = {
      dari: document.getElementById("attendance-from").value,
      sampai: document.getElementById("attendance-to").value,
      kegiatan_id: document.getElementById("attendance-activity").value,
      angkatan: document.getElementById("attendance-cohort").value,
      status: document.getElementById("attendance-status").value,
      q: document.getElementById("attendance-search").value.trim(),
    };
    Object.keys(values).forEach(function (key) {
      if (values[key]) params.set(key, values[key]);
    });
    return params;
  }

  function violationParams(offset, limit) {
    const params = new URLSearchParams({ limit: String(limit || pageSize), offset: String(offset || 0) });
    const values = {
      dari: document.getElementById("violation-from").value,
      sampai: document.getElementById("violation-to").value,
      angkatan: document.getElementById("violation-cohort").value,
      status: document.getElementById("violation-status").value,
      q: document.getElementById("violation-search").value.trim(),
    };
    Object.keys(values).forEach(function (key) {
      if (values[key]) params.set(key, values[key]);
    });
    return params;
  }

  function renderAttendance(data) {
    const summary = data.ringkasan || {};
    document.getElementById("attendance-total").textContent = formatNumber(summary.total);
    document.getElementById("attendance-present").textContent = formatNumber(summary.hadir);
    document.getElementById("attendance-permission").textContent = formatNumber(summary.izin);
    document.getElementById("attendance-sick").textContent = formatNumber(summary.sakit);
    document.getElementById("attendance-absent").textContent = formatNumber(summary.alpa);
    state.attendanceTotal = Number(summary.total) || 0;
    state.attendanceRows = Array.isArray(data.absensi) ? data.absensi : [];
    document.getElementById("attendance-result-count").textContent = formatNumber(state.attendanceTotal);
    fillOptions("attendance-activity", data.kegiatan, "id", "nama_kegiatan", "Semua kegiatan", "");
    fillOptions("attendance-cohort", data.angkatan, "value", "value", "Semua angkatan", "Angkatan ");

    const body = document.getElementById("attendance-table");
    if (!state.attendanceRows.length) {
      setTableState("attendance-table", state.attendanceTotal ? "Tidak ada catatan pada halaman ini." : "Belum ada catatan kehadiran sesuai filter.", 6);
      return;
    }
    body.replaceChildren();
    state.attendanceRows.forEach(function (record) {
      const row = document.createElement("tr");
      appendCell(row, formatDate(record.tanggal));
      const student = appendCell(row, record.nama_santri);
      appendStudentProfileLink(student, record);
      if (record.no_ktm) {
        const detail = document.createElement("small");
        detail.className = "report-subtext";
        detail.textContent = "NIM " + record.no_ktm;
        student.appendChild(document.createElement("br"));
        student.appendChild(detail);
      }
      appendCell(row, record.angkatan ? "Angkatan " + record.angkatan : "—");
      const activity = appendCell(row, record.nama_kegiatan);
      if (record.kategori) {
        const detail = document.createElement("small");
        detail.className = "report-subtext";
        detail.textContent = record.kategori;
        activity.appendChild(document.createElement("br"));
        activity.appendChild(detail);
      }
      appendStatus(row, record.status);
      appendCell(row, record.keterangan);
      body.appendChild(row);
    });
  }

  function renderViolations(data) {
    const summary = data.ringkasan || {};
    document.getElementById("violation-total").textContent = formatNumber(summary.total);
    document.getElementById("violation-pending").textContent = formatNumber(summary.belum);
    document.getElementById("violation-progress").textContent = formatNumber(summary.diproses);
    document.getElementById("violation-completed").textContent = formatNumber(summary.selesai);
    document.getElementById("violation-points").textContent = formatNumber(summary.poin);
    state.violationTotal = Number(summary.total) || 0;
    state.violationRows = Array.isArray(data.pelanggaran) ? data.pelanggaran : [];
    document.getElementById("violation-result-count").textContent = formatNumber(state.violationTotal);
    fillOptions("violation-cohort", data.angkatan, "value", "value", "Semua angkatan", "Angkatan ");

    const body = document.getElementById("violation-table");
    if (!state.violationRows.length) {
      setTableState("violation-table", state.violationTotal ? "Tidak ada catatan pada halaman ini." : "Belum ada laporan pelanggaran sesuai filter.", 7);
      return;
    }
    body.replaceChildren();
    state.violationRows.forEach(function (record) {
      const row = document.createElement("tr");
      appendCell(row, formatDate(record.tanggal));
      const student = appendCell(row, record.nama_santri);
      appendStudentProfileLink(student, record);
      if (record.no_ktm) {
        const detail = document.createElement("small");
        detail.className = "report-subtext";
        detail.textContent = "NIM " + record.no_ktm;
        student.appendChild(document.createElement("br"));
        student.appendChild(detail);
      }
      appendCell(row, record.angkatan ? "Angkatan " + record.angkatan : "—");
      appendCell(row, record.jenis_pelanggaran);
      appendCell(row, formatNumber(record.poin));
      appendStatus(row, record.status_tindak_lanjut);
      appendCell(row, record.keterangan);
      body.appendChild(row);
    });
  }

  function updatePagination(kind) {
    const isAttendance = kind === "attendance";
    const prefix = isAttendance ? "attendance" : "violation";
    const offset = isAttendance ? state.attendanceOffset : state.violationOffset;
    const total = isAttendance ? state.attendanceTotal : state.violationTotal;
    const rows = isAttendance ? state.attendanceRows : state.violationRows;
    const loading = isAttendance ? state.attendanceLoading : state.violationLoading;
    document.getElementById(prefix + "-page-label").textContent = "Halaman " + formatNumber(Math.floor(offset / pageSize) + 1);
    document.getElementById(prefix + "-prev").disabled = loading || offset <= 0;
    document.getElementById(prefix + "-next").disabled = loading || offset + rows.length >= total;
  }

  async function loadAttendance(resetOffset) {
    if (resetOffset) state.attendanceOffset = 0;
    const sequence = ++state.attendanceSequence;
    state.attendanceLoading = true;
    updatePagination("attendance");
    setTableState("attendance-table", "Memuat laporan kehadiran...", 6);
    try {
      const data = await requestJson("api/get-laporan-absensi.php?" + attendanceParams(state.attendanceOffset).toString());
      if (sequence !== state.attendanceSequence) return false;
      renderAttendance(data);
      state.attendanceLoaded = true;
      showMessage("", "");
      return true;
    } catch (error) {
      if (sequence !== state.attendanceSequence) return false;
      state.attendanceRows = [];
      state.attendanceTotal = 0;
      setTableState("attendance-table", error.message || "Laporan kehadiran gagal dimuat.", 6);
      showMessage(error.message || "Laporan kehadiran gagal dimuat.", "error");
      return false;
    } finally {
      if (sequence === state.attendanceSequence) state.attendanceLoading = false;
      updatePagination("attendance");
    }
  }

  async function loadViolations(resetOffset) {
    if (resetOffset) state.violationOffset = 0;
    const sequence = ++state.violationSequence;
    state.violationLoading = true;
    updatePagination("violation");
    setTableState("violation-table", "Memuat laporan pelanggaran...", 7);
    try {
      const data = await requestJson("api/get-laporan-pelanggaran.php?" + violationParams(state.violationOffset).toString());
      if (sequence !== state.violationSequence) return false;
      renderViolations(data);
      state.violationLoaded = true;
      showMessage("", "");
      return true;
    } catch (error) {
      if (sequence !== state.violationSequence) return false;
      state.violationRows = [];
      state.violationTotal = 0;
      setTableState("violation-table", error.message || "Laporan pelanggaran gagal dimuat.", 7);
      showMessage(error.message || "Laporan pelanggaran gagal dimuat.", "error");
      return false;
    } finally {
      if (sequence === state.violationSequence) state.violationLoading = false;
      updatePagination("violation");
    }
  }

  function selectTab(kind) {
    state.activeTab = kind;
    const attendanceActive = kind === "attendance";
    document.getElementById("attendance-tab").classList.toggle("is-active", attendanceActive);
    document.getElementById("attendance-tab").setAttribute("aria-selected", String(attendanceActive));
    document.getElementById("violation-tab").classList.toggle("is-active", !attendanceActive);
    document.getElementById("violation-tab").setAttribute("aria-selected", String(!attendanceActive));
    document.getElementById("attendance-report").hidden = !attendanceActive;
    document.getElementById("violation-report").hidden = attendanceActive;
    if (attendanceActive && !state.attendanceLoaded) loadAttendance(false);
    if (!attendanceActive && !state.violationLoaded) loadViolations(false);
  }

  function csvSafe(value) {
    let text = String(value == null ? "" : value);
    if (/^[\s]*[=+\-@]/.test(text)) text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
  }

  function downloadCsv(filename, headers, rows) {
    const csv = "\uFEFF" + [headers].concat(rows).map(function (row) {
      return row.map(csvSafe).join(",");
    }).join("\r\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
  }

  async function fetchAllPages(kind) {
    const isAttendance = kind === "attendance";
    const paramsFor = isAttendance ? attendanceParams : violationParams;
    const endpoint = isAttendance ? "api/get-laporan-absensi.php?" : "api/get-laporan-pelanggaran.php?";
    const key = isAttendance ? "absensi" : "pelanggaran";
    const filterParams = paramsFor(0, 100);
    const allRows = [];
    let offset = 0;
    let expectedTotal = 0;
    do {
      const requestParams = new URLSearchParams(filterParams);
      requestParams.set("limit", "100");
      requestParams.set("offset", String(offset));
      const data = await requestJson(endpoint + requestParams.toString());
      const page = Array.isArray(data[key]) ? data[key] : [];
      allRows.push.apply(allRows, page);
      expectedTotal = Number((data.ringkasan || {}).total) || 0;
      offset += page.length;
      if (page.length === 0 || offset > 100000) break;
    } while (offset < expectedTotal);
    if (offset < expectedTotal) {
      throw new Error("Ekspor melebihi batas laporan yang dapat dimuat sekaligus. Persempit rentang tanggal.");
    }
    return allRows;
  }

  async function exportReport(kind, button) {
    button.disabled = true;
    showMessage("Menyiapkan berkas CSV, mohon tunggu...", "success");
    try {
      const rows = await fetchAllPages(kind);
      if (kind === "attendance") {
        downloadCsv("laporan-kehadiran-" + localDateValue(new Date()) + ".csv",
          ["Tanggal", "Nama Santri", "NIM", "Angkatan", "Kegiatan", "Kategori", "Status", "Keterangan"],
          rows.map(function (row) {
            return [row.tanggal, row.nama_santri, row.no_ktm, row.angkatan, row.nama_kegiatan, row.kategori, labels[row.status] || row.status, row.keterangan];
          }));
      } else {
        downloadCsv("laporan-pelanggaran-" + localDateValue(new Date()) + ".csv",
          ["Tanggal", "Nama Santri", "NIM", "Angkatan", "Jenis Pelanggaran", "Poin", "Tindak Lanjut", "Keterangan"],
          rows.map(function (row) {
            return [row.tanggal, row.nama_santri, row.no_ktm, row.angkatan, row.jenis_pelanggaran, row.poin, labels[row.status_tindak_lanjut] || row.status_tindak_lanjut, row.keterangan];
          }));
      }
      showMessage(rows.length ? "CSV berhasil diunduh (" + formatNumber(rows.length) + " baris)." : "Tidak ada data untuk diekspor.", "success");
    } catch (error) {
      showMessage(error.message || "Berkas CSV gagal disiapkan.", "error");
    } finally {
      button.disabled = false;
    }
  }

  function setDefaultDates(prefix) {
    const today = new Date();
    document.getElementById(prefix + "-from").value = today.getFullYear() + "-"
      + String(today.getMonth() + 1).padStart(2, "0") + "-01";
    document.getElementById(prefix + "-to").value = localDateValue(today);
  }

  function initFilterListeners(prefix, load) {
    ["from", "to", "activity", "cohort", "status"].forEach(function (filter) {
      const element = document.getElementById(prefix + "-" + filter);
      if (!element) return;
      element.addEventListener("change", function () { load(true); });
    });
    document.getElementById(prefix + "-search").addEventListener("input", function () {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () { load(true); }, 250);
    });
    document.getElementById(prefix + "-clear").addEventListener("click", function () {
      const from = document.getElementById(prefix + "-from");
      const to = document.getElementById(prefix + "-to");
      from.value = "";
      to.value = "";
      document.getElementById(prefix + "-search").value = "";
      document.getElementById(prefix + "-status").value = "";
      if (prefix === "attendance") {
        document.getElementById("attendance-activity").value = "";
      }
      document.getElementById(prefix + "-cohort").value = "";
      load(true);
    });
  }

  function setUser(user) {
    const name = user.name || user.email || "Pengguna";
    const initials = name.trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase() || "?";
    document.getElementById("sidebar-name").textContent = name;
    document.getElementById("sidebar-role").textContent = user.role || "Pengguna";
    document.getElementById("topbar-name").textContent = name;
    document.getElementById("sidebar-avatar").textContent = initials;
    document.getElementById("top-avatar").textContent = initials;
    if (String(user.role || "").toLowerCase() === "admin") {
      document.querySelectorAll(".admin-nav").forEach(function (item) { item.hidden = false; });
    }
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

  document.getElementById("attendance-tab").addEventListener("click", function () { selectTab("attendance"); });
  document.getElementById("violation-tab").addEventListener("click", function () { selectTab("violation"); });
  ["attendance", "violation"].forEach(function (kind) {
    const isAttendance = kind === "attendance";
    document.getElementById(kind + "-prev").addEventListener("click", function () {
      if (isAttendance) state.attendanceOffset = Math.max(0, state.attendanceOffset - pageSize);
      else state.violationOffset = Math.max(0, state.violationOffset - pageSize);
      (isAttendance ? loadAttendance : loadViolations)(false);
    });
    document.getElementById(kind + "-next").addEventListener("click", function () {
      if (isAttendance) state.attendanceOffset += pageSize;
      else state.violationOffset += pageSize;
      (isAttendance ? loadAttendance : loadViolations)(false);
    });
    document.getElementById(kind + "-export").addEventListener("click", function () {
      exportReport(kind, this);
    });
  });
  initFilterListeners("attendance", loadAttendance);
  initFilterListeners("violation", loadViolations);
  document.getElementById("current-year").textContent = String(new Date().getFullYear());
  document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
    weekday: "long", day: "numeric", month: "long",
  }).format(new Date());
  setDefaultDates("attendance");
  setDefaultDates("violation");

  requestJson("api/session.php")
    .then(function (data) {
      const user = data.user;
      if (!user || !allowedRoles.includes(String(user.role || "").toLowerCase())) {
        window.location.replace(user ? "dashboard.html" : "index.html");
        throw new Error("Akun ini tidak memiliki akses ke laporan.");
      }
      setUser(user);
      return loadAttendance(false);
    })
    .catch(function (error) {
      if (error.message !== "Akun ini tidak memiliki akses ke laporan.") {
        showMessage(error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    });
});
