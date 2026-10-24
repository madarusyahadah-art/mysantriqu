(function () {
  let previewCalendar = null;

  function showError(message) {
    document.getElementById("calendar-error").textContent = message || "";
  }

  function formatDateRange(start, end) {
    if (!start) return "Tanggal belum ditentukan";
    return start === end ? start : start + " – " + end;
  }

  function renderEvents(calendar, events, processedRollovers) {
    document.getElementById("calendar-title").textContent = calendar ? calendar.judul : "Kegiatan kalender";
    document.getElementById("calendar-year").textContent = calendar
      ? "Tahun ajaran " + calendar.tahun_ajaran + " · Diimpor dari " + calendar.nama_berkas
      : "Belum ada Kaldik yang diimpor.";
    document.getElementById("semester-message").textContent = processedRollovers
      ? "Semester Mahasantri telah diperbarui berdasarkan " + processedRollovers + " ujian akhir yang sudah selesai."
      : "";

    const container = document.getElementById("calendar-events");
    container.replaceChildren();
    if (!events.length) {
      const empty = document.createElement("p");
      empty.textContent = calendar ? "Tidak ada kegiatan pada bulan ini." : "Admin belum mengimpor kalender akademik.";
      container.appendChild(empty);
      return;
    }

    events.forEach((event) => {
      const item = document.createElement("article");
      item.className = "calendar-event";
      const date = document.createElement("div");
      date.className = "calendar-date";
      date.textContent = formatDateRange(event.tanggal_mulai, event.tanggal_selesai);
      const details = document.createElement("div");
      details.style.flex = "1";
      const name = document.createElement("strong");
      name.textContent = event.nama_kegiatan;
      details.appendChild(name);
      if (Number(event.is_semester_end)) {
        const badge = document.createElement("span");
        badge.className = "calendar-badge" + (event.semester_incremented_at ? " done" : "");
        badge.textContent = event.semester_incremented_at ? "Semester sudah diperbarui" : "Pemicu kenaikan semester";
        details.appendChild(document.createElement("br"));
        details.appendChild(badge);
      }
      item.append(date, details);
      container.appendChild(item);
    });
  }

  async function loadCalendar() {
    const month = document.getElementById("calendar-month").value;
    if (!month) return;
    showError("");
    const response = await fetch("api/academic-calendar.php?bulan=" + encodeURIComponent(month), { credentials: "same-origin" });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || "Kalender akademik gagal dimuat.");
    renderEvents(data.calendar, data.events, data.processed_rollovers);
  }

  function renderPreview(calendar) {
    previewCalendar = calendar;
    const preview = document.getElementById("import-preview");
    preview.replaceChildren();
    const summary = document.createElement("p");
    summary.textContent = calendar.judul + " · Tahun ajaran " + calendar.tahun_ajaran + " · " + calendar.events.length + " kegiatan";
    preview.appendChild(summary);

    calendar.events.forEach((event, index) => {
      const label = document.createElement("label");
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.checked = event.akhir_semester;
      checkbox.dataset.eventIndex = String(index);
      const detail = document.createElement("span");
      const range = formatDateRange(event.tanggal_mulai, event.tanggal_selesai);
      detail.textContent = range + " · " + event.nama_kegiatan + (event.akhir_semester ? " (kenaikan semester)" : "");
      label.append(checkbox, detail);
      preview.appendChild(label);
    });
    preview.hidden = false;
    document.getElementById("import-submit").hidden = false;
  }

  document.addEventListener("DOMContentLoaded", async function () {
    const now = new Date();
    document.getElementById("calendar-month").value =
      now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
    document.getElementById("calendar-month").addEventListener("change", function () {
      loadCalendar().catch((error) => showError(error.message));
    });

    try {
      const sessionResponse = await fetch("api/session.php", { credentials: "same-origin" });
      const session = await sessionResponse.json();
      if (!sessionResponse.ok || !session.success || !session.user) throw new Error("Silakan login untuk membuka kalender.");
      document.getElementById("import-card").hidden = session.user.role !== "admin";
      await loadCalendar();
    } catch (error) {
      showError(error.message);
    }

    document.getElementById("calendar-file").addEventListener("change", async function () {
      const message = document.getElementById("import-message");
      document.getElementById("import-preview").hidden = true;
      document.getElementById("import-submit").hidden = true;
      previewCalendar = null;
      if (!this.files || !this.files[0]) return;
      message.textContent = "Membaca kalender dan menyiapkan pratinjau...";
      try {
        const calendar = await window.parseAcademicCalendarXlsx(this.files[0]);
        renderPreview(calendar);
        const rolloverCount = calendar.events.filter((event) => event.akhir_semester).length;
        message.textContent = rolloverCount
          ? "Periksa " + rolloverCount + " kegiatan yang dikenali sebagai ujian akhir semester. Ubah centang bila perlu."
          : "Tidak ada ujian akhir semester yang dikenali. Tandai secara manual jika sesuai.";
      } catch (error) {
        message.textContent = error.message;
        message.className = "import-message calendar-error";
      }
    });

    document.getElementById("calendar-import-form").addEventListener("submit", async function (event) {
      event.preventDefault();
      if (!previewCalendar) return;
      const checkboxes = document.querySelectorAll("#import-preview input[type=checkbox]");
      checkboxes.forEach((checkbox) => {
        previewCalendar.events[Number(checkbox.dataset.eventIndex)].akhir_semester = checkbox.checked;
      });
      const message = document.getElementById("import-message");
      const button = document.getElementById("import-submit");
      button.disabled = true;
      message.className = "import-message";
      message.textContent = "Menyimpan kalender...";
      try {
        const response = await fetch("api/academic-calendar.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(previewCalendar),
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Kalender gagal disimpan.");
        message.textContent = result.jumlah_kegiatan + " kegiatan tersimpan, termasuk " +
          result.jumlah_pemicu_semester + " pemicu kenaikan semester.";
        await loadCalendar();
      } catch (error) {
        message.textContent = error.message;
        message.className = "import-message calendar-error";
      } finally {
        button.disabled = false;
      }
    });
  });
})();
