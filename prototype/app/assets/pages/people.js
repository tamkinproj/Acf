(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, q = (function () { try { return new URLSearchParams(location.search).get("q") || ""; } catch (e) { return ""; } })(), tmp = 0, TABS = ["all", "active", "pending", "ended"];

  A.strings({
    people: "Orphans", search: "Search name or number", all: "All", unverified: "Bank not verified",
    sort_btn: "Sort and filter", sort_show: "Show", sort_by: "Sort by", sort_ref: "Reference", sort_name: "Name (A to Z)", sort_new: "Newest first", sort_no: "Orphan number", sort_apply: "Done",
    add_child: "Add orphan", add_title: "Add orphan", waiting: "Waiting to send", child_saved: "Orphan saved", yrs: "{n} yrs", total_n: "{n} orphans", sample_tag: "Sample", rm_samples: "Remove sample orphans", rm_q: "Remove all {n} sample orphans? Real records are not touched. This cannot be undone.", rm_done: "Sample orphans removed.", apps_btn: "Applications", q_edit: "Edit", q_saved: "Saved", q_conn: "Editing needs a connection."
  }, {
    people: "الأيتام", search: "ابحث بالاسم أو الرقم", all: "الكل", unverified: "الحساب البنكي غير موثّق",
    sort_btn: "ترتيب وتصفية", sort_show: "عرض", sort_by: "الترتيب حسب", sort_ref: "الرقم المرجعي", sort_name: "الاسم (أ إلى ي)", sort_new: "الأحدث أولًا", sort_no: "رقم اليتيم", sort_apply: "تم",
    add_child: "إضافة يتيم", add_title: "إضافة يتيم", waiting: "بانتظار الإرسال", child_saved: "تم حفظ اليتيم", yrs: "{n} سنة", total_n: "{n} يتيمًا", sample_tag: "تجريبي", rm_samples: "احذف الأيتام التجريبيين", rm_q: "حذف كل الأيتام التجريبيين ({n})؟ لن تُمس السجلات الحقيقية. لا يمكن التراجع.", rm_done: "تم حذف الأيتام التجريبيين.", apps_btn: "الطلبات", q_edit: "تعديل", q_saved: "تم الحفظ", q_conn: "التعديل يحتاج اتصالًا."
  });

  var rows = [];
  function tab() { return TABS.indexOf(A.ui.tab) > -1 ? A.ui.tab : "all"; }   // an older saved filter (bank/cash) just means "all"
  /* Arabic and English compared loosely: no vowel marks, one kind of alef / ya / ta marbuta, Arabic digits as 0-9, any word order */
  function nrm(x) {
    return String(x == null ? "" : x).toLowerCase().replace(/[\u064B-\u065F\u0670\u0640]/g, "").replace(/[\u0622\u0623\u0625\u0671]/g, "\u0627").replace(/\u0649/g, "\u064A").replace(/\u0629/g, "\u0647")
      .replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x660); }).replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x6F0); }).replace(/\s+/g, " ").trim();
  }
  function sorted(list) {
    var how = A.ui.sort || "ref", lg = A.lang() === "ar" ? "ar" : "en";
    var l = list.slice();
    if (how === "name") l.sort(function (a, b) { return (A.name(a) || "").localeCompare(A.name(b) || "", lg); });
    else if (how === "new") l.sort(function (a, b) { return (b.id > 0 ? b.id : 1e12) - (a.id > 0 ? a.id : 1e12); });
    else if (how === "no") l.sort(function (a, b) { return (parseInt(a.external_no, 10) || 1e12) - (parseInt(b.external_no, 10) || 1e12); });
    return l;
  }
  function items() {
    var words = nrm(q).split(" ").filter(Boolean), cur = tab();
    var list = sorted(rows.filter(function (c) {
      if (cur !== "all" && (c.status || "active") !== cur) return false;
      if (!words.length) return true;
      var hay = nrm((c.name_en || "") + " " + (c.name_ar || "") + " " + c.ref + " " + (c.external_no || "") + " " + (c.guardian || "") + " " + (c.account || ""));
      return words.every(function (w) { return hay.indexOf(w) > -1; });
    }));
    if (!list.length) return '<p class="note">' + t("none") + "</p>";
    return list.map(function (c) {
      var inner = '<span class="avatar" data-id="' + c.id + '"' + (c.photo ? ' data-photo="' + c.photo + '"' : "") + ">" + esc(String(A.name(c) || "?").charAt(0)) + '</span><span class="orow-name">' + esc(A.name(c)) + "</span>" +
        (c._pending ? '<span class="badge grey">' + t("waiting") + "</span>" : ic("chev", "ios-chev"));
      return c.id > 0 ? '<a class="orow-card" href="person.html?id=' + c.id + '">' + inner + "</a>" : '<div class="orow-card">' + inner + "</div>";
    }).join("");
  }
  /* profile pictures load only for the rows on screen */
  var picUrls = {}, picIO = null;
  function showPic(av) {
    var id = av.getAttribute("data-id"), pid = av.getAttribute("data-photo"); if (!pid) return;
    var apply = function (u) { av.style.backgroundImage = 'url("' + u + '")'; av.classList.add("has-photo"); };
    if (picUrls[pid]) { apply(picUrls[pid]); return; }
    A.api.blobUrl("children/" + id + "/photos/" + pid).then(function (u) { picUrls[pid] = u; apply(u); }, function () {});
  }
  function watchPics() {
    var list = document.querySelectorAll("#plist .avatar[data-photo]"); if (!list.length) return;
    if (!("IntersectionObserver" in window)) return;
    if (picIO) picIO.disconnect();
    picIO = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { picIO.unobserve(e.target); showPic(e.target); } }); }, { rootMargin: "200px" });
    Array.prototype.forEach.call(list, function (el) { picIO.observe(el); });
  }
  function tools() {
    var samples = rows.filter(function (c) { return c.is_sample; }).length, out = "";
    if (samples && A.can("children.update")) out += '<button class="btn btn-line btn-sm" type="button" data-act="rmsamples">' + t("rm_samples") + " (" + samples + ")</button>";
    return out ? '<div class="actions" style="margin-bottom:1rem">' + out + "</div>" : "";
  }
  A.act("rmsamples", function () {
    var n = rows.filter(function (c) { return c.is_sample; }).length;
    if (!confirm(t("rm_q", { n: n }))) return;
    A.api.write("DELETE", "children/samples", undefined, { online: true }).then(function () { A.toast(t("rm_done")); A.api.wipeCache(); A.reload(true); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });

  A.act("sortmenu", function () {
    var cur = tab(), how = A.ui.sort || "ref", n = { all: rows.length };
    ["active", "pending", "ended"].forEach(function (k) { n[k] = rows.filter(function (c) { return (c.status || "active") === k; }).length; });
    var opt = function (kind, k, label, on) { return '<button class="opt' + (on ? " on" : "") + '" type="button" data-act="setview" data-kind="' + kind + '" data-v="' + k + '"><span>' + label + "</span>" + (on ? ic("check") : "") + "</button>"; };
    A.sheet(t("sort_btn"), '<h4 class="optlbl">' + t("sort_show") + '</h4><div class="opts">' + TABS.map(function (k) { return opt("tab", k, t(k) + " (" + n[k] + ")", cur === k); }).join("") + "</div>" +
      '<h4 class="optlbl">' + t("sort_by") + '</h4><div class="opts">' + [["ref", "sort_ref"], ["name", "sort_name"], ["new", "sort_new"], ["no", "sort_no"]].map(function (o) { return opt("sort", o[0], t(o[1]), how === o[0]); }).join("") + "</div>");
  });
  A.act("setview", function (el) {
    var kind = el.getAttribute("data-kind"), v = el.getAttribute("data-v");
    if (kind === "tab") A.ui.tab = v; else A.ui.sort = v;
    A.saveUI(); A.closeSheet(); A.paint();
  });
  function render(data) {
    rows = data; var cur = tab();
    var n = { all: rows.length }; ["active", "pending", "ended"].forEach(function (k) { n[k] = rows.filter(function (c) { return (c.status || "active") === k; }).length; });
    return '<div class="head"><h1 class="title">' + t("people") + '</h1><p class="lead">' + t("total_n", { n: rows.length }) + "</p></div>" + tools() +
      '<div class="sbar"><div class="search">' + ic("search") + '<input id="q" type="search" placeholder="' + esc(t("search")) + '" value="' + esc(q) + '" aria-label="' + esc(t("search")) + '" autocomplete="off"></div>' +
      '<button class="sortbtn" type="button" data-act="sortmenu" aria-label="' + esc(t("sort_btn")) + '">' + ic("sliders") + (cur !== "all" || (A.ui.sort && A.ui.sort !== "ref") ? '<i class="sdot"></i>' : "") + "</button></div>" +
      '<div class="list" id="plist">' + items() + "</div>" +
      (A.can("children.create") ? '<a class="btn btn-main fab" href="add.html">' + ic("plus") + t("add_child") + "</a>" : "");
  }
  function after(data, st) {
    var box = document.getElementById("q");
    box.addEventListener("input", function () { q = box.value; document.getElementById("plist").innerHTML = items(); watchPics(); });
    watchPics();
    if (!st.stale) { // save every profile on the phone so each one also opens offline
      var ids = data.filter(function (c) { return c.id > 0; }).slice(0, 200).map(function (c) { return "children/" + c.id; });
      A.api.prefetch(ids, 900000);
    }
    if (/[?&]add=1/.test(location.search) && A.can("children.create")) { location.replace("add.html"); }
  }



  A.start({ page: "people", nav: "people", title: "n_people", perm: "children.view", shell: true, load: function () { return A.api.getEvery("children"); }, render: render, after: after });
})();
