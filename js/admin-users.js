"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("user-form");
  const tableBody = document.getElementById("user-table-body");
  const dialog = document.getElementById("user-dialog");
  const pageMessage = document.getElementById("user-message");
  const dialogMessage = document.getElementById("user-dialog-message");
  const saveButton = document.getElementById("save-user-button");
  const roleSelect = document.getElementById("user-role");
  const studentField = document.getElementById("user-student-field");
  const studentSelect = document.getElementById("user-student-id");
  const linkHint = document.getElementById("user-link-hint");
  let currentUserId = 0;
  const studentRoles = ["mahasantri", "santri"];
  const roleLabels = {
    admin: "Admin",
    kajur: "Kajur",
    supervisor: "Supervisor",
    musyrif: "Musyrif",
    "mas'ul": "Mas'ul",
    mahasantri: "Mahasantri",
    santri: "Santri",
  };
  let users = [];
  let students = [];

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

  function setTableState(text) {
    tableBody.replaceChildren();
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.className = "table-state";
    cell.colSpan = 6;
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

  function makeEditButton(id) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "row-action";
    button.textContent = "Atur";
    button.dataset.id = String(id);
    return button;
  }

  function renderUsers() {
    const query = document.getElementById("user-search-input").value.trim().toLocaleLowerCase("id");
    const selectedRole = document.getElementById("user-role-filter").value;
    const filtered = users.filter(function (user) {
      const matchesQuery = [user.name, user.email]
        .some(function (value) { return String(value || "").toLocaleLowerCase("id").includes(query); });
      return matchesQuery && (selectedRole === "all" || user.role === selectedRole);
    });

    document.getElementById("user-count").textContent = String(users.length);
    if (!filtered.length) {
      setTableState(query || selectedRole !== "all"
        ? "Tidak ada akun yang cocok dengan filter."
        : "Belum ada akun terdaftar.");
      return;
    }

    tableBody.replaceChildren();
    filtered.forEach(function (user, index) {
      const row = document.createElement("tr");
      appendCell(row, String(index + 1));
      const nameCell = appendCell(row, String(user.name || "Tanpa nama"), "student-name-cell");
      const idLabel = document.createElement("span");
      idLabel.className = "student-id-badge";
      idLabel.textContent = "ID " + String(user.id);
      nameCell.appendChild(document.createElement("br"));
      nameCell.appendChild(idLabel);
      appendCell(row, String(user.email || "—"));
      const roleCell = document.createElement("td");
      const roleBadge = document.createElement("span");
      roleBadge.className = "user-role-badge";
      roleBadge.textContent = roleLabels[user.role] || user.role;
      roleCell.appendChild(roleBadge);
      row.appendChild(roleCell);
      appendCell(row, user.santri_nama
        ? String(user.santri_nama) + (user.angkatan ? " · Angkatan " + String(user.angkatan) : "")
        : "Belum terhubung", "user-link-badge");
      const actions = document.createElement("td");
      if (Number(user.id) === currentUserId) {
        const selfLabel = document.createElement("span");
        selfLabel.className = "user-self-label";
        selfLabel.textContent = "Akun Anda";
        actions.appendChild(selfLabel);
      } else {
        actions.appendChild(makeEditButton(user.id));
      }
      row.appendChild(actions);
      tableBody.appendChild(row);
    });
  }

  async function loadUsers() {
    setTableState("Memuat daftar akun...");
    try {
      const data = await requestJson("api/get-users.php");
      users = data.users;
      students = data.santri;
      renderUsers();
    } catch (error) {
      setTableState(error.message || "Daftar akun gagal dimuat.");
      showMessage(pageMessage, error.message || "Daftar akun gagal dimuat.", "error");
    }
  }

  function updateStudentRequirement() {
    const needsStudent = studentRoles.includes(roleSelect.value);
    studentField.hidden = !needsStudent;
    linkHint.hidden = !needsStudent;
    studentSelect.required = needsStudent;
  }

  function openEditDialog(id) {
    const user = users.find(function (item) { return Number(item.id) === Number(id); });
    if (!user) {
      showMessage(pageMessage, "Akun tidak ditemukan. Muat ulang daftar dan coba lagi.", "error");
      return;
    }
    if (Number(user.id) === currentUserId) {
      showMessage(pageMessage, "Role akun Anda sendiri tidak dapat diubah dari halaman ini.", "error");
      return;
    }

    form.reset();
    document.getElementById("user-id").value = String(user.id);
    document.getElementById("user-account-summary").textContent =
      (user.name || "Tanpa nama") + " · " + (user.email || "Email tidak tersedia");
    roleSelect.value = user.role;
    studentSelect.replaceChildren(new Option("Pilih data santri", ""));
    students.forEach(function (student) {
      const option = new Option(
        student.nama + (student.angkatan ? " · Angkatan " + student.angkatan : "") + " · ID " + student.id,
        String(student.id)
      );
      studentSelect.add(option);
    });
    studentSelect.value = user.santri_id ? String(user.santri_id) : "";
    updateStudentRequirement();
    showMessage(dialogMessage, "", "");
    dialog.showModal();
  }

  document.getElementById("user-search-input").addEventListener("input", renderUsers);
  document.getElementById("user-role-filter").addEventListener("change", renderUsers);
  roleSelect.addEventListener("change", updateStudentRequirement);
  tableBody.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-id]");
    if (button) openEditDialog(button.dataset.id);
  });
  document.getElementById("close-user-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("cancel-user-dialog").addEventListener("click", function () { dialog.close(); });
  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) dialog.close();
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const payload = {
      id: Number(document.getElementById("user-id").value),
      role: roleSelect.value,
      santri_id: studentRoles.includes(roleSelect.value) ? Number(studentSelect.value) : null,
    };
    saveButton.disabled = true;
    saveButton.textContent = "Menyimpan...";
    showMessage(dialogMessage, "", "");
    try {
      await requestJson("api/update-user-role.php", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      dialog.close();
      showMessage(pageMessage, "Pengaturan akun berhasil diperbarui.", "success");
      await loadUsers();
    } catch (error) {
      showMessage(dialogMessage, error.message || "Pengaturan akun gagal disimpan.", "error");
    } finally {
      saveButton.disabled = false;
      saveButton.textContent = "Simpan perubahan";
    }
  });

  function formatRole(role) {
    return roleLabels[role] || role || "Pengguna";
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
      currentUserId = Number(user.id);
      const name = user.name || user.email || "Admin";
      document.getElementById("sidebar-name").textContent = name;
      document.getElementById("sidebar-role").textContent = formatRole(user.role);
      document.getElementById("topbar-name").textContent = name;
      const initials = name.trim().split(/\s+/).slice(0, 2).map(function (part) {
        return part.charAt(0);
      }).join("").toUpperCase() || "?";
      document.getElementById("sidebar-avatar").textContent = initials;
      document.getElementById("top-avatar").textContent = initials;
      return loadUsers();
    })
    .catch(function (error) {
      if (error.message !== "Halaman ini hanya untuk admin.") {
        showMessage(pageMessage, error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    });
});
