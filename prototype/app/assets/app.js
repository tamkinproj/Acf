/* Shared core for every page: text (EN/AR), role-based menus, top and bottom bars, sync status and common helpers.
   It talks to the server through api.js (which keeps everything working offline).
   Each page loads api.js, then this file, then its own small script from assets/pages/. */
(function () {
  "use strict";
  var API = window.ACFAPI, CUR = "AED", root = document.documentElement;

  /* ---------------- text ---------------- */
  var S = {
    en: {
      brand: "Al Insania Charity Foundation", staff: "Staff portal", signin: "Sign in", email: "Email", password: "Password",
      login_hint: "Sign in with your account. After the first sign-in the app also works without internet.",
      login_err: "Enter your email and password.", login_fail: "Wrong email or password.", login_offline: "The first sign-in needs an internet connection.",
      login_expired: "Your session ended. Sign in again; anything not yet sent is kept.", login_busy: "Signing in…", login_locked: "Too many attempts. Wait a minute and try again.",
      n_home: "Home", n_people: "Orphans", n_apps: "Applications", set_sync_short: "Offline and sync", n_pay: "Payments", n_proj: "Projects", n_rep: "Reports", n_set: "Settings", n_users: "Accounts", n_audit: "Activity", n_system: "System", n_updates: "Updates", n_website: "Website", n_more: "More", n_family: "My family",
      loading: "Loading…", none: "No matches.", back: "Back", cancel: "Cancel", save: "Save", required: "This field is required.", lang_btn: "العربية", signout: "Sign out",
      noaccess: "Your role does not include this page.", per_month: "per month",
      offline_none: "Nothing is saved on this phone for this page yet. Open it once while connected.", load_err: "Could not load. Try again.",
      stale: "Offline. Showing the copy saved on this phone.", phone_saved: "Saved on this phone. It is sent when you are online.", sent_ok: "Saved",
      c_offline: "Offline", c_waiting: "{n} to send", c_failed: "{n} need attention", c_sending: "Sending…", c_ok: "All sent",
      retry: "Try again", discard: "Discard", back_site: "Back to website", reload_new: "A new version is ready. Tap to update.",
      need_conn: "This needs an internet connection.", expired_bar: "Sign in again to send your waiting changes."
    },
    ar: {
      brand: "مؤسسة الإنسانية الخيرية", staff: "بوابة الموظفين", signin: "تسجيل الدخول", email: "البريد الإلكتروني", password: "كلمة المرور",
      login_hint: "سجّل الدخول بحسابك. بعد أول تسجيل دخول يعمل التطبيق أيضًا بدون إنترنت.",
      login_err: "أدخل بريدك وكلمة المرور.", login_fail: "البريد أو كلمة المرور غير صحيحة.", login_offline: "أول تسجيل دخول يحتاج اتصالًا بالإنترنت.",
      login_expired: "انتهت الجلسة. سجّل الدخول من جديد؛ ما لم يُرسل بعد محفوظ.", login_busy: "جارٍ تسجيل الدخول…", login_locked: "محاولات كثيرة. انتظر دقيقة ثم حاول مجددًا.",
      n_home: "الرئيسية", n_people: "الأيتام", n_apps: "الطلبات", set_sync_short: "العمل دون اتصال والمزامنة", n_pay: "المدفوعات", n_proj: "المشاريع", n_rep: "التقارير", n_set: "الإعدادات", n_users: "الحسابات", n_audit: "النشاط", n_system: "النظام", n_updates: "التحديثات", n_website: "الموقع", n_more: "المزيد", n_family: "عائلتي",
      loading: "جارٍ التحميل…", none: "لا نتائج.", back: "رجوع", cancel: "إلغاء", save: "حفظ", required: "هذا الحقل مطلوب.", lang_btn: "English", signout: "تسجيل الخروج",
      noaccess: "دورك لا يشمل هذه الصفحة.", per_month: "شهريًا",
      offline_none: "لا يوجد شيء محفوظ على هذا الهاتف لهذه الصفحة بعد. افتحها مرة واحدة أثناء الاتصال.", load_err: "تعذّر التحميل. حاول مجددًا.",
      stale: "بدون اتصال. تُعرض النسخة المحفوظة على هذا الهاتف.", phone_saved: "حُفظ على هذا الهاتف. يُرسل عند عودة الاتصال.", sent_ok: "تم الحفظ",
      c_offline: "بدون اتصال", c_waiting: "{n} بانتظار الإرسال", c_failed: "{n} تحتاج انتباهًا", c_sending: "جارٍ الإرسال…", c_ok: "تم إرسال الكل",
      retry: "حاول مجددًا", discard: "تجاهل", back_site: "العودة إلى الموقع", reload_new: "إصدار جديد جاهز. اضغط للتحديث.",
      need_conn: "هذا يحتاج اتصالًا بالإنترنت.", expired_bar: "سجّل الدخول من جديد لإرسال تغييراتك المنتظرة."
    }
  };
  var lang = "en";
  try { if (localStorage.getItem("acf_lang") === "ar") lang = "ar"; } catch (e) {}
  function t(k, v) {
    var s = (S[lang] && S[lang][k]) || S.en[k] || k;
    if (v) for (var n in v) s = s.replace("{" + n + "}", v[n]);
    return s;
  }
  function strings(en, ar) { for (var k in en) S.en[k] = en[k]; for (var j in ar) S.ar[j] = ar[j]; }

  /* ---------------- helpers ---------------- */
  function $(q, r) { return (r || document).querySelector(q); }
  function esc(s) { return String(s === null || s === undefined ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function ic(n, cls) { return '<svg class="icon ' + (cls || "") + '"><use href="#i-' + n + '"/></svg>'; }
  function can(p) { return API.can(p); }
  function user() { return API.user(); }
  function showAmounts() { var u = user(); return !(u && u.preferences && u.preferences.show_amounts === false); }
  function money(n) { return showAmounts() ? CUR + " " + Number(n || 0).toLocaleString("en-US") : "••••"; }
  function name(c) { return lang === "ar" && c.name_ar ? c.name_ar : (c.name_en || ""); }
  function facts(rows) { return '<dl class="facts">' + rows.map(function (r) { return "<div><dt>" + r[0] + "</dt><dd>" + r[1] + "</dd></div>"; }).join("") + "</dl>"; }
  function monthKey(d) { return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); }
  function months() {
    var out = [], d = new Date(); d.setDate(1);
    for (var i = 0; i < 3; i++) { var x = new Date(d.getFullYear(), d.getMonth() - i, 1); out.push({ k: monthKey(x), label: x.toLocaleDateString(lang === "ar" ? "ar" : "en-GB", { month: "long", year: "numeric" }) }); }
    return out;
  }
  function monthLabel(k) { var m = months().filter(function (x) { return x.k === k; })[0]; return m ? m.label : k; }
  function msg(e) { return (e && e.message) || t("load_err"); }
  var toastTimer;
  function toast(m) {
    var el = $("#toast"); if (!el) return; el.textContent = m; el.classList.add("show");
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.classList.remove("show"); }, 2800);
  }
  function ui() { try { return JSON.parse(sessionStorage.getItem("acf_ui")) || {}; } catch (e) { return {}; } }
  var UI = ui(); if (!UI.month) UI.month = monthKey(new Date()); if (!UI.scope) UI.scope = "all"; if (!UI.tab) UI.tab = "all";
  function saveUI() { try { sessionStorage.setItem("acf_ui", JSON.stringify(UI)); } catch (e) {} }

  /* ---------------- icons ---------------- */
  var SPRITE = '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>' +
    '<symbol id="i-home" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></symbol>' +
    '<symbol id="i-users" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></symbol>' +
    '<symbol id="i-wallet" viewBox="0 0 24 24"><path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></symbol>' +
    '<symbol id="i-folder" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></symbol>' +
    '<symbol id="i-sliders" viewBox="0 0 24 24"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></symbol>' +
    '<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></symbol>' +
    '<symbol id="i-plus" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>' +
    '<symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>' +
    '<symbol id="i-back" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></symbol>' +
    '<symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></symbol>' +
    '<symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>' +
    '<symbol id="i-edit" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></symbol>' +
    '<symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></symbol>' +
    '<symbol id="i-grid" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/></symbol>' +
    '<symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></symbol>' +
    '<symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></symbol>' +
    '<symbol id="i-dots" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/></symbol>' +
    '<symbol id="i-up" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></symbol>' +
    '<symbol id="i-down" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></symbol>' +
    '<symbol id="i-chev" viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></symbol>' +
    '<symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></symbol>' +
    '<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>' +
    '<symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></symbol>' +
 '<symbol id="i-menu" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></symbol>' +
    '<symbol id="i-cloud" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></symbol>' +
    "</defs></svg>";

  /* ---------------- menus by role ---------------- */
  // [id, page, text key, permission needed (null = everyone), icon]
  var MENU = [
    ["dashboard", "dashboard.html", "n_home", null, "grid"], ["people", "people.html", "n_people", "children.view", "users"],
    ["applications", "applications.html", "n_apps", "applications.review", "file"], ["payments", "payments.html", "n_pay", "payments.view", "wallet"], ["projects", "projects.html", "n_proj", "projects.view", "folder"], ["website", "website.html", "n_website", "site.edit", "globe"],
    ["reports", "reports.html", "n_rep", "reports.view", "file"], ["family", "family.html", "n_family", "family.view_own", "users"],
    ["users", "users.html", "n_users", "users.manage", "shield"], ["audit", "audit.html", "n_audit", "audit.read", "file"],
    ["system", "system.html", "n_system", "system.health", "sliders"], ["updates", "updates.html", "n_updates", "system.updates", "download"], ["settings", "settings.html", "n_set", null, "sliders"]
  ];
  function allowed() { return MENU.filter(function (m) { return !m[3] || can(m[3]); }); }
  var spec = null, state = null, loadSeq = 0, moreItems = [];

  function chipHtml() {
    var s = API.state(), c = null;
    if (s.failed > 0) c = ["bad", t("c_failed", { n: s.failed })];
    else if (s.expired && s.waiting > 0) c = ["bad", t("expired_bar")];
    else if (s.syncing) c = ["wait", t("c_sending")];
    else if (s.waiting > 0) c = ["wait", t("c_waiting", { n: s.waiting })];
    else if (!s.online) c = ["off", t("c_offline")];
    return c ? '<a class="chip ' + c[0] + '" href="settings.html#outbox" id="syncChip">' + ic("cloud") + "<span>" + esc(c[1]) + "</span></a>" : '<span id="syncChip"></span>';
  }
  function initial() { var u = user(); return u && u.name ? u.name.trim().charAt(0).toUpperCase() : "?"; }
  function bellDot() { var st = API.state(); return st.failed > 0 || st.waiting > 0 || st.expired; }
  function shell() {
    var cur = spec.nav, items = allowed();
    var PRI = ["dashboard", "people", "applications", "payments", "website", "projects", "users", "system", "updates", "audit", "family", "reports", "settings"];
    var sorted = items.slice().sort(function (a, b) { return PRI.indexOf(a[0]) - PRI.indexOf(b[0]); });
    /* top bar: brand left, sections as pills in the middle, round tools and the account on the right */
    $("#nav").innerHTML = '<div class="row"><a class="brand" href="dashboard.html" aria-label="' + esc(t("n_home")) + '"><span class="logo" role="img" aria-hidden="true"></span><span><span>' + t("brand") + "</span><small>" + t("staff") + "</small></span></a>" +
      '<nav class="mo-tabs" aria-label="Main"><ul class="menu">' + sorted.map(function (m) { return '<li><a href="' + m[1] + '"' + (cur === m[0] ? ' class="active" aria-current="page"' : "") + ">" + t(m[2]) + "</a></li>"; }).join("") + "</ul></nav>" +
      '<div class="tools"><span id="chipBox">' + chipHtml() + '</span>' +
      '<a class="mo-circle" id="bellBtn" href="settings.html#outbox" aria-label="' + esc(t("set_sync_short")) + '">' + ic("bell") + (bellDot() ? '<i class="dot"></i>' : "") + "</a>" +
      '<button class="mo-circle" type="button" data-act="lang" aria-label="Language">' + ic("globe") + "</button>" +
      '<a class="mo-circle mo-hide-sm" href="settings.html" aria-label="' + esc(t("n_set")) + '">' + ic("gear") + "</a>" +
      '<a class="mo-avatar" href="settings.html" aria-label="' + esc(t("n_set")) + '">' + esc(initial()) + "</a></div></div>";
    /* left rail (large screens): the same sections as icons; the active one sits in a black circle. Settings, language and sign-out below. */
    var main = sorted.filter(function (m) { return m[0] !== "settings"; });
    $("#tabbar").innerHTML = '<div class="mo-rail">' + main.map(function (m) { return '<a href="' + m[1] + '" title="' + esc(t(m[2])) + '"' + (cur === m[0] ? ' class="active" aria-current="page"' : "") + ">" + ic(m[4]) + '<span class="sr">' + t(m[2]) + "</span></a>"; }).join("") + "</div>" +
      '<div class="mo-rail mo-rail-foot"><a href="settings.html" title="' + esc(t("n_set")) + '"' + (cur === "settings" ? ' class="active" aria-current="page"' : "") + ">" + ic("gear") + '<span class="sr">' + t("n_set") + "</span></a>" +
      '<button type="button" data-act="lang" title="Language">' + ic("globe") + '<span class="sr">' + t("lang_btn") + "</span></button>" +
      '<button type="button" data-act="signout" title="' + esc(t("signout")) + '">' + ic("logout") + '<span class="sr">' + t("signout") + "</span></button></div>";
    moreItems = [];
    var act = $(".mo-tabs a.active"); if (act && act.scrollIntoView && window.innerWidth < 1024) { try { act.scrollIntoView({ block: "nearest", inline: "center" }); } catch (e) {} }
  }
  function refreshChip() { var b = $("#chipBox"); if (b) b.innerHTML = chipHtml(); var bell = $("#bellBtn"); if (bell) { var d = bell.querySelector(".dot"); if (bellDot() && !d) bell.insertAdjacentHTML("beforeend", '<i class="dot"></i>'); else if (!bellDot() && d) d.remove(); } }

  function chrome() {
    root.lang = lang; root.dir = lang === "ar" ? "rtl" : "ltr";
    document.title = t(spec.title) + " | " + t("brand");
    Array.prototype.forEach.call(document.querySelectorAll("[data-t]"), function (n) { n.textContent = t(n.getAttribute("data-t")); });
    if (spec.shell) shell();
  }
  function paint() {
    chrome();
    if (!spec.render) return;
    var v = $("#view");
    if (state && state.error) { v.innerHTML = '<p class="note">' + esc(state.error) + '</p><div class="actions"><button class="btn btn-line" data-act="reload">' + t("retry") + "</button></div>"; return; }
    if (!state || state.loading) { v.innerHTML = '<p class="note">' + t("loading") + "</p>"; return; }
    v.innerHTML = (state.stale ? '<p class="banner">' + ic("cloud") + t("stale") + "</p>" : "") + spec.render(state.data, state);
    if (spec.after) spec.after(state.data, state);
  }
  function load(quiet) {
    if (!spec.load) { state = { data: null }; paint(); return; }
    var seq = ++loadSeq;
    if (!quiet) { state = { loading: true }; paint(); }
    spec.load().then(function (r) {
      if (seq !== loadSeq) return;
      state = { data: r.data, stale: !!r.stale, meta: r.meta, at: r.at };
      paint();
    }, function (e) {
      if (seq !== loadSeq) return;
      if (e && e.status === 401) { location.replace("login.html?expired=1"); return; }
      state = { error: e && e.offline ? t(spec.needsConn ? "need_conn" : "offline_none") : msg(e) }; paint();
    });
  }

  /* ---------------- sheet (pop-up form) ---------------- */
  function sheet(title, body) {
    $("#sheetRoot").innerHTML = '<div class="backdrop" data-act="close"><div class="sheet" role="dialog" aria-modal="true" aria-label="' + esc(title) + '"><div class="sheet-head"><h3>' + esc(title) + '</h3><button class="sheet-x" type="button" data-act="close" aria-label="' + esc(t("cancel")) + '">&times;</button></div>' + body + "</div></div>";
    document.documentElement.classList.add("sheet-open");   // the page behind stops scrolling while a sheet is open
    // (no automatic focus on a phone: it would pop the keyboard up over the form before the person has read it)
    var f = $("#sheetRoot input, #sheetRoot select"); if (f && window.matchMedia && window.matchMedia("(hover:hover)").matches) f.focus();
  }
  function closeSheet() { $("#sheetRoot").innerHTML = ""; document.documentElement.classList.remove("sheet-open"); }
  function field(id, label, extra, type, attrs) {
    return '<div><label for="' + id + '">' + label + '</label><input id="' + id + '" type="' + (type || "text") + '" ' + (attrs || "") + ">" + (extra || "") + '<div class="err" id="' + id + 'Err"></div></div>';
  }
  function showErrors(map, prefix) {
    // maps server field errors onto the form's inputs; returns true if any field matched
    var hit = false;
    Object.keys(map || {}).forEach(function (k) {
      var el = document.getElementById(prefix + k.replace(/[^a-z0-9]/gi, "_") + "Err");
      if (el) { el.textContent = map[k][0]; hit = true; }
    });
    return hit;
  }
  function formEnd(saveKey) { return '<div class="err" id="formErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit">' + t(saveKey || "save") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("cancel") + "</button></div>"; }

  /* ---------------- actions and forms registered by pages ---------------- */
  var ACTIONS = {}, FORMS = {};
  function act(n, f) { ACTIONS[n] = f; }
  function form(id, f) { FORMS[id] = f; }

  ACTIONS.lang = function () { lang = lang === "en" ? "ar" : "en"; try { localStorage.setItem("acf_lang", lang); } catch (x) {} paint(); };
  ACTIONS.reload = function () { load(); };
  ACTIONS.more = function (el, e) {
    e.preventDefault();
    sheet(t("n_more"), '<div class="list">' + moreItems.map(function (m) { return '<a class="item" href="' + m[1] + '"><span class="grow"><b>' + t(m[2]) + "</b></span>" + ic(m[4]) + "</a>"; }).join("") + '</div><div class="actions"><button class="btn btn-line" type="button" data-act="close">' + t("cancel") + "</button></div>");
  };
  ACTIONS.close = function (el, e) { if (e.target === el || el.tagName === "BUTTON") closeSheet(); };
  ACTIONS.tab = function (el) { UI.tab = el.getAttribute("data-v"); saveUI(); paint(); };
  ACTIONS.month = function (el) { UI.month = el.getAttribute("data-v"); saveUI(); load(); };
  ACTIONS.scope = function (el) { UI.scope = el.getAttribute("data-v"); saveUI(); load(); };
  ACTIONS.signout = function () {
    var n = API.counts().total;
    if (n && !confirm(lang === "ar" ? "لديك " + n + " تغييرات لم تُرسل بعد. تسجيل الخروج سيُبقيها على الهاتف حتى تسجّل الدخول بنفس الحساب. متابعة؟" : n + " change(s) are not sent yet. They stay on this phone until you sign in again with the same account. Sign out anyway?")) return;
    API.logout().then(function () { location.href = "login.html"; });
  };
  document.addEventListener("click", function (e) {
    var el = e.target.closest("[data-act]"); if (!el) return;
    var a = el.getAttribute("data-act");
    if (ACTIONS[a]) ACTIONS[a](el, e);
  });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && $("#sheetRoot")) closeSheet(); });
  document.addEventListener("submit", function (e) {
    var f = e.target; if (FORMS[f.id]) { e.preventDefault(); FORMS[f.id](f); }
  });

  // phones and tablets: hide the bars while scrolling down, show them when scrolling up
  var lastY = 0, ticking = false;
  window.addEventListener("scroll", function () {
    if (ticking) return; ticking = true;
    requestAnimationFrame(function () {
      var y = window.scrollY, d = y - lastY;
      if (y < 80 || d < -6) root.classList.remove("bars-hidden"); else if (d > 6) root.classList.add("bars-hidden");
      if (Math.abs(d) > 6 || y < 80) lastY = y; ticking = false;
    });
  }, { passive: true });

  var wasOnline = navigator.onLine !== false;
  API.onChange(function (s) {
    refreshChip();
    if (s.online !== wasOnline) { wasOnline = s.online; if (spec && spec.shell) toast(t(s.online ? "c_ok" : "c_offline")); if (s.online && spec && spec.load && !$("#sheetRoot").innerHTML) load(true); }
    if (s.synced && spec && spec.load && !($("#sheetRoot") && $("#sheetRoot").innerHTML)) load(true);
    if (spec && spec.onSync) spec.onSync(s);
  });
  document.addEventListener("acf-update", function () {
    var b = document.createElement("button"); b.className = "banner upd"; b.textContent = t("reload_new");
    b.onclick = function () { location.reload(); }; var v = $("#view"); if (v) v.parentNode.insertBefore(b, v);
  });

  /* ---------------- start a page ---------------- */
  window.ACF = {
    t: t, strings: strings, esc: esc, ic: ic, name: name, money: money, facts: facts, months: months, monthLabel: monthLabel, toast: toast, msg: msg,
    can: can, user: user, api: API, ui: UI, saveUI: saveUI, sheet: sheet, closeSheet: closeSheet, field: field, formEnd: formEnd, showErrors: showErrors,
    act: act, form: form, lang: function () { return lang; }, paint: paint, reload: function (q) { load(q); }, $: $, CUR: CUR,
    start: function (s) {
      spec = s;
      var holder = document.createElement("div"); holder.innerHTML = SPRITE; document.body.insertBefore(holder.firstChild, document.body.firstChild);
      if (s.page === "login") {
        if (API.session() && !API.expired()) { location.replace("dashboard.html"); return; }
        chrome(); if (s.after) s.after(); return;
      }
      if (!API.session()) { location.replace("login.html"); return; }
      if (API.expired() && navigator.onLine !== false) { location.replace("login.html?expired=1"); return; }
      if (s.perm && !can(s.perm)) { chrome(); $("#view").innerHTML = '<p class="note">' + t("noaccess") + "</p>"; return; }
      load();
      var before = JSON.stringify((user() || {}).permissions || []);
      API.refreshMe().then(function (u) {
        if (u && JSON.stringify(u.permissions || []) !== before) location.reload();
        else { refreshChip(); }
      });
    }
  };
})();
