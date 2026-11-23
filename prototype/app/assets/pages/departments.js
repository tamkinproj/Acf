(function () {
  var A = ACF, t = A.t, ic = A.ic;
  A.strings({
    dp_title: "Departments", dp_hello: "Choose a department.", dp_aytam: "Orphans", dp_aytam_sub: "Orphan records, add an orphan, sign-in accounts", dp_edu: "Education", dp_edu_sub: "Alimni teachers, contracts, madrasa and markaz, curriculum", dp_att: "HR and attendance", dp_att_sub: "Clock in and out with a face photo, late and absent pay, payroll", dp_work: "Work tracker", dp_work_sub: "How staff use the app: active time, last seen, actions",
    dp_partner: "Partner", dp_partner_note: "Orphans, applications and education belong to one partner. Choose the partner to work in.", dp_shared: "Shared by all partners", dp_partner_dep: "Departments for {n}",
    pt_s_active: "Working", pt_s_paused: "Paused", pt_s_not_setup: "Not set up yet", pt_here: "Working in", pk_ro: "Paused · look only"
  }, {
    dp_title: "الأقسام", dp_hello: "اختر قسمًا.", dp_aytam: "الأيتام", dp_aytam_sub: "سجلات الأيتام وإضافة يتيم وحسابات الدخول", dp_edu: "التعليم", dp_edu_sub: "معلمو علّمني والعقود والمدرسة والمراكز والمنهج", dp_att: "الموارد البشرية والحضور", dp_att_sub: "تسجيل الحضور والانصراف بصورة الوجه، خصم التأخر والغياب، كشف الرواتب", dp_work: "متابعة العمل", dp_partner: "الشريك", dp_partner_note: "الأيتام والطلبات والتعليم تتبع شريكًا واحدًا. اختر الشريك الذي تعمل معه.", dp_shared: "مشتركة بين كل الشركاء", dp_partner_dep: "أقسام {n}",
    pt_s_active: "يعمل", pt_s_paused: "متوقف مؤقتًا", pt_s_not_setup: "لم يُجهّز بعد", pt_here: "تعمل معه", pk_ro: "متوقف مؤقتًا · للاطلاع فقط", dp_work_sub: "كيف يستخدم الموظفون التطبيق: وقت النشاط وآخر ظهور والإجراءات"
  });
  function tile(icon, href, title, sub) {
    return '<a class="fo" href="' + href + '"><span class="fo-top">' + ic(icon) + ic("chev", "fo-go") + "</span><b>" + t(title) + '</b><span class="fo-sub">' + t(sub) + "</span></a>";
  }
  var P = A.partners;
  function partnerCard(p, cur) {
    var on = cur && cur.code === p.code, look = p.status === "paused" && A.can("partners.manage"), usable = p.status === "active" || look;
    var badge = '<span class="badge ' + (p.status === "active" ? "" : p.status === "paused" ? "warn" : "grey") + '">' + t(p.status === "paused" ? (look ? "pk_ro" : "pt_s_paused") : on ? "pt_here" : "pt_s_" + p.status) + "</span>";
    var inner = P.logo(p, "pt-logo") + "<b>" + A.esc(P.name(p)) + "</b>" + badge;
    return usable ? '<button class="pt-card' + (on ? " on" : "") + '" type="button" data-act="pickpartner" data-code="' + A.esc(p.code) + '" aria-pressed="' + !!on + '">' + inner + "</button>"
      : '<div class="pt-card off' + (on ? " on" : "") + '" aria-disabled="true">' + inner + "</div>";
  }
  function render(d) {
    var list = d.list, cur = P.info(), paused = cur && cur.status === "paused", look = paused && A.can("partners.manage");
    var mine = [], shared = [];
    if (A.can("children.view") && (!paused || look)) mine.push(tile("users", "people.html", "dp_aytam", "dp_aytam_sub"));
    if (A.can("education.view") && (!paused || look)) mine.push(tile("book", "education.html", "dp_edu", "dp_edu_sub"));
    if (A.can("attendance.clock") || A.can("attendance.manage")) shared.push(tile("clock", "attendance.html", "dp_att", "dp_att_sub"));
    if (A.can("work.view")) shared.push(tile("pulse", "work.html", "dp_work", "dp_work_sub"));
    var out = '<div class="head"><h1 class="title">' + t("dp_title") + "</h1></div>";
    if (list && list.length) {
      out += '<h3 class="optlbl">' + t("dp_partner") + '</h3><p class="note" style="margin-top:0">' + t("dp_partner_note") + '</p><div class="pt-row">' + list.map(function (p) { return partnerCard(p, cur); }).join("") + "</div>";
    }
    if (paused) out += '<p class="banner warn">' + A.esc(t(look ? "pt_paused_pres" : "pt_paused_note", { n: P.name(cur) })) + "</p>";
    if (mine.length) out += '<h3 class="optlbl">' + A.esc(t("dp_partner_dep", { n: cur ? P.name(cur) : "" })) + '</h3><div class="fo-grid">' + mine.join("") + "</div>";
    if (shared.length) out += '<h3 class="optlbl" style="margin-top:1.4rem">' + t("dp_shared") + '</h3><div class="fo-grid">' + shared.join("") + "</div>";
    return out;
  }
  A.act("pickpartner", function (el) {
    var code = el.getAttribute("data-code"), list = (lastList || []);
    P.choose(list, code, true); A.paint();
  });
  var lastList = null;
  function load() {
    return P.refresh().then(function (list) { lastList = list; return { data: { list: list } }; }, function () { return { data: { list: null } }; });
  }
  A.start({ page: "departments", nav: "departments", title: "dp_title", shell: true, load: load, render: render });
})();
