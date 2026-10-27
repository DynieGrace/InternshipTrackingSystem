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
      button.setAttribute("aria-label", show ? "Hide password" : "Show password");
      button.setAttribute("aria-pressed", String(show));
      button.querySelector('[data-password-icon="show"]')?.toggleAttribute("hidden", show);
      button.querySelector('[data-password-icon="hide"]')?.toggleAttribute("hidden", !show);
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
  const scanCanvas = document.createElement("canvas");
  const scanContext = scanCanvas.getContext("2d", { willReadFrequently: true });
  let stream;
  let scanning = false;
  let detector = null;
  let detectorFailures = 0;

  // Native BarcodeDetector only exists on some platforms (Android, ChromeOS,
  // macOS). Windows desktop Chrome/Edge does not have it, so jsQR is used as
  // an offline fallback that decodes frames drawn on a canvas.
  if ("BarcodeDetector" in window) {
    try {
      detector = new window.BarcodeDetector({ formats: ["qr_code"] });
    } catch (error) {
      detector = null;
    }
  }

  const setScanStatus = (message) => {
    if (scanStatus) scanStatus.textContent = message;
  };

  const resetScanButton = () => {
    if (startButton) {
      startButton.disabled = false;
      startButton.textContent = "Start camera scanner";
    }
  };

  const stopCamera = () => {
    scanning = false;
    stream?.getTracks().forEach((track) => track.stop());
    stream = undefined;
    video?.classList.remove("active");
    if (video) video.srcObject = null;
  };

  // Accept a raw token or a QR that wraps it in a URL/query string.
  const normalizeToken = (raw) => {
    const text = String(raw || "").trim();
    const match = text.match(/[0-9a-f]{64}/i);
    return match ? match[0] : text;
  };

  const readFrame = async () => {
    if (detector) {
      const codes = await detector.detect(video);
      return codes.length ? codes[0].rawValue : null;
    }
    if (typeof window.jsQR !== "function" || !video.videoWidth || !video.videoHeight) {
      return null;
    }

    const maxSide = 720;
    const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
    scanCanvas.width = Math.round(video.videoWidth * scale);
    scanCanvas.height = Math.round(video.videoHeight * scale);
    scanContext.drawImage(video, 0, 0, scanCanvas.width, scanCanvas.height);
    const frame = scanContext.getImageData(0, 0, scanCanvas.width, scanCanvas.height);
    const code = window.jsQR(frame.data, frame.width, frame.height, {
      inversionAttempts: "attemptBoth",
    });
    return code ? code.data : null;
  };

  startButton?.addEventListener("click", async () => {
    if (scanning) {
      stopCamera();
      resetScanButton();
      setScanStatus("Scanner stopped.");
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      setScanStatus("This browser cannot open the camera. Enter the student's token manually instead.");
      return;
    }
    if (!detector && typeof window.jsQR !== "function") {
      setScanStatus("The QR decoder did not load. Reload the page or enter the student's token manually.");
      return;
    }

    startButton.disabled = true;
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: "environment" },
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
        audio: false,
      });
      video.srcObject = stream;
      video.classList.add("active");
      await video.play();
      scanning = true;
      startButton.disabled = false;
      startButton.textContent = "Stop camera";
      setScanStatus("Camera active. Hold the QR code inside the video frame.");

      const scan = async () => {
        if (!scanning) return;
        try {
          const raw = await readFrame();
          const token = raw ? normalizeToken(raw) : "";
          if (token) {
            tokenInput.value = token;
            setScanStatus("QR code read. Recording attendance…");
            stopCamera();
            resetScanButton();
            tokenForm.requestSubmit();
            return;
          }
        } catch (error) {
          // A bad frame is normal, but a detector that keeps failing (some
          // Windows builds expose the API without a working backend) is
          // dropped so the offline jsQR decoder takes over.
          if (detector) {
            detectorFailures += 1;
            if (detectorFailures >= 3) {
              detector = null;
              setScanStatus("Native detector unavailable. Using the offline QR decoder…");
            }
          }
        }
        window.setTimeout(scan, 180);
      };
      scan();
    } catch (error) {
      const name = error && error.name ? error.name : "";
      if (name === "NotAllowedError" || name === "SecurityError") {
        setScanStatus("Camera permission was blocked. Click the camera icon in the address bar, choose Allow, then start the scanner again.");
      } else if (name === "NotFoundError" || name === "DevicesNotFoundError" || name === "OverconstrainedError") {
        setScanStatus("No camera was found on this device. Enter the student's token manually instead.");
      } else if (name === "NotReadableError" || name === "TrackStartError") {
        setScanStatus("The camera is already in use by another app. Close it and try again, or enter the token manually.");
      } else {
        setScanStatus("Camera access failed. Allow the camera for localhost in your browser, then try again.");
      }
      console.warn("ITS scanner:", name || error);
      stopCamera();
      resetScanButton();
    }
  });

  window.addEventListener("pagehide", stopCamera);

  /* ---------------- QR codes on ID cards ---------------- */
  document.querySelectorAll("[data-qr-token]").forEach((box) => {
    if (box.dataset.qrReady === "1" || typeof window.QRCode !== "function") return;
    const size = parseInt(box.dataset.qrSize || "160", 10);
    new window.QRCode(box, {
      text: box.dataset.qrToken,
      width: size,
      height: size,
      colorDark: "#111111",
      colorLight: "#ffffff",
      correctLevel: window.QRCode.CorrectLevel.M,
    });
    box.dataset.qrReady = "1";
  });

  /* ---------------- Scan result popup ---------------- */
  document.querySelectorAll("[data-modal]").forEach((modal) => {
    const close = () => modal.classList.remove("open");
    modal.querySelectorAll("[data-modal-close]").forEach((button) => {
      button.addEventListener("click", close);
    });
    modal.addEventListener("click", (event) => {
      if (event.target === modal) close();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && modal.classList.contains("open")) close();
    });
    window.requestAnimationFrame(() => modal.classList.add("open"));
  });

  /* ---------------- Student ID card -> PNG download ---------------- */
  const loadImage = (src) =>
    new Promise((resolve) => {
      if (!src) {
        resolve(null);
        return;
      }
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = () => resolve(null);
      image.src = src;
    });

  const roundRectPath = (ctx, x, y, width, height, radius) => {
    const r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
  };

  const setFittedFont = (ctx, text, maxWidth, size, weight, family) => {
    let current = size;
    ctx.font = `${weight} ${current}px ${family}`;
    while (ctx.measureText(text).width > maxWidth && current > 10) {
      current -= 1;
      ctx.font = `${weight} ${current}px ${family}`;
    }
  };

  const drawIdCard = async (card) => {
    const W = 900;
    const H = 560;
    const scale = 2;
    const canvas = document.createElement("canvas");
    canvas.width = W * scale;
    canvas.height = H * scale;
    const ctx = canvas.getContext("2d");
    ctx.scale(scale, scale);
    ctx.textBaseline = "alphabetic";

    const data = card.dataset;
    const name = data.name || "Student Intern";
    const number = data.number || "-";
    const course = data.course || "BS Information Technology";
    const year = data.year || "4th Year";
    const company = data.company || "Not yet assigned";
    const supervisor = data.supervisor || "To be assigned";
    const valid = data.valid || "";
    const token = data.token || "";

    const family = '"DM Sans", "Segoe UI", Arial, sans-serif';
    const headFamily = 'Manrope, "Segoe UI", Arial, sans-serif';

    const [avatar, logo] = await Promise.all([
      loadImage(data.avatar || ""),
      loadImage(data.logo || ""),
    ]);

    // Interface palette: orange, black, white only.
    const ORANGE = "#ff7300";
    const ORANGE_DEEP = "#e86500";
    const BLACK = "#10141c";
    const WHITE = "#ffffff";

    ctx.save();
    roundRectPath(ctx, 0, 0, W, H, 20);
    ctx.clip();
    ctx.fillStyle = WHITE;
    ctx.fillRect(0, 0, W, H);

    /* Header band */
    ctx.fillStyle = BLACK;
    ctx.fillRect(0, 0, W, 112);
    ctx.fillStyle = ORANGE;
    ctx.fillRect(0, 112, W, 4);

    if (logo) {
      ctx.save();
      ctx.beginPath();
      ctx.arc(58, 56, 28, 0, Math.PI * 2);
      ctx.clip();
      ctx.fillStyle = WHITE;
      ctx.fillRect(30, 28, 56, 56);
      ctx.drawImage(logo, 34, 32, 48, 48);
      ctx.restore();
    } else {
      ctx.fillStyle = ORANGE;
      ctx.beginPath();
      ctx.arc(58, 56, 28, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = BLACK;
      ctx.font = `800 18px ${headFamily}`;
      ctx.textAlign = "center";
      ctx.fillText("ITS", 58, 63);
      ctx.textAlign = "left";
    }

    ctx.fillStyle = WHITE;
    ctx.font = `800 22px ${headFamily}`;
    ctx.fillText("InternTrack", 102, 52);
    ctx.fillStyle = ORANGE;
    ctx.font = `700 10px ${family}`;
    ctx.fillText("STUDENT INTERN IDENTIFICATION", 103, 72);

    ctx.textAlign = "right";
    ctx.fillStyle = ORANGE;
    ctx.font = `700 10px ${family}`;
    ctx.fillText(`${course} · ${year}`.toUpperCase(), W - 32, 48);
    ctx.fillStyle = WHITE;
    ctx.font = `600 10px ${family}`;
    ctx.fillText("INTERNSHIP TRACKING SYSTEM", W - 32, 68);
    ctx.textAlign = "left";

    /* Photo */
    ctx.save();
    roundRectPath(ctx, 44, 146, 170, 200, 12);
    ctx.clip();
    ctx.fillStyle = WHITE;
    ctx.fillRect(44, 146, 170, 200);
    if (avatar) {
      const ratio = Math.max(170 / avatar.width, 200 / avatar.height);
      const drawWidth = avatar.width * ratio;
      const drawHeight = avatar.height * ratio;
      ctx.drawImage(
        avatar,
        44 + (170 - drawWidth) / 2,
        146 + (200 - drawHeight) / 2,
        drawWidth,
        drawHeight
      );
    } else {
      ctx.fillStyle = ORANGE;
      ctx.font = `800 46px ${headFamily}`;
      ctx.textAlign = "center";
      ctx.fillText(name.trim().slice(0, 2).toUpperCase(), 129, 258);
      ctx.textAlign = "left";
    }
    ctx.restore();
    ctx.strokeStyle = BLACK;
    ctx.lineWidth = 2;
    roundRectPath(ctx, 44, 146, 170, 200, 12);
    ctx.stroke();

    /* Identity details */
    const x = 244;
    ctx.fillStyle = ORANGE_DEEP;
    ctx.font = `800 10px ${family}`;
    ctx.fillText("STUDENT INTERN", x, 166);

    setFittedFont(ctx, name, 420, 30, 800, headFamily);
    ctx.fillStyle = BLACK;
    ctx.fillText(name, x, 200);

    const facts = [
      ["STUDENT NO.", number],
      ["PROGRAM", `${course} · ${year}`],
      ["COMPANY", company],
      ["SUPERVISOR", supervisor],
    ];
    let y = 238;
    facts.forEach(([label, value]) => {
      ctx.fillStyle = ORANGE_DEEP;
      ctx.font = `800 9px ${family}`;
      ctx.fillText(label, x, y);
      setFittedFont(ctx, String(value), 400, 14, 600, family);
      ctx.fillStyle = BLACK;
      ctx.fillText(String(value), x, y + 16);
      y += 44;
    });

    /* QR tile */
    const qrSource =
      card.querySelector("[data-qr-token] canvas") || card.querySelector("[data-qr-token] img");
    ctx.save();
    roundRectPath(ctx, 690, 146, 180, 180, 12);
    ctx.clip();
    ctx.fillStyle = WHITE;
    ctx.fillRect(690, 146, 180, 180);
    if (qrSource) ctx.drawImage(qrSource, 700, 156, 160, 160);
    ctx.restore();
    ctx.strokeStyle = BLACK;
    ctx.lineWidth = 2;
    roundRectPath(ctx, 690, 146, 180, 180, 12);
    ctx.stroke();

    ctx.textAlign = "center";
    setFittedFont(ctx, name, 176, 13, 700, family);
    ctx.fillStyle = BLACK;
    ctx.fillText(name, 780, 352);
    ctx.fillStyle = ORANGE_DEEP;
    ctx.font = `800 9px ${family}`;
    ctx.fillText("SCAN FOR ATTENDANCE", 780, 368);
    ctx.textAlign = "left";

    /* Footer */
    ctx.fillStyle = BLACK;
    ctx.fillRect(0, 470, W, H - 470);

    ctx.fillStyle = WHITE;
    ctx.font = `600 10px ${family}`;
    ctx.fillText(`Valid: ${valid}`, 44, 505);
    if (token) {
      ctx.fillStyle = ORANGE;
      ctx.font = `600 9px ${family}`;
      ctx.fillText(`TOKEN ${token}`, 44, 524);
    }

    ctx.restore();

    ctx.strokeStyle = BLACK;
    ctx.lineWidth = 2;
    roundRectPath(ctx, 1, 1, W - 2, H - 2, 19);
    ctx.stroke();
    return canvas;
  };

  document.querySelectorAll("[data-download-id]").forEach((button) => {
    button.addEventListener("click", async () => {
      const card = button.closest("[data-id-card]") || document.querySelector("[data-id-card]");
      const label = button.textContent;
      if (!card) return;
      button.disabled = true;
      button.textContent = "Preparing…";
      try {
        if (document.fonts?.ready) await document.fonts.ready;
        const canvas = await drawIdCard(card);
        const safeNumber = (card.dataset.number || "student").replace(/[^A-Za-z0-9_-]+/g, "");
        canvas.toBlob((blob) => {
          if (!blob) return;
          const link = document.createElement("a");
          link.href = URL.createObjectURL(blob);
          link.download = `StudentID-${safeNumber}.png`;
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.setTimeout(() => URL.revokeObjectURL(link.href), 1500);
        }, "image/png");
      } finally {
        button.disabled = false;
        button.textContent = label;
      }
    });
  });
});
