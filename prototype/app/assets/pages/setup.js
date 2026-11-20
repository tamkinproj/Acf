(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    setup_title: "Getting started", setup_sub: "Do these in order. Each one turns green when it is done.", setup_prog: "{n} of {m} done", required: "Required", optional: "Optional",
    setup_go: "Open", setup_done_all: "Everything required is done.", setup_home: "Go to Home", setup_empty: "Nothing to set up for your role. Go to Home.",
    st_security_admin: "Create the Security Admin", sd_security_admin: "This person creates and manages every other account. Choose someone you trust.",
    st_org_settings: "Set the organisation name and currency", sd_org_settings: "Shown on reports and screens.",
    st_partner_key: "Create a key for a partner", sd_partner_key: "Lets a partner such as Dar Al Ber read totals only, never names.",
    st_president_admin: "Create the President Admin", sd_president_admin: "Runs every office feature.",
    st_staff_aytam: "Create Aytam staff", sd_staff_aytam: "Register children and keep their records.",
    st_staff_finance: "Create Finance staff", sd_staff_finance: "Payments and reports.",
    st_staff_projects: "Create Projects staff", sd_staff_projects: "Project records and progress.",
    st_maintenance: "Create an Admin Maintenance account", sd_maintenance: "Can watch system health and nothing else.",
    st_first_child: "Add the first child", sd_first_child: "Start the records. The full list can be imported later.",
    st_first_project: "Add a project", sd_first_project: "For example a well, a classroom or a drive.",
    sa_title: "Create the Security Admin", sa_name: "Full name", sa_email: "Email", sa_pass: "Password", sa_gen: "Generate", sa_hint: "At least 12 characters with upper and lower case and a number. Give it to the person privately.",
    sa_made: "Security Admin created. They can sign in now.", sa_note: "This needs a connection."
  }, {
    setup_title: "البدء", setup_sub: "نفّذ هذه الخطوات بالترتيب. تتحول كل خطوة إلى الأخضر عند اكتمالها.", setup_prog: "اكتمل {n} من {m}", required: "مطلوب", optional: "اختياري",
    setup_go: "افتح", setup_done_all: "اكتمل كل المطلوب.", setup_home: "اذهب إلى الرئيسية", setup_empty: "لا شيء لإعداده لدورك. اذهب إلى الرئيسية.",
    st_security_admin: "أنشئ مدير الأمان", sd_security_admin: "هذا الشخص ينشئ ويدير كل الحسابات الأخرى. اختر شخصًا تثق به.",
    st_org_settings: "حدّد اسم المؤسسة والعملة", sd_org_settings: "يظهران في التقارير والشاشات.",
    st_partner_key: "أنشئ مفتاحًا لشريك", sd_partner_key: "يتيح لشريك مثل دار البر قراءة المجاميع فقط دون أسماء.",
    st_president_admin: "أنشئ رئيس الإدارة", sd_president_admin: "يدير كل ميزات المكتب.",
    st_staff_aytam: "أنشئ موظفي الأيتام", sd_staff_aytam: "تسجيل الأطفال وحفظ سجلاتهم.",
    st_staff_finance: "أنشئ موظفي المالية", sd_staff_finance: "المدفوعات والتقارير.",
    st_staff_projects: "أنشئ موظفي المشاريع", sd_staff_projects: "سجلات المشاريع وتقدمها.",
    st_maintenance: "أنشئ حساب مشرف الصيانة", sd_maintenance: "يراقب صحة النظام فقط.",
    st_first_child: "أضف أول طفل", sd_first_child: "ابدأ السجلات. يمكن استيراد القائمة الكاملة لاحقًا.",
    st_first_project: "أضف مشروعًا", sd_first_project: "مثل بئر أو فصل دراسي أو حملة.",
    sa_title: "أنشئ مدير الأمان", sa_name: "الاسم الكامل", sa_email: "البريد الإلكتروني", sa_pass: "كلمة المرور", sa_gen: "توليد", sa_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. أعطها للشخص بسرية.",
    sa_made: "تم إنشاء مدير الأمان. يستطيع تسجيل الدخول الآن.", sa_note: "هذا يحتاج اتصالًا."
  });

  function render(d) {
    var pct = d.total ? Math.round(d.done / d.total * 100) : 100;
    if (!d.steps.length) return '<div class="head"><h1 class="title">' + t("setup_title") + '</h1></div><p class="note">' + t("setup_empty") + '</p><div class="actions"><a class="btn btn-main" href="dashboard.html">' + t("setup_home") + "</a></div>";
    return '<div class="head"><h1 class="title">' + t("setup_title") + '</h1><p class="lead">' + t("setup_sub") + "</p></div>" +
      '<div class="card" style="margin-bottom:1rem"><b>' + t("setup_prog", { n: d.done, m: d.total }) + '</b><div class="bar"><i style="width:' + pct + '%"></i></div>' + (d.required_left === 0 ? '<p class="note" style="margin:0">' + t("setup_done_all") + "</p>" : "") + "</div>" +
      '<div class="list">' + d.steps.map(function (s) {
        var go = s.to.indexOf("sheet:") === 0 ? '<button class="btn btn-main btn-sm" data-act="stepsheet">' + t("setup_go") + "</button>" : '<a class="btn btn-main btn-sm" href="' + s.to + '">' + t("setup_go") + "</a>";
        return '<div class="item"><span class="avatar">' + (s.done ? ic("check") : "·") + '</span><span class="grow"><b>' + t("st_" + s.key) + '</b><span class="sub">' + t("sd_" + s.key) + '</span></span><span class="right"><span class="badge ' + (s.done ? "" : s.required ? "warn" : "grey") + '">' + (s.done ? "✓" : t(s.required ? "required" : "optional")) + "</span>" + (s.done ? "" : go) + "</span></div>";
      }).join("") + '</div><div class="actions" style="margin-top:1rem"><a class="btn btn-line" href="dashboard.html">' + t("setup_home") + "</a></div>";
  }

  function genPass() {
    var U = "ABCDEFGHJKLMNPQRSTUVWXYZ", L = "abcdefghijkmnopqrstuvwxyz", D = "23456789", all = U + L + D, a = new Uint8Array(16); crypto.getRandomValues(a);
    var out = [U[a[0] % U.length], L[a[1] % L.length], D[a[2] % D.length]]; for (var i = 3; i < 16; i++) out.push(all[a[i] % all.length]); return out.join("");
  }
  A.act("stepsheet", function () {
    A.sheet(t("sa_title"), '<form id="saForm" novalidate>' + A.field("sa_name", t("sa_name")) + A.field("sa_email", t("sa_email"), "", "email", 'dir="ltr" autocomplete="off"') +
      A.field("sa_password", t("sa_pass"), '<div class="actions" style="margin-top:.4rem"><button class="btn btn-line btn-sm" type="button" data-act="sagen">' + t("sa_gen") + '</button></div><p class="note" style="margin:.4rem 0 0">' + t("sa_hint") + "</p>", "text", 'dir="ltr" autocomplete="off"') + A.formEnd() + "</form>");
  });
  A.act("sagen", function () { document.getElementById("sa_password").value = genPass(); });
  function v(id) { return document.getElementById(id).value.trim(); }
  A.form("saForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; });
    var body = { name: v("sa_name"), email: v("sa_email"), password: v("sa_password") };
    if (!body.name) { document.getElementById("sa_nameErr").textContent = t("required"); return; }
    A.api.write("POST", "system/security-admin", body, { online: true }).then(function () { A.closeSheet(); A.toast(t("sa_made")); A.reload(true); }, function (e) {
      if (!(e.errors && A.showErrors(e.errors, "sa_"))) document.getElementById("formErr").textContent = e.offline ? t("sa_note") : A.msg(e);
    });
  });

  A.start({
    page: "setup", nav: "dashboard", title: "setup_title", shell: true,
    load: function () { return A.api.get("setup"); }, render: render,
    after: function (d, st) {
      var u = A.user(); if (u && !(u.preferences && u.preferences.setup_seen) && !st.stale) {
        A.api.setPrefs({ setup_seen: true }); A.api.write("PATCH", "me/preferences", { setup_seen: true }, { label: "setup" }).then(function () {}, function () {});
      }
    }
  });
})();
