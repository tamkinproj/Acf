(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, facts = A.facts;
  var id = parseInt((location.search.match(/[?&]id=(-?\d+)/) || [])[1], 10) || 0, current = null, editing = null;

  A.strings({
    child_title: "Orphan profile", p_edit_all: "Edit all", p_call: "Call guardian", p_age: "Age", p_years: "{n} years", p_grade: "Grade", f_health: "Health status", good: "Good", needs_followup: "Needs follow-up",
    p_added: "Added on", p_updated: "Last updated", p_submitted: "Form submitted", p_photos: "Photos uploaded", sec_record: "Record", sec_photos: "Photos", sec_docs: "Documents", sec_bank: "Bank account",
    p_saved: "Saved", p_conn: "Editing needs a connection.", p_samplebadge: "Sample", p_back: "Back", p_add_photo: "Add photo", p_remove_q: "Remove this photo?", p_removed: "Photo removed", p_photo_added: "Photo added",
    f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits", verify: "Verified", notver: "Not verified", ver_on: "Bank account verified", ver_off: "Verification removed", no_bank: "No bank account yet (cash).",
    doc_on: "On file", doc_missing: "Missing", doc_view: "View", doc_upload: "Upload", doc_replace: "Replace", doc_done: "Document saved", d_birth_certificate: "Orphan's birth certificate", d_father_death_certificate: "Father's death certificate", d_school_record: "School certificate", d_guardian_id: "Direct responsible person's ID", d_case_study: "Case study",
    waiting: "Waiting to send", wait_note: "This orphan was added offline. Details appear once it has been sent.", active: "Active", pending: "Pending", ended: "Ended", p_edit_sec: "Edit: {s}", p_from_app: "Original application", p_account: "Sign-in account", p_noacct: "No account yet"
  }, {
    child_title: "ملف اليتيم", p_edit_all: "تعديل الكل", p_call: "اتصل بالوصي", p_age: "العمر", p_years: "{n} سنة", p_grade: "الصف", f_health: "الحالة الصحية", good: "جيدة", needs_followup: "تحتاج متابعة",
    p_added: "تاريخ الإضافة", p_updated: "آخر تحديث", p_submitted: "تاريخ تقديم النموذج", p_photos: "الصور المرفوعة", sec_record: "السجل", sec_photos: "الصور", sec_docs: "المستندات", sec_bank: "الحساب البنكي",
    p_saved: "تم الحفظ", p_conn: "التعديل يحتاج اتصالًا.", p_samplebadge: "تجريبي", p_back: "رجوع", p_add_photo: "أضف صورة", p_remove_q: "حذف هذه الصورة؟", p_removed: "تم حذف الصورة", p_photo_added: "تمت إضافة الصورة",
    f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام", verify: "موثّق", notver: "غير موثّق", ver_on: "تم توثيق الحساب البنكي", ver_off: "أُلغي التوثيق", no_bank: "لا يوجد حساب بنكي بعد (نقدًا).",
    doc_on: "موجود", doc_missing: "ناقص", doc_view: "عرض", doc_upload: "رفع", doc_replace: "استبدال", doc_done: "تم حفظ المستند", d_birth_certificate: "صورة شهادة ميلاد اليتيم", d_father_death_certificate: "صورة شهادة وفاة الاب", d_school_record: "صورة الشهادة الدراسية", d_guardian_id: "صورة هوية المسؤول المباشر", d_case_study: "صورة بحث الحالة",
    waiting: "بانتظار الإرسال", wait_note: "أُضيف هذا اليتيم بدون اتصال. تظهر التفاصيل بعد إرساله.", active: "نشط", pending: "معلّق", ended: "منتهٍ", p_edit_sec: "تعديل: {s}", p_from_app: "الطلب الأصلي", p_account: "حساب الدخول", p_noacct: "لا يوجد حساب بعد"
  });

  function dash(v) { return v === null || v === undefined || v === "" ? "-" : esc(v); }
  function load() {
    if (id < 0) return A.api.get("children?per_page=100").then(function (r) { return { data: { _pending: true, item: r.data.filter(function (x) { return x.id === id; })[0] || null }, stale: r.stale }; });
    return A.api.get("children/" + id);
  }
  var editing = null;   // null, "all", or one group id: the sections that are shown as fields on the page itself
  function bar(title) {   // the only bar on this page: back, the page name, and Edit all
    return '<div class="dp-bar"><a class="dp-back" href="people.html" data-act="goback" aria-label="' + t("p_back") + '">' + ic("back", "flip") + '</a><span class="dp-title">' + esc(title) + "</span>" +
      (current && A.can("children.update") && !editing ? '<button class="dp-act" type="button" data-act="editall" aria-label="' + t("p_edit_all") + '">' + ic("edit") + "</button>" : '<span class="dp-act ghost"></span>') + "</div>";
  }
  function badge(s) { return '<span class="badge ' + (s === "active" ? "" : s === "ended" ? "grey" : "warn") + '">' + t(s) + "</span>"; }
  /* the grouped list, like Settings: a title with its pencil, then rows (label on the start, value on the end) */
  function row(label, val, extra, tap) { return '<div class="ios-row no-tile dp-row' + (extra ? " " + extra : "") + (tap ? " dp-tap" : "") + '"' + (tap ? ' role="button" tabindex="0" ' + tap : "") + '><span class="ios-lbl">' + label + '</span><span class="ios-val">' + val + "</span></div>"; }
  function pen() { return ""; }   // no pencil on the cards: tap a value to change it
  function fieldDef(k) { var out = null; A.orphan.schema.forEach(function (g) { g.fields.forEach(function (f) { if (f.k === k) out = f; }); }); return out; }
  function inlineEditor(k, d) {   // one field turned into an input, right where its value was
    var ctl;
    if (k === "status") ctl = '<div><label for="f_status">' + t("f_status") + '</label><select id="f_status">' + ["active", "pending", "ended"].map(function (x) { return '<option value="' + x + '"' + ((current.status || "active") === x ? " selected" : "") + ">" + t(x) + "</option>"; }).join("") + '</select><div class="err" id="f_statusErr"></div></div>';
    else ctl = A.orphan.control(fieldDef(k), d[k]);
    return '<div class="dp-inrow">' + ctl + '<div class="dp-inbtns"><button class="btn btn-line btn-sm" type="button" data-act="cancelfield">' + t("cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="savefield">' + t("save") + "</button></div></div>";
  }
  function sec(title, inner, pencil, id) { return '<section class="dp-sec"' + (id ? ' id="' + id + '"' : "") + '><div class="ios-head"><h3>' + esc(title) + "</h3>" + (pencil || "") + "</div>" + inner + "</section>"; }
  function rows(list) { return '<div class="ios-group dp-rows">' + list.join("") + "</div>"; }
  function fields(g) { return '<div class="ios-group ios-edit">' + A.orphan.formHtml(current, g) + "</div>"; }

  function render(c) {
    if (c._pending) { current = null; var it = c.item; return '<div class="dp">' + bar(t("child_title")) + '<section class="card dp-head"><div class="phead"><span class="avatar">' + esc(it ? A.name(it).charAt(0) : "?") + '</span><div class="grow"><h1 class="title">' + esc(it ? A.name(it) : "") + '</h1></div>' + '<span class="badge grey">' + t("waiting") + '</span></div><p class="note">' + t("wait_note") + "</p></section></div>"; }
    current = c; var d = c.details || {}, p = c.personal || {}, canEdit = A.can("children.update");
    var avatar = canEdit ? '<button class="avatar dp-avatar" id="dpAvatar" type="button" data-act="addphoto" aria-label="' + t("p_add_photo") + '">' + esc(A.name(c).charAt(0)) + '<span class="dp-cam">' + ic("plus") + "</span></button>"
      : '<span class="avatar dp-avatar" id="dpAvatar">' + esc(A.name(c).charAt(0)) + "</span>";
    var head = '<section class="card dp-head"><div class="phead">' + avatar + '<div class="grow"><h1 class="title">' + esc(A.name(c)) + "</h1>" +
      (c.name_ar && A.lang() !== "ar" ? '<div class="dp-ar" dir="rtl">' + esc(c.name_ar) + "</div>" : "") + '<span class="note" dir="ltr">' + esc(c.ref) + (p.city ? " · " + esc(p.city) : "") + "</span>" + (c.is_sample ? ' <span class="badge grey">' + t("p_samplebadge") + "</span>" : "") + "</div>" +
      A.shareBtn("child", c.id, null, true) + "</div>" +
      '<div class="pchips">' + (c.age !== null && c.age !== undefined ? '<span class="pchip"><small>' + t("p_age") + "</small><b>" + t("p_years", { n: c.age }) + "</b></span>" : "") +
      (p.grade ? '<span class="pchip"><small>' + t("p_grade") + "</small><b>" + esc(p.grade) + "</b></span>" : "") +
      (p.health_status ? '<span class="pchip ' + (p.health_status === "good" ? "" : "warn") + '"><small>' + t("f_health") + "</small><b>" + t(p.health_status) + "</b></span>" : "") + "</div>" +
      (d.guardian_phone ? '<div class="dp-actions"><a class="btn btn-line" href="tel:' + esc(String(d.guardian_phone).replace(/[^0-9+]/g, "")) + '">' + ic("phone") + t("p_call") + "</a></div>" : "") +
      (canEdit ? '<input type="file" id="photoIn" accept="image/*" hidden>' : "") + "</section>";

    var groups = A.orphan.schema.filter(function (g) { return g.id !== "bank" && g.fields.length; }).map(function (g) {
      var ed = editing === "all" || editing === g.id, list = [];
      var one = function (k) { return canEdit && editing && editing.field === k; };
      if (g.id === "info") list.push(one("status") ? inlineEditor("status", d) : row(t("f_status"), esc(t(c.status || "active")), "", canEdit ? 'data-act="editfield" data-k="status"' : ""));
      g.fields.forEach(function (f) { list.push(one(f.k) ? inlineEditor(f.k, d) : row(esc(A.orphan.lab(f)), A.orphan.show(f, d[f.k]), "", canEdit ? 'data-act="editfield" data-k="' + f.k + '"' : "")); });
      return sec(A.orphan.lab(g), ed ? fields(g.id) : rows(list), ed ? "" : pen(g.id), "sec-" + g.id);
    }).join("");

    var bg = A.orphan.schema.filter(function (g) { return g.id === "bank"; })[0], bed = editing === "all" || editing === "bank";
    var bt = canEdit ? 'data-act="editsec" data-g="bank"' : "";
    var bankRows = c.bank ? [row(t("f_holder"), dash(c.bank.holder), "", bt), row(t("f_bankname"), dash(c.bank.bank_name), "", bt), row(t("f_last4"), '<span dir="ltr">•••• ' + esc(c.bank.last4) + "</span>", "", bt),
      '<div class="ios-row no-tile"><span class="ios-lbl">' + t("verify") + "</span>" + (A.can("bank.verify") ? '<button class="switch" type="button" role="switch" aria-checked="' + !!c.bank.verified + '" aria-label="' + t("verify") + '" data-act="verify"></button>' : '<span class="ios-val">' + t(c.bank.verified ? "verify" : "notver") + "</span>") + "</div>"] : [row(t("no_bank"), canEdit ? ic("plus") : "", "", bt)];
    var bank = sec(t("sec_bank"), bed ? fields("bank") : rows(bankRows), bed ? "" : pen("bank"), "sec-bank");

    var photos = sec(t("sec_photos"), '<div class="ios-group dp-pad"><div class="photos" id="photos">' + (c.photos || []).map(function (pid) {
      return '<div class="ph-wrap"><button class="ph" type="button" data-act="openphoto" data-id="' + pid + '" aria-label="' + t("sec_photos") + '"></button>' + (canEdit ? '<button class="ph-x" type="button" data-act="rmphoto" data-id="' + pid + '" aria-label="' + t("p_remove_q") + '">&times;</button>' : "") + "</div>";
    }).join("") + (canEdit ? '<button class="ph ph-add" type="button" data-act="addphoto">' + ic("plus") + "<span>" + t("p_add_photo") + "</span></button>" : "") + "</div></div>");

    var docs = A.can("documents.view") && c.documents ? sec(t("sec_docs"), '<div class="ios-group dp-rows">' + c.documents.map(function (doc) {
      return '<div class="ios-row no-tile dp-row docrow"><span class="ios-lbl">' + t("d_" + doc.type) + '</span><span class="ios-val">' + t(doc.on_file ? "doc_on" : "doc_missing") + '</span><span class="dp-btns">' +
        (doc.on_file ? '<button class="btn btn-line btn-sm" type="button" data-act="viewdoc" data-type="' + doc.type + '">' + t("doc_view") + "</button>" : "") +
        (A.can("documents.upload") ? '<button class="btn btn-line btn-sm" type="button" data-act="updoc" data-type="' + doc.type + '">' + t(doc.on_file ? "doc_replace" : "doc_upload") + "</button>" : "") + "</span></div>";
    }).join("") + '</div><input type="file" id="docIn" accept="image/*,application/pdf" hidden>') : "";

    var acct = A.can("orphans.accounts") ? sec(t("p_account"), '<div class="ios-group"><button class="ios-row no-tile" type="button" data-act="oacct" data-id="' + c.id + '" data-name="' + esc(A.name(c)) + '" data-has="' + (c.account ? 1 : 0) + '"><span class="ios-lbl">' +
      (c.account ? '<span dir="ltr">@' + esc(c.account) + "</span>" : t("p_noacct")) + '</span><span class="ios-val">' + t(c.account ? "acc_reset" : "acc_one") + "</span>" + ic("chev", "ios-chev") + "</button></div>") : "";

    var record = sec(t("sec_record"), rows([row(t("p_added"), dash(c.created_at)), row(t("p_updated"), dash(c.updated_at)), row(t("p_submitted"), dash(c.form_submitted_at ? A.dateTime(c.form_submitted_at) : null)), row(t("p_photos"), String((c.photos || []).length))]));
    var bar2 = typeof editing === "string" ? '<div class="err" id="formErr" role="alert"></div><div class="dp-savebar"><button class="btn btn-line" type="button" data-act="canceledit">' + t("cancel") + '</button><button class="btn btn-main" type="button" data-act="saveedit">' + t("save") + "</button></div>" : "";
    var report = A.report && A.report.here() ? A.report.card() : "";
    return '<div class="dp">' + bar(A.name(c)) + head + '<div class="dp-grid">' + groups + bank + photos + docs + report + acct + record + "</div>" + bar2 + "</div>";
  }

  /* ---- editing: right on the page, all sections or one ---- */
  A.act("editall", function () { if (current) { editing = "all"; A.paint(); } });
  A.act("editsec", function (el) { if (current) { editing = el.getAttribute("data-g"); A.paint(); } });
  A.act("canceledit", function () { editing = null; A.paint(); });
  A.act("editfield", function (el) { if (!current) return; editing = { field: el.getAttribute("data-k") }; A.paint(); });
  A.act("cancelfield", function () { editing = null; A.paint(); });
  function saveField(el) {
    var k = editing && editing.field; if (!k) return;
    Array.prototype.forEach.call(document.querySelectorAll(".dp .err"), function (e) { e.textContent = ""; });
    var body, d = (current && current.details) || {};
    if (k.indexOf("guardian_") === 0) {   // the guardian is saved as a whole: the other two answers stay as they were
      var v = (document.getElementById("f_" + k).value || "").trim(), gd = { name: d.guardian_name, relation: d.guardian_relation, phone: d.guardian_phone }; gd[k.slice(9)] = v;
      if (!gd.name) { if (k === "guardian_name") { document.getElementById("f_" + k + "Err").textContent = t("required"); return; } gd.name = "-"; }
      Object.keys(gd).forEach(function (x) { if (gd[x] === undefined || gd[x] === null || gd[x] === "") delete gd[x]; });
      body = { guardian: gd };
    } else {
      body = A.orphan.readBody(true);
      if (body.error) { var box = document.getElementById(body.error[0] + "Err"); if (box) box.textContent = body.error[1]; return; }
    }
    if (el) el.disabled = true;
    A.api.write("PATCH", "children/" + id, body, { online: true }).then(function () { editing = null; A.toast(t("p_saved")); A.reload(true); }, function (e) {
      if (el) el.disabled = false;
      var err = document.getElementById("f_" + k + "Err"); if (!err) return;
      if (e.offline) { err.textContent = t("p_conn"); return; }
      if (!(e.errors && A.showErrors(e.errors, "f_"))) err.textContent = A.msg(e);
    });
  }
  A.act("savefield", function (el) { saveField(el); });
  document.addEventListener("keydown", function (e) {
    if (!editing || !editing.field || !e.target || !e.target.closest || !e.target.closest(".dp-inrow")) return;
    if (e.key === "Escape") { editing = null; A.paint(); }
    else if (e.key === "Enter" && e.target.tagName !== "TEXTAREA") { e.preventDefault(); saveField(document.querySelector(".dp-inrow [data-act=savefield]")); }
  });
  A.act("saveedit", function (el) {
    Array.prototype.forEach.call(document.querySelectorAll(".dp .err"), function (e) { e.textContent = ""; });
    var body = A.orphan.readBody(true);
    if (body.error) { var box = document.getElementById(body.error[0] === "formErr" ? "formErr" : body.error[0] + "Err"); if (box) box.textContent = body.error[1]; var fld = document.getElementById(body.error[0]); if (fld && fld.scrollIntoView) fld.scrollIntoView({ block: "center" }); return; }
    el.disabled = true;
    A.api.write("PATCH", "children/" + id, body, { online: true }).then(function () { editing = null; A.toast(t("p_saved")); A.reload(true); }, function (e) {
      el.disabled = false;
      if (e.offline) { document.getElementById("formErr").textContent = t("p_conn"); return; }
      if (!(e.errors && A.showErrors(e.errors, "f_"))) document.getElementById("formErr").textContent = A.msg(e);
    });
  });
  A.act("goback", function (el, e) { if (document.referrer && document.referrer.indexOf(location.origin) === 0 && history.length > 1) { e.preventDefault(); history.back(); } });

  /* ---- photos ---- */
  function loadPhotos(c) {
    (c.photos || []).forEach(function (pid, i) {
      A.api.blobUrl("children/" + id + "/photos/" + pid).then(function (u) {
        var b = document.querySelector('.ph[data-id="' + pid + '"]'); if (b) { b.style.backgroundImage = 'url("' + u + '")'; b.setAttribute("data-url", u); }
        if (i === (c.photos || []).length - 1) { var av = document.getElementById("dpAvatar"); if (av) { av.style.backgroundImage = 'url("' + u + '")'; av.classList.add("has-photo"); } }
      }, function () {});
    });
  }
  A.act("openphoto", function (el) { var u = el.getAttribute("data-url"); if (u) window.open(u, "_blank", "noopener"); });
  A.act("addphoto", function () { var f = document.getElementById("photoIn"); f.onchange = function () { var file = f.files[0]; f.value = ""; if (!file) return; var fd = new FormData(); fd.append("photo", file); A.toast("…"); A.api.upload("children/" + id + "/photos", fd).then(function () { A.toast(t("p_photo_added")); A.reload(true); }, function (e) { A.toast(e.offline ? t("p_conn") : A.msg(e)); }); }; f.click(); });
  A.act("rmphoto", function (el) { if (!confirm(t("p_remove_q"))) return; A.api.write("DELETE", "children/" + id + "/photos/" + el.getAttribute("data-id"), undefined, { online: true }).then(function () { A.toast(t("p_removed")); A.reload(true); }, function (e) { A.toast(e.offline ? t("p_conn") : A.msg(e)); }); });

  /* ---- documents ---- */
  A.act("viewdoc", function (el) { var w = window.open("", "_blank"); A.api.blobUrl("children/" + id + "/documents/" + el.getAttribute("data-type") + "/download").then(function (u) { if (w) w.location = u; else location.href = u; }, function (e) { if (w) w.close(); A.toast(A.msg(e)); }); });
  A.act("updoc", function (el) {
    var type = el.getAttribute("data-type"), f = document.getElementById("docIn");
    f.onchange = function () { var file = f.files[0]; f.value = ""; if (!file) return; var fd = new FormData(); fd.append("type", type); fd.append("file", file); A.toast("…"); A.api.upload("children/" + id + "/documents", fd).then(function () { A.toast(t("doc_done")); A.reload(true); }, function (e) { A.toast(e.offline ? t("p_conn") : A.msg(e)); }); };
    f.click();
  });

  /* ---- bank verification ---- */
  A.act("verify", function (el) {
    var now = el.getAttribute("aria-checked") !== "true"; el.setAttribute("aria-checked", String(now));
    A.api.write("PUT", "children/" + id + "/bank/verification", { verified: now }, {
      label: t("verify") + ": #" + id,
      optimistic: function () {
        A.api.patchCache("children/" + id, function (p) { if (p.data && p.data.bank) { p.data.bank.verified = now; p.data.bank_verified = now; return p; } });
        A.api.patchCache("children?", function (p) { if (Array.isArray(p.data)) { p.data.forEach(function (c) { if (c.id === id) c.bank_verified = now; }); return p; } });
      }
    }).then(function (r) { A.toast(r.queued ? t("phone_saved") : t(now ? "ver_on" : "ver_off")); }, function (e) { el.setAttribute("aria-checked", String(!now)); A.toast(A.msg(e)); });
  });

  A.start({
    page: "person", nav: "people", title: "child_title", perm: "children.view", shell: true, bare: true, load: load, render: render,
    after: function (c) { if (c && c.ref) { document.title = A.name(c) + " | " + t("brand"); loadPhotos(c); if (A.report) A.report.mount(c.id); if (editing && editing.field) { var inp = document.getElementById("f_" + editing.field); if (inp && inp.focus) { inp.focus(); } } } }
  });
})();
