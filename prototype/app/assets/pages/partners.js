/* Partners (President Admin): Dar Al Ber Society works; the others are listed until their form is set up.
   A partner can only be paused and resumed (with your own password). There is no delete and no unlink: a pause stops the work, the records stay. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, P = A.partners, list = [];
  A.strings({
    pa_title: "Partners", pa_note: "Each partner has its own orphans, applications and education. A partner cannot be deleted or unlinked: pause it to stop all staff from using it, and resume it when work goes on. Its records stay.",
    pa_pause: "Pause", pa_resume: "Resume", pa_pause_q: "Pause {n}?", pa_resume_q: "Resume {n}?", pa_pause_txt: "All staff stop working in {n}: its orphans, applications and education cannot be opened or changed, and its application link shows \"closed for now\". You can still look at its records. Nothing is deleted.",
    pa_resume_txt: "Staff can work in {n} again, and its application link opens.", pa_pw: "Your own password", pa_done_p: "Partner paused", pa_done_r: "Partner resumed",
    pa_setup: "Not set up yet: its form is prepared first. Until then it cannot be used, paused or resumed.", pa_link: "Application link", pa_copy: "Copy", pa_copied: "Link copied",
    pa_since: "Paused since {d}", pt_s_active: "Working", pt_s_paused: "Paused", pt_s_not_setup: "Not set up yet",
    rf_title: "Periodic orphan report (PDF form)", rf_none: "Not added yet. Add the report form you received from Dar Al Ber: the system fills its fields for each orphan and changes nothing else in it.",
    rf_have: "Added {d} · {f} fields", rf_add: "Add the form", rf_replace: "Replace the form", rf_details: "Details on the reports", rf_org: "Organization name in Arabic (as written on the reports)",
    rf_country: "Country of the organization (two letters, PH = Philippines)", rf_saved: "Saved", rf_added: "Report form added", rf_checking: "Checking the form…", rf_on: "On the reports: {o} · {c}"
  }, {
    pa_title: "الشركاء", pa_note: "لكل شريك أيتامه وطلباته وتعليمه. لا يمكن حذف شريك أو فك ارتباطه: أوقفه مؤقتًا ليتوقف كل الموظفين عن استخدامه، واستأنفه عند عودة العمل. تبقى سجلاته كما هي.",
    pa_pause: "إيقاف مؤقت", pa_resume: "استئناف", pa_pause_q: "إيقاف {n} مؤقتًا؟", pa_resume_q: "استئناف {n}؟", pa_pause_txt: "يتوقف كل الموظفين عن العمل مع {n}: لا يمكن فتح أيتامه وطلباته وتعليمه أو تغييرها، ورابط التقديم يعرض «مغلق حاليًا». يمكنك الاطلاع على سجلاته. لا يُحذف شيء.",
    pa_resume_txt: "يعود الموظفون إلى العمل مع {n}، ويُفتح رابط التقديم.", pa_pw: "كلمة مرورك أنت", pa_done_p: "تم إيقاف الشريك مؤقتًا", pa_done_r: "تم استئناف الشريك",
    pa_setup: "لم يُجهّز بعد: يُعدّ نموذجه أولًا. حتى ذلك الحين لا يمكن استخدامه أو إيقافه أو استئنافه.", pa_link: "رابط التقديم", pa_copy: "نسخ", pa_copied: "تم نسخ الرابط",
    pa_since: "متوقف منذ {d}", pt_s_active: "يعمل", pt_s_paused: "متوقف مؤقتًا", pt_s_not_setup: "لم يُجهّز بعد",
    rf_title: "التقرير الدوري لليتيم (نموذج PDF)", rf_none: "لم يُضف بعد. أضف نموذج التقرير الذي وصلك من دار البر: يعبّئ النظام حقوله لكل يتيم ولا يغيّر فيه شيئًا آخر.",
    rf_have: "أُضيف {d} · {f} حقلًا", rf_add: "إضافة النموذج", rf_replace: "استبدال النموذج", rf_details: "البيانات في التقارير", rf_org: "اسم الهيئة بالعربية (كما يُكتب في التقارير)",
    rf_country: "دولة الهيئة (حرفان، PH = الفلبين)", rf_saved: "تم الحفظ", rf_added: "تمت إضافة نموذج التقرير", rf_checking: "جارٍ فحص النموذج…", rf_on: "في التقارير: {o} · {c}"
  });
  var RF = null;   // the report form of Dar Al Ber, once loaded
  function rfHtml() {
    if (!RF) return '<p class="note">…</p>';
    return '<b>' + t("rf_title") + "</b>" + (RF.available ? '<span class="note">' + esc(t("rf_have", { d: A.dateTime(RF.current.uploaded_at), f: RF.current.fields })) + "</span>" : '<span class="note">' + t("rf_none") + "</span>") +
      '<span class="note">' + esc(t("rf_on", { o: "\u0001", c: RF.country })).replace("\u0001", '<bdi dir="rtl">' + esc(RF.org_name) + "</bdi>") + "</span>" +
      '<div class="pt-actions"><button class="btn ' + (RF.available ? "btn-line" : "btn-main") + ' btn-sm" type="button" data-act="rfupload">' + t(RF.available ? "rf_replace" : "rf_add") + '</button><button class="btn btn-line btn-sm" type="button" data-act="rfdetails">' + t("rf_details") + "</button></div>" +
      '<input type="file" id="rfIn" accept="application/pdf,.pdf" hidden>';
  }
  function rfPaint() { var b = document.getElementById("rfBox"); if (b) b.innerHTML = rfHtml(); }
  function rfLoad() { if (!document.getElementById("rfBox")) return; A.api.get("partners/dar_al_ber/report-form", { cache: false }).then(function (r) { RF = r.data; rfPaint(); }, function (e) { var b = document.getElementById("rfBox"); if (b) b.innerHTML = '<span class="note">' + esc(A.msg(e)) + "</span>"; }); }
  A.act("rfupload", function () {
    var f = document.getElementById("rfIn");
    f.onchange = function () {
      var file = f.files[0]; f.value = ""; if (!file) return;
      var fd = new FormData(); fd.append("file", file); A.toast(t("rf_checking"));
      A.api.upload("partners/dar_al_ber/report-form", fd).then(function (d) { RF = d; rfPaint(); A.toast(t("rf_added")); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); });
    };
    f.click();
  });
  A.act("rfdetails", function () {
    A.sheet(t("rf_details"), '<form id="rfForm" novalidate>' + A.field("rf_org_name", t("rf_org"), "", "text", 'dir="rtl" maxlength="150" value="' + esc(RF ? RF.org_name : "") + '"') +
      A.field("rf_country", t("rf_country"), "", "text", 'dir="ltr" maxlength="2" autocapitalize="characters" value="' + esc(RF ? RF.country : "PH") + '"') + A.formEnd("save") + "</form>");
  });
  A.form("rfForm", function () {
    var o = document.getElementById("rf_org_name").value.trim(), c = document.getElementById("rf_country").value.trim().toUpperCase();
    ["rf_org_nameErr", "rf_countryErr", "formErr"].forEach(function (k) { document.getElementById(k).textContent = ""; });
    if (!o) { document.getElementById("rf_org_nameErr").textContent = t("required"); return; }
    if (!/^[A-Z]{2}$/.test(c)) { document.getElementById("rf_countryErr").textContent = t("rf_country"); return; }
    A.api.write("PUT", "partners/dar_al_ber/report-form", { org_name: o, country: c }, { online: true }).then(function (r) { RF = r.data; A.closeSheet(); rfPaint(); A.toast(t("rf_saved")); }, function (e) {
      if (!(e.errors && A.showErrors(e.errors, "rf_"))) document.getElementById("formErr").textContent = e.offline ? t("need_conn") : A.msg(e);
    });
  });
  function applyLink(p) { return location.origin + "/apply?partner=" + encodeURIComponent(p.code); }
  function card(p) {
    var badge = '<span class="badge ' + (p.status === "active" ? "" : p.status === "paused" ? "warn" : "grey") + '">' + t("pt_s_" + p.status) + "</span>", act = "";
    if (p.status === "active") act = '<button class="btn btn-line btn-sm" type="button" data-act="ptchange" data-code="' + esc(p.code) + '" data-to="pause">' + t("pa_pause") + "</button>";
    else if (p.status === "paused") act = '<button class="btn btn-main btn-sm" type="button" data-act="ptchange" data-code="' + esc(p.code) + '" data-to="resume">' + t("pa_resume") + "</button>";
    return '<div class="pt-card' + (p.status === "not_setup" ? " off" : "") + '" data-code="' + esc(p.code) + '">' + P.logo(p, "pt-logo") + "<b>" + esc(P.name(p)) + "</b>" +
      (p.name_ar && A.lang() !== "ar" ? '<span class="note" dir="rtl" style="margin:0">' + esc(p.name_ar) + "</span>" : "") + badge +
      (p.status === "paused" && p.paused_at ? '<span class="note" style="margin:0">' + esc(t("pa_since", { d: A.dateTime(p.paused_at) })) + "</span>" : "") +
      (p.status === "not_setup" ? '<span class="note" style="margin:0">' + t("pa_setup") + "</span>" : "") +
      (p.status === "active" ? '<label class="pt-link note" style="margin:0">' + t("pa_link") + '<input type="text" readonly dir="ltr" value="' + esc(applyLink(p)) + '"></label>' : "") +
      '<div class="pt-actions">' + act + (p.status === "active" ? '<button class="btn btn-line btn-sm" type="button" data-act="ptcopy" data-code="' + esc(p.code) + '">' + t("pa_copy") + "</button>" : "") + "</div>" +
      (p.code === "dar_al_ber" && p.status !== "not_setup" ? '<div class="rf" id="rfBox">' + rfHtml() + "</div>" : "") + "</div>";
  }
  function render(d) {
    list = d;
    return '<div class="head"><h1 class="title">' + t("pa_title") + '</h1></div><p class="note">' + t("pa_note") + '</p><div class="pt-row">' + d.map(card).join("") + "</div>";
  }
  function find(code) { return list.filter(function (p) { return p.code === code; })[0]; }
  A.act("ptcopy", function (el) {
    var v = applyLink(find(el.getAttribute("data-code")));
    (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { A.toast(t("pa_copied")); }, function () { var i = el.closest(".pt-card").querySelector("input"); if (i) { i.select(); } });
  });
  A.act("ptchange", function (el) {
    var p = find(el.getAttribute("data-code")), to = el.getAttribute("data-to"), n = P.name(p);
    A.sheet(t(to === "pause" ? "pa_pause_q" : "pa_resume_q", { n: n }), '<form id="ptForm" novalidate data-code="' + esc(p.code) + '" data-to="' + to + '"><p class="note" style="margin-top:0">' + esc(t(to === "pause" ? "pa_pause_txt" : "pa_resume_txt", { n: n })) + "</p>" +
      A.field("pt_admin_password", t("pa_pw"), "", "password", 'dir="ltr" autocomplete="current-password"') + '<div class="err" id="formErr"></div><div class="actions"><button class="btn ' + (to === "pause" ? "btn-line" : "btn-main") + '" type="submit">' + t(to === "pause" ? "pa_pause" : "pa_resume") + "</button></div></form>");
  });
  A.form("ptForm", function () {
    var f = document.getElementById("ptForm"), code = f.getAttribute("data-code"), to = f.getAttribute("data-to"), pw = document.getElementById("pt_admin_password").value;
    document.getElementById("formErr").textContent = ""; document.getElementById("pt_admin_passwordErr").textContent = "";
    if (!pw) { document.getElementById("pt_admin_passwordErr").textContent = t("required"); return; }
    var btn = f.querySelector("button[type=submit]"); btn.disabled = true;
    A.api.write("POST", "partners/" + code + "/" + to, { admin_password: pw }, { online: true }).then(function (r) {
      A.closeSheet(); A.toast(t(to === "pause" ? "pa_done_p" : "pa_done_r")); P.choose(r.data || [], A.api.partner()); A.reload(true);
    }, function (e) {
      btn.disabled = false;
      if (!(e.errors && A.showErrors(e.errors, "pt_"))) document.getElementById("formErr").textContent = e.offline ? t("need_conn") : A.msg(e);
    });
  });
  A.start({ page: "partners", nav: "partners", title: "pa_title", perm: "partners.manage", needsConn: true, shell: true, load: function () { return P.refresh().then(function (l) { return { data: l }; }); }, render: render, after: rfLoad });
})();
