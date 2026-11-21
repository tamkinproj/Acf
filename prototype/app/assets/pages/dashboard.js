(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    hello: "Welcome back", hello_sub: "Here is where things stand today.",
    s_children: "Orphans", s_verified: "Verified bank accounts", s_paid: "Paid this month", s_pending: "Pending this month",
    s_projects: "Active projects", s_users: "Active accounts", s_locked: "Failed sign-ins (24 h)", s_php: "PHP version", s_env: "Environment", s_family: "Children in my family",
    attn: "Needs attention", attn_none: "Nothing needs attention.", unverified: "Bank account not verified",
    setup_card: "Getting started", setup_card_sub: "{n} of {m} steps done", setup_open: "Continue setup", quick: "Quick actions", add_child: "Add orphan", export_csv: "Export CSV", month_rep: "Monthly report", open_users: "Manage accounts", open_site: "Edit the website", open_system: "System health",
    off_title: "Features are on the way", off_note: "The office features are switched off for now. They will be switched on one by one, and each one will appear here when it is ready. You can already change your password in Settings.", off_set: "Settings", off_more: "More office features will be switched on one by one. Each one appears here when it is ready.",
    w_orphans: "Orphans overview", w_orphans_sub: "By status", w_latest_orphans: "Latest orphans", w_all: "View all", w_name: "Name", w_status: "Status", w_added: "Added",
    st_active: "Active", st_pending: "Pending", st_ended: "Ended", w_total: "total", w_none: "Nothing here yet.",
    w_accounts: "Accounts overview", w_accounts_sub: "Active accounts by role", w_latest_accounts: "Latest accounts", w_role: "Role",
    r_system_admin: "System Admin", r_president_admin: "President Admin", r_security_admin: "Security Admin", r_admin_maintenance: "Admin Maintenance", r_staff: "Staff", r_user: "Family",
    w_system: "System status", w_database: "Database", w_storage: "Storage", w_ok: "OK", w_problem: "Problem", w_president: "President Admin", w_manage: "Manage", w_no_pres: "Not created yet",
    ap_stat: "New applications", w_active: "Active", w_off: "Switched off", w_of: "of {m}", w_quick_sub: "Go straight to what you do most"
  }, {
    hello: "أهلًا بعودتك", hello_sub: "هذا ما يجري اليوم.",
    s_children: "الأيتام", s_verified: "حسابات بنكية موثّقة", s_paid: "مدفوع هذا الشهر", s_pending: "معلّق هذا الشهر",
    s_projects: "مشاريع قيد التنفيذ", s_users: "حسابات نشطة", s_locked: "محاولات دخول فاشلة (24 ساعة)", s_php: "إصدار PHP", s_env: "البيئة", s_family: "الأطفال في عائلتي",
    attn: "يحتاج انتباهًا", attn_none: "لا شيء يحتاج انتباهًا.", unverified: "الحساب البنكي غير موثّق",
    setup_card: "البدء", setup_card_sub: "اكتملت {n} من {m} خطوات", setup_open: "تابع الإعداد", quick: "إجراءات سريعة", add_child: "إضافة يتيم", export_csv: "تصدير CSV", month_rep: "التقرير الشهري", open_users: "إدارة الحسابات", open_site: "تعديل الموقع", open_system: "صحة النظام",
    off_title: "الميزات في الطريق", off_note: "ميزات المكتب متوقفة حاليًا. ستُفعَّل واحدة تلو الأخرى، وستظهر كل ميزة هنا عندما تجهز. يمكنك الآن تغيير كلمة المرور من الإعدادات.", off_set: "الإعدادات", off_more: "ستُفعَّل ميزات أخرى للمكتب واحدة تلو الأخرى، وتظهر كل ميزة هنا عندما تجهز.",
    w_orphans: "نظرة على الأيتام", w_orphans_sub: "حسب الحالة", w_latest_orphans: "آخر الأيتام", w_all: "عرض الكل", w_name: "الاسم", w_status: "الحالة", w_added: "أُضيف",
    st_active: "نشط", st_pending: "معلّق", st_ended: "منتهٍ", w_total: "الإجمالي", w_none: "لا شيء هنا بعد.",
    w_accounts: "نظرة على الحسابات", w_accounts_sub: "الحسابات النشطة حسب الدور", w_latest_accounts: "آخر الحسابات", w_role: "الدور",
    r_system_admin: "مدير النظام", r_president_admin: "رئيس الإدارة", r_security_admin: "مدير الأمان", r_admin_maintenance: "مشرف الصيانة", r_staff: "موظف", r_user: "عائلة",
    w_system: "حالة النظام", w_database: "قاعدة البيانات", w_storage: "التخزين", w_ok: "سليم", w_problem: "مشكلة", w_president: "رئيس الإدارة", w_manage: "إدارة", w_no_pres: "لم يُنشأ بعد",
    ap_stat: "طلبات جديدة", w_active: "نشط", w_off: "متوقف", w_of: "من {m}", w_quick_sub: "اذهب مباشرة إلى ما تفعله كثيرًا"
  });

  var COLORS = { active: "#4f9d12", pending: "#e0a400", ended: "#9aa595" };
  var ROLE_COLORS = { president_admin: "#3f7010", security_admin: "#6aa31f", admin_maintenance: "#9acd32", staff: "#c4dd8a", user: "#9aa595", system_admin: "#17300d" };

  function fmtDate(d) {   // short ("21 Nov") so it fits a phone; the full date is the tooltip
    if (!d) return "-"; var l = A.lang() === "ar" ? "ar" : "en-GB";
    try { var x = new Date(d + "T00:00:00"); return '<span title="' + esc(x.toLocaleDateString(l, { day: "numeric", month: "long", year: "numeric" })) + '">' + esc(x.toLocaleDateString(l, { day: "numeric", month: "short" })) + "</span>"; } catch (e) { return esc(d); }
  }

  /* ---- Flowbite-style building blocks: white bordered cards, stat cards, a stacked bar, a table, link rows ---- */
  function stat(icon, num, label, o) {
    o = o || {};
    return '<div class="fb-card fb-stat' + (o.warn ? " warn" : "") + '"><div class="fb-stat-top"><div><div class="fb-num">' + num + (o.of !== undefined ? "<small>/ " + o.of + "</small>" : "") + '</div><div class="fb-lab">' + label + '</div></div><span class="fb-ico">' + ic(icon) + "</span></div>" +
      (o.pct !== undefined ? '<div class="fb-bar"><i style="width:' + Math.max(0, Math.min(100, o.pct)) + '%"></i></div>' : "") + "</div>";
  }
  function stacked(parts, total) {   // parts: [[label, count, color]]
    if (!total) return '<p class="note">' + t("w_none") + "</p>";
    return '<div class="fb-stack" role="img" aria-label="' + esc(parts.map(function (p) { return p[0] + " " + p[1]; }).join(", ")) + '">' + parts.filter(function (p) { return p[1] > 0; }).map(function (p) { return '<i style="width:' + (p[1] / total * 100) + "%;background:" + p[2] + '"></i>'; }).join("") + "</div>" +
      '<ul class="fb-legend">' + parts.map(function (p) { return '<li><span class="dot" style="background:' + p[2] + '"></span><span class="grow">' + p[0] + "</span><b>" + p[1] + "</b></li>"; }).join("") + "</ul>";
  }
  function badge(status) { return '<span class="badge ' + (status === "active" ? "" : status === "ended" ? "grey" : "warn") + '">' + t("st_" + status) + "</span>"; }
  function card(title, sub, body, link) {
    return '<div class="fb-card"><div class="fb-card-head"><div><h3>' + title + "</h3>" + (sub ? '<p class="fb-sub">' + sub + "</p>" : "") + "</div>" + (link ? '<a class="fb-link" href="' + link[0] + '">' + link[1] + ic("back", "rev") + "</a>" : "") + "</div>" + body + "</div>";
  }
  function table(head, rows) {
    return '<div class="fb-table-wrap"><table class="fb-table"><thead><tr>' + head.map(function (h) { return "<th>" + h + "</th>"; }).join("") + "</tr></thead><tbody>" + rows.join("") + "</tbody></table></div>";
  }
  function who(a, b, href) { return '<td><a class="fb-who" href="' + href + '"><span class="avatar">' + esc(String(a).charAt(0)) + '</span><span><b>' + esc(a) + "</b><small>" + b + "</small></span></a></td>"; }

  function render(d) {
    var u = A.user(), stats = [], left = [], right = [];
    var today = new Date().toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { weekday: "long", day: "numeric", month: "long" });

    /* ---- stat cards ---- */
    if (d.children) {
      stats.push(stat("users", d.children.total, t("s_children")));
      stats.push(stat("shield", d.children.bank_verified, t("s_verified"), { of: d.children.bank_accounts, pct: d.children.bank_accounts ? d.children.bank_verified / d.children.bank_accounts * 100 : 0 }));
    }
    if (d.applications) stats.push('<a class="fb-stat-link" href="applications.html">' + stat("file", d.applications.new, t("ap_stat"), { warn: d.applications.new > 0 }) + "</a>");
    if (d.payments) { stats.push(stat("check", d.payments.paid_count, t("s_paid"), { of: d.payments.children, pct: d.payments.children ? d.payments.paid_count / d.payments.children * 100 : 0 })); stats.push(stat("wallet", d.payments.pending_count, t("s_pending"))); }
    if (d.projects) stats.push(stat("folder", d.projects.active, t("s_projects"), { of: d.projects.total }));
    if (d.security) {
      stats.push(stat("users", d.security.active_users, t("s_users")));
      stats.push(stat("shield", d.security.failed_logins_24h, t("s_locked"), { warn: d.security.failed_logins_24h > 0 }));
    }
    if (d.family) stats.push(stat("users", d.family.children, t("s_family")));
    if (d.health) {
      var okAll = d.health.status === "ok";
      stats.push(stat("check", t(okAll ? "w_ok" : "w_problem"), t("w_system"), { warn: !okAll }));
      stats.push(stat("sliders", esc(d.health.versions.php), t("s_php")));
    } else if (d.system) { stats.push(stat("sliders", esc(d.system.php), t("s_php"))); stats.push(stat("file", esc(d.system.environment), t("s_env"))); }

    /* ---- main widgets (left, wide) ---- */
    if (d.children) {
      var bs = d.children.by_status || {};
      left.push(card(t("w_orphans"), t("w_orphans_sub"), '<div class="fb-big"><b>' + d.children.total + "</b><span>" + t("w_total") + "</span></div>" +
        stacked([[t("st_active"), bs.active || 0, COLORS.active], [t("st_pending"), bs.pending || 0, COLORS.pending], [t("st_ended"), bs.ended || 0, COLORS.ended]], d.children.total), A.can("children.view") ? ["people.html", t("w_all")] : null));
      var lat = d.children.latest || [];
      left.push(card(t("w_latest_orphans"), "", lat.length ? table([t("w_name"), t("w_status"), t("w_added")], lat.map(function (c) {
        return "<tr>" + who(A.name(c), esc(c.ref) + (c.city ? " · " + esc(c.city) : ""), "person.html?id=" + c.id) + "<td>" + badge(c.status || "active") + '</td><td class="muted">' + fmtDate(c.added) + "</td></tr>";
      })) : '<p class="note">' + t("w_none") + "</p>", ["people.html", t("w_all")]));
    }
    if (d.security) {
      var br = d.security.by_role || {}, order = ["president_admin", "security_admin", "admin_maintenance", "staff", "user", "system_admin"];
      left.push(card(t("w_accounts"), t("w_accounts_sub"), '<div class="fb-big"><b>' + d.security.active_users + "</b><span>" + t("w_total") + "</span></div>" +
        stacked(order.filter(function (r) { return br[r]; }).map(function (r) { return [t("r_" + r), br[r], ROLE_COLORS[r]]; }), d.security.active_users), A.can("users.manage") ? ["users.html", t("w_all")] : null));
      var lu = d.security.latest_users || [];
      left.push(card(t("w_latest_accounts"), "", lu.length ? table([t("w_name"), t("w_status"), t("w_added")], lu.map(function (x) {
        return "<tr>" + who(x.name, t("r_" + x.role), "users.html") + "<td>" + badge(x.active ? "active" : "ended").replace(t("st_ended"), t("w_off")) + '</td><td class="muted">' + fmtDate(x.added) + "</td></tr>";
      })) : '<p class="note">' + t("w_none") + "</p>", ["users.html", t("w_all")]));
    }
    if (d.health) {
      var c = d.health.checks || {}, row = function (label, ok, extra) { return '<li><span class="grow">' + label + "</span>" + (extra ? '<span class="muted">' + extra + "</span>" : "") + '<span class="badge ' + (ok ? "" : "warn") + '">' + t(ok ? "w_ok" : "w_problem") + "</span></li>"; };
      left.push(card(t("w_system"), "", '<ul class="fb-rows">' + row(t("w_database"), c.database) + row(t("w_storage"), c.storage_writable) + row("PHP", true, esc(d.health.versions.php)) + row("Laravel", true, esc(d.health.versions.laravel)) + row(t("s_env"), true, esc(d.health.environment)) + "</ul>", ["system.html", t("w_manage")]));
    }

    /* ---- side column: needs attention, president (System Admin), quick actions ---- */
    if (d.children) {
      var attn = d.children.needs_attention || [];
      right.push(card(t("attn"), "", attn.length ? '<ul class="fb-rows">' + attn.map(function (a) {
        return '<li><a class="fb-who" href="person.html?id=' + a.child_id + '"><span class="avatar">' + esc((A.lang() === "ar" && a.name_ar ? a.name_ar : a.name_en).charAt(0)) + "</span><span><b>" + esc(a.name_en) + "</b><small>" + esc(a.ref) + " · " + t("unverified") + '</small></span></a><span class="badge warn">!</span></li>';
      }).join("") + "</ul>" : '<p class="note">' + t("attn_none") + "</p>"));
    }
    if (d.president !== undefined) {
      right.push(card(t("w_president"), "", d.president ? '<div class="fb-who static"><span class="avatar">' + esc(d.president.name.charAt(0)) + "</span><span><b>" + esc(d.president.name) + '</b><small dir="ltr">' + esc(d.president.email) + '</small></span></div><p style="margin:.8rem 0 0">' + badge(d.president.active ? "active" : "ended").replace(t("st_ended"), t("w_off")) + "</p>" : '<p class="note">' + t("w_no_pres") + "</p>", ["system.html", t("w_manage")]));
    }
    var q = [];
    function link(href, icon, label) { q.push('<li><a class="fb-qa" href="' + href + '"><span class="fb-ico sm">' + ic(icon) + '</span><span class="grow">' + label + "</span>" + ic("back", "rev") + "</a></li>"); }
    if (A.can("children.create")) link("people.html?add=1", "plus", t("add_child"));
    if (A.can("reports.export")) link("reports.html", "download", t("export_csv"));
    if (A.can("reports.view")) link("reports.html", "file", t("month_rep"));
    if (A.can("site.edit")) link("website.html", "globe", t("open_site"));
    if (A.can("users.manage")) link("users.html", "shield", t("open_users"));
    if (A.can("system.health")) link("system.html", "sliders", t("open_system"));
    if (q.length) right.push(card(t("quick"), t("w_quick_sub"), '<ul class="fb-rows qa">' + q.join("") + "</ul>"));

    /* ---- banners ---- */
    var banners = "";
    if (d.setup && d.setup.steps.length && d.setup.done < d.setup.total) {
      var pct = Math.round(d.setup.done / d.setup.total * 100);
      banners += '<div class="fb-card fb-banner"><div class="grow"><h3>' + t("setup_card") + '</h3><p class="fb-sub">' + t("setup_card_sub", { n: d.setup.done, m: d.setup.total }) + '</p><div class="fb-bar"><i style="width:' + pct + '%"></i></div></div><a class="btn btn-main" href="setup.html">' + t("setup_open") + "</a></div>";
    }
    if (d.features_off) banners += '<div class="fb-card fb-note"><span class="fb-ico sm">' + ic("sliders") + '</span><div><h3>' + t("off_title") + '</h3><p class="fb-sub">' + t(stats.length || q.length ? "off_more" : "off_note") + "</p>" + (stats.length || q.length ? "" : '<a class="btn btn-line btn-sm" href="settings.html">' + t("off_set") + "</a>") + "</div></div>";

    return '<div class="fb">' +
      '<div class="fb-head"><div><h1 class="title">' + t("hello") + (u ? ", " + esc(u.name.split(" ")[0]) : "") + '</h1><p class="lead">' + t("hello_sub") + '</p></div><span class="fb-date">' + ic("file") + esc(today) + "</span></div>" +
      banners + (stats.length ? '<div class="fb-stats">' + stats.join("") + "</div>" : "") +
      (left.length || right.length ? '<div class="fb-main"><div class="fb-col">' + left.join("") + '</div><div class="fb-col">' + right.join("") + "</div></div>" : "") + "</div>";
  }
  function load() {
    var jobs = [A.api.get("dashboard"), A.api.get("setup").catch(function () { return null; })];
    jobs.push(A.can("system.health") ? A.api.get("system/health", { cache: false }).catch(function () { return null; }) : Promise.resolve(null));
    jobs.push(A.can("system.first_admin") ? A.api.get("system/president-admin", { cache: false }).catch(function () { return undefined; }) : Promise.resolve(undefined));
    return Promise.all(jobs).then(function (r) {
      var d = r[0].data; d.setup = r[1] ? r[1].data : null; if (r[2]) d.health = r[2].data; if (r[3] !== undefined && r[3] !== null) d.president = r[3].data; else if (r[3] === null) d.president = null;
      return { data: d, stale: r[0].stale, at: r[0].at };
    });
  }
  function after(d, st) {
    // the very first sign-in of an account with required steps goes straight to the checklist (once)
    var u = A.user();
    if (d.setup && d.setup.required_left > 0 && !st.stale && u && !(u.preferences && u.preferences.setup_seen)) location.replace("setup.html");
  }
  A.start({ page: "dashboard", nav: "dashboard", title: "n_home", shell: true, load: load, render: render, after: after });
})();
