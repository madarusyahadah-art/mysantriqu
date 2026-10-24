(function () {
  const decoder = new TextDecoder();

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
    const xml = new DOMParser().parseFromString(decoder.decode(buffer), "application/xml");
    if (xml.querySelector("parsererror")) throw new Error("Ada bagian berkas XLSX yang tidak valid.");
    return xml;
  }

  function descendants(element, localName) {
    return Array.from(element.getElementsByTagNameNS("*", localName));
  }

  function excelDate(serial) {
    const date = new Date(Date.UTC(1899, 11, 30) + serial * 86400000);
    return { year: date.getUTCFullYear(), month: date.getUTCMonth() + 1 };
  }

  function makeDate(year, month, day) {
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() + 1 !== month || date.getUTCDate() !== day) {
      throw new Error("Tanggal kegiatan di kalender tidak valid.");
    }
    return date.toISOString().slice(0, 10);
  }

  function parseEventDates(rawDate, base) {
    const match = rawDate.trim().match(/^(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?$/);
    if (!match || !base) return { start: null, end: null };

    const firstDay = Number(match[1]);
    const lastDay = match[2] ? Number(match[2]) : firstDay;
    let endYear = base.year;
    let endMonth = base.month;
    if (lastDay < firstDay) {
      endMonth++;
      if (endMonth > 12) {
        endMonth = 1;
        endYear++;
      }
    }
    return {
      start: makeDate(base.year, base.month, firstDay),
      end: makeDate(endYear, endMonth, lastDay),
    };
  }

  async function parseCalendar(file) {
    if (!file || !/\.xlsx$/i.test(file.name)) throw new Error("Pilih berkas Excel .xlsx.");
    if (file.size > 10 * 1024 * 1024) throw new Error("Ukuran berkas maksimal 10 MB.");
    const entries = await unzipEntries(await file.arrayBuffer());
    const workbook = parseXml(entries.get("xl/workbook.xml"));
    const relationshipXml = parseXml(entries.get("xl/_rels/workbook.xml.rels"));
    const firstSheet = descendants(workbook, "sheet")[0];
    if (!firstSheet) throw new Error("Lembar kalender tidak ditemukan.");

    const relationId = firstSheet.getAttribute("r:id") || firstSheet.getAttributeNS(
      "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
      "id"
    );
    const relationship = descendants(relationshipXml, "Relationship").find((item) => item.getAttribute("Id") === relationId);
    if (!relationship) throw new Error("Lembar kalender utama tidak ditemukan.");
    let sheetPath = relationship.getAttribute("Target").replace(/^\//, "");
    if (!sheetPath.startsWith("xl/")) sheetPath = "xl/" + sheetPath;
    sheetPath = sheetPath.replace(/^xl\/worksheets\/\.\.\//, "xl/");
    const sheet = parseXml(entries.get(sheetPath));

    const sharedStrings = entries.has("xl/sharedStrings.xml")
      ? descendants(parseXml(entries.get("xl/sharedStrings.xml")), "si").map((item) =>
          descendants(item, "t").map((text) => text.textContent).join("")
        )
      : [];
    const rows = new Map();
    for (const row of descendants(sheet, "row")) {
      const cells = new Map();
      for (const cell of descendants(row, "c")) {
        const reference = cell.getAttribute("r");
        const match = reference.match(/^([A-Z]+)(\d+)$/);
        if (!match) continue;
        const valueElement = descendants(cell, "v")[0];
        let value = valueElement ? valueElement.textContent : descendants(cell, "t").map((text) => text.textContent).join("");
        if (cell.getAttribute("t") === "s" && value !== "") value = sharedStrings[Number(value)];
        cells.set(match[1], value === undefined ? "" : String(value).trim());
      }
      rows.set(Number(row.getAttribute("r")), cells);
    }

    let calendarTitle = firstSheet.getAttribute("name") || "Kalender Akademik";
    let academicYear = "";
    let currentMonth = null;
    const events = [];
    const seen = new Set();

    for (const [rowNumber, cells] of rows) {
      const monthCell = cells.get("B");
      if (monthCell && /^\d+(?:\.\d+)?$/.test(monthCell) && Number(monthCell) > 30000) {
        currentMonth = excelDate(Number(monthCell));
      }
      if (rowNumber === 1 && cells.get("B")) calendarTitle = cells.get("B");
      if (cells.get("B")) {
        const yearMatch = cells.get("B").match(/(20\d{2})\s*[-–]\s*(20\d{2})/);
        if (yearMatch) academicYear = yearMatch[1] + "-" + yearMatch[2];
      }

      const eventName = cells.get("J");
      if (!eventName || ["KEGIATAN", ""].includes(eventName.toUpperCase())) continue;
      const rawDate = cells.get("I") || "";
      const dates = parseEventDates(rawDate, currentMonth);
      const key = [dates.start || "", dates.end || "", eventName].join("|");
      if (seen.has(key)) continue;
      seen.add(key);
      events.push({
        nama_kegiatan: eventName,
        tanggal_mulai: dates.start,
        tanggal_selesai: dates.end,
        akhir_semester: !/\b(?:UTS|ujian\s+tengah\s+semester)\b/i.test(eventName) &&
          !/\/\s*UAS\b/i.test(eventName) &&
          /\b(?:UAS|ujian\s+(?:tulis\s+)?akhir\s+semester)\b/i.test(eventName),
      });
    }

    const academicYearStart = events.find((event) =>
      /Awal KBM TA/i.test(event.nama_kegiatan) &&
      event.nama_kegiatan.includes(academicYear.split("-")[0])
    );
    if (academicYearStart && academicYearStart.tanggal_mulai) {
      events.forEach((event) => {
        if (event.tanggal_mulai && event.tanggal_mulai < academicYearStart.tanggal_mulai) {
          event.akhir_semester = false;
        }
      });
    }

    if (!academicYear || !events.length) throw new Error("Tahun ajaran atau daftar kegiatan kalender tidak berhasil dibaca.");
    return {
      judul: calendarTitle,
      tahun_ajaran: academicYear,
      nama_berkas: file.name,
      events,
    };
  }

  window.parseAcademicCalendarXlsx = parseCalendar;
})();
