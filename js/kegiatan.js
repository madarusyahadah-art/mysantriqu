"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("activity-form");
  const tableBody = document.getElementById("activity-table-body");
  const dialog = document.getElementById("activity-dialog");
  const pageMessage = document.getElementById("activity-message");
  const dialogMessage = document.getElementById("activity-dialog-message");
  const saveButton = document.getElementById("save-activity-button");
  let activities = [];

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

  function makeAction(label, action, id, destructive) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "row-action" + (destructive ? " delete" : "");
    button.textContent = label;
    button.dataset.action = action;
    button.dataset.id = String(id);
    return button;
  }

  function renderActivities() {
    const query = document.getElementById("activity-search-input").value.trim().toLocaleLowerCase("id");
    const statusFilter = document.getElementById("activity-status-filter").value;
    const filtered = activities.filter(function (activity) {
      const matchesQuery = [activity.nama_kegiatan, activity.kategori, activity.frekuensi]
        .some(function (value) { return String(value || "").toLocaleLowerCase("id").includes(query); });
      const matchesStatus = statusFilter === "all"
        || (statusFilter === "active" && activity.is_active)
        || (statusFilter === "inactive" && !activity.is_active);
      return matchesQuery && matchesStatus;
    });

    document.getElementById("activity-count").textContent = String(activities.length);
    if (!filtered.length) {
      setTableState(query || statusFilter !== "all"
        ? "Tidak ada data kegiatan yang cocok dengan filter."
        : "Belum ada data kegiatan. Tambahkan kegiatan pertama.");
      return;
    }

    tableBody.replaceChildren();
    filtered.forEach(function (activity, index) {
      const row = document.createElement("tr");
      appendCell(row, String(index + 1));
      const nameCell = appendCell(row, String(activity.nama_kegiatan || ""), "student-name-cell");
      const idLabel = document.createElement("span");
      idLabel.className = "student-id-badge";
      idLabel.textContent = "ID " + String(activity.id);
      nameCell.appendChild(document.createElement("br"));
      nameCell.appendChild(idLabel);
      appendCell(row, String(activity.kategori || "—"));
      appendCell(row, String(activity.frekuensi_label || activity.frekuensi || "—"));
      const statusCell = document.createElement("td");
      const statusBadge = document.createElement("span");
      statusBadge.className = "activity-status";
      statusBadge.dataset.active = String(Boolean(activity.is_active));
      statusBadge.textContent = activity.is_active ? "Aktif" : "Nonaktif";
      statusCell.appendChild(statusBadge);
      row.appendChild(statusCell);
      const actions = document.createElement("td");
      actions.append(
        makeAction("Edit", "edit", activity.id, false),
        makeAction(activity.is_active ? "Nonaktifkan" : "Aktifkan", "toggle", activity.id, !activity.is_active)
      );
      row.appendChild(actions);
      tableBody.appendChild(row);
    });
  }

  async function loadActivities() {
    setTableState("Memuat data kegiatan...");
    try {
      const data = await requestJson("api/kegiatan-management.php");
      activities = data.kegiatan;
      renderActivities();
    } catch (error) {
      setTableState(error.message || "Data kegiatan gagal dimuat.");
      showMessage(pageMessage, error.message || "Data kegiatan gagal dimuat.", "error");
    }
  }

  function openCreateDialog() {
    form.reset();
    document.getElementById("activity-id").value = "";
    document.getElementById("activity-dialog-title").textContent = "Tambah Kegiatan";
    saveButton.textContent = "Simpan data";
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    document.getElementById("activity-name").focus();
  }

  function openEditDialog(id) {
    const activity = activities.find(function (item) { return Number(item.id) === Number(id); });
    if (!activity) {
      showMessage(pageMessage, "Data kegiatan tidak ditemukan. Muat ulang daftar dan coba lagi.", "error");
      return;
    }

    form.reset();
    document.getElementById("activity-id").value = activity.id;
    document.getElementById("activity-dialog-title").textContent = "Edit Kegiatan";
    saveButton.textContent = "Simpan perubahan";
    document.getElementById("activity-name").value = activity.nama_kegiatan || "";
    document.getElementById("activity-category").value = activity.kategori || "";
    document.getElementById("activity-frequency").value = activity.frekuensi || "harian";
    showMessage(dialogMessage, "", "");
    dialog.showModal();
    document.getElementById("activity-name").focus();
  }

  async function toggleActivity(id) {
    const activity = activities.find(function (item) { return Number(item.id) === Number(id); });
    if (!activity) return;
    const nextStatus = !activity.is_active;
    const confirmation = nextStatus
      ? "Aktifkan kembali kegiatan \"" + activity.nama_kegiatan + "\"?"
      : "Nonaktifkan kegiatan \"" + activity.nama_kegiatan + "\"? Riwayat yang sudah tercatat tetap tersimpan.";
    if (!window.confirm(confirmation)) return;

    try {
      await requestJson("api/kegiatan-management.php", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: Number(id), is_active: nextStatus }),
      });
      showMessage(pageMessage, nextStatus
        ? "Kegiatan berhasil diaktifkan."
        : "Kegiatan berhasil dinonaktifkan. Riwayat sebelumnya tetap tersimpan.", "success");
      await loadActivities();
    } catch (error) {
      showMessage(pageMessage, error.message || "Status kegiatan gagal diperbarui.", "error");
    }
  }

  document.getElementById("add-activity-button").addEventListener("click", openCreateDialog);
  document.getElementById("close-activity-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("cancel-activity-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("activity-search-input").addEventListener("input", renderActivities);
  document.getElementById("activity-status-filter").addEventListener("change", renderActivities);
  tableBody.addEventListener("click", function (event) {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    if (button.dataset.action === "edit") openEditDialog(button.dataset.id);
    if (button.dataset.action === "toggle") toggleActivity(button.dataset.id);
  });
  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) dialog.close();
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const id = document.getElementById("activity-id").value;
    const payload = {
      nama_kegiatan: document.getElementById("activity-name").value.trim(),
      kategori: document.getElementById("activity-category").value.trim(),
      frekuensi: document.getElementById("activity-frequency").value,
    };
    if (id) payload.id = Number(id);

    saveButton.disabled = true;
    saveButton.textContent = id ? "Menyimpan..." : "Menambahkan...";
    showMessage(dialogMessage, "", "");
    try {
      await requestJson("api/kegiatan-management.php", {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      dialog.close();
      showMessage(pageMessage, id ? "Data kegiatan berhasil diperbarui." : "Data kegiatan berhasil ditambahkan.", "success");
      await loadActivities();
    } catch (error) {
      showMessage(dialogMessage, error.message || "Data kegiatan gagal disimpan.", "error");
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
      return loadActivities();
    })
    .catch(function (error) {
      if (error.message !== "Halaman ini hanya untuk admin.") {
        showMessage(pageMessage, error.message || "Sesi login tidak dapat diperiksa.", "error");
      }
    });
});
