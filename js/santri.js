"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("student-form");
  const tableBody = document.getElementById("student-table-body");
  const dialog = document.getElementById("student-dialog");
  const pageMessage = document.getElementById("student-message");
  const dialogMessage = document.getElementById("dialog-message");
  const saveButton = document.getElementById("save-student-button");
  let students = [];

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
    cell.colSpan = 7;
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

  function renderStudents() {
    const query = document.getElementById("student-search-input").value.trim().toLocaleLowerCase("id");
    const sortBy = document.getElementById("student-sort").value;
    const filtered = students.filter(function (student) {
      return [student.nama, student.no_ktm, student.asal, student.angkatan]
        .some(function (value) { return String(value || "").toLocaleLowerCase("id").includes(query); });
    });
    const collator = new Intl.Collator("id", { numeric: true, sensitivity: "base" });
    filtered.sort(function (first, second) {
      if (sortBy === "cohort") {
        const firstCohort = String(first.angkatan || "").trim();
        const secondCohort = String(second.angkatan || "").trim();
        if (!firstCohort && secondCohort) return 1;
        if (firstCohort && !secondCohort) return -1;
        const cohortOrder = collator.compare(firstCohort, secondCohort);
        if (cohortOrder !== 0) return cohortOrder;
      }

      const nameOrder = collator.compare(String(first.nama || ""), String(second.nama || ""));
      return nameOrder || Number(first.id) - Number(second.id);
    });

    document.getElementById("student-count").textContent = String(students.length);
    if (!filtered.length) {
      setTableState(query ? "Tidak ada data yang cocok dengan pencarian." : "Belum ada data santri. Tambahkan data pertama.");
      return;
    }

    tableBody.replaceChildren();
    filtered.forEach(function (student, index) {
      const row = document.createElement("tr");
      appendCell(row, String(index + 1));
      const nameCell = appendCell(row, String(student.nama || ""), "student-name-cell");
      const idLabel = document.createElement("span");
      idLabel.className = "student-id-badge";
      idLabel.textContent = "ID " + String(student.id);
      nameCell.appendChild(document.createElement("br"));
      nameCell.appendChild(idLabel);
      appendCell(row, String(student.no_ktm || "—"));
      appendCell(row, String(student.asal || "—"));
      appendCell(row, String(student.angkatan || "—"));
      const semesterCell = document.createElement("td");
      const semesterBadge = document.createElement("span");
      semesterBadge.className = "semester-badge";
      semesterBadge.textContent = "Semester " + String(student.semester_aktif || 1);
      semesterCell.appendChild(semesterBadge);
      row.appendChild(semesterCell);
      const actions = document.createElement("td");
      actions.append(
        makeAction("Edit", "edit", student.id, false),
        makeAction("Hapus", "delete", student.id, true)
      );
      row.appendChild(actions);
      tableBody.appendChild(row);
    });
  }

  async function loadStudents() {
    setTableState("Memuat data santri...");
    try {
      const data = await requestJson("api/santri-management.php");
      students = data.santri;
      renderStudents();
    } catch (error) {
      setTableState(error.message || "Data santri gagal dimuat.");
      showMessage(pageMessage, error.message || "Data santri gagal dimuat.", "error");
    }
  }

  function openCreateDialog() {
    form.reset();
    document.getElementById("student-id").value = "";
    document.getElementById("student-dialog-title").textContent = "Tambah Santri";
    document.getElementById("save-student-button").textContent = "Simpan data";
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    document.getElementById("student-name").focus();
  }

  function openEditDialog(id) {
    const student = students.find(function (item) { return Number(item.id) === Number(id); });
    if (!student) {
      showMessage(pageMessage, "Data santri tidak ditemukan. Muat ulang daftar dan coba lagi.", "error");
      return;
    }

    form.reset();
    document.getElementById("student-dialog-title").textContent = "Edit Data Santri";
    document.getElementById("save-student-button").textContent = "Simpan perubahan";
    document.getElementById("student-id").value = student.id;
    document.getElementById("student-name").value = student.nama || "";
    document.getElementById("student-ktm").value = student.no_ktm || "";
    document.getElementById("student-origin").value = student.asal || "";
    document.getElementById("student-cohort").value = student.angkatan || "";
    document.getElementById("student-semester").value = String(student.semester_aktif || 1);
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    document.getElementById("student-name").focus();
  }

  async function deleteStudent(id) {
    const student = students.find(function (item) { return Number(item.id) === Number(id); });
    if (!student) return;
    const confirmed = window.confirm("Hapus data santri \"" + student.nama + "\"? Data yang masih dipakai oleh akun atau catatan lain mungkin tidak dapat dihapus.");
    if (!confirmed) return;

    try {
      await requestJson("api/santri-management.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: Number(id) }),
      });
      showMessage(pageMessage, "Data santri berhasil dihapus.", "success");
      await loadStudents();
    } catch (error) {
      showMessage(pageMessage, error.message || "Data santri gagal dihapus.", "error");
    }
  }

  document.getElementById("add-student-button").addEventListener("click", openCreateDialog);
  document.getElementById("close-student-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("cancel-student-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("student-search-input").addEventListener("input", renderStudents);
  document.getElementById("student-sort").addEventListener("change", renderStudents);
  tableBody.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    if (button.dataset.action === "edit") openEditDialog(button.dataset.id);
    if (button.dataset.action === "delete") deleteStudent(button.dataset.id);
  });
  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) dialog.close();
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const id = document.getElementById("student-id").value;
    const payload = {
      nama: document.getElementById("student-name").value.trim(),
      no_ktm: document.getElementById("student-ktm").value.trim(),
      asal: document.getElementById("student-origin").value.trim(),
      angkatan: document.getElementById("student-cohort").value.trim(),
      semester_aktif: Number(document.getElementById("student-semester").value),
    };
    if (id) payload.id = Number(id);

    saveButton.disabled = true;
    saveButton.textContent = id ? "Menyimpan..." : "Menambahkan...";
    showMessage(dialogMessage, "", "");
    try {
      await requestJson("api/santri-management.php", {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      dialog.close();
      showMessage(pageMessage, id ? "Data santri berhasil diperbarui." : "Data santri berhasil ditambahkan.", "success");
      await loadStudents();
    } catch (error) {
      showMessage(dialogMessage, error.message || "Data santri gagal disimpan.", "error");
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
      return loadStudents();
    })
    .catch(function (error) {
      if (error.message !== "Halaman ini hanya untuk admin.") {
        showMessage(pageMessage, error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    });
});
