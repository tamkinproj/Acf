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

  A.strings({
    f_attendance: "Attendance", fo_attendance: "{p} in · {l} late · {a} absent", f_work: "Work tracker", fo_work: "{n} active now · {m} active today", h_att_sub: "Who is in the office today", h_work_sub: "How staff use the app today", d_in_of: "of {n} employees", d_open_att: "Open attendance", d_open_work: "Open work tracker", d_att_week: "Clocked in, last 7 days", d_att_present: "Clocked in", d_att_late: "Late", t_att_today: "Today", t_att_people: "People", t_att_pay: "Payroll", s_ontime: "On time", s_late: "Late", s_absent: "Absent", s_onleave: "On leave", d_latest_in: "Latest clock-ins", d_latest_in_sub: "Today", d_late_min: "{n} min late", d_w_now: "active now", d_w_today: "Active today", d_w_idle: "Clocked in, not active", d_w_actions: "Actions today", d_w_time: "Active time today", d_w_chart: "Active minutes today", d_w_top: "Most active today", d_w_top_sub: "Staff", d_idle_t: "Clocked in but not active", d_idle_txt: "{n} staff are clocked in but have not used the app for over 30 minutes.", d_h: "h", d_m: "m", d_wk_nodata: "No activity yet today", f_education: "Education", fo_education: "{n} teachers · {m} contracts to watch", h_edu_sub: "Alimni teachers, contracts and salaries, madrasa and markaz, curriculum", h_add_teacher: "Add teacher", last30: "next 30 days", edu_mk: "{m} madrasa · {k} markaz", edu_attn: "Contracts need attention", edu_attn_txt: "{n} contracts end within 30 days or have ended. Renew them from the teacher's page.", edu_attn_btn: "Open Education", tb_teachers: "Teachers", tb_salaries: "Salaries", tb_institutions: "Madrasa and markaz", tb_curricula: "Curriculum", cs_active: "Active", cs_ending_soon: "Ending soon", cs_expired: "Expired",
    f_hello: "Choose a department.", f_back: "Home", f_aytam: "Aytam", f_finance: "Finance", f_projects: "Projects", f_website: "Website", f_accounts: "Accounts", f_chat: "Chat",
    fo_aytam: "{n} orphans · {m} new applications", fo_finance: "{p} paid · {d} due", fo_projects: "{n} active of {m}", fo_website: "Edit the landing page", fo_accounts: "{n} accounts",
    fo_chat: "{n} unread", fo_chat0: "No new messages", f_add_user: "Add office user", f_month: "This month", f_paid: "Paid", f_due: "Due", f_pending_n: "Pending",
    f_amt_paid: "Paid", f_amt_due: "Still due", f_total: "Total", f_active_p: "Active", f_open: "Open", f_edit_site: "Edit the page", f_site_page: "Website editor", f_pay_page: "Payments", f_rep_page: "Reports",
    f_proj_page: "Projects", f_site_note: "Click texts on the page to change them, then Save draft and Publish."
  }, {
    f_attendance: "الحضور", fo_attendance: "{p} حاضر · {l} متأخر · {a} غائب", f_work: "متابعة العمل", fo_work: "{n} نشط الآن · {m} نشط اليوم", h_att_sub: "من في المكتب اليوم", h_work_sub: "كيف يستخدم الموظفون التطبيق اليوم", d_in_of: "من {n} موظفًا", d_open_att: "افتح الحضور", d_open_work: "افتح متابعة العمل", d_att_week: "الحضور آخر 7 أيام", d_att_present: "حضر", d_att_late: "متأخر", t_att_today: "اليوم", t_att_people: "الموظفون", t_att_pay: "الرواتب", s_ontime: "في الوقت", s_late: "متأخر", s_absent: "غائب", s_onleave: "في إجازة", d_latest_in: "آخر تسجيلات الحضور", d_latest_in_sub: "اليوم", d_late_min: "تأخر {n} دقيقة", d_w_now: "نشط الآن", d_w_today: "نشط اليوم", d_w_idle: "سجّل حضوره ولا نشاط", d_w_actions: "إجراءات اليوم", d_w_time: "وقت النشاط اليوم", d_w_chart: "دقائق النشاط اليوم", d_w_top: "الأكثر نشاطًا اليوم", d_w_top_sub: "الموظفون", d_idle_t: "سجّل حضوره ولا نشاط", d_idle_txt: "{n} موظفين سجّلوا حضورهم ولم يستخدموا التطبيق منذ أكثر من 30 دقيقة.", d_h: "س", d_m: "د", d_wk_nodata: "لا نشاط اليوم بعد", f_education: "التعليم", fo_education: "{n} معلمًا · {m} عقود للمتابعة", h_edu_sub: "معلمو علّمني والعقود والرواتب والمدرسة والمراكز والمنهج", h_add_teacher: "إضافة معلم", last30: "خلال 30 يومًا", edu_mk: "{m} مدرسة · {k} مراكز", edu_attn: "عقود تحتاج إلى انتباه", edu_attn_txt: "{n} عقود تنتهي خلال 30 يومًا أو انتهت. جدّدها من صفحة المعلم.", edu_attn_btn: "افتح التعليم", tb_teachers: "المعلمون", tb_salaries: "الرواتب", tb_institutions: "المدرسة والمراكز", tb_curricula: "المنهج", cs_active: "ساري", cs_ending_soon: "ينتهي قريبًا", cs_expired: "منتهٍ",
    f_hello: "اختر قسمًا.", f_back: "الرئيسية", f_aytam: "الأيتام", f_finance: "المالية", f_projects: "المشاريع", f_website: "الموقع", f_accounts: "الحسابات", f_chat: "المحادثات",
    fo_aytam: "{n} يتيمًا · {m} طلبات جديدة", fo_finance: "{p} مدفوعة · {d} مستحقة", fo_projects: "{n} نشطة من {m}", fo_website: "عدّل الصفحة الرئيسية للموقع", fo_accounts: "{n} حسابًا",
    fo_chat: "{n} غير مقروءة", fo_chat0: "لا رسائل جديدة", f_add_user: "إضافة مستخدم للمكتب", f_month: "هذا الشهر", f_paid: "مدفوعة", f_due: "مستحقة", f_pending_n: "قيد الانتظار",
    f_amt_paid: "المدفوع", f_amt_due: "المتبقي", f_total: "الإجمالي", f_active_p: "نشطة", f_open: "فتح", f_edit_site: "تعديل الصفحة", f_site_page: "محرر الموقع", f_pay_page: "المدفوعات", f_rep_page: "التقارير",
    f_proj_page: "المشاريع", f_site_note: "اضغط على النصوص في الصفحة لتغييرها، ثم احفظ المسودة وانشر."
  });
  var FOLDERS = ["aytam", "finance", "projects", "education", "attendance", "work", "website", "accounts"];
  var ICONS = { aytam: "users", finance: "wallet", projects: "folder", education: "book", attendance: "clock", work: "pulse", website: "globe", accounts: "shield", chat: "chat" };
  // The President's Home: one quiet tile per department. Everything else lives inside the department's folder (dashboard.html?d=NAME).
  function folderTile(key, href, sub) {
    return '<a class="fo" href="' + href + '"><span class="fo-top">' + ic(ICONS[key]) + ic("chev", "fo-go") + '</span><b>' + t("f_" + key) + '</b><span class="fo-sub">' + sub + "</span></a>";
  }
  function homeFolders(d, head, extra) {
    var tiles = [];
    if (d.children) tiles.push(folderTile("aytam", "dashboard.html?d=aytam", t("fo_aytam", { n: num(d.children.total), m: num(d.applications ? d.applications.new : 0) })));
    if (d.payments) tiles.push(folderTile("finance", "dashboard.html?d=finance", t("fo_finance", { p: num(d.payments.paid_count), d: num(d.payments.pending_count) })));
    if (d.projects) tiles.push(folderTile("projects", "dashboard.html?d=projects", t("fo_projects", { n: num(d.projects.active), m: num(d.projects.total) })));
    if (d.education) tiles.push(folderTile("education", "dashboard.html?d=education", t("fo_education", { n: num(d.education.teachers), m: num(d.education.contracts.ending_soon + d.education.contracts.expired) })));
    if (d.attendance) tiles.push(folderTile("attendance", "dashboard.html?d=attendance", t("fo_attendance", { p: num(d.attendance.counts.present + d.attendance.counts.late), l: num(d.attendance.counts.late), a: num(d.attendance.counts.absent) })));
    if (d.work) tiles.push(folderTile("work", "dashboard.html?d=work", t("fo_work", { n: num(d.work.active_now), m: num(d.work.active_today) })));
    if (A.can("site.edit")) tiles.push(folderTile("website", "dashboard.html?d=website", t("fo_website")));
    if (d.security) tiles.push(folderTile("accounts", "dashboard.html?d=accounts", t("fo_accounts", { n: num(d.security.active_users) })));
    if (A.can("chat.use")) tiles.push(folderTile("chat", "chat.html", d.unread ? t("fo_chat", { n: num(d.unread) }) : t("fo_chat0")));
    return '<div class="mo">' + head + (A.can("users.manage") ? '<a class="fo-add" href="users.html?add=1">' + ic("plus") + "<span>" + t("f_add_user") + "</span></a>" : "") +
      '<div class="fo-grid">' + tiles.join("") + "</div>" + (extra || "") + "</div>";
  }
  function folderHead(key) {
    return '<div class="mo-head"><div><a class="fo-back" href="dashboard.html">' + ic("back", "flip") + "<span>" + t("f_back") + '</span></a><h1 class="title">' + t("f_" + key) + "</h1></div></div>";
  }

  var charts = [];   // each: { sets: [{ label, bars: [[label, value]] }], cur: 0, sel: -1, title, total }
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
    var i = charts.push({ sets: sets, cur: 0, sel: -1, title: title, total: total }) - 1;
    return '<section class="card mo-chart" id="moChart' + i + '">' + chartInner(i) + "</section>";
  }
  function chartInner(ci) {
    var c = charts[ci], set = c.sets[c.cur], vals = set.bars.map(function (b) { return b[1]; }), max = Math.max.apply(null, vals.concat([1]));
    var top = niceTop(max), sel = c.sel >= 0 ? c.sel : vals.indexOf(Math.max.apply(null, vals.concat([0])));
    var ticks = [top, top * .75, top * .5, top * .25, 0].map(function (v) { return Math.round(v); });
    return '<div class="mo-chart-head"><div><h3>' + c.title + '</h3><div class="mo-big sm">' + num(c.total) + '</div></div><span class="mo-select">' + t("c_all") + "</span></div>" +
      (c.sets.length > 1 ? '<div class="mo-seg" role="tablist">' + c.sets.map(function (s, i) { return '<button type="button" role="tab" data-act="mochart" data-c="' + ci + '" data-i="' + i + '" aria-selected="' + (i === c.cur) + '">' + s.label + "</button>"; }).join("") + "</div>" : "") +
      '<div class="mo-plot"><div class="mo-axis">' + ticks.map(function (v) { return "<span>" + v + "</span>"; }).join("") + '</div><div class="mo-bars" style="--n:' + set.bars.length + '">' +
      set.bars.map(function (b, i) { var h = top ? b[1] / top * 100 : 0; return '<button type="button" class="mo-bar' + (i === sel ? " on" : "") + '" data-act="mobar" data-c="' + ci + '" data-i="' + i + '" aria-label="' + esc(b[0]) + ": " + b[1] + '"><span class="mo-colbar">' + (i === sel ? '<b class="mo-tip">' + num(b[1]) + "</b>" : "") + '<i style="height:' + Math.max(h, b[1] ? 4 : 0) + '%"></i></span><small>' + esc(b[0]) + "</small></button>"; }).join("") + "</div></div>";
  }
  function niceTop(m) { if (m <= 4) return 4; var p = Math.pow(10, Math.floor(Math.log10(m))), f = m / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p; }
  A.act("mochart", function (el) { var c = +el.getAttribute("data-c"); charts[c].cur = +el.getAttribute("data-i"); charts[c].sel = -1; document.getElementById("moChart" + c).innerHTML = chartInner(c); });
  A.act("mobar", function (el) { var c = +el.getAttribute("data-c"); charts[c].sel = +el.getAttribute("data-i"); document.getElementById("moChart" + c).innerHTML = chartInner(c); });

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

  function durH(sec) { sec = Math.max(0, Math.round(sec || 0)); var h = Math.floor(sec / 3600), m = Math.round((sec % 3600) / 60); if (m === 60) { h++; m = 0; } return h ? h + t("d_h") + " " + m + t("d_m") : m + t("d_m"); }
  function wday(date) { var p = date.split("-"); return new Date(+p[0], +p[1] - 1, +p[2], 12).toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { weekday: "short" }); }
  function attBranch(d, L, R, pres) {   // ---- Attendance overview ----
    var a = d.attendance, c = a.counts;
    L.push('<div class="mo-row">' + hero({ title: t("f_attendance"), sub: t("h_att_sub"), big: num(a.clocked_in), unit: "/ " + num(a.total), caption: t("d_in_of", { n: num(a.total) }), btn: ["attendance.html", t("d_open_att")],
      tiles: [["attendance.html", "clock", t("t_att_today")], ["attendance.html", "users", t("t_att_people")]].concat(A.can("attendance.salary") ? [["attendance.html", "wallet", t("t_att_pay")]] : []) }) +
      '<div class="mo-minis">' + mini("check", t("s_ontime"), num(c.present), t("of_n", { n: num(a.total) }), "attendance.html", "up") + mini("bell", t("s_late"), num(c.late), t("of_n", { n: num(a.total) }), "attendance.html", c.late ? "down" : "up") +
      mini("sliders", t("s_absent"), num(c.absent), t("of_n", { n: num(a.total) }), "attendance.html", c.absent ? "down" : "up") + mini("file", t("s_onleave"), num(c.leave), t("of_n", { n: num(a.total) }), "attendance.html") + "</div></div>");
    L.push(chartCard(t("d_att_week"), a.clocked_in, [
      { label: t("d_att_present"), bars: a.week.map(function (w) { return [wday(w.date), w.present]; }) },
      { label: t("d_att_late"), bars: a.week.map(function (w) { return [wday(w.date), w.late]; }) }
    ]));
    R.push(people(t("d_latest_in"), t("d_latest_in_sub"), (a.latest || []).map(function (x) { return { name: A.lang() === "ar" && x.name_ar ? x.name_ar : x.name_en, href: "attendance.html" }; }), "attendance.html"));
    if (d.work) R.push(plan(t("f_work"), t("fo_work", { n: num(d.work.active_now), m: num(d.work.active_today) }), ["work.html", t("d_open_work")]));
  }
  function workBranch(d, L, R) {   // ---- Work tracker overview ----
    var w = d.work;
    L.push('<div class="mo-row">' + hero({ title: t("f_work"), sub: t("h_work_sub"), big: num(w.active_now), unit: "/ " + num(w.staff), caption: t("d_w_now"), btn: ["work.html", t("d_open_work")], tiles: [["work.html", "pulse", t("t_att_today")], ["work.html", "users", t("t_att_people")]] }) +
      '<div class="mo-minis">' + mini("check", t("d_w_today"), num(w.active_today), t("of_n", { n: num(w.staff) }), "work.html", "up") + mini("bell", t("d_w_idle"), num(w.idle_clocked_in), "", "work.html", w.idle_clocked_in ? "down" : "up") +
      mini("sliders", t("d_w_actions"), num(w.actions_today), "", "work.html") + mini("clock", t("d_w_time"), durH(w.seconds_today), "", "work.html") + "</div></div>");
    var top = w.top || [];
    if (top.length) L.push(chartCard(t("d_w_chart"), Math.round(w.seconds_today / 60), [{ label: t("d_w_top"), bars: top.map(function (x) { return [String(x.name).split(" ")[0], Math.round(x.seconds / 60)]; }) }]));
    R.push(people(t("d_w_top"), t("d_w_top_sub"), top.map(function (x) { return { name: x.name, href: "work.html" }; }), "work.html"));
    R.push(w.idle_clocked_in ? plan(t("d_idle_t"), t("d_idle_txt", { n: w.idle_clocked_in }), ["work.html", t("d_open_work")]) : plan(t("pl_soon"), t("pl_soon_txt"), ["work.html", t("d_open_work")]));
  }
  /* ---------- the page ---------- */
  function render(d) {
    var u = A.user() || {}, h = new Date().getHours(), first = esc(String(u.name || "").split(" ")[0]);
    var head = '<div class="mo-head"><div><h1 class="title">' + t(h < 12 ? "gm" : h < 18 ? "ga" : "ge") + (first ? (A.lang() === "ar" ? "، " : ", ") + first : "") + '</h1><p class="lead">' + t("hello_sub") + "</p></div>" +
      (d.children ? '<form class="mo-search" action="people.html" method="get" role="search">' + ic("search") + '<input name="q" type="search" placeholder="' + esc(t("search_ph")) + '" aria-label="' + esc(t("search_ph")) + '"></form>' : "") + "</div>";
    charts = [];
    var pres = u.role === "president_admin", folder = pres ? ((location.search.match(/[?&]d=([a-z]+)/) || [])[1] || "") : "";
    if (pres && FOLDERS.indexOf(folder) < 0) {
      var hm = '<div class="mo-head"><div><h1 class="title">' + t(h < 12 ? "gm" : h < 18 ? "ga" : "ge") + (first ? (A.lang() === "ar" ? "، " : ", ") + first : "") + '</h1><p class="lead">' + t("f_hello") + "</p></div></div>";
      return homeFolders(d, hm, homeSections(d, u, first));
    }
    if (pres) head = folderHead(folder);
    return '<div class="mo">' + head + body(d, folder, pres, u, first) + "</div>";
  }

  /* The President's Home: every overview, one after the other, each with its own Open link. */
  function homeSections(d, u, first) {
    var secs = [["aytam", d.children], ["attendance", d.attendance], ["work", d.work], ["education", d.education], ["finance", d.payments], ["projects", d.projects]];
    return secs.filter(function (x) { return x[1]; }).map(function (x) {
      return '<section class="ov"><div class="ov-head"><h2>' + t("f_" + x[0]) + '</h2><a class="mo-select" href="dashboard.html?d=' + x[0] + '">' + t("f_open") + "</a></div>" + body(d, x[0], true, u, first) + "</section>";
    }).join("");
  }

  /* One overview (or, for other roles, the whole page body): the cards, the chart, the side column and the tables. */
  function body(d, folder, pres, u, first) {
    var L = [], R = [], below = [];

    if (d.children && (!pres || folder === "aytam")) {   // ---- Aytam ----
      var c = d.children, bs = c.by_status || {}, apps = d.applications ? d.applications.new : null;
      L.push('<div class="mo-row">' + hero({ title: t("h_orphans"), sub: t("h_orphans_sub"), big: num(c.total), btn: A.can("children.create") ? ["add.html", t("h_add_orphan")] : null,
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
        buttons: [[null, t("b_copy"), "mocopy"], [location.origin + "/apply", t("b_open")]], actions: [["add.html", "plus", t("qa_add")], ["applications.html", "down", t("qa_apps")], ["people.html", "dots", t("qa_more")]] }));
      R.push(c.samples ? plan(t("pl_samples"), t("pl_samples_txt", { n: c.samples }), ["people.html", t("pl_samples_btn")]) : plan(t("pl_soon"), t("pl_soon_txt"), ["settings.html", t("pl_soon_btn")]));
      var lat = c.latest || [], attn = c.needs_attention || [];
      below.push(table(t("w_latest"), [t("w_name"), t("w_status"), t("w_added")], lat.map(function (x) { return "<tr>" + who(A.name(x), esc(x.ref) + (x.city ? " · " + esc(x.city) : ""), "person.html?id=" + x.id) + "<td>" + badge(x.status || "active") + '</td><td class="muted">' + fmtDate(x.added) + "</td></tr>"; }), "people.html"));
      below.push('<section class="card"><div class="mo-card-head"><h3>' + t("attn") + "</h3></div>" + (attn.length ? '<ul class="fb-rows">' + attn.map(function (a) { return '<li><a class="fb-who" href="person.html?id=' + a.child_id + '"><span class="avatar">' + esc(a.name_en.charAt(0)) + "</span><span><b>" + esc(a.name_en) + "</b><small>" + esc(a.ref) + " · " + t("unverified") + "</small></span></a></li>"; }).join("") + "</ul>" : '<p class="note">' + t("attn_none") + "</p>") + "</section>");
    } else if (d.education && (!pres || folder === "education")) {   // ---- Education ----
      var ed = d.education, ec = ed.contracts || {}, eattn = (ec.ending_soon || 0) + (ec.expired || 0);
      L.push('<div class="mo-row">' + hero({ title: t("f_education"), sub: t("h_edu_sub"), big: num(ed.teachers), btn: A.can("education.manage") ? ["edu-new.html?kind=teacher", t("h_add_teacher")] : null,
        tiles: [["education.html", "users", t("tb_teachers")]].concat(A.can("education.salary") ? [["education.html", "wallet", t("tb_salaries")]] : []).concat([["education.html", "folder", t("tb_curricula")]]) }) +
        '<div class="mo-minis">' + mini("check", t("cs_active"), num(ec.active || 0), t("of_n", { n: num(ed.teachers) }), "education.html", "up") + mini("bell", t("cs_ending_soon"), num(ec.ending_soon || 0), t("last30"), "education.html", ec.ending_soon ? "down" : "up") +
        mini("sliders", t("cs_expired"), num(ec.expired || 0), "", "education.html", ec.expired ? "down" : "up") + mini("folder", t("tb_institutions"), num(ed.madrasa + ed.markaz), t("edu_mk", { m: num(ed.madrasa), k: num(ed.markaz) }), "education.html") + "</div></div>");
      R.push(eattn ? plan(t("edu_attn"), t("edu_attn_txt", { n: eattn }), ["education.html", t("edu_attn_btn")]) : plan(t("pl_soon"), t("pl_soon_txt"), ["education.html", t("f_education")]));
    } else if (d.security && (!pres || folder === "accounts")) {   // ---- accounts ----
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
    } else if (pres && folder === "finance" && d.payments) {   // ---- Finance (President's folder) ----
      var py = d.payments;
      L.push('<div class="mo-row">' + hero({ title: t("f_finance"), sub: t("f_month") + " · " + esc(A.monthLabel(py.month)), big: A.money(py.paid_total), caption: t("f_amt_paid"),
        tiles: [["payments.html", "wallet", t("f_pay_page")]].concat(A.can("reports.view") ? [["reports.html", "file", t("f_rep_page")]] : []) }) +
        '<div class="mo-minis">' + mini("check", t("f_paid"), num(py.paid_count), t("of_n", { n: num(py.children) }), "payments.html", "up") + mini("sliders", t("f_pending_n"), num(py.pending_count), t("of_n", { n: num(py.children) }), "payments.html", py.pending_count ? "down" : "up") +
        mini("wallet", t("f_amt_due"), A.money(py.due_total), t("f_month"), "payments.html") + "</div></div>");
    } else if (pres && folder === "projects" && d.projects) {   // ---- Projects ----
      var pj = d.projects;
      L.push('<div class="mo-row">' + hero({ title: t("f_projects"), sub: t("f_active_p"), big: num(pj.active), caption: t("of_n", { n: num(pj.total) }),
        tiles: [["projects.html", "folder", t("f_proj_page")]] }) + '<div class="mo-minis">' + mini("folder", t("f_total"), num(pj.total), "", "projects.html") + mini("check", t("f_active_p"), num(pj.active), t("of_n", { n: num(pj.total) }), "projects.html", "up") + "</div></div>");
    } else if (pres && folder === "website") {   // ---- Website ----
      L.push('<div class="mo-row">' + hero({ title: t("f_website"), sub: t("f_site_note"), big: t("f_website"),
        btn: ["../index.html?edit=1", t("f_edit_site")], tiles: [["../index.html?edit=1", "edit", t("f_edit_site")], ["website.html", "globe", t("f_site_page")], ["../index.html", "search", t("f_open")]] }) + "</div>");
    } else if (d.attendance && (!pres || folder === "attendance")) {
      attBranch(d, L, R, pres);
    } else if (d.work && (!pres || folder === "work")) {
      workBranch(d, L, R);
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
    return '<div class="mo-grid"><div class="mo-col">' + L.join("") + '</div><div class="mo-col">' + R.join("") + "</div></div>" + (below.length ? '<div class="mo-below">' + below.join("") + "</div>" : "");
  }
  A.act("mocopy", function () {
    var link = location.origin + "/apply";
    (navigator.clipboard ? navigator.clipboard.writeText(link) : Promise.reject()).then(function () { A.toast(t("copied")); }, function () { A.toast(link); });
  });

  function load() {
    var jobs = [A.api.get("dashboard"), A.api.get("setup").catch(function () { return null; })];
    jobs.push(A.can("chat.use") && A.user() && A.user().role === "president_admin" ? A.api.get("chat/unread", { cache: false }).catch(function () { return null; }) : Promise.resolve(null));
    jobs.push(A.can("system.health") ? A.api.get("system/health", { cache: false }).catch(function () { return null; }) : Promise.resolve(null));
    jobs.push(A.can("system.first_admin") ? A.api.get("system/president-admin", { cache: false }).catch(function () { return undefined; }) : Promise.resolve(undefined));
    return Promise.all(jobs).then(function (r) {
      var d = r[0].data; d.setup = r[1] ? r[1].data : null; if (r[2]) { d.unread = r[2].data.unread || 0; A.setUnread(d.unread); } if (r[3]) d.health = r[3].data;
      if (r[4] !== undefined && r[4] !== null) d.president = r[4].data;
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
