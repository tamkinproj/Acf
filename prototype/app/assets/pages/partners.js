/* Partners (President Admin): Dar Al Ber Society works; the others are listed until their form is set up.
   A partner can only be paused and resumed (with your own password). There is no delete and no unlink: a pause stops the work, the records stay. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, P = A.partners, list = [];
  A.strings({
    pa_title: "Partners", pa_note: "Each partner has its own orphans, applications and education. A partner cannot be deleted or unlinked: pause it to stop all staff from using it, and resume it when work goes on. Its records stay.",
    pa_pause: "Pause", pa_resume: "Resume", pa_pause_q: "Pause {n}?", pa_resume_q: "Resume {n}?", pa_pause_txt: "All staff stop working in {n}: its orphans, applications and education cannot be opened or changed, and its application link shows \"closed for now\". You can still look at its records. Nothing is deleted.",
    pa_resume_txt: "Staff can work in {n} again, and its application link opens.", pa_pw: "Your own password", pa_done_p: "Partner paused", pa_done_r: "Partner resumed",
    pa_setup: "Not set up yet: its form is prepared first. Until then it cannot be used, paused or resumed.", pa_link: "Application link", pa_copy: "Copy", pa_copied: "Link copied",
    pa_since: "Paused since {d}", pt_s_active: "Working", pt_s_paused: "Paused", pt_s_not_setup: "Not set up yet"
  }, {
    pa_title: "الشركاء", pa_note: "لكل شريك أيتامه وطلباته وتعليمه. لا يمكن حذف شريك أو فك ارتباطه: أوقفه مؤقتًا ليتوقف كل الموظفين عن استخدامه، واستأنفه عند عودة العمل. تبقى سجلاته كما هي.",
    pa_pause: "إيقاف مؤقت", pa_resume: "استئناف", pa_pause_q: "إيقاف {n} مؤقتًا؟", pa_resume_q: "استئناف {n}؟", pa_pause_txt: "يتوقف كل الموظفين عن العمل مع {n}: لا يمكن فتح أيتامه وطلباته وتعليمه أو تغييرها، ورابط التقديم يعرض «مغلق حاليًا». يمكنك الاطلاع على سجلاته. لا يُحذف شيء.",
    pa_resume_txt: "يعود الموظفون إلى العمل مع {n}، ويُفتح رابط التقديم.", pa_pw: "كلمة مرورك أنت", pa_done_p: "تم إيقاف الشريك مؤقتًا", pa_done_r: "تم استئناف الشريك",
    pa_setup: "لم يُجهّز بعد: يُعدّ نموذجه أولًا. حتى ذلك الحين لا يمكن استخدامه أو إيقافه أو استئنافه.", pa_link: "رابط التقديم", pa_copy: "نسخ", pa_copied: "تم نسخ الرابط",
    pa_since: "متوقف منذ {d}", pt_s_active: "يعمل", pt_s_paused: "متوقف مؤقتًا", pt_s_not_setup: "لم يُجهّز بعد"
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
      '<div class="pt-actions">' + act + (p.status === "active" ? '<button class="btn btn-line btn-sm" type="button" data-act="ptcopy" data-code="' + esc(p.code) + '">' + t("pa_copy") + "</button>" : "") + "</div></div>";
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
  A.start({ page: "partners", nav: "partners", title: "pa_title", perm: "partners.manage", needsConn: true, shell: true, load: function () { return P.refresh().then(function (l) { return { data: l }; }); }, render: render });
})();
