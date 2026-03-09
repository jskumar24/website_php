/**
 * ts12-auth.js  –  TS-12 Session Guard & Role Enforcement
 * --------------------------------------------------------
 * Add as the LAST <script> tag on every page EXCEPT login.html.
 *
 * What it does:
 *   1. Reads session from localStorage ("ts12_user")
 *   2. Redirects to login.html if no valid session
 *   3. Blocks preceptors from admin-only pages
 *   4. Injects a live user dropdown in the navbar
 *   5. Hides sidebar items preceptors cannot access
 *   6. Disables delete buttons for non-ZC/DC users
 */

(function () {
  "use strict";

  /* ════════════════════════════════════════════════════
     STEP 1 – Read session
  ════════════════════════════════════════════════════ */
  var session = null;
  try {
    var raw = localStorage.getItem("ts12_user");
    if (raw) session = JSON.parse(raw);
  } catch (e) { session = null; }

  /* Path helpers */
  var path      = window.location.pathname;
  var page      = (path.split("/").pop() || "index.html").split("?")[0];
  var inTables  = path.indexOf("/pages/tables/") !== -1;
  var loginUrl  = inTables ? "../../login.html" : "login.html";
  var indexUrl  = inTables ? "../../index.html" : "index.html";

  /* ════════════════════════════════════════════════════
     STEP 2 – Guard: no session → login
  ════════════════════════════════════════════════════ */
  if (!session || !session.srcm_id) {
    window.location.replace(loginUrl);
    return;
  }

  var roles     = (session.roles || []).map(function (r) { return r.toLowerCase(); });
  var roleLevel = session.roleLevel  || "preceptor";
  var canDelete = session.canDelete  === true;

  /* ════════════════════════════════════════════════════
     STEP 3 – Page access (preceptors blocked from admin pages)
  ════════════════════════════════════════════════════ */
  var adminOnly = [
    "view-abhyasis.html",
    "view-centers.html",
    "view-subcenters.html",
    "view-volunteer-works.html",
    "view-volunteer-work-abhyasi.html"
  ];

  if (roleLevel === "preceptor" && adminOnly.indexOf(page) !== -1) {
    window.location.replace(indexUrl);
    return;
  }

  /* ════════════════════════════════════════════════════
     STEP 4 – Inject user dropdown into navbar
  ════════════════════════════════════════════════════ */
  function buildDropdown() {
    var initial  = (session.name || "?").charAt(0).toUpperCase();
    var badgeHtml = session.roles.map(function (r) {
      return '<span style="display:inline-block;font-size:.58rem;font-weight:700;padding:1px 7px;'
           + 'border-radius:20px;background:rgba(255,255,255,.25);color:#fff;'
           + 'border:1px solid rgba(255,255,255,.4);text-transform:uppercase;margin:1px;">'
           + r + '</span>';
    }).join("");

    var accessLabel = canDelete
      ? '<span style="color:#00b894;font-weight:700;">Full Access + Delete</span>'
      : roleLevel === "full"
        ? '<span style="color:#4B49AC;font-weight:700;">Full Access</span>'
        : '<span style="color:#e17055;font-weight:700;">Limited Access</span>';

    return '<li class="nav-item dropdown d-none d-lg-block ts12-user-dd">'
      + '<a class="nav-link" id="TS12DD" href="#" data-bs-toggle="dropdown" aria-expanded="false">'
      + '<div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#4B49AC,#7b79d4);'
      + 'display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.85rem;'
      + 'border:2px solid rgba(75,73,172,.3);">' + initial + '</div></a>'
      + '<div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="TS12DD"'
      + ' style="min-width:220px;border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(75,73,172,.18);border:1px solid #e4e4f5;">'
      /* Header */
      + '<div style="background:linear-gradient(135deg,#4B49AC,#7b79d4);padding:.9rem 1rem;text-align:center;">'
      + '<div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.25);'
      + 'display:flex;align-items:center;justify-content:center;margin:0 auto .4rem;'
      + 'font-size:1.4rem;font-weight:800;color:#fff;border:2px solid rgba(255,255,255,.4);">' + initial + '</div>'
      + '<p style="margin:0;font-weight:700;color:#fff;font-size:.88rem;">' + (session.name || "") + '</p>'
      + '<p style="margin:2px 0 5px;color:rgba(255,255,255,.75);font-size:.7rem;">' + session.srcm_id + '</p>'
      + '<div>' + badgeHtml + '</div>'
      + '</div>'
      /* Access row */
      + '<div style="padding:.5rem .9rem;border-bottom:1px solid #eee;font-size:.76rem;display:flex;align-items:center;gap:.4rem;">'
      + '<i class="mdi mdi-shield-check-outline" style="color:#4B49AC;font-size:.95rem;"></i>' + accessLabel
      + '</div>'
      /* Sign out */
      + '<a class="dropdown-item" onclick="ts12Logout()" style="cursor:pointer;color:#d63031;font-size:.82rem;padding:.55rem .9rem;">'
      + '<i class="dropdown-item-icon mdi mdi-power text-danger me-2"></i>Sign Out</a>'
      + '</div></li>';
  }

  function injectDropdown() {
    /* Remove old static dropdown */
    document.querySelectorAll(".user-dropdown, .ts12-user-dd").forEach(function (el) { el.remove(); });
    /* Also hide the login button if present */
    var loginBtn = document.getElementById("ts12LoginNavItem");
    if (loginBtn) loginBtn.style.display = "none";
    /* Add our dropdown to the navbar */
    var navs = document.querySelectorAll(".navbar-menu-wrapper .navbar-nav.ms-auto");
    navs.forEach(function (ul) {
      ul.insertAdjacentHTML("beforeend", buildDropdown());
    });
  }

  /* ════════════════════════════════════════════════════
     STEP 5 – Hide restricted sidebar links (preceptors)
  ════════════════════════════════════════════════════ */
  var restrictedPages = [
    "view-centers.html",
    "view-subcenters.html",
    "view-abhyasis.html",
    "view-volunteer-works.html",
    "view-volunteer-work-abhyasi.html"
  ];

  function hideSidebarLinks() {
    if (roleLevel === "full") return;

    document.querySelectorAll("#sidebar a.nav-link, .sidebar a.nav-link").forEach(function (a) {
      var href = (a.getAttribute("href") || "").split("/").pop().split("?")[0];
      if (restrictedPages.indexOf(href) !== -1) {
        var li = a.closest("li");
        if (li) li.style.display = "none";
      }
    });

    /* Hide parent collapse sections with no visible children */
    document.querySelectorAll("#sidebar .collapse, .sidebar .collapse").forEach(function (col) {
      var items = col.querySelectorAll("li.nav-item");
      if (items.length && Array.from(items).every(function (li) { return li.style.display === "none"; })) {
        var parentLi = col.closest("li.nav-item");
        if (parentLi) parentLi.style.display = "none";
      }
    });
  }

  /* ════════════════════════════════════════════════════
     STEP 6 – Disable delete buttons for non-ZC/DC users
  ════════════════════════════════════════════════════ */
  var TIP = "Delete access is restricted to ZC and DC roles only";

  function disableDeleteButtons() {
    if (canDelete) return;

    function lockBtn(btn) {
      if (!btn || btn._ts12locked) return;
      btn._ts12locked      = true;
      btn.disabled         = true;
      btn.title            = TIP;
      btn.style.opacity    = "0.3";
      btn.style.cursor     = "not-allowed";
      btn.style.pointerEvents = "none";
    }

    /* btn-outline-danger = delete buttons in all CRUD tables */
    document.querySelectorAll(".btn-outline-danger").forEach(lockBtn);
    /* Events page delete + remove buttons */
    document.querySelectorAll(".row-del, .img-del-btn").forEach(lockBtn);
    /* Confirm delete modal button */
    var confirmBtn = document.getElementById("confirmDeleteBtn");
    if (confirmBtn) lockBtn(confirmBtn);

    /* Intercept JS delete functions (set once) */
    if (!window._ts12DeleteBlocked) {
      window._ts12DeleteBlocked = true;
      var noop = function () { alert(TIP); return false; };
      window.openDeleteModal = noop;
      window.deleteEvent     = noop;
      window.removeAbhyasi   = noop;
      window.deleteImage     = noop;
    }
  }

  /* ════════════════════════════════════════════════════
     Globals
  ════════════════════════════════════════════════════ */
  window.ts12User      = session;
  window.ts12Roles     = roles;
  window.ts12RoleLevel = roleLevel;
  window.ts12CanDelete = canDelete;

  window.ts12Logout = function () {
    localStorage.removeItem("ts12_user");
    window.location.replace(loginUrl);
  };

  /* ════════════════════════════════════════════════════
     Boot on DOM ready
  ════════════════════════════════════════════════════ */
  function boot() {
    injectDropdown();
    hideSidebarLinks();
    disableDeleteButtons();

    /* Re-run delete gate after dynamic content loads */
    setTimeout(disableDeleteButtons, 500);
    setTimeout(disableDeleteButtons, 1500);

    /* Watch for dynamically rendered rows */
    var obs = new MutationObserver(function () { disableDeleteButtons(); });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

})();
