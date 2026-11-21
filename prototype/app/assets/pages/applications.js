(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, TABS = ["new", "approved", "rejected"], cur = "new";

  A.strings({
    ap_title: "Applications", ap_sub: "Sent through the public form. Nothing becomes a record until you approve it.", ap_link: "Application form link",
    ap_link_note: "Send this link to families. Anyone who has it can fill in the form, no sign-in needed. Photos and answers are private to the Aytam staff.",
    ap_copy: "Copy link", ap_copied: "Link copied", ap_share: "Share", ap_whatsapp: "WhatsApp", ap_open: "Open form",
    ap_new: "New", ap_approved: "Approved", ap_rejected: "Rejected", ap_none: "No applications here yet.", ap_photos: "{n} photos", ap_msg: "Apply for orphan support: "
  }, {
    ap_title: "الطلبات", ap_sub: "وصلت عبر النموذج العام. لا يصبح أي طلب سجلًا إلا بعد موافقتك.", ap_link: "رابط نموذج التقديم",
    ap_link_note: "أرسل هذا الرابط للأسر. يستطيع أي شخص لديه الرابط تعبئة النموذج دون تسجيل دخول. الصور والإجابات خاصة بموظفي الأيتام.",
    ap_copy: "انسخ الرابط", ap_copied: "تم نسخ الرابط", ap_share: "مشاركة", ap_whatsapp: "واتساب", ap_open: "افتح النموذج",
    ap_new: "جديد", ap_approved: "مقبول", ap_rejected: "مرفوض", ap_none: "لا طلبات هنا بعد.", ap_photos: "{n} صور", ap_msg: "قدّم طلب كفالة يتيم: "
  });

  var data = { items: [], counts: {} };
  function link() { return location.origin + "/apply"; }
  function list() {
    var items = data.items.filter(function (a) { return a.status === cur; });
    if (!items.length) return '<p class="note">' + t("ap_none") + "</p>";
    return items.map(function (a) {
      var nm = A.lang() === "ar" && a.name_ar ? a.name_ar : (a.name_en || a.name_ar || "-");
      return A.fold({ id: a.id, av: nm, title: nm, tone: a.status === "new" ? "warn" : a.status === "approved" ? "ok" : "grey",
        meta: '<span dir="ltr">' + esc(a.reference) + " · " + esc(a.submitted_at || "") + (a.photos ? " · " + t("ap_photos", { n: a.photos }) : "") + "</span>",
        badges: '<span class="badge ' + (a.status === "new" ? "warn" : a.status === "approved" ? "" : "grey") + '">' + t("ap_" + a.status) + "</span>",
        actions: '<a class="btn btn-main btn-sm" href="application.html?id=' + a.id + '">' + t("fold_open") + "</a>" + A.shareBtn("application", a.id) });
    }).join("");
  }
  function render(d) {
    data = d; var n = d.counts || {};
    if (!n[cur] && n.new === 0 && cur === "new" && (n.approved || n.rejected) && !render.shown) cur = "new";
    render.shown = true;
    return '<div class="head"><h1 class="title">' + t("ap_title") + '</h1><p class="lead">' + t("ap_sub") + "</p></div>" +
      '<div class="card linkcard"><h3>' + t("ap_link") + '</h3><p class="note" style="margin-top:0">' + t("ap_link_note") + '</p><input id="applyLink" type="text" readonly dir="ltr" value="' + esc(link()) + '" aria-label="' + esc(t("ap_link")) + '">' +
      '<div class="actions" style="margin-top:.8rem"><button class="btn btn-main" type="button" data-act="copylink">' + ic("download") + t("ap_copy") + '</button>' +
      '<button class="btn btn-line" type="button" data-act="sharelink">' + t("ap_share") + '</button><a class="btn btn-line" target="_blank" rel="noopener" href="https://wa.me/?text=' + encodeURIComponent(t("ap_msg") + link()) + '">' + t("ap_whatsapp") + '</a><a class="btn btn-line" target="_blank" rel="noopener" href="' + esc(link()) + '">' + ic("globe") + t("ap_open") + "</a></div></div>" +
      '<div class="filters" role="group" style="margin-top:1rem">' + TABS.map(function (k) { return '<button class="fbtn" type="button" data-act="aptab" data-v="' + k + '" aria-pressed="' + (cur === k) + '">' + t("ap_" + k) + " (" + (n[k] || 0) + ")</button>"; }).join("") + "</div>" +
      '<div class="list" id="aplist">' + list() + "</div>";
  }
  A.act("aptab", function (el) { cur = el.getAttribute("data-v"); A.paint(); });
  A.act("copylink", function () {
    var inp = document.getElementById("applyLink");
    (navigator.clipboard ? navigator.clipboard.writeText(link()) : Promise.reject()).then(function () { A.toast(t("ap_copied")); }, function () { inp.select(); inp.setSelectionRange(0, 999); try { document.execCommand("copy"); A.toast(t("ap_copied")); } catch (e) {} });
  });
  A.act("sharelink", function () {
    if (navigator.share) navigator.share({ title: t("ap_title"), text: t("ap_msg"), url: link() }).catch(function () {});
    else A.toast(link());
  });
  A.start({ page: "applications", nav: "applications", title: "ap_title", perm: "applications.review", needsConn: true, shell: true, load: function () { return A.api.get("applications", { cache: false }); }, render: render });
})();
