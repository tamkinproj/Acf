(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, tmp = 0;

  A.strings({
    proj_title: "Projects", budget: "Budget", spent: "Spent", progress: "Progress", st_active: "In progress", st_completed: "Completed", add_proj: "Add project",
    f_pname_en: "Project name (English)", f_pname_ar: "Project name (Arabic)", f_ploc: "Location", f_pbudget: "Budget", waiting: "Waiting to send", proj_saved: "Project saved"
  }, {
    proj_title: "المشاريع", budget: "الميزانية", spent: "المصروف", progress: "التقدّم", st_active: "قيد التنفيذ", st_completed: "مكتمل", add_proj: "إضافة مشروع",
    f_pname_en: "اسم المشروع (بالإنجليزية)", f_pname_ar: "اسم المشروع (بالعربية)", f_ploc: "الموقع", f_pbudget: "الميزانية", waiting: "بانتظار الإرسال", proj_saved: "تم حفظ المشروع"
  });

  function render(list) {
    return '<div class="head"><h1 class="title">' + t("proj_title") + '</h1></div><div class="grid g2">' + (list.length ? list.map(function (p) {
      var done = p.status === "completed";
      return '<div class="card"><span class="badge ' + (done ? "grey" : "") + '">' + t(done ? "st_completed" : "st_active") + "</span>" + (p._pending ? ' <span class="badge grey">' + t("waiting") + "</span>" : "") +
        '<h3 style="margin-top:.7rem">' + esc(A.lang() === "ar" && p.name_ar ? p.name_ar : p.name_en) + '</h3><p class="note" style="margin-top:0">' + esc(p.location || "") + "</p>" +
        '<div class="bar"><i style="width:' + Math.min(100, p.progress) + '%"></i></div>' + A.facts([[t("budget"), A.money(p.budget)], [t("spent"), A.money(p.spent)], [t("progress"), p.progress + "%"]]) + "</div>";
    }).join("") : '<p class="note">' + t("none") + "</p>") + "</div>" +
      (A.can("projects.manage") ? '<button class="btn btn-main fab" data-act="addproj">' + ic("plus") + t("add_proj") + "</button>" : "");
  }
  A.act("addproj", function () {
    A.sheet(t("add_proj"), '<form id="projForm" novalidate>' + A.field("p_name_en", t("f_pname_en"), "", "text", 'dir="ltr"') + A.field("p_name_ar", t("f_pname_ar"), "", "text", 'dir="rtl"') +
      A.field("p_location", t("f_ploc")) + A.field("p_budget", t("f_pbudget") + " (" + A.CUR + ")", "", "number", 'min="0" step="1" inputmode="decimal"') + A.formEnd() + "</form>");
  });
  function v(id) { return document.getElementById(id).value.trim(); }
  A.form("projForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    var body = { name_en: v("p_name_en") };
    if (!body.name_en) { document.getElementById("p_name_enErr").textContent = t("required"); return; }
    ["name_ar", "location"].forEach(function (k) { if (v("p_" + k)) body[k] = v("p_" + k); });
    if (v("p_budget")) body.budget = Number(v("p_budget"));
    var btn = document.querySelector("#projForm button[type=submit]"); btn.disabled = true;
    A.api.write("POST", "projects", body, {
      label: t("add_proj") + ": " + body.name_en,
      optimistic: function () {
        var item = { id: --tmp - Date.now() % 100000, name_en: body.name_en, name_ar: body.name_ar || null, location: body.location || null, budget: body.budget || 0, spent: 0, status: "active", progress: 0, _pending: true };
        A.api.patchCache("projects", function (p) { p.data = p.data.concat([item]); return p; });
      }
    }).then(function (r) { A.closeSheet(); A.toast(r.queued ? t("phone_saved") : t("proj_saved")); A.reload(true); }, function (e) {
      btn.disabled = false;
      if (!(e.errors && A.showErrors(e.errors, "p_"))) document.getElementById("formErr").textContent = A.msg(e);
    });
  });

  A.start({ page: "projects", nav: "projects", title: "n_proj", perm: "projects.view", shell: true, load: function () { return A.api.get("projects"); }, render: render });
})();
