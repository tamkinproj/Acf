/* Shared core for every page: text (EN/AR), fictional data, top and bottom bars, login, and common actions.
   Each page loads this file, then its own small script from assets/pages/. */
(function () {
  "use strict";
  var CUR = "AED", root = document.documentElement;
  var KEY = "acf_data", UIK = "acf_ui", AUTH = "acf_auth";

  /* ---------------- text ---------------- */
  var S = {
    en: {
      brand: "Al Insania Charity Foundation", staff: "Staff portal", signin: "Sign in", email: "Email", password: "Password",
      login_hint: "Prototype: any email and password work. Data is fictional.", login_err: "Enter a valid email and a password.",
      n_home: "Home", n_people: "People", n_pay: "Payments", n_proj: "Projects", n_rep: "Reports", n_set: "Settings",
      hello: "Welcome back", hello_sub: "Here is where things stand today.",
      s_children: "Children", s_verified: "Verified bank accounts", s_paid: "Paid this month", s_pending: "Pending this month",
      attn: "Needs attention", attn_none: "Nothing needs attention.", unverified: "Bank account not verified", pending_pay: "Payment pending",
      quick: "Quick actions", add_child: "Add child", export_csv: "Export CSV", month_rep: "Monthly report",
      people: "People", search: "Search name, reference or guardian", all: "All", bank: "Bank", cash: "Cash", none: "No matches.",
      verified: "Verified", notver: "Not verified", active: "Active", back: "Back",
      sec_personal: "Personal information", sec_mother: "Mother and family", sec_resp: "Direct responsible person", sec_docs: "Documents",
      sec_bank: "Bank details", sec_channel: "Channel and source", sec_hist: "Channel history",
      f_gender: "Gender", f_dob: "Date of birth", f_city: "City", f_health: "Health status", f_grade: "Grade",
      f_mother: "Mother name", f_mstatus: "Mother marital status", f_fdeath: "Father death date",
      f_bro: "Number of brothers", f_sis: "Number of sisters", f_guardian: "Guardian", f_rel: "Relationship", f_phone: "Phone",
      f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits", f_channel: "Channel", f_source: "Source", f_since: "Since",
      f_amount: "Monthly amount", f_paytype: "Payment type",
      male: "Male", female: "Female", widowed: "Widowed", good: "Good", fair: "Needs follow-up",
      doc_on: "On file", doc_missing: "Missing", doc_upload: "Document upload opens in the next phase", verify: "Verified",
      d_birth: "Birth certificate", d_death: "Father's death certificate", d_school: "School record", d_id: "Guardian ID",
      pay_title: "Payments for the month", paid: "Paid", pending: "Pending", mark_cash: "Mark cash received",
      total_paid: "Total paid", total_due: "Total due",
      proj_title: "Projects", budget: "Budget", spent: "Spent", progress: "Progress", st_active: "In progress", st_done: "Completed",
      rep_title: "Reports", rep_scope: "Scope", rep_month: "Month", rep_pdf: "Download PDF", pdf_next: "PDF export arrives in the next phase", rep_sum: "Summary",
      set_title: "Settings", set_rem: "Monthly reminders", set_amt: "Show amounts", set_lang: "Language", set_role: "Role",
      role_admin: "Foundation Admin", set_data: "Sample data is fictional. Changes are kept only for this browser session.", signout: "Sign out",
      add_title: "Add child", cancel: "Cancel", save: "Save", saved: "Child added", f_name_en: "Name (English)", f_name_ar: "Name (Arabic)",
      required: "This field is required.", lang_btn: "العربية", cash_marked: "Marked as received", ver_on: "Bank account verified",
      ver_off: "Verification removed", exported: "CSV downloaded", per_month: "per month", cash_only: "Cash payment", bank_pay: "Bank transfer",
      direct: "Direct donor", partner: "Partner sponsorship", child_title: "Child profile", ph_loading: "Loading", back_site: "Back to website"
    },
    ar: {
      brand: "مؤسسة الإنسانية الخيرية", staff: "بوابة الموظفين", signin: "تسجيل الدخول", email: "البريد الإلكتروني", password: "كلمة المرور",
      login_hint: "نموذج أوّلي: أي بريد وكلمة مرور تعمل. البيانات وهمية.", login_err: "أدخل بريدًا صحيحًا وكلمة مرور.",
      n_home: "الرئيسية", n_people: "الأشخاص", n_pay: "المدفوعات", n_proj: "المشاريع", n_rep: "التقارير", n_set: "الإعدادات",
      hello: "أهلًا بعودتك", hello_sub: "هذا ما يجري اليوم.",
      s_children: "الأطفال", s_verified: "حسابات بنكية موثّقة", s_paid: "مدفوع هذا الشهر", s_pending: "معلّق هذا الشهر",
      attn: "يحتاج انتباهًا", attn_none: "لا شيء يحتاج انتباهًا.", unverified: "الحساب البنكي غير موثّق", pending_pay: "دفعة معلّقة",
      quick: "إجراءات سريعة", add_child: "إضافة طفل", export_csv: "تصدير CSV", month_rep: "التقرير الشهري",
      people: "الأشخاص", search: "ابحث بالاسم أو الرقم المرجعي أو الوصي", all: "الكل", bank: "بنك", cash: "نقدًا", none: "لا نتائج.",
      verified: "موثّق", notver: "غير موثّق", active: "نشط", back: "رجوع",
      sec_personal: "المعلومات الشخصية", sec_mother: "الأم والأسرة", sec_resp: "المسؤول المباشر", sec_docs: "المستندات",
      sec_bank: "البيانات البنكية", sec_channel: "القناة والمصدر", sec_hist: "سجل القناة",
      f_gender: "الجنس", f_dob: "تاريخ الميلاد", f_city: "المدينة", f_health: "الحالة الصحية", f_grade: "الصف",
      f_mother: "اسم الأم", f_mstatus: "الحالة الاجتماعية للأم", f_fdeath: "تاريخ وفاة الأب",
      f_bro: "عدد الإخوة", f_sis: "عدد الأخوات", f_guardian: "الوصي", f_rel: "صلة القرابة", f_phone: "الهاتف",
      f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام", f_channel: "القناة", f_source: "المصدر", f_since: "منذ",
      f_amount: "المبلغ الشهري", f_paytype: "طريقة الدفع",
      male: "ذكر", female: "أنثى", widowed: "أرملة", good: "جيدة", fair: "تحتاج متابعة",
      doc_on: "موجود", doc_missing: "ناقص", doc_upload: "رفع المستندات يفتح في المرحلة التالية", verify: "موثّق",
      d_birth: "شهادة الميلاد", d_death: "شهادة وفاة الأب", d_school: "السجل المدرسي", d_id: "هوية الوصي",
      pay_title: "مدفوعات الشهر", paid: "مدفوع", pending: "معلّق", mark_cash: "تأكيد استلام النقد",
      total_paid: "إجمالي المدفوع", total_due: "إجمالي المستحق",
      proj_title: "المشاريع", budget: "الميزانية", spent: "المصروف", progress: "التقدّم", st_active: "قيد التنفيذ", st_done: "مكتمل",
      rep_title: "التقارير", rep_scope: "النطاق", rep_month: "الشهر", rep_pdf: "تنزيل PDF", pdf_next: "تصدير PDF يصل في المرحلة التالية", rep_sum: "الملخص",
      set_title: "الإعدادات", set_rem: "تذكيرات شهرية", set_amt: "إظهار المبالغ", set_lang: "اللغة", set_role: "الدور",
      role_admin: "مدير المؤسسة", set_data: "البيانات التجريبية وهمية. تُحفظ التغييرات لهذه الجلسة فقط.", signout: "تسجيل الخروج",
      add_title: "إضافة طفل", cancel: "إلغاء", save: "حفظ", saved: "تمت إضافة الطفل", f_name_en: "الاسم (بالإنجليزية)", f_name_ar: "الاسم (بالعربية)",
      required: "هذا الحقل مطلوب.", lang_btn: "English", cash_marked: "تم تسجيل الاستلام", ver_on: "تم توثيق الحساب البنكي",
      ver_off: "أُلغي التوثيق", exported: "تم تنزيل CSV", per_month: "شهريًا", cash_only: "دفع نقدي", bank_pay: "حوالة بنكية",
      direct: "متبرع مباشر", partner: "كفالة عبر شريك", child_title: "ملف الطفل", ph_loading: "جارٍ التحميل", back_site: "العودة إلى الموقع"
    }
  };
  var lang = "en";
  try { if (localStorage.getItem("acf_lang") === "ar") lang = "ar"; } catch (e) {}
  function t(k) { return (S[lang] && S[lang][k]) || S.en[k] || k; }

  /* ---------------- fictional sample data ---------------- */
  var MONTHS = [
    { k: "2026-10", en: "October 2026", ar: "أكتوبر 2026" },
    { k: "2026-11", en: "November 2026", ar: "نوفمبر 2026" },
    { k: "2026-12", en: "December 2026", ar: "ديسمبر 2026" }
  ];
  var CITIES = ["Pagadian City", "Zamboanga City", "Molave", "Dimataling"];
  var GRADES = ["Grade 3", "Grade 5", "Grade 2", "Grade 6", "Grade 4", "Grade 1"];
  var BANKS = ["Sample Bank A", "Sample Bank B", "Sample Bank C"];
  var RAW = [
    ["Amina Hassan", "أمينة حسن", "f", 2014, "Hassan Ibrahim"], ["Yusuf Ali", "يوسف علي", "m", 2012, "Ali Mahmoud"],
    ["Fatima Noor", "فاطمة نور", "f", 2015, "Noor Salim"], ["Ibrahim Saleh", "إبراهيم صالح", "m", 2011, "Saleh Khalid"],
    ["Maryam Omar", "مريم عمر", "f", 2013, "Omar Faisal"], ["Zaid Karim", "زيد كريم", "m", 2016, "Karim Adel"],
    ["Aisha Rahman", "عائشة رحمن", "f", 2012, "Rahman Tariq"], ["Hamza Yousef", "حمزة يوسف", "m", 2014, "Yousef Nabil"],
    ["Layla Sami", "ليلى سامي", "f", 2015, "Sami Walid"], ["Omar Nasser", "عمر ناصر", "m", 2010, "Nasser Hamid"],
    ["Sara Idris", "سارة إدريس", "f", 2013, "Idris Jamal"], ["Khalid Mansour", "خالد منصور", "m", 2011, "Mansour Rashid"]
  ];
  var PROJECTS = [
    { en: "Clean water well", ar: "بئر مياه نظيفة", loc: "Zone 5, Pagadian City", budget: 4800, spent: 3100, done: false },
    { en: "School supplies, term 2", ar: "مستلزمات مدرسية للفصل الثاني", loc: "Madrasa, Molave", budget: 2200, spent: 2200, done: true },
    { en: "Winter clothing drive", ar: "حملة ملابس الشتاء", loc: "Zamboanga City", budget: 1500, spent: 600, done: false },
    { en: "Madrasa classroom repair", ar: "ترميم فصول المدرسة", loc: "Dimataling", budget: 6400, spent: 1900, done: false }
  ];
  function seed() {
    var children = RAW.map(function (r, i) {
      var id = i + 1, bankPay = id % 3 !== 0;
      return {
        id: id, ref: "ACF-" + ("000" + id).slice(-4), en: r[0], ar: r[1], gender: r[2], dob: r[3] + "-0" + ((i % 9) + 1) + "-1" + (i % 9),
        city: CITIES[i % 4], health: i % 5 === 3 ? "fair" : "good", grade: GRADES[i % 6],
        guardian: r[4], rel: i % 2 ? "Uncle" : "Mother", phone: "+63 900 000 00" + ("0" + id).slice(-2),
        mother: "Mother of " + r[0].split(" ")[0], bro: i % 3, sis: (i + 1) % 3, fdeath: (2018 + (i % 5)) + "-0" + ((i % 8) + 1) + "-15",
        pay: bankPay ? "bank" : "cash", amount: i % 2 ? 150 : 200,
        bank: bankPay ? { holder: r[4], name: BANKS[i % 3], last4: "" + (1000 + id * 137), verified: id % 4 !== 1 } : null,
        channel: i % 2 ? "direct" : "partner", since: "2025-0" + ((i % 9) + 1) + "-01",
        docs: { d_birth: true, d_death: i % 4 !== 2, d_school: i % 3 !== 1, d_id: true },
        hist: [["2025-0" + ((i % 9) + 1) + "-01", i % 2 ? "direct" : "partner"]]
      };
    });
    var pay = {};
    MONTHS.forEach(function (m, mi) {
      pay[m.k] = {};
      children.forEach(function (c) { pay[m.k][c.id] = mi === 0 ? "paid" : mi === 1 ? (c.id % 4 === 0 || c.id === 6 ? "pending" : "paid") : "pending"; });
    });
    return { children: children, pay: pay };
  }

  /* ---------------- saved state (this browser session only) ---------------- */
  function read(k) { try { return JSON.parse(sessionStorage.getItem(k)); } catch (e) { return null; } }
  var data = read(KEY); if (!data || !data.children) data = seed();
  var u = read(UIK) || {};
  var ui = { month: u.month || MONTHS[1].k, scope: u.scope || "all", tab: u.tab || "all", reminders: u.reminders !== false, amounts: u.amounts !== false };
  function save() { try { sessionStorage.setItem(KEY, JSON.stringify(data)); sessionStorage.setItem(UIK, JSON.stringify(ui)); } catch (e) {} }
  function authed() { try { return sessionStorage.getItem(AUTH) === "1"; } catch (e) { return false; } }

  /* ---------------- helpers ---------------- */
  function $(q, r) { return (r || document).querySelector(q); }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function name(c) { return lang === "ar" ? c.ar : c.en; }
  function ic(n, cls) { return '<svg class="icon ' + (cls || "") + '"><use href="#i-' + n + '"/></svg>'; }
  function money(n) { return ui.amounts ? CUR + " " + n.toLocaleString("en-US") : "••••"; }
  function monthLabel(k) { var m = MONTHS.filter(function (x) { return x.k === k; })[0]; return lang === "ar" ? m.ar : m.en; }
  function chName(c) { return c === "direct" ? t("direct") : t("partner"); }
  function facts(rows) { return '<dl class="facts">' + rows.map(function (r) { return "<div><dt>" + r[0] + "</dt><dd>" + r[1] + "</dd></div>"; }).join("") + "</dl>"; }
  function monthPills() {
    return '<div class="filters" role="group">' + MONTHS.map(function (m) {
      return '<button class="fbtn" type="button" data-act="month" data-v="' + m.k + '" aria-pressed="' + (ui.month === m.k) + '">' + (lang === "ar" ? m.ar : m.en) + "</button>";
    }).join("") + "</div>";
  }
  function paidCount(m) { return data.children.filter(function (c) { return data.pay[m][c.id] === "paid"; }).length; }
  function sum(m, status) { return data.children.reduce(function (a, c) { return a + (data.pay[m][c.id] === status ? c.amount : 0); }, 0); }
  function scoped() { return data.children.filter(function (c) { return ui.scope === "all" || c.pay === ui.scope; }); }
  function child(id) { return data.children.filter(function (x) { return x.id === id; })[0]; }
  var toastTimer;
  function toast(msg) {
    var el = $("#toast"); if (!el) return; el.textContent = msg; el.classList.add("show");
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.classList.remove("show"); }, 2200);
  }

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
    '<symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></symbol>' +
    '<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>' +
    '<symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></symbol>' +
    "</defs></svg>";

  /* ---------------- pages and bars ---------------- */
  var MENU = [["dashboard", "dashboard.html", "n_home"], ["people", "people.html", "n_people"], ["payments", "payments.html", "n_pay"],
              ["projects", "projects.html", "n_proj"], ["reports", "reports.html", "n_rep"], ["settings", "settings.html", "n_set"]];
  var TABS = [["dashboard", "dashboard.html", "home", "n_home"], ["people", "people.html", "users", "n_people"], ["payments", "payments.html", "wallet", "n_pay"],
              ["projects", "projects.html", "folder", "n_proj"], ["settings", "settings.html", "sliders", "n_set"]];
  var spec = null;

  function shell() {
    var cur = spec.nav;
    $("#nav").innerHTML = '<div class="row"><a class="brand" href="dashboard.html" aria-label="' + esc(t("n_home")) + '"><span class="logo" role="img" aria-hidden="true"></span><span><span>' + t("brand") + "</span><small>" + t("staff") + "</small></span></a>" +
      '<nav aria-label="Main"><ul class="menu">' + MENU.map(function (m) { return '<li><a href="' + m[1] + '"' + (cur === m[0] ? ' class="active" aria-current="page"' : "") + ">" + t(m[2]) + "</a></li>"; }).join("") + "</ul></nav>" +
      '<div class="tools"><button class="btn btn-line btn-sm round" type="button" data-act="lang" aria-label="Language">' + ic("globe") + '</button><button class="btn btn-main btn-sm round" type="button" data-act="signout" aria-label="' + esc(t("signout")) + '">' + ic("logout") + "</button></div></div>";
    $("#tabbar").innerHTML = TABS.map(function (m) { return '<a href="' + m[1] + '"' + (cur === m[0] ? ' class="active" aria-current="page"' : "") + ">" + ic(m[2]) + "<span>" + t(m[3]) + "</span></a>"; }).join("");
  }
  function draw() {
    root.lang = lang; root.dir = lang === "ar" ? "rtl" : "ltr";
    document.title = t(spec.title) + " | " + t("brand");
    Array.prototype.forEach.call(document.querySelectorAll("[data-t]"), function (n) { n.textContent = t(n.getAttribute("data-t")); });
    if (spec.shell) shell();
    if (spec.render) $("#view").innerHTML = spec.render();
    if (spec.after) spec.after();
  }

  /* ---------------- actions ---------------- */
  function csv(rows) { return rows.map(function (r) { return r.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(","); }).join("\r\n"); }
  function download(fileName, text) {
    var a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob(["﻿" + text], { type: "text/csv;charset=utf-8" }));
    a.download = fileName; document.body.appendChild(a); a.click(); a.remove(); toast(t("exported"));
  }
  function openAdd() {
    $("#sheetRoot").innerHTML = '<div class="backdrop" data-act="close"><div class="sheet" role="dialog" aria-modal="true" aria-label="' + esc(t("add_title")) + '"><h3>' + t("add_title") + "</h3>" +
      '<form id="addForm" novalidate><div><label for="aEn">' + t("f_name_en") + '</label><input id="aEn" dir="ltr"><div class="err" id="aErr"></div></div>' +
      '<div><label for="aAr">' + t("f_name_ar") + '</label><input id="aAr" dir="rtl"></div>' +
      '<div><label for="aG">' + t("f_guardian") + '</label><input id="aG"></div>' +
      '<div><label for="aP">' + t("f_paytype") + '</label><select id="aP"><option value="bank">' + t("bank_pay") + '</option><option value="cash">' + t("cash_only") + "</option></select></div>" +
      '<div class="actions"><button class="btn btn-main" type="submit">' + t("save") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("cancel") + "</button></div></form></div></div>";
    $("#aEn").focus();
  }
  var ACTIONS = {
    lang: function () { lang = lang === "en" ? "ar" : "en"; try { localStorage.setItem("acf_lang", lang); } catch (x) {} draw(); },
    signout: function () { try { sessionStorage.removeItem(AUTH); } catch (x) {} location.href = "login.html"; },
    tab: function (el) { ui.tab = el.getAttribute("data-v"); save(); draw(); },
    month: function (el) { ui.month = el.getAttribute("data-v"); save(); draw(); },
    scope: function (el) { ui.scope = el.getAttribute("data-v"); save(); draw(); },
    toggle: function (el) { var k = el.getAttribute("data-k"); ui[k] = !ui[k]; save(); draw(); },
    verify: function (el) { var c = child(parseInt(el.getAttribute("data-id"), 10)); c.bank.verified = !c.bank.verified; save(); draw(); toast(t(c.bank.verified ? "ver_on" : "ver_off")); },
    cash: function (el) { data.pay[ui.month][parseInt(el.getAttribute("data-id"), 10)] = "paid"; save(); draw(); toast(t("cash_marked")); },
    add: openAdd,
    docnote: function () { toast(t("doc_upload")); },
    pdf: function () { toast(t("pdf_next")); },
    "csv-people": function () { download("children.csv", csv([["ref", "name_en", "name_ar", "guardian", "payment", "monthly_amount_" + CUR]].concat(data.children.map(function (c) { return [c.ref, c.en, c.ar, c.guardian, c.pay, c.amount]; })))); },
    "csv-pay": function () { download("payments-" + ui.month + ".csv", csv([["ref", "name_en", "payment", "status", "amount_" + CUR]].concat(scoped().map(function (c) { return [c.ref, c.en, c.pay, data.pay[ui.month][c.id], c.amount]; })))); }
  };
  document.addEventListener("click", function (e) {
    var el = e.target.closest("[data-act]"); if (!el) return;
    var a = el.getAttribute("data-act");
    if (a === "close") { if (e.target === el || el.tagName === "BUTTON") $("#sheetRoot").innerHTML = ""; return; }
    if (ACTIONS[a]) ACTIONS[a](el);
  });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && $("#sheetRoot")) $("#sheetRoot").innerHTML = ""; });
  document.addEventListener("submit", function (e) {
    var f = e.target;
    if (f.id === "loginForm") {
      e.preventDefault();
      var em = $("#lEmail").value.trim(), pw = $("#lPass").value;
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em) || !pw) { $("#loginErr").textContent = t("login_err"); return; }
      $("#loginErr").textContent = "";
      try { sessionStorage.setItem(AUTH, "1"); } catch (x) {}
      location.replace("dashboard.html");
    } else if (f.id === "addForm") {
      e.preventDefault();
      var en = $("#aEn").value.trim();
      if (!en) { $("#aErr").textContent = t("required"); return; }
      var n = data.children.reduce(function (a, c) { return Math.max(a, c.id); }, 0) + 1, bankPay = $("#aP").value === "bank", g = $("#aG").value.trim();
      data.children.push({ id: n, ref: "ACF-" + ("000" + n).slice(-4), en: en, ar: $("#aAr").value.trim() || en, gender: "m", dob: "2015-01-01", city: CITIES[0], health: "good", grade: "Grade 1",
        guardian: g || "-", rel: "-", phone: "-", mother: "-", bro: 0, sis: 0, fdeath: "-", pay: bankPay ? "bank" : "cash", amount: 150,
        bank: bankPay ? { holder: g || en, name: BANKS[0], last4: "0000", verified: false } : null,
        channel: "direct", since: "2026-11-20", docs: { d_birth: false, d_death: false, d_school: false, d_id: false }, hist: [["2026-11-20", "direct"]] });
      MONTHS.forEach(function (m) { data.pay[m.k][n] = "pending"; });
      save(); $("#sheetRoot").innerHTML = ""; draw(); toast(t("saved"));
    }
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

  /* ---------------- start a page ---------------- */
  window.ACF = {
    t: t, esc: esc, ic: ic, name: name, money: money, monthLabel: monthLabel, chName: chName, facts: facts, monthPills: monthPills,
    paidCount: paidCount, sum: sum, scoped: scoped, child: child, toast: toast, ui: ui, MONTHS: MONTHS, PROJECTS: PROJECTS,
    children: data.children, pay: data.pay, lang: function () { return lang; }, draw: draw, save: save, authed: authed,
    start: function (s) {
      spec = s;
      var holder = document.createElement("div"); holder.innerHTML = SPRITE; document.body.insertBefore(holder.firstChild, document.body.firstChild);
      if (s.page === "login") { if (authed()) { location.replace("dashboard.html"); return; } }
      else if (!authed()) { location.replace("login.html"); return; }
      draw();
    }
  };
})();
