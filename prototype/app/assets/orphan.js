/* Shared by the Orphans list (add) and the orphan profile (edit): the form, and reading it back. */
(function () {
  var A = ACF, t = A.t;

  A.strings({
    o_sec_basic: "About the child", o_sec_school: "School and health", o_sec_family: "Mother and family", o_sec_guardian: "Direct responsible person", o_sec_bank: "Bank account (optional)",
    f_name_en: "Name (English)", f_name_ar: "Name (Arabic)", f_gender: "Gender", male: "Male", female: "Female", f_dob: "Date of birth", f_city: "City",
    f_grade: "Grade / school level", f_health: "Health status", good: "Good", needs_followup: "Needs follow-up",
    f_mother: "Mother name", f_mstatus: "Mother marital status", f_fdeath: "Father death date", f_bro: "Number of brothers", f_sis: "Number of sisters",
    f_guardian: "Guardian name", f_rel: "Relationship", f_phone: "Guardian phone", f_paytype: "Payment type", bank_pay: "Bank transfer", cash_only: "Cash payment",
    f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits of the account", f_status: "Status",
    active: "Active", pending: "Pending", ended: "Ended", bank_all_or_none: "Fill in all three bank fields, or none."
  }, {
    o_sec_basic: "عن الطفل", o_sec_school: "الدراسة والصحة", o_sec_family: "الأم والأسرة", o_sec_guardian: "المسؤول المباشر", o_sec_bank: "الحساب البنكي (اختياري)",
    f_name_en: "الاسم (بالإنجليزية)", f_name_ar: "الاسم (بالعربية)", f_gender: "الجنس", male: "ذكر", female: "أنثى", f_dob: "تاريخ الميلاد", f_city: "المدينة",
    f_grade: "الصف / المستوى الدراسي", f_health: "الحالة الصحية", good: "جيدة", needs_followup: "تحتاج متابعة",
    f_mother: "اسم الأم", f_mstatus: "الحالة الاجتماعية للأم", f_fdeath: "تاريخ وفاة الأب", f_bro: "عدد الإخوة", f_sis: "عدد الأخوات",
    f_guardian: "اسم الوصي", f_rel: "صلة القرابة", f_phone: "هاتف الوصي", f_paytype: "طريقة الدفع", bank_pay: "حوالة بنكية", cash_only: "دفع نقدي",
    f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام من الحساب", f_status: "الحالة",
    active: "نشط", pending: "معلّق", ended: "منتهٍ", bank_all_or_none: "املأ حقول البنك الثلاثة كلها أو اتركها فارغة."
  });

  function esc(s) { return A.esc(s === null || s === undefined ? "" : String(s)); }
  function val(v) { return v === null || v === undefined ? "" : 'value="' + esc(v) + '"'; }
  function sel(id, label, opts, cur) {
    return '<div><label for="' + id + '">' + label + '</label><select id="' + id + '">' + opts.map(function (o) { return '<option value="' + o[0] + '"' + (String(cur === null || cur === undefined ? "" : cur) === o[0] ? " selected" : "") + ">" + o[1] + "</option>"; }).join("") + '</select><div class="err" id="' + id + 'Err"></div></div>';
  }
  function sec(k) { return '<h4 class="fsec">' + t(k) + "</h4>"; }

  /* c = the profile being edited (or nothing, for a new child). */
  function formHtml(c) {
    c = c || {}; var p = c.personal || {}, f = c.family || {}, g = c.guardian_detail || {}, edit = !!c.id;
    return sec("o_sec_basic") +
      A.field("f_name_en", t("f_name_en"), "", "text", 'dir="ltr" autocomplete="off" ' + val(c.name_en)) + A.field("f_name_ar", t("f_name_ar"), "", "text", 'dir="rtl" ' + val(c.name_ar)) +
      sel("f_gender", t("f_gender"), [["", "-"], ["f", t("female")], ["m", t("male")]], p.gender) +
      A.field("f_birth_date", t("f_dob"), "", "date", val(p.birth_date)) + A.field("f_city", t("f_city"), "", "text", val(p.city)) +
      (edit ? sel("f_status", t("f_status"), [["active", t("active")], ["pending", t("pending")], ["ended", t("ended")]], c.status) : "") +
      sec("o_sec_school") + A.field("f_grade", t("f_grade"), "", "text", val(p.grade)) +
      sel("f_health_status", t("f_health"), [["", "-"], ["good", t("good")], ["needs_followup", t("needs_followup")]], p.health_status) +
      sec("o_sec_family") + A.field("f_mother_name", t("f_mother"), "", "text", val(f.mother_name)) + A.field("f_mother_marital_status", t("f_mstatus"), "", "text", val(f.mother_marital_status)) +
      A.field("f_father_death_date", t("f_fdeath"), "", "date", val(f.father_death_date)) +
      '<div class="two">' + A.field("f_brothers", t("f_bro"), "", "number", 'min="0" max="30" inputmode="numeric" ' + val(f.brothers)) + A.field("f_sisters", t("f_sis"), "", "number", 'min="0" max="30" inputmode="numeric" ' + val(f.sisters)) + "</div>" +
      sec("o_sec_guardian") + A.field("f_guardian_name", t("f_guardian"), "", "text", val(g.name)) + A.field("f_guardian_relation", t("f_rel"), "", "text", val(g.relation)) +
      A.field("f_guardian_phone", t("f_phone"), "", "tel", 'dir="ltr" inputmode="tel" ' + val(g.phone)) +
      (edit ? "" : sec("o_sec_bank") + sel("f_pay_type", t("f_paytype"), [["bank", t("bank_pay")], ["cash", t("cash_only")]], "bank") +
        '<div id="bankBox">' + A.field("f_bank_holder", t("f_holder")) + A.field("f_bank_bank_name", t("f_bankname")) + A.field("f_bank_last4", t("f_last4"), "", "text", 'inputmode="numeric" maxlength="4" dir="ltr"') + "</div>");
  }
  function wire() {
    var pt = document.getElementById("f_pay_type"); if (pt) pt.addEventListener("change", function () { document.getElementById("bankBox").style.display = this.value === "bank" ? "" : "none"; });
  }
  function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ""; }

  /* Returns the request body, or { error: [fieldId, message] }. */
  function readBody(edit) {
    var body = { name_en: v("f_name_en") };
    if (!body.name_en) return { error: ["f_name_en", t("required")] };
    ["name_ar", "gender", "birth_date", "city", "grade", "health_status", "mother_name", "mother_marital_status", "father_death_date"].forEach(function (k) {
      if (edit) body[k] = v("f_" + k) || null; else if (v("f_" + k)) body[k] = v("f_" + k);
    });
    ["brothers", "sisters"].forEach(function (k) { if (v("f_" + k) !== "") body[k] = parseInt(v("f_" + k), 10); else if (edit) body[k] = null; });
    if (edit) body.status = v("f_status");
    if (v("f_guardian_name")) { body.guardian = { name: v("f_guardian_name") }; if (v("f_guardian_relation")) body.guardian.relation = v("f_guardian_relation"); if (v("f_guardian_phone")) body.guardian.phone = v("f_guardian_phone"); }
    if (!edit) {
      body.pay_type = v("f_pay_type");
      if (body.pay_type === "bank") {
        var b = { holder: v("f_bank_holder"), bank_name: v("f_bank_bank_name"), last4: v("f_bank_last4") }, filled = [b.holder, b.bank_name, b.last4].filter(Boolean).length;
        if (filled && filled < 3) return { error: ["formErr", t("bank_all_or_none")] };
        if (filled === 3) body.bank = b;
      }
    }
    return body;
  }
  A.orphan = { formHtml: formHtml, wire: wire, readBody: readBody };
})();
