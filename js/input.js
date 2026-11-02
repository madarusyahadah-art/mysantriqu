(function () {
  const elements = {
    activity: document.getElementById("kegiatan"),
    date: document.getElementById("tanggal"),
    sessionMessage: document.getElementById("session-message"),
    prepareButton: document.getElementById("prepare-session"),
    statusMessage: document.getElementById("status-message"),
    form: document.getElementById("attendance-form"),
    search: document.getElementById("cari"),
    results: document.getElementById("hasil"),
    reason: document.getElementById("alasan"),
    addButton: document.getElementById("add-santri"),
    count: document.getElementById("jml"),
    tableBody: document.getElementById("daftar"),
    clearButton: document.getElementById("clear-batch"),
    saveButton: document.getElementById("save-batch"),
  };
  const state = {
    santri: [],
    selectedSantri: null,
    savedAbsences: [],
    batch: [],
    sessionActive: false,
    canEdit: false,
    saving: false,
    attendanceRequestId: 0,
  };
  const statusLabels = {
    izin: "Izin",
    sakit: "Sakit",
    alpha: "Tanpa Keterangan (Alpha)",
    izin_menginap: "Izin Menginap",
  };

  function setStatus(message, isError) {
    elements.statusMessage.textContent = message || "";
    elements.statusMessage.classList.toggle("status-error", Boolean(isError));
  }

  async function requestJson(url, options) {
    const response = await fetch(url, Object.assign({ credentials: "same-origin" }, options || {}));
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || "Permintaan gagal diproses.");
    }
    return data;
  }

  function localDateString(date) {
    const localDate = new Date(date);
    localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
    return localDate.toISOString().slice(0, 10);
  }

  function santriId(santri) {
    return String(santri.santri_id ?? santri.id);
  }

  function isAlreadySaved(id) {
    return state.savedAbsences.some(function (item) {
      return String(item.santri_id) === String(id);
    });
  }

  function renderRows() {
    const rows = state.savedAbsences.map(function (item) {
      return { name: item.nama, status: item.status, saved: true };
    }).concat(state.batch.map(function (item) {
      return { name: item.nama, status: item.status, saved: false };
    }));

    elements.count.textContent = String(rows.length);
    elements.tableBody.replaceChildren();
    elements.saveButton.disabled = state.saving || state.batch.length === 0;
    elements.clearButton.disabled = state.saving || state.batch.length === 0;
    if (!rows.length) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 4;
      cell.className = "empty-state";
      cell.textContent = "Belum ada data ketidakhadiran";
      row.appendChild(cell);
      elements.tableBody.appendChild(row);
      return;
    }

    rows.forEach(function (item, index) {
      const row = document.createElement("tr");
      [String(index + 1), item.name || "Santri", statusLabels[item.status] || item.status].forEach(function (value) {
        const cell = document.createElement("td");
        cell.textContent = value || "-";
        row.appendChild(cell);
      });

      const actionCell = document.createElement("td");
      if (item.saved) {
        actionCell.textContent = "Tersimpan";
      } else {
        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "btn btn-danger";
        removeButton.textContent = "Hapus";
        removeButton.dataset.batchIndex = String(index - state.savedAbsences.length);
        removeButton.addEventListener("click", function () {
          const batchIndex = Number(removeButton.dataset.batchIndex);
          state.batch.splice(batchIndex, 1);
          renderRows();
          updateFormState();
        });
        actionCell.appendChild(removeButton);
      }
      row.appendChild(actionCell);
      elements.tableBody.appendChild(row);
    });

  }

  function updateFormState() {
    const activityDateSelected = Boolean(elements.activity.value && elements.date.value);
    const canRecord = activityDateSelected && state.sessionActive && state.canEdit;
    elements.form.hidden = !activityDateSelected;
    elements.search.disabled = !canRecord || state.saving;
    elements.reason.disabled = !canRecord || state.saving;
    elements.addButton.disabled = !canRecord || state.saving;
    elements.prepareButton.hidden = !activityDateSelected || state.sessionActive;
    elements.prepareButton.disabled = !activityDateSelected || !state.canEdit || state.saving;
    elements.activity.disabled = state.batch.length > 0 || state.saving;
    elements.date.disabled = state.batch.length > 0 || state.saving;
    renderRows();
  }

  async function loadActivities() {
    const data = await requestJson("api/get-kegiatan.php");
    if (!Array.isArray(data.kegiatan)) throw new Error("Daftar kegiatan tidak tersedia.");
    elements.activity.replaceChildren();
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "-- Pilih Kegiatan --";
    elements.activity.appendChild(placeholder);

    data.kegiatan.filter(function (activity) {
      return Number(activity.is_active) === 1;
    }).forEach(function (activity) {
      const option = document.createElement("option");
      option.value = String(activity.id);
      option.textContent = activity.nama_kegiatan + " (" + activity.frekuensi + ")";
      elements.activity.appendChild(option);
    });
  }

  async function loadAttendanceState() {
    const requestId = ++state.attendanceRequestId;
    const activityId = elements.activity.value;
    const date = elements.date.value;
    state.savedAbsences = [];
    state.sessionActive = false;
    state.canEdit = false;
    elements.results.replaceChildren();
    elements.results.hidden = true;

    if (!activityId || !date) {
      elements.sessionMessage.textContent = "Pilih kegiatan dan tanggal untuk melihat status sesi.";
      updateFormState();
      return;
    }

    setStatus("Memuat status absensi...");
    updateFormState();
    const query = new URLSearchParams({ kegiatan_id: activityId, tanggal: date });
    let data;
    try {
      data = await requestJson("api/get-absensi-hari-ini.php?" + query.toString());
    } catch (error) {
      if (requestId !== state.attendanceRequestId) return;
      throw error;
    }
    if (requestId !== state.attendanceRequestId) return;
    state.sessionActive = Boolean(data.session_active);
    state.canEdit = data.report ? Boolean(data.can_edit_report) : true;
    if (!Array.isArray(data.tidak_hadir)) throw new Error("Daftar ketidakhadiran tidak tersedia.");
    state.savedAbsences = data.tidak_hadir;

    if (state.sessionActive) {
      elements.sessionMessage.textContent = "Sesi absensi aktif. Tambahkan santri yang tidak hadir; status hadir santri lain tetap tercatat.";
    } else if (data.report) {
      elements.sessionMessage.textContent = data.can_edit_report
        ? "Laporan kegiatan sudah dikirim. Sesi absensi belum aktif."
        : "Laporan dikirim supervisor lain; hanya admin atau pengirimnya yang dapat mengubahnya.";
    } else {
      elements.sessionMessage.textContent = "Siapkan sesi terlebih dahulu agar status hadir dan tidak hadir tercatat lengkap.";
    }

    setStatus("");
    updateFormState();
  }

  async function loadSantri() {
    if (state.santri.length) return;
    const data = await requestJson("api/get-santri.php");
    if (!Array.isArray(data.santri)) throw new Error("Daftar santri tidak tersedia.");
    state.santri = data.santri;
  }

  function renderSearchResults(keyword) {
    elements.results.replaceChildren();
    if (keyword.length < 2) {
      elements.results.hidden = true;
      return;
    }

    const normalizedKeyword = keyword.toLocaleLowerCase("id");
    const savedIds = new Set(state.savedAbsences.map(function (item) { return String(item.santri_id); }));
    const queuedIds = new Set(state.batch.map(function (item) { return String(item.santri_id); }));
    const matches = state.santri.filter(function (item) {
      const name = String(item.nama || "");
      const identityNumber = String(item.no_ktm || item.nis || "");
      return (name.toLocaleLowerCase("id").includes(normalizedKeyword) || identityNumber.toLocaleLowerCase("id").includes(normalizedKeyword))
        && !savedIds.has(santriId(item))
        && !queuedIds.has(santriId(item));
    }).slice(0, 20);

    if (!matches.length) {
      const empty = document.createElement("div");
      empty.className = "search-item";
      empty.textContent = "Santri tidak ditemukan atau sudah tercatat tidak hadir";
      elements.results.appendChild(empty);
    } else {
      matches.forEach(function (item) {
        const option = document.createElement("button");
        option.type = "button";
        option.className = "search-item";
        option.setAttribute("role", "option");
        const name = document.createElement("span");
        name.textContent = item.nama || "Santri";
        const nis = document.createElement("small");
        nis.textContent = "No. KTM: " + (item.no_ktm || item.nis || "-");
        option.append(name, nis);
        option.addEventListener("click", function () {
          state.selectedSantri = item;
          elements.search.value = item.nama || "";
          elements.results.hidden = true;
          elements.results.replaceChildren();
        });
        elements.results.appendChild(option);
      });
    }
    elements.results.hidden = false;
  }

  function addSantri() {
    if (!state.sessionActive || !state.canEdit) {
      setStatus("Siapkan sesi absensi sebelum menambahkan ketidakhadiran.", true);
      return;
    }
    if (!state.selectedSantri) {
      setStatus("Pilih santri dari hasil pencarian terlebih dahulu.", true);
      return;
    }
    const id = santriId(state.selectedSantri);
    if (isAlreadySaved(id) || state.batch.some(function (item) { return String(item.santri_id) === id; })) {
      setStatus("Santri tersebut sudah tercatat tidak hadir.", true);
      return;
    }
    if (!elements.reason.value) {
      setStatus("Pilih status ketidakhadiran.", true);
      return;
    }

    state.batch.push({
      kegiatan_id: elements.activity.value,
      tanggal: elements.date.value,
      santri_id: id,
      nama: state.selectedSantri.nama,
      status: elements.reason.value,
    });
    state.selectedSantri = null;
    elements.search.value = "";
    elements.reason.value = "";
    elements.results.replaceChildren();
    elements.results.hidden = true;
    setStatus("");
    updateFormState();
    elements.search.focus();
  }

  async function prepareSession() {
    if (!elements.activity.value || !elements.date.value) return;
    if (!window.confirm("Tandai semua santri sebagai hadir untuk kegiatan dan tanggal ini? Gunakan hanya untuk sesi baru, bukan untuk merekonstruksi riwayat lama.")) return;

    elements.prepareButton.disabled = true;
    try {
      await requestJson("api/prepare-absensi.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ kegiatan_id: elements.activity.value, tanggal: elements.date.value }),
      });
      await loadAttendanceState();
      if (!state.sessionActive) throw new Error("Sesi absensi belum aktif setelah persiapan.");
      setStatus("Sesi absensi berhasil disiapkan.");
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      updateFormState();
    }
  }

  async function saveBatch() {
    if (!state.batch.length || state.saving) return;
    state.saving = true;
    setStatus("Menyimpan data ketidakhadiran...");
    updateFormState();

    const failed = [];
    let savedCount = 0;
    for (const item of state.batch) {
      try {
        await requestJson("api/save-absensi-input.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            kegiatan_id: item.kegiatan_id,
            tanggal: item.tanggal,
            santri_id: item.santri_id,
            status: item.status,
            keterangan: "",
            tindak_lanjut: "",
          }),
        });
        savedCount++;
      } catch (error) {
        failed.push({ item: item, error: error.message });
      }
    }
    state.batch = failed.map(function (entry) { return entry.item; });
    state.saving = false;

    try {
      await loadAttendanceState();
    } catch (error) {
      setStatus("Data tersimpan: " + savedCount + ". Gagal memuat ulang daftar: " + error.message, true);
      updateFormState();
      return;
    }

    state.batch = state.batch.filter(function (item) { return !isAlreadySaved(item.santri_id); });
    if (failed.length) {
      setStatus(savedCount + " data tersimpan. Gagal: " + failed.map(function (entry) {
        return entry.item.nama + " (" + entry.error + ")";
      }).join("; "), true);
    } else {
      setStatus(savedCount + " data ketidakhadiran berhasil disimpan.");
    }
    updateFormState();
  }

  function clearBatch() {
    if (!state.batch.length || state.saving) return;
    if (!window.confirm("Hapus semua santri yang belum disimpan dari daftar?")) return;
    state.batch = [];
    setStatus("");
    updateFormState();
  }

  async function initialize() {
    elements.date.value = localDateString(new Date());
    try {
      const [session] = await Promise.all([
        requestJson("api/session.php"),
        loadActivities(),
      ]);
      if (!session.user) throw new Error("Silakan masuk untuk mencatat absensi.");
      await loadAttendanceState();
    } catch (error) {
      setStatus(error.message, true);
      elements.activity.disabled = true;
      elements.date.disabled = true;
      updateFormState();
    }
  }

  elements.search.addEventListener("input", async function () {
    state.selectedSantri = null;
    const keyword = elements.search.value.trim();
    if (keyword.length < 2) {
      elements.results.replaceChildren();
      elements.results.hidden = true;
      return;
    }
    try {
      await loadSantri();
      renderSearchResults(keyword);
    } catch (error) {
      setStatus(error.message, true);
    }
  });
  elements.activity.addEventListener("change", function () {
    loadAttendanceState().catch(function (error) { setStatus(error.message, true); });
  });
  elements.date.addEventListener("change", function () {
    loadAttendanceState().catch(function (error) { setStatus(error.message, true); });
  });
  elements.addButton.addEventListener("click", addSantri);
  elements.prepareButton.addEventListener("click", prepareSession);
  elements.saveButton.addEventListener("click", saveBatch);
  elements.clearButton.addEventListener("click", clearBatch);

  initialize();
})();
