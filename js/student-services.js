(function () {
  const elements = {
    journalCard: document.getElementById("student-journal-card"),
    journalForm: document.getElementById("student-journal-form"),
    journalDate: document.getElementById("journal-date"),
    journalTitle: document.getElementById("journal-title"),
    journalContent: document.getElementById("journal-content"),
    journalMessage: document.getElementById("journal-message"),
    journalList: document.getElementById("student-journal-list"),
    thesisCard: document.getElementById("student-thesis-card"),
    thesisForm: document.getElementById("student-thesis-form"),
    thesisTitle: document.getElementById("thesis-title"),
    thesisFile: document.getElementById("thesis-file"),
    thesisMessage: document.getElementById("thesis-message"),
    thesisList: document.getElementById("student-thesis-list"),
    khsCard: document.getElementById("student-khs-card"),
    khsList: document.getElementById("student-khs-list"),
  };
  const statusLabels = {
    menunggu: "Menunggu pemeriksaan",
    diterima: "Diterima",
    perlu_revisi: "Perlu revisi",
  };

  function localDateString(date) {
    const localDate = new Date(date);
    localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
    return localDate.toISOString().slice(0, 10);
  }

  async function requestJson(url, options) {
    const response = await fetch(url, Object.assign({ credentials: "same-origin" }, options || {}));
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || "Permintaan gagal diproses.");
    return data;
  }

  function appendEmpty(container, message) {
    const empty = document.createElement("p");
    empty.className = "role-empty";
    empty.textContent = message;
    container.appendChild(empty);
  }

  function renderJournals(journals) {
    elements.journalList.replaceChildren();
    if (!journals.length) {
      appendEmpty(elements.journalList, "Belum ada catatan jurnal.");
      return;
    }
    journals.forEach(function (journal) {
      const item = document.createElement("article");
      item.className = "record-item";
      const title = document.createElement("strong");
      title.textContent = journal.entry_date + " · " + journal.title;
      const content = document.createElement("p");
      content.textContent = journal.content;
      item.append(title, content);
      elements.journalList.appendChild(item);
    });
  }

  function appendDownload(container, type, documentRecord, label) {
    const item = document.createElement("article");
    item.className = "record-item";
    const title = document.createElement("strong");
    title.textContent = label;
    const details = document.createElement("p");
    details.className = "service-record-meta";
    const date = documentRecord.submitted_at || documentRecord.uploaded_at || "";
    details.textContent = [documentRecord.original_filename, date].filter(Boolean).join(" · ");
    const link = document.createElement("a");
    link.className = "student-service-link";
    link.href = "api/download-student-document.php?type=" + encodeURIComponent(type) +
      "&id=" + encodeURIComponent(documentRecord.id);
    link.textContent = "Unduh PDF";
    item.append(title, details, link);
    container.appendChild(item);
  }

  function renderThesis(submissions) {
    elements.thesisList.replaceChildren();
    if (!submissions.length) {
      appendEmpty(elements.thesisList, "Belum ada skripsi yang dikumpulkan.");
      return;
    }
    submissions.forEach(function (submission) {
      const item = document.createElement("article");
      item.className = "record-item";
      const title = document.createElement("strong");
      title.textContent = submission.title + " · " + (statusLabels[submission.status] || submission.status);
      const details = document.createElement("p");
      details.className = "service-record-meta";
      details.textContent = [submission.original_filename, submission.submitted_at].filter(Boolean).join(" · ");
      const actions = document.createElement("div");
      actions.className = "student-service-actions";
      const link = document.createElement("a");
      link.className = "student-service-link";
      link.href = "api/download-student-document.php?type=thesis&id=" + encodeURIComponent(submission.id);
      link.textContent = "Unduh PDF";
      actions.appendChild(link);
      item.append(title, details, actions);
      if (submission.admin_note) {
        const note = document.createElement("p");
        note.textContent = "Catatan admin: " + submission.admin_note;
        item.appendChild(note);
      }
      elements.thesisList.appendChild(item);
    });
  }

  function renderKhs(documents) {
    elements.khsList.replaceChildren();
    if (!documents.length) {
      appendEmpty(elements.khsList, "KHS belum tersedia. Silakan hubungi admin.");
      return;
    }
    documents.forEach(function (documentRecord) {
      appendDownload(elements.khsList, "khs", documentRecord, "KHS Semester " + documentRecord.semester);
    });
  }

  async function loadServices() {
    const data = await requestJson("api/student-services.php");
    renderJournals(data.journals);
    renderThesis(data.thesis);
    renderKhs(data.khs);
  }

  function setMessage(element, message, isError) {
    element.textContent = message;
    element.classList.toggle("status-error", Boolean(isError));
  }

  async function initialize() {
    elements.journalDate.value = localDateString(new Date());
    try {
      const session = await requestJson("api/session.php");
      if (!session.user || session.user.role !== "mahasantri") return;
      if (!session.user.santri_id) {
        [elements.journalForm, elements.thesisForm].forEach(function (form) {
          form.querySelectorAll("input, textarea, button").forEach(function (control) {
            control.disabled = true;
          });
        });
        setMessage(elements.journalMessage, "Profil santri belum dihubungkan oleh admin.", true);
        setMessage(elements.thesisMessage, "Profil santri belum dihubungkan oleh admin.", true);
        [
          [elements.journalList, "Profil santri belum dihubungkan oleh admin."],
          [elements.thesisList, "Profil santri belum dihubungkan oleh admin."],
          [elements.khsList, "Profil santri belum dihubungkan oleh admin."],
        ].forEach(function ([container, message]) {
          appendEmpty(container, message);
        });
        return;
      }
      await loadServices();
    } catch (error) {
      [elements.journalMessage, elements.thesisMessage].forEach(function (message) {
        setMessage(message, error.message, true);
      });
    }
  }

  elements.journalForm.addEventListener("submit", async function (event) {
    event.preventDefault();
    const button = elements.journalForm.querySelector('button[type="submit"]');
    button.disabled = true;
    setMessage(elements.journalMessage, "Menyimpan jurnal...");
    try {
      await requestJson("api/student-services.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create-journal",
          entry_date: elements.journalDate.value,
          title: elements.journalTitle.value.trim(),
          content: elements.journalContent.value.trim(),
        }),
      });
      elements.journalTitle.value = "";
      elements.journalContent.value = "";
      await loadServices();
      setMessage(elements.journalMessage, "Jurnal berhasil disimpan.");
    } catch (error) {
      setMessage(elements.journalMessage, error.message, true);
    } finally {
      button.disabled = false;
    }
  });

  elements.thesisForm.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!elements.thesisFile.files || !elements.thesisFile.files[0]) {
      setMessage(elements.thesisMessage, "Pilih file skripsi PDF terlebih dahulu.", true);
      return;
    }
    const button = elements.thesisForm.querySelector('button[type="submit"]');
    button.disabled = true;
    setMessage(elements.thesisMessage, "Mengunggah skripsi...");
    const form = new FormData();
    form.append("type", "thesis");
    form.append("title", elements.thesisTitle.value.trim());
    form.append("document", elements.thesisFile.files[0]);
    try {
      const response = await fetch("api/student-document-upload.php", {
        method: "POST",
        credentials: "same-origin",
        body: form,
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error || "Skripsi gagal dikirim.");
      elements.thesisTitle.value = "";
      elements.thesisFile.value = "";
      await loadServices();
      setMessage(elements.thesisMessage, "Skripsi berhasil dikirim untuk diperiksa.");
    } catch (error) {
      setMessage(elements.thesisMessage, error.message, true);
    } finally {
      button.disabled = false;
    }
  });

  document.addEventListener("DOMContentLoaded", initialize);
})();
