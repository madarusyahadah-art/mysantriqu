"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("teacher-form");
  const tableBody = document.getElementById("teacher-table-body");
  const dialog = document.getElementById("teacher-dialog");
  const pageMessage = document.getElementById("teacher-message");
  const dialogMessage = document.getElementById("teacher-dialog-message");
  const saveButton = document.getElementById("save-teacher-button");
  let teachers = [];

  function showMessage(element, text, state) {
    element.textContent = text;
    element.dataset.state = state || "error";
    element.hidden = !text;
  }

  function formatRole(role) {
    const labels = {
      admin: "Admin",
      kajur: "Kajur",
      musyrif: "Musyrif",
      supervisor: "Musyrif",
      "mas'ul": "Mas'ul",
      mahasantri: "Mahasantri",
      santri: "Santri",
    };
    return labels[role] || role || "Pengguna";
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
    cell.className = "table-state";
    cell.colSpan = 5;
    cell.textContent = text;
    row.appendChild(cell);
    tableBody.appendChild(row);
  }

  function appendCell(row, text, className) {
    const cell = document.createElement("td");
    cell.textContent = text;
    if (className) cell.className = className;
    row.appendChild(cell);
    return cell;
  }

  function makeAction(label, action, id, destructive) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "row-action" + (destructive ? " delete" : "");
    button.textContent = label;
    button.dataset.action = action;
    button.dataset.id = String(id);
    return button;
  }

  function renderTeachers() {
    const query = document.getElementById("teacher-search-input").value.trim().toLocaleLowerCase("id");
    const filtered = teachers.filter(function (teacher) {
      return [teacher.nama, teacher.mata_pelajaran, teacher.jabatan]
        .some(function (value) { return String(value || "").toLocaleLowerCase("id").includes(query); });
    });

    document.getElementById("teacher-count").textContent = String(teachers.length);
    if (!filtered.length) {
      setTableState(query ? "Tidak ada data yang cocok dengan pencarian." : "Belum ada data Asatidzah. Tambahkan data pertama.");
      return;
    }

    tableBody.replaceChildren();
    filtered.forEach(function (teacher, index) {
      const row = document.createElement("tr");
      appendCell(row, String(index + 1));
      const nameCell = appendCell(row, String(teacher.nama || ""), "student-name-cell");
      const idLabel = document.createElement("span");
      idLabel.className = "student-id-badge";
      idLabel.textContent = "ID " + String(teacher.id);
      nameCell.appendChild(document.createElement("br"));
      nameCell.appendChild(idLabel);
      appendCell(row, String(teacher.mata_pelajaran || "—"));
      appendCell(row, String(teacher.jabatan || "—"));
      const actions = document.createElement("td");
      actions.append(
        makeAction("Edit", "edit", teacher.id, false),
        makeAction("Hapus", "delete", teacher.id, true)
      );
      row.appendChild(actions);
      tableBody.appendChild(row);
    });
  }

  async function loadTeachers() {
    setTableState("Memuat data Asatidzah...");
    try {
      const data = await requestJson("api/asatidz-management.php");
      teachers = data.asatidz;
      renderTeachers();
    } catch (error) {
      setTableState(error.message || "Data Asatidzah gagal dimuat.");
      showMessage(pageMessage, error.message || "Data Asatidzah gagal dimuat.", "error");
    }
  }

  function openCreateDialog() {
    form.reset();
    document.getElementById("teacher-id").value = "";
    document.getElementById("teacher-dialog-title").textContent = "Tambah Asatidzah";
    saveButton.textContent = "Simpan data";
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    document.getElementById("teacher-name").focus();
  }

  function openEditDialog(id) {
    const teacher = teachers.find(function (item) { return Number(item.id) === Number(id); });
    if (!teacher) {
      showMessage(pageMessage, "Data Asatidzah tidak ditemukan. Muat ulang daftar dan coba lagi.", "error");
      return;
    }

    form.reset();
    document.getElementById("teacher-dialog-title").textContent = "Edit Data Asatidzah";
    saveButton.textContent = "Simpan perubahan";
    document.getElementById("teacher-id").value = teacher.id;
    document.getElementById("teacher-name").value = teacher.nama || "";
    document.getElementById("teacher-subject").value = teacher.mata_pelajaran || "";
    document.getElementById("teacher-position").value = teacher.jabatan || "";
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    document.getElementById("teacher-name").focus();
  }

  async function deleteTeacher(id) {
    const teacher = teachers.find(function (item) { return Number(item.id) === Number(id); });
    if (!teacher) return;
    const confirmed = window.confirm("Hapus data Asatidzah \"" + teacher.nama + "\"? Riwayat kehadiran tetap tersimpan.");
    if (!confirmed) return;

    try {
      await requestJson("api/asatidz-management.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: Number(id) }),
      });
      showMessage(pageMessage, "Data Asatidzah berhasil dihapus. Riwayat kehadiran tetap tersimpan.", "success");
      await loadTeachers();
    } catch (error) {
      showMessage(pageMessage, error.message || "Data Asatidzah gagal dihapus.", "error");
    }
  }

  document.getElementById("add-teacher-button").addEventListener("click", openCreateDialog);
  document.getElementById("close-teacher-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("cancel-teacher-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("teacher-search-input").addEventListener("input", renderTeachers);
  tableBody.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    if (button.dataset.action === "edit") openEditDialog(button.dataset.id);
    if (button.dataset.action === "delete") deleteTeacher(button.dataset.id);
  });
  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) dialog.close();
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const id = document.getElementById("teacher-id").value;
    const payload = {
      nama: document.getElementById("teacher-name").value.trim(),
      mata_pelajaran: document.getElementById("teacher-subject").value.trim(),
      jabatan: document.getElementById("teacher-position").value.trim(),
    };
    if (id) payload.id = Number(id);

    saveButton.disabled = true;
    saveButton.textContent = id ? "Menyimpan..." : "Menambahkan...";
    showMessage(dialogMessage, "", "");
    try {
      await requestJson("api/asatidz-management.php", {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      dialog.close();
      showMessage(pageMessage, id ? "Data Asatidzah berhasil diperbarui." : "Data Asatidzah berhasil ditambahkan.", "success");
      await loadTeachers();
    } catch (error) {
      showMessage(dialogMessage, error.message || "Data Asatidzah gagal disimpan.", "error");
    } finally {
      saveButton.disabled = false;
      saveButton.textContent = id ? "Simpan perubahan" : "Simpan data";
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

  const today = new Date();
  document.getElementById("current-year").textContent = String(today.getFullYear());
  document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
    weekday: "long", day: "numeric", month: "long",
  }).format(today);

  requestJson("api/session.php")
    .then(function (data) {
      const user = data.user;
      if (!user || String(user.role || "").toLowerCase() !== "admin") {
        window.location.replace(user ? "dashboard.html" : "index.html");
        throw new Error("Halaman ini hanya untuk admin.");
      }
      const name = user.name || user.email || "Admin";
      document.getElementById("sidebar-name").textContent = name;
      document.getElementById("sidebar-role").textContent = formatRole(user.role);
      document.getElementById("topbar-name").textContent = name;
      const initials = name.trim().split(/\s+/).slice(0, 2).map(function (part) {
        return part.charAt(0);
      }).join("").toUpperCase() || "?";
      document.getElementById("sidebar-avatar").textContent = initials;
      document.getElementById("top-avatar").textContent = initials;
      return loadTeachers();
    })
    .catch(function (error) {
      if (error.message !== "Halaman ini hanya untuk admin.") {
        showMessage(pageMessage, error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    });
});
