"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const dateInput = document.getElementById("attendance-date");
  const activitySelect = document.getElementById("attendance-activity");
  const tableBody = document.getElementById("attendance-table-body");
  const pageMessage = document.getElementById("attendance-message");
  const saveButton = document.getElementById("save-attendance-button");
  const bulkButton = document.getElementById("mark-visible-present");
  const statusOptions = [
    ["", "Belum diisi"],
    ["hadir", "Hadir"],
    ["izin", "Izin"],
    ["sakit", "Sakit"],
    ["alpa", "Alpa"],
  ];
  const allowedRoles = ["admin", "musyrif", "mas'ul"];
  let students = [];
  let activities = [];
  let drafts = new Map();
  let loading = false;
  let saving = false;
  let loadSequence = 0;
  let loadedDate = "";
  let loadedActivityId = "";

  function localDateValue(date) {
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0")
      + "-" + String(date.getDate()).padStart(2, "0");
  }

  function showMessage(text, state) {
    pageMessage.textContent = text;
    pageMessage.dataset.state = state || "error";
    pageMessage.hidden = !text;
  }

  function initials(name) {
    return String(name || "").trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase() || "?";
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
    cell.className = "attendance-table-state";
    cell.colSpan = 5;
    cell.textContent = text;
    row.appendChild(cell);
    tableBody.appendChild(row);
  }

  function appendCell(row, text, label, className) {
    const cell = document.createElement("td");
    cell.textContent = text;
    if (label) cell.dataset.label = label;
    if (className) cell.className = className;
    row.appendChild(cell);
    return cell;
  }

  function getVisibleStudents() {
    const query = document.getElementById("attendance-search").value.trim().toLocaleLowerCase("id");
    const cohort = document.getElementById("attendance-cohort").value;
    return students.filter(function (student) {
      const matchesQuery = [student.nama, student.no_ktm]
        .some(function (value) { return String(value || "").toLocaleLowerCase("id").includes(query); });
      return matchesQuery && (!cohort || String(student.angkatan || "") === cohort);
    });
  }

  function getRowValues(row) {
    return {
      status: row.querySelector(".attendance-control-select").value,
      keterangan: row.querySelector(".attendance-note-input").value.trim(),
    };
  }

  function updateSummary() {
    const counts = { hadir: 0, izin: 0, sakit: 0, alpa: 0, pending: 0 };
    if (activitySelect.value) {
      students.forEach(function (student) {
        const row = tableBody.querySelector('[data-student-id="' + student.id + '"]');
        const draft = drafts.get(Number(student.id));
        const status = row
          ? row.querySelector(".attendance-control-select").value
          : (draft ? draft.status : (student.attendance ? String(student.attendance.status || "") : ""));
        const countKey = status || "pending";
        counts[countKey] = (counts[countKey] || 0) + 1;
      });
    }
    document.getElementById("count-total").textContent = activitySelect.value ? String(students.length) : "0";
    document.getElementById("count-hadir").textContent = String(counts.hadir);
    document.getElementById("count-izin").textContent = String(counts.izin);
    document.getElementById("count-sakit").textContent = String(counts.sakit);
    document.getElementById("count-alpa").textContent = String(counts.alpa);
    document.getElementById("count-pending").textContent = String(counts.pending);
    document.getElementById("visible-student-count").textContent = activitySelect.value
      ? String(getVisibleStudents().length) : "0";
    updateButtons();
  }

  function getChangedRecords() {
    return students.reduce(function (changed, student) {
      const studentId = Number(student.id);
      const row = tableBody.querySelector('[data-student-id="' + studentId + '"]');
      const current = row
        ? getRowValues(row)
        : (drafts.get(studentId) || {
          status: student.attendance ? String(student.attendance.status || "") : "",
          keterangan: student.attendance ? String(student.attendance.keterangan || "").trim() : "",
        });
      if (row) drafts.set(studentId, current);
      const previousStatus = student.attendance ? String(student.attendance.status || "").toLowerCase() : "";
      const previousNote = student.attendance ? String(student.attendance.keterangan || "").trim() : "";
      const hasSavedRecord = previousStatus !== "";
      const isChanged = current.status !== previousStatus || current.keterangan !== previousNote;
      if (!isChanged) return changed;
      if (current.status === "" && !hasSavedRecord) return changed;
      changed.push({
        santri_id: studentId,
        status: current.status,
        keterangan: current.keterangan,
      });
      return changed;
    }, []);
  }

  function updateButtons() {
    const hasActivity = Boolean(activitySelect.value);
    saveButton.disabled = loading || saving || !hasActivity || getChangedRecords().length === 0;
    bulkButton.disabled = loading || saving || !hasActivity || getVisibleStudents().length === 0;
    dateInput.disabled = loading || saving;
    activitySelect.disabled = loading || saving || activities.length === 0;
  }

  function createStatusSelect(student, attendance, currentStatus) {
    const select = document.createElement("select");
    select.className = "attendance-control-select";
    select.setAttribute("aria-label", "Status kehadiran " + student.nama);
    const savedStatus = attendance ? String(attendance.status || "").toLowerCase() : "";
    statusOptions.forEach(function (option) {
      const element = document.createElement("option");
      element.value = option[0];
      element.textContent = option[1];
      select.appendChild(element);
    });
    const displayedStatus = currentStatus || savedStatus;
    if (displayedStatus && !statusOptions.some(function (option) { return option[0] === displayedStatus; })) {
      const legacyOption = document.createElement("option");
      legacyOption.value = displayedStatus;
      legacyOption.textContent = "Status lama: " + displayedStatus;
      select.appendChild(legacyOption);
    }
    select.value = displayedStatus;
    select.dataset.status = displayedStatus;
    return select;
  }

  function renderStudents() {
    const filtered = getVisibleStudents();
    document.getElementById("visible-student-count").textContent = String(filtered.length);
    if (!activitySelect.value) {
      setTableState("Pilih tanggal dan kegiatan untuk memuat catatan kehadiran.");
      updateSummary();
      return;
    }
    if (!filtered.length) {
      setTableState(students.length
        ? "Tidak ada santri yang cocok dengan pencarian atau filter angkatan."
        : "Belum ada data santri. Tambahkan data santri terlebih dahulu.");
      updateSummary();
      return;
    }

    tableBody.replaceChildren();
    filtered.forEach(function (student, index) {
      const attendance = student.attendance || null;
      const draft = drafts.get(Number(student.id));
      const status = draft ? draft.status : (attendance ? String(attendance.status || "").toLowerCase() : "");
      const note = draft ? draft.keterangan : (attendance ? String(attendance.keterangan || "") : "");
      const row = document.createElement("tr");
      row.dataset.studentId = String(student.id);
      appendCell(row, String(index + 1));
      const nameCell = appendCell(row, String(student.nama || ""), "Nama", "attendance-name-cell");
      if (student.no_ktm) {
        const badge = document.createElement("span");
        badge.className = "attendance-id-badge";
        badge.textContent = "NIM " + String(student.no_ktm);
        nameCell.appendChild(document.createElement("br"));
        nameCell.appendChild(badge);
      }
      appendCell(row, String(student.angkatan || "—"), "Angkatan");
      const statusCell = document.createElement("td");
      statusCell.dataset.label = "Status";
      statusCell.appendChild(createStatusSelect(student, attendance, status));
      row.appendChild(statusCell);
      const noteCell = document.createElement("td");
      noteCell.dataset.label = "Keterangan";
      const noteInput = document.createElement("input");
      noteInput.className = "attendance-note-input";
      noteInput.type = "text";
      noteInput.maxLength = 2000;
      noteInput.value = note;
      noteInput.disabled = !status;
      noteInput.placeholder = "Contoh: izin keluarga";
      noteInput.setAttribute("aria-label", "Keterangan kehadiran " + student.nama);
      noteCell.appendChild(noteInput);
      row.appendChild(noteCell);
      tableBody.appendChild(row);
    });
    updateSummary();
  }

  function fillActivityOptions() {
    activitySelect.replaceChildren();
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = activities.length ? "Pilih kegiatan" : "Belum ada kegiatan aktif";
    activitySelect.appendChild(placeholder);
    activities.forEach(function (activity) {
      const option = document.createElement("option");
      option.value = String(activity.id);
      option.textContent = activity.nama_kegiatan;
      activitySelect.appendChild(option);
    });
    activitySelect.disabled = activities.length === 0;
  }

  function fillCohortOptions() {
    const select = document.getElementById("attendance-cohort");
    select.replaceChildren();
    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = "Semua angkatan";
    select.appendChild(allOption);
    const collator = new Intl.Collator("id", { numeric: true, sensitivity: "base" });
    const cohorts = Array.from(new Set(students.map(function (student) {
      return String(student.angkatan || "").trim();
    }).filter(Boolean))).sort(function (first, second) {
      return collator.compare(first, second);
    });
    cohorts.forEach(function (cohort) {
      const option = document.createElement("option");
      option.value = cohort;
      option.textContent = "Angkatan " + cohort;
      select.appendChild(option);
    });
  }

  async function loadAttendance(clearDrafts) {
    const date = dateInput.value;
    if (!date) return;
    if (clearDrafts) drafts.clear();
    const sequence = ++loadSequence;
    loading = true;
    updateButtons();
    showMessage("", "");
    setTableState("Memuat daftar santri dan catatan...");
    try {
      const params = new URLSearchParams({ tanggal: date });
      if (activitySelect.value) params.set("kegiatan_id", activitySelect.value);
      const data = await requestJson("api/prepare-absensi.php?" + params.toString());
      if (sequence !== loadSequence) return false;
      activities = data.kegiatan;
      students = data.santri;
      const selectedActivityId = activitySelect.value;
      fillActivityOptions();
      if (selectedActivityId && activities.some(function (item) { return String(item.id) === selectedActivityId; })) {
        activitySelect.value = selectedActivityId;
      }
      loadedDate = date;
      loadedActivityId = activitySelect.value;
      fillCohortOptions();
      renderStudents();
      return true;
    } catch (error) {
      if (sequence !== loadSequence) return false;
      students = [];
      setTableState(error.message || "Daftar kehadiran gagal dimuat.");
      showMessage(error.message || "Daftar kehadiran gagal dimuat.", "error");
      updateSummary();
      return false;
    } finally {
      if (sequence === loadSequence) {
        loading = false;
        updateButtons();
      }
    }
  }

  async function saveAttendance() {
    const records = getChangedRecords();
    if (!records.length || saving) return;
    saving = true;
    updateButtons();
    showMessage("", "");
    saveButton.textContent = "Menyimpan...";
    try {
      const result = await requestJson("api/save-absensi-input.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          tanggal: dateInput.value,
          kegiatan_id: Number(activitySelect.value),
          records: records,
        }),
      });
      const reloaded = await loadAttendance(true);
      if (reloaded) {
        showMessage(result.message || "Catatan kehadiran berhasil disimpan.", "success");
      } else {
        showMessage("Catatan tersimpan, tetapi daftar belum dapat diperbarui. Muat ulang halaman untuk memastikan isian terbaru.", "error");
      }
    } catch (error) {
      showMessage(error.message || "Catatan kehadiran gagal disimpan.", "error");
    } finally {
      saving = false;
      saveButton.textContent = "Simpan perubahan";
      updateButtons();
    }
  }

  function applyVisibleAsPresent() {
    const visible = getVisibleStudents();
    if (!visible.length || !activitySelect.value) return;
    const activity = activities.find(function (item) { return String(item.id) === activitySelect.value; });
    const confirmed = window.confirm(
      "Tandai " + visible.length + " santri yang terlihat sebagai hadir untuk "
      + (activity ? activity.nama_kegiatan : "kegiatan ini") + " pada " + dateInput.value
      + "? Status yang sudah tercatat akan diganti."
    );
    if (!confirmed) return;
    visible.forEach(function (student) {
      const row = tableBody.querySelector('[data-student-id="' + student.id + '"]');
      if (!row) return;
      const select = row.querySelector(".attendance-control-select");
      select.value = "hadir";
      select.dataset.status = "hadir";
      row.querySelector(".attendance-note-input").disabled = false;
      drafts.set(Number(student.id), getRowValues(row));
    });
    updateSummary();
  }

  async function initialize() {
    const today = new Date();
    const todayValue = localDateValue(today);
    dateInput.value = todayValue;
    dateInput.max = todayValue;
    document.getElementById("current-year").textContent = String(today.getFullYear());
    document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
      weekday: "long", day: "numeric", month: "long",
    }).format(today);

    try {
      const session = await requestJson("api/session.php");
      const user = session.user;
      if (!user) {
        window.location.replace("index.html");
        return;
      }
      if (!allowedRoles.includes(String(user.role || "").toLowerCase())) {
        window.location.replace("dashboard.html");
        return;
      }
      const name = user.name || user.email || "Pengguna";
      document.getElementById("sidebar-name").textContent = name;
      document.getElementById("sidebar-role").textContent = String(user.role || "Akun");
      document.getElementById("topbar-name").textContent = name;
      document.getElementById("sidebar-avatar").textContent = initials(name);
      document.getElementById("top-avatar").textContent = initials(name);
      await loadAttendance();
    } catch (error) {
      if (error.message !== "Sesi login berakhir.") {
        showMessage(error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    }
  }

  tableBody.addEventListener("change", function (event) {
    if (!event.target.matches(".attendance-control-select")) return;
    const row = event.target.closest("tr");
    row.querySelector(".attendance-note-input").disabled = !event.target.value;
    drafts.set(Number(row.dataset.studentId), getRowValues(row));
    event.target.dataset.status = event.target.value;
    updateSummary();
  });
  tableBody.addEventListener("input", function (event) {
    if (!event.target.matches(".attendance-note-input")) return;
    const row = event.target.closest("tr");
    drafts.set(Number(row.dataset.studentId), getRowValues(row));
    updateButtons();
  });
  activitySelect.addEventListener("change", function () {
    if (getChangedRecords().length && !window.confirm("Perubahan belum disimpan. Ganti pilihan dan buang perubahan?")) {
      activitySelect.value = loadedActivityId;
      return;
    }
    loadAttendance(true);
  });
  dateInput.addEventListener("change", function () {
    if (!dateInput.value) {
      dateInput.value = loadedDate || localDateValue(new Date());
      return;
    }
    if (getChangedRecords().length && !window.confirm("Perubahan belum disimpan. Ganti tanggal dan buang perubahan?")) {
      dateInput.value = loadedDate;
      return;
    }
    loadAttendance(true);
  });
  document.getElementById("attendance-search").addEventListener("input", renderStudents);
  document.getElementById("attendance-cohort").addEventListener("change", renderStudents);
  saveButton.addEventListener("click", saveAttendance);
  bulkButton.addEventListener("click", applyVisibleAsPresent);

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

  initialize();
});
