"use strict";

(function () {
  const decoder = new TextDecoder();
  const categoryRules = [
    { pattern: /uas|uts|ujian|ujian/i, category: "Ujian" },
    { pattern: /libur|cuti|idul|ramadhan|ramadan/i, category: "Libur" },
    { pattern: /kuliah|akademik|perkuliahan|krs|khs/i, category: "Akademik" },
    { pattern: /ma.?had|santri|munazharah|orientasi|wisuda|kegiatan/i, category: "Kegiatan Ma'had" },
  ];

  function findEndOfCentralDirectory(view) {
    const minimumOffset = Math.max(0, view.byteLength - 65557);
    for (let offset = view.byteLength - 22; offset >= minimumOffset; offset--) {
      if (view.getUint32(offset, true) === 0x06054b50) return offset;
    }
    throw new Error("Berkas bukan arsip XLSX yang valid.");
  }

  async function unzipEntries(buffer) {
    const view = new DataView(buffer);
    const endOffset = findEndOfCentralDirectory(view);
    const entryCount = view.getUint16(endOffset + 10, true);
    let offset = view.getUint32(endOffset + 16, true);
    const entries = new Map();
    let totalUncompressedSize = 0;
    if (entryCount > 2000) throw new Error("Berkas XLSX memiliki terlalu banyak bagian.");

    for (let index = 0; index < entryCount; index++) {
      if (view.getUint32(offset, true) !== 0x02014b50) throw new Error("Struktur XLSX tidak dapat dibaca.");
      const method = view.getUint16(offset + 10, true);
      const compressedSize = view.getUint32(offset + 20, true);
      const uncompressedSize = view.getUint32(offset + 24, true);
      const nameLength = view.getUint16(offset + 28, true);
      const extraLength = view.getUint16(offset + 30, true);
      const commentLength = view.getUint16(offset + 32, true);
      const localOffset = view.getUint32(offset + 42, true);
      totalUncompressedSize += uncompressedSize;
      if (totalUncompressedSize > 20 * 1024 * 1024) {
        throw new Error("Ukuran isi XLSX setelah dibuka melebihi batas aman.");
      }
      const name = decoder.decode(new Uint8Array(buffer, offset + 46, nameLength));
      const localNameLength = view.getUint16(localOffset + 26, true);
      const localExtraLength = view.getUint16(localOffset + 28, true);
      const dataOffset = localOffset + 30 + localNameLength + localExtraLength;
      const compressed = buffer.slice(dataOffset, dataOffset + compressedSize);
      if (method === 0) {
        entries.set(name, compressed);
      } else if (method === 8) {
        if (typeof DecompressionStream !== "function") {
          throw new Error("Browser ini belum mendukung pembacaan XLSX. Gunakan browser versi terbaru.");
        }
        const stream = new Blob([compressed]).stream().pipeThrough(new DecompressionStream("deflate-raw"));
        entries.set(name, await new Response(stream).arrayBuffer());
      } else {
        throw new Error("Metode kompresi XLSX ini belum didukung.");
      }
      offset += 46 + nameLength + extraLength + commentLength;
    }
    return entries;
  }

  function parseXml(buffer) {
    if (!buffer) throw new Error("Lembar kalender tidak ditemukan di dalam XLSX.");
    const xml = new DOMParser().parseFromString(decoder.decode(buffer), "application/xml");
    if (xml.querySelector("parsererror")) throw new Error("Ada bagian berkas XLSX yang tidak valid.");
    return xml;
  }

  function descendants(element, localName) {
    return Array.from(element.getElementsByTagNameNS("*", localName));
  }

  function columnName(reference) {
    const match = reference.match(/^([A-Z]+)\d+$/);
    return match ? match[1] : "";
  }

  function normalizeHeader(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLocaleLowerCase("id")
      .replace(/[^a-z0-9]/g, "");
  }

  function parseDate(value) {
    const text = String(value || "").trim();
    if (!text) return "";
    if (/^\d+(?:\.\d+)?$/.test(text) && Number(text) > 10000) {
      const date = new Date(Date.UTC(1899, 11, 30) + Math.floor(Number(text)) * 86400000);
      return date.toISOString().slice(0, 10);
    }
    let match = text.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
    if (match) return makeDate(Number(match[1]), Number(match[2]), Number(match[3]));
    match = text.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
    if (match) return makeDate(Number(match[3]), Number(match[2]), Number(match[1]));
    const months = {
      januari: 1, jan: 1, februari: 2, feb: 2, maret: 3, mar: 3,
      april: 4, apr: 4, mei: 5, juni: 6, jun: 6, juli: 7, jul: 7,
      agustus: 8, agu: 8, september: 9, sep: 9, oktober: 10, okt: 10,
      november: 11, nov: 11, desember: 12, des: 12,
    };
    match = text.toLocaleLowerCase("id").match(/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/);
    if (match && months[match[2]]) return makeDate(Number(match[3]), months[match[2]], Number(match[1]));
    throw new Error("Format tanggal tidak dikenali: " + text);
  }

  function makeDate(year, month, day) {
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() + 1 !== month || date.getUTCDate() !== day) {
      throw new Error("Tanggal kegiatan tidak valid: " + [year, month, day].join("-"));
    }
    return date.toISOString().slice(0, 10);
  }

  function parseTime(value) {
    const text = String(value || "").trim();
    if (!text) return "";
    if (/^\d+(?:\.\d+)?$/.test(text) && Number(text) >= 0 && Number(text) < 1) {
      const minutes = Math.round(Number(text) * 1440) % 1440;
      return String(Math.floor(minutes / 60)).padStart(2, "0")
        + ":" + String(minutes % 60).padStart(2, "0");
    }
    const match = text.match(/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/);
    if (!match) throw new Error("Format jam tidak dikenali: " + text);
    return String(Number(match[1])).padStart(2, "0") + ":" + match[2];
  }

  function readRows(sheet, sharedStrings) {
    const rows = new Map();
    for (const row of descendants(sheet, "row")) {
      const cells = new Map();
      for (const cell of descendants(row, "c")) {
        const reference = cell.getAttribute("r");
        const column = columnName(reference);
        if (!column) continue;
        const valueElement = descendants(cell, "v")[0];
        let value = valueElement ? valueElement.textContent : descendants(cell, "t").map((text) => text.textContent).join("");
        if (cell.getAttribute("t") === "s" && value !== "") value = sharedStrings[Number(value)] || "";
        cells.set(column, String(value || "").trim());
      }
      rows.set(Number(row.getAttribute("r")), cells);
    }
    return rows;
  }

  function inferCategory(name) {
    const rule = categoryRules.find((item) => item.pattern.test(name));
    return rule ? rule.category : "Lainnya";
  }

  function isSemesterEnd(name) {
    return /\b(?:UAS|ujian\s+(?:tulis\s+)?akhir\s+semester)\b/i.test(name)
      && !/\b(?:UAS|ujian\s+akhir\s+semester)\s*\/\s*(?:UTS|ujian\s+tengah\s+semester)\b/i.test(name);
  }

  function locateHeaders(rows) {
    const aliases = {
      start: ["tanggalmulai", "mulaikegiatan", "tanggalawal", "tanggal"],
      end: ["tanggalselesai", "tanggalakhir", "sampaitanggal"],
      name: ["namakegiatan", "kegiatannama", "kegiatan", "agenda"],
      description: ["keterangan", "deskripsi", "catatan"],
      category: ["kategori", "jenis"],
      startTime: ["jammulai", "waktumulai"],
      endTime: ["jamselesai", "waktuselesai"],
      semesterEnd: ["akhirsemester", "pemicuakhirsemester"],
    };
    for (const [rowNumber, cells] of rows) {
      if (rowNumber > 30) break;
      const headers = new Map();
      cells.forEach((value, column) => {
        const normalized = normalizeHeader(value);
        Object.keys(aliases).forEach((key) => {
          if (aliases[key].includes(normalized)) headers.set(key, column);
        });
      });
      if (headers.has("start") && headers.has("name")) return { rowNumber, headers };
    }
    return null;
  }

  function parseStandardRows(rows, headerInfo) {
    const events = [];
    for (const [rowNumber, cells] of rows) {
      if (rowNumber <= headerInfo.rowNumber) continue;
      const get = (key) => {
        const column = headerInfo.headers.get(key);
        return column ? (cells.get(column) || "") : "";
      };
      const name = get("name");
      if (!name) continue;
      const start = parseDate(get("start"));
      const end = get("end") ? parseDate(get("end")) : start;
      const startTime = parseTime(get("startTime"));
      const endTime = parseTime(get("endTime"));
      if (!start || !end || start > end) {
        throw new Error("Tanggal mulai/selesai kegiatan \"" + name + "\" pada baris " + rowNumber + " tidak valid.");
      }
      if (Boolean(startTime) !== Boolean(endTime) || (start === end && startTime && startTime > endTime)) {
        throw new Error("Jam mulai dan selesai kegiatan \"" + name + "\" pada baris " + rowNumber + " harus diisi berpasangan dan berurutan.");
      }
      const rawCategory = get("category");
      const category = rawCategory ? normalizeCategory(rawCategory) : inferCategory(name);
      const endFlag = get("semesterEnd").toLocaleLowerCase("id");
      events.push({
        nama_kegiatan: name,
        tanggal_mulai: start,
        tanggal_selesai: end,
        jam_mulai: startTime || null,
        jam_selesai: endTime || null,
        keterangan: get("description"),
        kategori: category,
        akhir_semester: ["1", "ya", "yes", "true", "x"].includes(endFlag) || (!endFlag && isSemesterEnd(name)),
      });
    }
    return events;
  }

  function normalizeCategory(value) {
    const normalized = normalizeHeader(value);
    const known = [
      ["akademik", "Akademik"],
      ["ujian", "Ujian"],
      ["libur", "Libur"],
      ["kegiatanmahad", "Kegiatan Ma'had"],
      ["kegiatan", "Kegiatan Ma'had"],
      ["lainnya", "Lainnya"],
    ];
    const result = known.find((item) => item[0] === normalized);
    return result ? result[1] : "Lainnya";
  }

  function parseLegacyRows(rows) {
    let currentMonth = null;
    const events = [];
    const seen = new Set();
    for (const [rowNumber, cells] of rows) {
      const monthValue = cells.get("B") || "";
      if (/^\d+(?:\.\d+)?$/.test(monthValue) && Number(monthValue) > 30000) {
        const date = new Date(Date.UTC(1899, 11, 30) + Number(monthValue) * 86400000);
        currentMonth = { year: date.getUTCFullYear(), month: date.getUTCMonth() + 1 };
      }
      const name = cells.get("J") || "";
      if (!name || normalizeHeader(name) === "kegiatan" || !currentMonth) continue;
      const dateText = cells.get("I") || "";
      const dateMatch = dateText.match(/^(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?$/);
      if (!dateMatch) continue;
      const dayStart = Number(dateMatch[1]);
      const dayEnd = Number(dateMatch[2] || dayStart);
      let endMonth = currentMonth.month;
      let endYear = currentMonth.year;
      if (dayEnd < dayStart) {
        endMonth++;
        if (endMonth > 12) {
          endMonth = 1;
          endYear++;
        }
      }
      const start = makeDate(currentMonth.year, currentMonth.month, dayStart);
      const end = makeDate(endYear, endMonth, dayEnd);
      const key = [start, end, name].join("|");
      if (seen.has(key)) continue;
      seen.add(key);
      events.push({
        nama_kegiatan: name,
        tanggal_mulai: start,
        tanggal_selesai: end,
        jam_mulai: null,
        jam_selesai: null,
        keterangan: "",
        kategori: inferCategory(name),
        akhir_semester: isSemesterEnd(name),
      });
    }
    return events;
  }

  async function parseCalendar(file) {
    if (!file || !/\.xlsx$/i.test(file.name)) throw new Error("Pilih berkas Excel .xlsx.");
    if (file.size > 10 * 1024 * 1024) throw new Error("Ukuran berkas maksimal 10 MB.");
    const entries = await unzipEntries(await file.arrayBuffer());
    const workbook = parseXml(entries.get("xl/workbook.xml"));
    const relationships = parseXml(entries.get("xl/_rels/workbook.xml.rels"));
    const firstSheet = descendants(workbook, "sheet")[0];
    if (!firstSheet) throw new Error("Lembar kalender tidak ditemukan.");
    const relationId = firstSheet.getAttribute("r:id") || firstSheet.getAttributeNS(
      "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
      "id"
    );
    const relationship = descendants(relationships, "Relationship")
      .find((item) => item.getAttribute("Id") === relationId);
    if (!relationship) throw new Error("Lembar kalender utama tidak ditemukan.");
    let sheetPath = relationship.getAttribute("Target").replace(/^\//, "");
    if (!sheetPath.startsWith("xl/")) sheetPath = "xl/" + sheetPath;
    const normalizedParts = [];
    sheetPath.split("/").forEach((part) => {
      if (part === "..") normalizedParts.pop();
      else if (part !== ".") normalizedParts.push(part);
    });
    sheetPath = normalizedParts.join("/");
    const sharedStrings = entries.has("xl/sharedStrings.xml")
      ? descendants(parseXml(entries.get("xl/sharedStrings.xml")), "si").map((item) =>
        descendants(item, "t").map((text) => text.textContent).join("")
      )
      : [];
    const rows = readRows(parseXml(entries.get(sheetPath)), sharedStrings);
    const headerInfo = locateHeaders(rows);
    const events = headerInfo ? parseStandardRows(rows, headerInfo) : parseLegacyRows(rows);
    const title = firstSheet.getAttribute("name") || "Kalender Akademik";
    if (!events.length) {
      throw new Error("Kegiatan tidak ditemukan. Gunakan kolom Tanggal Mulai, Tanggal Selesai, Nama Kegiatan, Keterangan, dan Kategori.");
    }
    return { judul: title, nama_berkas: file.name, events: events };
  }

  window.parseAcademicCalendarXlsx = parseCalendar;
})();