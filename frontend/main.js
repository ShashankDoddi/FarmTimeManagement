/* ============================================================
   main.js — Farm Time Admin Login
   Basic client-side validation only.
   Authentication will be handled by PHP backend.
   ============================================================ */

(function () {
  "use strict";

  const form          = document.getElementById("loginForm");
  const emailInput    = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const emailError    = document.getElementById("emailError");
  const passwordError = document.getElementById("passwordError");
  const loginAlert    = document.getElementById("loginAlert");
  const togglePwBtn   = document.getElementById("togglePw");
  const eyeIcon       = document.getElementById("eyeIcon");
  const eyeOffIcon    = document.getElementById("eyeOffIcon");
  const rememberMe    = document.getElementById("rememberMe");
  const forgotLink    = document.getElementById("forgotLink");

  const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

//   restore email
  const savedEmail = localStorage.getItem("ftms_email");
  if (savedEmail) {
    emailInput.value   = savedEmail;
    rememberMe.checked = true;
  }
// helper functions
  function showError(input, errorEl, message) {
    input.classList.add("input-error");
    input.classList.remove("input-valid");
    errorEl.textContent = message;
  }

  function showValid(input, errorEl) {
    input.classList.remove("input-error");
    input.classList.add("input-valid");
    errorEl.textContent = "";
  }

  function clearField(input, errorEl) {
    input.classList.remove("input-error", "input-valid");
    errorEl.textContent = "";
  }

// email validation
  function validateEmail() {
    const val = emailInput.value.trim();
    if (!val) {
      showError(emailInput, emailError, "Email is required.");
      return false;
    }
    if (!EMAIL_REGEX.test(val)) {
      showError(emailInput, emailError, "Please enter a valid email address.");
      return false;
    }
    showValid(emailInput, emailError);
    return true;
  }

  function validatePassword() {
    const val = passwordInput.value;
    if (!val) {
      showError(passwordInput, passwordError, "Password is required.");
      return false;
    }
    if (val.length < 6) {
      showError(passwordInput, passwordError, "Password must be at least 6 characters.");
      return false;
    }
    showValid(passwordInput, passwordError);
    return true;
  }

  emailInput.addEventListener("blur", validateEmail);
  passwordInput.addEventListener("blur", validatePassword);

  emailInput.addEventListener("input", function () {
    clearField(emailInput, emailError);
    loginAlert.classList.remove("visible");
  });

  passwordInput.addEventListener("input", function () {
    clearField(passwordInput, passwordError);
    loginAlert.classList.remove("visible");
  });

  // ── SHOW / HIDE PASSWORD ──
  togglePwBtn.addEventListener("click", function () {
    const show = passwordInput.type === "password";
    passwordInput.type       = show ? "text"  : "password";
    eyeIcon.style.display    = show ? "none"  : "block";
    eyeOffIcon.style.display = show ? "block" : "none";
  });


  forgotLink.addEventListener("click", function (e) {
    e.preventDefault();
    const val = emailInput.value.trim();
    if (!val || !EMAIL_REGEX.test(val)) {
      showError(emailInput, emailError, "Enter your email above first.");
      emailInput.focus();
      return;
    }

    alert("Password reset link will be sent to: " + val);
  });
// form submit function
  form.addEventListener("submit", function (e) {
    const emailOk    = validateEmail();
    const passwordOk = validatePassword();

    if (!emailOk || !passwordOk) {
      e.preventDefault();
      if (!emailOk) emailInput.focus();
      else          passwordInput.focus();
      return;
    }


    if (rememberMe.checked) {
      localStorage.setItem("ftms_email", emailInput.value.trim());
    } else {
      localStorage.removeItem("ftms_email");
    }


  });

})();
