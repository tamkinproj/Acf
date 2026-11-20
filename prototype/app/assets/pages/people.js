(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, q = "";

  function items() {
    var needle = q.trim().toLowerCase();
    var rows = A.children.filter(function (c) {
      if (A.ui.tab !== "all" && c.pay !== A.ui.tab) return false;
      return !needle || (c.en + " " + c.ar + " " + c.ref + " " + c.guardian).toLowerCase().indexOf(needle) > -1;
    });
    if (!rows.length) return '<p class="note">' + t("none") + "</p>";
    return rows.map(function (c) {
      var badge = c.bank ? (c.bank.verified ? '<span class="badge">' + t("verified") + "</span>" : '<span class="badge warn">' + t("notver") + "</span>") : '<span class="badge grey">' + t("cash") + "</span>";
      return '<a class="item" href="person.html?id=' + c.id + '"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><span class="grow"><b>' + esc(A.name(c)) + '</b><span class="sub">' + c.ref + " · " + esc(c.guardian) + '</span></span><span class="right">' + badge + '<span class="amt">' + A.money(c.amount) + "</span></span></a>";
    }).join("");
  }
  function render() {
    var n = { all: A.children.length, bank: A.children.filter(function (c) { return c.pay === "bank"; }).length, cash: A.children.filter(function (c) { return c.pay === "cash"; }).length };
    return '<div class="head"><h1 class="title">' + t("people") + "</h1></div>" +
      '<div class="search">' + ic("search") + '<input id="q" type="search" placeholder="' + esc(t("search")) + '" value="' + esc(q) + '" aria-label="' + esc(t("search")) + '"></div>' +
      '<div class="filters" role="group">' + ["all", "bank", "cash"].map(function (k) {
        return '<button class="fbtn" type="button" data-act="tab" data-v="' + k + '" aria-pressed="' + (A.ui.tab === k) + '">' + t(k) + " (" + n[k] + ")</button>";
      }).join("") + '</div><div class="list" id="plist">' + items() + "</div>" +
      '<button class="btn btn-main fab" data-act="add">' + ic("plus") + t("add_child") + "</button>";
  }
  function after() {
    var box = document.getElementById("q");
    box.addEventListener("input", function () { q = box.value; document.getElementById("plist").innerHTML = items(); });
  }
  A.start({ page: "people", nav: "people", title: "n_people", shell: true, render: render, after: after });
})();
