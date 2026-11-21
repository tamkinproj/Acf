(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  var id = parseInt((location.search.match(/[?&]id=(\d+)/) || [])[1], 10) || 0, current = null;

  A.strings({
    ad_title: "Application", ad_back: "Applications", ad_approve: "Approve and create orphan", ad_reject: "Reject", ad_confirm: "Create an orphan record from this application? You can edit everything afterwards.",
    ad_done: "Orphan record created.", ad_rejected: "Application rejected.", ad_note: "Reason (optional, for your own record)", ad_open: "Open the orphan profile", ad_submitted: "Submitted", ad_photos: "Photos",
    ap_new: "New", ap_approved: "Approved", ap_rejected: "Rejected", ad_reject_q: "Reject this application?", ad_call: "Call guardian", cancel: "Cancel"
  }, {
    ad_title: "الطلب", ad_back: "الطلبات", ad_approve: "وافق وأنشئ سجل اليتيم", ad_reject: "ارفض", ad_confirm: "إنشاء سجل يتيم من هذا الطلب؟ يمكنك تعديل كل شيء بعد ذلك.",
    ad_done: "تم إنشاء سجل اليتيم.", ad_rejected: "تم رفض الطلب.", ad_note: "السبب (اختياري، لسجلك أنت)", ad_open: "افتح ملف اليتيم", ad_submitted: "وقت التقديم", ad_photos: "الصور",
    ap_new: "جديد", ap_approved: "مقبول", ap_rejected: "مرفوض", ad_reject_q: "رفض هذا الطلب؟", ad_call: "اتصل بالوصي", cancel: "إلغاء"
  });

  function render(a) {
    current = a; var d = a.details || {}, nm = A.lang() === "ar" && a.name_ar ? a.name_ar : (a.name_en || a.name_ar || "-");
    var back = '<a class="btn btn-line btn-sm" href="applications.html">' + ic("back", "flip") + t("ad_back") + "</a>";
    var photos = a.photos ? '<div class="card"><h3>' + t("ad_photos") + '</h3><div class="photos">' + Array.apply(null, Array(a.photos)).map(function (_, i) { return '<button class="ph" type="button" data-act="openphoto" data-n="' + i + '"></button>'; }).join("") + "</div></div>" : "";
    return back + '<div class="card profile" style="margin:1rem 0"><div class="phead"><span class="avatar">' + esc(String(nm).charAt(0)) + '</span><div style="flex:1;min-width:0"><h1 class="title" style="font-size:1.5rem;margin:0">' + esc(nm) + '</h1><span class="note" dir="ltr" style="display:block">' + esc(a.reference) + " · " + esc(a.submitted_at || "") +
      '</span></div><span class="badge ' + (a.status === "new" ? "warn" : a.status === "approved" ? "" : "grey") + '">' + t("ap_" + a.status) + "</span></div>" +
      '<div class="actions" style="margin-top:1rem">' +
      (a.status === "new" ? '<button class="btn btn-main" type="button" data-act="approve">' + ic("check") + t("ad_approve") + '</button><button class="btn btn-line" type="button" data-act="reject">' + t("ad_reject") + "</button>" : "") +
      (a.status === "approved" && a.child_id ? '<a class="btn btn-main" href="person.html?id=' + a.child_id + '">' + t("ad_open") + "</a>" : "") +
      (d.guardian_phone ? '<a class="btn btn-line" href="tel:' + esc(String(d.guardian_phone).replace(/[^0-9+]/g, "")) + '">' + ic("phone") + t("ad_call") + "</a>" : "") + "</div>" +
      (a.note ? '<p class="note" style="margin-bottom:0">' + esc(a.note) + "</p>" : "") + "</div>" +
      '<div class="grid g2" style="align-items:start">' + A.orphan.view(d, { bank: true, skipStaff: true }) + photos + "</div>";
  }
  A.act("openphoto", function (el) { var u = el.getAttribute("data-url"); if (u) window.open(u, "_blank", "noopener"); });
  A.act("approve", function (el) {
    if (!confirm(t("ad_confirm"))) return; el.disabled = true;
    A.api.write("POST", "applications/" + id + "/approve", {}, { online: true }).then(function (r) { A.toast(t("ad_done")); location.href = "person.html?id=" + r.data.child.id; }, function (e) { el.disabled = false; A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });
  A.act("reject", function () {
    A.sheet(t("ad_reject_q"), '<form id="rejForm" novalidate><div><label for="rj_note">' + t("ad_note") + '</label><input id="rj_note" type="text" maxlength="300"></div>' + A.formEnd("ad_reject") + "</form>");
  });
  A.form("rejForm", function () {
    A.api.write("POST", "applications/" + id + "/reject", { note: document.getElementById("rj_note").value.trim() || null }, { online: true }).then(function () { A.closeSheet(); A.toast(t("ad_rejected")); A.reload(true); }, function (e) { document.getElementById("formErr").textContent = e.offline ? t("need_conn") : A.msg(e); });
  });
  A.start({
    page: "application", nav: "applications", title: "ad_title", perm: "applications.review", needsConn: true, shell: true, load: function () { return A.api.get("applications/" + id, { cache: false }); }, render: render,
    after: function (a) { for (var i = 0; i < (a.photos || 0); i++) (function (n) { A.api.blobUrl("applications/" + id + "/photos/" + n).then(function (u) { var b = document.querySelector('.ph[data-n="' + n + '"]'); if (b) { b.style.backgroundImage = 'url("' + u + '")'; b.setAttribute("data-url", u); } }, function () {}); })(i); }
  });
})();
