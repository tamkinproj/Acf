(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    sess_title: "Where you are signed in", sess_note: "Sign out any device you do not recognise or have lost.", sess_this: "This device", sess_last: "Last used {d}", sess_out: "Sign out", sess_others: "Sign out all other devices", sess_done: "Signed out", sess_load: "Loading…",
    pw_title: "Change my password", pw_btn: "Change password", pw_cur: "Current password", pw_new: "New password", pw_again: "New password again", pw_hint: "At least 12 characters with upper and lower case and a number. No common words, and not your name or username. Your other devices will be signed out.",
    pw_same: "The two new passwords are not the same.", pw_done: "Password changed.", pw_conn: "Changing the password needs a connection.",
    set_title: "Settings", set_rem: "Monthly reminders", set_amt: "Show amounts", set_lang: "Language", set_acct: "Account", set_role: "Role", set_dept: "Department",
    set_sync: "Offline and sync", set_last: "Last sent", set_never: "Not yet", set_now: "Send now", set_none: "Nothing is waiting.", set_install: "Install app", set_installed: "To install: use your browser menu, Add to Home Screen.",
    set_note: "After the first sign-in this app opens and works without internet. Changes made offline are saved here and sent when you are back online.", pref_saved: "Saved",
    dept_aytam: "Aytam", dept_finance: "Finance", dept_projects: "Projects", dept_relief: "Relief", dept_teaching: "Teaching", dept_hr: "Human resources", dept_administration: "Administration", waiting_item: "Waiting"
  }, {
    sess_title: "الأجهزة المسجّلة الدخول", sess_note: "سجّل الخروج من أي جهاز لا تعرفه أو فقدته.", sess_this: "هذا الجهاز", sess_last: "آخر استخدام {d}", sess_out: "تسجيل الخروج", sess_others: "تسجيل الخروج من كل الأجهزة الأخرى", sess_done: "تم تسجيل الخروج", sess_load: "جارٍ التحميل…",
    pw_title: "تغيير كلمة المرور", pw_btn: "غيّر كلمة المرور", pw_cur: "كلمة المرور الحالية", pw_new: "كلمة المرور الجديدة", pw_again: "أعد كتابة كلمة المرور الجديدة", pw_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. سيتم تسجيل خروج أجهزتك الأخرى.",
    pw_same: "كلمتا المرور الجديدتان غير متطابقتين.", pw_done: "تم تغيير كلمة المرور.", pw_conn: "تغيير كلمة المرور يحتاج اتصالًا.",
    set_title: "الإعدادات", set_rem: "تذكيرات شهرية", set_amt: "إظهار المبالغ", set_lang: "اللغة", set_acct: "الحساب", set_role: "الدور", set_dept: "القسم",
    set_sync: "العمل بدون اتصال والمزامنة", set_last: "آخر إرسال", set_never: "لم يتم بعد", set_now: "أرسل الآن", set_none: "لا شيء بانتظار الإرسال.", set_install: "تثبيت التطبيق", set_installed: "للتثبيت: من قائمة المتصفح اختر إضافة إلى الشاشة الرئيسية.",
    set_note: "بعد أول تسجيل دخول يفتح التطبيق ويعمل بدون إنترنت. التغييرات التي تتم بدون اتصال تُحفظ هنا وتُرسل عند عودة الاتصال.", pref_saved: "تم الحفظ",
    dept_aytam: "أيتام", dept_finance: "المالية", dept_projects: "المشاريع", dept_relief: "الإغاثة", dept_teaching: "التدريس", dept_hr: "الموارد البشرية", dept_administration: "الإدارة", waiting_item: "بانتظار الإرسال"
  });

  A.strings({ set_wait: "Waiting to send", set_pw_row: "Password", set_dev: "Devices", set_sec: "Security" }, { set_wait: "بانتظار الإرسال", set_pw_row: "كلمة المرور", set_dev: "الأجهزة", set_sec: "الأمان" });

  /* iOS-style grouped list: rows with a coloured icon tile, the value on the end and a chevron when the row opens something */
  function row(o) {
    var tag = o.href ? "a" : o.act ? "button" : "div", attrs = o.href ? ' href="' + esc(o.href) + '"' : o.act ? ' type="button" data-act="' + o.act + '"' + (o.k ? ' data-k="' + o.k + '"' : "") : "";
    var end = o.sw ? '<button class="switch" type="button" role="switch" aria-checked="' + o.sw.on + '" aria-label="' + esc(o.label) + '" data-act="pref" data-k="' + o.sw.k + '"></button>' :
      (o.val != null ? '<span class="ios-val">' + o.val + "</span>" : "") + ((o.act || o.href) && !o.noChev ? ic("chev", "ios-chev") : "");
    return "<" + tag + ' class="ios-row' + (o.cls ? " " + o.cls : "") + (o.tile ? "" : " no-tile") + '"' + attrs + ">" + (o.tile ? '<span class="ios-ic t-' + o.tile[0] + '">' + ic(o.tile[1]) + "</span>" : "") + '<span class="ios-lbl">' + o.label + "</span>" + end + "</" + tag + ">";
  }
  function group(rows, note) { return '<div class="ios-group">' + rows.join("") + "</div>" + (note ? '<p class="ios-note">' + note + "</p>" : ""); }

  function render() {
    var u = A.user() || {}, pref = u.preferences || {}, items = A.api.outbox(), st = A.api.state(), ar = A.lang() === "ar";
    var last = st.lastSync ? A.dateTime(st.lastSync) : t("set_never");
    var install = window.ACF_PWA && window.ACF_PWA.canInstall ? [row({ label: t("set_install"), tile: ["blue", "download"], act: "install", noChev: true })] : [];
    return '<div class="head"><h1 class="title">' + t("set_title") + '</h1></div><div class="ios">' +
      '<button class="ios-prof" type="button" data-act="profile">' + A.avatar(u, "big") + '<span class="grow"><b>' + esc(u.name) + '</b><span class="sub" dir="ltr">@' + esc(u.username || "") + "</span><span class=\"sub\">" + esc(u.role_label || "") + "</span></span>" + ic("chev", "ios-chev") + "</button>" +
      '<input id="avatarIn" type="file" accept="image/jpeg,image/png,image/webp" hidden>' +
      (A.moreItems().length ? group(A.moreItems().map(function (m) { return row({ label: t(m[2]), tile: ["gray", m[4]], href: m[1] }); })) : "") +
      group([
        row({ label: t("set_rem"), tile: ["orange", "bell"], sw: { k: "reminders", on: pref.reminders !== false } }),
        row({ label: t("set_amt"), tile: ["green", "wallet"], sw: { k: "show_amounts", on: pref.show_amounts !== false } }),
        row({ label: t("set_lang"), tile: ["blue", "globe"], act: "lang", val: ar ? "العربية" : "English" })]) +
      group([
        row({ label: t("email"), tile: ["gray", "mail"], val: '<span dir="ltr">' + esc(u.email) + "</span>" }),
        row({ label: t("prof_username"), tile: ["lime", "users"], act: "chuser", val: '<span dir="ltr">@' + esc(u.username || "") + "</span>" }),
        row({ label: t("set_role"), tile: ["purple", "shield"], val: esc(u.role_label) })].concat(u.department ? [row({ label: t("set_dept"), tile: ["gray", "folder"], val: esc(t("dept_" + u.department)) })] : [])) +
      group([
        row({ label: t("set_pw_row"), tile: ["gray", "lock"], act: "changepw" }),
        row({ label: t("set_dev"), tile: ["green", "phone"], act: "devices" })]) +
      group([
        row({ label: t("set_last"), tile: ["blue", "cloud"], val: esc(last) }),
        row({ label: t("set_wait"), tile: ["orange", "send"], act: "outbox", val: items.length ? String(items.length) : "" }),
        row({ label: t("set_now"), tile: ["green", "cloud"], act: "syncnow", noChev: true })].concat(install), t("set_note")) +
      group([row({ label: t("signout"), act: "signout", noChev: true, cls: "ios-danger" })]) + "</div>";
  }
  A.act("profile", function () {
    var u = A.user() || {};
    A.sheet(t("prof_title"), '<div class="prof-head">' + A.avatar(u, "big") + '<div class="grow"><b>' + esc(u.name) + '</b><span class="note" dir="ltr" style="display:block">@' + esc(u.username || "") + '</span></div></div>' +
      '<div class="actions" style="margin-top:1rem"><button class="btn btn-main btn-sm" type="button" data-act="pickavatar">' + A.ic("edit") + "<span>" + t("prof_photo") + "</span></button>" + (u.avatar ? '<button class="btn btn-line btn-sm" type="button" data-act="rmavatar">' + t("prof_remove") + "</button>" : "") +
      '<button class="btn btn-line btn-sm" type="button" data-act="chuser">' + t("prof_change") + "</button></div>");
  });
  A.act("devices", function () {
    A.sheet(t("sess_title"), '<p class="note" style="margin-top:0">' + t("sess_note") + '</p><div class="list" id="sessBox"><p class="note">' + t("sess_load") + "</p></div>");
    loadSessions();
  });
  A.act("outbox", function () {
    var items = A.api.outbox();
    var list = items.length ? '<div class="list">' + items.map(function (o) {
      var failed = o.status === "failed";
      return '<div class="item"><span class="grow"><b>' + esc(o.label) + '</b><span class="sub">' + (failed ? esc(o.error || "") : t("waiting_item")) + '</span></span><span class="right">' +
        (failed ? '<button class="btn btn-line btn-sm" data-act="retry" data-id="' + o.id + '">' + t("retry") + "</button>" : "") +
        '<button class="btn btn-line btn-sm" data-act="discard" data-id="' + o.id + '">' + t("discard") + "</button></span></div>";
    }).join("") + "</div>" : '<p class="note">' + t("set_none") + "</p>";
    A.sheet(t("set_wait"), list);
  });

  function refreshAll() { return A.api.refreshMe().then(function () { A.closeSheet(); A.paint(); }); }
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
  function loadSessions() {
    var box = document.getElementById("sessBox"); if (!box) return;
    A.api.get("me/sessions", { cache: false }).then(function (r) {
      var l = A.lang() === "ar" ? "ar" : "en-GB", rows = r.data;
      box.innerHTML = rows.map(function (s) {
        return '<div class="item"><span class="grow"><b>' + esc(s.device) + (s.current ? ' <span class="badge">' + t("sess_this") + "</span>" : "") + '</b><span class="sub">' + esc(t("sess_last", { d: A.dateTime(s.last_used || s.signed_in) })) + "</span></span>" +
          (s.current ? "" : '<button class="btn btn-line btn-sm" type="button" data-act="endsess" data-id="' + s.id + '">' + t("sess_out") + "</button>") + "</div>";
      }).join("") + (rows.length > 1 ? '<div class="actions" style="margin-top:.8rem"><button class="btn btn-line btn-sm" type="button" data-act="endothers">' + t("sess_others") + "</button></div>" : "");
    }, function () { box.innerHTML = '<p class="note">' + t("need_conn") + "</p>"; });
  }
  A.act("endsess", function (el) { A.api.write("DELETE", "me/sessions/" + el.getAttribute("data-id"), null, { online: true }).then(function () { A.toast(t("sess_done")); loadSessions(); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); }); });
  A.act("endothers", function () { A.api.write("POST", "me/sessions/end-others", {}, { online: true }).then(function () { A.toast(t("sess_done")); loadSessions(); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); }); });
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
    after: function () { if (location.hash === "#outbox") { var el = document.querySelector("[data-act=outbox]"); if (el) el.click(); } }
  });
})();
