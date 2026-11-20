(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  function render() {
    var m = A.ui.month;
    return '<div class="head"><h1 class="title">' + t("pay_title") + "</h1></div>" + A.monthPills() +
      '<div class="grid g2"><div class="card stat"><div class="num">' + A.money(A.sum(m, "paid")) + '</div><div class="lab">' + t("total_paid") + '</div></div><div class="card stat"><div class="num">' + A.money(A.sum(m, "pending")) + '</div><div class="lab">' + t("total_due") + "</div></div></div>" +
      '<div class="list" style="margin-top:1rem">' + A.children.map(function (c) {
        var st = A.pay[m][c.id], right = st === "paid" ? '<span class="badge">' + t("paid") + "</span>" : '<span class="badge warn">' + t("pending") + "</span>";
        var act = st === "pending" && c.pay === "cash" ? '<button class="btn btn-line btn-sm" data-act="cash" data-id="' + c.id + '">' + t("mark_cash") + "</button>" : "";
        return '<div class="item"><a class="avatar" href="person.html?id=' + c.id + '" aria-label="' + esc(A.name(c)) + '" style="text-decoration:none">' + esc(A.name(c).charAt(0)) + '</a><span class="grow"><b>' + esc(A.name(c)) + '</b><span class="sub">' + c.ref + " · " + t(c.pay) + '</span></span><span class="right">' + right + '<span class="amt">' + A.money(c.amount) + "</span>" + act + "</span></div>";
      }).join("") + "</div>";
  }
  A.start({ page: "payments", nav: "payments", title: "n_pay", shell: true, render: render });
})();
