(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, K = window.EDU;
  var id = parseInt((location.search.match(/[?&]id=(\d+)/) || [])[1], 10) || 0;
  A.strings({ pl_title: "Institution", pl_teachers: "Teachers here", pl_none: "No teachers assigned yet. Choose this place under Teaches at on a teacher's page." }, { pl_title: "الجهة", pl_teachers: "المعلمون هنا", pl_none: "لا يوجد معلمون معيّنون بعد. اختر هذه الجهة من خانة «يدرّس في» في صفحة المعلم." });
  function nm(x) { return A.lang() === "ar" && x.name_ar ? x.name_ar : x.name_en; }
  function render(d) {
    var ed = A.can("education.manage");
    var teachers = '<section class="dp-sec"><div class="ios-head"><h3>' + t("pl_teachers") + '</h3><span class="note">' + d.teachers.length + '</span></div><div class="ios-group">' + (d.teachers.map(function (x) {
      return '<a class="ios-row no-tile" href="edu-teacher.html?id=' + x.id + '"><span class="ios-lbl">' + esc(nm(x)) + '</span><span class="ios-val">' + K.chip(x.contract_state) + "</span>" + ic("chev", "ios-chev") + "</a>";
    }).join("") || '<p class="note" style="padding:1rem;margin:0">' + t("pl_none") + "</p>") + "</div></section>";
    return K.bar("education.html", nm(d)) +
      '<section class="card dp-head"><div class="phead"><span class="avatar dp-avatar">' + esc(String(nm(d) || "?").charAt(0)) + '</span><div class="grow"><h1 class="title">' + esc(nm(d)) + '</h1><span class="note">' + esc(t(d.kind === "madrasa" ? "f_madrasa" : "f_markaz")) + (d.partnership === "partner" ? " · " + esc(t("f_partner")) : "") + "</span></div></div></section>" +
      '<div class="dp-grid">' + K.sec(t("pl_title"), K.schema.institution.map(function (f) { return K.row(f, d[f.k], ed); }).join("")) + teachers + "</div>";
  }
  K.wire({ path: function () { return "edu/institutions/" + id; }, fields: function () { return K.schema.institution; }, reload: function () { A.reload(true); } });
  A.start({ page: "edu-place", nav: "education", title: "pl_title", perm: "education.view", needsConn: true, shell: true, bare: true, load: function () { return A.api.get("edu/institutions/" + id, { cache: false, forceNet: true }); }, render: render });
})();
