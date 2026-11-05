"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const tableBody = document.getElementById("violation-table-body");
  const pageMessage = document.getElementById("violation-message");
  const recordDialog = document.getElementById("violation-dialog");
  const recordForm = document.getElementById("violation-form");
  const recordDialogMessage = document.getElementById("violation-dialog-message");
  const typeDialog = document.getElementById("violation-types-dialog");
  const typeForm = document.getElementById("violation-type-form");
  const typesBody = document.getElementById("violation-types-body");
  const typesMessage = document.getElementById("types-message");
  const saveRecordButton = document.getElementById("save-violation-button");
  const saveTypeButton = document.getElementById("save-violation-type");
  const pageSize = document.getElementById("violation-page-size");
  const allowedRoles = ["admin", "musyrif", "supervisor", "mas'ul"];
  const followupLabels = { belum: "Belum diproses", diproses: "Diproses", selesai: "Selesai" };
  let userRole = "";
  let records = [];
  let students = [];
  let types = [];
  let total = 0;
  let offset = 0;
  let loading = false;
  let savingRecord = false;
  let savingType = false;
  let typePreferenceChanged = false;
  let searchTimer = null;
  let requestSequence = 0;

  function localDateValue(date) {
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0")
      + "-" + String(date.getDate()).padStart(2, "0");
  }

  function showMessage(element, text, state) {
    element.textContent = text;
    element.dataset.state = state || "error";
    element.hidden = !text;
  }

  function initials(name) {
    return String(name || "").trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase() || "?";
  }

  function formatDate(value) {
    const date = new Date(String(value || "") + "T12:00:00");
    if (Number.isNaN(date.getTime())) return String(value || "—");
    return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" }).format(date);
  }

  function formatNumber(value) {
    return new Intl.NumberFormat("id-ID").format(Number(value) || 0);
  }

  async function requestJson(url, options) {
    const response = await fetch(url, Object.assign({ credentials: "same-origin" }, options || {}));
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

  function setTableState(text) {
    tableBody.replaceChildren();
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.className = "violation-table-state";
    cell.colSpan = 6;
    cell.textContent = text;
    row.appendChild(cell);
    tableBody.appendChild(row);
  }

  function appendCell(row, text, label, className) {
    const cell = document.createElement("td");
    cell.textContent = text;
    cell.dataset.label = label;
    if (className) cell.className = className;
    row.appendChild(cell);
    return cell;
  }

  function makeAction(label, action, id, destructive) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "violation-row-action" + (destructive ? " delete" : "");
    button.textContent = label;
    button.dataset.action = action;
    button.dataset.id = String(id);
    return button;
  }

  function fillCohortOptions(cohorts) {
    const select = document.getElementById("violation-cohort");
    const previous = select.value;
    select.replaceChildren();
    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = "Semua angkatan";
    select.appendChild(allOption);
    const collator = new Intl.Collator("id", { numeric: true, sensitivity: "base" });
    (Array.isArray(cohorts) ? cohorts.slice() : []).sort(function (first, second) {
      return collator.compare(second, first);
    }).forEach(function (cohort) {
      const option = document.createElement("option");
      option.value = cohort;
      option.textContent = "Angkatan " + cohort;
      select.appendChild(option);
    });
    if (Array.from(select.options).some(function (option) { return option.value === previous; })) {
      select.value = previous;
    }
  }

  function fillStudentOptions(selectedId) {
    const select = document.getElementById("violation-student");
    select.replaceChildren();
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = students.length ? "Pilih santri" : "Belum ada data santri";
    select.appendChild(placeholder);
    students.forEach(function (student) {
      const option = document.createElement("option");
      option.value = String(student.id);
      option.textContent = student.nama + (student.no_ktm ? " · " + student.no_ktm : "");
      select.appendChild(option);
    });
    select.value = selectedId ? String(selectedId) : "";
    select.disabled = students.length === 0;
  }

  function fillTypeOptions(selectedId) {
    const select = document.getElementById("violation-type");
    const activeTypes = types.filter(function (type) { return type.is_active; });
    select.replaceChildren();
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = activeTypes.length ? "Pilih jenis" : "Belum ada jenis aktif";
    select.appendChild(placeholder);
    activeTypes.forEach(function (type) {
      const option = document.createElement("option");
      option.value = String(type.id);
      option.textContent = type.nama + " · " + formatNumber(type.poin_default) + " poin";
      select.appendChild(option);
    });
    if (selectedId && !activeTypes.some(function (type) { return Number(type.id) === Number(selectedId); })) {
      const record = records.find(function (item) { return Number(item.id) === Number(document.getElementById("violation-id").value); });
      if (record && Number(record.jenis_pelanggaran_id) === Number(selectedId)) {
        const inactiveOption = document.createElement("option");
        inactiveOption.value = String(selectedId);
        inactiveOption.textContent = record.jenis_pelanggaran + " · nonaktif (riwayat)";
        select.appendChild(inactiveOption);
      }
    }
    select.value = selectedId ? String(selectedId) : "";
    select.disabled = activeTypes.length === 0 && !selectedId;
  }

  function renderSummary(summary) {
    const data = summary || {};
    document.getElementById("violation-total").textContent = formatNumber(data.total);
    document.getElementById("violation-pending").textContent = formatNumber(data.belum);
    document.getElementById("violation-in-progress").textContent = formatNumber(data.diproses);
    document.getElementById("violation-completed").textContent = formatNumber(data.selesai);
    document.getElementById("violation-points").textContent = formatNumber(data.poin);
    document.getElementById("violation-total-count").textContent = formatNumber(data.total);
  }

  function renderRecords() {
    if (!records.length) {
      setTableState(total ? "Tidak ada catatan pada halaman ini." : "Belum ada catatan pelanggaran sesuai filter.");
      return;
    }
    tableBody.replaceChildren();
    records.forEach(function (record) {
      const row = document.createElement("tr");
      appendCell(row, formatDate(record.tanggal), "Tanggal", "violation-date-cell");
      const studentCell = appendCell(row, "", "Santri", "violation-student-cell");
      const studentName = document.createElement("span");
      studentName.className = "violation-student-name";
      studentName.textContent = record.nama_santri || "Santri";
      studentCell.appendChild(studentName);
      const studentMeta = document.createElement("span");
      studentMeta.className = "violation-subtext";
      studentMeta.textContent = [record.no_ktm ? "NIM " + record.no_ktm : "", record.angkatan ? "Angkatan " + record.angkatan : ""]
        .filter(Boolean).join(" · ") || "Data santri";
      studentCell.appendChild(studentMeta);

      const typeCell = appendCell(row, "", "Jenis & Poin");
      const typeName = document.createElement("span");
      typeName.className = "violation-type-name";
      typeName.textContent = record.jenis_pelanggaran || "Jenis lain";
      const points = document.createElement("span");
      points.className = "violation-points";
      points.textContent = formatNumber(record.poin) + " poin";
      typeCell.append(typeName, points);

      const noteCell = appendCell(row, "", "Catatan", "violation-note-cell");
      const note = document.createElement("span");
      note.textContent = record.keterangan || "Tidak ada keterangan tambahan.";
      noteCell.appendChild(note);
      if (record.catatan_bk) {
        const internalNote = document.createElement("span");
        internalNote.className = "violation-subtext";
        internalNote.textContent = "Tindak lanjut: " + record.catatan_bk;
        noteCell.appendChild(internalNote);
      }

      const statusCell = appendCell(row, "", "Tindak lanjut");
      const status = document.createElement("span");
      status.className = "violation-status";
      status.dataset.status = record.status_tindak_lanjut;
      status.textContent = followupLabels[record.status_tindak_lanjut] || "Belum diproses";
      statusCell.appendChild(status);

      const actionCell = appendCell(row, "", "Aksi", "violation-actions-cell");
      actionCell.appendChild(makeAction("Edit", "edit", record.id, false));
      if (userRole === "admin") {
        actionCell.appendChild(makeAction("Hapus", "delete", record.id, true));
      }
      tableBody.appendChild(row);
    });
  }

  function updatePagination() {
    const pageNumber = Math.floor(offset / Number(pageSize.value)) + 1;
    document.getElementById("violation-page-label").textContent = "Halaman " + formatNumber(pageNumber);
    document.getElementById("violation-result-count").textContent = formatNumber(records.length);
    document.getElementById("violation-prev-page").disabled = loading || offset <= 0;
    document.getElementById("violation-next-page").disabled = loading || offset + records.length >= total;
  }

  function renderTypeManager() {
    if (!types.length) {
      typesBody.replaceChildren();
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.className = "violation-table-state";
      cell.colSpan = 4;
      cell.textContent = "Belum ada jenis. Tambahkan jenis pelanggaran terlebih dahulu.";
      row.appendChild(cell);
      typesBody.appendChild(row);
      return;
    }
    typesBody.replaceChildren();
    types.forEach(function (type) {
      const row = document.createElement("tr");
      const nameCell = appendCell(row, type.nama, "Jenis");
      const pointsCell = appendCell(row, formatNumber(type.poin_default), "Poin default");
      const statusCell = appendCell(row, type.is_active ? "Aktif" : "Nonaktif", "Status");
      const actionsCell = appendCell(row, "", "Aksi");
      nameCell.className = "violation-student-name";
      pointsCell.textContent += " poin";
      statusCell.className = "violation-status";
      statusCell.dataset.status = type.is_active ? "selesai" : "belum";
      actionsCell.append(
        makeAction("Edit", "edit-type", type.id, false),
        makeAction(type.is_active ? "Nonaktifkan" : "Aktifkan", "toggle-type", type.id, !type.is_active)
      );
      typesBody.appendChild(row);
    });
  }

  async function loadTypes() {
    const query = userRole === "admin" ? "?include_inactive=1" : "";
    const data = await requestJson("api/get-jenis-pelanggaran.php" + query);
    types = data.jenis_pelanggaran;
    fillTypeOptions();
    renderTypeManager();
    document.getElementById("add-violation-button").disabled = students.length === 0 || !types.some(function (type) { return type.is_active; });
  }

  function buildReportUrl() {
    const params = new URLSearchParams({
      limit: pageSize.value,
      offset: String(offset),
    });
    const query = document.getElementById("violation-search").value.trim();
    const from = document.getElementById("violation-from").value;
    const to = document.getElementById("violation-to").value;
    const cohort = document.getElementById("violation-cohort").value;
    const status = document.getElementById("violation-followup-filter").value;
    if (query) params.set("q", query);
    if (from) params.set("dari", from);
    if (to) params.set("sampai", to);
    if (cohort) params.set("angkatan", cohort);
    if (status) params.set("status", status);
    return "api/get-laporan-pelanggaran.php?" + params.toString();
  }

  async function loadRecords(resetOffset) {
    if (resetOffset) offset = 0;
    const sequence = ++requestSequence;
    loading = true;
    updatePagination();
    setTableState("Memuat data pelanggaran...");
    try {
      const data = await requestJson(buildReportUrl());
      if (sequence !== requestSequence) return false;
      showMessage(pageMessage, "", "");
      records = data.pelanggaran;
      total = Number(data.ringkasan.total) || 0;
      students = data.santri.map(function (student) {
        student.id = Number(student.id);
        return student;
      });
      renderSummary(data.ringkasan);
      fillCohortOptions(data.angkatan);
      fillStudentOptions();
      renderRecords();
      updatePagination();
      return true;
    } catch (error) {
      if (sequence !== requestSequence) return false;
      records = [];
      total = 0;
      renderSummary({});
      setTableState(error.message || "Data pelanggaran gagal dimuat.");
      showMessage(pageMessage, error.message || "Data pelanggaran gagal dimuat.", "error");
      updatePagination();
      return false;
    } finally {
      if (sequence === requestSequence) loading = false;
      updatePagination();
    }
  }

  function resetRecordForm() {
    recordForm.reset();
    document.getElementById("violation-id").value = "";
    const today = localDateValue(new Date());
    document.getElementById("violation-date").value = today;
    document.getElementById("violation-date").max = today;
    document.getElementById("violation-followup").value = "belum";
    document.getElementById("violation-counseling-note").value = "";
    document.getElementById("violation-counseling-note").disabled = true;
    document.getElementById("violation-points-input").value = "1";
    typePreferenceChanged = false;
    fillStudentOptions();
    fillTypeOptions();
    showMessage(recordDialogMessage, "", "");
  }

  function openCreateRecord() {
    resetRecordForm();
    document.getElementById("violation-dialog-title").textContent = "Catat Pelanggaran";
    saveRecordButton.textContent = "Simpan catatan";
    if (!types.some(function (type) { return type.is_active; })) {
      showMessage(recordDialogMessage, "Belum ada jenis pelanggaran aktif. Minta admin menambahkan jenis terlebih dahulu.", "error");
    }
    recordDialog.showModal();
    document.getElementById("violation-student").focus();
  }

  function openEditRecord(id) {
    const record = records.find(function (item) { return Number(item.id) === Number(id); });
    if (!record) {
      showMessage(pageMessage, "Catatan tidak ditemukan. Muat ulang daftar dan coba lagi.", "error");
      return;
    }
    resetRecordForm();
    document.getElementById("violation-id").value = String(record.id);
    document.getElementById("violation-dialog-title").textContent = "Edit Catatan Pelanggaran";
    saveRecordButton.textContent = "Simpan perubahan";
    document.getElementById("violation-date").value = record.tanggal;
    document.getElementById("violation-student").value = String(record.santri_id);
    document.getElementById("violation-points-input").value = String(record.poin);
    document.getElementById("violation-description").value = record.keterangan || "";
    document.getElementById("violation-followup").value = record.status_tindak_lanjut || "belum";
    document.getElementById("violation-counseling-note").value = record.catatan_bk || "";
    document.getElementById("violation-counseling-note").disabled = record.status_tindak_lanjut === "belum";
    fillTypeOptions(record.jenis_pelanggaran_id);
    recordDialog.showModal();
    document.getElementById("violation-student").focus();
  }

  async function saveRecord(event) {
    event.preventDefault();
    if (savingRecord) return;
    const id = document.getElementById("violation-id").value;
    const status = document.getElementById("violation-followup").value;
    const payload = {
      santri_id: Number(document.getElementById("violation-student").value),
      jenis_pelanggaran_id: Number(document.getElementById("violation-type").value),
      tanggal: document.getElementById("violation-date").value,
      poin: Number(document.getElementById("violation-points-input").value),
      keterangan: document.getElementById("violation-description").value.trim(),
      status_tindak_lanjut: status,
      catatan_bk: status === "belum" ? "" : document.getElementById("violation-counseling-note").value.trim(),
    };
    if (id) payload.id = Number(id);
    if (!payload.jenis_pelanggaran_id) {
      showMessage(recordDialogMessage, "Pilih jenis pelanggaran yang aktif.", "error");
      return;
    }

    savingRecord = true;
    saveRecordButton.disabled = true;
    saveRecordButton.textContent = "Menyimpan...";
    showMessage(recordDialogMessage, "", "");
    try {
      await requestJson("api/save-pelanggaran.php", {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      recordDialog.close();
      showMessage(pageMessage, id ? "Catatan pelanggaran berhasil diperbarui." : "Catatan pelanggaran berhasil disimpan.", "success");
      await loadRecords(true);
    } catch (error) {
      showMessage(recordDialogMessage, error.message || "Catatan pelanggaran gagal disimpan.", "error");
    } finally {
      savingRecord = false;
      saveRecordButton.disabled = false;
      saveRecordButton.textContent = id ? "Simpan perubahan" : "Simpan catatan";
    }
  }

  async function deleteRecord(id) {
    const record = records.find(function (item) { return Number(item.id) === Number(id); });
    if (!record || userRole !== "admin") return;
    const confirmed = window.confirm(
      "Hapus catatan pelanggaran " + record.nama_santri + " · " + record.jenis_pelanggaran
      + " tanggal " + formatDate(record.tanggal) + "? Tindakan ini tidak dapat dibatalkan."
    );
    if (!confirmed) return;
    try {
      await requestJson("api/delete-pelanggaran.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: Number(id) }),
      });
      showMessage(pageMessage, "Catatan pelanggaran berhasil dihapus.", "success");
      await loadRecords(true);
    } catch (error) {
      showMessage(pageMessage, error.message || "Catatan pelanggaran gagal dihapus.", "error");
    }
  }

  function resetTypeForm() {
    typeForm.reset();
    document.getElementById("violation-type-id").value = "";
    document.getElementById("violation-type-points").value = "1";
    saveTypeButton.textContent = "Tambah jenis";
    document.getElementById("cancel-violation-type-edit").hidden = true;
  }

  function openTypeEditor(type) {
    document.getElementById("violation-type-id").value = String(type.id);
    document.getElementById("violation-type-name").value = type.nama;
    document.getElementById("violation-type-points").value = String(type.poin_default);
    saveTypeButton.textContent = "Simpan perubahan";
    document.getElementById("cancel-violation-type-edit").hidden = false;
    document.getElementById("violation-type-name").focus();
  }

  async function loadTypeManager() {
    try {
      const data = await requestJson("api/get-jenis-pelanggaran.php?include_inactive=1");
      types = data.jenis_pelanggaran;
      fillTypeOptions();
      renderTypeManager();
      document.getElementById("add-violation-button").disabled = students.length === 0
        || !types.some(function (type) { return type.is_active; });
    } catch (error) {
      showMessage(typesMessage, error.message || "Jenis pelanggaran gagal dimuat.", "error");
    }
  }

  async function saveType(event) {
    event.preventDefault();
    if (savingType) return;
    const id = document.getElementById("violation-type-id").value;
    const payload = {
      nama: document.getElementById("violation-type-name").value.trim(),
      poin_default: Number(document.getElementById("violation-type-points").value),
    };
    if (id) payload.id = Number(id);
    savingType = true;
    saveTypeButton.disabled = true;
    showMessage(typesMessage, "", "");
    try {
      await requestJson("api/manage-jenis-pelanggaran.php", {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      resetTypeForm();
      showMessage(typesMessage, id ? "Jenis pelanggaran berhasil diperbarui." : "Jenis pelanggaran berhasil ditambahkan.", "success");
      await loadTypeManager();
      await loadRecords(false);
    } catch (error) {
      showMessage(typesMessage, error.message || "Jenis pelanggaran gagal disimpan.", "error");
    } finally {
      savingType = false;
      saveTypeButton.disabled = false;
    }
  }

  async function toggleType(id) {
    const type = types.find(function (item) { return Number(item.id) === Number(id); });
    if (!type) return;
    const active = !type.is_active;
    const action = active ? "Aktifkan kembali" : "Nonaktifkan";
    if (!window.confirm(action + " jenis pelanggaran \"" + type.nama + "\"? Catatan lama tetap tersimpan.")) return;
    try {
      await requestJson("api/manage-jenis-pelanggaran.php", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: Number(id), nama: type.nama, poin_default: type.poin_default, is_active: active }),
      });
      showMessage(typesMessage, "Status jenis pelanggaran berhasil diperbarui.", "success");
      await loadTypeManager();
      await loadRecords(false);
    } catch (error) {
      showMessage(typesMessage, error.message || "Status jenis pelanggaran gagal diperbarui.", "error");
    }
  }

  function closeMenu() {
    document.getElementById("sidebar").classList.remove("is-open");
    document.getElementById("sidebar-scrim").classList.remove("is-visible");
    document.getElementById("menu-button").setAttribute("aria-expanded", "false");
  }

  document.getElementById("add-violation-button").addEventListener("click", openCreateRecord);
  document.getElementById("close-violation-dialog").addEventListener("click", function () { recordDialog.close(); });
  document.getElementById("cancel-violation-dialog").addEventListener("click", function () { recordDialog.close(); });
  recordForm.addEventListener("submit", saveRecord);
  document.getElementById("violation-type").addEventListener("change", function () {
    if (typePreferenceChanged) return;
    const type = types.find(function (item) { return Number(item.id) === Number(this.value); }, this);
    if (type) document.getElementById("violation-points-input").value = String(type.poin_default);
  });
  document.getElementById("violation-points-input").addEventListener("input", function () {
    typePreferenceChanged = true;
  });
  document.getElementById("violation-followup").addEventListener("change", function () {
    const note = document.getElementById("violation-counseling-note");
    note.disabled = this.value === "belum";
    if (note.disabled) note.value = "";
  });
  tableBody.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    if (button.dataset.action === "edit") openEditRecord(button.dataset.id);
    if (button.dataset.action === "delete") deleteRecord(button.dataset.id);
  });

  document.getElementById("manage-types-button").addEventListener("click", async function () {
    resetTypeForm();
    showMessage(typesMessage, "", "");
    await loadTypeManager();
    typeDialog.showModal();
    document.getElementById("violation-type-name").focus();
  });
  document.getElementById("close-types-dialog").addEventListener("click", function () { typeDialog.close(); });
  document.getElementById("cancel-violation-type-edit").addEventListener("click", resetTypeForm);
  typeForm.addEventListener("submit", saveType);
  typesBody.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const type = types.find(function (item) { return Number(item.id) === Number(button.dataset.id); });
    if (!type) return;
    if (button.dataset.action === "edit-type") openTypeEditor(type);
    if (button.dataset.action === "toggle-type") toggleType(type.id);
  });

  document.getElementById("violation-search").addEventListener("input", function () {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function () { loadRecords(true); }, 250);
  });
  ["violation-from", "violation-to", "violation-cohort", "violation-followup-filter", "violation-page-size"]
    .forEach(function (id) {
      document.getElementById(id).addEventListener("change", function () {
        if (id === "violation-page-size") offset = 0;
        loadRecords(true);
      });
    });
  document.getElementById("clear-violation-filters").addEventListener("click", function () {
    document.getElementById("violation-search").value = "";
    document.getElementById("violation-cohort").value = "";
    document.getElementById("violation-followup-filter").value = "";
    document.getElementById("violation-page-size").value = "50";
    setDefaultDateRange();
    loadRecords(true);
  });
  document.getElementById("violation-prev-page").addEventListener("click", function () {
    offset = Math.max(0, offset - Number(pageSize.value));
    loadRecords(false);
  });
  document.getElementById("violation-next-page").addEventListener("click", function () {
    offset += Number(pageSize.value);
    loadRecords(false);
  });
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
      showMessage(pageMessage, error.message || "Gagal keluar. Silakan coba lagi.", "error");
      this.disabled = false;
    }
  });

  function setDefaultDateRange() {
    const today = new Date();
    document.getElementById("violation-from").value = today.getFullYear() + "-"
      + String(today.getMonth() + 1).padStart(2, "0") + "-01";
    document.getElementById("violation-to").value = localDateValue(today);
  }

  async function initialize() {
    const today = new Date();
    document.getElementById("current-year").textContent = String(today.getFullYear());
    document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
      weekday: "long", day: "numeric", month: "long",
    }).format(today);
    setDefaultDateRange();

    try {
      const session = await requestJson("api/session.php");
      const user = session.user;
      if (!user) {
        window.location.replace("index.html");
        return;
      }
      userRole = String(user.role || "").toLowerCase();
      if (!allowedRoles.includes(userRole)) {
        window.location.replace("dashboard.html");
        return;
      }
      const name = user.name || user.email || "Petugas";
      document.getElementById("sidebar-name").textContent = name;
      document.getElementById("sidebar-role").textContent = userRole;
      document.getElementById("topbar-name").textContent = name;
      document.getElementById("sidebar-avatar").textContent = initials(name);
      document.getElementById("top-avatar").textContent = initials(name);
      document.getElementById("manage-types-button").hidden = userRole !== "admin";
      await loadRecords(true);
      await loadTypes();
    } catch (error) {
      if (error.message !== "Sesi login berakhir.") {
        showMessage(pageMessage, error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    }
  }

  initialize();
});
