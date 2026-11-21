(function () {
  var A = ACF, t = A.t, ic = A.ic;
  A.strings({
    dp_title: "Departments", dp_hello: "Choose a department.", dp_aytam: "Orphans", dp_aytam_sub: "Orphan records, add an orphan, sign-in accounts", dp_edu: "Education", dp_edu_sub: "Alimni teachers, contracts, madrasa and markaz, curriculum", dp_att: "HR and attendance", dp_att_sub: "Clock in and out with a face photo, late and absent pay, payroll", dp_work: "Work tracker", dp_work_sub: "How staff use the app: active time, last seen, actions"
  }, {
    dp_title: "الأقسام", dp_hello: "اختر قسمًا.", dp_aytam: "الأيتام", dp_aytam_sub: "سجلات الأيتام وإضافة يتيم وحسابات الدخول", dp_edu: "التعليم", dp_edu_sub: "معلمو علّمني والعقود والمدرسة والمراكز والمنهج", dp_att: "الموارد البشرية والحضور", dp_att_sub: "تسجيل الحضور والانصراف بصورة الوجه، خصم التأخر والغياب، كشف الرواتب", dp_work: "متابعة العمل", dp_work_sub: "كيف يستخدم الموظفون التطبيق: وقت النشاط وآخر ظهور والإجراءات"
  });
  function tile(icon, href, title, sub) {
    return '<a class="fo" href="' + href + '"><span class="fo-top">' + ic(icon) + ic("chev", "fo-go") + "</span><b>" + t(title) + '</b><span class="fo-sub">' + t(sub) + "</span></a>";
  }
  function render() {
    var tiles = [];
    if (A.can("children.view")) tiles.push(tile("users", "people.html", "dp_aytam", "dp_aytam_sub"));
    if (A.can("education.view")) tiles.push(tile("book", "education.html", "dp_edu", "dp_edu_sub"));
    if (A.can("attendance.clock") || A.can("attendance.manage")) tiles.push(tile("clock", "attendance.html", "dp_att", "dp_att_sub"));
    if (A.can("work.view")) tiles.push(tile("pulse", "work.html", "dp_work", "dp_work_sub"));
    return '<div class="head"><h1 class="title">' + t("dp_title") + '</h1></div><p class="note">' + t("dp_hello") + '</p><div class="fo-grid">' + tiles.join("") + "</div>";
  }
  A.start({ page: "departments", nav: "departments", title: "dp_title", shell: true, load: function () { return Promise.resolve({ data: {} }); }, render: render });
})();
