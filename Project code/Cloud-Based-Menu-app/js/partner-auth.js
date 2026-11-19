import { initializeApp } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-app.js";
import { getAnalytics, isSupported } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-analytics.js";
import {
  getAuth,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  updateProfile,
  sendEmailVerification,
  signOut
} from "https://www.gstatic.com/firebasejs/12.11.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyCT0zWINeu015x2Ox34bGsjAMocXOLcgJ4",
  authDomain: "food-book-d97eb.firebaseapp.com",
  projectId: "food-book-d97eb",
  storageBucket: "food-book-d97eb.firebasestorage.app",
  messagingSenderId: "258905818274",
  appId: "1:258905818274:web:72dd0bfec3fe51e9d743fb",
  measurementId: "G-LF6YTBPJH0"
};

const app = initializeApp(firebaseConfig);

isSupported().then((supported) => {
  if (supported) {
    getAnalytics(app);
  }
}).catch(() => {
  // Analytics is optional for localhost auth flow.
});

const auth = getAuth(app);

const authMessage = document.getElementById("partnerAuthMessage");
const loginForm = document.getElementById("partnerLoginForm");
const registerForm = document.getElementById("partnerRegisterForm");
const applyRestaurantBtn = document.getElementById("applyRestaurantBtn");
const authTabs = document.querySelectorAll("[data-auth-tab]");

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const fullNamePattern = /^[A-Za-z ]+$/;
const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/;

const fieldMap = {
  partnerLoginEmail: document.getElementById("partnerLoginEmail"),
  partnerLoginPassword: document.getElementById("partnerLoginPassword"),
  partnerRegisterName: document.getElementById("partnerRegisterName"),
  partnerRegisterEmail: document.getElementById("partnerRegisterEmail"),
  partnerRegisterPassword: document.getElementById("partnerRegisterPassword"),
  partnerRegisterConfirmPassword: document.getElementById("partnerRegisterConfirmPassword")
};

const errorMap = {
  partnerLoginEmail: document.getElementById("partnerLoginEmailError"),
  partnerLoginPassword: document.getElementById("partnerLoginPasswordError"),
  partnerRegisterName: document.getElementById("partnerRegisterNameError"),
  partnerRegisterEmail: document.getElementById("partnerRegisterEmailError"),
  partnerRegisterPassword: document.getElementById("partnerRegisterPasswordError"),
  partnerRegisterConfirmPassword: document.getElementById("partnerRegisterConfirmPasswordError")
};

function showMessage(message, type = "error") {
  if (!authMessage) {
    return;
  }

  authMessage.textContent = message;
  authMessage.className = `auth-message is-visible ${type === "success" ? "is-success" : "is-error"}`;
}

function clearMessage() {
  if (!authMessage) {
    return;
  }

  authMessage.textContent = "";
  authMessage.className = "auth-message";
}

function setButtonsDisabled(disabled) {
  const buttons = [
    document.getElementById("partnerLoginSubmitBtn"),
    document.getElementById("partnerRegisterSubmitBtn"),
    applyRestaurantBtn
  ];

  buttons.forEach((button) => {
    if (button) {
      button.disabled = disabled;
    }
  });
}

function setFieldError(fieldId, message) {
  const input = fieldMap[fieldId];
  const error = errorMap[fieldId];

  if (input) {
    input.classList.toggle("has-error", Boolean(message));
  }

  if (error) {
    error.textContent = message || "";
  }
}

function clearFieldErrors(formName) {
  const groups = formName === "login"
    ? ["partnerLoginEmail", "partnerLoginPassword"]
    : ["partnerRegisterName", "partnerRegisterEmail", "partnerRegisterPassword", "partnerRegisterConfirmPassword"];

  groups.forEach((fieldId) => {
    setFieldError(fieldId, "");
  });
}

function hasOnlySpaces(value) {
  return value.trim().length === 0;
}

function validateEmailField(value, fieldId) {
  if (!value || hasOnlySpaces(value)) {
    setFieldError(fieldId, "Email is required.");
    return false;
  }

  if (!emailPattern.test(value.trim())) {
    setFieldError(fieldId, "Enter a valid email address.");
    return false;
  }

  setFieldError(fieldId, "");
  return true;
}

function validatePasswordField(value, fieldId) {
  if (!value || hasOnlySpaces(value)) {
    setFieldError(fieldId, "Password is required.");
    return false;
  }

  if (!passwordPattern.test(value)) {
    setFieldError(fieldId, "Password must be 8+ characters with uppercase, lowercase, number, and special character.");
    return false;
  }

  setFieldError(fieldId, "");
  return true;
}

function validateLoginForm(email, password) {
  const isEmailValid = validateEmailField(email, "partnerLoginEmail");
  const isPasswordValid = validatePasswordField(password, "partnerLoginPassword");
  return isEmailValid && isPasswordValid;
}

function validateRegisterForm(fullName, email, password, confirmPassword) {
  let isValid = true;

  if (!fullName || hasOnlySpaces(fullName)) {
    setFieldError("partnerRegisterName", "Full name is required.");
    isValid = false;
  } else if (!fullNamePattern.test(fullName.trim())) {
    setFieldError("partnerRegisterName", "Full name can contain only alphabets and spaces.");
    isValid = false;
  } else {
    setFieldError("partnerRegisterName", "");
  }

  if (!validateEmailField(email, "partnerRegisterEmail")) {
    isValid = false;
  }

  if (!validatePasswordField(password, "partnerRegisterPassword")) {
    isValid = false;
  }

  if (!confirmPassword || hasOnlySpaces(confirmPassword)) {
    setFieldError("partnerRegisterConfirmPassword", "Confirm password is required.");
    isValid = false;
  } else if (password !== confirmPassword) {
    setFieldError("partnerRegisterConfirmPassword", "Confirm password must match password.");
    isValid = false;
  } else {
    setFieldError("partnerRegisterConfirmPassword", "");
  }

  return isValid;
}

function switchTab(tabName) {
  authTabs.forEach((tab) => {
    tab.classList.toggle("is-active", tab.dataset.authTab === tabName);
  });

  loginForm.classList.toggle("is-active", tabName === "login");
  registerForm.classList.toggle("is-active", tabName === "register");
  clearMessage();
  clearFieldErrors("login");
  clearFieldErrors("register");
}

async function syncPartnerToServer(payload) {
  const response = await fetch("save-partner-user.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  const data = await response.json();

  if (!response.ok || !data.success) {
    throw new Error(data.message || "Unable to save restaurant partner data.");
  }

  return data;
}

function normalizeFirebaseError(error) {
  const code = error?.code || "";

  switch (code) {
    case "auth/invalid-credential":
    case "auth/wrong-password":
    case "auth/user-not-found":
      return "Invalid email or password. If this email was already used before, please use Partner Login with the same password.";
    case "auth/email-already-in-use":
      return "This email already exists in FOOD BOOK. Please use Partner Login with the same email and password.";
    case "auth/weak-password":
      return "Password should be at least 6 characters.";
    default:
      return error?.message || "Authentication failed. Please try again.";
  }
}

authTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    switchTab(tab.dataset.authTab);
  });
});

if (loginForm) {
  loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage();
    clearFieldErrors("login");
    setButtonsDisabled(true);

    const email = document.getElementById("partnerLoginEmail").value.trim();
    const password = document.getElementById("partnerLoginPassword").value;

    if (!validateLoginForm(email, password)) {
      setButtonsDisabled(false);
      return;
    }

    try {
      const result = await signInWithEmailAndPassword(auth, email, password);

      if (!result.user.emailVerified) {
        await signOut(auth);
        showMessage("Please verify your email before logging in.");
        setButtonsDisabled(false);
        return;
      }

      const syncResult = await syncPartnerToServer({
        uid: result.user.uid,
        full_name: result.user.displayName || "Restaurant Partner",
        email,
        provider: "password",
        email_verified: 1
      });
      window.location.href = "partner-restaurant.php";
    } catch (error) {
      if (error?.code === "auth/email-already-in-use") {
        registerForm.reset();
        switchTab("login");
        document.getElementById("partnerLoginEmail").value = email;
        showMessage("This email already exists in FOOD BOOK. Please log in here with the same email and password.", "success");
        setButtonsDisabled(false);
        return;
      }

      showMessage(normalizeFirebaseError(error));
      setButtonsDisabled(false);
    }
  });
}

if (registerForm) {
  registerForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage();
    clearFieldErrors("register");
    setButtonsDisabled(true);

    const fullName = document.getElementById("partnerRegisterName").value.trim();
    const email = document.getElementById("partnerRegisterEmail").value.trim();
    const password = document.getElementById("partnerRegisterPassword").value;
    const confirmPassword = document.getElementById("partnerRegisterConfirmPassword").value;

    if (!validateRegisterForm(fullName, email, password, confirmPassword)) {
      setButtonsDisabled(false);
      return;
    }

    try {
      const result = await createUserWithEmailAndPassword(auth, email, password);
      await updateProfile(result.user, {
        displayName: fullName
      });

      await syncPartnerToServer({
        uid: result.user.uid,
        full_name: fullName,
        email,
        provider: "password",
        email_verified: 0
      });

      await sendEmailVerification(result.user);
      await signOut(auth);

      registerForm.reset();
      switchTab("login");
      document.getElementById("partnerLoginEmail").value = email;
      showMessage("Partner registration successful. A verification email has been sent. Please verify your email before login.", "success");
      setButtonsDisabled(false);
    } catch (error) {
      showMessage(normalizeFirebaseError(error));
      setButtonsDisabled(false);
    }
  });
}

Object.entries(fieldMap).forEach(([fieldId, input]) => {
  if (!input) {
    return;
  }

  input.addEventListener("input", () => {
    if (input.classList.contains("has-error")) {
      setFieldError(fieldId, "");
    }
  });
});

if (applyRestaurantBtn) {
  applyRestaurantBtn.addEventListener("click", () => {
    showMessage("Restaurant application onboarding will be available soon.", "success");
  });
}
