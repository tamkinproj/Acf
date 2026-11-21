(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, facts = A.facts;
  var id = parseInt((location.search.match(/[?&]id=(-?\d+)/) || [])[1], 10) || 0, current = null, editing = null;

  A.strings({
    child_title: "Orphan profile", p_edit_all: "Edit all", p_call: "Call guardian", p_age: "Age", p_years: "{n} years", p_grade: "Grade", f_health: "Health status", good: "Good", needs_followup: "Needs follow-up",
    p_added: "Added on", p_updated: "Last updated", p_submitted: "Form submitted", p_photos: "Photos uploaded", sec_record: "Record", sec_photos: "Photos", sec_docs: "Documents", sec_bank: "Bank account",
    p_saved: "Saved", p_conn: "Editing needs a connection.", p_samplebadge: "Sample", p_back: "Back", p_add_photo: "Add photo", p_remove_q: "Remove this photo?", p_removed: "Photo removed", p_photo_added: "Photo added",
    f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits", verify: "Verified", notver: "Not verified", ver_on: "Bank account verified", ver_off: "Verification removed", no_bank: "No bank account yet (cash).",
    doc_on: "On file", doc_missing: "Missing", doc_view: "View", doc_upload: "Upload", doc_replace: "Replace", doc_done: "Document saved", d_birth_certificate: "Birth certificate", d_father_death_certificate: "Father's death certificate", d_school_record: "School record", d_guardian_id: "Guardian ID",
    waiting: "Waiting to send", wait_note: "This orphan was added offline. Details appear once it has been sent.", active: "Active", pending: "Pending", ended: "Ended", p_edit_sec: "Edit: {s}", p_from_app: "Original application"
  }, {
    child_title: "ملف اليتيم", p_edit_all: "تعديل الكل", p_call: "اتصل بالوصي", p_age: "العمر", p_years: "{n} سنة", p_grade: "الصف", f_health: "الحالة الصحية", good: "جيدة", needs_followup: "تحتاج متابعة",
    p_added: "تاريخ الإضافة", p_updated: "آخر تحديث", p_submitted: "تاريخ تقديم النموذج", p_photos: "الصور المرفوعة", sec_record: "السجل", sec_photos: "الصور", sec_docs: "المستندات", sec_bank: "الحساب البنكي",
    p_saved: "تم الحفظ", p_conn: "التعديل يحتاج اتصالًا.", p_samplebadge: "تجريبي", p_back: "رجوع", p_add_photo: "أضف صورة", p_remove_q: "حذف هذه الصورة؟", p_removed: "تم حذف الصورة", p_photo_added: "تمت إضافة الصورة",
    f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام", verify: "موثّق", notver: "غير موثّق", ver_on: "تم توثيق الحساب البنكي", ver_off: "أُلغي التوثيق", no_bank: "لا يوجد حساب بنكي بعد (نقدًا).",
    doc_on: "موجود", doc_missing: "ناقص", doc_view: "عرض", doc_upload: "رفع", doc_replace: "استبدال", doc_done: "تم حفظ المستند", d_birth_certificate: "شهادة الميلاد", d_father_death_certificate: "شهادة وفاة الأب", d_school_record: "السجل المدرسي", d_guardian_id: "هوية الوصي",
    waiting: "بانتظار الإرسال", wait_note: "أُضيف هذا اليتيم بدون اتصال. تظهر التفاصيل بعد إرساله.", active: "نشط", pending: "معلّق", ended: "منتهٍ", p_edit_sec: "تعديل: {s}", p_from_app: "الطلب الأصلي"
  });

  function dash(v) { return v === null || v === undefined || v === "" ? "-" : esc(v); }
  function load() {
    if (id < 0) return A.api.get("children?per_page=100").then(function (r) { return { data: { _pending: true, item: r.data.filter(function (x) { return x.id === id; })[0] || null }, stale: r.stale }; });
    return A.api.get("children/" + id);
  }
  function bar(title) {   // the only bar on this page: back, the page name, and Edit all
    return '<div class="dp-bar"><a class="dp-back" href="people.html" data-act="goback" aria-label="' + t("p_back") + '">' + ic("back", "flip") + '</a><span class="dp-title">' + esc(title) + "</span>" +
      (current && A.can("children.update") ? '<button class="dp-act" type="button" data-act="editall" aria-label="' + t("p_edit_all") + '">' + ic("edit") + "</button>" : '<span class="dp-act ghost"></span>') + "</div>";
  }
  function badge(s) { return '<span class="badge ' + (s === "active" ? "" : s === "ended" ? "grey" : "warn") + '">' + t(s) + "</span>"; }
  function editBtn(act, g) { return A.can("children.update") ? '<button class="osec-edit" type="button" data-act="' + act + '"' + (g ? ' data-g="' + g + '"' : "") + ">" + ic("edit") + "<span>" + t("o_edit") + "</span></button>" : ""; }

  function render(c) {
    if (c._pending) { current = null; var it = c.item; return '<div class="dp">' + bar(t("child_title")) + '<section class="card dp-head"><div class="phead"><span class="avatar">' + esc(it ? A.name(it).charAt(0) : "?") + '</span><div class="grow"><h1 class="title">' + esc(it ? A.name(it) : "") + '</h1></div>' + '<span class="badge grey">' + t("waiting") + '</span></div><p class="note">' + t("wait_note") + "</p></section></div>"; }
    current = c; var d = c.details || {}, p = c.personal || {}, canEdit = A.can("children.update");
    var head = '<section class="card dp-head"><div class="phead"><span class="avatar dp-avatar" id="dpAvatar">' + esc(A.name(c).charAt(0)) + '</span><div class="grow"><h1 class="title">' + esc(A.name(c)) + "</h1>" +
      (c.name_ar && A.lang() !== "ar" ? '<div class="dp-ar" dir="rtl">' + esc(c.name_ar) + "</div>" : "") + '<span class="note" dir="ltr">' + esc(c.ref) + (p.city ? " · " + esc(p.city) : "") + "</span></div>" +
      '<div class="dp-badges">' + (c.is_sample ? '<span class="badge grey">' + t("p_samplebadge") + "</span>" : "") + badge(c.status || "active") + "</div></div>" +
      '<div class="pchips">' + (c.age !== null && c.age !== undefined ? '<span class="pchip"><small>' + t("p_age") + "</small><b>" + t("p_years", { n: c.age }) + "</b></span>" : "") +
      (p.grade ? '<span class="pchip"><small>' + t("p_grade") + "</small><b>" + esc(p.grade) + "</b></span>" : "") +
      (p.health_status ? '<span class="pchip ' + (p.health_status === "good" ? "" : "warn") + '"><small>' + t("f_health") + "</small><b>" + t(p.health_status) + "</b></span>" : "") + "</div>" +
      '<div class="dp-actions">' + (d.guardian_phone ? '<a class="btn btn-line" href="tel:' + esc(String(d.guardian_phone).replace(/[^0-9+]/g, "")) + '">' + ic("phone") + t("p_call") + "</a>" : "") +
      A.shareBtn("child", c.id) + (canEdit ? '<button class="btn btn-main" type="button" data-act="editall">' + ic("edit") + t("p_edit_all") + "</button>" : "") + "</div></section>";
    var bank = '<section class="card osec" id="sec-bank"><div class="osec-head"><h3>' + t("sec_bank") + "</h3>" + editBtn("editsec", "bank") + "</div>" + (c.bank ? facts([[t("f_holder"), dash(c.bank.holder)], [t("f_bankname"), dash(c.bank.bank_name)], [t("f_last4"), '<span dir="ltr">•••• ' + esc(c.bank.last4) + "</span>"]]) +
      (A.can("bank.verify") ? '<div class="setrow"><b>' + t("verify") + '</b><button class="switch" type="button" role="switch" aria-checked="' + !!c.bank.verified + '" aria-label="' + t("verify") + '" data-act="verify"></button></div>' : '<div class="setrow"><b>' + t("verify") + "</b>" + badge(c.bank.verified ? "active" : "pending").replace(t("active"), t("verify")).replace(t("pending"), t("notver")) + "</div>") : '<p class="note">' + t("no_bank") + "</p>") + "</section>";
    var photos = '<section class="card osec"><div class="osec-head"><h3>' + t("sec_photos") + '</h3></div><div class="photos" id="photos">' + (c.photos || []).map(function (pid) {
      return '<div class="ph-wrap"><button class="ph" type="button" data-act="openphoto" data-id="' + pid + '" aria-label="' + t("sec_photos") + '"></button>' + (canEdit ? '<button class="ph-x" type="button" data-act="rmphoto" data-id="' + pid + '" aria-label="' + t("p_remove_q") + '">&times;</button>' : "") + "</div>";
    }).join("") + (canEdit ? '<button class="ph ph-add" type="button" data-act="addphoto">' + ic("plus") + "<span>" + t("p_add_photo") + "</span></button>" : "") + '</div><input type="file" id="photoIn" accept="image/*" hidden></section>';
    var docs = A.can("documents.view") && c.documents ? '<section class="card osec"><div class="osec-head"><h3>' + t("sec_docs") + '</h3></div><div class="list">' + c.documents.map(function (doc) {
      return '<div class="item docrow"><span class="grow"><b>' + t("d_" + doc.type) + "</b>" + badge(doc.on_file ? "active" : "pending").replace(">" + t("active") + "<", ">" + t("doc_on") + "<").replace(">" + t("pending") + "<", ">" + t("doc_missing") + "<") + "</span>" +
        (doc.on_file ? '<button class="btn btn-line btn-sm" type="button" data-act="viewdoc" data-type="' + doc.type + '">' + t("doc_view") + "</button>" : "") +
        (A.can("documents.upload") ? '<button class="btn btn-line btn-sm" type="button" data-act="updoc" data-type="' + doc.type + '">' + t(doc.on_file ? "doc_replace" : "doc_upload") + "</button>" : "") + "</div>";
    }).join("") + '</div><input type="file" id="docIn" accept="image/*,application/pdf" hidden></section>' : "";
    var record = '<section class="card osec"><div class="osec-head"><h3>' + t("sec_record") + "</h3></div>" + facts([[t("p_added"), dash(c.created_at)], [t("p_updated"), dash(c.updated_at)], [t("p_submitted"), dash(c.form_submitted_at)], [t("p_photos"), String((c.photos || []).length)]]) + "</section>";
    return '<div class="dp">' + bar(A.name(c)) + head + '<div class="dp-grid">' + A.orphan.view(d, { edit: canEdit }) + bank + photos + docs + record + "</div></div>";
  }

  /* ---- editing: all at once, or one section ---- */
  function openEdit(group) {
    if (!current) return; editing = group || null;
    var g = group && A.orphan.schema.filter(function (x) { return x.id === group; })[0];
    A.sheet(g ? t("p_edit_sec", { s: A.orphan.lab(g) }) : t("p_edit_all"), '<form id="editForm" novalidate>' + A.orphan.formHtml(current, group) + A.formEnd() + "</form>");
  }
  A.act("editall", function () { openEdit(null); });
  A.act("editsec", function (el) { openEdit(el.getAttribute("data-g")); });
  A.form("editForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    var body = A.orphan.readBody(true);
    if (body.error) { document.getElementById(body.error[0] === "formErr" ? "formErr" : body.error[0] + "Err").textContent = body.error[1]; return; }
    var btn = document.querySelector("#editForm button[type=submit]"); btn.disabled = true;
    A.api.write("PATCH", "children/" + id, body, { online: true }).then(function () { A.closeSheet(); A.toast(t("p_saved")); A.reload(true); }, function (e) {
      btn.disabled = false;
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
        if (i === 0) { var av = document.getElementById("dpAvatar"); if (av) { av.style.backgroundImage = 'url("' + u + '")'; av.classList.add("has-photo"); } }
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
    after: function (c) { if (c && c.ref) { document.title = A.name(c) + " | " + t("brand"); loadPhotos(c); } }
  });
})();
