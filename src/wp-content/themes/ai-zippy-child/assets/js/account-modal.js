/**
 * Account Modal & Dropdown Logic
 */
document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("ai-zippy-auth-modal");
  if (!modal) return;

  const dropdown = document.querySelector(".ai-zippy-account-dropdown");
  const trigger = document.querySelector(".ai-zippy-account-trigger");

  if (!trigger) {
    console.warn("AI Zippy Child: Account trigger not found.");
    return;
  }

  // Safety check for localized object
  const authConfig =
    typeof ai_zippy_auth_obj !== "undefined"
      ? ai_zippy_auth_obj
      : {
          is_logged_in: false,
          my_account_url: "/my-account/",
          rest_url: "/wp-json/ai-zippy/v1",
        };

  // Change behavior if logged in (Check variable or body class)
  const isLoggedIn =
    authConfig.is_logged_in || document.body.classList.contains("logged-in");

  if (isLoggedIn) {
    if (trigger) {
      trigger.href = authConfig.my_account_url || "/my-account/";
      trigger.classList.remove("ai-zippy-account-trigger"); // Remove trigger class to avoid JS interception
    }
    if (dropdown) dropdown.remove(); // Remove login/register dropdown
    return; // Don't attach modal logic
  }

  // Toggle dropdown
  trigger.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    dropdown.classList.toggle("active");
  });

  // Close dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (
      dropdown &&
      !trigger.contains(e.target) &&
      !dropdown.contains(e.target)
    ) {
      dropdown.classList.remove("active");
    }
  });

  // Open Modal function
  function openModal(tab = "login") {
    modal.classList.add("active");
    document.body.style.overflow = "hidden"; // Prevent scroll
    switchTab(tab);
  }

  // Close Modal function
  function closeModal() {
    modal.classList.remove("active");
    document.body.style.overflow = "";
  }

  // Event Delegation for Modal Triggers
  document.addEventListener("click", function (e) {
    // Login Modal Trigger
    if (e.target.closest(".open-login-modal")) {
      e.preventDefault();
      if (dropdown) dropdown.classList.remove("active");
      openModal("login");
    }

    // Register Modal Trigger
    if (e.target.closest(".open-register-modal")) {
      e.preventDefault();
      if (dropdown) dropdown.classList.remove("active");
      openModal("register");
    }

    // Close Buttons
    if (
      e.target.closest(".modal-close") ||
      e.target.classList.contains("ai-zippy-modal-overlay")
    ) {
      closeModal();
    }
  });

  // Tab Switching
  document.querySelectorAll(".auth-tab-trigger").forEach((btn) => {
    btn.addEventListener("click", function () {
      const tab = this.getAttribute("data-tab");
      switchTab(tab);
    });
  });

  function switchTab(tabId) {
    const triggers = document.querySelectorAll(".auth-tab-trigger");
    const contents = document.querySelectorAll(".auth-tab-content");

    triggers.forEach((b) =>
      b.classList.toggle("active", b.getAttribute("data-tab") === tabId),
    );
    contents.forEach((c) =>
      c.classList.toggle("active", c.id === `auth-tab-${tabId}`),
    );
  }

  // REST API Form Submissions
  document.querySelectorAll(".auth-form").forEach((form) => {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      const isLogin = this.querySelector('button[name="login"]');
      const endpoint = isLogin ? "/login" : "/register";

      const formData = new FormData(this);
      const data = {};
      formData.forEach((value, key) => (data[key] = value));

      const btn = this.querySelector(".auth-btn");
      const originalText = btn.innerText;
      btn.innerText = "Processing...";
      btn.disabled = true;

      fetch(authConfig.rest_url + endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": authConfig.nonce,
        },
        body: JSON.stringify(data),
      })
        .then((response) => response.json())
        .then((result) => {
          const messageBox =
            this.querySelector(".auth-status-message") ||
            document.createElement("div");
          const isSuccess = !result.code && (result.success || result.id);

          messageBox.className =
            "auth-status-message " + (isSuccess ? "success" : "error");
          messageBox.innerText =
            result.message ||
            (result.data && result.data.message) ||
            result.message ||
            "An error occurred";

          if (!this.querySelector(".auth-status-message")) {
            this.prepend(messageBox);
          }

          if (isSuccess) {
            setTimeout(() => window.location.reload(), 1500);
          } else {
            btn.innerText = originalText;
            btn.disabled = false;
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          btn.innerText = originalText;
          btn.disabled = false;
        });
    });
  });

  // Close on ESC
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("active")) {
      closeModal();
    }
  });

  // Hijack /my-account links if user is not logged in (optional, but good for UX)
  // Note: This logic assumes if the modal exists, we might want to use it
  // But we should check if global ai_zippy_is_logged_in variable exists (not implemented yet)

  // Intercept and redirect shop links (e.g., in Mini Cart)
  document.addEventListener("click", function (e) {
    const link = e.target.closest("a");
    if (
      link &&
      (link.href.includes("/shop/") ||
        link.classList.contains("wc-block-mini-cart__shopping-button"))
    ) {
      // If it matches shop pattern or is the mini cart button, redirect to party-order
      if (link.href.includes("/shop/")) {
        link.href = link.href.replace(/\/shop\/?$/, "/party-order/");
      } else {
        // If it's the shopping button but doesn't have /shop/ yet for some reason
        link.href = "/party-order/";
      }
    }
  });
});
