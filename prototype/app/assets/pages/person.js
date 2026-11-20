(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, facts = A.facts;
  var id = parseInt((location.search.match(/[?&]id=(-?\d+)/) || [])[1], 10) || 0;

  A.strings({
    child_title: "Child profile", sec_personal: "Personal information", sec_mother: "Mother and family", sec_resp: "Direct responsible person", sec_docs: "Documents",
    sec_bank: "Bank details", sec_channel: "Channel and source", sec_hist: "Channel history",
    f_gender: "Gender", f_dob: "Date of birth", f_city: "City", f_health: "Health status", f_grade: "Grade", f_mother: "Mother name", f_mstatus: "Mother marital status",
    f_fdeath: "Father death date", f_bro: "Number of brothers", f_sis: "Number of sisters", f_guardian: "Guardian", f_rel: "Relationship", f_phone: "Phone",
    f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits", f_channel: "Channel", f_source: "Source", f_since: "Since", f_amount: "Monthly amount", f_paytype: "Payment type",
    male: "Male", female: "Female", good: "Good", needs_followup: "Needs follow-up", doc_on: "On file", doc_missing: "Missing", doc_upload: "Document upload opens in the next phase",
    verify: "Verified", direct: "Direct donor", partner: "Partner sponsorship", cash_only: "Cash payment", bank_pay: "Bank transfer", active: "Active", pending: "Pending", ended: "Ended",
    d_birth_certificate: "Birth certificate", d_father_death_certificate: "Father's death certificate", d_school_record: "School record", d_guardian_id: "Guardian ID",
    ver_on: "Bank account verified", ver_off: "Verification removed", waiting: "Waiting to send", wait_note: "This child was added offline. Details appear once it has been sent."
  }, {
    child_title: "ملف الطفل", sec_personal: "المعلومات الشخصية", sec_mother: "الأم والأسرة", sec_resp: "المسؤول المباشر", sec_docs: "المستندات",
    sec_bank: "البيانات البنكية", sec_channel: "القناة والمصدر", sec_hist: "سجل القناة",
    f_gender: "الجنس", f_dob: "تاريخ الميلاد", f_city: "المدينة", f_health: "الحالة الصحية", f_grade: "الصف", f_mother: "اسم الأم", f_mstatus: "الحالة الاجتماعية للأم",
    f_fdeath: "تاريخ وفاة الأب", f_bro: "عدد الإخوة", f_sis: "عدد الأخوات", f_guardian: "الوصي", f_rel: "صلة القرابة", f_phone: "الهاتف",
    f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام", f_channel: "القناة", f_source: "المصدر", f_since: "منذ", f_amount: "المبلغ الشهري", f_paytype: "طريقة الدفع",
    male: "ذكر", female: "أنثى", good: "جيدة", needs_followup: "تحتاج متابعة", doc_on: "موجود", doc_missing: "ناقص", doc_upload: "رفع المستندات يفتح في المرحلة التالية",
    verify: "موثّق", direct: "متبرع مباشر", partner: "كفالة عبر شريك", cash_only: "دفع نقدي", bank_pay: "حوالة بنكية", active: "نشط", pending: "معلّق", ended: "منتهٍ",
    d_birth_certificate: "شهادة الميلاد", d_father_death_certificate: "شهادة وفاة الأب", d_school_record: "السجل المدرسي", d_guardian_id: "هوية الوصي",
    ver_on: "تم توثيق الحساب البنكي", ver_off: "أُلغي التوثيق", waiting: "بانتظار الإرسال", wait_note: "أُضيف هذا الطفل بدون اتصال. تظهر التفاصيل بعد إرساله."
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
    var p = c.personal || {}, f = c.family || {}, g = c.guardian_detail, ch = c.channel, canVerify = A.can("bank.verify");
    var docs = A.can("documents.view") && c.documents ? '<div class="card"><h3>' + t("sec_docs") + '</h3><div class="list">' + c.documents.map(function (d) {
      return '<button class="item" type="button" data-act="docnote"><span class="grow"><b>' + t("d_" + d.type) + '</b></span><span class="badge ' + (d.on_file ? "" : "warn") + '">' + t(d.on_file ? "doc_on" : "doc_missing") + "</span></button>";
    }).join("") + "</div></div>" : "";
    var bank = c.bank ? '<div class="card"><h3>' + t("sec_bank") + "</h3>" + facts([[t("f_holder"), dash(c.bank.holder)], [t("f_bankname"), dash(c.bank.bank_name)], [t("f_last4"), "<span dir=ltr>•••• " + esc(c.bank.last4) + "</span>"]]) +
      (canVerify ? '<div class="setrow"><b>' + t("verify") + '</b><button class="switch" type="button" role="switch" aria-checked="' + !!c.bank.verified + '" aria-label="' + t("verify") + '" data-act="verify"></button></div>' :
        '<div class="setrow"><b>' + t("verify") + '</b><span class="badge ' + (c.bank.verified ? "" : "warn") + '">' + t(c.bank.verified ? "verify" : "notver") + "</span></div>") + "</div>" :
      '<div class="card"><h3>' + t("sec_bank") + '</h3><p class="note">' + t("cash_only") + "</p></div>";
    return back +
      '<div class="card" style="margin:1rem 0"><div class="phead"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><div style="flex:1"><h1 class="title" style="font-size:1.7rem;margin:0">' + esc(A.name(c)) + '</h1><span class="note">' + esc(c.ref) + '</span></div><span class="badge">' + t(c.status || "active") + "</span></div></div>" +
      '<div class="grid g2" style="align-items:start">' +
      '<div class="card"><h3>' + t("sec_personal") + "</h3>" + facts([[t("f_gender"), p.gender ? t(p.gender === "m" ? "male" : "female") : "-"], [t("f_dob"), dash(p.birth_date)], [t("f_city"), dash(p.city)], [t("f_health"), p.health_status ? t(p.health_status) : "-"], [t("f_grade"), dash(p.grade)]]) + "</div>" +
      '<div class="card"><h3>' + t("sec_mother") + "</h3>" + facts([[t("f_mother"), dash(f.mother_name)], [t("f_mstatus"), dash(f.mother_marital_status)], [t("f_fdeath"), dash(f.father_death_date)], [t("f_bro"), dash(f.brothers)], [t("f_sis"), dash(f.sisters)]]) + "</div>" +
      '<div class="card"><h3>' + t("sec_resp") + "</h3>" + (g ? facts([[t("f_guardian"), dash(g.name)], [t("f_rel"), dash(g.relation)], [t("f_phone"), "<span dir=ltr>" + dash(g.phone) + "</span>"]]) : '<p class="note">-</p>') + "</div>" + docs + bank +
      '<div class="card"><h3>' + t("sec_channel") + "</h3>" + facts([[t("f_channel"), ch ? t(ch.channel) : "-"], [t("f_source"), c.pay_type ? t(c.pay_type === "bank" ? "bank_pay" : "cash_only") : "-"], [t("f_since"), ch ? dash(ch.since) : "-"],
        [t("f_amount"), c.monthly_amount !== null && c.monthly_amount !== undefined ? A.money(c.monthly_amount) + " " + t("per_month") : "-"]]) +
      '<h3 style="margin-top:1rem;font-size:1rem">' + t("sec_hist") + "</h3>" + facts((c.channel_history || []).map(function (h) { return [esc(h.since), t(h.channel)]; })) + "</div></div>";
  }

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
    after: function (c) { if (c && c.ref) document.title = A.name(c) + " | " + t("brand"); }
  });
})();
