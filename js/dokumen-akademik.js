"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const documentType = new URLSearchParams(window.location.search).get("type");
  const pageConfig = {
    khs: {
      title: "Kartu Hasil Studi",
      navLabel: "KHS",
      description: "KHS dalam format PDF yang diunggah admin untuk akun santri Anda.",
      listTitle: "KHS Saya",
      searchPlaceholder: "Cari periode atau nama file...",
      hint: "Dokumen KHS hanya dapat dilihat oleh santri pemilik akun dan admin.",
    },
    skripsi: {
      title: "Referensi Skripsi",
      navLabel: "Skripsi",
      description: "Kumpulan skripsi santri dan alumni sebagai referensi akademik.",
      listTitle: "Pustaka Skripsi",
      searchPlaceholder: "Cari judul, penulis, atau tahun...",
      hint: "File PDF pustaka ini dapat dibaca oleh santri yang telah masuk dan admin.",
    },
    jurnal: {
      title: "Jurnal Munazharah",
      navLabel: "Jurnal Munazharah",
      description: "Kumpulan jurnal karya santri dan alumni untuk bahan bacaan dan referensi.",
      listTitle: "Pustaka Jurnal Munazharah",
      searchPlaceholder: "Cari judul, penulis, atau tahun...",
      hint: "File PDF pustaka ini dapat dibaca oleh santri yang telah masuk dan admin.",
    },
  };
  const config = pageConfig[documentType];
  const form = document.getElementById("document-form");
  const dialog = document.getElementById("document-dialog");
  const pageMessage = document.getElementById("page-message");
  const dialogMessage = document.getElementById("dialog-message");
  const saveButton = document.getElementById("save-document");
  const documentList = document.getElementById("document-list");
  const studentSelect = document.getElementById("document-student");
  const studentField = document.getElementById("student-field");
  const periodField = document.getElementById("period-field");
  const authorField = document.getElementById("author-field");
  const authorStatusField = document.getElementById("author-status-field");
  const publicationFields = document.getElementById("publication-fields");
  const descriptionField = document.getElementById("description-field");
  const titleField = document.getElementById("document-title");
  const periodInput = document.getElementById("document-period");
  const authorInput = document.getElementById("document-author");
  const yearInput = document.getElementById("publication-year");
  let documents = [];
  let students = [];
  let currentUser = null;
  let isAdmin = false;

  function showMessage(element, text, state) {
    element.textContent = text;
    element.dataset.state = state || "error";
    element.hidden = !text;
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

  function formatRole(role) {
    const labels = {
      admin: "Admin",
      kajur: "Kajur",
      musyrif: "Musyrif",
      supervisor: "Supervisor",
      "mas'ul": "Mas'ul",
      mahasantri: "Mahasantri",
      santri: "Santri",
    };
    return labels[role] || role || "Pengguna";
  }

  function formatFileSize(bytes) {
    if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }

  function setEmptyState(message) {
    documentList.replaceChildren();
    const empty = document.createElement("p");
    empty.className = "academic-empty";
    empty.textContent = message;
    documentList.appendChild(empty);
  }

  function makeDocumentCard(document) {
    const card = documentList.ownerDocument.createElement("article");
    card.className = "academic-document-card";
    const header = documentList.ownerDocument.createElement("div");
    header.className = "academic-document-card-header";
    const icon = documentList.ownerDocument.createElement("span");
    icon.className = "academic-document-icon";
    icon.textContent = "PDF";
    header.appendChild(icon);
    card.appendChild(header);

    const title = documentList.ownerDocument.createElement("h3");
    title.textContent = document.title || document.original_file_name || "Dokumen akademik";
    card.appendChild(title);

    const metadata = documentList.ownerDocument.createElement("p");
    metadata.className = "academic-document-meta";
    if (documentType === "khs") {
      if (isAdmin && document.student_name) {
        const student = documentList.ownerDocument.createElement("span");
        student.append("Santri: ");
        const studentName = documentList.ownerDocument.createElement("strong");
        studentName.textContent = document.student_name;
        student.appendChild(studentName);
        metadata.appendChild(student);
      }
      const period = documentList.ownerDocument.createElement("span");
      period.append("Periode: ");
      const periodValue = documentList.ownerDocument.createElement("strong");
      periodValue.textContent = document.academic_period || "—";
      period.appendChild(periodValue);
      metadata.appendChild(period);
    } else {
      const author = documentList.ownerDocument.createElement("span");
      author.append("Penulis: ");
      const authorValue = documentList.ownerDocument.createElement("strong");
      authorValue.textContent = document.author_name || "Tidak diketahui";
      author.appendChild(authorValue);
      metadata.appendChild(author);
      if (document.author_status) {
        const status = documentList.ownerDocument.createElement("span");
        status.textContent = document.author_status === "alumni" ? "Alumni" : "Santri aktif";
        metadata.appendChild(status);
      }
      if (document.publication_year) {
        const year = documentList.ownerDocument.createElement("span");
        year.append("Tahun: ");
        const yearValue = documentList.ownerDocument.createElement("strong");
        yearValue.textContent = String(document.publication_year);
        year.appendChild(yearValue);
        metadata.appendChild(year);
      }
    }
    const file = documentList.ownerDocument.createElement("span");
    file.textContent = document.original_file_name || "dokumen.pdf";
    metadata.appendChild(file);
    card.appendChild(metadata);

    if (document.description) {
      const description = documentList.ownerDocument.createElement("p");
      description.className = "academic-document-description";
      description.textContent = document.description;
      card.appendChild(description);
    }

    const footer = documentList.ownerDocument.createElement("div");
    footer.className = "academic-document-card-footer";
    const openLink = documentList.ownerDocument.createElement("a");
    openLink.className = "academic-pdf-link";
    openLink.href = "api/academic-document-file.php?id=" + encodeURIComponent(String(document.id));
    openLink.target = "_blank";
    openLink.rel = "noopener noreferrer";
    openLink.textContent = "Buka PDF";
    footer.appendChild(openLink);
    const size = documentList.ownerDocument.createElement("span");
    size.className = "academic-file-size";
    size.textContent = formatFileSize(Number(document.file_size) || 0);
    footer.appendChild(size);
    if (isAdmin) {
      const deleteButton = documentList.ownerDocument.createElement("button");
      deleteButton.type = "button";
      deleteButton.className = "academic-delete-button";
      deleteButton.dataset.documentId = String(document.id);
      deleteButton.textContent = "Hapus";
      footer.appendChild(deleteButton);
    }
    card.appendChild(footer);
    return card;
  }

  function renderDocuments() {
    const query = document.getElementById("document-search").value.trim().toLocaleLowerCase("id");
    const filtered = documents.filter(function (document) {
      return [
        document.title,
        document.original_file_name,
        document.author_name,
        document.author_status,
        document.academic_period,
        document.student_name,
        document.publication_year,
        document.description,
      ].some(function (value) {
        return String(value || "").toLocaleLowerCase("id").includes(query);
      });
    });
    document.getElementById("document-count").textContent = String(documents.length);
    if (!filtered.length) {
      setEmptyState(query ? "Tidak ada dokumen yang cocok dengan pencarian." : "Belum ada dokumen yang tersedia.");
      return;
    }
    documentList.replaceChildren();
    filtered.forEach(function (document) {
      documentList.appendChild(makeDocumentCard(document));
    });
  }

  function populateStudents() {
    studentSelect.replaceChildren(new Option("Pilih santri", ""));
    students.forEach(function (student) {
      studentSelect.add(new Option(
        student.nama + (student.angkatan ? " · Angkatan " + student.angkatan : "") + " · ID " + student.id,
        String(student.id)
      ));
    });
  }

  async function loadDocuments() {
    setEmptyState("Memuat dokumen...");
    try {
      const data = await requestJson("api/academic-documents.php?type=" + encodeURIComponent(documentType));
      documents = data.documents;
      if (isAdmin && documentType === "khs") {
        students = data.santri;
        populateStudents();
      }
      renderDocuments();
    } catch (error) {
      setEmptyState(error.message || "Dokumen gagal dimuat.");
      showMessage(pageMessage, error.message || "Dokumen gagal dimuat.", "error");
    }
  }

  function openUploadDialog() {
    form.reset();
    if (documentType === "khs") {
      studentField.hidden = false;
      periodField.hidden = false;
      authorField.hidden = true;
      authorStatusField.hidden = true;
      publicationFields.hidden = true;
      descriptionField.hidden = true;
      studentSelect.required = true;
      periodInput.required = true;
      authorInput.required = false;
      yearInput.required = false;
      titleField.value = "KHS";
      titleField.readOnly = true;
      document.getElementById("dialog-title").textContent = "Unggah KHS";
      document.getElementById("title-label").textContent = "Jenis dokumen";
    } else {
      studentField.hidden = true;
      periodField.hidden = true;
      authorField.hidden = false;
      authorStatusField.hidden = false;
      publicationFields.hidden = false;
      descriptionField.hidden = false;
      studentSelect.required = false;
      periodInput.required = false;
      authorInput.required = true;
      yearInput.required = true;
      titleField.value = "";
      titleField.readOnly = false;
      document.getElementById("dialog-title").textContent = "Unggah " + config.navLabel;
      document.getElementById("title-label").textContent = "Judul karya *";
    }
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    (documentType === "khs" ? studentSelect : titleField).focus();
  }

  async function deleteDocument(id) {
    const document = documents.find(function (item) { return Number(item.id) === Number(id); });
    if (!document) return;
    const confirmed = window.confirm("Hapus dokumen \"" + (document.title || document.original_file_name) + "\" secara permanen?");
    if (!confirmed) return;
    try {
      await requestJson("api/academic-documents.php?type=" + encodeURIComponent(documentType), {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: Number(id) }),
      });
      showMessage(pageMessage, "Dokumen berhasil dihapus.", "success");
      await loadDocuments();
    } catch (error) {
      showMessage(pageMessage, error.message || "Dokumen gagal dihapus.", "error");
    }
  }

  document.getElementById("document-search").addEventListener("input", renderDocuments);
  document.getElementById("add-document-button").addEventListener("click", openUploadDialog);
  document.getElementById("close-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("cancel-dialog").addEventListener("click", function () { dialog.close(); });
  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) dialog.close();
  });
  documentList.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-document-id]");
    if (button) deleteDocument(button.dataset.documentId);
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const pdf = document.getElementById("document-pdf").files[0];
    if (!pdf) {
      showMessage(dialogMessage, "Pilih file PDF yang akan diunggah.", "error");
      return;
    }
    if (pdf.size > 20 * 1024 * 1024) {
      showMessage(dialogMessage, "Ukuran PDF maksimal 20 MB.", "error");
      return;
    }

    const payload = new FormData(form);
    payload.set("type", documentType);
    if (documentType === "khs") {
      payload.set("title", "KHS");
    }
    saveButton.disabled = true;
    saveButton.textContent = "Mengunggah...";
    showMessage(dialogMessage, "", "");
    try {
      await requestJson("api/academic-documents.php", { method: "POST", body: payload });
      dialog.close();
      showMessage(pageMessage, "PDF berhasil diunggah.", "success");
      await loadDocuments();
    } catch (error) {
      showMessage(dialogMessage, error.message || "PDF gagal diunggah.", "error");
    } finally {
      saveButton.disabled = false;
      saveButton.textContent = "Unggah PDF";
    }
  });

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
      showMessage(pageMessage, error.message || "Gagal keluar. Silakan coba lagi.", "error");
      this.disabled = false;
    }
  });

  if (!config) {
    window.location.replace("dashboard.html");
    return;
  }
  document.title = config.title + " - Recording Kemahasantrian";
  document.getElementById("page-title").textContent = config.title;
  document.getElementById("breadcrumb-title").textContent = config.title;
  document.getElementById("page-description").textContent = config.description;
  document.getElementById("list-title").textContent = config.listTitle;
  document.getElementById("document-search").placeholder = config.searchPlaceholder;
  document.getElementById("list-hint").textContent = config.hint;
  document.querySelectorAll("[data-academic-type]").forEach(function (link) {
    if (link.dataset.academicType === documentType) {
      link.classList.add("active");
      link.setAttribute("aria-current", "page");
    }
  });

  const today = new Date();
  document.getElementById("current-year").textContent = String(today.getFullYear());
  document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
    weekday: "long", day: "numeric", month: "long",
  }).format(today);

  requestJson("api/session.php")
    .then(function (data) {
      currentUser = data.user;
      const role = String(currentUser && currentUser.role || "").toLowerCase();
      if (!currentUser || !["admin", "mahasantri", "santri"].includes(role)) {
        window.location.replace(currentUser ? "dashboard.html" : "index.html");
        throw new Error("Modul akademik hanya tersedia untuk admin dan santri.");
      }
      isAdmin = role === "admin";
      document.getElementById("add-document-button").hidden = !isAdmin;
      const name = currentUser.name || currentUser.email || "Pengguna";
      document.getElementById("sidebar-name").textContent = name;
      document.getElementById("sidebar-role").textContent = formatRole(role);
      document.getElementById("topbar-name").textContent = name;
      const initials = name.trim().split(/\s+/).slice(0, 2).map(function (part) {
        return part.charAt(0);
      }).join("").toUpperCase() || "?";
      document.getElementById("sidebar-avatar").textContent = initials;
      document.getElementById("top-avatar").textContent = initials;
      document.querySelectorAll(".admin-nav").forEach(function (item) {
        item.hidden = !isAdmin;
      });
      return loadDocuments();
    })
    .catch(function (error) {
      showMessage(pageMessage, error.message || "Sesi login tidak dapat diperiksa.", "error");
    });
});
