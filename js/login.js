"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("login-form");
  const identifierInput = document.getElementById("identifier");
  const passwordInput = document.getElementById("password");
  const message = document.getElementById("form-message");
  const submitButton = document.getElementById("submit-button");
  document.getElementById("current-year").textContent = String(new Date().getFullYear());

  function showMessage(text, state) {
    message.textContent = text;
    message.dataset.state = state || "error";
    message.hidden = !text;
  }

  function destinationFor(user) {
    return user && user.role_selected ? "dashboard.html" : "pilih-role.html";
  }

  async function readJson(response) {
    let data;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error("Respons server tidak dapat dibaca. Coba lagi beberapa saat.");
    }

    if (!response.ok || !data.success) {
      throw new Error(data.error || "Permintaan tidak dapat diproses.");
    }
    return data;
  }

  document.getElementById("toggle-password").addEventListener("click", function () {
    const showPassword = passwordInput.type === "password";
    passwordInput.type = showPassword ? "text" : "password";
    this.setAttribute("aria-pressed", String(showPassword));
    this.setAttribute("aria-label", showPassword ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
  });

  document.getElementById("forgot-password").addEventListener("click", function () {
    showMessage("Silakan hubungi admin untuk bantuan mengatur ulang kata sandi.", "info");
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    showMessage("", "");

    if (!form.reportValidity()) return;

    const identifier = identifierInput.value.trim();
    const password = passwordInput.value;
    if (!identifier || !password) {
      showMessage("Email/nomor HP dan kata sandi harus diisi.");
      return;
    }

    submitButton.disabled = true;
    submitButton.querySelector("span").textContent = "Memeriksa...";

    try {
      const response = await fetch("api/password-login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          identifier: identifier,
          password: password,
          remember_me: document.getElementById("remember-me").checked,
        }),
      });
      const data = await readJson(response);
      window.location.assign(destinationFor(data.user));
    } catch (error) {
      showMessage(error.message || "Login gagal. Silakan coba lagi.");
    } finally {
      submitButton.disabled = false;
      submitButton.querySelector("span").textContent = "Masuk";
    }
  });

  const error = new URLSearchParams(window.location.search).get("error");
  if (error) {
    showMessage(error);
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  fetch("api/session.php", { credentials: "same-origin" })
    .then(readJson)
    .then(function (data) {
      if (data.user) window.location.replace(destinationFor(data.user));
    })
    .catch(function (error) {
      showMessage(error.message || "Status sesi belum dapat diperiksa. Anda tetap dapat mencoba masuk.", "info");
    });
});
