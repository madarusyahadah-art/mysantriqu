"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const adminLinks = document.querySelectorAll(".admin-nav");
  const attendanceLinks = document.querySelectorAll(
    'a[href^="absensi-input.html"], a[href^="pelanggaran.html"]'
  );
  const reportLinks = document.querySelectorAll(
    'a[href^="laporan.html"], a[href^="laporan-kegiatan.html"]'
  );
  const khsLinks = document.querySelectorAll(
    'a[href^="dokumen-akademik.html?type=khs"], a[data-academic-type="khs"]'
  );

  adminLinks.forEach(function (link) { link.hidden = true; });
  attendanceLinks.forEach(function (link) { link.hidden = true; });
  reportLinks.forEach(function (link) { link.hidden = true; });
  khsLinks.forEach(function (link) { link.hidden = true; });

  fetch("api/session.php", { credentials: "same-origin" })
    .then(async function (response) {
      const data = await response.json();
      if (!response.ok || !data.success || !data.user) return null;
      return String(data.user.role || "").toLowerCase();
    })
    .then(function (role) {
      if (!role) return;
      const isAdmin = role === "admin";
      const isStudent = role === "santri" || role === "mahasantri";
      const canWriteAttendance = isAdmin || role === "musyrif" || role === "mas'ul";
      const canViewReports = isAdmin || role === "kajur" || role === "supervisor"
        || role === "musyrif" || role === "mas'ul";
      const canViewKhs = isAdmin || isStudent;

      adminLinks.forEach(function (link) { link.hidden = !isAdmin; });
      attendanceLinks.forEach(function (link) { link.hidden = !canWriteAttendance; });
      reportLinks.forEach(function (link) { link.hidden = !canViewReports; });
      khsLinks.forEach(function (link) { link.hidden = !canViewKhs; });
    })
    .catch(function (error) {
      console.error("Role navigation could not be loaded.", error);
    });
});
