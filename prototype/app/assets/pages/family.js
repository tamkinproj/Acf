(function () {
  var A = ACF, t = A.t, esc = A.esc;

  A.strings({
    fam_title: "My family", fam_me: "My information", fam_num: "Number", fam_age: "{n} years old", fam_tip: "You can change your photo, username and password in Settings.", fam_none: "No children are linked to your account yet.", fam_docs: "Documents", doc_on: "On file", doc_missing: "Missing",
    d_birth_certificate: "Orphan's birth certificate", d_father_death_certificate: "Father's death certificate", d_school_record: "School certificate", d_guardian_id: "Direct responsible person's ID", d_case_study: "Case study", f_grade: "Grade"
  }, {
    fam_title: "عائلتي", fam_me: "معلوماتي", fam_num: "الرقم", fam_age: "العمر {n} سنة", fam_tip: "يمكنك تغيير صورتك واسم المستخدم وكلمة المرور من الإعدادات.", fam_none: "لا يوجد أطفال مرتبطون بحسابك بعد.", fam_docs: "المستندات", doc_on: "موجود", doc_missing: "ناقص",
    d_birth_certificate: "صورة شهادة ميلاد اليتيم", d_father_death_certificate: "صورة شهادة وفاة الاب", d_school_record: "صورة الشهادة الدراسية", d_guardian_id: "صورة هوية المسؤول المباشر", d_case_study: "صورة بحث الحالة", f_grade: "الصف"
  });

  function render(list) {
    var me = list.some(function (c) { return c.is_self; });
    return '<div class="head"><h1 class="title">' + t(me ? "fam_me" : "fam_title") + "</h1>" + (me ? '<p class="lead">' + t("fam_tip") + "</p>" : "") + "</div>" + (list.length ? '<div class="grid g2">' + list.map(function (c) {
      return '<div class="card"><div class="phead"><span class="avatar">' + esc(A.name(c).charAt(0)) + '</span><div style="flex:1"><h3 style="margin:0">' + esc(A.name(c)) + '</h3><span class="note">' + esc(c.ref) + (c.external_no ? " · #" + esc(c.external_no) : "") + (c.age !== null && c.age !== undefined ? " · " + t("fam_age", { n: c.age }) : "") + (c.grade ? " · " + esc(c.grade) : "") + "</span></div></div>" +
        '<h3 style="margin-top:1rem;font-size:1rem">' + t("fam_docs") + '</h3><div class="list">' + c.documents.map(function (d) {
          return '<div class="item"><span class="grow"><b>' + t("d_" + d.type) + '</b></span><span class="badge ' + (d.on_file ? "" : "warn") + '">' + t(d.on_file ? "doc_on" : "doc_missing") + "</span></div>";
        }).join("") + "</div></div>";
    }).join("") + "</div>" : '<p class="note">' + t("fam_none") + "</p>");
  }
  A.start({ page: "family", nav: "family", title: "n_family", perm: "family.view_own", shell: true, load: function () { return A.api.get("my/family"); }, render: render });
})();
