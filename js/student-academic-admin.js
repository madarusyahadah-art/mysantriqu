(function () {
  const studentSelect = document.getElementById("student-select");
  const pageMessage = document.getElementById("page-message");
  const khsForm = document.getElementById("khs-upload-form");
  const khsSemester = document.getElementById("khs-semester");
  const khsFile = document.getElementById("khs-file");
  const khsList = document.getElementById("khs-list");
  const thesisList = document.getElementById("thesis-list");

  async function requestJson(url, options) {
    const response = await fetch(url, Object.assign({ credentials: "same-origin" }, options || {}));
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || "Permintaan gagal diproses.");
    return data;
  }

  function setMessage(message, isError) {
    pageMessage.textContent = message || "";
    pageMessage.style.color = isError ? "#fca5a5" : "#9ae6b4";
  }

  function appendEmpty(container, message) {
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent = message;
    container.appendChild(empty);
  }

  function renderKhs(documents) {
    khsList.replaceChildren();
    if (!documents.length) {
      appendEmpty(khsList, "Belum ada KHS untuk Mahasantri ini.");
      return;
    }
    documents.forEach(function (documentRecord) {
      const item = document.createElement("article");
      item.className = "document-item";
      const title = document.createElement("strong");
      title.textContent = "Semester " + documentRecord.semester;
      const filename = document.createElement("p");
      filename.textContent = documentRecord.original_filename + " · " + documentRecord.uploaded_at;
      const link = document.createElement("a");
      link.href = "api/download-student-document.php?type=khs&id=" + encodeURIComponent(documentRecord.id);
      link.textContent = "Unduh KHS";
      item.append(title, filename, link);
      khsList.appendChild(item);
    });
  }

  async function reviewThesis(id, status, note, button) {
    button.disabled = true;
    try {
      await requestJson("api/student-services.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "review-thesis", id: id, status: status, admin_note: note }),
      });
      setMessage("Pemeriksaan skripsi berhasil disimpan.");
      await loadStudentDocuments();
    } catch (error) {
      setMessage(error.message, true);
    } finally {
      button.disabled = false;
    }
  }

  function renderThesis(submissions) {
    thesisList.replaceChildren();
    if (!submissions.length) {
      appendEmpty(thesisList, "Belum ada skripsi yang dikumpulkan Mahasantri ini.");
      return;
    }
    submissions.forEach(function (submission) {
      const item = document.createElement("article");
      item.className = "document-item";
      const title = document.createElement("strong");
      title.textContent = submission.title + " · " + submission.status;
      const filename = document.createElement("p");
      filename.textContent = submission.original_filename + " · " + submission.submitted_at;
      const download = document.createElement("a");
      download.href = "api/download-student-document.php?type=thesis&id=" + encodeURIComponent(submission.id);
      download.textContent = "Unduh skripsi";

      const form = document.createElement("form");
      form.className = "review-form";
      const status = document.createElement("select");
      [["diterima", "Diterima"], ["perlu_revisi", "Perlu revisi"]].forEach(function (optionData) {
        const option = document.createElement("option");
        option.value = optionData[0];
        option.textContent = optionData[1];
        option.selected = submission.status === option.value;
        status.appendChild(option);
      });
      const note = document.createElement("textarea");
      note.rows = 3;
      note.maxLength = 5000;
      note.placeholder = "Catatan untuk Mahasantri (opsional)";
      note.value = submission.admin_note || "";
      const save = document.createElement("button");
      save.type = "submit";
      save.textContent = "Simpan hasil pemeriksaan";
      form.append(status, note, save);
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        reviewThesis(Number(submission.id), status.value, note.value, save);
      });
      item.append(title, filename, download, form);
      thesisList.appendChild(item);
    });
  }

  async function loadStudentDocuments() {
    if (!studentSelect.value) {
      khsList.replaceChildren();
      thesisList.replaceChildren();
      appendEmpty(khsList, "Pilih Mahasantri untuk melihat dokumen.");
      appendEmpty(thesisList, "Pilih Mahasantri untuk melihat dokumen.");
      return;
    }
    const query = new URLSearchParams({ admin: "1", santri_id: studentSelect.value });
    const data = await requestJson("api/student-services.php?" + query.toString());
    renderKhs(data.khs);
    renderThesis(data.thesis);
  }

  async function initialize() {
    try {
      const data = await requestJson("api/student-services.php?admin=1");
      studentSelect.replaceChildren();
      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "-- Pilih Mahasantri --";
      studentSelect.appendChild(placeholder);
      data.students.forEach(function (student) {
        const option = document.createElement("option");
        option.value = String(student.id);
        option.textContent = student.nama + (student.angkatan ? " · " + student.angkatan : "");
        studentSelect.appendChild(option);
      });
      if (!data.students.length) setMessage("Belum ada data Mahasantri.", true);
    } catch (error) {
      setMessage(error.message, true);
      studentSelect.disabled = true;
      khsForm.querySelector("button[type=submit]").disabled = true;
    }
  }

  studentSelect.addEventListener("change", function () {
    setMessage("");
    loadStudentDocuments().catch(function (error) { setMessage(error.message, true); });
  });

  khsForm.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!studentSelect.value) {
      setMessage("Pilih Mahasantri terlebih dahulu.", true);
      return;
    }
    if (!khsFile.files || !khsFile.files[0]) {
      setMessage("Pilih file KHS PDF terlebih dahulu.", true);
      return;
    }

    const button = khsForm.querySelector("button[type=submit]");
    button.disabled = true;
    setMessage("Mengunggah KHS...");
    const form = new FormData();
    form.append("type", "khs");
    form.append("santri_id", studentSelect.value);
    form.append("semester", khsSemester.value);
    form.append("document", khsFile.files[0]);
    try {
      const response = await fetch("api/student-document-upload.php", {
        method: "POST",
        credentials: "same-origin",
        body: form,
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error || "KHS gagal diunggah.");
      khsFile.value = "";
      khsSemester.value = "";
      await loadStudentDocuments();
      setMessage("KHS berhasil diunggah.");
    } catch (error) {
      setMessage(error.message, true);
    } finally {
      button.disabled = false;
    }
  });

  initialize();
})();
