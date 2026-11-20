(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  function render() {
    var m = A.ui.month, kids = A.children;
    var ver = kids.filter(function (c) { return c.bank && c.bank.verified; }).length;
    var banks = kids.filter(function (c) { return c.bank; }).length;
    var attn = [];
    kids.forEach(function (c) {
      if (c.bank && !c.bank.verified) attn.push({ c: c, msg: t("unverified") });
      if (A.pay[m][c.id] === "pending") attn.push({ c: c, msg: t("pending_pay") + " · " + A.monthLabel(m) });
    });
    return '<div class="head"><h1 class="title">' + t("hello") + '</h1><p class="lead">' + t("hello_sub") + "</p></div>" +
      '<div class="grid g4">' +
      '<div class="card stat">' + ic("users") + '<div class="num">' + kids.length + '</div><div class="lab">' + t("s_children") + "</div></div>" +
      '<div class="card stat">' + ic("shield") + '<div class="num">' + ver + "<span>/ " + banks + '</span></div><div class="lab">' + t("s_verified") + "</div></div>" +
      '<div class="card stat">' + ic("check") + '<div class="num">' + A.paidCount(m) + "<span>/ " + kids.length + '</span></div><div class="lab">' + t("s_paid") + "</div></div>" +
      '<div class="card stat">' + ic("wallet") + '<div class="num">' + (kids.length - A.paidCount(m)) + '</div><div class="lab">' + t("s_pending") + "</div></div></div>" +
      '<div class="grid g2" style="margin-top:1rem;align-items:start">' +
      '<div class="card"><h3>' + t("attn") + "</h3>" + (attn.length ? '<div class="list">' + attn.slice(0, 6).map(function (a) {
        return '<a class="item" href="person.html?id=' + a.c.id + '"><span class="avatar">' + esc(A.name(a.c).charAt(0)) + '</span><span class="grow"><b>' + esc(A.name(a.c)) + '</b><span class="sub">' + esc(a.msg) + '</span></span><span class="badge warn">!</span></a>';
      }).join("") + "</div>" : '<p class="note">' + t("attn_none") + "</p>") + "</div>" +
      '<div class="card"><h3>' + t("quick") + '</h3><div class="actions">' +
      '<button class="btn btn-main" data-act="add">' + ic("plus") + t("add_child") + "</button>" +
      '<button class="btn btn-line" data-act="csv-people">' + ic("download") + t("export_csv") + "</button>" +
      '<a class="btn btn-line" href="reports.html">' + ic("file") + t("month_rep") + "</a></div>" +
      '<p class="note" style="margin-top:1rem">' + t("set_data") + "</p></div></div>";
  }
  A.start({ page: "dashboard", nav: "dashboard", title: "n_home", shell: true, render: render });
})();
