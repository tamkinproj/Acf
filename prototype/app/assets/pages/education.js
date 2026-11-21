(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, K = window.EDU;
  var TABS = ["teachers", "salaries", "institutions", "curricula"];

  A.strings({
    edu_lead: "Alimni teachers, their contracts and salaries, the madrasa and partner markaz, and the curriculum.", tb_teachers: "Teachers", tb_salaries: "Salaries", tb_institutions: "Madrasa and markaz", tb_curricula: "Curriculum",
    edu_attn: "Contracts that need attention", edu_none_t: "No teachers yet.", edu_none_i: "No madrasa or markaz yet.", edu_none_c: "No curriculum yet.", edu_add_t: "Add teacher", edu_add_i: "Add institution", edu_add_c: "Add curriculum",
    sal_total_paid: "Paid", sal_total_due: "Still to pay", sal_make: "Create this month's lines", sal_none: "No salary lines for this month yet. Create them to start.", sal_paid: "Paid", sal_mark: "Mark paid", sal_made: "{n} lines created",
    sal_cash: "Cash", sal_bank: "Bank", units_n: "{n} units", teachers_n: "{n} teachers", madrasa_tag: "Madrasa", markaz_tag: "Markaz", partner_tag: "Partner"
  }, {
    edu_lead: "معلمو علّمني وعقودهم ورواتبهم، والمدرسة والمراكز الشريكة، والمنهج.", tb_teachers: "المعلمون", tb_salaries: "الرواتب", tb_institutions: "المدرسة والمراكز", tb_curricula: "المنهج",
    edu_attn: "عقود تحتاج إلى انتباه", edu_none_t: "لا يوجد معلمون بعد.", edu_none_i: "لا توجد مدرسة أو مراكز بعد.", edu_none_c: "لا يوجد منهج بعد.", edu_add_t: "إضافة معلم", edu_add_i: "إضافة جهة", edu_add_c: "إضافة منهج",
    sal_total_paid: "المدفوع", sal_total_due: "المتبقي", sal_make: "إنشاء أسطر هذا الشهر", sal_none: "لا توجد أسطر رواتب لهذا الشهر بعد. أنشئها للبدء.", sal_paid: "مدفوع", sal_mark: "تحديد كمدفوع", sal_made: "تم إنشاء {n} سطرًا",
    sal_cash: "نقدًا", sal_bank: "بنكيًا", units_n: "{n} وحدة", teachers_n: "{n} معلمًا", madrasa_tag: "مدرسة", markaz_tag: "مركز", partner_tag: "شريك"
  });

  var D = null;
  function tab() { var x = A.ui.edutab; if (TABS.indexOf(x) < 0 || (x === "salaries" && !A.can("education.salary"))) x = "teachers"; return x; }
  function month() { return A.ui.edumonth || (function () { var d = new Date(); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); })(); }
  function shift(m, n) { var p = m.split("-"), d = new Date(+p[0], +p[1] - 1 + n, 1); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); }

  function nm(x) { return A.lang() === "ar" && x.name_ar ? x.name_ar : x.name_en; }
  function row(href, avatar, title, sub, end) {
    return '<a class="orow-card edu-row" href="' + href + '"><span class="avatar">' + esc(String(avatar || "?").charAt(0)) + '</span><span class="grow"><b>' + esc(title) + '</b><span class="sub">' + sub + "</span></span>" + (end || "") + ic("chev", "ios-chev") + "</a>";
  }
  function teachers() {
    if (!D.teachers.length) return '<p class="note">' + t("edu_none_t") + "</p>";
    return D.teachers.map(function (x) {
      return row("edu-teacher.html?id=" + x.id, nm(x), nm(x), '<span dir="ltr">' + esc(x.ref) + "</span>" + (x.institution ? " · " + esc(x.institution) : "") + (x.status !== "active" ? " · " + t("s_inactive") : ""), K.chip(x.contract_state, x.days_left));
    }).join("");
  }
  function institutions() {
    if (!D.institutions.length) return '<p class="note">' + t("edu_none_i") + "</p>";
    return D.institutions.map(function (x) {
      return row("edu-place.html?id=" + x.id, nm(x), nm(x), esc(t(x.kind === "madrasa" ? "madrasa_tag" : "markaz_tag")) + (x.partnership === "partner" ? " · " + esc(t("partner_tag")) : "") + " · " + esc(t("teachers_n", { n: x.teachers_count })) + (x.status === "ended" ? " · " + esc(t("s_ended")) : ""));
    }).join("");
  }
  function curricula() {
    if (!D.curricula.length) return '<p class="note">' + t("edu_none_c") + "</p>";
    return D.curricula.map(function (x) {
      var title = A.lang() === "ar" && x.title_ar ? x.title_ar : x.title_en;
      return row("edu-curriculum.html?id=" + x.id, title, title, esc(x.source || "") + " · " + esc(t("units_n", { n: x.units_count })) + " · " + esc(t("teachers_n", { n: x.teachers_count })));
    }).join("");
  }
  function salaries() {
    var s = D.salaries, m = month(), p = m.split("-"), lbl = new Date(+p[0], +p[1] - 1, 1).toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { month: "long", year: "numeric" });
    var head = '<div class="msel"><button class="mo-circle" type="button" data-act="emonth" data-d="-1" aria-label="-">' + ic("back", "flip") + '</button><b>' + esc(lbl) + '</b><button class="mo-circle" type="button" data-act="emonth" data-d="1" aria-label="+">' + ic("chev", "flip") + "</button></div>";
    if (!s || !s.rows.length) return head + '<p class="note">' + t("sal_none") + '</p><div class="actions"><button class="btn btn-main" type="button" data-act="esalgen">' + t("sal_make") + "</button></div>";
    var tt = s.totals;
    return head + '<div class="edu-tot"><div><small>' + t("sal_total_paid") + "</small><b>" + K.money(tt.paid_total) + "</b></div><div><small>" + t("sal_total_due") + "</small><b>" + K.money(tt.due_total) + "</b></div></div>" +
      s.rows.map(function (r) {
        var paid = r.status === "paid";
        return '<div class="orow-card edu-row"><a class="avatar" href="edu-teacher.html?id=' + r.teacher_id + '">' + esc(String(nm(r) || "?").charAt(0)) + '</a><span class="grow"><b>' + esc(nm(r)) + '</b><span class="sub">' + K.money(r.amount) + " · " + esc(t(r.method === "bank" ? "sal_bank" : "sal_cash")) + '</span></span>' +
          '<button class="btn btn-sm ' + (paid ? "btn-line" : "btn-main") + '" type="button" data-act="esalpay" data-id="' + r.teacher_id + '" data-to="' + (paid ? "pending" : "paid") + '">' + (paid ? ic("check") + t("sal_paid") : t("sal_mark")) + "</button></div>";
      }).join("") + '<div class="actions" style="margin-top:.8rem"><button class="btn btn-line btn-sm" type="button" data-act="esalgen">' + t("sal_make") + "</button></div>";
  }

  function render(d) {
    D = d; var cur = tab(), tabs = TABS.filter(function (x) { return x !== "salaries" || A.can("education.salary"); });
    var attn = d.summary.attention || [];
    var banner = attn.length ? '<section class="card edu-attn"><h3>' + t("edu_attn") + "</h3>" + attn.map(function (a) { return '<a class="edu-al" href="edu-teacher.html?id=' + a.id + '"><b>' + esc(nm(a)) + "</b>" + K.chip(a.state, a.days_left) + "</a>"; }).join("") + "</section>" : "";
    var body = cur === "teachers" ? teachers() : cur === "salaries" ? salaries() : cur === "institutions" ? institutions() : curricula();
    var add = A.can("education.manage") && cur !== "salaries" ? '<a class="btn btn-main fab" href="edu-new.html?kind=' + (cur === "teachers" ? "teacher" : cur === "institutions" ? "institution" : "curriculum") + '">' + ic("plus") + t(cur === "teachers" ? "edu_add_t" : cur === "institutions" ? "edu_add_i" : "edu_add_c") + "</a>" : "";
    return '<div class="head"><h1 class="title">' + t("edu_title") + '</h1><p class="lead">' + t("edu_lead") + "</p></div>" + banner +
      '<div class="filters" role="group">' + tabs.map(function (k) { return '<button class="fbtn" type="button" data-act="etab" data-v="' + k + '" aria-pressed="' + (cur === k) + '">' + t("tb_" + k) + "</button>"; }).join("") + "</div>" +
      '<div class="edu-list">' + body + "</div>" + add;
  }
  A.act("etab", function (el) { A.ui.edutab = el.getAttribute("data-v"); A.saveUI(); A.reload(true); });
  A.act("emonth", function (el) { A.ui.edumonth = shift(month(), +el.getAttribute("data-d")); A.saveUI(); A.reload(true); });
  A.act("esalgen", function (el) { el.disabled = true; A.api.write("POST", "edu/salaries/generate", { month: month() }, { online: true }).then(function (r) { A.toast(t("sal_made", { n: (r.data || r).created })); A.reload(true); }, function (e) { el.disabled = false; A.toast(e.offline ? t("edu_conn") : A.msg(e)); }); });
  A.act("esalpay", function (el) { el.disabled = true; A.api.write("PUT", "edu/teachers/" + el.getAttribute("data-id") + "/salaries/" + month(), { status: el.getAttribute("data-to") }, { online: true }).then(function () { A.reload(true); }, function (e) { el.disabled = false; A.toast(e.offline ? t("edu_conn") : A.msg(e)); }); });

  function load() {
    var calls = [A.api.get("edu/summary", { cache: false }), A.api.get("edu/teachers", { cache: false }), A.api.get("edu/institutions", { cache: false }), A.api.get("edu/curricula", { cache: false })];
    if (A.can("education.salary") && tab() === "salaries") calls.push(A.api.get("edu/salaries?month=" + month(), { cache: false }));
    return Promise.all(calls).then(function (r) { return { data: { summary: r[0].data, teachers: r[1].data, institutions: r[2].data, curricula: r[3].data, salaries: r[4] ? r[4].data : null } }; });
  }
  A.start({ page: "education", nav: "education", title: "edu_title", perm: "education.view", needsConn: true, shell: true, load: load, render: render });
})();
