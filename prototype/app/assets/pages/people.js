(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, q = (function () { try { return new URLSearchParams(location.search).get("q") || ""; } catch (e) { return ""; } })(), tmp = 0, TABS = ["all", "active", "pending", "ended"];

  A.strings({
    people: "Orphans", search: "Search name, reference or guardian", all: "All", unverified: "Bank not verified",
    add_child: "Add orphan", add_title: "Add orphan", waiting: "Waiting to send", child_saved: "Orphan saved", yrs: "{n} yrs", total_n: "{n} orphans", sample_tag: "Sample", rm_samples: "Remove sample orphans", rm_q: "Remove all {n} sample orphans? Real records are not touched. This cannot be undone.", rm_done: "Sample orphans removed.", apps_btn: "Applications", q_edit: "Edit", q_saved: "Saved", q_conn: "Editing needs a connection."
  }, {
    people: "الأيتام", search: "ابحث بالاسم أو الرقم المرجعي أو الوصي", all: "الكل", unverified: "الحساب البنكي غير موثّق",
    add_child: "إضافة يتيم", add_title: "إضافة يتيم", waiting: "بانتظار الإرسال", child_saved: "تم حفظ اليتيم", yrs: "{n} سنة", total_n: "{n} يتيمًا", sample_tag: "تجريبي", rm_samples: "احذف الأيتام التجريبيين", rm_q: "حذف كل الأيتام التجريبيين ({n})؟ لن تُمس السجلات الحقيقية. لا يمكن التراجع.", rm_done: "تم حذف الأيتام التجريبيين.", apps_btn: "الطلبات", q_edit: "تعديل", q_saved: "تم الحفظ", q_conn: "التعديل يحتاج اتصالًا."
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
      var tone = c._pending || c.status === "ended" ? "grey" : (c.status || "active") === "active" ? "ok" : "warn";
      return A.fold({ id: c.id, av: A.name(c), title: A.name(c), tone: tone, meta: '<span dir="ltr">' + bits.join(" · ") + "</span>",
        badges: (c.is_sample ? '<span class="badge grey">' + t("sample_tag") + "</span>" : "") + st + (c.pay_type === "bank" && c.bank_verified === false ? '<span class="badge warn" title="' + esc(t("unverified")) + '">! ' + esc(t("unverified")) + "</span>" : ""),
        actions: (c.id > 0 ? '<a class="btn btn-main btn-sm" href="person.html?id=' + c.id + '">' + t("fold_open_profile") + "</a>" : "") + (c.id > 0 ? A.shareBtn("child", c.id) : "") +
          (A.can("children.update") && c.id > 0 ? '<button class="btn btn-line btn-sm" type="button" data-act="quickedit" data-id="' + c.id + '">' + ic("edit") + "<span>" + t("q_edit") + "</span></button>" : "") });
    }).join("");
  }
  function tools() {
    var samples = rows.filter(function (c) { return c.is_sample; }).length, out = "";
    if (A.can("applications.review")) out += '<a class="btn btn-line btn-sm" href="applications.html">' + ic("file") + t("apps_btn") + "</a>";
    if (samples && A.can("children.update")) out += '<button class="btn btn-line btn-sm" type="button" data-act="rmsamples">' + t("rm_samples") + " (" + samples + ")</button>";
    return out ? '<div class="actions" style="margin-bottom:1rem">' + out + "</div>" : "";
  }
  A.act("rmsamples", function () {
    var n = rows.filter(function (c) { return c.is_sample; }).length;
    if (!confirm(t("rm_q", { n: n }))) return;
    A.api.write("DELETE", "children/samples", undefined, { online: true }).then(function () { A.toast(t("rm_done")); A.api.wipeCache(); A.reload(true); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });
  function render(data) {
    rows = data; var cur = tab();
    var n = { all: rows.length }; ["active", "pending", "ended"].forEach(function (k) { n[k] = rows.filter(function (c) { return (c.status || "active") === k; }).length; });
    return '<div class="head"><h1 class="title">' + t("people") + '</h1><p class="lead">' + t("total_n", { n: rows.length }) + "</p></div>" + tools() +
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

  /* edit one orphan straight from the list */
  var qid = 0;
  A.act("quickedit", function (el) {
    qid = +el.getAttribute("data-id"); el.disabled = true;
    A.api.get("children/" + qid).then(function (r) {
      el.disabled = false;
      A.sheet(t("q_edit") + ": " + A.name(r.data), '<form id="qeForm" novalidate>' + A.orphan.formHtml(r.data) + A.formEnd() + "</form>");
    }, function (e) { el.disabled = false; A.toast(e.offline ? t("q_conn") : A.msg(e)); });
  });
  A.form("qeForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    var body = A.orphan.readBody(true);
    if (body.error) { document.getElementById(body.error[0] === "formErr" ? "formErr" : body.error[0] + "Err").textContent = body.error[1]; return; }
    var btn = document.querySelector("#qeForm button[type=submit]"); btn.disabled = true;
    A.api.write("PATCH", "children/" + qid, body, { online: true }).then(function () { A.closeSheet(); A.toast(t("q_saved")); A.reload(true); }, function (e) {
      btn.disabled = false;
      if (e.offline) { document.getElementById("formErr").textContent = t("q_conn"); return; }
      if (!(e.errors && A.showErrors(e.errors, "f_"))) document.getElementById("formErr").textContent = A.msg(e);
    });
  });

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
