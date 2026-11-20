(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, found = null;

  A.strings({
    upd_title: "Updates", upd_note: "Upload a signed patch (the .zip and its .sig file). The system checks the signature, backs up what it will change, applies it, and can undo it. Needs a connection.",
    upd_build: "Version on this server", upd_key: "Trusted signing key", upd_pick_zip: "Patch file (.zip)", upd_pick_sig: "Signature file (.sig)", upd_check: "Check patch",
    upd_checking: "Checking…", upd_found: "This patch will", upd_add: "add {n} files", upd_chg: "change {n} files", upd_del: "remove {n} files", upd_db: "It also changes the database (a small new table or column).",
    upd_apply: "Apply update", upd_applying: "Updating… do not close this page.", upd_cancel: "Cancel", upd_ok: "Updated. Reload the app to use the new version.", upd_reload: "Reload now",
    upd_hist: "History", upd_none: "No updates yet.", upd_undo: "Undo this update", upd_undo_q: "Put the previous version back? The files return to how they were before this update.",
    upd_undone: "Undone. The previous version is back.", s_applied: "Applied", s_undone: "Undone", s_failed: "Failed, restored", upd_from: "from build",
    upd_backup_note: "Undo restores the program files and this update's database changes. It does not back up your records or documents.", upd_need_both: "Choose both files.",
    upd_files: "Files", upd_no_site: "The website folder was not found next to insania_app, so website files cannot be updated here."
  }, {
    upd_title: "التحديثات", upd_note: "ارفع رقعة موقّعة (ملف .zip وملف التوقيع .sig). يتحقق النظام من التوقيع، وينسخ احتياطيًا ما سيغيّره، ثم يطبّقها ويمكنه التراجع. يحتاج اتصالًا.",
    upd_build: "الإصدار على هذا الخادم", upd_key: "مفتاح التوقيع الموثوق", upd_pick_zip: "ملف الرقعة (.zip)", upd_pick_sig: "ملف التوقيع (.sig)", upd_check: "افحص الرقعة",
    upd_checking: "جارٍ الفحص…", upd_found: "ستقوم هذه الرقعة بـ", upd_add: "إضافة {n} ملفات", upd_chg: "تغيير {n} ملفات", upd_del: "حذف {n} ملفات", upd_db: "وتغيّر أيضًا قاعدة البيانات (جدول أو عمود صغير جديد).",
    upd_apply: "طبّق التحديث", upd_applying: "جارٍ التحديث… لا تغلق هذه الصفحة.", upd_cancel: "إلغاء", upd_ok: "تم التحديث. أعد تحميل التطبيق لاستخدام الإصدار الجديد.", upd_reload: "أعد التحميل الآن",
    upd_hist: "السجل", upd_none: "لا تحديثات بعد.", upd_undo: "تراجع عن هذا التحديث", upd_undo_q: "إرجاع الإصدار السابق؟ ستعود الملفات كما كانت قبل هذا التحديث.",
    upd_undone: "تم التراجع. عاد الإصدار السابق.", s_applied: "مطبّق", s_undone: "تم التراجع", s_failed: "فشل، أُعيد", upd_from: "من الإصدار",
    upd_backup_note: "التراجع يعيد ملفات البرنامج وتغييرات قاعدة البيانات الخاصة بهذا التحديث. لا يحفظ نسخة من سجلاتك أو مستنداتك.", upd_need_both: "اختر الملفين.",
    upd_files: "الملفات", upd_no_site: "لم يُعثر على مجلد الموقع بجوار insania_app، لذا لا يمكن تحديث ملفات الموقع من هنا."
  });

  function load() { return A.api.get("system/updates", { cache: false }); }
  function render(d) {
    var hist = d.history.length ? '<div class="list">' + d.history.map(function (h) {
      var st = h.status === "applied" ? "s_applied" : h.status === "undone" ? "s_undone" : "s_failed";
      return '<div class="item"><span class="grow"><b>' + esc(h.name) + " · " + esc(h.version) + '</b><span class="sub" dir="ltr" style="text-align:start">' + esc(h.build) + " · " + esc((h.at || "").slice(0, 16).replace("T", " ")) + (h.status === "failed" ? " · " + esc(h.error || "") : "") + '</span></span><span class="right"><span class="badge ' + (h.status === "applied" ? "" : h.status === "undone" ? "grey" : "warn") + '">' + t(st) + "</span>" +
        (d.undo_id === h.id ? '<button class="btn btn-line btn-sm" data-act="undo" data-id="' + esc(h.id) + '">' + t("upd_undo") + "</button>" : "") + "</span></div>";
    }).join("") + "</div>" : '<p class="note">' + t("upd_none") + "</p>";
    return '<div class="head"><h1 class="title">' + t("upd_title") + '</h1></div><p class="note">' + t("upd_note") + "</p>" +
      '<div class="grid g2" style="align-items:start"><div class="card"><h3>' + t("upd_build") + "</h3>" + A.facts([[t("upd_build"), '<span dir="ltr">' + esc(d.build) + "</span>"], [t("upd_key"), '<span dir="ltr">' + esc(d.key_fingerprint) + "</span>"]]) + (d.website_folder_found ? "" : '<p class="note">' + t("upd_no_site") + "</p>") +
      '<form id="patchForm" novalidate style="margin-top:1rem"><div><label for="pz">' + t("upd_pick_zip") + '</label><input id="pz" type="file" accept=".zip,application/zip"></div><div><label for="ps">' + t("upd_pick_sig") + '</label><input id="ps" type="file" accept=".sig,.txt,text/plain"></div>' +
      '<div class="err" id="formErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit" id="checkBtn">' + ic("download") + t("upd_check") + "</button></div></form></div>" +
      '<div class="card" id="found"></div></div><div class="card" style="margin-top:1rem"><h3>' + t("upd_hist") + "</h3>" + hist + '<p class="note">' + t("upd_backup_note") + "</p></div>";
  }
  function after() { drawFound(); }

  function drawFound() {
    var box = document.getElementById("found"); if (!box) return;
    if (!found) { box.style.display = "none"; return; }
    box.style.display = "";
    var f = found, parts = [];
    if (f.add) parts.push(t("upd_add", { n: f.add })); if (f.change) parts.push(t("upd_chg", { n: f.change })); if (f.delete) parts.push(t("upd_del", { n: f.delete }));
    box.innerHTML = '<h3>' + esc(f.name) + " · " + esc(f.version) + '</h3><p class="note" dir="ltr" style="text-align:start;margin-top:0">' + esc(f.current_build) + " → " + esc(f.build) + "</p>" + (f.notes ? "<p>" + esc(f.notes) + "</p>" : "") +
      "<p><b>" + t("upd_found") + "</b> " + esc(parts.join(", ")) + ".</p>" + (f.database_changes ? '<p class="note">' + t("upd_db") + "</p>" : "") +
      '<details><summary>' + t("upd_files") + '</summary><div class="list" style="margin-top:.6rem">' + f.files.map(function (x) { return '<div class="item"><span class="grow"><b dir="ltr" style="text-align:start;font-size:.85rem">' + esc(x.path) + '</b></span><span class="badge grey">' + esc(x.op) + "</span></div>"; }).join("") + "</div></details>" +
      '<div class="err" id="applyErr" role="alert"></div><div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="applypatch">' + ic("check") + t("upd_apply") + '</button><button class="btn btn-line" data-act="cancelpatch">' + t("upd_cancel") + "</button></div>";
  }

  A.form("patchForm", function () {
    var z = document.getElementById("pz").files[0], s = document.getElementById("ps").files[0], err = document.getElementById("formErr"), btn = document.getElementById("checkBtn");
    err.textContent = ""; found = null; drawFound();
    if (!z || !s) { err.textContent = t("upd_need_both"); return; }
    var fd = new FormData(); fd.append("patch", z); fd.append("signature", s);
    btn.disabled = true; btn.lastChild.textContent = t("upd_checking");
    A.api.upload("system/updates/check", fd).then(function (d) { btn.disabled = false; btn.lastChild.textContent = t("upd_check"); found = d; drawFound(); }, function (e) {
      btn.disabled = false; btn.lastChild.textContent = t("upd_check"); err.textContent = e.offline ? t("need_conn") : A.msg(e);
    });
  });
  A.act("cancelpatch", function () { found = null; drawFound(); });
  A.act("applypatch", function (el) {
    var box = document.getElementById("found"); el.disabled = true; var applyErr = document.getElementById("applyErr"); applyErr.textContent = ""; el.lastChild.textContent = t("upd_applying");
    A.api.write("POST", "system/updates/" + found.id + "/apply", {}, { online: true }).then(function () {
      found = null; box.innerHTML = '<h3>✓</h3><p>' + t("upd_ok") + '</p><div class="actions"><button class="btn btn-main" data-act="reloadpage">' + t("upd_reload") + "</button></div>";
    }, function (e) { el.disabled = false; el.lastChild.textContent = t("upd_apply"); applyErr.textContent = e.offline ? t("need_conn") : A.msg(e); });
  });
  A.act("reloadpage", function () { location.reload(); });
  A.act("undo", function (el) {
    if (!confirm(t("upd_undo_q"))) return;
    el.disabled = true;
    A.api.write("POST", "system/updates/" + el.getAttribute("data-id") + "/undo", {}, { online: true }).then(function () { A.toast(t("upd_undone")); A.reload(true); }, function (e) { el.disabled = false; A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });

  A.start({ page: "updates", nav: "updates", title: "upd_title", needsConn: true, perm: "system.updates", shell: true, load: load, render: render, after: after });
})();
