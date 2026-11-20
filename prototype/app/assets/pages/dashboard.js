(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    hello: "Welcome back", hello_sub: "Here is where things stand today.",
    s_children: "Children", s_verified: "Verified bank accounts", s_paid: "Paid this month", s_pending: "Pending this month",
    s_projects: "Active projects", s_users: "Active accounts", s_locked: "Failed sign-ins (24 h)", s_php: "PHP version", s_env: "Environment", s_family: "Children in my family",
    attn: "Needs attention", attn_none: "Nothing needs attention.", unverified: "Bank account not verified",
    setup_card: "Getting started", setup_card_sub: "{n} of {m} steps done", setup_open: "Continue setup", quick: "Quick actions", add_child: "Add child", export_csv: "Export CSV", month_rep: "Monthly report", open_users: "Manage accounts", open_site: "Edit the website", open_system: "System health"
  }, {
    hello: "أهلًا بعودتك", hello_sub: "هذا ما يجري اليوم.",
    s_children: "الأطفال", s_verified: "حسابات بنكية موثّقة", s_paid: "مدفوع هذا الشهر", s_pending: "معلّق هذا الشهر",
    s_projects: "مشاريع قيد التنفيذ", s_users: "حسابات نشطة", s_locked: "محاولات دخول فاشلة (24 ساعة)", s_php: "إصدار PHP", s_env: "البيئة", s_family: "الأطفال في عائلتي",
    attn: "يحتاج انتباهًا", attn_none: "لا شيء يحتاج انتباهًا.", unverified: "الحساب البنكي غير موثّق",
    setup_card: "البدء", setup_card_sub: "اكتملت {n} من {m} خطوات", setup_open: "تابع الإعداد", quick: "إجراءات سريعة", add_child: "إضافة طفل", export_csv: "تصدير CSV", month_rep: "التقرير الشهري", open_users: "إدارة الحسابات", open_site: "تعديل الموقع", open_system: "صحة النظام"
  });

  function stat(icon, num, sub, label) {
    return '<div class="card stat">' + ic(icon) + '<div class="num">' + num + (sub ? "<span>/ " + sub + "</span>" : "") + '</div><div class="lab">' + label + "</div></div>";
  }
  function render(d) {
    var u = A.user(), cards = [], attn = d.children ? d.children.needs_attention || [] : [];
    if (d.children) { cards.push(stat("users", d.children.total, "", t("s_children"))); cards.push(stat("shield", d.children.bank_verified, d.children.bank_accounts, t("s_verified"))); }
    if (d.payments) { cards.push(stat("check", d.payments.paid_count, d.payments.children, t("s_paid"))); cards.push(stat("wallet", d.payments.pending_count, "", t("s_pending"))); }
    if (d.projects) cards.push(stat("folder", d.projects.active, d.projects.total, t("s_projects")));
    if (d.security) { cards.push(stat("users", d.security.active_users, "", t("s_users"))); cards.push(stat("shield", d.security.failed_logins_24h, "", t("s_locked"))); }
    if (d.system) { cards.push(stat("sliders", esc(d.system.php), "", t("s_php"))); cards.push(stat("file", esc(d.system.environment), "", t("s_env"))); }
    if (d.family) cards.push(stat("users", d.family.children, "", t("s_family")));

    var actions = "";
    if (A.can("children.create")) actions += '<a class="btn btn-main" href="people.html?add=1">' + ic("plus") + t("add_child") + "</a>";
    if (A.can("reports.export")) actions += '<a class="btn btn-line" href="reports.html">' + ic("download") + t("export_csv") + "</a>";
    if (A.can("reports.view")) actions += '<a class="btn btn-line" href="reports.html">' + ic("file") + t("month_rep") + "</a>";
    if (A.can("site.edit")) actions += '<a class="btn btn-line" href="website.html">' + ic("globe") + t("open_site") + "</a>";
    if (A.can("users.manage")) actions += '<a class="btn btn-main" href="users.html">' + ic("shield") + t("open_users") + "</a>";
    if (A.can("system.health")) actions += '<a class="btn btn-line" href="system.html">' + ic("sliders") + t("open_system") + "</a>";

    var attnCard = d.children ? '<div class="card"><h3>' + t("attn") + "</h3>" + (attn.length ? '<div class="list">' + attn.map(function (a) {
      return '<a class="item" href="person.html?id=' + a.child_id + '"><span class="avatar">' + esc((A.lang() === "ar" && a.name_ar ? a.name_ar : a.name_en).charAt(0)) + '</span><span class="grow"><b>' + esc(a.name_en) + '</b><span class="sub">' + esc(a.ref) + " · " + t("unverified") + '</span></span><span class="badge warn">!</span></a>';
    }).join("") + "</div>" : '<p class="note">' + t("attn_none") + "</p>") + "</div>" : "";

    var setupCard = d.setup && d.setup.steps.length && d.setup.done < d.setup.total ? '<div class="card" style="margin-bottom:1rem"><h3>' + t("setup_card") + '</h3><p class="note" style="margin-top:0">' + t("setup_card_sub", { n: d.setup.done, m: d.setup.total }) + '</p><div class="bar"><i style="width:' + Math.round(d.setup.done / d.setup.total * 100) + '%"></i></div><div class="actions"><a class="btn btn-main" href="setup.html">' + t("setup_open") + "</a></div></div>" : "";
    return '<div class="head"><h1 class="title">' + t("hello") + (u ? ", " + esc(u.name.split(" ")[0]) : "") + '</h1><p class="lead">' + t("hello_sub") + "</p></div>" +
      setupCard + '<div class="grid g4">' + cards.join("") + "</div>" +
      '<div class="grid g2" style="margin-top:1rem;align-items:start">' + attnCard + (actions ? '<div class="card"><h3>' + t("quick") + '</h3><div class="actions">' + actions + "</div></div>" : "") + "</div>";
  }
  function load() {
    return Promise.all([A.api.get("dashboard"), A.api.get("setup").catch(function () { return null; })]).then(function (r) {
      var d = r[0].data; d.setup = r[1] ? r[1].data : null; return { data: d, stale: r[0].stale, at: r[0].at };
    });
  }
  function after(d, st) {
    // the very first sign-in of an account with required steps goes straight to the checklist (once)
    var u = A.user();
    if (d.setup && d.setup.required_left > 0 && !st.stale && u && !(u.preferences && u.preferences.setup_seen)) location.replace("setup.html");
  }
  A.start({ page: "dashboard", nav: "dashboard", title: "n_home", shell: true, load: load, render: render, after: after });
})();
