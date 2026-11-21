(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  A.strings({
    gm: "Good morning", ga: "Good afternoon", ge: "Good evening", hello_sub: "Stay on top of your work, follow progress and check what needs you.",
    search_ph: "Search orphans", s_more: "More",
    // Aytam
    h_orphans: "Orphans", h_orphans_sub: "In the foundation's care", h_add_orphan: "Add New", t_orphans: "Orphans", t_apps: "Applications", t_search: "Search",
    s_active: "Active", s_pending: "Pending", s_verified: "Bank verified", s_newapps: "New applications", of_n: "of {n}", need_review: "to review", all_clear: "all reviewed",
    c_orphans: "Orphans overview", c_age: "Age", c_city: "City", c_status: "Status", c_all: "All records", ages: "years",
    q_latest_orphans: "Latest orphans", q_latest_sub: "The most recently added records",
    dk_form: "Application form", dk_new: "New", dk_total: "Orphans", b_copy: "Copy link", b_open: "Open form", copied: "Link copied",
    qa: "Quick Action", qa_add: "Add", qa_apps: "Review", qa_site: "Edit website", qa_more: "More",
    pl_samples: "Sample data", pl_samples_txt: "{n} sample orphans are in the system so the screens are not empty. Remove them before real use.", pl_samples_btn: "Go to Orphans",
    pl_soon: "More features soon", pl_soon_txt: "Office features are switched on one by one. Each one appears here when it is ready.", pl_soon_btn: "Open Settings",
    w_latest: "Latest orphans", w_all: "View all", w_name: "Name", w_status: "Status", w_added: "Added", st_active: "Active", st_pending: "Pending", st_ended: "Ended", w_none: "Nothing here yet.",
    attn: "Needs attention", attn_none: "Nothing needs attention.", unverified: "Bank account not verified",
    // President
    h_accounts: "Accounts", h_accounts_sub: "People who can sign in", h_add_account: "Add New", t_accounts: "Accounts", t_setup: "Setup", t_settings: "Settings",
    s_staff: "Staff", s_security: "Security Admin", s_maint: "Maintenance", s_failed: "Failed sign-ins", last24: "last 24 h",
    c_accounts: "Accounts overview", c_role: "Role", r_system_admin: "System", r_president_admin: "President", r_security_admin: "Security", r_admin_maintenance: "Maint.", r_staff: "Staff", r_user: "Family",
    q_latest_accounts: "Latest accounts", q_latest_acc_sub: "The most recently created accounts", dk_me: "Your account", b_password: "Password", b_accounts: "Accounts",
    pl_setup: "Getting started", pl_setup_txt: "{n} of {m} steps done. Create the staff accounts so each department can start.", pl_setup_btn: "Continue setup", w_latest_acc: "Latest accounts", w_role: "Role", w_off: "Off",
    // System
    h_system: "System", h_system_sub: "Everything the server needs", ok: "OK", problem: "Problem", h_update: "Update", t_system: "System", t_updates: "Updates",
    s_db: "Database", s_storage: "Storage", s_php: "PHP", s_laravel: "Laravel", c_sys: "System status", s_env: "Environment",
    dk_build: "Version on this server", b_upload: "Upload update", b_system: "System", q_pres: "President Admin", q_pres_sub: "Created and changed by you only", no_pres: "Not created yet",
    pl_key: "Keep the key safe", pl_key_txt: "Updates are accepted only when signed with your key file. Keep it off the server, with one private backup.", pl_key_btn: "Open Updates",
    // everyone else
    h_welcome: "Welcome", h_welcome_sub: "Your features are being prepared", off_note: "The office features are switched off for now. They will be switched on one by one. You can already change your password in Settings."
  }, {
    gm: "صباح الخير", ga: "مساء الخير", ge: "مساء الخير", hello_sub: "تابع عملك وتقدمك وما يحتاج إليك.",
    search_ph: "ابحث عن يتيم", s_more: "المزيد",
    h_orphans: "الأيتام", h_orphans_sub: "في رعاية المؤسسة", h_add_orphan: "إضافة", t_orphans: "الأيتام", t_apps: "الطلبات", t_search: "بحث",
    s_active: "نشط", s_pending: "معلّق", s_verified: "حسابات موثّقة", s_newapps: "طلبات جديدة", of_n: "من {n}", need_review: "للمراجعة", all_clear: "تمت مراجعتها",
    c_orphans: "نظرة على الأيتام", c_age: "العمر", c_city: "المدينة", c_status: "الحالة", c_all: "كل السجلات", ages: "سنة",
    q_latest_orphans: "آخر الأيتام", q_latest_sub: "آخر السجلات المضافة",
    dk_form: "نموذج التقديم", dk_new: "جديد", dk_total: "الأيتام", b_copy: "انسخ الرابط", b_open: "افتح النموذج", copied: "تم نسخ الرابط",
    qa: "إجراء سريع", qa_site: "تعديل الموقع", qa_add: "إضافة", qa_apps: "مراجعة", qa_more: "المزيد",
    pl_samples: "بيانات تجريبية", pl_samples_txt: "يوجد {n} يتيمًا تجريبيًا حتى لا تكون الشاشات فارغة. احذفهم قبل الاستخدام الحقيقي.", pl_samples_btn: "اذهب إلى الأيتام",
    pl_soon: "ميزات قادمة", pl_soon_txt: "تُفعَّل ميزات المكتب واحدة تلو الأخرى، وتظهر كل ميزة هنا عندما تجهز.", pl_soon_btn: "افتح الإعدادات",
    w_latest: "آخر الأيتام", w_all: "عرض الكل", w_name: "الاسم", w_status: "الحالة", w_added: "أُضيف", st_active: "نشط", st_pending: "معلّق", st_ended: "منتهٍ", w_none: "لا شيء هنا بعد.",
    attn: "يحتاج انتباهًا", attn_none: "لا شيء يحتاج انتباهًا.", unverified: "الحساب البنكي غير موثّق",
    h_accounts: "الحسابات", h_accounts_sub: "من يستطيع تسجيل الدخول", h_add_account: "إضافة", t_accounts: "الحسابات", t_setup: "الإعداد", t_settings: "الإعدادات",
    s_staff: "الموظفون", s_security: "مدير الأمان", s_maint: "الصيانة", s_failed: "دخول فاشل", last24: "آخر 24 ساعة",
    c_accounts: "نظرة على الحسابات", c_role: "الدور", r_system_admin: "النظام", r_president_admin: "الرئيس", r_security_admin: "الأمان", r_admin_maintenance: "الصيانة", r_staff: "موظف", r_user: "عائلة",
    q_latest_accounts: "آخر الحسابات", q_latest_acc_sub: "آخر الحسابات المنشأة", dk_me: "حسابك", b_password: "كلمة المرور", b_accounts: "الحسابات",
    pl_setup: "البدء", pl_setup_txt: "اكتمل {n} من {m}. أنشئ حسابات الموظفين ليبدأ كل قسم.", pl_setup_btn: "تابع الإعداد", w_latest_acc: "آخر الحسابات", w_role: "الدور", w_off: "متوقف",
    h_system: "النظام", h_system_sub: "كل ما يحتاجه الخادم", ok: "سليم", problem: "مشكلة", h_update: "تحديث", t_system: "النظام", t_updates: "التحديثات",
    s_db: "قاعدة البيانات", s_storage: "التخزين", s_php: "PHP", s_laravel: "Laravel", c_sys: "حالة النظام", s_env: "البيئة",
    dk_build: "الإصدار على هذا الخادم", b_upload: "ارفع تحديثًا", b_system: "النظام", q_pres: "رئيس الإدارة", q_pres_sub: "تنشئه وتغيّره أنت فقط", no_pres: "لم يُنشأ بعد",
    pl_key: "احفظ المفتاح بأمان", pl_key_txt: "لا تُقبل التحديثات إلا موقّعة بملف مفتاحك. أبقِه خارج الخادم مع نسخة احتياطية خاصة واحدة.", pl_key_btn: "افتح التحديثات",
    h_welcome: "أهلًا", h_welcome_sub: "ميزاتك قيد التجهيز", off_note: "ميزات المكتب متوقفة حاليًا وستُفعَّل واحدة تلو الأخرى. يمكنك الآن تغيير كلمة المرور من الإعدادات."
  });

  var chart = null;   // { sets: [{ key, label, bars: [[label, value]] }], cur: 0, sel: -1 }
  function fmtDate(d) { if (!d) return "-"; var l = A.lang() === "ar" ? "ar" : "en-GB"; try { var x = new Date(d + "T00:00:00"); return '<span title="' + esc(x.toLocaleDateString(l, { day: "numeric", month: "long", year: "numeric" })) + '">' + esc(x.toLocaleDateString(l, { day: "numeric", month: "short" })) + "</span>"; } catch (e) { return esc(d); } }
  function num(n) { return Number(n || 0).toLocaleString("en-US"); }

  /* ---------- building blocks of the reference layout ---------- */
  function hero(o) {   // the big "wallet" card: title, sub, big figure, a lime action pill, three grey tiles
    return '<section class="card mo-hero"><div class="mo-hero-top"><div><h3>' + o.title + '</h3><p class="mo-sub">' + o.sub + "</p></div>" +
      (o.btn ? '<a class="mo-pill-lime" href="' + o.btn[0] + '">' + o.btn[1] + ic("plus") + "</a>" : "") + "</div>" +
      '<div class="mo-big">' + o.big + (o.unit ? "<small>" + o.unit + "</small>" : "") + "</div>" + (o.caption ? '<p class="mo-sub" style="margin:.1rem 0 0">' + o.caption + "</p>" : "") +
      (o.tiles ? '<div class="mo-tiles">' + o.tiles.map(function (x) { return '<a class="mo-tile" href="' + x[0] + '">' + ic(x[1]) + "<span>" + x[2] + "</span></a>"; }).join("") + "</div>" : "") +
      (o.note ? '<p class="mo-sub" style="margin:1rem 0 0">' + o.note + "</p>" : "") + "</section>";
  }
  function mini(icon, label, value, sub, href, tone) {   // the small "Current Balance" cards
    return '<a class="card mo-mini" href="' + (href || "#") + '"><div class="mo-mini-top">' + ic(icon) + "<span>" + label + "</span>" + ic("dots", "mo-dots") + '</div><div class="mo-mini-val">' + value + "</div>" +
      (sub ? '<span class="mo-trend ' + (tone || "") + '">' + sub + "</span>" : "") + "</a>";
  }
  function chartCard(title, total, sets) {
    chart = { sets: sets, cur: 0, sel: -1, title: title, total: total };
    return '<section class="card mo-chart" id="moChart">' + chartInner() + "</section>";
  }
  function chartInner() {
    var c = chart, set = c.sets[c.cur], vals = set.bars.map(function (b) { return b[1]; }), max = Math.max.apply(null, vals.concat([1]));
    var top = niceTop(max), sel = c.sel >= 0 ? c.sel : vals.indexOf(Math.max.apply(null, vals.concat([0])));
    var ticks = [top, top * .75, top * .5, top * .25, 0].map(function (v) { return Math.round(v); });
    return '<div class="mo-chart-head"><div><h3>' + c.title + '</h3><div class="mo-big sm">' + num(c.total) + '</div></div><span class="mo-select">' + t("c_all") + "</span></div>" +
      (c.sets.length > 1 ? '<div class="mo-seg" role="tablist">' + c.sets.map(function (s, i) { return '<button type="button" role="tab" data-act="mochart" data-i="' + i + '" aria-selected="' + (i === c.cur) + '">' + s.label + "</button>"; }).join("") + "</div>" : "") +
      '<div class="mo-plot"><div class="mo-axis">' + ticks.map(function (v) { return "<span>" + v + "</span>"; }).join("") + '</div><div class="mo-bars" style="--n:' + set.bars.length + '">' +
      set.bars.map(function (b, i) { var h = top ? b[1] / top * 100 : 0; return '<button type="button" class="mo-bar' + (i === sel ? " on" : "") + '" data-act="mobar" data-i="' + i + '" aria-label="' + esc(b[0]) + ": " + b[1] + '"><span class="mo-colbar">' + (i === sel ? '<b class="mo-tip">' + num(b[1]) + "</b>" : "") + '<i style="height:' + Math.max(h, b[1] ? 4 : 0) + '%"></i></span><small>' + esc(b[0]) + "</small></button>"; }).join("") + "</div></div>";
  }
  function niceTop(m) { if (m <= 4) return 4; var p = Math.pow(10, Math.floor(Math.log10(m))), f = m / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p; }
  A.act("mochart", function (el) { chart.cur = +el.getAttribute("data-i"); chart.sel = -1; document.getElementById("moChart").innerHTML = chartInner(); });
  A.act("mobar", function (el) { chart.sel = +el.getAttribute("data-i"); document.getElementById("moChart").innerHTML = chartInner(); });

  function people(title, sub, list, href) {   // the "Quick send" avatars row
    return '<section class="card mo-people"><h3>' + title + '</h3><p class="mo-sub">' + sub + '</p><div class="mo-faces">' + (list.length ? list.slice(0, 6).map(function (x, i) {
      return '<a class="mo-face' + (i === 0 ? " on" : "") + '" href="' + x.href + '" title="' + esc(x.name) + '">' + esc(String(x.name || "?").charAt(0)) + "</a>";
    }).join("") : '<span class="mo-sub">' + t("w_none") + "</span>") + '<a class="mo-face more" href="' + href + '" aria-label="' + t("w_all") + '">' + ic("chev", "rev2") + "</a></div></section>";
  }
  function dark(o) {   // the black "card" with two buttons under it
    return '<section class="card mo-darkwrap"><div class="mo-dark"><div class="mo-dark-top">' + ic(o.icon) + "<b>" + o.brand + '</b></div><div class="mo-dark-num" dir="ltr">' + o.number + '</div><div class="mo-dark-foot">' + o.foot.map(function (f) { return "<span><small>" + f[0] + "</small>" + f[1] + "</span>"; }).join("") + "</div></div>" +
      '<div class="mo-duo">' + o.buttons.map(function (b, i) { return b[2] ? '<button type="button" class="' + (i === 0 ? "mo-btn-lime" : "mo-btn-white") + '" data-act="' + b[2] + '">' + b[1] + "</button>" : '<a class="' + (i === 0 ? "mo-btn-lime" : "mo-btn-white") + '" href="' + b[0] + '">' + b[1] + "</a>"; }).join("") + "</div>" +
      (o.actions ? '<h4 class="mo-h4">' + t("qa") + '</h4><div class="mo-tiles">' + o.actions.map(function (x) { return '<a class="mo-tile" href="' + x[0] + '">' + ic(x[1]) + "<span>" + x[2] + "</span></a>"; }).join("") + "</div>" : "") + "</section>";
  }
  function plan(title, text, btn) {   // the "Starter Plan" call-to-action
    return '<section class="card mo-plan"><h3>' + title + '</h3><p class="mo-sub">' + text + '</p><a class="mo-btn-lime wide" href="' + btn[0] + '">' + btn[1] + "</a></section>";
  }
  function badge(s, offLabel) { return '<span class="badge ' + (s === "active" ? "" : s === "ended" ? "grey" : "warn") + '">' + (offLabel || t("st_" + s)) + "</span>"; }
  function table(title, head, rows, href) {
    return '<section class="card"><div class="mo-card-head"><h3>' + title + '</h3><a class="mo-select" href="' + href + '">' + t("w_all") + "</a></div>" +
      (rows.length ? '<div class="fb-table-wrap"><table class="fb-table"><thead><tr>' + head.map(function (h) { return "<th>" + h + "</th>"; }).join("") + "</tr></thead><tbody>" + rows.join("") + "</tbody></table></div>" : '<p class="note">' + t("w_none") + "</p>") + "</section>";
  }
  function who(a, b, href) { return '<td><a class="fb-who" href="' + href + '"><span class="avatar">' + esc(String(a).charAt(0)) + '</span><span><b>' + esc(a) + "</b><small>" + b + "</small></span></a></td>"; }

  /* ---------- the page ---------- */
  function render(d) {
    var u = A.user() || {}, h = new Date().getHours(), first = esc(String(u.name || "").split(" ")[0]);
    var head = '<div class="mo-head"><div><h1 class="title">' + t(h < 12 ? "gm" : h < 18 ? "ga" : "ge") + (first ? (A.lang() === "ar" ? "، " : ", ") + first : "") + '</h1><p class="lead">' + t("hello_sub") + "</p></div>" +
      (d.children ? '<form class="mo-search" action="people.html" method="get" role="search">' + ic("search") + '<input name="q" type="search" placeholder="' + esc(t("search_ph")) + '" aria-label="' + esc(t("search_ph")) + '"></form>' : "") + "</div>";
    var L = [], R = [], below = [];

    if (d.children) {   // ---- Aytam ----
      var c = d.children, bs = c.by_status || {}, apps = d.applications ? d.applications.new : null;
      L.push('<div class="mo-row">' + hero({ title: t("h_orphans"), sub: t("h_orphans_sub"), big: num(c.total), btn: A.can("children.create") ? ["people.html?add=1", t("h_add_orphan")] : null,
        tiles: [["people.html", "users", t("t_orphans")]].concat(d.applications ? [["applications.html", "file", t("t_apps")]] : []).concat([["people.html", "search", t("t_search")]]) }) +
        '<div class="mo-minis">' + mini("check", t("s_active"), num(bs.active), t("of_n", { n: num(c.total) }), "people.html") + mini("sliders", t("s_pending"), num(bs.pending), t("of_n", { n: num(c.total) }), "people.html") +
        mini("shield", t("s_verified"), num(c.bank_verified) + '<small>/' + num(c.bank_accounts) + "</small>", c.bank_accounts ? Math.round(c.bank_verified / c.bank_accounts * 100) + "%" : "-", "people.html", "up") +
        (apps !== null ? mini("file", t("s_newapps"), num(apps), apps ? t("need_review") : t("all_clear"), "applications.html", apps ? "down" : "up") : mini("users", t("st_ended"), num(bs.ended), t("of_n", { n: num(c.total) }), "people.html")) + "</div></div>");
      var age = c.by_age || {}, city = c.by_city || {};
      L.push(chartCard(t("c_orphans"), c.total, [
        { label: t("c_age"), bars: Object.keys(age).map(function (k) { return [k, age[k]]; }) },
        { label: t("c_city"), bars: Object.keys(city).map(function (k) { return [k.replace(/ City$/, ""), city[k]]; }) },
        { label: t("c_status"), bars: [[t("st_active"), bs.active || 0], [t("st_pending"), bs.pending || 0], [t("st_ended"), bs.ended || 0]] }
      ].filter(function (s) { return s.bars.length; })));
      R.push(people(t("q_latest_orphans"), t("q_latest_sub"), (c.latest || []).map(function (x) { return { name: A.name(x), href: "person.html?id=" + x.id }; }), "people.html"));
      if (d.applications) R.push(dark({ icon: "file", brand: t("dk_form"), number: esc(location.host + "/apply"), foot: [[t("dk_new"), num(apps)], [t("dk_total"), num(c.total)]],
        buttons: [[null, t("b_copy"), "mocopy"], [location.origin + "/apply", t("b_open")]], actions: [["people.html?add=1", "plus", t("qa_add")], ["applications.html", "down", t("qa_apps")], ["people.html", "dots", t("qa_more")]] }));
      R.push(c.samples ? plan(t("pl_samples"), t("pl_samples_txt", { n: c.samples }), ["people.html", t("pl_samples_btn")]) : plan(t("pl_soon"), t("pl_soon_txt"), ["settings.html", t("pl_soon_btn")]));
      var lat = c.latest || [], attn = c.needs_attention || [];
      below.push(table(t("w_latest"), [t("w_name"), t("w_status"), t("w_added")], lat.map(function (x) { return "<tr>" + who(A.name(x), esc(x.ref) + (x.city ? " · " + esc(x.city) : ""), "person.html?id=" + x.id) + "<td>" + badge(x.status || "active") + '</td><td class="muted">' + fmtDate(x.added) + "</td></tr>"; }), "people.html"));
      below.push('<section class="card"><div class="mo-card-head"><h3>' + t("attn") + "</h3></div>" + (attn.length ? '<ul class="fb-rows">' + attn.map(function (a) { return '<li><a class="fb-who" href="person.html?id=' + a.child_id + '"><span class="avatar">' + esc(a.name_en.charAt(0)) + "</span><span><b>" + esc(a.name_en) + "</b><small>" + esc(a.ref) + " · " + t("unverified") + "</small></span></a></li>"; }).join("") + "</ul>" : '<p class="note">' + t("attn_none") + "</p>") + "</section>");
    } else if (d.security) {   // ---- President (accounts) ----
      var s = d.security, br = s.by_role || {};
      L.push('<div class="mo-row">' + hero({ title: t("h_accounts"), sub: t("h_accounts_sub"), big: num(s.active_users), btn: A.can("users.manage") ? ["users.html?add=1", t("h_add_account")] : null,
        tiles: [["users.html", "shield", t("t_accounts")], ["setup.html", "check", t("t_setup")], ["settings.html", "gear", t("t_settings")]] }) +
        '<div class="mo-minis">' + mini("users", t("s_staff"), num(br.staff || 0), t("of_n", { n: num(s.active_users) }), "users.html") + mini("shield", t("s_security"), num(br.security_admin || 0), "", "users.html") +
        mini("sliders", t("s_maint"), num(br.admin_maintenance || 0), "", "users.html") + mini("bell", t("s_failed"), num(s.failed_logins_24h), t("last24"), "users.html", s.failed_logins_24h ? "down" : "up") + "</div></div>");
      var order = ["president_admin", "security_admin", "admin_maintenance", "staff", "user", "system_admin"];
      L.push(chartCard(t("c_accounts"), s.active_users, [{ label: t("c_role"), bars: order.map(function (r) { return [t("r_" + r), br[r] || 0]; }) }]));
      R.push(people(t("q_latest_accounts"), t("q_latest_acc_sub"), (s.latest_users || []).map(function (x) { return { name: x.name, href: "users.html" }; }), "users.html"));
      R.push(dark({ icon: "shield", brand: t("dk_me"), number: esc(u.role_label || ""), foot: [[t("h_accounts"), num(s.active_users)], [t("s_failed"), num(s.failed_logins_24h)]],
        buttons: [["settings.html", t("b_password")], ["users.html", t("b_accounts")]], actions: [["users.html?add=1", "plus", t("qa_add")], ["users.html", "users", t("t_accounts")]].concat(A.can("site.edit") ? [["../index.html?edit=1", "globe", t("qa_site")]] : [["settings.html", "dots", t("qa_more")]]) }));
      R.push(d.setup && d.setup.steps.length && d.setup.done < d.setup.total ? plan(t("pl_setup"), t("pl_setup_txt", { n: d.setup.done, m: d.setup.total }), ["setup.html", t("pl_setup_btn")]) : plan(t("pl_soon"), t("pl_soon_txt"), ["settings.html", t("pl_soon_btn")]));
      below.push(table(t("w_latest_acc"), [t("w_name"), t("w_status"), t("w_added")], (s.latest_users || []).map(function (x) { return "<tr>" + who(x.name, t("r_" + x.role), "users.html") + "<td>" + badge(x.active ? "active" : "ended", x.active ? null : t("w_off")) + '</td><td class="muted">' + fmtDate(x.added) + "</td></tr>"; }), "users.html"));
    } else if (d.health) {   // ---- System Admin / Maintenance ----
      var hl = d.health, ck = hl.checks || {}, okAll = hl.status === "ok";
      L.push('<div class="mo-row">' + hero({ title: t("h_system"), sub: t("h_system_sub"), big: t(okAll ? "ok" : "problem"), btn: A.can("system.updates") ? ["updates.html", t("h_update")] : null,
        tiles: [["system.html", "sliders", t("t_system")]].concat(A.can("system.updates") ? [["updates.html", "download", t("t_updates")]] : []).concat([["settings.html", "gear", t("t_settings")]]) }) +
        '<div class="mo-minis">' + mini("check", t("s_db"), t(ck.database ? "ok" : "problem"), "", "system.html", ck.database ? "up" : "down") + mini("folder", t("s_storage"), t(ck.storage_writable ? "ok" : "problem"), "", "system.html", ck.storage_writable ? "up" : "down") +
        mini("sliders", t("s_php"), esc(hl.versions.php), "", "system.html") + mini("file", t("s_laravel"), esc(hl.versions.laravel), "", "system.html") + "</div></div>");
      L.push('<section class="card"><div class="mo-card-head"><h3>' + t("c_sys") + '</h3><a class="mo-select" href="system.html">' + t("t_system") + '</a></div><ul class="fb-rows">' +
        [[t("s_db"), ck.database], [t("s_storage"), ck.storage_writable], ["PHP " + esc(hl.versions.php), true], ["Laravel " + esc(hl.versions.laravel), true], [t("s_env") + ": " + esc(hl.environment), true]].map(function (r) { return '<li><span class="grow">' + r[0] + "</span>" + badge(r[1] ? "active" : "pending", t(r[1] ? "ok" : "problem")) + "</li>"; }).join("") + "</ul></section>");
      if (d.president !== undefined) R.push(people(t("q_pres"), t("q_pres_sub"), d.president ? [{ name: d.president.name, href: "system.html" }] : [], "system.html"));
      if (A.can("system.updates")) R.push(dark({ icon: "download", brand: t("dk_build"), number: '<span id="moBuild">…</span>', foot: [["PHP", esc(hl.versions.php)], [t("s_env"), esc(hl.environment)]],
        buttons: [["updates.html", t("b_upload")], ["system.html", t("b_system")]], actions: [["updates.html", "up", t("t_updates")], ["system.html", "sliders", t("t_system")], ["settings.html", "dots", t("qa_more")]] }));
      R.push(d.setup && d.setup.steps.length && d.setup.done < d.setup.total ? plan(t("pl_setup"), t("pl_setup_txt", { n: d.setup.done, m: d.setup.total }), ["setup.html", t("pl_setup_btn")]) : (A.can("system.updates") ? plan(t("pl_key"), t("pl_key_txt"), ["updates.html", t("pl_key_btn")]) : ""));
    } else {   // ---- a role whose features are still off ----
      L.push(hero({ title: t("h_welcome"), sub: t("h_welcome_sub"), big: first || "-", note: t("off_note"), tiles: [["settings.html", "gear", t("t_settings")]] }));
      R.push(plan(t("pl_soon"), t("pl_soon_txt"), ["settings.html", t("pl_soon_btn")]));
    }
    return '<div class="mo">' + head + '<div class="mo-grid"><div class="mo-col">' + L.join("") + '</div><div class="mo-col">' + R.join("") + "</div></div>" + (below.length ? '<div class="mo-below">' + below.join("") + "</div>" : "") + "</div>";
  }
  A.act("mocopy", function () {
    var link = location.origin + "/apply";
    (navigator.clipboard ? navigator.clipboard.writeText(link) : Promise.reject()).then(function () { A.toast(t("copied")); }, function () { A.toast(link); });
  });

  function load() {
    var jobs = [A.api.get("dashboard"), A.api.get("setup").catch(function () { return null; })];
    jobs.push(A.can("system.health") ? A.api.get("system/health", { cache: false }).catch(function () { return null; }) : Promise.resolve(null));
    jobs.push(A.can("system.first_admin") ? A.api.get("system/president-admin", { cache: false }).catch(function () { return undefined; }) : Promise.resolve(undefined));
    return Promise.all(jobs).then(function (r) {
      var d = r[0].data; d.setup = r[1] ? r[1].data : null; if (r[2]) d.health = r[2].data;
      if (r[3] !== undefined && r[3] !== null) d.president = r[3].data;
      return { data: d, stale: r[0].stale, at: r[0].at };
    });
  }
  function after(d, st) {
    var bEl = document.getElementById("moBuild");   // the version number comes a moment later: the page does not wait for it
    if (bEl) A.api.get("system/updates", { cache: false }).then(function (r) { var x = document.getElementById("moBuild"); if (x) x.textContent = r.data.build || "-"; }, function () { var x = document.getElementById("moBuild"); if (x) x.textContent = "-"; });
    var u = A.user();
    if (d.setup && d.setup.required_left > 0 && !st.stale && u && !(u.preferences && u.preferences.setup_seen)) location.replace("setup.html");
  }
  A.start({ page: "dashboard", nav: "dashboard", title: "n_home", shell: true, load: load, render: render, after: after });
})();
