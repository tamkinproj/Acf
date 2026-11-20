(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, facts = A.facts;
  var id = parseInt((location.search.match(/[?&]id=(\d+)/) || [])[1], 10) || 0;

  function render() {
    var c = A.child(id);
    var back = '<a class="btn btn-line btn-sm" href="people.html">' + ic("back", "flip") + t("back") + "</a>";
    if (!c) return back + '<p class="note" style="margin-top:1rem">' + t("none") + "</p>";
    var docs = ["d_birth", "d_death", "d_school", "d_id"].map(function (k) {
      return '<button class="item" type="button" data-act="docnote"><span class="grow"><b>' + t(k) + '</b></span><span class="badge ' + (c.docs[k] ? "" : "warn") + '">' + (c.docs[k] ? t("doc_on") : t("doc_missing")) + "</span></button>";
    }).join("");
    var bankCard = c.bank ?
      '<div class="card"><h3>' + t("sec_bank") + "</h3>" + facts([[t("f_holder"), esc(c.bank.holder)], [t("f_bankname"), esc(c.bank.name)], [t("f_last4"), "<span dir=ltr>•••• " + c.bank.last4 + "</span>"]]) +
      '<div class="setrow"><b>' + t("verify") + '</b><button class="switch" type="button" role="switch" aria-checked="' + c.bank.verified + '" aria-label="' + t("verify") + '" data-act="verify" data-id="' + c.id + '"></button></div></div>' :
      '<div class="card"><h3>' + t("sec_bank") + '</h3><p class="note">' + t("cash_only") + "</p></div>";
    return back +
      '<div class="card" style="margin:1rem 0"><div class="phead"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><div style="flex:1"><h1 class="title" style="font-size:1.7rem;margin:0">' + esc(A.name(c)) + '</h1><span class="note">' + c.ref + '</span></div><span class="badge">' + t("active") + "</span></div></div>" +
      '<div class="grid g2" style="align-items:start">' +
      '<div class="card"><h3>' + t("sec_personal") + "</h3>" + facts([[t("f_gender"), t(c.gender === "m" ? "male" : "female")], [t("f_dob"), c.dob], [t("f_city"), esc(c.city)], [t("f_health"), t(c.health)], [t("f_grade"), c.grade]]) + "</div>" +
      '<div class="card"><h3>' + t("sec_mother") + "</h3>" + facts([[t("f_mother"), esc(c.mother)], [t("f_mstatus"), t("widowed")], [t("f_fdeath"), c.fdeath], [t("f_bro"), c.bro], [t("f_sis"), c.sis]]) + "</div>" +
      '<div class="card"><h3>' + t("sec_resp") + "</h3>" + facts([[t("f_guardian"), esc(c.guardian)], [t("f_rel"), c.rel], [t("f_phone"), "<span dir=ltr>" + c.phone + "</span>"]]) + "</div>" +
      '<div class="card"><h3>' + t("sec_docs") + '</h3><div class="list">' + docs + "</div></div>" + bankCard +
      '<div class="card"><h3>' + t("sec_channel") + "</h3>" + facts([[t("f_channel"), A.chName(c.channel)], [t("f_source"), t(c.pay === "bank" ? "bank_pay" : "cash_only")], [t("f_since"), c.since], [t("f_amount"), A.money(c.amount) + " " + t("per_month")]]) +
      '<h3 style="margin-top:1rem;font-size:1rem">' + t("sec_hist") + "</h3>" + facts(c.hist.map(function (h) { return [h[0], A.chName(h[1])]; })) + "</div></div>";
  }
  function after() {
    var c = A.child(id);
    if (c) document.title = A.name(c) + " | " + t("brand");
  }
  A.start({ page: "person", nav: "people", title: "child_title", shell: true, render: render, after: after });
})();
