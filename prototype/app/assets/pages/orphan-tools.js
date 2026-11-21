(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    imp_btn: "Import from Excel", imp_title: "Import orphans from Excel", imp_note: "Choose the report sheet (.xlsx or .csv) with the columns orphan number, orphan name, sponsor number, sponsor name. Cash or bank is read from the sheet title (نقدي / بنكي). Sheets can be imported again safely: an orphan already saved is updated, never added twice.", imp_file: "Excel file", imp_pay: "Cash or bank", imp_auto: "Read from the sheet title", imp_cash: "Cash", imp_bank: "Bank", imp_check: "Check the file", imp_go: "Import {n} orphans", imp_none: "Choose a file first.", imp_new: "New", imp_upd: "Already saved (updated)", imp_skip: "Skipped", imp_exp: "Sheet says {n}", imp_done: "Imported: {n} new, {u} updated", imp_busy: "Reading…", imp_nothing: "Nothing to import from this file.", imp_sheet: "Sheet", 
    acc_btn: "Orphan accounts", acc_title: "Orphan accounts", acc_note: "Every orphan can have their own account to sign in, see their information and set up their profile. Each account gets a username and a temporary password that they must replace the first time they sign in.", acc_sum: "{t} orphans · {w} have an account · {n} without", acc_pw: "Your own password (to confirm)", acc_make: "Create {n} accounts", acc_busy: "Creating… {d} of {t}", acc_done: "{n} accounts created", acc_warn: "These temporary passwords are shown only now and are never saved. Download the list and give each orphan their own.", acc_csv: "Download the list (CSV)", acc_none: "Every orphan already has an account.", acc_one: "Account", acc_reset: "Reset password", acc_user: "Username", acc_temp: "Temporary password", acc_need_pw: "Type your own password first.", acc_one_title: "Account for {name}", acc_make_one: "Create account", acc_reset_one: "Set a new temporary password", acc_has: "Has an account", 
  }, {
    imp_btn: "استيراد من إكسل", imp_title: "استيراد الأيتام من إكسل", imp_note: "اختر ملف الكشف (‎.xlsx أو ‎.csv) وفيه الأعمدة: رقم اليتيم واسم اليتيم ورقم الكافل واسم الكافل. يُعرف نقدي أو بنكي من عنوان الكشف. يمكن استيراد الكشف مرة أخرى بأمان: اليتيم المحفوظ يُحدَّث ولا يُضاف مرتين.", imp_file: "ملف إكسل", imp_pay: "نقدي أو بنكي", imp_auto: "من عنوان الكشف", imp_cash: "نقدي", imp_bank: "بنكي", imp_check: "افحص الملف", imp_go: "استيراد {n} يتيمًا", imp_none: "اختر ملفًا أولًا.", imp_new: "جديد", imp_upd: "محفوظ سابقًا (يُحدَّث)", imp_skip: "تم تخطيه", imp_exp: "عدد الكشف {n}", imp_done: "تم الاستيراد: {n} جديد و{u} محدَّث", imp_busy: "جارٍ القراءة…", imp_nothing: "لا يوجد ما يُستورد من هذا الملف.", imp_sheet: "الورقة", 
    acc_btn: "حسابات الأيتام", acc_title: "حسابات الأيتام", acc_note: "يمكن لكل يتيم أن يكون له حسابه الخاص ليسجّل الدخول ويرى معلوماته ويُعدّ ملفه. يحصل كل حساب على اسم مستخدم وكلمة مرور مؤقتة يجب أن يستبدلها عند أول تسجيل دخول.", acc_sum: "{t} يتيمًا · {w} لديهم حساب · {n} بدون حساب", acc_pw: "كلمة مرورك أنت (للتأكيد)", acc_make: "إنشاء {n} حسابًا", acc_busy: "جارٍ الإنشاء… {d} من {t}", acc_done: "تم إنشاء {n} حسابًا", acc_warn: "كلمات المرور المؤقتة هذه تظهر الآن فقط ولا تُحفظ. نزّل القائمة وسلّم كل يتيم بياناته.", acc_csv: "تنزيل القائمة (CSV)", acc_none: "لكل يتيم حساب بالفعل.", acc_one: "الحساب", acc_reset: "إعادة تعيين كلمة المرور", acc_user: "اسم المستخدم", acc_temp: "كلمة المرور المؤقتة", acc_need_pw: "اكتب كلمة مرورك أولًا.", acc_one_title: "حساب {name}", acc_make_one: "إنشاء حساب", acc_reset_one: "تعيين كلمة مرور مؤقتة جديدة", acc_has: "لديه حساب", 
  });

  /* ---- import the office's Excel sheets ---- */
  function impReport(r) {
    var total = r.new + r.updated;
    var html = r.sheets.map(function (s) {
      return '<div class="item" style="display:block"><b>' + esc(s.sheet) + '</b> ' + (s.pay_type ? '<span class="badge">' + t(s.pay_type === "cash" ? "imp_cash" : "imp_bank") + "</span>" : "") +
        (s.title ? '<p class="note" style="margin:.3rem 0">' + esc(s.title) + "</p>" : "") +
        '<p style="margin:.3rem 0">' + t("imp_new") + ": <b>" + s.new + "</b> · " + t("imp_upd") + ": <b>" + s.updated + "</b> · " + t("imp_skip") + ": <b>" + s.skipped + "</b>" + (s.expected !== null && s.expected !== undefined ? ' · <span class="note">' + t("imp_exp", { n: s.expected }) + "</span>" : "") + "</p>" +
        (s.errors || []).map(function (e) { return '<p class="err" style="margin:.2rem 0">' + esc(e) + "</p>"; }).join("") +
        (s.sample || []).map(function (x) { return '<p class="note" style="margin:.15rem 0" dir="auto">' + (x.no ? "#" + esc(x.no) + " · " : "") + esc(x.name) + (x.sponsor ? " — " + esc(x.sponsor) : "") + "</p>"; }).join("") + "</div>";
    }).join("");
    return '<div class="list">' + html + "</div>" + (total ? '<div class="actions" style="margin-top:1rem"><button class="btn btn-main" type="button" data-act="impgo">' + t("imp_go", { n: total }) + "</button></div>" : '<p class="note">' + t("imp_nothing") + "</p>");
  }
  function impSend(dry) {
    var f = document.getElementById("impFile").files[0], out = document.getElementById("impOut");
    if (!f) { out.innerHTML = '<p class="err">' + t("imp_none") + "</p>"; return; }
    var fd = new FormData(); fd.append("file", f); fd.append("pay_type", document.getElementById("impPay").value); if (dry) fd.append("dry_run", "1");
    out.innerHTML = '<p class="note">' + t("imp_busy") + "</p>";
    A.api.upload("children/import", fd).then(function (r) {
      var d = r.data || r;
      if (dry) { out.innerHTML = impReport(d); return; }
      A.closeSheet(); A.toast(t("imp_done", { n: d.new, u: d.updated })); A.api.wipeCache(); A.reload(true);
    }, function (e) { out.innerHTML = '<p class="err">' + esc(e.offline ? t("need_conn") : A.msg(e)) + "</p>"; });
  }
  A.act("importxl", function () {
    A.sheet(t("imp_title"), '<p class="note" style="margin-top:0">' + t("imp_note") + "</p>" +
      '<div style="display:grid;gap:1rem"><div><label for="impFile">' + t("imp_file") + '</label><input id="impFile" type="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"></div>' +
      '<div><label for="impPay">' + t("imp_pay") + '</label><select id="impPay"><option value="auto">' + t("imp_auto") + '</option><option value="cash">' + t("imp_cash") + '</option><option value="bank">' + t("imp_bank") + "</option></select></div>" +
      '<div class="actions"><button class="btn btn-line" type="button" data-act="impcheck">' + t("imp_check") + '</button></div><div id="impOut"></div></div>');
  });
  A.act("impcheck", function () { impSend(true); });
  A.act("impgo", function () { impSend(false); });

  /* ---- sign-in accounts for the orphans ---- */
  var made = [];
  function credTable(rows) {
    return '<div class="list">' + rows.map(function (c) {
      return '<div class="item" style="display:block"><b dir="auto">' + esc(c.name_en || c.name_ar) + '</b><p class="note" style="margin:.2rem 0" dir="ltr">' + t("acc_user") + ": <b>" + esc(c.username) + "</b> · " + t("acc_temp") + ": <b>" + esc(c.password) + "</b></p></div>";
    }).join("") + "</div>";
  }
  function csvDownload(rows) {
    var q = function (v) { return '"' + String(v == null ? "" : v).replace(/"/g, '""') + '"'; };
    var lines = [["Orphan number", "Reference", "Name (English)", "Name (Arabic)", "Username", "Temporary password"].map(q).join(",")].concat(rows.map(function (c) { return [c.external_no, c.ref, c.name_en, c.name_ar, c.username, c.password].map(q).join(","); }));
    var blob = new Blob(["﻿" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" }), a = document.createElement("a");
    a.href = URL.createObjectURL(blob); a.download = "orphan-accounts.csv"; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }
  A.act("accounts", function () {
    made = [];
    A.sheet(t("acc_title"), '<p class="note" style="margin-top:0">' + t("acc_note") + '</p><div id="accBox"><p class="note">' + t("sess_load") + "</p></div>");
    A.api.get("children/accounts", { cache: false }).then(function (r) {
      var d = r.data, box = document.getElementById("accBox"); if (!box) return;
      box.innerHTML = '<p><b>' + t("acc_sum", { t: d.total, w: d.with_account, n: d.without_account }) + "</b></p>" + (d.without_account ? '<div style="display:grid;gap:1rem"><div><label for="accPw">' + t("acc_pw") + '</label><input id="accPw" type="password" autocomplete="current-password" dir="ltr"></div><div id="accErr" class="err"></div><div class="actions"><button class="btn btn-main" type="button" data-act="accmake" data-n="' + d.without_account + '">' + t("acc_make", { n: d.without_account }) + "</button></div></div>" : '<p class="note">' + t("acc_none") + "</p>");
    }, function (e) { var box = document.getElementById("accBox"); if (box) box.innerHTML = '<p class="err">' + esc(e.offline ? t("need_conn") : A.msg(e)) + "</p>"; });
  });
  A.act("accmake", function (el) {
    var pw = document.getElementById("accPw").value, err = document.getElementById("accErr"), total = +el.getAttribute("data-n");
    if (!pw) { err.textContent = t("acc_need_pw"); return; }
    err.textContent = ""; el.disabled = true; el.textContent = t("acc_busy", { d: 0, t: total });
    (function step() {
      A.api.write("POST", "children/accounts", { confirm_password: pw }, { online: true }).then(function (r) {
        var d = r.data || r; made = made.concat(d.credentials || []); el.textContent = t("acc_busy", { d: made.length, t: total });
        if (d.remaining > 0 && d.created > 0) { step(); return; }
        document.getElementById("accBox").innerHTML = '<p><b>' + t("acc_done", { n: made.length }) + '</b></p><p class="note">' + t("acc_warn") + '</p><div class="actions"><button class="btn btn-main" type="button" data-act="acccsv">' + ic("download") + t("acc_csv") + "</button></div>" + credTable(made);
        A.api.wipeCache(); csvDownload(made);
      }, function (e) { el.disabled = false; el.textContent = t("acc_make", { n: total }); err.textContent = (e.errors && e.errors.confirm_password && e.errors.confirm_password[0]) || (e.offline ? t("need_conn") : A.msg(e)); if (made.length) { document.getElementById("accBox").insertAdjacentHTML("beforeend", '<p class="note">' + t("acc_done", { n: made.length }) + '</p><div class="actions"><button class="btn btn-main" type="button" data-act="acccsv">' + ic("download") + t("acc_csv") + "</button></div>"); } });
    })();
  });
  A.act("acccsv", function () { csvDownload(made); });
  A.act("oacct", function (el) {
    var id = el.getAttribute("data-id"), has = el.getAttribute("data-has") === "1";
    A.sheet(t("acc_one_title", { name: el.getAttribute("data-name") }), '<div style="display:grid;gap:1rem"><div><label for="accPw">' + t("acc_pw") + '</label><input id="accPw" type="password" autocomplete="current-password" dir="ltr"></div><div id="accErr" class="err"></div><div id="accOut"></div><div class="actions"><button class="btn btn-main" type="button" data-act="oacctgo" data-id="' + id + '">' + t(has ? "acc_reset_one" : "acc_make_one") + "</button></div></div>");
  });
  A.act("oacctgo", function (el) {
    var pw = document.getElementById("accPw").value, err = document.getElementById("accErr");
    if (!pw) { err.textContent = t("acc_need_pw"); return; }
    err.textContent = ""; el.disabled = true;
    A.api.write("POST", "children/" + el.getAttribute("data-id") + "/account", { confirm_password: pw }, { online: true }).then(function (r) {
      var d = r.data || r; made = [d]; document.getElementById("accOut").innerHTML = '<p class="note">' + t("acc_warn") + "</p>" + credTable([d]); el.remove(); A.api.wipeCache();
    }, function (e) { el.disabled = false; err.textContent = (e.errors && e.errors.confirm_password && e.errors.confirm_password[0]) || (e.offline ? t("need_conn") : A.msg(e)); });
  });
})();
