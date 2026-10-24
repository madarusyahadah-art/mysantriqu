(async function () {
  try {
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
    localStorage.setItem("user_data", JSON.stringify(user));
    window.dispatchEvent(new CustomEvent("verified-session", { detail: user }));

    if (localStorage.getItem("mysantriqu_remember_pending") === "1") {
      try {
        const rememberedUsers = JSON.parse(localStorage.getItem("mysantriqu_remembered_users") || "[]");
        const otherUsers = Array.isArray(rememberedUsers)
          ? rememberedUsers.filter((item) => item && item.email && item.email.toLowerCase() !== user.email.toLowerCase())
          : [];
        otherUsers.unshift({ name: user.name || user.email, email: user.email });
        localStorage.setItem("mysantriqu_remembered_users", JSON.stringify(otherUsers.slice(0, 10)));
        localStorage.removeItem("mysantriqu_remember_pending");
      } catch (error) {
        console.error("Akun tidak dapat diingat di perangkat ini:", error);
      }
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
