// JavaScript specific to index.php
// Smooth scrolling for in-page links
document.querySelectorAll(".nav-link").forEach((link) => {
  link.addEventListener("click", function (e) {
    if (this.getAttribute("href").startsWith("#")) {
      e.preventDefault();
      const targetId = this.getAttribute("href").substring(1);
      const targetElement = document.getElementById(targetId);
      if (targetElement) {
        window.scrollTo({
          top: targetElement.offsetTop - 60, // Adjust for navbar height
          behavior: "smooth",
        });
      }
    }
  });
});

// Mobile menu toggle
const menuToggle = document.querySelector(".menu-toggle");
const navLinks = document.querySelector(".nav-links");

menuToggle.addEventListener("click", () => {
  navLinks.classList.toggle("active");
  const isActive = navLinks.classList.contains("active");
  menuToggle.querySelectorAll("span").forEach((span, index) => {
    if (isActive) {
      if (index === 0)
        span.style.transform = "rotate(45deg) translate(5px, 5px)";
      if (index === 1) span.style.opacity = "0";
      if (index === 2)
        span.style.transform = "rotate(-45deg) translate(7px, -7px)";
    } else {
      span.style.transform = "none";
      span.style.opacity = "1";
    }
  });
});
