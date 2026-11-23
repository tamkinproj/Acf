/* A partner's own page: tapping a partner on Departments opens this, with the departments that belong to it. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, P = A.partners;
  var CODE = (location.search.match(/[?&]p=([a-z0-9_]{2,40})/) || [])[1] || "dar_al_ber";
  A.strings({
    dp_aytam: "Orphans", dp_aytam_sub: "Orphan records, add an orphan, sign-in accounts", dp_edu: "Education", dp_edu_sub: "Alimni teachers, contracts, madrasa and markaz, curriculum",
    pp_back: "Departments", pp_deps: "Departments", pp_none: "This partner is not set up yet.", pp_none_sub: "Its departments will appear here when its form is ready.", pp_paused: "This partner is paused. Its departments cannot be used until the President Admin resumes it.",
    pp_look: "This partner is paused. You can look, but nothing can be changed until you resume it.", pp_s_active: "Working", pp_s_paused: "Paused", pp_s_not_setup: "Not set up yet", pp_gone: "This partner is not known."
  }, {
    dp_aytam: "الأيتام", dp_aytam_sub: "سجلات الأيتام وإضافة يتيم وحسابات الدخول", dp_edu: "التعليم", dp_edu_sub: "معلمو علّمني والعقود والمدرسة والمراكز والمنهج",
    pp_back: "الأقسام", pp_deps: "الأقسام", pp_none: "هذا الشريك لم يُجهّز بعد.", pp_none_sub: "ستظهر أقسامه هنا عندما يجهز نموذجه.", pp_paused: "هذا الشريك متوقف مؤقتًا. لا يمكن استخدام أقسامه حتى يستأنفه رئيس الإدارة.",
    pp_look: "هذا الشريك متوقف مؤقتًا. يمكنك الاطلاع فقط، ولا يمكن تغيير شيء حتى تستأنفه.", pp_s_active: "يعمل", pp_s_paused: "متوقف مؤقتًا", pp_s_not_setup: "لم يُجهّز بعد", pp_gone: "هذا الشريك غير معروف."
  });
  function tile(icon, href, title, sub) {
    return '<a class="fo" href="' + href + '"><span class="fo-top">' + ic(icon) + ic("chev", "fo-go") + "</span><b>" + t(title) + '</b><span class="fo-sub">' + t(sub) + "</span></a>";
  }
  function render(list) {
    var p = list.filter(function (x) { return x.code === CODE; })[0];
    var back = '<a class="btn btn-line btn-sm" href="departments.html">' + ic("back", "flip") + t("pp_back") + "</a>";
    if (!p) return '<div class="head">' + back + '</div><p class="note">' + t("pp_gone") + "</p>";
    var look = p.status === "paused" && A.can("partners.manage"), usable = p.status === "active" || look;
    var badge = '<span class="badge ' + (p.status === "active" ? "" : p.status === "paused" ? "warn" : "grey") + '">' + t("pp_s_" + p.status) + "</span>";
    var head = '<div class="head">' + back + '</div><div class="pp-head">' + P.logo(p, "pt-logo pp-logo") + '<div><h1 class="title" style="margin:0">' + esc(P.name(p)) + "</h1>" + badge + "</div></div>";
    if (p.status === "not_setup") return head + '<div class="card"><b>' + t("pp_none") + '</b><p class="note" style="margin-bottom:0">' + t("pp_none_sub") + "</p></div>";
    if (!usable) return head + '<p class="banner warn">' + t("pp_paused") + "</p>";
    var tiles = [];
    if (A.can("children.view")) tiles.push(tile("users", "people.html", "dp_aytam", "dp_aytam_sub"));
    if (A.can("education.view")) tiles.push(tile("book", "education.html", "dp_edu", "dp_edu_sub"));
    return head + (look ? '<p class="banner warn">' + t("pp_look") + "</p>" : "") + '<h3 class="optlbl">' + t("pp_deps") + '</h3><div class="fo-grid">' + tiles.join("") + "</div>";
  }
  function load() {
    return P.refresh().then(function (list) {
      var p = list.filter(function (x) { return x.code === CODE; })[0];
      if (p && (p.status === "active" || (p.status === "paused" && A.can("partners.manage")))) P.choose(list, CODE, true);   // the pages that follow work inside this partner
      return { data: list };
    });
  }
  A.start({ page: "partner", nav: "departments", title: "dp_title", shell: true, load: load, render: render });
})();
