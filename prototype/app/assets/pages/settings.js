(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    pw_title: "Change my password", pw_btn: "Change password", pw_cur: "Current password", pw_new: "New password", pw_again: "New password again", pw_hint: "At least 12 characters with upper and lower case and a number. Your other devices will be signed out.",
    pw_same: "The two new passwords are not the same.", pw_done: "Password changed.", pw_conn: "Changing the password needs a connection.",
    set_title: "Settings", set_rem: "Monthly reminders", set_amt: "Show amounts", set_lang: "Language", set_acct: "Account", set_role: "Role", set_dept: "Department",
    set_sync: "Offline and sync", set_last: "Last sent", set_never: "Not yet", set_now: "Send now", set_none: "Nothing is waiting.", set_install: "Install app", set_installed: "To install: use your browser menu, Add to Home Screen.",
    set_note: "After the first sign-in this app opens and works without internet. Changes made offline are saved here and sent when you are back online.", pref_saved: "Saved",
    dept_aytam: "Aytam", dept_finance: "Finance", dept_projects: "Projects", dept_relief: "Relief", dept_teaching: "Teaching", dept_hr: "Human resources", dept_administration: "Administration", waiting_item: "Waiting"
  }, {
    pw_title: "تغيير كلمة المرور", pw_btn: "غيّر كلمة المرور", pw_cur: "كلمة المرور الحالية", pw_new: "كلمة المرور الجديدة", pw_again: "أعد كتابة كلمة المرور الجديدة", pw_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. سيتم تسجيل خروج أجهزتك الأخرى.",
    pw_same: "كلمتا المرور الجديدتان غير متطابقتين.", pw_done: "تم تغيير كلمة المرور.", pw_conn: "تغيير كلمة المرور يحتاج اتصالًا.",
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
      '<div class="card prof"><h3>' + t("prof_title") + '</h3><div class="prof-head">' + A.avatar(u, "big") + '<div class="grow"><b>' + esc(u.name) + '</b><span class="note" dir="ltr" style="display:block">@' + esc(u.username || "") + '</span></div></div>' +
        '<div class="actions"><button class="btn btn-main btn-sm" type="button" data-act="pickavatar">' + A.ic("edit") + "<span>" + t("prof_photo") + "</span></button>" + (u.avatar ? '<button class="btn btn-line btn-sm" type="button" data-act="rmavatar">' + t("prof_remove") + "</button>" : "") +
        '<button class="btn btn-line btn-sm" type="button" data-act="chuser">' + t("prof_change") + '</button></div><input id="avatarIn" type="file" accept="image/jpeg,image/png,image/webp" hidden></div>' +
      '<div class="card"><h3>' + t("set_acct") + "</h3>" + A.facts([[t("email"), esc(u.email)], [t("prof_username"), '<span dir="ltr">@' + esc(u.username || "") + "</span>"], [t("set_role"), esc(u.role_label)]].concat(u.department ? [[t("set_dept"), t("dept_" + u.department)]] : [])) +
      sw("reminders", t("set_rem"), pref.reminders !== false) + sw("show_amounts", t("set_amt"), pref.show_amounts !== false) +
      '<div class="setrow"><b>' + t("set_lang") + '</b><button class="btn btn-line btn-sm" data-act="lang">' + t("lang_btn") + "</button></div>" + install +
      '<div class="setrow"><button class="btn btn-line btn-sm" data-act="changepw">' + t("pw_btn") + '</button></div><div class="setrow"><button class="btn btn-line btn-sm" data-act="signout">' + ic("logout") + t("signout") + "</button></div></div>" +
      '<div class="card" id="outbox"><h3>' + t("set_sync") + "</h3>" + A.facts([[t("set_last"), esc(last)]]) + '<p class="note">' + t("set_note") + "</p>" + list +
      '<div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="syncnow">' + ic("cloud") + t("set_now") + "</button></div></div></div>";
  }

  function refreshAll() { return A.api.refreshMe().then(function () { A.paint(); }); }
  A.act("pickavatar", function () { document.getElementById("avatarIn").click(); });
  document.addEventListener("change", function (e) {
    var inp = e.target; if (!inp || inp.id !== "avatarIn") return;
    var f = inp.files[0]; inp.value = ""; if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { A.toast(t("prof_bad")); return; }
    if (navigator.onLine === false) { A.toast(t("need_conn")); return; }
    A.shrinkImage(f, 512).then(function (small) {
      var fd = new FormData(); fd.append("avatar", small, "profile.jpg");
      return A.api.upload("me/avatar", fd);
    }).then(function () { A.toast(t("prof_saved")); return refreshAll(); }, function (er) { A.toast(er.offline ? t("need_conn") : A.msg(er)); });
  });
  A.act("rmavatar", function () {
    A.api.write("DELETE", "me/avatar", null, { online: true }).then(function () { A.toast(t("prof_removed")); return refreshAll(); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });
  A.act("chuser", function () {
    A.sheet(t("un_title"), '<form id="unForm" novalidate>' + A.field("un_username", t("prof_username"), '<p class="note" style="margin:.4rem 0 0">' + t("un_hint") + "</p>", "text", 'dir="ltr" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="off" value="' + esc((A.user() || {}).username || "") + '"') + A.formEnd() + "</form>");
  });
  A.form("unForm", function () {
    var v = document.getElementById("un_username").value.trim(), btn = document.querySelector("#unForm button[type=submit]"); document.getElementById("un_usernameErr").textContent = "";
    btn.disabled = true;
    A.api.write("PATCH", "me/profile", { username: v }, { online: true }).then(function () { A.closeSheet(); A.toast(t("un_saved")); return refreshAll(); }, function (e) {
      btn.disabled = false; document.getElementById("un_usernameErr").textContent = e.errors && e.errors.username ? e.errors.username[0] : (e.offline ? t("need_conn") : A.msg(e));
    });
  });
  A.act("pref", function (el) {
    var k = el.getAttribute("data-k"), u = A.user(), now = !((u.preferences || {})[k] !== false), p = {}; p[k] = now;
    A.api.setPrefs(p); A.paint();
    A.api.write("PATCH", "me/preferences", p, { label: t("set_title") }).then(function () {}, function (e) { A.toast(A.msg(e)); });
  });
  A.act("changepw", function () {
    A.sheet(t("pw_title"), '<form id="pwForm" novalidate>' + A.field("pw_current_password", t("pw_cur"), "", "password", 'dir="ltr" autocomplete="current-password"') +
      A.field("pw_password", t("pw_new"), '<p class="note" style="margin:.4rem 0 0">' + t("pw_hint") + "</p>", "password", 'dir="ltr" autocomplete="new-password"') +
      A.field("pw_again", t("pw_again"), "", "password", 'dir="ltr" autocomplete="new-password"') + A.formEnd() + "</form>");
  });
  function pv(id) { return document.getElementById(id).value; }
  A.form("pwForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    if (!pv("pw_current_password")) { document.getElementById("pw_current_passwordErr").textContent = t("required"); return; }
    if (pv("pw_password") !== pv("pw_again")) { document.getElementById("pw_againErr").textContent = t("pw_same"); return; }
    A.api.write("POST", "me/password", { current_password: pv("pw_current_password"), password: pv("pw_password") }, { online: true }).then(function () { A.closeSheet(); A.toast(t("pw_done")); }, function (e) {
      if (e.errors && A.showErrors(e.errors, "pw_")) return;
      document.getElementById("formErr").textContent = e.offline ? t("pw_conn") : A.msg(e);
    });
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
