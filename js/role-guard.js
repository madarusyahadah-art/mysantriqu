(async function () {
  try {
    localStorage.removeItem("user_data");
    const response = await fetch("api/session.php");
    if (!response.ok) {
      window.location.href = "index.html";
      return;
    }

    const data = await response.json();
    if (!data.success || !data.user) {
      window.location.href = "index.html";
      return;
    }

    const user = data.user;
    localStorage.setItem("user_id", user.id);
    localStorage.setItem("user_role", user.role);
    window.currentSessionUser = user;
    window.dispatchEvent(new CustomEvent("verified-session", { detail: user }));

    const userName = document.getElementById("user-name");
    if (userName) userName.textContent = user.name || user.email || "Pengguna";
    const userPhoto = document.getElementById("user-photo");
    if (userPhoto) userPhoto.src = user.photo || user.picture || "assets/Logo.png";
    const catalogManagement = document.getElementById("catalog-management");
    if (catalogManagement && user.role === "admin") {
      catalogManagement.style.display = "block";
    }

    if (!user.role_selected) {
      window.location.href = "pilih-role.html";
      return;
    }

    const access = document.body.dataset.access || "authenticated";
    if (access === "admin" && user.role !== "admin") {
      window.location.href = "dashboard.html";
      return;
    }

    if (access === "supervisor" && !["admin", "musyrif", "mas'ul", "supervisor"].includes(user.role)) {
      window.location.href = "dashboard.html";
      return;
    }

    if (access === "profile" && user.role !== "admin" && !user.santri_id) {
      alert("Profil santri Anda belum dihubungkan oleh admin.");
      window.location.href = "dashboard.html";
    }
  } catch (error) {
    console.error("Gagal memverifikasi sesi:", error);
    window.location.href = "index.html";
  }
})();
