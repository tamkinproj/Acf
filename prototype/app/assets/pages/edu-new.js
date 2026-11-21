(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, K = window.EDU;
  var kind = (location.search.match(/[?&]kind=(\w+)/) || [])[1] || "teacher";
  if (["teacher", "institution", "curriculum"].indexOf(kind) < 0) kind = "teacher";
  A.strings({ nw_teacher: "Add teacher", nw_institution: "Add institution", nw_curriculum: "Add curriculum", nw_contract: "First contract (optional)", nw_create: "Create", nw_units_ph: "Surah Al-Fatiha\nSurah An-Nas\nDaily duas" }, { nw_teacher: "إضافة معلم", nw_institution: "إضافة جهة", nw_curriculum: "إضافة منهج", nw_contract: "العقد الأول (اختياري)", nw_create: "إنشاء", nw_units_ph: "سورة الفاتحة\nسورة الناس\nالأدعية اليومية" });
  var PLAIN = {
    teacher: ["name_en", "name_ar", "gender", "phone", "email", "city", "qualification", "specialization", "hired_on"],
    institution: ["kind", "partnership", "name_en", "name_ar", "city", "address", "contact_name", "phone", "since"],
    curriculum: ["title_en", "title_ar", "source", "level", "description"]
  };
  function defs() { var all = K.schema[kind].concat(kind === "teacher" ? K.schema.assign : []); return PLAIN[kind].map(function (k) { return K.field(all, k); }).concat(kind === "teacher" ? K.schema.assign : []); }
  function render() {
    var salary = A.can("education.salary");
    var fields = defs().map(function (d) { return K.control(d, kind === "curriculum" && d.k === "source" ? "Dar Al Ber" : ""); }).join("");
    var extra = kind === "teacher" ? '<section class="fpage"><h3>' + t("nw_contract") + '</h3><div class="fpage-body"><div><label for="n_start">' + t("f_start") + '</label><input id="n_start" type="date"></div><div><label for="n_months">' + t("f_months") + '</label><input id="n_months" type="number" min="1" max="24" value="6" inputmode="numeric"></div>' +
      (salary ? '<div><label for="n_salary">' + t("f_salary") + '</label><input id="n_salary" type="number" min="0" step="0.01" inputmode="decimal"></div>' : "") + "</div></section>"
      : kind === "curriculum" ? '<section class="fpage"><h3>' + t("f_units_lines") + '</h3><div class="fpage-body"><textarea id="n_units" rows="6" placeholder="' + esc(t("nw_units_ph")) + '"></textarea></div></section>' : "";
    return K.bar("education.html", t("nw_" + kind)) + '<div class="addwrap"><form id="newForm" novalidate><section class="fpage"><div class="fpage-body">' + fields + "</div></section>" + extra +
      '<div class="err" id="formErr" role="alert"></div><div class="actions addwrap-actions"><a class="btn btn-line" href="education.html">' + t("p_cancel") + '</a><button class="btn btn-main" type="submit">' + t("nw_create") + "</button></div></form></div>";
  }
  A.form("newForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll(".addwrap .err"), function (e) { e.textContent = ""; });
    var body = {}, bad = null;
    defs().forEach(function (d) { var v = K.read(d); if (v !== null && v !== undefined) body[d.k] = v; });
    if (!body.name_en && !body.title_en) { var k = kind === "curriculum" ? "title_en" : "name_en"; document.getElementById("f_" + k + "Err").textContent = t("required"); return; }
    if (kind === "teacher") {
      var s = document.getElementById("n_start").value, m = parseInt(document.getElementById("n_months").value, 10), sl = document.getElementById("n_salary");
      if (s || (sl && sl.value !== "")) { if (s) body.start_date = s; else { var d = new Date(); body.start_date = d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2); } if (m) body.months = m; if (sl && sl.value !== "") body.monthly_salary = Number(sl.value); }
    }
    if (kind === "curriculum") { var u = document.getElementById("n_units").value.split("\n").map(function (x) { return x.trim(); }).filter(Boolean); if (u.length) body.units = u; }
    var btn = document.querySelector("#newForm button[type=submit]"); btn.disabled = true;
    var path = kind === "teacher" ? "edu/teachers" : kind === "institution" ? "edu/institutions" : "edu/curricula";
    A.api.write("POST", path, body, { online: true }).then(function (r) { var d = r.data || r; location.replace(kind === "teacher" ? "edu-teacher.html?id=" + d.id : kind === "institution" ? "edu-place.html?id=" + d.id : "edu-curriculum.html?id=" + d.id); }, function (e) {
      btn.disabled = false; if (!(e.errors && A.showErrors(e.errors, "f_"))) document.getElementById("formErr").textContent = e.offline ? t("edu_conn") : A.msg(e);
    });
  });
  A.start({ page: "edu-new", nav: "education", title: "edu_title", perm: "education.manage", needsConn: true, shell: true, bare: true, load: function () { return K.loadLists().then(function () { return { data: {} }; }); }, render: render });
})();
