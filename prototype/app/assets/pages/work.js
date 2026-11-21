/* Work tracker (HR and the President): how each staff member uses the app. Counts and times only. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  var L = [
    ["wk_title", "Work tracker", "متابعة العمل"], ["wk_lead", "See how staff use the app: when they were active, for how long, and what they did. Only counts and times are kept, never what they looked at.", "اطّلع على كيفية استخدام الموظفين للتطبيق: متى كانوا نشطين ولكم من الوقت وماذا فعلوا. تُحفظ الأعداد والأوقات فقط، وليس ما اطلعوا عليه."],
    ["wk_today", "Today", "اليوم"], ["wk_week", "Last 7 days", "آخر 7 أيام"], ["wk_month", "Month", "الشهر"],
    ["wk_active_now", "Active now", "نشط الآن"], ["wk_active_today", "Active today", "نشط اليوم"], ["wk_idle", "Clocked in, not active", "سجّل حضوره ولا نشاط"], ["wk_actions", "Actions", "الإجراءات"],
    ["wk_s_active", "Active now", "نشط الآن"], ["wk_s_recent", "Recently active", "نشط مؤخرًا"], ["wk_s_away", "Away", "غائب"], ["wk_s_never", "No activity yet", "لا نشاط بعد"],
    ["wk_last", "Last active {x}", "آخر نشاط {x}"], ["wk_never", "Not active yet", "لم يكن نشطًا بعد"], ["wk_just", "just now", "الآن"], ["wk_min_ago", "{n} min ago", "قبل {n} دقيقة"], ["wk_h_ago", "{n} h ago", "قبل {n} ساعة"],
    ["wk_active_time", "Active time", "وقت النشاط"], ["wk_days", "Days active", "أيام النشاط"], ["wk_views", "Looked at", "اطّلع على"], ["wk_did", "Did", "فعل"],
    ["wk_clock_in", "Clocked in {x}", "حضر الساعة {x}"], ["wk_not_clocked", "Not clocked in", "لم يسجّل حضوره"], ["wk_no_link", "No attendance record", "لا سجل حضور"], ["wk_clocked_days", "{n} days clocked in", "{n} أيام حضور"],
    ["wk_idle_flag", "Clocked in but no activity for over 30 minutes", "سجّل حضوره ولا نشاط منذ أكثر من 30 دقيقة"],
    ["wk_empty_t", "No activity recorded yet", "لا نشاط مسجّل بعد"], ["wk_empty_p", "Activity is recorded from now on, as staff use the app. To see how the screens look, load sample activity for the last two weeks.", "يُسجَّل النشاط من الآن فصاعدًا أثناء استخدام الموظفين للتطبيق. لترى شكل الشاشات، حمّل نشاطًا تجريبيًا لآخر أسبوعين."],
    ["wk_none_staff", "No staff accounts yet. Add staff on the Accounts screen.", "لا توجد حسابات موظفين بعد. أضفهم من شاشة الحسابات."],
    ["wk_load", "Load sample activity", "تحميل نشاط تجريبي"], ["wk_remove", "Remove sample activity", "حذف النشاط التجريبي"], ["wk_loaded", "Sample activity loaded", "تم تحميل النشاط التجريبي"], ["wk_removed", "Sample activity removed", "تم حذف النشاط التجريبي"],
    ["wk_sure", "Remove the sample activity?", "حذف النشاط التجريبي؟"], ["wk_recent", "Recent actions", "آخر الإجراءات"], ["wk_none_recent", "No actions recorded yet.", "لا إجراءات مسجّلة بعد."], ["wk_by_area", "Where the time went", "أين ذهب الوقت"],
    ["wk_daily", "Day by day", "يومًا بيوم"], ["wk_no_activity_day", "No activity", "لا نشاط"], ["wk_actions_n", "{n} actions", "{n} إجراء"], ["wk_conn", "This needs a connection.", "هذا يحتاج اتصالًا."], ["wk_close", "Close", "إغلاق"],
    ["wk_a_orphans", "Orphans", "الأيتام"], ["wk_a_finance", "Payments and reports", "المدفوعات والتقارير"], ["wk_a_projects", "Projects", "المشاريع"], ["wk_a_education", "Education", "التعليم"], ["wk_a_attendance", "Attendance", "الحضور"],
    ["wk_a_chat", "Chat", "المحادثة"], ["wk_a_website", "Website", "الموقع"], ["wk_a_accounts", "Accounts", "الحسابات"], ["wk_a_other", "Other", "أخرى"],
    ["wk_d_aytam", "Aytam", "الأيتام"], ["wk_d_finance", "Finance", "المالية"], ["wk_d_projects", "Projects", "المشاريع"], ["wk_d_relief", "Relief", "الإغاثة"], ["wk_d_education", "Education", "التعليم"], ["wk_d_hr", "Human resources", "الموارد البشرية"], ["wk_d_administration", "Administration", "الإدارة"],
    ["wk_h", "h", "س"], ["wk_m", "m", "د"]
  ];
  var EN = {}, AR = {}; L.forEach(function (x) { EN[x[0]] = x[1]; AR[x[0]] = x[2]; }); A.strings(EN, AR);

  var D = null, timer = null;
  function ar() { return A.lang() === "ar"; }
  function loc() { return ar() ? "ar" : "en-GB"; }
  function range() { var x = A.ui.wkrange; return x === "week" || x === "month" ? x : "today"; }
  function month() { return A.ui.wkmonth || (function () { var d = new Date(); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); })(); }
  function shift(m, n) { var p = m.split("-"), d = new Date(+p[0], +p[1] - 1 + n, 1); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); }
  function monthLabel(m) { var p = m.split("-"); return new Date(+p[0], +p[1] - 1, 1).toLocaleDateString(loc(), { month: "long", year: "numeric" }); }
  function dur(sec) { sec = Math.max(0, Math.round(sec || 0)); var h = Math.floor(sec / 3600), m = Math.round((sec % 3600) / 60); if (m === 60) { h++; m = 0; } return h ? h + t("wk_h") + " " + m + t("wk_m") : m + t("wk_m"); }
  function ago(iso) {
    if (!iso) return t("wk_never"); var mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    if (mins < 1) return t("wk_last", { x: t("wk_just") }); if (mins < 60) return t("wk_last", { x: t("wk_min_ago", { n: mins }) });
    if (mins < 24 * 60) return t("wk_last", { x: t("wk_h_ago", { n: Math.floor(mins / 60) }) }); return t("wk_last", { x: A.dateTime(iso) });
  }
  function t12(hhmm) { if (!hhmm) return "-"; var p = String(hhmm).split(":"); return new Date(2000, 0, 1, +p[0], +p[1]).toLocaleTimeString(loc(), { hour: "numeric", minute: "2-digit", hour12: true }); }
  function dayLabel(date) { var p = date.split("-"); return new Date(+p[0], +p[1] - 1, +p[2], 12).toLocaleDateString(loc(), { weekday: "short", day: "numeric", month: "short" }); }
  function deptLabel(k) { return k ? t("wk_d_" + k) : ""; }
  function areas(map) { return Object.keys(map || {}).map(function (k) { return '<span class="cchip">' + esc(t("wk_a_" + k)) + " · " + map[k] + "</span>"; }).join(" "); }

  /* ---------------- list ---------------- */
  function card(k, n, cls) { return '<div class="at-c ' + (cls || "") + '"><b>' + n + "</b><small>" + t(k) + "</small></div>"; }
  function row(u) {
    var att = !u.linked ? t("wk_no_link") : u.clocked_in_now ? t("wk_clock_in", { x: t12(u.clock_in) }) : u.clock_in ? t("wk_clock_in", { x: t12(u.clock_in) }) : t("wk_not_clocked");
    return '<button class="orow-card wk-row" type="button" data-act="wkopen" data-id="' + u.id + '"><span class="wk-av ' + u.status + '">' + A.avatar({ id: u.id, name: u.name, avatar: u.avatar }, "avatar") + '<i class="wk-dot ' + u.status + '"></i></span>' +
      '<span class="grow"><b>' + esc(u.name) + '</b><span class="sub">' + esc(deptLabel(u.department)) + (u.department ? " · " : "") + esc(ago(u.last_seen_at)) + "</span>" +
      '<span class="sub">' + esc(att) + (u.idle_while_clocked_in ? ' · <b class="at-bad">' + esc(t("wk_idle_flag")) + "</b>" : "") + "</span>" + (Object.keys(u.areas).length ? '<span class="wk-areas">' + areas(u.areas) + "</span>" : "") + "</span>" +
      '<span class="wk-nums"><b>' + esc(dur(u.active_seconds)) + "</b><small>" + esc(t("wk_actions_n", { n: u.actions })) + "</small></span></button>";
  }
  function render(d) {
    D = d; var cur = range();
    var tabs = '<div class="filters" role="group">' + ["today", "week", "month"].map(function (k) { return '<button class="fbtn" type="button" data-act="wkrange" data-v="' + k + '" aria-pressed="' + (cur === k) + '">' + t("wk_" + k) + "</button>"; }).join("") + "</div>";
    var msel = cur === "month" ? '<div class="msel"><button class="mo-circle" type="button" data-act="wkmonth" data-d="-1" aria-label="-">' + ic("back", "flip") + "</button><b>" + esc(monthLabel(month())) + '</b><button class="mo-circle" type="button" data-act="wkmonth" data-d="1" aria-label="+">' + ic("back", "at-fwd") + "</button></div>" : "";
    var T = d.totals, none = d.staff.every(function (u) { return !u.last_seen_at; });
    var cards = '<div class="at-counts wk-cards">' + card("wk_active_now", T.active_now, "present") + card("wk_active_today", T.active_today) + card("wk_idle", T.idle_clocked_in, T.idle_clocked_in ? "absent" : "") + card("wk_actions", T.actions) + "</div>";
    var empty = !d.staff.length ? '<p class="note">' + t("wk_none_staff") + "</p>" : "";
    var sbtn = d.samples ? '<button class="btn btn-line btn-sm" type="button" data-act="wksamp" data-v="remove">' + t("wk_remove") + "</button>" : '<button class="btn ' + (none ? "btn-main" : "btn-line btn-sm") + '" type="button" data-act="wksamp" data-v="load">' + t("wk_load") + "</button>";
    var samples = none ? '<section class="card"><h3>' + t("wk_empty_t") + '</h3><p class="note">' + t("wk_empty_p") + '</p><div class="actions">' + sbtn + "</div></section>" : '<div class="actions" style="margin-top:1rem">' + sbtn + "</div>";
    var list = d.staff.slice().sort(function (a, b) { return b.active_seconds - a.active_seconds || b.actions - a.actions; });
    return '<div class="head"><h1 class="title">' + t("wk_title") + '</h1><p class="lead">' + t("wk_lead") + "</p></div>" + tabs + msel + cards + empty + list.map(row).join("") + samples;
  }
  A.act("wkrange", function (el) { A.ui.wkrange = el.getAttribute("data-v"); A.saveUI(); A.reload(true); });
  A.act("wkmonth", function (el) { A.ui.wkmonth = shift(month(), +el.getAttribute("data-d")); A.saveUI(); A.reload(true); });
  A.act("wksamp", function (el) {
    var load = el.getAttribute("data-v") === "load"; if (!load && !confirm(t("wk_sure"))) return; el.disabled = true;
    A.api.write(load ? "POST" : "DELETE", "work/samples", {}, { online: true }).then(function () { A.toast(t(load ? "wk_loaded" : "wk_removed")); A.reload(true); }, function (e) { el.disabled = false; A.toast(e.offline ? t("wk_conn") : A.msg(e)); });
  });

  /* ---------------- one person ---------------- */
  A.act("wkopen", function (el) {
    var id = +el.getAttribute("data-id"), u = D.staff.filter(function (x) { return x.id === id; })[0]; if (!u) return;
    A.sheet(u.name, '<p class="note">' + t("wk_conn") + "</p>");
    A.api.get("work/staff/" + id + "?month=" + (range() === "month" ? month() : ""), { cache: false }).then(function (r) { fill(u, r.data); }, function (e) { var s = document.querySelector("#sheetRoot .note"); if (s) s.textContent = e.offline ? t("wk_conn") : A.msg(e); });
  });
  function fill(u, d) {
    var sheet = document.querySelector("#sheetRoot .sheet"); if (!sheet) return;
    var T = d.totals, max = Math.max.apply(null, [8 * 3600].concat(d.days.map(function (x) { return x.active_seconds; })));
    var stats = '<div class="at-sum">' + [["wk_active_time", dur(T.active_seconds)], ["wk_days", T.days_active], ["wk_views", T.views], ["wk_did", T.actions]].map(function (c) { return "<div><b>" + esc(String(c[1])) + "</b><small>" + t(c[0]) + "</small></div>"; }).join("") + "</div>";
    var days = d.days.filter(function (x) { return x.requests > 0 || x.clock_in; }).reverse().map(function (x) {
      var pct = Math.min(100, Math.round(x.active_seconds / max * 100));
      return '<div class="wk-day"><div class="wk-dtop"><b>' + esc(dayLabel(x.date)) + "</b><span>" + (x.requests ? esc(dur(x.active_seconds)) + " · " + esc(t("wk_actions_n", { n: x.actions })) : esc(t("wk_no_activity_day"))) + '</span></div><div class="bar"><i style="width:' + pct + '%"></i></div>' +
        '<small class="sub">' + (x.first ? esc(t12(x.first)) + " – " + esc(t12(x.last)) : "") + (x.clock_in ? (x.first ? " · " : "") + esc(t("wk_clock_in", { x: t12(x.clock_in) })) + (x.clock_out ? " – " + esc(t12(x.clock_out)) : "") : "") + "</small></div>";
    }).join("");
    var recent = d.recent.length ? d.recent.map(function (x) { return '<div class="at-li"><span class="grow"><b>' + esc(ar() ? x.text.ar : x.text.en) + '</b><span class="sub">' + esc(A.dateTime(x.at)) + (x.subject ? " · " + esc(x.subject.label) : "") + "</span></span></div>"; }).join("") : '<p class="note">' + t("wk_none_recent") + "</p>";
    var title = sheet.querySelector(".sheet-head h3");
    sheet.querySelectorAll(":scope > :not(.sheet-head)").forEach(function (n) { n.remove(); });
    var body = document.createElement("div"); body.className = "wk-detail";
    body.innerHTML = '<p class="sub">' + esc(deptLabel(u.department)) + (u.department ? " · " : "") + esc(ago(u.last_seen_at)) + "</p>" + stats +
      (Object.keys(d.areas).length ? '<h4 class="optlbl">' + t("wk_by_area") + '</h4><p class="wk-areas">' + areas(d.areas) + "</p>" : "") +
      '<h4 class="optlbl">' + t("wk_daily") + " · " + esc(monthLabel(d.month)) + "</h4>" + (days || '<p class="note">' + t("wk_no_activity_day") + "</p>") + '<h4 class="optlbl">' + t("wk_recent") + "</h4>" + recent +
      '<div class="actions"><button class="btn btn-line" type="button" data-act="close">' + t("wk_close") + "</button></div>";
    sheet.appendChild(body); if (title) title.textContent = d.user.name;
  }

  function after() {
    if (timer) { clearInterval(timer); timer = null; }
    timer = setInterval(function () { if (!document.getElementById("wkTick") && !document.querySelector(".wk-row")) { clearInterval(timer); timer = null; return; } if (!document.hidden && !document.querySelector("#sheetRoot .sheet")) A.reload(true); }, 60000);
  }
  function load() { return A.api.get("work/summary?range=" + range() + "&month=" + month(), { cache: false }).then(function (r) { return { data: r.data }; }); }
  A.start({ page: "work", nav: "work", title: "wk_title", perm: "work.view", needsConn: true, shell: true, load: load, render: render, after: after });
})();
