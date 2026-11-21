(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, K = window.EDU;
  var id = parseInt((location.search.match(/[?&]id=(\d+)/) || [])[1], 10) || 0, T = null, renew = false;

  A.strings({
    tp_title: "Teacher", tp_contract: "Contract", tp_start: "Starts", tp_end: "Ends", tp_state: "State", tp_left: "{n} days left", tp_salary: "Monthly salary (sweldo)", tp_renew: "Renew contract", tp_new: "Make a contract",
    tp_renew_note: "A renewal starts the day after the current contract ends. Six months unless you change it.", tp_hist: "Earlier contracts", tp_create: "Create contract", tp_cancel: "Cancel", tp_sal: "Salaries", tp_sal_none: "No salary lines yet. Create them from the Salaries tab or add this month here.",
    tp_add_month: "Add this month", tp_paid: "Paid", tp_pending: "Pending", tp_mark: "Mark paid", tp_undo: "Undo", tp_prog: "Curriculum progress", tp_prog_of: "{d} of {n} units done", tp_none: "No curriculum assigned. Choose one under Teaches at.",
    tp_assign: "Where and what", tp_contract_saved: "Contract saved", tp_info: "Information", tp_back: "Back", cs_unit: "Units", tp_cash: "Cash", tp_bank: "Bank"
  }, {
    tp_title: "المعلم", tp_contract: "العقد", tp_start: "يبدأ", tp_end: "ينتهي", tp_state: "الحالة", tp_left: "باقي {n} يومًا", tp_salary: "الراتب الشهري (سويلدو)", tp_renew: "تجديد العقد", tp_new: "إنشاء عقد",
    tp_renew_note: "يبدأ التجديد في اليوم التالي لانتهاء العقد الحالي. ستة أشهر ما لم تغيّرها.", tp_hist: "العقود السابقة", tp_create: "إنشاء العقد", tp_cancel: "إلغاء", tp_sal: "الرواتب", tp_sal_none: "لا توجد أسطر رواتب بعد. أنشئها من تبويب الرواتب أو أضف هذا الشهر هنا.",
    tp_add_month: "إضافة هذا الشهر", tp_paid: "مدفوع", tp_pending: "معلّق", tp_mark: "تحديد كمدفوع", tp_undo: "تراجع", tp_prog: "تقدم المنهج", tp_prog_of: "تم {d} من {n} وحدة", tp_none: "لا يوجد منهج معيّن. اختر واحدًا من خانة «يدرّس في».",
    tp_assign: "المكان والمنهج", tp_contract_saved: "تم حفظ العقد", tp_info: "المعلومات", tp_back: "رجوع", cs_unit: "الوحدات", tp_cash: "نقدًا", tp_bank: "بنكيًا"
  });
  var can = function () { return A.can("education.manage"); };
  function nm(x) { return A.lang() === "ar" && x.name_ar ? x.name_ar : x.name_en; }
  function mlabel(m) { var p = m.split("-"); return new Date(+p[0], +p[1] - 1, 1).toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { month: "long", year: "numeric" }); }
  function plain(label, val) { return '<div class="ios-row no-tile dp-row"><span class="ios-lbl">' + label + '</span><span class="ios-val">' + val + "</span></div>"; }
  function today() { var d = new Date(); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2); }

  function contractBlock(d) {
    var c = d.contract, salary = d.can_salary, rows = [];
    if (c) {
      rows.push(plain(t("tp_state"), K.chip(d.contract_state, d.days_left) + (d.contract_state === "active" ? ' <span class="note">' + esc(t("tp_left", { n: c.days_left })) + "</span>" : "")));
      rows.push(plain(t("tp_start"), esc(A.dateOnly(c.start_date + "T00:00:00"))));
      rows.push(plain(t("tp_end"), esc(A.dateOnly(c.end_date + "T00:00:00"))));
      if (salary) rows.push(plain(t("tp_salary"), K.money(c.monthly_salary)));
    }
    var form = renew ? '<div class="dp-inrow"><p class="note" style="margin:0">' + t("tp_renew_note") + '</p><div><label for="rn_start">' + t("f_start") + '</label><input id="rn_start" type="date" value="' + (c && c.end_date >= today() ? "" : today()) + '"></div><div><label for="rn_months">' + t("f_months") + '</label><input id="rn_months" type="number" min="1" max="24" value="6" inputmode="numeric"></div>' +
      (salary ? '<div><label for="rn_salary">' + t("f_salary") + '</label><input id="rn_salary" type="number" min="0" step="0.01" inputmode="decimal" value="' + (c ? c.monthly_salary : "") + '"></div>' : "") +
      '<div class="err" id="rn_err"></div><div class="dp-inbtns"><button class="btn btn-line btn-sm" type="button" data-act="rncancel">' + t("tp_cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="rngo">' + t("tp_create") + "</button></div></div>" : "";
    var btn = can() && !renew ? '<div class="actions" style="margin:.7rem 0 0"><button class="btn btn-main btn-sm" type="button" data-act="rnopen">' + ic("plus") + "<span>" + t(c ? "tp_renew" : "tp_new") + "</span></button></div>" : "";
    var hist = d.contracts.length > 1 ? '<div class="ios-head" style="margin-top:1rem"><h3>' + t("tp_hist") + '</h3></div><div class="ios-group dp-rows">' + d.contracts.slice(1).map(function (x) { return plain(esc(A.dateOnly(x.start_date + "T00:00:00")) + " → " + esc(A.dateOnly(x.end_date + "T00:00:00")), d.can_salary ? K.money(x.monthly_salary) : ""); }).join("") + "</div>" : "";
    return '<section class="dp-sec"><div class="ios-head"><h3>' + t("tp_contract") + '</h3></div><div class="ios-group dp-rows">' + rows.join("") + form + "</div>" + btn + hist + "</section>";
  }
  function salaryBlock(d) {
    if (!d.can_salary) return "";
    var list = (d.salaries || []).map(function (s) {
      var paid = s.status === "paid";
      return '<div class="ios-row no-tile dp-row"><span class="ios-lbl">' + esc(mlabel(s.month)) + '</span><span class="ios-val">' + K.money(s.amount) + ' <span class="note">· ' + esc(t(s.method === "bank" ? "tp_bank" : "tp_cash")) + '</span></span><span class="dp-btns"><button class="btn btn-sm ' + (paid ? "btn-line" : "btn-main") + '" type="button" data-act="salpay" data-m="' + s.month + '" data-to="' + (paid ? "pending" : "paid") + '">' + (paid ? t("tp_undo") : t("tp_mark")) + "</button></span></div>";
    }).join("") || '<p class="note" style="padding:1rem">' + t("tp_sal_none") + "</p>";
    return '<section class="dp-sec"><div class="ios-head"><h3>' + t("tp_sal") + '</h3></div><div class="ios-group dp-rows">' + list + "</div>" + (can() && d.contract ? '<div class="actions" style="margin:.7rem 0 0"><button class="btn btn-line btn-sm" type="button" data-act="saladd">' + ic("plus") + "<span>" + t("tp_add_month") + "</span></button></div>" : "") + "</section>";
  }
  function progressBlock(d) {
    var c = d.curriculum; if (!c) return '<section class="dp-sec"><div class="ios-head"><h3>' + t("tp_prog") + '</h3></div><div class="ios-group"><p class="note" style="padding:1rem;margin:0">' + t("tp_none") + "</p></div></section>";
    var done = c.units.filter(function (u) { return u.done; }).length;
    return '<section class="dp-sec"><div class="ios-head"><h3>' + t("tp_prog") + '</h3><span class="note">' + esc(t("tp_prog_of", { d: done, n: c.units.length })) + '</span></div><div class="ios-group">' + (c.units.map(function (u) {
      var title = A.lang() === "ar" && u.title_ar ? u.title_ar : u.title_en;
      return '<button class="ios-row no-tile pick' + (u.done ? " on" : "") + '" type="button"' + (can() ? ' data-act="unitdone" data-id="' + u.id + '" data-to="' + (u.done ? 0 : 1) + '"' : "") + ' role="checkbox" aria-checked="' + u.done + '"><span class="grow"><b>' + esc(title) + '</b></span><span class="tick">' + (u.done ? ic("check") : "") + "</span></button>";
    }).join("") || '<p class="note" style="padding:1rem;margin:0">-</p>') + "</div></section>";
  }

  function render(d) {
    T = d; var ed = can(), F = K.schema.teacher, rowsInfo = F.map(function (f) { return K.row(f, d[f.k], ed); }).join(""), rowsAssign = K.schema.assign.map(function (f) { return K.row(f, d[f.k], ed); }).join("");
    return K.bar("education.html", d.ref) +
      '<section class="card dp-head"><div class="phead"><span class="avatar dp-avatar">' + esc(String(nm(d) || "?").charAt(0)) + '</span><div class="grow"><h1 class="title">' + esc(nm(d)) + "</h1>" + (d.name_ar && A.lang() !== "ar" ? '<div class="dp-ar" dir="rtl">' + esc(d.name_ar) + "</div>" : "") + '<span class="note" dir="ltr">' + esc(d.ref) + (d.institution ? " · " + esc(d.institution) : "") + "</span></div></div>" +
      '<div class="pchips">' + K.chip(d.contract_state, d.days_left) + "</div></section>" +
      '<div class="dp-grid">' + K.sec(t("tp_info"), rowsInfo) + K.sec(t("tp_assign"), rowsAssign) + contractBlock(d) + salaryBlock(d) + progressBlock(d) + "</div>";
  }
  function again() { return A.reload(true); }
  function fail(e) { A.toast(e.offline ? t("edu_conn") : A.msg(e)); }
  A.act("rnopen", function () { renew = true; A.paint(); });
  A.act("rncancel", function () { renew = false; A.paint(); });
  A.act("rngo", function (el) {
    var body = {}, s = document.getElementById("rn_start").value, m = parseInt(document.getElementById("rn_months").value, 10), sl = document.getElementById("rn_salary");
    if (s) body.start_date = s; if (m) body.months = m; if (sl && sl.value !== "") body.monthly_salary = Number(sl.value);
    el.disabled = true; A.api.write("POST", "edu/teachers/" + id + "/contracts", body, { online: true }).then(function () { renew = false; A.toast(t("tp_contract_saved")); again(); }, function (e) { el.disabled = false; var er = document.getElementById("rn_err"); if (er) er.textContent = e.offline ? t("edu_conn") : A.msg(e); });
  });
  A.act("salpay", function (el) { el.disabled = true; A.api.write("PUT", "edu/teachers/" + id + "/salaries/" + el.getAttribute("data-m"), { status: el.getAttribute("data-to") }, { online: true }).then(again, function (e) { el.disabled = false; fail(e); }); });
  A.act("saladd", function () { var d = new Date(), m = d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); A.api.write("PUT", "edu/teachers/" + id + "/salaries/" + m, { status: "pending" }, { online: true }).then(again, fail); });
  A.act("unitdone", function (el) { A.api.write("PUT", "edu/teachers/" + id + "/progress/" + el.getAttribute("data-id"), { done: el.getAttribute("data-to") === "1" }, { online: true }).then(again, fail); });

  K.wire({ path: function () { return "edu/teachers/" + id; }, fields: function () { return K.schema.teacher.concat(K.schema.assign); }, reload: again });
  A.start({
    page: "edu-teacher", nav: "education", title: "tp_title", perm: "education.view", needsConn: true, shell: true, bare: true,
    load: function () { return K.loadLists().then(function () { return A.api.get("edu/teachers/" + id, { cache: false, forceNet: true }); }); }, render: render,
    after: function (d) { if (d && d.name_en) document.title = nm(d) + " | " + t("brand"); }
  });
})();
