/* Choose a partner. Orphans and Education belong to one partner, so tapping either opens this list first:
   every partner is shown; the ones that work can be opened, the others are greyed out with their status. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, P = A.partners, list = [];
  var FOR = ((location.search.match(/[?&]for=([a-z]+)/) || [])[1]) === "education" ? "education" : "people";
  A.strings({
    pk_orphans: "Orphans", pk_education: "Education", pk_note: "Choose a partner. Its {d} open next.", pk_back: "Departments",
    pk_ro: "Paused · look only", pk_off_paused: "Paused", pk_off_setup: "Not set up yet", pt_s_active: "Working", pt_s_paused: "Paused", pt_s_not_setup: "Not set up yet", pk_open: "Open"
  }, {
    pk_orphans: "الأيتام", pk_education: "التعليم", pk_note: "اختر شريكًا. تُفتح {d} الخاصة به بعد ذلك.", pk_back: "الأقسام",
    pk_ro: "متوقف مؤقتًا · للاطلاع فقط", pk_off_paused: "متوقف مؤقتًا", pk_off_setup: "لم يُجهّز بعد", pt_s_active: "يعمل", pt_s_paused: "متوقف مؤقتًا", pt_s_not_setup: "لم يُجهّز بعد", pk_open: "فتح"
  });
  var DEPT = { people: ["pk_orphans", "people.html"], education: ["pk_education", "education.html"] };
  function card(p) {
    var cur = P.info(), on = cur && cur.code === p.code, look = p.status === "paused" && A.can("partners.manage"), usable = p.status === "active" || look;
    var badge = '<span class="badge ' + (p.status === "active" ? "" : p.status === "paused" ? "warn" : "grey") + '">' + t(look ? "pk_ro" : "pt_s_" + p.status) + "</span>";
    var inner = P.logo(p, "pt-logo") + "<b>" + esc(P.name(p)) + "</b>" + badge;
    return usable ? '<a class="pt-card' + (on ? " on" : "") + '" href="' + DEPT[FOR][1] + '" data-act="gopartner" data-code="' + esc(p.code) + '">' + inner + "</a>"
      : '<div class="pt-card off" aria-disabled="true">' + inner + "</div>";
  }
  function render(d) {
    list = d;
    return '<div class="head"><a class="btn btn-line btn-sm" href="departments.html" data-act="pkback">' + A.ic("back", "flip") + t("pk_back") + '</a></div><h1 class="title" style="margin-top:.8rem">' + t(DEPT[FOR][0]) + '</h1><p class="note">' +
      esc(t("pk_note", { d: t(DEPT[FOR][0]).toLowerCase() })) + '</p><div class="pt-row">' + d.map(card).join("") + "</div>";
  }
  A.act("gopartner", function (el) { P.choose(list, el.getAttribute("data-code"), true); });   // then the link opens the department
  A.start({ page: "partnerpick", nav: "departments", title: "dp_title", shell: true, load: function () { return P.refresh().then(function (l) { return { data: l }; }); }, render: render });
})();
