(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, q = "", tmp = 0, TABS = ["all", "active", "pending", "ended"];

  A.strings({
    people: "Orphans", search: "Search name, reference or guardian", all: "All", unverified: "Bank not verified",
    add_child: "Add orphan", add_title: "Add orphan", waiting: "Waiting to send", child_saved: "Orphan saved", yrs: "{n} yrs", total_n: "{n} orphans"
  }, {
    people: "الأيتام", search: "ابحث بالاسم أو الرقم المرجعي أو الوصي", all: "الكل", unverified: "الحساب البنكي غير موثّق",
    add_child: "إضافة يتيم", add_title: "إضافة يتيم", waiting: "بانتظار الإرسال", child_saved: "تم حفظ اليتيم", yrs: "{n} سنة", total_n: "{n} يتيمًا"
  });

  var rows = [];
  function tab() { return TABS.indexOf(A.ui.tab) > -1 ? A.ui.tab : "all"; }   // an older saved filter (bank/cash) just means "all"
  function items() {
    var needle = q.trim().toLowerCase(), cur = tab();
    var list = rows.filter(function (c) {
      if (cur !== "all" && (c.status || "active") !== cur) return false;
      return !needle || ((c.name_en || "") + " " + (c.name_ar || "") + " " + c.ref + " " + (c.guardian || "")).toLowerCase().indexOf(needle) > -1;
    });
    if (!list.length) return '<p class="note">' + t("none") + "</p>";
    return list.map(function (c) {
      var st = c._pending ? '<span class="badge grey">' + t("waiting") + "</span>" : '<span class="badge ' + ((c.status || "active") === "active" ? "" : (c.status === "ended" ? "grey" : "warn")) + '">' + t(c.status || "active") + "</span>";
      var bits = [esc(c.ref)]; if (c.age !== null && c.age !== undefined) bits.push(t("yrs", { n: c.age })); if (c.guardian) bits.push(esc(c.guardian));
      return '<a class="item" href="person.html?id=' + c.id + '"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><span class="grow"><b>' + esc(A.name(c)) + '</b><span class="sub">' + bits.join(" · ") + '</span></span><span class="right">' + st +
        (c.pay_type === "bank" && c.bank_verified === false ? '<span class="badge warn" title="' + esc(t("unverified")) + '" aria-label="' + esc(t("unverified")) + '">!</span>' : "") + "</span></a>";
    }).join("");
  }
  function render(data) {
    rows = data; var cur = tab();
    var n = { all: rows.length }; ["active", "pending", "ended"].forEach(function (k) { n[k] = rows.filter(function (c) { return (c.status || "active") === k; }).length; });
    return '<div class="head"><h1 class="title">' + t("people") + '</h1><p class="lead">' + t("total_n", { n: rows.length }) + "</p></div>" +
      '<div class="search">' + ic("search") + '<input id="q" type="search" placeholder="' + esc(t("search")) + '" value="' + esc(q) + '" aria-label="' + esc(t("search")) + '"></div>' +
      '<div class="filters" role="group">' + TABS.map(function (k) { return '<button class="fbtn" type="button" data-act="tab" data-v="' + k + '" aria-pressed="' + (cur === k) + '">' + t(k) + " (" + n[k] + ")</button>"; }).join("") + "</div>" +
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
    A.sheet(t("add_title"), '<form id="addForm" novalidate>' + A.orphan.formHtml() + A.formEnd() + "</form>");
    A.orphan.wire();
  }
  A.act("add", openAdd);

  A.form("addForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    var body = A.orphan.readBody(false);
    if (body.error) { document.getElementById(body.error[0] === "formErr" ? "formErr" : body.error[0] + "Err").textContent = body.error[1]; return; }
    var btn = document.querySelector("#addForm button[type=submit]"); btn.disabled = true;
    A.api.write("POST", "children", body, {
      label: t("add_child") + ": " + body.name_en,
      optimistic: function () {
        var item = { id: --tmp - Date.now() % 100000, ref: "…", name_en: body.name_en, name_ar: body.name_ar || null, guardian: body.guardian ? body.guardian.name : null, pay_type: body.pay_type,
          monthly_amount: 0, bank_verified: body.bank ? false : null, status: "pending", _pending: true };
        A.api.patchCache("children?", function (p, key) { if (!/[?&]page=/.test(key)) { p.data = p.data.concat([item]); return p; } });
      }
    }).then(function (r) { A.closeSheet(); A.toast(r.queued ? t("phone_saved") : t("child_saved")); A.reload(true); }, function (e) {
      btn.disabled = false;
      if (!(e.errors && A.showErrors(e.errors, "f_"))) document.getElementById("formErr").textContent = A.msg(e);
    });
  });

  A.start({ page: "people", nav: "people", title: "n_people", perm: "children.view", shell: true, load: function () { return A.api.getEvery("children"); }, render: render, after: after });
})();
