"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const allowedRoles = ["admin", "kajur", "musyrif", "supervisor", "mas'ul"];
  const pageSize = 50;
  const state = { offset: 0, total: 0, loading: false, sequence: 0, rows: [] };
  let searchTimer = null;

  function localDateValue(date) {
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0")
      + "-" + String(date.getDate()).padStart(2, "0");
  }

  function showMessage(text, kind) {
    const message = document.getElementById("activity-report-message");
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

  function params(offset, limit) {
    const query = new URLSearchParams({ limit: String(limit || pageSize), offset: String(offset || 0) });
    const values = {
      dari: document.getElementById("activity-report-from").value,
      sampai: document.getElementById("activity-report-to").value,
      kegiatan_id: document.getElementById("activity-report-activity").value,
      angkatan: document.getElementById("activity-report-cohort").value,
      status: document.getElementById("activity-report-status").value,
      q: document.getElementById("activity-report-search").value.trim(),
    };
    Object.keys(values).forEach(function (key) {
      if (values[key]) query.set(key, values[key]);
    });
    return query;
  }

  function fillOptions(id, values, placeholder, prefix) {
    const select = document.getElementById(id);
    const previous = select.value;
    select.replaceChildren();
    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = placeholder;
    select.appendChild(allOption);
    (Array.isArray(values) ? values : []).forEach(function (item) {
      const option = document.createElement("option");
      if (typeof item === "object") {
        option.value = String(item.id);
        option.textContent = (prefix || "") + String(item.nama_kegiatan);
      } else {
        option.value = String(item);
        option.textContent = (prefix || "") + String(item);
      }
      select.appendChild(option);
    });
    if (Array.from(select.options).some(function (option) { return option.value === previous; })) {
      select.value = previous;
    }
  }

  function appendCell(row, value) {
    const cell = document.createElement("td");
    cell.textContent = String(value == null || value === "" ? "—" : value);
    row.appendChild(cell);
    return cell;
  }

  function setTableState(message) {
    const body = document.getElementById("activity-report-table");
    body.replaceChildren();
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.className = "activity-report-table-state";
    cell.colSpan = 9;
    cell.textContent = message;
    row.appendChild(cell);
    body.appendChild(row);
  }

  function render(data) {
    const summary = data.ringkasan || {};
    const total = Number(summary.total) || 0;
    const present = Number(summary.hadir) || 0;
    document.getElementById("session-count").textContent = formatNumber(summary.sesi);
    document.getElementById("record-count").textContent = formatNumber(total);
    document.getElementById("present-count").textContent = formatNumber(present);
    document.getElementById("present-rate").textContent = (total ? (present / total * 100).toFixed(1) : "0") + "%";
    state.total = Number(data.total) || 0;
    state.rows = Array.isArray(data.laporan) ? data.laporan : [];
    document.getElementById("activity-report-total").textContent = formatNumber(state.total);
    fillOptions("activity-report-activity", data.kegiatan, "Semua kegiatan");
    fillOptions("activity-report-cohort", data.angkatan, "Semua angkatan", "Angkatan ");

    const body = document.getElementById("activity-report-table");
    if (!state.rows.length) {
      setTableState(state.total ? "Tidak ada sesi pada halaman ini." : "Belum ada data kegiatan sesuai filter.");
      return;
    }
    body.replaceChildren();
    state.rows.forEach(function (session) {
      const row = document.createElement("tr");
      appendCell(row, formatDate(session.tanggal));
      appendCell(row, session.nama_kegiatan);
      appendCell(row, session.kategori);
      appendCell(row, formatNumber(session.total));
      appendCell(row, formatNumber(session.hadir));
      appendCell(row, formatNumber(session.izin));
      appendCell(row, formatNumber(session.sakit));
      appendCell(row, formatNumber(session.alpa));
      appendCell(row, (Number(session.persentase_hadir) || 0).toFixed(1) + "%");
      body.appendChild(row);
    });
  }

  function updatePagination() {
    document.getElementById("activity-report-page").textContent = "Halaman " + formatNumber(Math.floor(state.offset / pageSize) + 1);
    document.getElementById("activity-report-prev").disabled = state.loading || state.offset <= 0;
    document.getElementById("activity-report-next").disabled = state.loading || state.offset + state.rows.length >= state.total;
  }

  async function load(resetOffset) {
    if (resetOffset) state.offset = 0;
    const sequence = ++state.sequence;
    state.loading = true;
    updatePagination();
    setTableState("Memuat laporan kegiatan...");
    try {
      const data = await requestJson("api/get-laporan-kegiatan.php?" + params(state.offset).toString());
      if (sequence !== state.sequence) return false;
      render(data);
      showMessage("", "");
      return true;
    } catch (error) {
      if (sequence !== state.sequence) return false;
      state.rows = [];
      state.total = 0;
      setTableState(error.message || "Laporan kegiatan gagal dimuat.");
      showMessage(error.message || "Laporan kegiatan gagal dimuat.", "error");
      return false;
    } finally {
      if (sequence === state.sequence) state.loading = false;
      updatePagination();
    }
  }

  function csvSafe(value) {
    let text = String(value == null ? "" : value);
    if (/^[\s]*[=+\-@]/.test(text)) text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
  }

  async function exportCsv(button) {
    button.disabled = true;
    showMessage("Menyiapkan berkas CSV, mohon tunggu...", "success");
    try {
      const filterParams = params(0, 100);
      const allRows = [];
      let offset = 0;
      let total = 0;
      do {
        const requestParams = new URLSearchParams(filterParams);
        requestParams.set("limit", "100");
        requestParams.set("offset", String(offset));
        const data = await requestJson("api/get-laporan-kegiatan.php?" + requestParams.toString());
        const page = Array.isArray(data.laporan) ? data.laporan : [];
        total = Number(data.total) || 0;
        allRows.push.apply(allRows, page);
        offset += page.length;
        if (!page.length || offset > 100000) break;
      } while (offset < total);
      if (offset < total) {
        throw new Error("Ekspor melebihi batas laporan yang dapat dimuat sekaligus. Persempit rentang tanggal.");
      }
      const headers = ["Tanggal", "Nama Kegiatan", "Kategori", "Total Catatan", "Hadir", "Izin", "Sakit", "Alpa", "Persentase Hadir"];
      const rows = allRows.map(function (session) {
        return [session.tanggal, session.nama_kegiatan, session.kategori, session.total, session.hadir, session.izin, session.sakit, session.alpa, (Number(session.persentase_hadir) || 0).toFixed(1) + "%"];
      });
      const csv = "\uFEFF" + [headers].concat(rows).map(function (row) {
        return row.map(csvSafe).join(",");
      }).join("\r\n");
      const link = document.createElement("a");
      link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
      link.download = "laporan-kegiatan-" + localDateValue(new Date()) + ".csv";
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
      showMessage(allRows.length ? "CSV berhasil diunduh (" + formatNumber(allRows.length) + " sesi)." : "Tidak ada data untuk diekspor.", "success");
    } catch (error) {
      showMessage(error.message || "Berkas CSV gagal disiapkan.", "error");
    } finally {
      button.disabled = false;
    }
  }

  const filterIds = ["activity-report-from", "activity-report-to", "activity-report-activity", "activity-report-cohort", "activity-report-status"];
  filterIds.forEach(function (id) {
    document.getElementById(id).addEventListener("change", function () { load(true); });
  });
  document.getElementById("activity-report-search").addEventListener("input", function () {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function () { load(true); }, 250);
  });
  document.getElementById("activity-report-clear").addEventListener("click", function () {
    filterIds.forEach(function (id) {
      document.getElementById(id).value = "";
    });
    document.getElementById("activity-report-search").value = "";
    load(true);
  });
  document.getElementById("activity-report-prev").addEventListener("click", function () {
    state.offset = Math.max(0, state.offset - pageSize);
    load(false);
  });
  document.getElementById("activity-report-next").addEventListener("click", function () {
    state.offset += pageSize;
    load(false);
  });
  document.getElementById("activity-report-export").addEventListener("click", function () {
    exportCsv(this);
  });

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

  const now = new Date();
  document.getElementById("current-year").textContent = String(now.getFullYear());
  document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
    weekday: "long", day: "numeric", month: "long",
  }).format(now);
  document.getElementById("activity-report-from").value = now.getFullYear() + "-"
    + String(now.getMonth() + 1).padStart(2, "0") + "-01";
  document.getElementById("activity-report-to").value = localDateValue(now);

  requestJson("api/session.php")
    .then(function (data) {
      const user = data.user;
      if (!user || !allowedRoles.includes(String(user.role || "").toLowerCase())) {
        window.location.replace(user ? "dashboard.html" : "index.html");
        throw new Error("Akun ini tidak memiliki akses ke laporan kegiatan.");
      }
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
      return load(false);
    })
    .catch(function (error) {
      if (error.message !== "Akun ini tidak memiliki akses ke laporan kegiatan.") {
        showMessage(error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    });
});
