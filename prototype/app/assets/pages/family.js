(function () {
  var A = ACF, t = A.t, esc = A.esc;

  A.strings({
    fam_title: "My family", fam_none: "No children are linked to your account yet.", fam_docs: "Documents", doc_on: "On file", doc_missing: "Missing",
    d_birth_certificate: "Birth certificate", d_father_death_certificate: "Father's death certificate", d_school_record: "School record", d_guardian_id: "Guardian ID", f_grade: "Grade"
  }, {
    fam_title: "عائلتي", fam_none: "لا يوجد أطفال مرتبطون بحسابك بعد.", fam_docs: "المستندات", doc_on: "موجود", doc_missing: "ناقص",
    d_birth_certificate: "شهادة الميلاد", d_father_death_certificate: "شهادة وفاة الأب", d_school_record: "السجل المدرسي", d_guardian_id: "هوية الوصي", f_grade: "الصف"
  });

  function render(list) {
    return '<div class="head"><h1 class="title">' + t("fam_title") + "</h1></div>" + (list.length ? '<div class="grid g2">' + list.map(function (c) {
      return '<div class="card"><div class="phead"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><div style="flex:1"><h3 style="margin:0">' + esc(A.name(c)) + '</h3><span class="note">' + esc(c.ref) + (c.grade ? " · " + esc(c.grade) : "") + "</span></div></div>" +
        '<h3 style="margin-top:1rem;font-size:1rem">' + t("fam_docs") + '</h3><div class="list">' + c.documents.map(function (d) {
          return '<div class="item"><span class="grow"><b>' + t("d_" + d.type) + '</b></span><span class="badge ' + (d.on_file ? "" : "warn") + '">' + t(d.on_file ? "doc_on" : "doc_missing") + "</span></div>";
        }).join("") + "</div></div>";
    }).join("") + "</div>" : '<p class="note">' + t("fam_none") + "</p>");
  }
  A.start({ page: "family", nav: "family", title: "n_family", perm: "family.view_own", shell: true, load: function () { return A.api.get("my/family"); }, render: render });
})();
