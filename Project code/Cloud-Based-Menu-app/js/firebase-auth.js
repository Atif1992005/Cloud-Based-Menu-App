import { initializeApp } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-app.js";
import { getAnalytics, isSupported } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-analytics.js";
import {
  getAuth,
  GoogleAuthProvider,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signInWithPopup,
  signInAnonymously,
  updateProfile
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
const provider = new GoogleAuthProvider();

const authMessage = document.getElementById("authMessage");
const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const googleBtn = document.getElementById("googleLoginBtn");
const guestBtn = document.getElementById("guestLoginBtn");
const authTabs = document.querySelectorAll("[data-auth-tab]");

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
    document.getElementById("loginSubmitBtn"),
    document.getElementById("registerSubmitBtn"),
    googleBtn,
    guestBtn
  ];

  buttons.forEach((button) => {
    if (button) {
      button.disabled = disabled;
    }
  });
}

function switchTab(tabName) {
  authTabs.forEach((tab) => {
    tab.classList.toggle("is-active", tab.dataset.authTab === tabName);
  });

  loginForm.classList.toggle("is-active", tabName === "login");
  registerForm.classList.toggle("is-active", tabName === "register");
  clearMessage();
}

function storeCustomer(userData) {
  localStorage.setItem("foodBookCustomer", JSON.stringify(userData));
}

async function syncUserToServer(payload) {
  const response = await fetch("save-user.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  const data = await response.json();

  if (!response.ok || !data.success) {
    throw new Error(data.message || "Unable to save user data to MySQL.");
  }

  return data;
}

async function completeLogin(user, extra = {}) {
  const payload = {
    uid: user.uid,
    full_name: extra.fullName ?? user.displayName ?? "Guest User",
    email: extra.email ?? user.email ?? "",
    provider: extra.provider ?? user.providerData?.[0]?.providerId ?? "password",
    is_guest: extra.isGuest ? 1 : 0
  };

  await syncUserToServer(payload);

  storeCustomer({
    uid: user.uid,
    name: payload.full_name,
    email: payload.email,
    provider: payload.provider,
    isGuest: Boolean(payload.is_guest)
  });

  window.location.href = "restaurants.php";
}

function normalizeFirebaseError(error) {
  const code = error?.code || "";

  switch (code) {
    case "auth/invalid-credential":
    case "auth/wrong-password":
    case "auth/user-not-found":
      return "Invalid email or password.";
    case "auth/email-already-in-use":
      return "This email is already registered.";
    case "auth/weak-password":
      return "Password should be at least 6 characters.";
    case "auth/popup-closed-by-user":
      return "Google sign-in was closed before completion.";
    case "auth/admin-restricted-operation":
      return "This authentication method is not currently allowed.";
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
    setButtonsDisabled(true);

    const email = document.getElementById("loginEmail").value.trim();
    const password = document.getElementById("loginPassword").value;

    try {
      const result = await signInWithEmailAndPassword(auth, email, password);
      await completeLogin(result.user, {
        fullName: result.user.displayName || "FOOD BOOK Customer",
        email,
        provider: "password",
        isGuest: false
      });
    } catch (error) {
      showMessage(normalizeFirebaseError(error));
      setButtonsDisabled(false);
    }
  });
}

if (registerForm) {
  registerForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage();
    setButtonsDisabled(true);

    const fullName = document.getElementById("registerName").value.trim();
    const email = document.getElementById("registerEmail").value.trim();
    const password = document.getElementById("registerPassword").value;
    const confirmPassword = document.getElementById("registerConfirmPassword").value;

    if (!fullName) {
      showMessage("Full name is required.");
      setButtonsDisabled(false);
      return;
    }

    if (password !== confirmPassword) {
      showMessage("Password and confirm password do not match.");
      setButtonsDisabled(false);
      return;
    }

    try {
      const result = await createUserWithEmailAndPassword(auth, email, password);
      await updateProfile(result.user, {
        displayName: fullName
      });

      await completeLogin(result.user, {
        fullName,
        email,
        provider: "password",
        isGuest: false
      });
    } catch (error) {
      showMessage(normalizeFirebaseError(error));
      setButtonsDisabled(false);
    }
  });
}

if (googleBtn) {
  googleBtn.addEventListener("click", async () => {
    clearMessage();
    setButtonsDisabled(true);

    try {
      const result = await signInWithPopup(auth, provider);
      await completeLogin(result.user, {
        fullName: result.user.displayName || "Google User",
        email: result.user.email || "",
        provider: "google.com",
        isGuest: false
      });
    } catch (error) {
      showMessage(normalizeFirebaseError(error));
      setButtonsDisabled(false);
    }
  });
}

if (guestBtn) {
  guestBtn.addEventListener("click", async () => {
    clearMessage();
    setButtonsDisabled(true);

    try {
      const result = await signInAnonymously(auth);
      await completeLogin(result.user, {
        fullName: "Guest User",
        email: "",
        provider: "anonymous",
        isGuest: true
      });
    } catch (error) {
      showMessage(normalizeFirebaseError(error));
      setButtonsDisabled(false);
    }
  });
}
