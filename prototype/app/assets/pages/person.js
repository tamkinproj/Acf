(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, facts = A.facts;
  var id = parseInt((location.search.match(/[?&]id=(-?\d+)/) || [])[1], 10) || 0;

  A.strings({
    child_title: "Orphan profile", p_edit: "Edit profile", p_call: "Call guardian", p_age: "Age", p_years: "{n} years", p_grade: "Grade", p_added: "Added on", p_updated: "Last updated", sec_record: "Record", p_saved: "Profile saved", p_conn: "Editing needs a connection.", sec_quick: "At a glance", sec_photos: "Photos", p_submitted: "Form submitted", p_photos: "Photos uploaded", p_samplebadge: "Sample", sec_personal: "Personal information", sec_mother: "Mother and family", sec_resp: "Direct responsible person", sec_docs: "Documents",
    sec_bank: "Bank details", sec_channel: "Channel and source", sec_hist: "Channel history",
    f_gender: "Gender", f_dob: "Date of birth", f_city: "City", f_health: "Health status", f_grade: "Grade", f_mother: "Mother name", f_mstatus: "Mother marital status",
    f_fdeath: "Father death date", f_bro: "Number of brothers", f_sis: "Number of sisters", f_guardian: "Guardian", f_rel: "Relationship", f_phone: "Phone",
    f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits", f_channel: "Channel", f_source: "Source", f_since: "Since", f_amount: "Monthly amount", f_paytype: "Payment type",
    male: "Male", female: "Female", good: "Good", needs_followup: "Needs follow-up", doc_on: "On file", doc_missing: "Missing", doc_upload: "Document upload opens in the next phase",
    verify: "Verified", direct: "Direct donor", partner: "Partner sponsorship", cash_only: "Cash payment", bank_pay: "Bank transfer", active: "Active", pending: "Pending", ended: "Ended",
    d_birth_certificate: "Birth certificate", d_father_death_certificate: "Father's death certificate", d_school_record: "School record", d_guardian_id: "Guardian ID",
    notver: "Not verified", ver_on: "Bank account verified", ver_off: "Verification removed", waiting: "Waiting to send", wait_note: "This child was added offline. Details appear once it has been sent."
  }, {
    child_title: "ملف اليتيم", p_edit: "تعديل الملف", p_call: "اتصل بالوصي", p_age: "العمر", p_years: "{n} سنة", p_grade: "الصف", p_added: "تاريخ الإضافة", p_updated: "آخر تحديث", sec_record: "السجل", p_saved: "تم حفظ الملف", p_conn: "التعديل يحتاج اتصالًا.", sec_quick: "نظرة سريعة", sec_photos: "الصور", p_submitted: "تاريخ تقديم النموذج", p_photos: "الصور المرفوعة", p_samplebadge: "تجريبي", sec_personal: "المعلومات الشخصية", sec_mother: "الأم والأسرة", sec_resp: "المسؤول المباشر", sec_docs: "المستندات",
    sec_bank: "البيانات البنكية", sec_channel: "القناة والمصدر", sec_hist: "سجل القناة",
    f_gender: "الجنس", f_dob: "تاريخ الميلاد", f_city: "المدينة", f_health: "الحالة الصحية", f_grade: "الصف", f_mother: "اسم الأم", f_mstatus: "الحالة الاجتماعية للأم",
    f_fdeath: "تاريخ وفاة الأب", f_bro: "عدد الإخوة", f_sis: "عدد الأخوات", f_guardian: "الوصي", f_rel: "صلة القرابة", f_phone: "الهاتف",
    f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام", f_channel: "القناة", f_source: "المصدر", f_since: "منذ", f_amount: "المبلغ الشهري", f_paytype: "طريقة الدفع",
    male: "ذكر", female: "أنثى", good: "جيدة", needs_followup: "تحتاج متابعة", doc_on: "موجود", doc_missing: "ناقص", doc_upload: "رفع المستندات يفتح في المرحلة التالية",
    verify: "موثّق", direct: "متبرع مباشر", partner: "كفالة عبر شريك", cash_only: "دفع نقدي", bank_pay: "حوالة بنكية", active: "نشط", pending: "معلّق", ended: "منتهٍ",
    d_birth_certificate: "شهادة الميلاد", d_father_death_certificate: "شهادة وفاة الأب", d_school_record: "السجل المدرسي", d_guardian_id: "هوية الوصي",
    notver: "غير موثّق", ver_on: "تم توثيق الحساب البنكي", ver_off: "أُلغي التوثيق", waiting: "بانتظار الإرسال", wait_note: "أُضيف هذا الطفل بدون اتصال. تظهر التفاصيل بعد إرساله."
  });

  function dash(v) { return v === null || v === undefined || v === "" ? "-" : esc(v); }
  function load() {
    if (id < 0) { // added offline and not sent yet: only what was typed is known
      return A.api.get("children?per_page=100").then(function (r) {
        var c = r.data.filter(function (x) { return x.id === id; })[0];
        return { data: { _pending: true, item: c || null }, stale: r.stale };
      });
    }
    return A.api.get("children/" + id);
  }
  function render(c) {
    var back = '<a class="btn btn-line btn-sm" href="people.html">' + ic("back", "flip") + t("back") + "</a>";
    if (c._pending) {
      var it = c.item;
      return back + '<div class="card" style="margin:1rem 0"><div class="phead"><span class="avatar">' + esc(it ? A.name(it).charAt(0) : "?") + '</span><div style="flex:1"><h1 class="title" style="font-size:1.7rem;margin:0">' + esc(it ? A.name(it) : "") + '</h1></div><span class="badge grey">' + t("waiting") + '</span></div><p class="note">' + t("wait_note") + "</p></div>";
    }
    var d = c.details || {}, p = c.personal || {}, g = { name: d.guardian_name, phone: d.guardian_phone }, canVerify = A.can("bank.verify");
    var photos = (c.photos || []).length ? '<div class="card"><h3>' + t("sec_photos") + '</h3><div class="photos" id="photos">' + c.photos.map(function (pid) { return '<button class="ph" type="button" data-act="openphoto" data-id="' + pid + '" aria-label="' + t("sec_photos") + '"></button>'; }).join("") + "</div></div>" : "";
    var docs = A.can("documents.view") && c.documents ? '<div class="card"><h3>' + t("sec_docs") + '</h3><div class="list">' + c.documents.map(function (d) {
      return '<button class="item" type="button" data-act="docnote"><span class="grow"><b>' + t("d_" + d.type) + '</b></span><span class="badge ' + (d.on_file ? "" : "warn") + '">' + t(d.on_file ? "doc_on" : "doc_missing") + "</span></button>";
    }).join("") + "</div></div>" : "";
    var bank = c.bank ? '<div class="card"><h3>' + t("sec_bank") + "</h3>" + facts([[t("f_holder"), dash(c.bank.holder)], [t("f_bankname"), dash(c.bank.bank_name)], [t("f_last4"), "<span dir=ltr>•••• " + esc(c.bank.last4) + "</span>"]]) +
      (canVerify ? '<div class="setrow"><b>' + t("verify") + '</b><button class="switch" type="button" role="switch" aria-checked="' + !!c.bank.verified + '" aria-label="' + t("verify") + '" data-act="verify"></button></div>' :
        '<div class="setrow"><b>' + t("verify") + '</b><span class="badge ' + (c.bank.verified ? "" : "warn") + '">' + t(c.bank.verified ? "verify" : "notver") + "</span></div>") + "</div>" :
      '<div class="card"><h3>' + t("sec_bank") + '</h3><p class="note">' + t("cash_only") + "</p></div>";
    return back +
      '<div class="card profile" style="margin:1rem 0"><div class="phead"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><div style="flex:1;min-width:0"><h1 class="title" style="font-size:1.6rem;margin:0">' + esc(A.name(c)) + "</h1>" +
        (c.name_ar && A.lang() !== "ar" ? '<div class="note" dir="rtl" style="margin:.1rem 0 0;font-size:1rem;text-align:left">' + esc(c.name_ar) + "</div>" : "") +
        '<span class="note" dir="ltr" style="display:block">' + esc(c.ref) + (c.personal && c.personal.city ? " · " + esc(c.personal.city) : "") + '</span></div>' + (c.is_sample ? '<span class="badge grey">' + t("p_samplebadge") + "</span>" : "") + '<span class="badge ' + ((c.status || "active") === "active" ? "" : c.status === "ended" ? "grey" : "warn") + '">' + t(c.status || "active") + "</span></div>" +
        '<div class="pchips">' + (c.age !== null && c.age !== undefined ? '<span class="pchip"><small>' + t("p_age") + "</small><b>" + t("p_years", { n: c.age }) + "</b></span>" : "") +
        (p.grade ? '<span class="pchip"><small>' + t("p_grade") + "</small><b>" + esc(p.grade) + "</b></span>" : "") +
        (p.health_status ? '<span class="pchip ' + (p.health_status === "good" ? "" : "warn") + '"><small>' + t("f_health") + "</small><b>" + t(p.health_status) + "</b></span>" : "") + "</div>" +
        '<div class="actions" style="margin-top:1rem">' + (A.can("children.update") ? '<button class="btn btn-main" type="button" data-act="editprofile">' + ic("edit") + t("p_edit") + "</button>" : "") +
        (g && g.phone ? '<a class="btn btn-line" href="tel:' + esc(String(g.phone).replace(/[^0-9+]/g, "")) + '">' + ic("phone") + t("p_call") + "</a>" : "") + "</div></div>" +
      '<div class="grid g2" style="align-items:start">' + A.orphan.view(d) + bank + docs + photos +
      '<div class="card"><h3>' + t("sec_record") + "</h3>" + facts([[t("p_added"), dash(c.created_at)], [t("p_updated"), dash(c.updated_at)], [t("p_submitted"), dash(c.form_submitted_at)], [t("p_photos"), String((c.photos || []).length)]]) + "</div></div>";
  }

  var current = null;
  A.act("editprofile", function () {
    if (!current) return;
    A.sheet(t("p_edit"), '<form id="editForm" novalidate>' + A.orphan.formHtml(current) + A.formEnd() + "</form>");
  });
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
  A.act("openphoto", function (el) { var u = el.getAttribute("data-url"); if (u) window.open(u, "_blank", "noopener"); });
  A.act("docnote", function () { A.toast(t("doc_upload")); });
  A.act("verify", function (el) {
    var now = el.getAttribute("aria-checked") !== "true";
    el.setAttribute("aria-checked", String(now));
    A.api.write("PUT", "children/" + id + "/bank/verification", { verified: now }, {
      label: t("verify") + ": #" + id,
      optimistic: function () {
        A.api.patchCache("children/" + id, function (p) { if (p.data && p.data.bank) { p.data.bank.verified = now; p.data.bank_verified = now; return p; } });
        A.api.patchCache("children?", function (p) { if (Array.isArray(p.data)) { p.data.forEach(function (c) { if (c.id === id) c.bank_verified = now; }); return p; } });
      }
    }).then(function (r) { A.toast(r.queued ? t("phone_saved") : t(now ? "ver_on" : "ver_off")); }, function (e) { el.setAttribute("aria-checked", String(!now)); A.toast(A.msg(e)); });
  });

  A.start({
    page: "person", nav: "people", title: "child_title", perm: "children.view", shell: true, load: load, render: render,
    after: function (c) { if (c && c.ref) current = c;
      if (c && c.photos && c.photos.length && A.api.blobUrl) c.photos.forEach(function (pid) { A.api.blobUrl("children/" + id + "/photos/" + pid).then(function (u) { var b = document.querySelector('.ph[data-id="' + pid + '"]'); if (b) { b.style.backgroundImage = 'url("' + u + '")'; b.setAttribute("data-url", u); } }, function () {}); }); if (c && c.ref) document.title = A.name(c) + " | " + t("brand"); }
  });
})();
