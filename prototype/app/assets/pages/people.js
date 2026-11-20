(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, q = "", tmp = 0;

  A.strings({
    people: "People", search: "Search name, reference or guardian", all: "All", bank: "Bank", cash: "Cash", verified: "Verified", notver: "Not verified",
    add_child: "Add child", add_title: "Add child", f_name_en: "Name (English)", f_name_ar: "Name (Arabic)", f_gender: "Gender", male: "Male", female: "Female",
    f_dob: "Date of birth", f_city: "City", f_guardian: "Guardian name", f_phone: "Guardian phone", f_paytype: "Payment type", bank_pay: "Bank transfer", cash_only: "Cash payment",
    f_amount: "Monthly amount", f_holder: "Account holder", f_bankname: "Bank", f_last4: "Last 4 digits of the account", waiting: "Waiting to send", child_saved: "Child saved",
    bank_all_or_none: "Fill in all three bank fields, or none."
  }, {
    people: "الأشخاص", search: "ابحث بالاسم أو الرقم المرجعي أو الوصي", all: "الكل", bank: "بنك", cash: "نقدًا", verified: "موثّق", notver: "غير موثّق",
    add_child: "إضافة طفل", add_title: "إضافة طفل", f_name_en: "الاسم (بالإنجليزية)", f_name_ar: "الاسم (بالعربية)", f_gender: "الجنس", male: "ذكر", female: "أنثى",
    f_dob: "تاريخ الميلاد", f_city: "المدينة", f_guardian: "اسم الوصي", f_phone: "هاتف الوصي", f_paytype: "طريقة الدفع", bank_pay: "حوالة بنكية", cash_only: "دفع نقدي",
    f_amount: "المبلغ الشهري", f_holder: "صاحب الحساب", f_bankname: "البنك", f_last4: "آخر 4 أرقام من الحساب", waiting: "بانتظار الإرسال", child_saved: "تم حفظ الطفل",
    bank_all_or_none: "املأ حقول البنك الثلاثة كلها أو اتركها فارغة."
  });

  var rows = [];
  function items() {
    var needle = q.trim().toLowerCase();
    var list = rows.filter(function (c) {
      if (A.ui.tab !== "all" && c.pay_type !== A.ui.tab) return false;
      return !needle || ((c.name_en || "") + " " + (c.name_ar || "") + " " + c.ref + " " + (c.guardian || "")).toLowerCase().indexOf(needle) > -1;
    });
    if (!list.length) return '<p class="note">' + t("none") + "</p>";
    return list.map(function (c) {
      var badge = c._pending ? '<span class="badge grey">' + t("waiting") + "</span>" : c.pay_type === "bank" ? (c.bank_verified ? '<span class="badge">' + t("verified") + "</span>" : '<span class="badge warn">' + t("notver") + "</span>") : '<span class="badge grey">' + t("cash") + "</span>";
      return '<a class="item" href="person.html?id=' + c.id + '"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><span class="grow"><b>' + esc(A.name(c)) + '</b><span class="sub">' + esc(c.ref) + " · " + esc(c.guardian || "-") + '</span></span><span class="right">' + badge + "</span></a>";
    }).join("");
  }
  function render(data) {
    rows = data;
    var n = { all: rows.length, bank: rows.filter(function (c) { return c.pay_type === "bank"; }).length, cash: rows.filter(function (c) { return c.pay_type === "cash"; }).length };
    return '<div class="head"><h1 class="title">' + t("people") + "</h1></div>" +
      '<div class="search">' + ic("search") + '<input id="q" type="search" placeholder="' + esc(t("search")) + '" value="' + esc(q) + '" aria-label="' + esc(t("search")) + '"></div>' +
      '<div class="filters" role="group">' + ["all", "bank", "cash"].map(function (k) { return '<button class="fbtn" type="button" data-act="tab" data-v="' + k + '" aria-pressed="' + (A.ui.tab === k) + '">' + t(k) + " (" + n[k] + ")</button>"; }).join("") + "</div>" +
      '<div class="list" id="plist">' + items() + "</div>" +
      (A.can("children.create") ? '<button class="btn btn-main fab" data-act="add">' + ic("plus") + t("add_child") + "</button>" : "");
  }
  function after(data, st) {
    var box = document.getElementById("q");
    box.addEventListener("input", function () { q = box.value; document.getElementById("plist").innerHTML = items(); });
    if (!st.stale) { // save every profile on the phone so each one also opens offline
      var ids = data.filter(function (c) { return c.id > 0; }).slice(0, 200).map(function (c) { return "children/" + c.id; });
      A.api.prefetch(ids, 900000);
    }
    if (/[?&]add=1/.test(location.search) && A.can("children.create") && !after.done) { after.done = true; openAdd(); }
  }

  function openAdd() {
    A.sheet(t("add_title"), '<form id="addForm" novalidate>' +
      A.field("f_name_en", t("f_name_en"), "", "text", 'dir="ltr" autocomplete="off"') + A.field("f_name_ar", t("f_name_ar"), "", "text", 'dir="rtl"') +
      '<div><label for="f_gender">' + t("f_gender") + '</label><select id="f_gender"><option value="">-</option><option value="f">' + t("female") + '</option><option value="m">' + t("male") + "</option></select></div>" +
      A.field("f_birth_date", t("f_dob"), "", "date") + A.field("f_city", t("f_city")) +
      A.field("f_guardian_name", t("f_guardian")) + A.field("f_guardian_phone", t("f_phone"), "", "tel", 'dir="ltr"') +
      '<div><label for="f_pay_type">' + t("f_paytype") + '</label><select id="f_pay_type"><option value="bank">' + t("bank_pay") + '</option><option value="cash">' + t("cash_only") + "</option></select></div>" +
      A.field("f_monthly_amount", t("f_amount") + " (" + A.CUR + ")", "", "number", 'min="0" step="1" inputmode="decimal"') +
      '<div id="bankBox">' + A.field("f_bank_holder", t("f_holder")) + A.field("f_bank_bank_name", t("f_bankname")) + A.field("f_bank_last4", t("f_last4"), "", "text", 'inputmode="numeric" maxlength="4" dir="ltr"') + "</div>" +
      A.formEnd() + "</form>");
    document.getElementById("f_pay_type").addEventListener("change", function () { document.getElementById("bankBox").style.display = this.value === "bank" ? "" : "none"; });
  }
  A.act("add", openAdd);

  function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ""; }
  A.form("addForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    var body = { name_en: v("f_name_en") };
    if (!body.name_en) { document.getElementById("f_name_enErr").textContent = t("required"); return; }
    ["name_ar", "gender", "birth_date", "city"].forEach(function (k) { if (v("f_" + k)) body[k] = v("f_" + k); });
    if (v("f_guardian_name")) { body.guardian = { name: v("f_guardian_name") }; if (v("f_guardian_phone")) body.guardian.phone = v("f_guardian_phone"); }
    body.pay_type = v("f_pay_type");
    if (v("f_monthly_amount")) body.monthly_amount = Number(v("f_monthly_amount"));
    if (body.pay_type === "bank") {
      var b = { holder: v("f_bank_holder"), bank_name: v("f_bank_bank_name"), last4: v("f_bank_last4") }, filled = [b.holder, b.bank_name, b.last4].filter(Boolean).length;
      if (filled && filled < 3) { document.getElementById("formErr").textContent = t("bank_all_or_none"); return; }
      if (filled === 3) body.bank = b;
    }
    var btn = document.querySelector("#addForm button[type=submit]"); btn.disabled = true;
    A.api.write("POST", "children", body, {
      label: t("add_child") + ": " + body.name_en,
      optimistic: function () {
        var item = { id: --tmp - Date.now() % 100000, ref: "…", name_en: body.name_en, name_ar: body.name_ar || null, guardian: body.guardian ? body.guardian.name : null, pay_type: body.pay_type,
          monthly_amount: body.monthly_amount || 0, bank_verified: body.bank ? false : null, status: "pending", _pending: true };
        A.api.patchCache("children?", function (p, key) { if (!/[?&]page=/.test(key)) { p.data = p.data.concat([item]); return p; } });
      }
    }).then(function (r) { A.closeSheet(); A.toast(r.queued ? t("phone_saved") : t("child_saved")); A.reload(true); }, function (e) {
      btn.disabled = false;
      if (!(e.errors && A.showErrors(e.errors, "f_"))) document.getElementById("formErr").textContent = A.msg(e);
    });
  });

  A.start({ page: "people", nav: "people", title: "n_people", perm: "children.view", shell: true, load: function () { return A.api.getEvery("children"); }, render: render, after: after });
})();
