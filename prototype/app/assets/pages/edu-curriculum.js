(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, K = window.EDU;
  var id = parseInt((location.search.match(/[?&]id=(\d+)/) || [])[1], 10) || 0, adding = false;
  A.strings({
    cu_title: "Curriculum", cu_units: "Units", cu_add: "Add a unit", cu_unit_ph: "Name of the unit", cu_rm_q: "Remove this unit?", cu_teachers: "Teachers following it", cu_none_u: "No units yet.", cu_none_t: "No teacher follows this curriculum yet.", cu_edit_unit: "Edit unit"
  }, {
    cu_title: "المنهج", cu_units: "الوحدات", cu_add: "إضافة وحدة", cu_unit_ph: "اسم الوحدة", cu_rm_q: "إزالة هذه الوحدة؟", cu_teachers: "المعلمون الذين يتبعونه", cu_none_u: "لا توجد وحدات بعد.", cu_none_t: "لا يوجد معلم يتبع هذا المنهج بعد.", cu_edit_unit: "تعديل الوحدة"
  });
  var edUnit = null;
  function nm(x) { return A.lang() === "ar" && x.name_ar ? x.name_ar : x.name_en; }
  function ut(u) { return A.lang() === "ar" && u.title_ar ? u.title_ar : u.title_en; }
  function render(d) {
    var ed = A.can("education.manage"), title = A.lang() === "ar" && d.title_ar ? d.title_ar : d.title_en;
    var units = d.units.map(function (u, i) {
      if (edUnit === u.id) return '<div class="dp-inrow"><div><label for="u_en">' + t("f_title_en") + '</label><input id="u_en" type="text" value="' + esc(u.title_en) + '"></div><div><label for="u_ar">' + t("f_title_ar") + '</label><input id="u_ar" type="text" dir="rtl" value="' + esc(u.title_ar || "") + '"></div><div class="dp-inbtns"><button class="btn btn-line btn-sm" type="button" data-act="ucancel">' + t("p_cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="usave" data-id="' + u.id + '">' + t("p_save") + "</button></div></div>";
      return '<div class="ios-row no-tile dp-row"><span class="ios-lbl" style="flex:0 0 2rem;color:var(--muted)">' + (i + 1) + '</span><span class="ios-val" style="text-align:start;font-weight:600">' + esc(ut(u)) + '</span>' + (ed ? '<span class="dp-btns"><button class="ci-x" type="button" data-act="uedit" data-id="' + u.id + '" aria-label="' + esc(t("cu_edit_unit")) + '">' + ic("edit") + '</button><button class="ci-x" type="button" data-act="urm" data-id="' + u.id + '" aria-label="x">&times;</button></span>' : "") + "</div>";
    }).join("") || '<p class="note" style="padding:1rem;margin:0">' + t("cu_none_u") + "</p>";
    var add = ed ? (adding ? '<div class="dp-inrow"><div><label for="u_new">' + t("cu_add") + '</label><input id="u_new" type="text" placeholder="' + esc(t("cu_unit_ph")) + '"></div><div class="dp-inbtns"><button class="btn btn-line btn-sm" type="button" data-act="uaddcancel">' + t("p_cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="uaddgo">' + t("p_save") + "</button></div></div>"
      : '<div class="actions" style="margin:.7rem 0 0"><button class="btn btn-line btn-sm" type="button" data-act="uadd">' + ic("plus") + "<span>" + t("cu_add") + "</span></button></div>") : "";
    var teachers = '<section class="dp-sec"><div class="ios-head"><h3>' + t("cu_teachers") + '</h3><span class="note">' + d.teachers.length + '</span></div><div class="ios-group">' + (d.teachers.map(function (x) { return '<a class="ios-row no-tile" href="edu-teacher.html?id=' + x.id + '"><span class="ios-lbl">' + esc(nm(x)) + "</span>" + ic("chev", "ios-chev") + "</a>"; }).join("") || '<p class="note" style="padding:1rem;margin:0">' + t("cu_none_t") + "</p>") + "</div></section>";
    return K.bar("education.html", title) +
      '<section class="card dp-head"><div class="phead"><span class="avatar dp-avatar">' + ic("folder") + '</span><div class="grow"><h1 class="title">' + esc(title) + '</h1><span class="note">' + esc(d.source || "") + (d.level ? " · " + esc(d.level) : "") + "</span></div></div></section>" +
      '<div class="dp-grid">' + K.sec(t("cu_title"), K.schema.curriculum.map(function (f) { return K.row(f, d[f.k], ed); }).join("")) +
      '<section class="dp-sec"><div class="ios-head"><h3>' + t("cu_units") + '</h3><span class="note">' + d.units.length + '</span></div><div class="ios-group dp-rows">' + units + "</div>" + add + "</section>" + teachers + "</div>";
  }
  function again() { return A.reload(true); }
  function fail(e) { A.toast(e.offline ? t("edu_conn") : A.msg(e)); }
  A.act("uadd", function () { adding = true; A.paint(); });
  A.act("uaddcancel", function () { adding = false; A.paint(); });
  A.act("uaddgo", function (el) { var v = document.getElementById("u_new").value.trim(); if (!v) return; el.disabled = true; A.api.write("POST", "edu/curricula/" + id + "/units", { title_en: v }, { online: true }).then(function () { adding = false; again(); }, function (e) { el.disabled = false; fail(e); }); });
  A.act("uedit", function (el) { edUnit = +el.getAttribute("data-id"); A.paint(); });
  A.act("ucancel", function () { edUnit = null; A.paint(); });
  A.act("usave", function (el) { var v = document.getElementById("u_en").value.trim(), a = document.getElementById("u_ar").value.trim(); if (!v) return; A.api.write("PATCH", "edu/units/" + el.getAttribute("data-id"), { title_en: v, title_ar: a || null }, { online: true }).then(function () { edUnit = null; again(); }, fail); });
  A.act("urm", function (el) { if (!confirm(t("cu_rm_q"))) return; A.api.write("DELETE", "edu/units/" + el.getAttribute("data-id"), undefined, { online: true }).then(again, fail); });
  K.wire({ path: function () { return "edu/curricula/" + id; }, fields: function () { return K.schema.curriculum; }, reload: again });
  A.start({ page: "edu-curriculum", nav: "education", title: "cu_title", perm: "education.view", needsConn: true, shell: true, bare: true, load: function () { return A.api.get("edu/curricula/" + id, { cache: false, forceNet: true }); }, render: render });
})();
