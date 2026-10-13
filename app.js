
/* =========================================
   INTERNTRACK - INTERNSHIP TRACKING SYSTEM
   ========================================= */

(() => {
  "use strict";

  // =========================================
  // STORAGE AND ROLE SETTINGS
  // =========================================

  const KEYS = {
    accounts: "internAccounts",
    oldAccount: "internAccount",
    session: "internLoggedIn",
    current: "internCurrentAccountId",
    theme: "internTheme"
  };

  const roleNames = {
    student: "Student Intern",
    supervisor: "Company Supervisor",
    coordinator: "Internship Coordinator"
  };

  // =========================================
  // ROLE-BASED NAVIGATION
  // Each role has its own menu.
  // =========================================

  const roleMenus = {
    student: [
      ["dashboard", "▦", "Dashboard"],
      ["profile", "◉", "My Profile"],
      ["company", "⌂", "Company / Placement"],
      ["requirements", "☷", "Requirements"],
      ["attendance", "◷", "Attendance & Hours"],
      ["documents", "▤", "My Documents"],
      ["evaluation", "☆", "Evaluation"],
      ["notifications", "♧", "Notifications"]
    ],

    supervisor: [
      ["dashboard", "▦", "Dashboard"],
      ["interns", "♙", "Assigned Interns"],
      ["attendance", "◷", "Attendance Review"],
      ["requirements", "☷", "Requirements Review"],
      ["evaluation", "☆", "Evaluations"],
      ["notifications", "♧", "Notifications"]
    ],

    coordinator: [
      ["dashboard", "▦", "Dashboard"],
      ["students", "♙", "Student Records"],
      ["companies", "⌂", "Company Records"],
      ["requirements", "☷", "Requirements"],
      ["attendance", "◷", "Attendance & Hours"],
      ["documents", "▤", "Documents"],
      ["progress", "↗", "Progress Monitoring"],
      ["reports", "▥", "Reports"]
    ]
  };

  // =========================================
  // HELPER FUNCTIONS
  // =========================================

  const $ = (id) => document.getElementById(id);

  const pages = [
    "login-page",
    "register-page",
    "forgot-page",
    "system-page"
  ];

  let accounts = loadAccounts();
  let currentAccount = null;
  let activePage = "dashboard";

  function loadAccounts() {
    try {
      const saved = JSON.parse(
        localStorage.getItem(KEYS.accounts) || "[]"
      );

      if (Array.isArray(saved) && saved.length) {
        return saved;
      }

      // Migrate the previous single-account version.
      const old = JSON.parse(
        localStorage.getItem(KEYS.oldAccount) || "null"
      );

      if (old && old.id && old.password) {
        const migrated = [{
          ...old,
          role: old.role || "student"
        }];

        localStorage.setItem(
          KEYS.accounts,
          JSON.stringify(migrated)
        );

        return migrated;
      }
    } catch (error) {
      console.warn(
        "Could not read saved demo accounts.",
        error
      );
    }

    return [];
  }

  function saveAccounts() {
    localStorage.setItem(
      KEYS.accounts,
      JSON.stringify(accounts)
    );
  }

  function showOnly(pageId) {
    pages.forEach(id => {
      $(id).classList.toggle(
        "hidden",
        id !== pageId
      );
    });
  }

  function message(id, text) {
    $(id).textContent = text || "";
  }

  // Escape user-provided text before inserting it into HTML.
  function escapeHTML(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      })[character]
    );
  }

  function initials(name) {
    return String(name || "IT")
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part[0]?.toUpperCase() || "")
      .join("") || "IT";
  }

  function titleFor(page) {
    const menu = (
      roleMenus[currentAccount.role] || []
    ).find(item => item[0] === page);

    return menu ? menu[2] : "Dashboard";
  }

  function goToAuth(page) {
    showOnly(page);

    [
      "login-error",
      "register-error",
      "register-success",
      "forgot-error",
      "forgot-success"
    ].forEach(id => {
      if ($(id)) {
        message(id, "");
      }
    });
  }

  // =========================================
  // LOGIN SUCCESS
  // =========================================

  function signIn(account) {
    currentAccount = account;

    localStorage.setItem(KEYS.session, "true");
    localStorage.setItem(KEYS.current, account.id);

    showOnly("system-page");

    buildNavigation();
    fillUserDetails();

    applyTheme(
      localStorage.getItem(KEYS.theme) || "light"
    );

    const now = new Date();

    $("today-label").textContent =
      now.toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
        year: "numeric"
      });

    showPage("dashboard");
  }

  // =========================================
  // DISPLAY LOGGED-IN USER
  // =========================================

  function fillUserDetails() {
    const role = roleNames[currentAccount.role] || "User";

    $("brand-role").textContent = role;
    $("sidebar-name").textContent = currentAccount.name;
    $("sidebar-role").textContent = role;
    $("header-name").textContent = currentAccount.name;
    $("header-role").textContent = role;

    const userInitials = initials(currentAccount.name);

    $("sidebar-avatar").textContent = userInitials;
    $("header-avatar").textContent = userInitials;
  }

  // =========================================
  // BUILD ROLE-SPECIFIC SIDEBAR
  // =========================================

  function buildNavigation() {
    const menu = roleMenus[currentAccount.role]
      || roleMenus.student;

    $("sidebar-nav").innerHTML = menu.map(
      ([page, icon, label]) => `
        <button
          type="button"
          class="menu-btn"
          data-page="${page}"
        >
          <span class="nav-icon" aria-hidden="true">${icon}</span>
          <span>${label}</span>
          <span class="nav-arrow">›</span>
        </button>
      `
    ).join("");

    $("sidebar-nav")
      .querySelectorAll(".menu-btn")
      .forEach(button => {
        button.addEventListener("click", () => {
          showPage(button.dataset.page);
        });
      });
  }

  // =========================================
  // PAGE SUBTITLES
  // =========================================

  const pageSubtitles = {
    dashboard: "Here is your internship overview.",
    profile: "Review your personal internship profile.",
    company: "View your assigned internship placement.",
    requirements: "Review internship requirement status.",
    attendance: "Track and review internship attendance and rendered hours.",
    documents: "Keep internship files organized.",
    evaluation: "Review performance feedback and evaluation.",
    notifications: "Stay updated with internship reminders.",
    interns: "Monitor the interns assigned to your company.",
    students: "Manage student internship records.",
    companies: "Review company and placement information.",
    progress: "Monitor internship progress across students.",
    reports: "Review report categories available for monitoring."
  };

  // =========================================
  // DISPLAY PAGE
  // =========================================

  function showPage(page) {
    if (!currentAccount) {
      goToAuth("login-page");
      return;
    }

    // Only allow pages assigned to the current role.
    const allowed = (
      roleMenus[currentAccount.role] || []
    ).map(item => item[0]);

    if (!allowed.includes(page)) {
      page = "dashboard";
    }

    activePage = page;

    $("page-title").textContent = titleFor(page);
    $("breadcrumb-page").textContent = titleFor(page);

    $("page-subtitle").textContent =
      pageSubtitles[page] ||
      "Manage your internship information.";

    $("sidebar-nav")
      .querySelectorAll(".menu-btn")
      .forEach(button => {
        button.classList.toggle(
          "active",
          button.dataset.page === page
        );
      });

    const render = pageRenderers[page]
      || pageRenderers.dashboard;

    $("content").innerHTML = render();

    bindPageActions();
  }

  // =========================================
  // REUSABLE DISPLAY COMPONENTS
  // =========================================

  function card(label, value, note, icon) {
    return `
      <article class="stat-card">
        <div class="stat-top">
          <span>${label}</span>
          <span class="stat-icon">${icon}</span>
        </div>

        <div class="stat-value">${value}</div>
        <div class="stat-note">${note}</div>
      </article>
    `;
  }

  function panel(title, description, body) {
    return `
      <section class="panel">
        <div class="panel-heading">
          <div>
            <h2>${title}</h2>
            <p>${description}</p>
          </div>
        </div>

        ${body}
      </section>
    `;
  }

  function empty(title, description) {
    return `
      <div class="empty-state">
        <div class="empty-icon">✦</div>
        <strong>${title}</strong>
        <p>${description}</p>
      </div>
    `;
  }

  // =========================================
  // DASHBOARDS AND SYSTEM PAGES
  // =========================================

  const pageRenderers = {

    // =======================================
    // DASHBOARD - DIFFERENT FOR EACH ROLE
    // =======================================

    dashboard() {

      // STUDENT DASHBOARD
      if (currentAccount.role === "student") {
        return `
          <section class="welcome-banner">
            <div>
              <div class="banner-kicker">
                STUDENT WORKSPACE
              </div>

              <h2>
                Welcome back,
                ${escapeHTML(currentAccount.name.split(" ")[0])}!
              </h2>

              <p>
                Keep your internship requirements, attendance,
                and progress on track.
              </p>
            </div>

            <div class="banner-mark">IT</div>
          </section>

          <div class="section-heading">
            <div>
              <h2>Your overview</h2>
              <p>A quick look at your internship activities.</p>
            </div>

            <span class="status-pill">● Active account</span>
          </div>

          <div class="dashboard-grid">
            ${card(
              "Requirements",
              "0",
              "No submitted items recorded",
              "☷"
            )}

            ${card(
              "Rendered hours",
              "0",
              "Hours appear when records are added",
              "◷"
            )}

            ${card(
              "Attendance records",
              "0",
              "No attendance entries yet",
              "▦"
            )}
          </div>

          ${panel(
            "Getting started",
            "Your student workspace",
            `
              <div class="quick-grid">
                <div class="quick-item">
                  <span>01</span>
                  <div>
                    <strong>Review your profile</strong>
                    <p>Check your student details.</p>
                  </div>
                </div>

                <div class="quick-item">
                  <span>02</span>
                  <div>
                    <strong>Check requirements</strong>
                    <p>Track documents you need to submit.</p>
                  </div>
                </div>

                <div class="quick-item">
                  <span>03</span>
                  <div>
                    <strong>Monitor your hours</strong>
                    <p>Review attendance and rendered hours.</p>
                  </div>
                </div>
              </div>
            `
          )}
        `;
      }

      // SUPERVISOR DASHBOARD
      if (currentAccount.role === "supervisor") {
        return `
          <section class="welcome-banner">
            <div>
              <div class="banner-kicker">
                SUPERVISOR WORKSPACE
              </div>

              <h2>
                Welcome,
                ${escapeHTML(currentAccount.name.split(" ")[0])}.
              </h2>

              <p>
                Review assigned interns, verify attendance,
                and provide constructive feedback.
              </p>
            </div>

            <div class="banner-mark">SV</div>
          </section>

          <div class="section-heading">
            <div>
              <h2>Supervision overview</h2>
              <p>Summary of activities in your supervisor workspace.</p>
            </div>

            <span class="status-pill">● Supervisor access</span>
          </div>

          <div class="dashboard-grid">
            ${card(
              "Assigned interns",
              "0",
              "No intern records linked yet",
              "♙"
            )}

            ${card(
              "Attendance reviews",
              "0",
              "No records awaiting review",
              "◷"
            )}

            ${card(
              "Evaluations",
              "0",
              "No evaluations submitted",
              "☆"
            )}
          </div>

          ${panel(
            "Supervisor responsibilities",
            "Tools for supporting your assigned interns",
            `
              <div class="quick-grid">
                <div class="quick-item">
                  <span>01</span>
                  <div>
                    <strong>Monitor assigned interns</strong>
                    <p>Review internship status and progress.</p>
                  </div>
                </div>

                <div class="quick-item">
                  <span>02</span>
                  <div>
                    <strong>Verify attendance</strong>
                    <p>Check recorded hours before approval.</p>
                  </div>
                </div>

                <div class="quick-item">
                  <span>03</span>
                  <div>
                    <strong>Submit feedback</strong>
                    <p>Provide evaluations and guidance.</p>
                  </div>
                </div>
              </div>
            `
          )}
        `;
      }

      // COORDINATOR DASHBOARD
      return `
        <section class="welcome-banner">
          <div>
            <div class="banner-kicker">
              COORDINATOR WORKSPACE
            </div>

            <h2>
              Welcome,
              ${escapeHTML(currentAccount.name.split(" ")[0])}.
            </h2>

            <p>
              Coordinate student records, internship placements,
              requirements, and progress.
            </p>
          </div>

          <div class="banner-mark">CO</div>
        </section>

        <div class="section-heading">
          <div>
            <h2>Program overview</h2>
            <p>
              High-level summary of internship management records.
            </p>
          </div>

          <span class="status-pill">● Coordinator access</span>
        </div>

        <div class="dashboard-grid">
          ${card(
            "Student records",
            "0",
            "No student records added yet",
            "♙"
          )}

          ${card(
            "Company records",
            "0",
            "No company records added yet",
            "⌂"
          )}

          ${card(
            "Reports",
            "0",
            "Reports become available as data is recorded",
            "▥"
          )}
        </div>

        ${panel(
          "Coordination tools",
          "Monitor the key parts of internship management",
          `
            <div class="quick-grid">
              <div class="quick-item">
                <span>01</span>
                <div>
                  <strong>Manage records</strong>
                  <p>Keep student and company information organized.</p>
                </div>
              </div>

              <div class="quick-item">
                <span>02</span>
                <div>
                  <strong>Monitor progress</strong>
                  <p>Review attendance, hours, and requirements.</p>
                </div>
              </div>

              <div class="quick-item">
                <span>03</span>
                <div>
                  <strong>Prepare reports</strong>
                  <p>Review report categories for program monitoring.</p>
                </div>
              </div>
            </div>
          `
        )}
      `;
    },

    // =======================================
    // STUDENT PROFILE
    // =======================================

    profile: () => panel(
      "Student profile",
      "Your account and internship identity",
      `
        <div class="profile-hero">
          <div class="profile-avatar">
            ${initials(currentAccount.name)}
          </div>

          <div>
            <h3>${escapeHTML(currentAccount.name)}</h3>
            <p>${escapeHTML(roleNames[currentAccount.role])}</p>
          </div>
        </div>

        <div class="detail-grid">
          <div class="detail-item">
            <span>Full name</span>
            <strong>${escapeHTML(currentAccount.name)}</strong>
          </div>

          <div class="detail-item">
            <span>ID number</span>
            <strong>${escapeHTML(currentAccount.id)}</strong>
          </div>

          <div class="detail-item">
            <span>Account role</span>
            <strong>${escapeHTML(roleNames[currentAccount.role])}</strong>
          </div>

          <div class="detail-item">
            <span>Account status</span>
            <strong><span class="status-pill">Active</span></strong>
          </div>
        </div>
      `
    ),

    // =======================================
    // COMPANY / PLACEMENT
    // =======================================

    company: () => panel(
      "Company / placement",
      "Placement details will be shown when they are assigned.",
      empty(
        "No placement assigned",
        "Company name, supervisor, work location, and placement details have not been added yet."
      )
    ),

    // =======================================
    // REQUIREMENTS
    // =======================================

    requirements: () => panel(
      currentAccount.role === "supervisor"
        ? "Requirements review"
        : "Internship requirements",

      "Track each requirement and its current status.",

      `
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Requirement</th>
                <th>Status</th>
                <th>Next step</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>Application Form</td>
                <td><span class="tag pending">Pending</span></td>
                <td>Awaiting record</td>
              </tr>

              <tr>
                <td>Endorsement Letter</td>
                <td><span class="tag pending">Pending</span></td>
                <td>Awaiting record</td>
              </tr>

              <tr>
                <td>Internship Documents</td>
                <td><span class="tag pending">Pending</span></td>
                <td>Awaiting record</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="table-note">
          These are sample placeholders. They do not represent
          real submissions or approvals.
        </p>
      `
    ),

    // =======================================
    // ATTENDANCE AND HOURS
    // =======================================

    attendance: () => panel(
      currentAccount.role === "supervisor"
        ? "Attendance review"
        : "Attendance & rendered hours",

      "Attendance data and verified hours will appear here when records are connected.",

      empty(
        "No attendance records yet",
        "Time-in, time-out, QR attendance scans, verification, and rendered-hour totals need to be connected to a data source."
      )
    ),

    // =======================================
    // DOCUMENTS
    // =======================================

    documents: () => panel(
      "Document management",
      "Organize files related to internship requirements.",

      empty(
        "No documents uploaded",
        "File upload and storage are not connected in this browser-only prototype."
      )
    ),

    // =======================================
    // EVALUATION
    // =======================================

    evaluation: () => panel(
      currentAccount.role === "supervisor"
        ? "Intern evaluations"
        : "Evaluation & feedback",

      "Performance feedback and evaluation records.",

      empty(
        "No evaluation records yet",
        "Evaluations will appear when they have been created and saved."
      )
    ),

    // =======================================
    // NOTIFICATIONS
    // =======================================

    notifications: () => panel(
      "Notifications",
      "Important reminders and internship updates.",

      empty(
        "You are all caught up",
        "No notifications have been recorded yet."
      )
    ),

    // =======================================
    // SUPERVISOR: ASSIGNED INTERNS
    // =======================================

    interns: () => panel(
      "Assigned interns",
      "Only interns assigned to your supervisor account should be displayed.",

      empty(
        "No assigned interns",
        "Intern-to-supervisor assignment needs to be set up by the coordinator."
      )
    ),

    // =======================================
    // COORDINATOR: STUDENT RECORDS
    // =======================================

    students: () => panel(
      "Student records",
      "Centralized student internship records.",

      empty(
        "No student records yet",
        "Student profiles will appear here after records are added through a connected management workflow."
      )
    ),

    // =======================================
    // COORDINATOR: COMPANY RECORDS
    // =======================================

    companies: () => panel(
      "Company records",
      "Host company and placement information.",

      empty(
        "No company records yet",
        "Company name, address, contact person, and placement details have not been entered."
      )
    ),

    // =======================================
    // COORDINATOR: PROGRESS MONITORING
    // =======================================

    progress: () => panel(
      "Internship progress monitoring",
      "Monitor student requirements, attendance, rendered hours, and status.",

      empty(
        "No progress data yet",
        "Progress summaries will be available when student records and internship activity data are connected."
      )
    ),

    // =======================================
    // COORDINATOR: REPORTS
    // =======================================

    reports: () => panel(
      "Report center",
      "Report categories planned for the internship program.",

      `
        <div class="report-grid">
          <div class="report-tile">
            <span>◷</span>
            <strong>Attendance report</strong>
            <p>Attendance and rendered-hour summaries.</p>
          </div>

          <div class="report-tile">
            <span>☷</span>
            <strong>Requirements report</strong>
            <p>Submitted, pending, and approved requirements.</p>
          </div>

          <div class="report-tile">
            <span>↗</span>
            <strong>Progress report</strong>
            <p>Internship progress and completion status.</p>
          </div>
        </div>

        <p class="table-note">
          Report generation requires saved internship records;
          this prototype currently displays report categories only.
        </p>
      `
    )
  };

  // Reserved for future page-specific button actions.
  function bindPageActions() {}

  // =========================================
  // LOGIN FORM
  // =========================================

  $("login-form").addEventListener("submit", event => {
    event.preventDefault();

    const id = $("login-id").value.trim();
    const password = $("login-password").value;

    const found = accounts.find(item =>
      item.id === id &&
      item.password === password
    );

    if (!found) {
      message(
        "login-error",
        "ID number or password is incorrect. Please try again."
      );
      return;
    }

    message("login-error", "");
    $("login-form").reset();

    signIn(found);
  });

  // =========================================
  // CREATE ACCOUNT FORM
  // =========================================

  $("register-form").addEventListener("submit", event => {
    event.preventDefault();

    const name = $("register-name").value.trim();
    const id = $("register-id").value.trim();
    const role = $("register-role").value;
    const password = $("register-password").value;
    const confirm = $("register-confirm").value;

    message("register-error", "");
    message("register-success", "");

    if (name.length < 2) {
      return message(
        "register-error",
        "Please enter your full name."
      );
    }

    if (id.length < 5) {
      return message(
        "register-error",
        "ID number must contain at least 5 characters."
      );
    }

    if (!["student", "supervisor", "coordinator"].includes(role)) {
      return message(
        "register-error",
        "Please select a valid account role."
      );
    }

    if (password.length < 6) {
      return message(
        "register-error",
        "Password must be at least 6 characters."
      );
    }

    if (password !== confirm) {
      return message(
        "register-error",
        "Passwords do not match."
      );
    }

    const exists = accounts.some(item =>
      item.id.toLowerCase() === id.toLowerCase()
    );

    if (exists) {
      return message(
        "register-error",
        "That ID number is already registered."
      );
    }

    // Save the new account and its selected role.
    accounts.push({
      name,
      id,
      role,
      password,
      createdAt: new Date().toISOString()
    });

    saveAccounts();

    $("register-form").reset();

    message(
      "register-success",
      "Account created. You can now sign in with your ID and password."
    );

    setTimeout(() => {
      goToAuth("login-page");
    }, 1200);
  });

  // =========================================
  // FORGOT PASSWORD FORM
  // =========================================

  $("forgot-form").addEventListener("submit", event => {
    event.preventDefault();

    const id = $("forgot-id").value.trim();
    const role = $("forgot-role").value;
    const password = $("forgot-password").value;
    const confirm = $("forgot-confirm").value;

    message("forgot-error", "");
    message("forgot-success", "");

    if (password.length < 6) {
      return message(
        "forgot-error",
        "New password must be at least 6 characters."
      );
    }

    if (password !== confirm) {
      return message(
        "forgot-error",
        "Passwords do not match."
      );
    }

    const found = accounts.find(item =>
      item.id.toLowerCase() === id.toLowerCase() &&
      item.role === role
    );

    if (!found) {
      return message(
        "forgot-error",
        "No account matches that ID number and role."
      );
    }

    found.password = password;
    saveAccounts();

    $("forgot-form").reset();

    message(
      "forgot-success",
      "Password reset for this browser demo. You can now sign in."
    );

    setTimeout(() => {
      goToAuth("login-page");
    }, 1400);
  });

  // =========================================
  // PAGE NAVIGATION BUTTONS
  // =========================================

  $("show-register").addEventListener("click", () => {
    goToAuth("register-page");
  });

  $("show-login").addEventListener("click", () => {
    goToAuth("login-page");
  });

  $("show-forgot").addEventListener("click", () => {
    goToAuth("forgot-page");
  });

  $("forgot-back").addEventListener("click", () => {
    goToAuth("login-page");
  });

  // =========================================
  // LOGOUT
  // =========================================

  $("logout-btn").addEventListener("click", () => {
    currentAccount = null;

    localStorage.removeItem(KEYS.session);
    localStorage.removeItem(KEYS.current);

    goToAuth("login-page");
  });

  // =========================================
  // DARK MODE AND LIGHT MODE
  // =========================================

  function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;

    localStorage.setItem(KEYS.theme, theme);

    $("theme-icon").textContent =
      theme === "dark" ? "☀" : "☾";

    $("theme-label").textContent =
      theme === "dark" ? "Light mode" : "Dark mode";
  }

  $("theme-toggle").addEventListener("click", () => {
    const newTheme =
      document.documentElement.dataset.theme === "dark"
        ? "light"
        : "dark";

    applyTheme(newTheme);
  });

  // =========================================
  // INITIAL PAGE AND SESSION
  // =========================================

  const savedTheme =
    localStorage.getItem(KEYS.theme) || "light";

  applyTheme(savedTheme);

  const activeId = localStorage.getItem(KEYS.current);

  const sessionAccount =
    localStorage.getItem(KEYS.session) === "true" &&
    accounts.find(item => item.id === activeId);

  if (sessionAccount) {
    signIn(sessionAccount);
  } else {
    goToAuth("login-page");
  }

})();
