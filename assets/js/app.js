"use strict";

document.addEventListener("DOMContentLoaded", () => {
  /* ---------------- Sidebar (mobile) ---------------- */
  const sidebar = document.querySelector("#sidebar");
  const menuToggle = document.querySelector("[data-menu-toggle]");
  menuToggle?.addEventListener("click", () => {
    const isOpen = sidebar?.classList.toggle("open") ?? false;
    menuToggle.setAttribute("aria-expanded", String(isOpen));
  });

  /* ---------------- Light / dark theme ---------------- */
  const syncThemeUI = () => {
    const theme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
    const icon = document.querySelector("[data-theme-icon]");
    const label = document.querySelector("[data-theme-label]");
    if (icon) icon.textContent = theme === "dark" ? "☾" : "☀";
    if (label) label.textContent = theme === "dark" ? "Light Mode" : "Dark Mode";
  };
  syncThemeUI();
  document.querySelector("[data-theme-toggle]")?.addEventListener("click", () => {
    const next = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem("its-theme", next); } catch (error) {}
    syncThemeUI();
  });

  /* ---------------- Show / hide password ---------------- */
  document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
      const field = button.parentElement?.querySelector("input");
      if (!field) return;
      const show = field.type === "password";
      field.type = show ? "text" : "password";
      button.textContent = show ? "Hide" : "Show";
      button.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });
  });

  /* ---------------- Confirm before destructive actions ---------------- */
  document.querySelectorAll("[data-confirm]").forEach((element) => {
    const message = element.getAttribute("data-confirm") || "Are you sure?";
    const guard = (event) => {
      if (!window.confirm(message)) {
        event.preventDefault();
        event.stopPropagation();
      }
    };
    element.addEventListener("click", guard);
    element.addEventListener("submit", guard);
  });

  /* ---------------- Print buttons ---------------- */
  document.querySelectorAll("[data-print]").forEach((button) => {
    button.addEventListener("click", () => window.print());
  });

  /* ---------------- Live table search ---------------- */
  document.querySelectorAll("[data-table-filter]").forEach((input) => {
    const table = document.querySelector(input.getAttribute("data-table-filter"));
    if (!table) return;
    input.addEventListener("input", () => {
      const needle = input.value.trim().toLowerCase();
      table.querySelectorAll("tbody tr").forEach((row) => {
        row.hidden = needle !== "" && !row.textContent.toLowerCase().includes(needle);
      });
    });
  });

  /* ---------------- QR camera scanner (student attendance) -------- */
  const startButton = document.querySelector("[data-start-scan]");
  const video = document.querySelector(".scanner video");
  const scanStatus = document.querySelector("[data-scan-status]");
  const tokenInput = document.querySelector('.qr-manual input[name="token"]');
  const tokenForm = document.querySelector(".qr-manual");
  let stream;
  let scanning = false;

  const stopCamera = () => {
    scanning = false;
    stream?.getTracks().forEach((track) => track.stop());
    stream = undefined;
    video?.classList.remove("active");
    if (video) video.srcObject = null;
  };

  startButton?.addEventListener("click", async () => {
    if (!navigator.mediaDevices?.getUserMedia || !("BarcodeDetector" in window)) {
      if (scanStatus) {
        scanStatus.textContent = "This browser cannot scan QR codes with its camera. Enter the coordinator's token manually instead.";
      }
      return;
    }
    try {
      const detector = new BarcodeDetector({ formats: ["qr_code"] });
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: "environment" } },
        audio: false,
      });
      video.srcObject = stream;
      video.classList.add("active");
      await video.play();
      scanning = true;
      if (scanStatus) scanStatus.textContent = "Camera active. Hold the QR code inside the video frame.";

      const scan = async () => {
        if (!scanning) return;
        try {
          const codes = await detector.detect(video);
          if (codes.length && codes[0].rawValue) {
            tokenInput.value = codes[0].rawValue;
            if (scanStatus) scanStatus.textContent = "QR code read. Submitting attendance for server validation.";
            stopCamera();
            tokenForm.requestSubmit();
            return;
          }
        } catch (error) {
          if (scanStatus) scanStatus.textContent = "The QR code could not be read. Try again or enter the token manually.";
          stopCamera();
          return;
        }
        window.setTimeout(scan, 300);
      };
      scan();
    } catch (error) {
      if (scanStatus) scanStatus.textContent = "Camera access failed or was denied. Enter the coordinator's token manually instead.";
      stopCamera();
    }
  });

  window.addEventListener("pagehide", stopCamera);
});
