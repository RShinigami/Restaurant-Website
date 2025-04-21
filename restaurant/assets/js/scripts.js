// Custom JavaScript for Restaurant System

document.addEventListener("DOMContentLoaded", () => {
  const navToggle = document.querySelector(".nav-toggle");
  const navMenu = document.querySelector(".nav-menu");

  // Toggle dropdown menu
  navToggle.addEventListener("click", () => {
    navMenu.classList.toggle("active");
  });

  // Close menu when clicking outside
  document.addEventListener("click", (event) => {
    if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
      navMenu.classList.remove("active");
    }
  });

  // Close menu when clicking a link
  navMenu.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      navMenu.classList.remove("active");
    });
  });

  // Initialize sliders for menu preview
  const sliderTracks = document.querySelectorAll(".slider-track");
  sliderTracks.forEach((track) => {
    const items = track.querySelectorAll(".slider-item");
    const category = track.getAttribute("data-category");
    const isRightSliding = ["Salad", "Pasta", "Dessert", "Side"].includes(
      category
    );

    if (items.length > 0) {
      // Duplicate items for seamless infinite loop
      items.forEach((item) => {
        const clone = item.cloneNode(true);
        if (isRightSliding) {
          track.insertBefore(clone, track.firstChild); // Prepend for right sliding
        } else {
          track.appendChild(clone); // Append for left sliding
        }
      });
    }
  });

  // Register form validation
  const registerForm = document.getElementById("registerForm");
  if (registerForm) {
    registerForm.addEventListener("submit", (e) => {
      const username = document.getElementById("username").value;
      const email = document.getElementById("email").value;
      const phone = document.getElementById("phone").value;
      const password = document.getElementById("password").value;
      const confirmPassword = document.getElementById("confirm_password").value;

      let errors = [];

      if (username.length < 3 || username.length > 50) {
        errors.push("Username must be 3-50 characters.");
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push("Invalid email address.");
      }
      if (!/^\+?\d{7,15}$/.test(phone)) {
        errors.push("Invalid phone number (7-15 digits).");
      }
      if (
        password.length < 8 ||
        !/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/.test(password)
      ) {
        errors.push("Password must be 8+ characters with letters and numbers.");
      }
      if (password !== confirmPassword) {
        errors.push("Passwords do not match.");
      }

      if (errors.length > 0) {
        e.preventDefault();
        const errorContainer = document.createElement("ul");
        errorContainer.className = "errors";
        errors.forEach((error) => {
          const li = document.createElement("li");
          li.textContent = error;
          errorContainer.appendChild(li);
        });
        const existingErrors = registerForm.querySelector(".errors");
        if (existingErrors) existingErrors.remove();
        registerForm.prepend(errorContainer);
      }
    });
  }

  // Login form validation
  const loginForm = document.getElementById("loginForm");
  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      const username = document.getElementById("username").value;
      const password = document.getElementById("password").value;

      if (!username || !password) {
        e.preventDefault();
        const errorContainer = document.createElement("ul");
        errorContainer.className = "errors";
        const li = document.createElement("li");
        li.textContent = "Username and password are required.";
        errorContainer.appendChild(li);
        const existingErrors = loginForm.querySelector(".errors");
        if (existingErrors) existingErrors.remove();
        loginForm.prepend(errorContainer);
      }
    });
  }

  // Forgot password placeholder
  const forgotPasswordLink = document.getElementById("forgot-password");
  if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener("click", (e) => {
      e.preventDefault();
      alert("Password reset functionality is not implemented yet.");
    });
  }
});
