document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("auth-form");
  const emailInput = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const nameInput = document.getElementById("name");
  const confirmPasswordInput = document.getElementById("confirm-password");
  const rememberInput = document.getElementById("remember-me");
  const message = document.getElementById("auth-message");
  const accountList = document.getElementById("remembered-accounts");
  const accountSelect = document.getElementById("remembered-account");
  const modes = {
    login: {
      title: "Login",
      submit: "Login",
      showPassword: true,
      showName: false,
      showConfirm: false,
      showRemember: true,
      showGoogle: false,
      showRegister: true,
      showForgot: true,
      showBack: false,
    },
    register: {
      title: "Buat Akun Baru",
      submit: "Buat Akun dengan Google",
      showPassword: false,
      showName: false,
      showConfirm: false,
      showRemember: false,
      showGoogle: true,
      showRegister: false,
      showForgot: false,
      showBack: true,
    },
    forgot: {
      title: "Lupa Password",
      submit: "Kirim Link Reset",
      showPassword: false,
      showName: false,
      showConfirm: false,
      showRemember: false,
      showGoogle: false,
      showRegister: false,
      showForgot: false,
      showBack: true,
    },
  };
  let mode = "login";

  function readRememberedAccounts() {
    try {
      const users = JSON.parse(localStorage.getItem("mysantriqu_remembered_users") || "[]");
      return Array.isArray(users)
        ? users.filter((user) => user && typeof user.email === "string" && typeof user.name === "string")
        : [];
    } catch (error) {
      console.error("Daftar akun tersimpan tidak dapat dibaca:", error);
      return [];
    }
  }

  function renderRememberedAccounts() {
    const users = readRememberedAccounts();
    accountSelect.replaceChildren(new Option("Pilih akun", ""));
    users.forEach((user) => {
      accountSelect.add(new Option(user.name + " (" + user.email + ")", user.email));
    });
    accountList.hidden = users.length === 0;
  }

  function rememberAccount(user) {
    if (!user || !user.email) return;
    const users = readRememberedAccounts().filter((item) => item.email.toLowerCase() !== user.email.toLowerCase());
    users.unshift({ name: user.name || user.email, email: user.email });
    try {
      localStorage.setItem("mysantriqu_remembered_users", JSON.stringify(users.slice(0, 10)));
    } catch (error) {
      console.error("Akun tidak dapat disimpan di perangkat ini:", error);
    }
  }

  function showMessage(text, state) {
    message.textContent = text;
    message.dataset.state = state || "info";
    message.hidden = !text;
  }

  function setMode(nextMode) {
    mode = nextMode;
    const settings = modes[mode];
    document.getElementById("auth-title").textContent = settings.title;
    document.getElementById("submit-button").textContent = settings.submit;
    emailInput.closest(".input-box").hidden = mode === "register";
    emailInput.required = mode !== "register";
    document.getElementById("password-field").hidden = !settings.showPassword;
    passwordInput.required = settings.showPassword;
    document.getElementById("name-field").hidden = !settings.showName;
    nameInput.required = settings.showName;
    document.getElementById("confirm-password-field").hidden = !settings.showConfirm;
    confirmPasswordInput.required = settings.showConfirm;
    document.getElementById("remember-options").hidden = !settings.showRemember;
    document.getElementById("submit-button").hidden = mode === "register";
    accountList.hidden = !settings.showRemember || readRememberedAccounts().length === 0;
    document.getElementById("google-button").hidden = !settings.showGoogle;
    document.getElementById("login-divider").hidden = !settings.showGoogle;
    document.getElementById("register-prompt").hidden = !settings.showRegister;
    document.getElementById("register-link").hidden = !settings.showRegister;
    document.getElementById("forgot-link").hidden = !settings.showForgot;
    document.getElementById("back-to-login").hidden = !settings.showBack;
    const help = document.getElementById("auth-help");
    help.textContent = mode === "register"
      ? "Verifikasi akun Google terlebih dahulu. Setelah itu, buat kata sandi untuk login menggunakan email Google Anda."
      : "";
    help.hidden = mode !== "register";
    document.getElementById("password").autocomplete = mode === "register" ? "new-password" : "current-password";
    showMessage("", "");
  }

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(body),
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || "Permintaan gagal diproses.");
    }
    return data;
  }

  document.getElementById("google-button").addEventListener("click", function () {
    window.location.href = "api/login.php?action=google&intent=register";
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (mode === "register") {
      document.getElementById("google-button").click();
      return;
    }
    if (!form.reportValidity()) return;
    const email = emailInput.value.trim();

    try {
      if (mode === "forgot") {
        const result = await postJson("api/forgot-password.php", { email: email });
        showMessage(result.message, "success");
        return;
      }

      const result = await postJson("api/password-login.php", {
        email: email,
        password: passwordInput.value,
        remember_me: rememberInput.checked,
      });
      if (rememberInput.checked) rememberAccount(result.user);
      if (!result.user.role_selected) {
        window.location.href = "pilih-role.html";
      } else {
        window.location.href = "dashboard.html";
      }
    } catch (error) {
      showMessage(error.message, "error");
    }
  });

  document.getElementById("register-link").addEventListener("click", function (event) {
    event.preventDefault();
    setMode("register");
  });

  document.getElementById("forgot-link").addEventListener("click", function (event) {
    event.preventDefault();
    setMode("forgot");
  });

  document.getElementById("back-to-login").addEventListener("click", function () {
    setMode("login");
  });

  accountSelect.addEventListener("change", function () {
    if (!accountSelect.value) return;
    emailInput.value = accountSelect.value;
    rememberInput.checked = true;
    passwordInput.focus();
  });

  document.getElementById("forget-account").addEventListener("click", function () {
    if (!accountSelect.value) {
      showMessage("Pilih akun yang ingin dihapus dari perangkat ini.", "error");
      return;
    }
    const users = readRememberedAccounts().filter((user) => user.email !== accountSelect.value);
    try {
      localStorage.setItem("mysantriqu_remembered_users", JSON.stringify(users));
    } catch (error) {
      showMessage("Akun tersimpan tidak dapat dihapus dari perangkat ini.", "error");
      return;
    }
    renderRememberedAccounts();
    showMessage("Akun dihapus dari daftar perangkat ini. Kata sandi tidak pernah disimpan.", "success");
  });

  setMode("login");
  renderRememberedAccounts();
  const query = new URLSearchParams(window.location.search);
  const error = query.get("error");
  if (error) {
    showMessage("Login Google gagal: " + error, "error");
    query.delete("error");
  }

  let registeredEmail = query.get("email");
  try {
    registeredEmail = registeredEmail || sessionStorage.getItem("mysantriqu_registered_email");
    sessionStorage.removeItem("mysantriqu_registered_email");
  } catch (storageError) {
    console.error("Email akun baru tidak dapat dipulihkan:", storageError);
  }
  if (registeredEmail) {
    emailInput.value = registeredEmail;
    query.delete("email");
  }
  if (query.has("registered")) {
    showMessage("Akun berhasil dibuat. Silakan login dengan email dan kata sandi yang baru dibuat.", "success");
    query.delete("registered");
  }
  if (registeredEmail || query.toString() !== window.location.search.replace(/^\?/, "")) {
    const nextUrl = window.location.pathname + (query.toString() ? "?" + query.toString() : "");
    window.history.replaceState({}, document.title, nextUrl);
  }

  fetch("api/session.php", { credentials: "same-origin" })
    .then(function (response) {
      if (!response.ok) return null;
      return response.json();
    })
    .then(function (data) {
      if (!data || !data.success || !data.user) return;
      window.location.replace(data.user.role_selected ? "dashboard.html" : "pilih-role.html");
    })
    .catch(function (error) {
      console.error("Sesi akun tidak dapat diperiksa:", error);
    });
});
