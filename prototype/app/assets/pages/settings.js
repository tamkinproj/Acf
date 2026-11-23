(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    sess_title: "Where you are signed in", sess_note: "Sign out any device you do not recognise or have lost.", sess_this: "This device", sess_last: "Last used {d}", sess_out: "Sign out", sess_others: "Sign out all other devices", sess_done: "Signed out", sess_load: "Loading…",
    pw_title: "Change my password", pw_btn: "Change password", pw_cur: "Current password", pw_new: "New password", pw_again: "New password again", pw_hint: "At least 12 characters with upper and lower case and a number. No common words, and not your name or username. Your other devices will be signed out.",
    pw_same: "The two new passwords are not the same.", pw_done: "Password changed.", pw_conn: "Changing the password needs a connection.",
    mc_row: "My card on the website", mc_title: "My card on the website", mc_note: "This is your card in the team section of the landing page. Your changes are saved as a draft; they appear on the website when the President publishes.", mc_live: "On the website as shown", mc_wait: "Changes waiting for the President to publish", mc_name_en: "Name (English)", mc_name_ar: "Name (Arabic)", mc_role_en: "Position (English)", mc_role_ar: "Position (Arabic)", mc_bio_en: "Details (English)", mc_bio_ar: "Details (Arabic)", mc_photo: "Photo", mc_change: "Change photo", mc_saved: "Saved. The President will publish it.", mc_card: "Card {n}",
    set_title: "Settings", set_rem: "Monthly reminders", set_amt: "Show amounts", set_lang: "Language", set_acct: "Account", set_role: "Role", set_dept: "Department",
    set_sync: "Offline and sync", set_last: "Last sent", set_never: "Not yet", set_now: "Send now", set_none: "Nothing is waiting.", set_install: "Install app", set_installed: "To install: use your browser menu, Add to Home Screen.",
    set_note: "After the first sign-in this app opens and works without internet. Changes made offline are saved here and sent when you are back online.", pref_saved: "Saved",
    dept_aytam: "Aytam", dept_finance: "Finance", dept_projects: "Projects", dept_relief: "Relief", dept_education: "Education", dept_hr: "Human resources", dept_administration: "Administration", waiting_item: "Waiting"
  }, {
    sess_title: "الأجهزة المسجّلة الدخول", sess_note: "سجّل الخروج من أي جهاز لا تعرفه أو فقدته.", sess_this: "هذا الجهاز", sess_last: "آخر استخدام {d}", sess_out: "تسجيل الخروج", sess_others: "تسجيل الخروج من كل الأجهزة الأخرى", sess_done: "تم تسجيل الخروج", sess_load: "جارٍ التحميل…",
    pw_title: "تغيير كلمة المرور", pw_btn: "غيّر كلمة المرور", pw_cur: "كلمة المرور الحالية", pw_new: "كلمة المرور الجديدة", pw_again: "أعد كتابة كلمة المرور الجديدة", pw_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. سيتم تسجيل خروج أجهزتك الأخرى.",
    pw_same: "كلمتا المرور الجديدتان غير متطابقتين.", pw_done: "تم تغيير كلمة المرور.", pw_conn: "تغيير كلمة المرور يحتاج اتصالًا.",
    mc_row: "بطاقتي في الموقع", mc_title: "بطاقتي في الموقع", mc_note: "هذه بطاقتك في قسم الفريق في الصفحة الرئيسية. تُحفظ تغييراتك كمسودة، وتظهر في الموقع عندما ينشرها الرئيس.", mc_live: "ظاهرة في الموقع كما هي", mc_wait: "تغييرات بانتظار نشر الرئيس", mc_name_en: "الاسم (بالإنجليزية)", mc_name_ar: "الاسم (بالعربية)", mc_role_en: "المنصب (بالإنجليزية)", mc_role_ar: "المنصب (بالعربية)", mc_bio_en: "نبذة (بالإنجليزية)", mc_bio_ar: "نبذة (بالعربية)", mc_photo: "الصورة", mc_change: "تغيير الصورة", mc_saved: "تم الحفظ. سينشرها الرئيس.", mc_card: "البطاقة {n}",
    set_title: "الإعدادات", set_rem: "تذكيرات شهرية", set_amt: "إظهار المبالغ", set_lang: "اللغة", set_acct: "الحساب", set_role: "الدور", set_dept: "القسم",
    set_sync: "العمل بدون اتصال والمزامنة", set_last: "آخر إرسال", set_never: "لم يتم بعد", set_now: "أرسل الآن", set_none: "لا شيء بانتظار الإرسال.", set_install: "تثبيت التطبيق", set_installed: "للتثبيت: من قائمة المتصفح اختر إضافة إلى الشاشة الرئيسية.",
    set_note: "بعد أول تسجيل دخول يفتح التطبيق ويعمل بدون إنترنت. التغييرات التي تتم بدون اتصال تُحفظ هنا وتُرسل عند عودة الاتصال.", pref_saved: "تم الحفظ",
    dept_aytam: "أيتام", dept_finance: "المالية", dept_projects: "المشاريع", dept_relief: "الإغاثة", dept_education: "التعليم", dept_hr: "الموارد البشرية", dept_administration: "الإدارة", waiting_item: "بانتظار الإرسال"
  });

  A.strings({ set_sound: "Sounds (messages and notifications)", set_wait: "Waiting to send", set_pw_row: "Password", set_dev: "Devices", set_sec: "Security" }, { set_sound: "الأصوات (الرسائل والإشعارات)", set_wait: "بانتظار الإرسال", set_pw_row: "كلمة المرور", set_dev: "الأجهزة", set_sec: "الأمان" });

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
      '<input id="avatarIn" type="file" accept="image/jpeg,image/png,image/webp" hidden>' + '<div id="myCardBox">' + (MC && MC.cards.length ? group(MC.cards.map(function (c, i) { return row({ label: t("mc_row") + (MC.cards.length > 1 ? " · " + (i + 1) : ""), tile: ["green", "globe"], act: "mycard", k: String(i), val: esc(t(c.published ? "mc_live" : "mc_wait")) }); })) : "") + "</div>" +
      (A.moreItems().length ? group(A.moreItems().map(function (m) { return row({ label: t(m[2]), tile: ["gray", m[4]], href: m[1] }); })) : "") +
      group([
        row({ label: t("set_rem"), tile: ["orange", "bell"], sw: { k: "reminders", on: pref.reminders !== false } }),
        row({ label: t("set_amt"), tile: ["green", "wallet"], sw: { k: "show_amounts", on: pref.show_amounts !== false } }),
        row({ label: t("set_sound"), tile: ["blue", "bell"], sw: { k: "sounds", on: pref.sounds !== false } }),
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
  A.strings({
    tr_title: "Trusted devices", tr_note: "A trusted phone or computer is never slowed down by the request limits, so it does not run into \"too many requests\". Trust only devices that only you use. It lasts 90 days, and ends when you change your password.",
    tr_this_on: "This device is trusted", tr_this_off: "This device is not trusted", tr_trust: "Trust this device", tr_remove: "Stop trusting", tr_pw: "Your password", tr_pw_hint: "Type your password to trust this device.",
    tr_done: "This device is trusted", tr_removed: "This device is no longer trusted", tr_others: "Other trusted devices", tr_until: "Until {d}", tr_cancel: "Cancel"
  }, {
    tr_title: "الأجهزة الموثوقة", tr_note: "الهاتف أو الحاسوب الموثوق لا تبطّئه حدود الطلبات، فلا تظهر له رسالة «طلبات كثيرة». وثّق فقط الأجهزة التي تستخدمها وحدك. تستمر 90 يومًا وتنتهي عند تغيير كلمة المرور.",
    tr_this_on: "هذا الجهاز موثوق", tr_this_off: "هذا الجهاز غير موثوق", tr_trust: "الوثوق بهذا الجهاز", tr_remove: "إيقاف الوثوق", tr_pw: "كلمة مرورك", tr_pw_hint: "اكتب كلمة مرورك للوثوق بهذا الجهاز.",
    tr_done: "تم الوثوق بهذا الجهاز", tr_removed: "لم يعد هذا الجهاز موثوقًا", tr_others: "أجهزة موثوقة أخرى", tr_until: "حتى {d}", tr_cancel: "إلغاء"
  });
  function loadTrust() {
    var box = document.getElementById("trustBox"); if (!box) return;
    A.api.get("me/devices", { cache: false }).then(function (r) {
      var d = r.data, mine = d.this_device, others = d.devices.filter(function (x) { return !x.current; });
      box.innerHTML = '<h4 class="optlbl">' + t("tr_title") + '</h4><p class="note" style="margin-top:0">' + t("tr_note") + "</p>" +
        '<div class="item"><span class="grow"><b>' + t(mine ? "tr_this_on" : "tr_this_off") + "</b></span>" +
        (mine ? '<button class="btn btn-line btn-sm" type="button" data-act="trustoff" data-id="' + (d.devices.filter(function (x) { return x.current; })[0] || {}).id + '">' + t("tr_remove") + "</button>" : '<button class="btn btn-main btn-sm" type="button" data-act="trustpw">' + t("tr_trust") + "</button>") + "</div>" +
        (others.length ? '<h4 class="optlbl">' + t("tr_others") + "</h4>" + others.map(function (x) {
          return '<div class="item"><span class="grow"><b>' + esc(x.name) + '</b><span class="sub">' + esc(t("sess_last", { d: A.dateTime(x.last_used) })) + " · " + esc(t("tr_until", { d: A.dateOnly(x.expires) })) + '</span></span><button class="btn btn-line btn-sm" type="button" data-act="trustoff" data-id="' + x.id + '">' + t("tr_remove") + "</button></div>";
        }).join("") : "");
    }, function () { box.innerHTML = ""; });
  }
  A.act("trustpw", function () {
    A.sheet(t("tr_trust"), '<form id="trForm" novalidate><p class="note" style="margin-top:0">' + t("tr_pw_hint") + "</p>" + A.field("tr_password", t("tr_pw"), "", "password", 'dir="ltr" autocomplete="current-password"') + A.formEnd("tr_trust") + "</form>");
  });
  A.form("trForm", function () {
    var pw = document.getElementById("tr_password").value, er = document.getElementById("formErr"), btn = document.querySelector("#trForm button[type=submit]"); er.textContent = "";
    if (!pw) { document.getElementById("tr_passwordErr").textContent = t("required"); return; }
    btn.disabled = true;
    A.api.write("POST", "me/devices", { password: pw }, { online: true }).then(function (r) {
      A.api.setDeviceToken((r.data || r).device_token); A.closeSheet(); A.toast(t("tr_done"));
    }, function (e) { btn.disabled = false; er.textContent = e.errors && e.errors.password ? e.errors.password[0] : (e.offline ? t("need_conn") : A.msg(e)); });
  });
  A.act("trustoff", function (el) {
    var id = el.getAttribute("data-id"); el.disabled = true;
    A.api.write("DELETE", "me/devices/" + id, null, { online: true }).then(function () { A.toast(t("tr_removed")); A.api.get("me/devices", { cache: false }).then(function (r) { if (!r.data.this_device) A.api.setDeviceToken(""); loadTrust(); }, loadTrust); }, function (e) { el.disabled = false; A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });
  A.act("devices", function () {
    A.sheet(t("sess_title"), '<p class="note" style="margin-top:0">' + t("sess_note") + '</p><div class="list" id="sessBox"><p class="note">' + t("sess_load") + '</p></div><div id="trustBox"></div>');
    loadSessions(); loadTrust();
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
    A.api.setPrefs(p); A.paint(); if (k === "sounds" && now) A.sound("notify");
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

  /* ---- my own card in the landing page team (when the President has linked one to me) ---- */
  var MC = null, mcPhoto;
  function mcLoad() { if (navigator.onLine === false) return; A.api.get("site/team/me", { cache: false }).then(function (r) { var had = MC && MC.cards.length; MC = r.data; if ((MC.cards.length || had) && document.getElementById("myCardBox")) A.paint(); }, function () {}); }
  function mcField(k, long) {
    var c = MC.cur, rtl = /_ar$/.test(k) ? ' dir="rtl"' : "", v = esc(c[k] || "");
    return '<div><label for="mc_' + k + '">' + t("mc_" + k) + "</label>" + (long ? '<textarea id="mc_' + k + '" rows="3" maxlength="400"' + rtl + ">" + v + "</textarea>" : '<input id="mc_' + k + '" maxlength="100"' + rtl + ' value="' + v + '">') + '<div class="err" id="mc_' + k + 'Err"></div></div>';
  }
  A.act("mycard", function (el) {
    MC.cur = MC.cards[+el.getAttribute("data-k")]; mcPhoto = MC.cur.photo; var im = mcPhoto && MC.images[mcPhoto];
    A.sheet(t("mc_title"), '<form id="mcForm" novalidate><p class="note" style="margin-top:0">' + t("mc_note") + "</p>" +
      '<div style="display:flex;gap:.8rem;align-items:center;margin-bottom:.8rem"><span id="mcImg" class="avatar big" style="' + (im ? "background-image:url('" + esc(im.url) + "');background-size:cover" : "") + '"></span><button class="btn btn-line btn-sm" type="button" data-act="mcphoto">' + t("mc_change") + '</button><input type="file" id="mcIn" accept="image/jpeg,image/png,image/webp" hidden></div>' +
      '<div style="display:grid;gap:.8rem">' + mcField("name_en") + mcField("name_ar") + mcField("role_en") + mcField("role_ar") + mcField("bio_en", true) + mcField("bio_ar", true) + "</div>" + A.formEnd("save") + "</form>");
  });
  A.act("mcphoto", function () {
    var f = document.getElementById("mcIn");
    f.onchange = function () { var file = f.files[0]; f.value = ""; if (!file) return; var fd = new FormData(); fd.append("image", file); A.toast("…");
      A.api.upload("site/team/me/photo", fd).then(function (d) { mcPhoto = d.id; var b = document.getElementById("mcImg"); if (b) { b.style.backgroundImage = "url('" + d.url + "')"; b.style.backgroundSize = "cover"; } A.toast("✓"); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); }); };
    f.click();
  });
  A.form("mcForm", function () {
    var body = { index: MC.cur.index, photo: mcPhoto };
    ["name_en", "name_ar", "role_en", "role_ar", "bio_en", "bio_ar"].forEach(function (k) { body[k] = document.getElementById("mc_" + k).value.trim(); document.getElementById("mc_" + k + "Err").textContent = ""; });
    document.getElementById("formErr").textContent = "";
    A.api.write("PUT", "site/team/me", body, { online: true }).then(function (r) { MC = r.data; A.closeSheet(); A.toast(t("mc_saved")); A.paint(); }, function (e) {
      if (!(e.errors && A.showErrors(e.errors, "mc_"))) document.getElementById("formErr").textContent = e.offline ? t("need_conn") : A.msg(e);
    });
  });

  A.start({
    page: "settings", nav: "settings", title: "n_set", shell: true, load: function () { return Promise.resolve({ data: {} }); }, render: render,
    onSync: function () { A.paint(); },
    after: function () { if (!MC) mcLoad(); if (location.hash === "#outbox") { var el = document.querySelector("[data-act=outbox]"); if (el) el.click(); } }
  });
})();
