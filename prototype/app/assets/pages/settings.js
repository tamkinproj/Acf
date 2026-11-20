(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    set_title: "Settings", set_rem: "Monthly reminders", set_amt: "Show amounts", set_lang: "Language", set_acct: "Account", set_role: "Role", set_dept: "Department",
    set_sync: "Offline and sync", set_last: "Last sent", set_never: "Not yet", set_now: "Send now", set_none: "Nothing is waiting.", set_install: "Install app", set_installed: "To install: use your browser menu, Add to Home Screen.",
    set_note: "After the first sign-in this app opens and works without internet. Changes made offline are saved here and sent when you are back online.", pref_saved: "Saved",
    dept_aytam: "Aytam", dept_finance: "Finance", dept_projects: "Projects", dept_relief: "Relief", dept_teaching: "Teaching", dept_hr: "Human resources", dept_administration: "Administration", waiting_item: "Waiting"
  }, {
    set_title: "الإعدادات", set_rem: "تذكيرات شهرية", set_amt: "إظهار المبالغ", set_lang: "اللغة", set_acct: "الحساب", set_role: "الدور", set_dept: "القسم",
    set_sync: "العمل بدون اتصال والمزامنة", set_last: "آخر إرسال", set_never: "لم يتم بعد", set_now: "أرسل الآن", set_none: "لا شيء بانتظار الإرسال.", set_install: "تثبيت التطبيق", set_installed: "للتثبيت: من قائمة المتصفح اختر إضافة إلى الشاشة الرئيسية.",
    set_note: "بعد أول تسجيل دخول يفتح التطبيق ويعمل بدون إنترنت. التغييرات التي تتم بدون اتصال تُحفظ هنا وتُرسل عند عودة الاتصال.", pref_saved: "تم الحفظ",
    dept_aytam: "أيتام", dept_finance: "المالية", dept_projects: "المشاريع", dept_relief: "الإغاثة", dept_teaching: "التدريس", dept_hr: "الموارد البشرية", dept_administration: "الإدارة", waiting_item: "بانتظار الإرسال"
  });

  function sw(k, label, on) {
    return '<div class="setrow"><b>' + label + '</b><button class="switch" type="button" role="switch" aria-checked="' + on + '" aria-label="' + esc(label) + '" data-act="pref" data-k="' + k + '"></button></div>';
  }
  function render() {
    var u = A.user() || {}, pref = u.preferences || {}, items = A.api.outbox(), st = A.api.state();
    var last = st.lastSync ? new Date(st.lastSync).toLocaleString(A.lang() === "ar" ? "ar" : "en-GB") : t("set_never");
    var list = items.length ? '<div class="list">' + items.map(function (o) {
      var failed = o.status === "failed";
      return '<div class="item"><span class="grow"><b>' + esc(o.label) + '</b><span class="sub">' + (failed ? esc(o.error || "") : t("waiting_item")) + '</span></span><span class="right">' +
        (failed ? '<button class="btn btn-line btn-sm" data-act="retry" data-id="' + o.id + '">' + t("retry") + '</button>' : "") +
        '<button class="btn btn-line btn-sm" data-act="discard" data-id="' + o.id + '">' + t("discard") + "</button></span></div>";
    }).join("") + "</div>" : '<p class="note">' + t("set_none") + "</p>";
    var install = window.ACF_PWA && window.ACF_PWA.canInstall ? '<div class="setrow"><b>' + t("set_install") + '</b><button class="btn btn-main btn-sm" data-act="install">' + ic("download") + t("set_install") + "</button></div>" : "";
    return '<div class="head"><h1 class="title">' + t("set_title") + '</h1></div><div class="grid g2" style="align-items:start">' +
      '<div class="card"><h3>' + t("set_acct") + "</h3>" + A.facts([[t("email"), esc(u.email)], [t("set_role"), esc(u.role_label)]].concat(u.department ? [[t("set_dept"), t("dept_" + u.department)]] : [])) +
      sw("reminders", t("set_rem"), pref.reminders !== false) + sw("show_amounts", t("set_amt"), pref.show_amounts !== false) +
      '<div class="setrow"><b>' + t("set_lang") + '</b><button class="btn btn-line btn-sm" data-act="lang">' + t("lang_btn") + "</button></div>" + install +
      '<div class="setrow"><button class="btn btn-line btn-sm" data-act="signout">' + ic("logout") + t("signout") + "</button></div></div>" +
      '<div class="card" id="outbox"><h3>' + t("set_sync") + "</h3>" + A.facts([[t("set_last"), esc(last)]]) + '<p class="note">' + t("set_note") + "</p>" + list +
      '<div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="syncnow">' + ic("cloud") + t("set_now") + "</button></div></div></div>";
  }

  A.act("pref", function (el) {
    var k = el.getAttribute("data-k"), u = A.user(), now = !((u.preferences || {})[k] !== false), p = {}; p[k] = now;
    A.api.setPrefs(p); A.paint();
    A.api.write("PATCH", "me/preferences", p, { label: t("set_title") }).then(function () {}, function (e) { A.toast(A.msg(e)); });
  });
  A.act("syncnow", function () { A.api.sync().then(function () { A.paint(); }); });
  A.act("retry", function (el) { A.api.retry(el.getAttribute("data-id")); });
  A.act("discard", function (el) { A.api.discard(el.getAttribute("data-id")); A.paint(); });
  A.act("install", function () { window.ACF_PWA.install().then(function () { A.paint(); }); });

  A.start({
    page: "settings", nav: "settings", title: "n_set", shell: true, load: function () { return Promise.resolve({ data: {} }); }, render: render,
    onSync: function () { A.paint(); },
    after: function () { if (location.hash === "#outbox") { var el = document.getElementById("outbox"); if (el) el.scrollIntoView(); } }
  });
})();
