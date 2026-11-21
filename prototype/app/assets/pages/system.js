(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, keys = [], pres = null;

  A.strings({
sec_title: "Security check", sec_ok: "OK", sec_fix: "Needs attention", sec_note: "Privileged accounts are signed out after {i} minutes idle and {h} hours at most. An account is locked for {m} minutes after {a} wrong passwords.", sec_https: "The site address uses HTTPS", sec_debug_off: "Error details are hidden", sec_production: "Production mode", sec_app_key: "Encryption key is set", sec_env_private: "The settings file cannot be reached from the web", sec_cors_own_site: "Only this site may call the API from a browser", sec_update_key: "Update signing key is installed", sec_no_demo_accounts: "No demo accounts", sec_strong_limits: "Sign-in limits are strict", sech_https: "Use https:// in the site address (hPanel, SSL).", sech_debug_off: "Set APP_DEBUG=false in the settings file.", sech_production: "Set APP_ENV=production in the settings file.", sech_app_key: "The encryption key is missing: contact the maintainer.", sech_env_private: "Move the settings file out of the website folder.", sech_cors_own_site: "Remove * from CORS_ALLOWED_ORIGINS.", sech_update_key: "Add the update signing key in config/updates.php.", sech_no_demo_accounts: "Delete the demo accounts (email ending .test) in Accounts.", sech_strong_limits: "Lower the limits in config/security.php.", pres_goto: "Getting started", pres_title: "President Admin", pres_note: "Change who the President Admin is, or their sign-in details. It is the same account, so nothing in the office is lost. Their devices are signed out at once.", pres_none: "There is no President Admin yet. Create one from Getting started.",
    pres_change: "Change President Admin", pres_name: "Full name", pres_email: "Email", pres_pass: "New password (leave empty to keep it)", pres_confirm: "Your own password, to confirm", pres_gen: "Generate", pres_saved: "President Admin updated.", pres_hint: "At least 12 characters with upper and lower case and a number. Give it to the person privately.", pres_off: "Switched off", pres_on: "Active",
    sys_title: "System", sys_health: "Health", sys_status: "Status", sys_db: "Database", sys_storage: "Storage writable", sys_php: "PHP", sys_lar: "Laravel", sys_env: "Environment", sys_ok: "OK", sys_bad: "Problem",
    sys_settings: "Settings", f_org: "Organisation name", f_cur: "Currency code", sys_saved: "Saved", sys_keys: "Partner keys", sys_keys_note: "A key lets an outside system (for example Dar Al Ber) read totals only, never names. It is shown once.",
    add_key: "Create key", f_kname: "Who is it for?", key_made: "Copy the key now. It cannot be shown again.", copy: "Copy", copied: "Copied", revoke_key: "Revoke", revoked: "Revoked", expires: "Expires", no_keys: "No keys yet.",
    sys_note: "This page needs an internet connection. It holds no personal data."
  }, {
sec_title: "فحص الأمان", sec_ok: "سليم", sec_fix: "يحتاج انتباهًا", sec_note: "تُنهى جلسات الحسابات المميزة بعد {i} دقيقة خمول وبحد أقصى {h} ساعات. يُقفل الحساب {m} دقيقة بعد {a} كلمات مرور خاطئة.", sec_https: "عنوان الموقع يستخدم HTTPS", sec_debug_off: "تفاصيل الأخطاء مخفية", sec_production: "وضع الإنتاج", sec_app_key: "مفتاح التشفير موجود", sec_env_private: "لا يمكن الوصول إلى ملف الإعدادات من الويب", sec_cors_own_site: "هذا الموقع فقط يستدعي الواجهة من المتصفح", sec_update_key: "مفتاح توقيع التحديثات مثبّت", sec_no_demo_accounts: "لا حسابات تجريبية", sec_strong_limits: "حدود تسجيل الدخول صارمة", sech_https: "استخدم https:// في عنوان الموقع (hPanel، SSL).", sech_debug_off: "اضبط APP_DEBUG=false في ملف الإعدادات.", sech_production: "اضبط APP_ENV=production في ملف الإعدادات.", sech_app_key: "مفتاح التشفير مفقود: تواصل مع المسؤول عن الصيانة.", sech_env_private: "انقل ملف الإعدادات خارج مجلد الموقع.", sech_cors_own_site: "احذف * من CORS_ALLOWED_ORIGINS.", sech_update_key: "أضف مفتاح توقيع التحديثات في config/updates.php.", sech_no_demo_accounts: "احذف الحسابات التجريبية (بريد ينتهي بـ .test) من الحسابات.", sech_strong_limits: "خفّض الحدود في config/security.php.", pres_goto: "البدء", pres_title: "رئيس الإدارة", pres_note: "غيّر من هو رئيس الإدارة أو بيانات دخوله. الحساب نفسه يبقى، فلا يضيع شيء من المكتب. تُنهى جلساته على أجهزته فورًا.", pres_none: "لا يوجد رئيس إدارة بعد. أنشئ واحدًا من صفحة البدء.",
    pres_change: "غيّر رئيس الإدارة", pres_name: "الاسم الكامل", pres_email: "البريد الإلكتروني", pres_pass: "كلمة مرور جديدة (اتركها فارغة للإبقاء)", pres_confirm: "كلمة مرورك أنت للتأكيد", pres_gen: "توليد", pres_saved: "تم تحديث رئيس الإدارة.", pres_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. أعطها للشخص بسرية.", pres_off: "متوقف", pres_on: "نشط",
    sys_title: "النظام", sys_health: "الصحة", sys_status: "الحالة", sys_db: "قاعدة البيانات", sys_storage: "التخزين قابل للكتابة", sys_php: "PHP", sys_lar: "Laravel", sys_env: "البيئة", sys_ok: "سليم", sys_bad: "مشكلة",
    sys_settings: "الإعدادات", f_org: "اسم المؤسسة", f_cur: "رمز العملة", sys_saved: "تم الحفظ", sys_keys: "مفاتيح الشركاء", sys_keys_note: "المفتاح يتيح لنظام خارجي (مثل دار البر) قراءة المجاميع فقط، دون أسماء. يظهر مرة واحدة.",
    add_key: "إنشاء مفتاح", f_kname: "لمن هذا المفتاح؟", key_made: "انسخ المفتاح الآن. لا يمكن عرضه مرة أخرى.", copy: "نسخ", copied: "تم النسخ", revoke_key: "إلغاء", revoked: "أُلغي", expires: "ينتهي", no_keys: "لا مفاتيح بعد.",
    sys_note: "هذه الصفحة تحتاج اتصالًا بالإنترنت. لا تحوي بيانات شخصية."
  });

  function load() {
    var jobs = [A.api.get("system/health", { cache: false })];
    jobs.push(A.can("system.settings") ? A.api.get("system/settings", { cache: false }) : Promise.resolve(null));
    jobs.push(A.can("system.api_keys") ? A.api.get("system/api-keys", { cache: false }) : Promise.resolve(null));
    jobs.push(A.can("system.first_admin") ? A.api.get("system/president-admin", { cache: false }) : Promise.resolve(null));
    jobs.push(A.api.get("system/security", { cache: false }).catch(function () { return null; }));
    return Promise.all(jobs).then(function (r) { return { data: { health: r[0].data, settings: r[1] && r[1].data, keys: r[2] && r[2].data, pres: r[3] ? { have: true, p: r[3].data } : null, sec: r[4] && r[4].data }, stale: false }; });
  }
  function render(d) {
    var h = d.health, ok = h.status === "ok", out = '<div class="head"><h1 class="title">' + t("sys_title") + '</h1></div><p class="note">' + t("sys_note") + "</p>";
    out += '<div class="grid g2" style="align-items:start"><div class="card"><h3>' + t("sys_health") + "</h3>" + A.facts([
      [t("sys_status"), '<span class="badge ' + (ok ? "" : "warn") + '">' + t(ok ? "sys_ok" : "sys_bad") + "</span>"],
      [t("sys_db"), h.checks.database ? t("sys_ok") : t("sys_bad")], [t("sys_storage"), h.checks.storage_writable ? t("sys_ok") : t("sys_bad")],
      [t("sys_php"), esc(h.versions.php)], [t("sys_lar"), esc(h.versions.laravel)], [t("sys_env"), esc(h.environment)]]) + "</div>";
    if (d.sec) {
      var lim = d.sec.limits || {};
      out += '<div class="card"><h3>' + t("sec_title") + '</h3><ul class="fb-rows">' + d.sec.checks.map(function (c) {
        return "<li><span>" + esc(t("sec_" + c.key)) + '</span><span class="badge ' + (c.ok ? "" : "warn") + '">' + t(c.ok ? "sec_ok" : "sec_fix") + "</span></li>" + (c.ok ? "" : '<li class="note">' + esc(t("sech_" + c.key)) + "</li>");
      }).join("") + '</ul><p class="note">' + esc(t("sec_note", { i: (lim.idle_minutes || {}).privileged, h: (lim.max_hours || {}).privileged, m: (lim.lockout || {}).minutes, a: (lim.lockout || {}).attempts })) + "</p></div>";
    }
    if (d.settings) {
      out += '<div class="card"><h3>' + t("sys_settings") + '</h3><form id="setForm" novalidate>' + A.field("s_org_name", t("f_org"), "", "text", 'value="' + esc(d.settings.org_name || "") + '"') +
        A.field("s_currency", t("f_cur"), "", "text", 'maxlength="3" dir="ltr" value="' + esc(d.settings.currency || "") + '"') + '<div class="err" id="formErr"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("save") + "</button></div></form></div>";
    }
    out += "</div>";
    if (d.pres) {
      pres = d.pres.p;
      out += '<div class="card" style="margin-top:1rem"><h3>' + t("pres_title") + '</h3>' + (pres ? '<p class="note">' + t("pres_note") + "</p>" + A.facts([[t("pres_name"), esc(pres.name)], [t("pres_email"), '<span dir="ltr">' + esc(pres.email) + "</span>"], ["", '<span class="badge ' + (pres.active ? "" : "warn") + '">' + t(pres.active ? "pres_on" : "pres_off") + "</span>"]]) +
        '<div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="chgpres">' + t("pres_change") + "</button></div>" : '<p class="note">' + t("pres_none") + '</p><div class="actions"><a class="btn btn-main" href="setup.html">' + t("pres_goto") + "</a></div>") + "</div>";
    }
    if (d.keys) {
      keys = d.keys;
      out += '<div class="card" style="margin-top:1rem"><h3>' + t("sys_keys") + '</h3><p class="note">' + t("sys_keys_note") + '</p><div class="list">' + (keys.length ? keys.map(function (k) {
        return '<div class="item"><span class="grow"><b>' + esc(k.name) + '</b><span class="sub" dir="ltr" style="text-align:start">' + esc(k.prefix) + "… · " + t("expires") + " " + esc((k.expires_at || "").slice(0, 10)) + '</span></span><span class="right"><span class="badge ' + (k.active ? "" : "grey") + '">' + (k.active ? t("sys_ok") : t("revoked")) + "</span>" +
          (k.active ? '<button class="btn btn-line btn-sm" data-act="revokekey" data-id="' + k.id + '">' + t("revoke_key") + "</button>" : "") + "</span></div>";
      }).join("") : '<p class="note">' + t("no_keys") + "</p>") + '</div><div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="addkey">' + ic("plus") + t("add_key") + "</button></div></div>";
    }
    return out;
  }

  function v(id) { return document.getElementById(id).value.trim(); }
  function failMsg(e) { return e.offline ? t("need_conn") : A.msg(e); }
  A.form("setForm", function () {
    var body = {}; if (v("s_org_name")) body.org_name = v("s_org_name"); if (v("s_currency")) body.currency = v("s_currency").toUpperCase();
    A.api.write("PATCH", "system/settings", body, { online: true }).then(function () { A.toast(t("sys_saved")); }, function (e) { document.getElementById("formErr").textContent = failMsg(e); });
  });
  function genPass() {
    var U = "ABCDEFGHJKLMNPQRSTUVWXYZ", L = "abcdefghijkmnopqrstuvwxyz", D = "23456789", all = U + L + D, a = new Uint8Array(16); crypto.getRandomValues(a);
    var out = [U[a[0] % U.length], L[a[1] % L.length], D[a[2] % D.length]]; for (var i = 3; i < 16; i++) out.push(all[a[i] % all.length]); return out.join("");
  }
  A.act("chgpres", function () {
    A.sheet(t("pres_change"), '<form id="presForm" novalidate>' + A.field("pr_name", t("pres_name"), "", "text", 'value="' + esc(pres.name) + '"') + A.field("pr_email", t("pres_email"), "", "email", 'dir="ltr" autocomplete="off" value="' + esc(pres.email) + '"') +
      A.field("pr_password", t("pres_pass"), '<div class="actions" style="margin-top:.4rem"><button class="btn btn-line btn-sm" type="button" data-act="prgen">' + t("pres_gen") + '</button></div><p class="note" style="margin:.4rem 0 0">' + t("pres_hint") + "</p>", "text", 'dir="ltr" autocomplete="off"') +
      A.field("pr_admin_password", t("pres_confirm"), "", "password", 'dir="ltr" autocomplete="current-password"') + A.formEnd() + "</form>");
  });
  A.act("prgen", function () { document.getElementById("pr_password").value = genPass(); });
  A.form("presForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    if (!v("pr_admin_password")) { document.getElementById("pr_admin_passwordErr").textContent = t("required"); return; }
    var body = { admin_password: document.getElementById("pr_admin_password").value };
    if (v("pr_name") && v("pr_name") !== pres.name) body.name = v("pr_name");
    if (v("pr_email") && v("pr_email") !== pres.email) body.email = v("pr_email");
    if (v("pr_password")) body.password = v("pr_password");
    A.api.write("PATCH", "system/president-admin", body, { online: true }).then(function () { A.closeSheet(); A.toast(t("pres_saved")); A.reload(true); }, function (e) {
      if (!(e.errors && A.showErrors(e.errors, "pr_"))) document.getElementById("formErr").textContent = failMsg(e);
    });
  });
  A.act("addkey", function () {
    A.sheet(t("add_key"), '<form id="keyForm" novalidate>' + A.field("k_name", t("f_kname")) + '<div class="err" id="formErr"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("add_key") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("cancel") + "</button></div></form>");
  });
  A.form("keyForm", function () {
    var name = v("k_name"); if (!name) { document.getElementById("k_nameErr").textContent = t("required"); return; }
    A.api.write("POST", "system/api-keys", { name: name }, { online: true }).then(function (r) {
      A.sheet(t("add_key"), '<p>' + t("key_made") + '</p><p><code dir="ltr" id="newKey" style="word-break:break-all;display:block;padding:.8rem;background:var(--fill);border-radius:var(--r-sm)">' + esc(r.data.key) + '</code></p><div class="actions"><button class="btn btn-main" type="button" data-act="copykey">' + t("copy") + '</button><button class="btn btn-line" type="button" data-act="close">OK</button></div>');
      A.reload(true);
    }, function (e) { document.getElementById("formErr").textContent = failMsg(e); });
  });
  A.act("copykey", function () {
    var txt = document.getElementById("newKey").textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { A.toast(t("copied")); }, function () {
      var r = document.createRange(); r.selectNodeContents(document.getElementById("newKey")); var s = getSelection(); s.removeAllRanges(); s.addRange(r);
    });
  });
  A.act("revokekey", function (el) {
    A.api.write("DELETE", "system/api-keys/" + el.getAttribute("data-id"), undefined, { online: true }).then(function () { A.toast(t("revoked")); A.reload(true); }, function (e) { A.toast(failMsg(e)); });
  });

  A.start({ page: "system", nav: "system", title: "n_system", needsConn: true, perm: "system.health", shell: true, load: load, render: render });
})();
