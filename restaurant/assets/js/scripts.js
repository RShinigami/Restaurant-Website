// Custom JavaScript for Restaurant System

document.addEventListener("DOMContentLoaded", () => {
  const navToggle = document.querySelector(".nav-toggle");
  const navMenu = document.querySelector(".nav-menu");

  // Toggle dropdown menu
  if (navToggle && navMenu) {
    navToggle.addEventListener("click", () => {
      navMenu.classList.toggle("active");
    });
  }

  // Close menu when clicking outside
  document.addEventListener("click", (event) => {
    if (
      navToggle &&
      navMenu &&
      !navToggle.contains(event.target) &&
      !navMenu.contains(event.target)
    ) {
      navMenu.classList.remove("active");
    }
  });

  // Close menu when clicking a link
  if (navMenu) {
    navMenu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        navMenu.classList.remove("active");
      });
    });
  }

  // Initialize sliders for menu preview
  const sliderTracks = document.querySelectorAll(".slider-track");
  sliderTracks.forEach((track) => {
    const items = track.querySelectorAll(".slider-item");
    const category = track.getAttribute("data-category");
    const isRightSliding = ["Salad", "Pasta", "Dessert", "Side"].includes(
      category
    );

    if (items.length > 0) {
      items.forEach((item) => {
        const clone = item.cloneNode(true);
        if (isRightSliding) {
          track.insertBefore(clone, track.firstChild);
        } else {
          track.appendChild(clone);
        }
      });
    }
  });

  // Show toast notification
  function showToast(message, type) {
    const toast = document.getElementById("toast");
    if (toast) {
      toast.textContent = message;
      toast.className = `toast ${type} active`;
      setTimeout(() => {
        toast.className = "toast";
      }, 3000);
    }
  }

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
        !/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/.test(password) ||
        password.length < 8
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
      alert("one day xD");
    });
  }

  // Update cart UI (for order page)
  function updateCartUI(data) {
    const cartItemsContainer = document.getElementById("cart-items");
    const cartTotal = document.getElementById("cart-total");
    const cartCount = document.getElementById("cart-count");
    const placeOrderBtn = document.getElementById("place-order-btn");
    if (cartItemsContainer && cartTotal && cartCount && placeOrderBtn) {
      cartItemsContainer.innerHTML = "";
      if (data.cart.length === 0) {
        cartItemsContainer.innerHTML = "<p>Your cart is empty.</p>";
        placeOrderBtn.disabled = true;
      } else {
        data.cart.forEach((item, index) => {
          const div = document.createElement("div");
          div.className = "cart-item";
          div.style.animationDelay = `${index * 0.1}s`;
          div.innerHTML = `
            <span>${item.name} ($${Number(item.price).toFixed(2)})</span>
            <div class="quantity-controls">
                <button class="decrement" data-item-id="${
                  item.item_id
                }">-</button>
                <input type="number" value="${
                  item.quantity
                }" min="0" data-item-id="${item.item_id}">
                <button class="increment" data-item-id="${
                  item.item_id
                }">+</button>
            </div>
          `;
          cartItemsContainer.appendChild(div);
        });
        placeOrderBtn.disabled = false;
      }
      cartTotal.textContent = `Total: $${data.total}`;
      cartCount.textContent = data.cart_count;
      if (data.cart_count > 0) {
        cartCount.classList.add("pulse");
        setTimeout(() => cartCount.classList.remove("pulse"), 500);
      }
    }
  }

  // Order page functionality
  const menuGrid = document.getElementById("menu-grid");
  if (menuGrid) {
    const categoryFilter = document.getElementById("category-filter");
    const searchFilter = document.getElementById("search-filter");
    const cartSidebar = document.getElementById("cart-sidebar");
    const cartToggle = document.querySelector(".cart-toggle");
    const cartClose = document.querySelector(".cart-close");
    const cartItemsContainer = document.getElementById("cart-items");
    const placeOrderBtn = document.getElementById("place-order-btn");
    const clearCartBtn = document.getElementById("clear-cart-btn");
    const orderModal = document.getElementById("order-modal");
    const modalCartItems = document.getElementById("modal-cart-items");
    const modalCartTotal = document.getElementById("modal-cart-total");
    const cancelOrderBtn = document.getElementById("cancel-order-btn");

    // Update modal UI
    function updateModalUI(data) {
      if (modalCartItems && modalCartTotal) {
        modalCartItems.innerHTML = "";
        data.cart.forEach((item) => {
          const div = document.createElement("div");
          div.className = "modal-cart-item";
          div.innerHTML = `
            <span>${item.name}</span>
            <span>$${Number(item.price).toFixed(2)} x ${
            item.quantity
          } = $${Number(item.subtotal).toFixed(2)}</span>
          `;
          modalCartItems.appendChild(div);
        });
        modalCartTotal.textContent = `Total: $${data.total}`;
      }
    }

    // Filter menu items
    function filterMenu() {
      const category = categoryFilter ? categoryFilter.value : "all";
      const search = searchFilter ? searchFilter.value.toLowerCase() : "";
      const items = menuGrid.querySelectorAll(".item-card");
      items.forEach((item) => {
        const itemCategory = item.getAttribute("data-category");
        const itemName = item.getAttribute("data-name");
        const categoryMatch = category === "all" || itemCategory === category;
        const searchMatch = !search || itemName.includes(search);
        item.style.display = categoryMatch && searchMatch ? "block" : "none";
      });
    }

    // Cart sidebar
    if (cartSidebar && cartToggle && cartClose) {
      cartToggle.addEventListener("click", () => {
        cartSidebar.classList.toggle("active");
      });
      cartClose.addEventListener("click", () => {
        cartSidebar.classList.remove("active");
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && cartSidebar.classList.contains("active")) {
          cartSidebar.classList.remove("active");
        }
      });
    }

    // Load initial cart
    fetch("/public/cart_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=get",
    })
      .then((response) => response.json())
      .then((data) => updateCartUI(data))
      .catch((error) => showToast("Error loading cart: " + error, "error"));

    // Category filter
    if (categoryFilter) {
      categoryFilter.addEventListener("change", filterMenu);
    }

    // Search filter
    if (searchFilter) {
      searchFilter.addEventListener("input", filterMenu);
    }

    // Add to cart
    menuGrid.addEventListener("click", (e) => {
      if (e.target.classList.contains("add-to-cart")) {
        const itemId = e.target.getAttribute("data-item-id");
        fetch("/public/cart_handler.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=add&item_id=${itemId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              updateCartUI(data);
              showToast(data.message, "success");
            } else {
              showToast(data.message, "error");
            }
          })
          .catch((error) => showToast("Error: " + error, "error"));
      }
    });

    // Cart actions
    if (cartItemsContainer) {
      cartItemsContainer.addEventListener("click", (e) => {
        const itemId = e.target.getAttribute("data-item-id");
        if (e.target.classList.contains("increment")) {
          const input = e.target.previousElementSibling;
          input.value = Number(input.value) + 1;
          updateQuantity(itemId, input.value);
        }
        if (e.target.classList.contains("decrement")) {
          const input = e.target.nextElementSibling;
          if (input.value > 0) {
            input.value = Number(input.value) - 1;
            updateQuantity(itemId, input.value);
          }
        }
      });

      cartItemsContainer.addEventListener("change", (e) => {
        if (e.target.tagName === "INPUT") {
          const itemId = e.target.getAttribute("data-item-id");
          const quantity = e.target.value;
          updateQuantity(itemId, quantity);
        }
      });
    }

    // Update quantity
    function updateQuantity(itemId, quantity) {
      fetch("/public/cart_handler.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=update&item_id=${itemId}&quantity=${quantity}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            updateCartUI(data);
            showToast(data.message, "success");
          } else {
            showToast(data.message, "error");
          }
        })
        .catch((error) => showToast("Error: " + error, "error"));
    }

    // Clear cart
    if (clearCartBtn) {
      clearCartBtn.addEventListener("click", () => {
        fetch("/public/cart_handler.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "action=clear",
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              updateCartUI(data);
              showToast(data.message, "success");
            } else {
              showToast(data.message, "error");
            }
          })
          .catch((error) => showToast("Error: " + error, "error"));
      });
    }

    // Place order
    if (placeOrderBtn) {
      placeOrderBtn.addEventListener("click", () => {
        fetch("/public/cart_handler.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "action=get",
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.cart.length > 0) {
              updateModalUI(data);
              orderModal.classList.add("active");
            } else {
              showToast("Cart is empty!", "error");
            }
          })
          .catch((error) => showToast("Error: " + error, "error"));
      });
    }

    // Cancel order
    if (cancelOrderBtn) {
      cancelOrderBtn.addEventListener("click", () => {
        orderModal.classList.remove("active");
      });
    }

    // Close modal on outside click
    if (orderModal) {
      orderModal.addEventListener("click", (e) => {
        if (e.target === orderModal) {
          orderModal.classList.remove("active");
        }
      });
    }
  }

  // Reservation page functionality
  const reservationForm = document.getElementById("reservation-form");
  if (reservationForm) {
    const reservationDate = document.getElementById("reservation-date");
    const reservationTime = document.getElementById("reservation-time");
    const partySize = document.getElementById("party-size");
    const tableNumber = document.getElementById("table-number");
    const specialRequests = document.getElementById("special-requests");
    const reserveBtn = document.getElementById("reserve-btn");
    const availabilityMessage = document.getElementById("availability-message");
    const reservationModal = document.getElementById("reservation-modal");
    const modalDate = document.getElementById("modal-date");
    const modalTime = document.getElementById("modal-time");
    const modalPartySize = document.getElementById("modal-party-size");
    const modalTable = document.getElementById("modal-table");
    const modalRequests = document.getElementById("modal-requests");
    const confirmReservationBtn = document.getElementById(
      "confirm-reservation-btn"
    );
    const cancelReservationBtn = document.getElementById(
      "cancel-reservation-btn"
    );
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    // Set date constraints
    const today = new Date();
    reservationDate.min = today.toISOString().split("T")[0];
    reservationDate.max = new Date(today.setDate(today.getDate() + 30))
      .toISOString()
      .split("T")[0];

    // Check availability
    function checkAvailability() {
      const date = reservationDate.value;
      const time = reservationTime.value;
      const party_size = partySize.value;

      if (date && time && party_size) {
        fetch("availability_handler.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=check_availability&date=${encodeURIComponent(
            date
          )}&time=${encodeURIComponent(time)}&party_size=${party_size}`,
        })
          .then((response) => {
            console.log("Availability response status:", response.status);
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            return response.json();
          })
          .then((data) => {
            console.log("Availability data:", data);
            tableNumber.innerHTML = '<option value="">Select Table</option>';
            if (data.success) {
              data.tables.forEach((table) => {
                const option = document.createElement("option");
                option.value = table.table_number;
                option.textContent = table.label;
                tableNumber.appendChild(option);
              });
              tableNumber.disabled = false;
              availabilityMessage.textContent = data.message;
              availabilityMessage.className = "availability-message success";
            } else {
              tableNumber.disabled = true;
              availabilityMessage.textContent = data.message;
              availabilityMessage.className = "availability-message error";
            }
          })
          .catch((error) => {
            tableNumber.disabled = true;
            availabilityMessage.textContent = "Error checking availability.";
            availabilityMessage.className = "availability-message error";
            showToast("Error checking availability: " + error.message, "error");
          });
      } else {
        tableNumber.innerHTML = '<option value="">Select Table</option>';
        tableNumber.disabled = true;
        availabilityMessage.textContent = "";
      }
    }

    // Trigger availability check
    reservationDate.addEventListener("change", checkAvailability);
    reservationTime.addEventListener("change", checkAvailability);
    partySize.addEventListener("change", checkAvailability);

    // Reserve button
    reserveBtn.addEventListener("click", () => {
      const date = reservationDate.value;
      const time = reservationTime.value;
      const party_size = partySize.value;
      const table_number = tableNumber.value;
      const requests = specialRequests.value;

      if (!date || !time || !party_size || !table_number) {
        showToast("Please fill all required fields.", "error");
        return;
      }

      // Populate modal
      modalDate.textContent = date;
      modalTime.textContent = time;
      modalPartySize.textContent = `${party_size} ${
        party_size == 1 ? "Person" : "People"
      }`;
      modalTable.textContent =
        tableNumber.options[tableNumber.selectedIndex].text;
      modalRequests.textContent = requests || "None";

      reservationModal.classList.add("active");
    });

    // Confirm reservation
    if (confirmReservationBtn) {
      confirmReservationBtn.addEventListener("click", () => {
        const formData = new FormData();
        formData.append("action", "submit_reservation");
        formData.append("csrf_token", csrfToken);
        formData.append("date", reservationDate.value);
        formData.append("time", reservationTime.value);
        formData.append("party_size", partySize.value);
        formData.append("table_number", tableNumber.value);
        formData.append("special_requests", specialRequests.value);

        console.log("Submitting reservation:", Object.fromEntries(formData));

        fetch("reserve.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => {
            console.log("Reservation response status:", response.status);
            if (!response.ok)
              throw new Error(
                "Network response was not ok: " + response.statusText
              );
            return response.json();
          })
          .then((data) => {
            console.log("Reservation response:", data);
            showToast(data.message, data.success ? "success" : "error");
            reservationModal.classList.remove("active");
            if (data.success) {
              // Reset form and UI
              reservationForm.reset();
              tableNumber.innerHTML = '<option value="">Select Table</option>';
              tableNumber.disabled = true;
              availabilityMessage.textContent = "";
              // Redirect to account.php after toast
              setTimeout(() => {
                window.location.href = "account.php#reservations";
              }, 2000);
            }
          })
          .catch((error) => {
            console.error("Reservation fetch error:", error);
            showToast(
              "Error submitting reservation: " + error.message,
              "error"
            );
            reservationModal.classList.remove("active");
          });
      });
    }

    // Cancel reservation
    if (cancelReservationBtn) {
      cancelReservationBtn.addEventListener("click", () => {
        reservationModal.classList.remove("active");
      });
    }

    // Close modal on outside click
    if (reservationModal) {
      reservationModal.addEventListener("click", (e) => {
        if (e.target === reservationModal) {
          reservationModal.classList.remove("active");
        }
      });
    }
  }

  // Account page functionality
  // Profile form validation and submission
  const profileForm = document.getElementById("profile-form");
  if (profileForm) {
    profileForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const username = document.getElementById("username").value;
      const email = document.getElementById("email").value;
      const phone = document.getElementById("phone").value;
      const currentPassword = document.getElementById("current_password").value;
      const newPassword = document.getElementById("new_password").value;
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
        (email !== profileForm.dataset.originalEmail || newPassword) &&
        !currentPassword
      ) {
        errors.push(
          "Current password is required to change email or password."
        );
      }
      if (newPassword) {
        if (
          !/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/.test(newPassword) ||
          newPassword.length < 8
        ) {
          errors.push(
            "New password must be 8+ characters with letters and numbers."
          );
        }
        if (newPassword !== confirmPassword) {
          errors.push("New passwords do not match.");
        }
      }

      const errorContainer = document.createElement("ul");
      errorContainer.className = "errors";
      if (errors.length > 0) {
        errors.forEach((error) => {
          const li = document.createElement("li");
          li.textContent = error;
          errorContainer.appendChild(li);
        });
        const existingErrors = profileForm.querySelector(".errors");
        if (existingErrors) existingErrors.remove();
        profileForm.prepend(errorContainer);
        return;
      }

      // Clear errors
      const existingErrors = profileForm.querySelector(".errors");
      if (existingErrors) existingErrors.remove();

      // Submit via AJAX
      const formData = new FormData(profileForm);
      formData.append("action", "update_profile");

      fetch("account.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          showToast(data.message, data.success ? "success" : "error");
          if (data.success) {
            profileForm.dataset.originalEmail = email;
            document.getElementById("current_password").value = "";
            document.getElementById("new_password").value = "";
            document.getElementById("confirm_password").value = "";
            setTimeout(() => {
              window.location.reload();
            }, 2000);
          }
        })
        .catch((error) => {
          showToast("Error updating profile: " + error, "error");
          console.error("Fetch error:", error);
        });
    });
  }

  // Tab switching and hash management
  const tabButtons = document.querySelectorAll(".tab-btn");
  const tabPanes = document.querySelectorAll(".tab-pane");
  if (tabButtons && tabPanes) {
    // Function to activate a tab
    function activateTab(tabId) {
      tabButtons.forEach((btn) => btn.classList.remove("active"));
      tabPanes.forEach((pane) => pane.classList.remove("active"));

      const button = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
      const pane = document.getElementById(tabId);
      if (button && pane) {
        button.classList.add("active");
        pane.classList.add("active");
      }
    }

    // Handle tab clicks and update hash
    tabButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const tabId = button.dataset.tab;
        activateTab(tabId);
        window.location.hash = tabId;
      });
    });

    // On page load, check hash and activate tab
    const hash = window.location.hash.replace("#", "");
    if (hash && ["account-details", "reservations", "orders"].includes(hash)) {
      activateTab(hash);
    } else {
      activateTab("account-details"); // Default tab
    }
  }

  // Cancellation buttons
  const cancelButtons = document.querySelectorAll(".cancel-btn");
  if (cancelButtons) {
    cancelButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const id = button.dataset.id;
        const csrfToken = button.dataset.csrf;
        const isReservation = button.classList.contains(
          "cancel-reservation-btn"
        );
        const type = isReservation ? "reservation" : "order";
        const action = isReservation ? "cancel_reservation" : "cancel_order";
        const currentTab = isReservation ? "reservations" : "orders";

        if (confirm(`Are you sure you want to cancel this ${type}?`)) {
          const formData = new FormData();
          formData.append("action", action);
          formData.append("id", id);
          formData.append("csrf_token", csrfToken);

          fetch("account.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              showToast(data.message, data.success ? "success" : "error");
              if (data.success) {
                setTimeout(() => {
                  window.location.href = `account.php#${currentTab}`;
                }, 2000);
              }
            })
            .catch((error) => {
              showToast(`Error cancelling ${type}.`, "error");
              console.error("Fetch error:", error);
            });
        }
      });
    });
  }
});
