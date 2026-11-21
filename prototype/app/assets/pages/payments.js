(function () {
  var A = ACF, t = A.t, esc = A.esc;
  var qm = (location.search.match(/[?&]month=(\d{4}-\d{2})/) || [])[1]; if (qm) { A.ui.month = qm; A.saveUI(); }   // a shared card opens the month it was shared for

  A.strings({
    pay_title: "Payments for the month", paid: "Paid", pending: "Pending", mark_cash: "Mark cash received", total_paid: "Total paid", total_due: "Total due",
    all: "All", bank: "Bank", cash: "Cash", cash_marked: "Marked as received", wait_pay: "Waiting to send", bank_pay: "Bank transfer", cash_only: "Cash payment"
  }, {
    pay_title: "مدفوعات الشهر", paid: "مدفوع", pending: "معلّق", mark_cash: "تأكيد استلام النقد", total_paid: "إجمالي المدفوع", total_due: "إجمالي المستحق",
    all: "الكل", bank: "بنك", cash: "نقدًا", cash_marked: "تم تسجيل الاستلام", wait_pay: "بانتظار الإرسال", bank_pay: "حوالة بنكية", cash_only: "دفع نقدي"
  });

  var queued = {}; // child ids marked while offline, this visit
  function pills() {
    return '<div class="filters" role="group">' + A.months().map(function (m) { return '<button class="fbtn" type="button" data-act="month" data-v="' + m.k + '" aria-pressed="' + (A.ui.month === m.k) + '">' + esc(m.label) + "</button>"; }).join("") + "</div>";
  }
  function load() {
    var m = A.ui.month;
    return A.api.get("payments?month=" + m).then(function (r) {
      if (!r.stale) A.api.prefetch(A.months().map(function (x) { return "payments?month=" + x.k; }), 900000); // the other months, for offline
      return r;
    });
  }
  function render(rows) {
    var list = rows.filter(function (r) { return A.ui.scope === "all" || r.method === A.ui.scope; });
    var paid = list.filter(function (r) { return r.status === "paid"; }), due = list.filter(function (r) { return r.status !== "paid"; });
    var sum = function (a) { return a.reduce(function (s, r) { return s + Number(r.amount); }, 0); };
    return '<div class="head"><h1 class="title">' + t("pay_title") + "</h1></div>" + pills() +
      '<div class="filters" role="group">' + ["all", "bank", "cash"].map(function (k) { return '<button class="fbtn" type="button" data-act="scope" data-v="' + k + '" aria-pressed="' + (A.ui.scope === k) + '">' + t(k) + "</button>"; }).join("") + "</div>" +
      '<div class="grid g2"><div class="card stat"><div class="num">' + A.money(sum(paid)) + '</div><div class="lab">' + t("total_paid") + '</div></div><div class="card stat"><div class="num">' + A.money(sum(due)) + '</div><div class="lab">' + t("total_due") + "</div></div></div>" +
      '<div class="list" style="margin-top:1rem">' + (list.length ? list.map(function (r) {
        var isPaid = r.status === "paid", right = isPaid ? '<span class="badge">' + t("paid") + "</span>" : '<span class="badge warn">' + t("pending") + "</span>";
        var act = !isPaid && r.method === "cash" && A.can("payments.mark_cash") ? '<button class="btn btn-line btn-sm" data-act="cash" data-id="' + r.child_id + '">' + t("mark_cash") + "</button>" : "";
        var wait = queued[r.child_id] ? '<span class="badge grey">' + t("wait_pay") + "</span>" : "";
        return '<div class="item" data-focus="' + r.child_id + '"><a class="avatar" href="person.html?id=' + r.child_id + '" aria-label="' + esc(A.name(r)) + '" style="text-decoration:none">' + esc(A.name(r).charAt(0)) + '</a><span class="grow"><b>' + esc(A.name(r)) + '</b><span class="sub">' + esc(r.ref) + " · " + t(r.method === "bank" ? "bank_pay" : "cash_only") + " · " + A.money(r.amount) + '</span></span><span class="right">' + right + wait + act + A.shareBtn("payment", r.child_id, A.ui.month, true) + "</span></div>";
      }).join("") : '<p class="note">' + t("none") + "</p>") + "</div>";
  }

  A.act("cash", function (el) {
    var cid = parseInt(el.getAttribute("data-id"), 10), month = A.ui.month; el.disabled = true;
    A.api.write("POST", "payments/" + cid + "/mark-cash", { month: month }, {
      label: t("mark_cash") + " #" + cid,
      optimistic: function () {
        A.api.patchCache("payments?month=" + month, function (p) { if (Array.isArray(p.data)) { p.data.forEach(function (r) { if (r.child_id === cid) r.status = "paid"; }); return p; } });
      }
    }).then(function (r) { if (r.queued) queued[cid] = true; A.toast(r.queued ? t("phone_saved") : t("cash_marked")); A.reload(true); }, function (e) { el.disabled = false; A.toast(A.msg(e)); });
  });

  A.start({ page: "payments", nav: "payments", title: "n_pay", perm: "payments.view", shell: true, load: load, render: render });
})();
