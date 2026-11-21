(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, tmp = 0;

  A.strings({
    add_child: "Add orphan", add_title: "Add orphan", child_saved: "Orphan saved", p_back: "Back", add_more: "More ways to add", add_more_note: "Many orphans at once, or sign-in accounts for the orphans already saved."
  }, {
    add_child: "إضافة يتيم", add_title: "إضافة يتيم", child_saved: "تم حفظ اليتيم", p_back: "رجوع", add_more: "طرق أخرى للإضافة", add_more_note: "عدة أيتام دفعة واحدة، أو حسابات دخول للأيتام المحفوظين."
  });

  function render() {
    var more = "";
    if (A.can("children.create")) more += '<button class="btn btn-line btn-sm" type="button" data-act="importxl">' + ic("download") + t("imp_btn") + "</button>";
    if (A.can("orphans.accounts")) more += '<button class="btn btn-line btn-sm" type="button" data-act="accounts">' + ic("users") + t("acc_btn") + "</button>";
    return '<div class="dp-bar"><a class="dp-back" href="people.html" data-act="goback" aria-label="' + t("p_back") + '">' + ic("back", "flip") + '</a><span class="dp-title">' + t("add_title") + "</span></div>" +
      '<div class="addwrap"><form id="addForm" novalidate>' + A.orphan.formHtml(null, null, true) +
      '<div class="err" id="formErr" role="alert"></div><div class="actions addwrap-actions"><a class="btn btn-line" href="people.html">' + t("cancel") + '</a><button class="btn btn-main" type="submit">' + t("save") + "</button></div></form>" +
      (more ? '<section class="fpage"><h3>' + t("add_more") + '</h3><p class="note" style="margin:0 0 .8rem">' + t("add_more_note") + '</p><div class="actions">' + more + "</div></section>" : "") + "</div>";
  }
  function wire() {
    A.wizard(document.getElementById("addForm"), {
      steps: ".fpage", page: true, cancelHref: "people.html", create: "save",
      validate: function (i, step) {
        if (step.querySelector("#f_name_en") && !document.getElementById("f_name_en").value.trim() && !document.getElementById("f_name_ar").value.trim()) { document.getElementById("f_name_enErr").textContent = t("required"); return false; }
        return true;
      }
    });
  }
  A.act("goback", function (el, e) { if (document.referrer && document.referrer.indexOf(location.origin) === 0 && history.length > 1) { e.preventDefault(); history.back(); } });

  A.form("addForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll(".addwrap .err"), function (e) { e.textContent = ""; });
    var body = A.orphan.readBody(false);
    if (body.error) {
      var box = document.getElementById(body.error[0] === "formErr" ? "formErr" : body.error[0] + "Err"); if (box) box.textContent = body.error[1];
      var f = document.getElementById(body.error[0] === "formErr" ? "f_bank_holder" : body.error[0]); A.wizardTo(document.getElementById("addForm"), f); if (f && f.scrollIntoView) { f.scrollIntoView({ block: "center" }); }
      return;
    }
    var btn = document.querySelector("#addForm button[type=submit]"); btn.disabled = true;
    A.api.write("POST", "children", body, {
      label: t("add_child") + ": " + body.name_en,
      optimistic: function () {
        var item = { id: --tmp - Date.now() % 100000, ref: "…", name_en: body.name_en, name_ar: body.name_ar || null, guardian: body.guardian ? body.guardian.name : null, pay_type: body.pay_type,
          monthly_amount: 0, bank_verified: body.bank ? false : null, status: "pending", _pending: true };
        A.api.patchCache("children?", function (p, key) { if (!/[?&]page=/.test(key)) { p.data = p.data.concat([item]); return p; } });
      }
    }).then(function (r) { A.toast(r.queued ? t("phone_saved") : t("child_saved")); setTimeout(function () { location.replace("people.html"); }, 700); }, function (e) {
      btn.disabled = false;
      if (!(e.errors && A.showErrors(e.errors, "f_"))) document.getElementById("formErr").textContent = A.msg(e);
      A.wizardReveal(document.getElementById("addForm"));
    });
  });

  A.start({ page: "add", nav: "people", title: "add_title", perm: "children.create", shell: true, bare: true, load: function () { return Promise.resolve({ data: {} }); }, render: render, after: wire });
})();
