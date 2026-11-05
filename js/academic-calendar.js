"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const categories = {
    Akademik: { color: "#3d896c", bg: "#eaf5ee", text: "#31644e" },
    Ujian: { color: "#bf7d28", bg: "#fff2dd", text: "#8a5a18" },
    Libur: { color: "#6984bb", bg: "#edf2ff", text: "#496293" },
    "Kegiatan Ma'had": { color: "#925ca7", bg: "#f5edfa", text: "#704682" },
    Lainnya: { color: "#829087", bg: "#f0f3f0", text: "#5e6b62" },
  };
  const monthInput = document.getElementById("calendar-month");
  const calendarGrid = document.getElementById("calendar-grid");
  const eventList = document.getElementById("calendar-events");
  const changesList = document.getElementById("calendar-changes");
  const pageMessage = document.getElementById("page-message");
  const dialog = document.getElementById("event-dialog");
  const eventForm = document.getElementById("event-form");
  let currentUser = null;
  let isAdmin = false;
  let calendarEvents = [];
  let currentDraftId = null;
  let previewEvents = [];
  let activeEvent = null;
  let latestChangeId = 0;
  let pushRegistration = null;
  let pushConfiguration = null;
  let monthIsInitial = true;

  function showMessage(element, text, state) {
    element.textContent = text || "";
    if (state) element.dataset.state = state;
    else delete element.dataset.state;
    element.hidden = !text;
  }

  function roleLabel(role) {
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
    if (!response.ok || !data.success) throw new Error(data.error || "Permintaan tidak dapat diproses.");
    return data;
  }

  async function postCalendar(payload) {
    return requestJson("api/academic-calendar.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
  }

  function pushDeliveryMessage(result) {
    if (!result || !result.configured) {
      return "Push perangkat belum dikonfigurasi; perubahan tetap terlihat saat kalender dibuka.";
    }
    if (!result.subscriptions) return "Tidak ada perangkat yang mengaktifkan notifikasi push.";
    if (result.failed) {
      return "Push terkirim ke " + result.sent + " perangkat; " + result.failed + " gagal. Periksa log server.";
    }
    return "Notifikasi push terkirim ke " + result.sent + " perangkat.";
  }

  function localDate(value) {
    const parts = String(value).split("-").map(Number);
    return new Date(parts[0], parts[1] - 1, parts[2]);
  }

  function dateValue(date) {
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0") + "-" + String(date.getDate()).padStart(2, "0");
  }

  function formatDate(value) {
    if (!value) return "Tanggal belum ditentukan";
    return localDate(value).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" });
  }

  function formatRange(start, end) {
    if (!start) return "Tanggal belum ditentukan";
    if (start === end) return formatDate(start);
    return formatDate(start) + " – " + formatDate(end);
  }

  function formatSchedule(event) {
    const startTime = event.jam_mulai ? String(event.jam_mulai).slice(0, 5) : "";
    const endTime = event.jam_selesai ? String(event.jam_selesai).slice(0, 5) : "";
    const time = startTime ? " · " + startTime + (endTime ? "–" + endTime : "") : "";
    return formatRange(event.tanggal_mulai, event.tanggal_selesai) + time;
  }

  function formatDateTime(value) {
    return new Date(String(value).replace(" ", "T")).toLocaleString("id-ID", {
      day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit",
    });
  }

  function makeCategorySelect(selected) {
    const select = document.createElement("select");
    select.required = true;
    Object.keys(categories).forEach(function (category) {
      const option = new Option(category, category, false, category === selected);
      select.add(option);
    });
    return select;
  }

  function categoryStyle(category) {
    const colors = categories[category] || categories.Lainnya;
    return "--category-color:" + colors.color + ";--category-bg:" + colors.bg + ";--category-text:" + colors.text + ";";
  }

  function renderLegend() {
    const legend = document.getElementById("calendar-legend");
    legend.replaceChildren();
    Object.keys(categories).forEach(function (category) {
      const item = document.createElement("span");
      item.className = "calendar-legend-item";
      item.style.cssText = categoryStyle(category);
      const dot = document.createElement("span");
      dot.className = "calendar-legend-dot";
      item.append(dot, document.createTextNode(category));
      legend.appendChild(item);
    });
  }

  function createEmpty(message) {
    const empty = document.createElement("p");
    empty.className = "calendar-empty";
    empty.textContent = message;
    return empty;
  }

  function renderCalendarGrid() {
    calendarGrid.replaceChildren();
    ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"].forEach(function (day) {
      const weekday = document.createElement("div");
      weekday.className = "calendar-weekday";
      weekday.setAttribute("role", "columnheader");
      weekday.textContent = day;
      calendarGrid.appendChild(weekday);
    });

    const [year, month] = monthInput.value.split("-").map(Number);
    const first = new Date(year, month - 1, 1);
    const offset = (first.getDay() + 6) % 7;
    const dayCount = new Date(year, month, 0).getDate();
    const cellCount = Math.ceil((offset + dayCount) / 7) * 7;
    const today = dateValue(new Date());
    const eventsByDate = new Map();
    calendarEvents.forEach(function (event) {
      const start = localDate(event.tanggal_mulai);
      const end = localDate(event.tanggal_selesai);
      const cursor = new Date(Math.max(start.getTime(), new Date(year, month - 1, 1).getTime()));
      const last = new Date(Math.min(end.getTime(), new Date(year, month, 0).getTime()));
      while (cursor <= last) {
        const key = dateValue(cursor);
        if (!eventsByDate.has(key)) eventsByDate.set(key, []);
        eventsByDate.get(key).push(event);
        cursor.setDate(cursor.getDate() + 1);
      }
    });

    for (let index = 0; index < cellCount; index++) {
      const date = new Date(year, month - 1, index - offset + 1);
      const value = dateValue(date);
      const cell = document.createElement("div");
      cell.className = "calendar-day";
      cell.setAttribute("role", "gridcell");
      if (date.getMonth() !== month - 1) cell.classList.add("is-outside");
      if (value === today) cell.classList.add("is-today");
      const number = document.createElement("span");
      number.className = "calendar-day-number";
      number.textContent = String(date.getDate());
      cell.appendChild(number);
      const dayEvents = eventsByDate.get(value) || [];
      const eventContainer = document.createElement("div");
      eventContainer.className = "calendar-day-events";
      dayEvents.slice(0, 3).forEach(function (event) {
        const chip = document.createElement("span");
        chip.className = "calendar-day-event" + (event.is_cancelled ? " is-cancelled" : "");
        chip.style.cssText = categoryStyle(event.kategori);
        chip.textContent = event.nama_kegiatan;
        chip.title = event.nama_kegiatan + (event.keterangan ? " — " + event.keterangan : "");
        eventContainer.appendChild(chip);
      });
      if (dayEvents.length > 3) {
        const more = document.createElement("span");
        more.className = "calendar-day-more";
        more.textContent = "+" + (dayEvents.length - 3) + " kegiatan";
        eventContainer.appendChild(more);
      }
      cell.appendChild(eventContainer);
      calendarGrid.appendChild(cell);
    }
  }

  function openEventDialog(event) {
    activeEvent = event;
    document.getElementById("event-dialog-title").textContent = event.nama_kegiatan;
    document.getElementById("edit-event-name").value = event.nama_kegiatan;
    document.getElementById("edit-event-start").value = event.tanggal_mulai;
    document.getElementById("edit-event-end").value = event.tanggal_selesai;
    document.getElementById("edit-event-start-time").value = event.jam_mulai ? String(event.jam_mulai).slice(0, 5) : "";
    document.getElementById("edit-event-end-time").value = event.jam_selesai ? String(event.jam_selesai).slice(0, 5) : "";
    const categorySelect = document.getElementById("edit-event-category");
    categorySelect.replaceChildren();
    Object.keys(categories).forEach(function (category) {
      categorySelect.add(new Option(category, category, false, category === event.kategori));
    });
    document.getElementById("edit-event-description").value = event.keterangan || "";
    document.getElementById("edit-event-semester-end").checked = Boolean(event.is_semester_end);
    document.getElementById("edit-event-reason").value = "";
    document.getElementById("event-dialog-message").textContent = "";
    document.getElementById("cancel-event-button").disabled = Boolean(event.semester_incremented_at);
    dialog.showModal();
  }

  function renderEvents() {
    eventList.replaceChildren();
    const [year, month] = monthInput.value.split("-").map(Number);
    const monthName = new Date(year, month - 1, 1).toLocaleDateString("id-ID", { month: "long", year: "numeric" });
    document.getElementById("event-count").textContent = calendarEvents.length + " kegiatan pada " + monthName;
    if (!calendarEvents.length) {
      eventList.appendChild(createEmpty("Tidak ada kegiatan pada bulan ini."));
      return;
    }
    calendarEvents.forEach(function (event) {
      const item = document.createElement("article");
      item.className = "calendar-event-item";
      const date = document.createElement("div");
      date.className = "calendar-event-date";
      date.textContent = formatSchedule(event);
      const copy = document.createElement("div");
      copy.className = "calendar-event-copy";
      const title = document.createElement("div");
      title.className = "calendar-event-title" + (event.is_cancelled ? " is-cancelled" : "");
      title.append(document.createTextNode(event.nama_kegiatan));
      const badge = document.createElement("span");
      badge.className = "calendar-category-badge";
      badge.style.cssText = categoryStyle(event.kategori);
      badge.textContent = event.kategori || "Lainnya";
      title.appendChild(badge);
      copy.appendChild(title);
      if (event.keterangan) {
        const description = document.createElement("p");
        description.className = "calendar-event-description";
        description.textContent = event.keterangan;
        copy.appendChild(description);
      }
      if (event.is_cancelled) {
        const cancelled = document.createElement("div");
        cancelled.className = "calendar-event-status";
        cancelled.textContent = "DIBATALKAN";
        copy.appendChild(cancelled);
      } else if (event.is_semester_end) {
        const rollover = document.createElement("div");
        rollover.className = "calendar-event-status";
        rollover.textContent = event.semester_incremented_at ? "Semester telah diperbarui" : "Pemicu kenaikan semester";
        rollover.style.color = "#477553";
        copy.appendChild(rollover);
      }
      item.append(date, copy);
      if (isAdmin && !event.is_cancelled) {
        const actions = document.createElement("div");
        actions.className = "calendar-event-actions";
        const edit = document.createElement("button");
        edit.type = "button";
        edit.className = "calendar-edit-button";
        const semesterProcessed = Boolean(event.is_semester_end && event.semester_incremented_at);
        edit.textContent = semesterProcessed ? "Semester diproses" : "Kelola";
        edit.disabled = semesterProcessed;
        if (!semesterProcessed) {
          edit.addEventListener("click", function () { openEventDialog(event); });
        }
        actions.appendChild(edit);
        item.appendChild(actions);
      }
      eventList.appendChild(item);
    });
  }

  function changeSummary(change) {
    if (change.change_type === "calendar_published") {
      const value = change.new_value || {};
      return "Diterbitkan: " + (value.judul || "Kalender akademik") + (value.tahun_ajaran ? " · TA " + value.tahun_ajaran : "");
    }
    const value = change.new_value || change.old_value || {};
    if (change.change_type === "cancelled") return "Dibatalkan: " + (value.nama_kegiatan || "Kegiatan");
    return "Jadwal diperbarui: " + (value.nama_kegiatan || "Kegiatan")
      + (value.tanggal_mulai ? " · " + formatSchedule(value) : "");
  }

  function renderChanges(changes) {
    changesList.replaceChildren();
    if (!changes.length) {
      changesList.appendChild(createEmpty("Belum ada perubahan yang tercatat."));
      return;
    }
    changes.forEach(function (change) {
      const item = document.createElement("article");
      item.className = "calendar-change-item";
      const time = document.createElement("time");
      time.className = "calendar-change-time";
      time.dateTime = String(change.created_at).replace(" ", "T");
      time.textContent = formatDateTime(change.created_at);
      const copy = document.createElement("div");
      const title = document.createElement("div");
      title.className = "calendar-change-title";
      title.textContent = changeSummary(change);
      copy.appendChild(title);
      if (change.change_type === "updated" && change.old_value && change.new_value) {
        const oldSchedule = document.createElement("div");
        oldSchedule.className = "calendar-change-detail";
        oldSchedule.textContent = "Sebelumnya: " + formatSchedule(change.old_value);
        const newSchedule = document.createElement("div");
        newSchedule.className = "calendar-change-detail";
        newSchedule.textContent = "Menjadi: " + formatSchedule(change.new_value);
        copy.append(oldSchedule, newSchedule);
      }
      const reason = document.createElement("div");
      reason.className = "calendar-change-reason";
      reason.textContent = "Alasan: " + change.reason;
      copy.appendChild(reason);
      item.append(time, copy);
      changesList.appendChild(item);
    });
  }

  async function loadCalendar(polling) {
    if (!monthInput.value) return;
    const query = new URLSearchParams({ bulan: monthInput.value });
    const data = await requestJson("api/academic-calendar.php?" + query.toString());
    const previousChangeId = latestChangeId;
    latestChangeId = Number(data.latest_change_id) || 0;
    const cursorKey = "academic-calendar-change-" + String(currentUser ? currentUser.id : "user");
    if (!polling && monthIsInitial) {
      const stored = sessionStorage.getItem(cursorKey);
      if (stored) latestChangeId = Math.max(Number(stored) || 0, latestChangeId);
      monthIsInitial = false;
    }
    calendarEvents = data.events || [];
    document.getElementById("calendar-title").textContent = data.calendar ? data.calendar.judul : "Kalender Akademik";
    document.getElementById("calendar-year").textContent = data.calendar
      ? "Tahun ajaran " + data.calendar.tahun_ajaran + " · Sumber: " + data.calendar.nama_berkas
      : "Admin belum menerbitkan kalender akademik.";
    document.getElementById("semester-message").textContent = data.processed_rollovers
      ? "Semester santri telah diperbarui berdasarkan " + data.processed_rollovers + " kegiatan akhir semester."
      : "";
    renderCalendarGrid();
    renderEvents();
    renderChanges(data.changes || []);
    if (isAdmin && data.draft) {
      currentDraftId = Number(data.draft.id);
      document.getElementById("import-message").textContent =
        "Draf tersimpan: " + data.draft.judul + " · TA " + data.draft.tahun_ajaran + ". Terbitkan setelah diperiksa.";
      document.getElementById("publish-draft").hidden = false;
      document.getElementById("publish-draft").disabled = false;
    }
    if (polling && latestChangeId > previousChangeId && previousChangeId > 0) {
      const recent = (data.changes || []).find(function (change) { return Number(change.id) > previousChangeId; });
      if (recent) showMessage(pageMessage, changeSummary(recent) + ". " + recent.reason, "success");
    }
    sessionStorage.setItem(cursorKey, String(latestChangeId));
  }

  function renderPreview() {
    const preview = document.getElementById("import-preview");
    preview.replaceChildren();
    const summary = document.createElement("div");
    summary.className = "calendar-preview-summary";
    summary.textContent = previewEvents.length + " kegiatan terbaca. Periksa tanggal, jam opsional, kategori, keterangan, dan penanda akhir semester.";
    preview.appendChild(summary);
    previewEvents.forEach(function (event, index) {
      const row = document.createElement("div");
      row.className = "calendar-preview-row";
      row.dataset.eventIndex = String(index);
      const start = document.createElement("input");
      start.type = "date";
      start.value = event.tanggal_mulai;
      start.required = true;
      start.setAttribute("aria-label", "Tanggal mulai");
      const startTime = document.createElement("input");
      startTime.type = "time";
      startTime.value = event.jam_mulai ? String(event.jam_mulai).slice(0, 5) : "";
      startTime.setAttribute("aria-label", "Jam mulai");
      const end = document.createElement("input");
      end.type = "date";
      end.value = event.tanggal_selesai;
      end.required = true;
      end.setAttribute("aria-label", "Tanggal selesai");
      const endTime = document.createElement("input");
      endTime.type = "time";
      endTime.value = event.jam_selesai ? String(event.jam_selesai).slice(0, 5) : "";
      endTime.setAttribute("aria-label", "Jam selesai");
      const name = document.createElement("input");
      name.type = "text";
      name.maxLength = 1000;
      name.value = event.nama_kegiatan;
      name.required = true;
      name.setAttribute("aria-label", "Nama kegiatan");
      const category = makeCategorySelect(event.kategori);
      category.setAttribute("aria-label", "Kategori");
      const description = document.createElement("textarea");
      description.maxLength = 5000;
      description.value = event.keterangan || "";
      description.placeholder = "Keterangan";
      description.setAttribute("aria-label", "Keterangan");
      const semesterLabel = document.createElement("label");
      const semesterEnd = document.createElement("input");
      semesterEnd.type = "checkbox";
      semesterEnd.checked = Boolean(event.akhir_semester);
      semesterEnd.setAttribute("aria-label", "Akhir semester");
      semesterLabel.append(semesterEnd, document.createTextNode("Akhir semester"));
      row.append(start, startTime, end, endTime, name, category, description, semesterLabel);
      preview.appendChild(row);
    });
    preview.hidden = false;
    document.getElementById("save-draft").hidden = false;
    document.getElementById("publish-draft").hidden = true;
    currentDraftId = null;
  }

  function collectPreviewEvents() {
    return Array.from(document.querySelectorAll(".calendar-preview-row")).map(function (row) {
      const fields = row.querySelectorAll("input, select, textarea");
      return {
        tanggal_mulai: fields[0].value,
        jam_mulai: fields[1].value || null,
        tanggal_selesai: fields[2].value,
        jam_selesai: fields[3].value || null,
        nama_kegiatan: fields[4].value.trim(),
        kategori: fields[5].value,
        keterangan: fields[6].value.trim(),
        akhir_semester: fields[7].checked,
      };
    });
  }

  async function saveDraft() {
    const form = document.getElementById("calendar-import-form");
    const file = document.getElementById("calendar-file").files[0];
    if (!file || !form.reportValidity()) return;
    const events = collectPreviewEvents();
    if (events.some(function (event) {
      return !event.nama_kegiatan || !event.tanggal_mulai || !event.tanggal_selesai
        || event.tanggal_mulai > event.tanggal_selesai
        || Boolean(event.jam_mulai) !== Boolean(event.jam_selesai)
        || (event.tanggal_mulai === event.tanggal_selesai && event.jam_mulai && event.jam_mulai > event.jam_selesai);
    })) {
      showMessage(document.getElementById("import-message"), "Periksa nama, rentang tanggal, dan pasangan jam kegiatan pada pratinjau.", "error");
      return;
    }
    const button = document.getElementById("save-draft");
    button.disabled = true;
    showMessage(document.getElementById("import-message"), "Menyimpan draf kalender...", "");
    try {
      const result = await postCalendar({
        action: "save_draft",
        judul: document.getElementById("calendar-import-title").value.trim(),
        tahun_ajaran: document.getElementById("calendar-import-year").value.trim(),
        nama_berkas: file.name,
        events: events,
      });
      currentDraftId = Number(result.draft_id);
      showMessage(document.getElementById("import-message"), result.event_count + " kegiatan tersimpan sebagai draf. Tinjau kembali sebelum diterbitkan.", "success");
      document.getElementById("publish-draft").hidden = false;
      document.getElementById("publish-draft").disabled = false;
    } catch (error) {
      showMessage(document.getElementById("import-message"), error.message, "error");
    } finally {
      button.disabled = false;
    }
  }

  async function publishDraft() {
    if (!currentDraftId || !window.confirm("Terbitkan kalender ini untuk semua pengguna? Kalender yang sedang aktif akan diarsipkan.")) return;
    const button = document.getElementById("publish-draft");
    button.disabled = true;
    try {
      const result = await postCalendar({ action: "publish", draft_id: currentDraftId });
      currentDraftId = null;
      document.getElementById("import-preview").hidden = true;
      document.getElementById("save-draft").hidden = true;
      document.getElementById("publish-draft").hidden = true;
      showMessage(document.getElementById("import-message"), "Kalender berhasil diterbitkan. " + pushDeliveryMessage(result.push), "success");
      showMessage(pageMessage, "Kalender akademik telah diperbarui. " + pushDeliveryMessage(result.push), "success");
      await loadCalendar(false);
    } catch (error) {
      showMessage(document.getElementById("import-message"), error.message, "error");
      button.disabled = false;
    }
  }

  async function submitEventChange(action) {
    if (!activeEvent) return;
    const reason = document.getElementById("edit-event-reason").value.trim();
    if (!reason) {
      document.getElementById("edit-event-reason").focus();
      showMessage(document.getElementById("event-dialog-message"), "Alasan perubahan wajib diisi.", "error");
      return;
    }
    if (action === "cancel_event" && !window.confirm("Batalkan kegiatan \"" + activeEvent.nama_kegiatan + "\"? Alasan akan dicatat pada riwayat.")) return;
    const payload = { action: action, event_id: activeEvent.id, reason: reason };
    if (action === "update_event") {
      if (!eventForm.reportValidity()) return;
      payload.nama_kegiatan = document.getElementById("edit-event-name").value.trim();
      payload.tanggal_mulai = document.getElementById("edit-event-start").value;
      payload.tanggal_selesai = document.getElementById("edit-event-end").value;
      payload.jam_mulai = document.getElementById("edit-event-start-time").value || null;
      payload.jam_selesai = document.getElementById("edit-event-end-time").value || null;
      payload.kategori = document.getElementById("edit-event-category").value;
      payload.keterangan = document.getElementById("edit-event-description").value.trim();
      payload.akhir_semester = document.getElementById("edit-event-semester-end").checked;
    }
    const saveButton = document.getElementById("save-event");
    saveButton.disabled = true;
    try {
      const result = await postCalendar(payload);
      dialog.close();
      const actionMessage = action === "cancel_event"
        ? "Kegiatan dibatalkan dan riwayat perubahan disimpan."
        : "Jadwal diperbarui dan riwayat perubahan disimpan.";
      showMessage(pageMessage, actionMessage + " " + pushDeliveryMessage(result.push), "success");
      await loadCalendar(false);
    } catch (error) {
      showMessage(document.getElementById("event-dialog-message"), error.message, "error");
    } finally {
      saveButton.disabled = false;
    }
  }

  function decodeApplicationKey(value) {
    const padded = value + "=".repeat((4 - value.length % 4) % 4);
    const binary = atob(padded.replace(/-/g, "+").replace(/_/g, "/"));
    return Uint8Array.from(binary, function (character) { return character.charCodeAt(0); });
  }

  async function updatePushButton() {
    const button = document.getElementById("notification-button");
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
      button.disabled = true;
      button.title = "Browser ini tidak mendukung Web Push. Pembaruan tetap muncul saat kalender terbuka.";
      return;
    }
    try {
      pushConfiguration = await requestJson("api/academic-calendar-push.php");
      if (!pushConfiguration.configured) {
        button.disabled = true;
        button.title = "Admin server perlu mengatur VAPID agar notifikasi perangkat dapat dikirim.";
        showMessage(pageMessage, "Notifikasi perangkat belum dikonfigurasi di server. Pembaruan tetap muncul ketika kalender terbuka.", "");
        return;
      }
      pushRegistration = await navigator.serviceWorker.register("service-worker.js");
      const subscription = await pushRegistration.pushManager.getSubscription();
      if (subscription) {
        const status = await requestJson("api/academic-calendar-push.php?endpoint=" + encodeURIComponent(subscription.endpoint));
        pushConfiguration.subscribed = status.subscribed;
      } else {
        pushConfiguration.subscribed = false;
      }
      button.textContent = pushConfiguration.subscribed ? "Nonaktifkan notifikasi" : "Aktifkan notifikasi";
    } catch (error) {
      button.disabled = true;
      button.title = error.message;
    }
  }

  async function togglePush() {
    const button = document.getElementById("notification-button");
    button.disabled = true;
    try {
      if (!pushRegistration) pushRegistration = await navigator.serviceWorker.register("service-worker.js");
      const existing = await pushRegistration.pushManager.getSubscription();
      if (existing && pushConfiguration.subscribed) {
        await requestJson("api/academic-calendar-push.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "unsubscribe", subscription: existing }),
        });
        await existing.unsubscribe();
        button.textContent = "Aktifkan notifikasi";
        showMessage(pageMessage, "Notifikasi kalender dinonaktifkan pada perangkat ini.", "success");
        return;
      }
      if (existing) {
        await requestJson("api/academic-calendar-push.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "subscribe", subscription: existing }),
        });
        pushConfiguration.subscribed = true;
        button.textContent = "Nonaktifkan notifikasi";
        showMessage(pageMessage, "Notifikasi kalender aktif pada perangkat ini.", "success");
        return;
      }
      const permission = await Notification.requestPermission();
      if (permission !== "granted") throw new Error("Izin notifikasi tidak diberikan. Ubah izin notifikasi dari pengaturan browser.");
      const subscription = await pushRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: decodeApplicationKey(pushConfiguration.public_key),
      });
      await requestJson("api/academic-calendar-push.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "subscribe", subscription: subscription }),
      });
      button.textContent = "Nonaktifkan notifikasi";
      showMessage(pageMessage, "Notifikasi kalender aktif pada perangkat ini.", "success");
    } catch (error) {
      showMessage(pageMessage, error.message || "Notifikasi tidak dapat diaktifkan.", "error");
      await updatePushButton();
    } finally {
      button.disabled = false;
    }
  }

  function closeMenu() {
    document.getElementById("sidebar").classList.remove("is-open");
    document.getElementById("sidebar-scrim").classList.remove("is-visible");
    document.getElementById("menu-button").setAttribute("aria-expanded", "false");
  }

  async function initialize() {
    const session = await requestJson("api/session.php");
    currentUser = session.user;
    if (!currentUser) {
      window.location.replace("index.html");
      return;
    }
    isAdmin = String(currentUser.role || "").toLowerCase() === "admin";
    document.querySelectorAll(".admin-nav").forEach(function (link) { link.hidden = !isAdmin; });
    document.getElementById("import-card").hidden = !isAdmin;
    const name = currentUser.name || currentUser.email || "Pengguna";
    const initials = name.trim().slice(0, 1).toLocaleUpperCase("id");
    document.getElementById("sidebar-name").textContent = name;
    document.getElementById("topbar-name").textContent = name;
    document.getElementById("sidebar-role").textContent = roleLabel(currentUser.role);
    document.getElementById("sidebar-avatar").textContent = initials;
    document.getElementById("top-avatar").textContent = initials;
    document.getElementById("today-label").textContent = new Date().toLocaleDateString("id-ID", { weekday: "long", day: "numeric", month: "short" });
    document.getElementById("current-year").textContent = String(new Date().getFullYear());
    const today = new Date();
    monthInput.value = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, "0");
    const academicStart = today.getMonth() >= 6 ? today.getFullYear() : today.getFullYear() - 1;
    document.getElementById("calendar-import-year").value = academicStart + "-" + (academicStart + 1);
    renderLegend();
    await loadCalendar(false);
    await updatePushButton();
    window.setInterval(function () {
      loadCalendar(true).catch(function (error) { showMessage(pageMessage, error.message, "error"); });
    }, 30000);
  }

  monthInput.addEventListener("change", function () {
    loadCalendar(false).catch(function (error) { showMessage(pageMessage, error.message, "error"); });
  });
  document.getElementById("previous-month").addEventListener("click", function () {
    const date = localDate(monthInput.value + "-01");
    date.setMonth(date.getMonth() - 1);
    monthInput.value = date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0");
    monthInput.dispatchEvent(new Event("change"));
  });
  document.getElementById("next-month").addEventListener("click", function () {
    const date = localDate(monthInput.value + "-01");
    date.setMonth(date.getMonth() + 1);
    monthInput.value = date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0");
    monthInput.dispatchEvent(new Event("change"));
  });
  document.getElementById("calendar-file").addEventListener("change", async function () {
    const file = this.files && this.files[0];
    const titleInput = document.getElementById("calendar-import-title");
    document.getElementById("import-preview").hidden = true;
    document.getElementById("save-draft").hidden = true;
    document.getElementById("publish-draft").hidden = true;
    currentDraftId = null;
    previewEvents = [];
    if (!file) return;
    showMessage(document.getElementById("import-message"), "Membaca file dan menyiapkan pratinjau...", "");
    try {
      const parsed = await window.parseAcademicCalendarXlsx(file);
      previewEvents = parsed.events;
      if (!titleInput.value || titleInput.value === "Kalender Akademik") titleInput.value = parsed.judul || "Kalender Akademik";
      renderPreview();
      showMessage(document.getElementById("import-message"), "File terbaca. Periksa dan rapikan semua kegiatan sebelum menyimpan draf.", "success");
    } catch (error) {
      showMessage(document.getElementById("import-message"), error.message || "File Excel gagal dibaca.", "error");
    }
  });
  document.getElementById("save-draft").addEventListener("click", saveDraft);
  document.getElementById("publish-draft").addEventListener("click", publishDraft);
  document.getElementById("calendar-import-form").addEventListener("submit", function (event) {
    event.preventDefault();
    saveDraft();
  });
  eventForm.addEventListener("submit", function (event) {
    event.preventDefault();
    submitEventChange("update_event");
  });
  document.getElementById("cancel-event-button").addEventListener("click", function () {
    submitEventChange("cancel_event");
  });
  document.getElementById("close-event-dialog").addEventListener("click", function () { dialog.close(); });
  document.getElementById("dismiss-event-dialog").addEventListener("click", function () { dialog.close(); });
  dialog.addEventListener("click", function (event) { if (event.target === dialog) dialog.close(); });
  document.getElementById("notification-button").addEventListener("click", togglePush);
  document.getElementById("menu-button").addEventListener("click", function () {
    document.getElementById("sidebar").classList.add("is-open");
    document.getElementById("sidebar-scrim").classList.add("is-visible");
    this.setAttribute("aria-expanded", "true");
  });
  document.getElementById("sidebar-scrim").addEventListener("click", closeMenu);
  document.addEventListener("keydown", function (event) { if (event.key === "Escape") closeMenu(); });
  document.getElementById("logout-button").addEventListener("click", async function () {
    this.disabled = true;
    try {
      const response = await fetch("api/logout.php", { method: "POST", credentials: "same-origin" });
      if (!response.ok) throw new Error("Sesi belum dapat diakhiri.");
      window.location.replace("index.html");
    } catch (error) {
      showMessage(pageMessage, error.message, "error");
      this.disabled = false;
    }
  });

  initialize().catch(function (error) {
    showMessage(pageMessage, error.message || "Kalender gagal dimuat.", "error");
  });
});