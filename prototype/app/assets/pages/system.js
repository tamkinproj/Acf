(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, keys = [];

  A.strings({
    sys_title: "System", sys_health: "Health", sys_status: "Status", sys_db: "Database", sys_storage: "Storage writable", sys_php: "PHP", sys_lar: "Laravel", sys_env: "Environment", sys_ok: "OK", sys_bad: "Problem",
    sys_settings: "Settings", f_org: "Organisation name", f_cur: "Currency code", sys_saved: "Saved", sys_keys: "Partner keys", sys_keys_note: "A key lets an outside system (for example Dar Al Ber) read totals only, never names. It is shown once.",
    add_key: "Create key", f_kname: "Who is it for?", key_made: "Copy the key now. It cannot be shown again.", copy: "Copy", copied: "Copied", revoke_key: "Revoke", revoked: "Revoked", expires: "Expires", no_keys: "No keys yet.",
    sys_note: "This page needs an internet connection. It holds no personal data."
  }, {
    sys_title: "النظام", sys_health: "الصحة", sys_status: "الحالة", sys_db: "قاعدة البيانات", sys_storage: "التخزين قابل للكتابة", sys_php: "PHP", sys_lar: "Laravel", sys_env: "البيئة", sys_ok: "سليم", sys_bad: "مشكلة",
    sys_settings: "الإعدادات", f_org: "اسم المؤسسة", f_cur: "رمز العملة", sys_saved: "تم الحفظ", sys_keys: "مفاتيح الشركاء", sys_keys_note: "المفتاح يتيح لنظام خارجي (مثل دار البر) قراءة المجاميع فقط، دون أسماء. يظهر مرة واحدة.",
    add_key: "إنشاء مفتاح", f_kname: "لمن هذا المفتاح؟", key_made: "انسخ المفتاح الآن. لا يمكن عرضه مرة أخرى.", copy: "نسخ", copied: "تم النسخ", revoke_key: "إلغاء", revoked: "أُلغي", expires: "ينتهي", no_keys: "لا مفاتيح بعد.",
    sys_note: "هذه الصفحة تحتاج اتصالًا بالإنترنت. لا تحوي بيانات شخصية."
  });

  function load() {
    var jobs = [A.api.get("system/health", { cache: false })];
    jobs.push(A.can("system.settings") ? A.api.get("system/settings", { cache: false }) : Promise.resolve(null));
    jobs.push(A.can("system.api_keys") ? A.api.get("system/api-keys", { cache: false }) : Promise.resolve(null));
    return Promise.all(jobs).then(function (r) { return { data: { health: r[0].data, settings: r[1] && r[1].data, keys: r[2] && r[2].data }, stale: false }; });
  }
  function render(d) {
    var h = d.health, ok = h.status === "ok", out = '<div class="head"><h1 class="title">' + t("sys_title") + '</h1></div><p class="note">' + t("sys_note") + "</p>";
    out += '<div class="grid g2" style="align-items:start"><div class="card"><h3>' + t("sys_health") + "</h3>" + A.facts([
      [t("sys_status"), '<span class="badge ' + (ok ? "" : "warn") + '">' + t(ok ? "sys_ok" : "sys_bad") + "</span>"],
      [t("sys_db"), h.checks.database ? t("sys_ok") : t("sys_bad")], [t("sys_storage"), h.checks.storage_writable ? t("sys_ok") : t("sys_bad")],
      [t("sys_php"), esc(h.versions.php)], [t("sys_lar"), esc(h.versions.laravel)], [t("sys_env"), esc(h.environment)]]) + "</div>";
    if (d.settings) {
      out += '<div class="card"><h3>' + t("sys_settings") + '</h3><form id="setForm" novalidate>' + A.field("s_org_name", t("f_org"), "", "text", 'value="' + esc(d.settings.org_name || "") + '"') +
        A.field("s_currency", t("f_cur"), "", "text", 'maxlength="3" dir="ltr" value="' + esc(d.settings.currency || "") + '"') + '<div class="err" id="formErr"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("save") + "</button></div></form></div>";
    }
    out += "</div>";
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
