/* Office attendance: clock in and out with a face photo, the office board, a month of records, people, payroll, leaves and holidays. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  var L = [
    ["at_title", "Attendance", "الحضور"], ["at_lead", "Clock in and out with your face. The date and time are taken from the server.", "سجّل حضورك وانصرافك بوجهك. التاريخ والوقت يؤخذان من الخادم."],
    ["tb_today", "Today", "اليوم"], ["tb_records", "Records", "السجلات"], ["tb_people", "People", "الموظفون"], ["tb_payroll", "Payroll", "الرواتب"], ["tb_setup", "Leaves and holidays", "الإجازات والعطل"],
    ["at_in", "Clock in", "تسجيل الحضور"], ["at_out", "Clock out", "تسجيل الانصراف"], ["at_done", "Done for today", "انتهى اليوم"],
    ["at_notin", "You have not clocked in yet.", "لم تسجّل حضورك بعد."], ["at_shift", "Shift {a} to {b}", "الدوام من {a} إلى {b}"], ["at_grace", "{n} minutes grace", "مهلة {n} دقيقة"],
    ["at_in_at", "Clocked in at {x}", "حضور الساعة {x}"], ["at_out_at", "Clocked out at {x}", "انصراف الساعة {x}"], ["at_late_n", "{n} min late", "تأخر {n} دقيقة"], ["at_early_n", "{n} min early", "انصراف مبكر {n} دقيقة"],
    ["at_ot_n", "{n} min overtime", "عمل إضافي {n} دقيقة"], ["at_ontime", "On time", "في الوقت"], ["at_notlinked", "Your account is not linked to an employee yet. Ask HR to link it on the People tab.", "حسابك غير مربوط بموظف بعد. اطلب من الموارد البشرية ربطه من تبويب الموظفين."],
    ["at_face_title", "Look at the camera", "انظر إلى الكاميرا"], ["at_face_hint", "Your face photo, the date and the time are saved with this clock.", "تُحفظ صورة وجهك والتاريخ والوقت مع هذا التسجيل."], ["at_shot", "Take photo and clock", "التقط الصورة وسجّل"],
    ["at_cam_fail", "The camera could not be opened. Allow the camera for this site, or use your phone's camera below.", "تعذّر فتح الكاميرا. اسمح بالكاميرا لهذا الموقع، أو استخدم كاميرا الهاتف أدناه."], ["at_cam_file", "Use the phone camera", "استخدم كاميرا الهاتف"],
    ["at_saving", "Saving…", "جارٍ الحفظ…"], ["at_clocked_in", "Clocked in at {x}", "تم تسجيل الحضور الساعة {x}"], ["at_clocked_out", "Clocked out at {x}", "تم تسجيل الانصراف الساعة {x}"], ["at_need_conn", "Clocking needs a connection, so the time cannot be changed on the phone.", "التسجيل يحتاج اتصالًا حتى لا يمكن تغيير الوقت من الهاتف."],
    ["at_office_today", "The office today", "المكتب اليوم"], ["c_present", "On time", "في الوقت"], ["c_late", "Late", "متأخر"], ["c_absent", "Absent", "غائب"], ["c_leave", "Leave", "إجازة"], ["c_pending", "Not yet", "لم يصل بعد"], ["c_off", "Off", "عطلة"],
    ["st_present", "Present", "حاضر"], ["st_late", "Late", "متأخر"], ["st_absent", "Absent", "غائب"], ["st_leave", "Paid leave", "إجازة مدفوعة"], ["st_leave_unpaid", "Unpaid leave", "إجازة غير مدفوعة"], ["st_excused", "Excused", "معذور"],
    ["st_holiday", "Holiday", "عطلة"], ["st_rest", "Rest day", "يوم راحة"], ["st_pending", "Not yet", "لم يصل بعد"], ["st_upcoming", "Upcoming", "قادم"], ["st_before", "Before hiring", "قبل التعيين"],
    ["at_no_people", "No employees yet. Add them on the People tab, or load sample data under Leaves and holidays.", "لا يوجد موظفون بعد. أضفهم من تبويب الموظفين، أو حمّل بيانات تجريبية من تبويب الإجازات والعطل."],
    ["at_employee", "Employee", "الموظف"], ["at_present_d", "Days present", "أيام الحضور"], ["at_late_d", "Late days", "أيام التأخر"], ["at_late_m", "Late minutes", "دقائق التأخر"], ["at_early_m", "Early minutes", "دقائق الانصراف المبكر"],
    ["at_absent_d", "Absent days", "أيام الغياب"], ["at_leave_d", "Leave days", "أيام الإجازة"], ["at_ot_m", "Overtime minutes", "دقائق العمل الإضافي"], ["at_wd", "Working days", "أيام العمل"],
    ["at_noout", "No clock out", "بلا انصراف"], ["at_pay", "Pay for the month", "راتب الشهر"], ["at_gross", "Salary for the days", "راتب الأيام"], ["at_ded", "Deductions", "الخصومات"], ["at_net", "Net pay", "صافي الراتب"],
    ["d_late", "Late", "التأخر"], ["d_early", "Leaving early", "الانصراف المبكر"], ["d_absent", "Absent", "الغياب"], ["d_unpaid", "Unpaid leave", "الإجازة غير المدفوعة"], ["at_rate", "{d} a day · {m} a minute", "{d} في اليوم · {m} في الدقيقة"],
    ["at_day", "Day", "اليوم"], ["at_photos", "Face photos", "صور الوجه"], ["at_ref", "Reference", "المرجع"], ["at_no_photo", "No photo", "لا صورة"], ["at_method_manual", "Entered by HR", "أدخله الموارد البشرية"],
    ["at_fix", "Correct this day", "تصحيح هذا اليوم"], ["at_status", "What happened", "ما حدث"], ["at_s_present", "Came to work", "حضر للعمل"], ["at_s_absent", "Absent", "غائب"], ["at_s_excused", "Excused (no deduction)", "معذور (دون خصم)"], ["at_s_clear", "Clear (back to automatic)", "مسح (العودة للتلقائي)"],
    ["at_ci", "Clock in time", "وقت الحضور"], ["at_co", "Clock out time", "وقت الانصراف"], ["at_note", "Why (required)", "السبب (مطلوب)"], ["at_saved", "Saved", "تم الحفظ"], ["at_save", "Save", "حفظ"], ["at_cancel", "Cancel", "إلغاء"],
    ["pe_add", "Add employee", "إضافة موظف"], ["pe_edit", "Employee", "الموظف"], ["pe_name_en", "Name (English)", "الاسم (بالإنجليزية)"], ["pe_name_ar", "Name (Arabic)", "الاسم (بالعربية)"], ["pe_no", "Employee number", "رقم الموظف"], ["pe_pos", "Position", "المسمى الوظيفي"],
    ["pe_dept", "Department", "القسم"], ["pe_phone", "Phone", "الهاتف"], ["pe_hired", "Hired on", "تاريخ التعيين"], ["pe_salary", "Monthly salary", "الراتب الشهري"], ["pe_start", "Shift starts", "بداية الدوام"], ["pe_end", "Shift ends", "نهاية الدوام"],
    ["pe_break", "Unpaid break (minutes)", "الاستراحة غير المدفوعة (دقائق)"], ["pe_grace", "Grace (minutes late with no deduction)", "المهلة (دقائق تأخر دون خصم)"], ["pe_days", "Working days", "أيام العمل"], ["pe_link", "Sign-in account", "حساب الدخول"],
    ["pe_nolink", "Not linked", "غير مربوط"], ["pe_status", "Status", "الحالة"], ["pe_active", "Active", "على رأس العمل"], ["pe_ended", "Ended", "منتهية خدمته"], ["pe_notes", "Notes", "ملاحظات"], ["pe_face", "Reference face photo", "صورة الوجه المرجعية"],
    ["pe_face_set", "Save a new reference photo", "حفظ صورة مرجعية جديدة"], ["pe_face_ok", "Reference photo saved", "تم حفظ الصورة المرجعية"], ["pe_linked", "Linked", "مربوط"], ["pe_sample", "Sample", "تجريبي"], ["pe_taken", "already linked", "مربوط بالفعل"],
    ["wd1", "Mon", "إثن"], ["wd2", "Tue", "ثلا"], ["wd3", "Wed", "أرب"], ["wd4", "Thu", "خمي"], ["wd5", "Fri", "جمع"], ["wd6", "Sat", "سبت"], ["wd7", "Sun", "أحد"],
    ["pr_title", "Payroll", "كشف الرواتب"], ["pr_total", "Total net pay", "إجمالي صافي الرواتب"], ["pr_ded", "Total deductions", "إجمالي الخصومات"], ["pr_csv", "Download CSV", "تنزيل CSV"], ["pr_gross", "Gross", "الإجمالي"],
    ["su_samples", "Sample data", "بيانات تجريبية"], ["su_samples_t", "Sample employees with five weeks of attendance (on time, late, absent, leave, early leaving) to try the screens and the pay rules. You get one sample employee linked to your own account so you can clock in. Remove them any time.", "موظفون تجريبيون مع خمسة أسابيع من الحضور (في الوقت، متأخر، غائب، إجازة، انصراف مبكر) لتجربة الشاشات وقواعد الراتب. تحصل على موظف تجريبي مربوط بحسابك لتجرّب التسجيل. يمكن حذفها في أي وقت."],
    ["su_load", "Load sample data", "تحميل بيانات تجريبية"], ["at_empty_t", "No employees yet", "لا يوجد موظفون بعد"], ["at_empty_p", "Load sample employees with five weeks of attendance to see how it works, or add your first real employee.", "حمّل موظفين تجريبيين مع خمسة أسابيع من الحضور لترى طريقة العمل، أو أضف أول موظف حقيقي."], ["su_remove", "Remove sample data", "حذف البيانات التجريبية"], ["su_loaded", "Sample data loaded", "تم تحميل البيانات التجريبية"], ["su_removed", "Sample data removed", "تم حذف البيانات التجريبية"],
    ["su_leaves", "Leaves", "الإجازات"], ["su_holidays", "Holidays", "العطل"], ["su_add_leave", "Add leave", "إضافة إجازة"], ["su_add_hol", "Add holiday", "إضافة عطلة"], ["su_none_l", "No leaves in this period.", "لا إجازات في هذه الفترة."], ["su_none_h", "No holidays this year.", "لا عطل هذا العام."],
    ["lv_from", "From", "من"], ["lv_to", "To", "إلى"], ["lv_kind", "Kind", "النوع"], ["lv_reason", "Reason", "السبب"], ["lv_annual", "Annual (paid)", "سنوية (مدفوعة)"], ["lv_sick", "Sick (paid)", "مرضية (مدفوعة)"], ["lv_emergency", "Emergency (paid)", "طارئة (مدفوعة)"],
    ["lv_unpaid", "Unpaid", "بدون راتب"], ["lv_other", "Other (paid)", "أخرى (مدفوعة)"], ["lv_paid", "paid", "مدفوعة"], ["lv_unp", "unpaid", "غير مدفوعة"], ["hol_date", "Date", "التاريخ"], ["hol_name_en", "Name (English)", "الاسم (بالإنجليزية)"], ["hol_name_ar", "Name (Arabic)", "الاسم (بالعربية)"],
    ["at_remove", "Remove", "حذف"], ["at_removed", "Removed", "تم الحذف"], ["at_conn", "This needs a connection.", "هذا يحتاج اتصالًا."], ["at_sure", "Remove all sample employees and their attendance?", "حذف كل الموظفين التجريبيين وحضورهم؟"]
  ];
  var EN = {}, AR = {}; L.forEach(function (x) { EN[x[0]] = x[1]; AR[x[0]] = x[2]; }); A.strings(EN, AR);

  var D = null, tz = "Asia/Manila", clockTimer = null;
  var TABS = ["today", "records", "people", "payroll", "setup"];
  function tabOk(k) { return k === "today" || k === "records" ? (A.can("attendance.clock") || A.can("attendance.manage")) : k === "payroll" ? A.can("attendance.salary") : A.can("attendance.manage"); }
  function tab() { var x = A.ui.atttab; if (TABS.indexOf(x) < 0 || !tabOk(x)) x = "today"; return x; }
  function ar() { return A.lang() === "ar"; }
  function loc() { return ar() ? "ar" : "en-GB"; }
  function nm(x) { return ar() && x.name_ar ? x.name_ar : x.name_en; }
  function month() { return A.ui.attmonth || (function () { var d = new Date(); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); })(); }
  function shift(m, n) { var p = m.split("-"), d = new Date(+p[0], +p[1] - 1 + n, 1); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2); }
  function monthLabel(m) { var p = m.split("-"); return new Date(+p[0], +p[1] - 1, 1).toLocaleDateString(loc(), { month: "long", year: "numeric" }); }
  function t12(hhmm) { if (!hhmm) return "-"; var p = String(hhmm).split(":"); return new Date(2000, 0, 1, +p[0], +p[1]).toLocaleTimeString(loc(), { hour: "numeric", minute: "2-digit", hour12: true }); }
  function dayLabel(date) { var p = date.split("-"); return new Date(+p[0], +p[1] - 1, +p[2], 12).toLocaleDateString(loc(), { weekday: "short", day: "numeric", month: "short" }); }
  function money(n, cur) { var u = A.user() || {}, pr = u.preferences || {}; return pr.show_amounts === false ? "••••" : (cur || "PHP") + " " + Number(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function chip(s) { return '<span class="cchip at-' + s + '">' + esc(t("st_" + s)) + "</span>"; }
  function msg(e) { return e && e.offline ? t("at_conn") : A.msg(e); }

  /* ---------------- camera ---------------- */
  var stream = null, camWatch = null;
  function stopCam() { if (stream) { stream.getTracks().forEach(function (tr) { tr.stop(); }); stream = null; } if (camWatch) { clearInterval(camWatch); camWatch = null; } }
  function openCamera(title, hint, done) {
    A.sheet(title, '<div class="cam"><video id="camVideo" autoplay playsinline muted></video><canvas id="camCanvas" hidden></canvas></div><p class="note" id="camMsg">' + esc(hint || "") + '</p><input id="camFile" type="file" accept="image/*" capture="user" hidden>' +
      '<div class="actions"><button class="btn btn-main" type="button" data-act="camshot" id="camBtn" disabled>' + ic("check") + "<span>" + t("at_shot") + '</span></button><button class="btn btn-line" type="button" data-act="close">' + t("at_cancel") + "</button></div>");
    CAMDONE = done;
    var v = document.getElementById("camVideo"), m = document.getElementById("camMsg");
    function fallback() { m.textContent = t("at_cam_fail"); var b = document.getElementById("camBtn"); b.disabled = false; b.querySelector("span").textContent = t("at_cam_file"); b.setAttribute("data-file", "1"); v.hidden = true; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { fallback(); return; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 640 } }, audio: false }).then(function (s) {
      stream = s; v.srcObject = s; var p = v.play(); if (p && p.catch) p.catch(function () {});
      document.getElementById("camBtn").disabled = false;
      camWatch = setInterval(function () { if (!document.getElementById("camVideo")) stopCam(); }, 500);   // the camera light goes off as soon as the sheet is closed
    }, fallback);
  }
  var CAMDONE = null;
  function busy(on) { var b = document.getElementById("camBtn"); if (b) b.disabled = on; var m = document.getElementById("camMsg"); if (m && on) m.textContent = t("at_saving"); }
  function send(blob) {
    var cb = CAMDONE; stopCam(); busy(true);
    cb(blob).then(function () { A.closeSheet(); }, function (e) { var b = document.getElementById("camBtn"); if (b) b.disabled = false; var m = document.getElementById("camMsg"); if (m) m.textContent = msg(e); stopCamLater(); });
  }
  function stopCamLater() { /* the preview is stopped after a try; the person can close and open again */ }
  A.act("camshot", function (el) {
    if (el.getAttribute("data-file")) {
      var f = document.getElementById("camFile"); f.onchange = function () { if (f.files[0]) send(f.files[0]); }; f.click(); return;
    }
    var v = document.getElementById("camVideo"), c = document.getElementById("camCanvas"), w = v.videoWidth || 640, h = v.videoHeight || 480, r = Math.min(1, 640 / Math.max(w, h));
    c.width = Math.round(w * r); c.height = Math.round(h * r); c.getContext("2d").drawImage(v, 0, 0, c.width, c.height);
    c.toBlob(function (b) { if (b) send(b); }, "image/jpeg", 0.85);
  });

  /* ---------------- today ---------------- */
  function nowText() { var off = D.offset || 0; return new Date(Date.now() + off).toLocaleTimeString(loc(), { hour: "numeric", minute: "2-digit", second: "2-digit", hour12: true, timeZone: tz }); }
  function dateText() { var off = D.offset || 0; return new Date(Date.now() + off).toLocaleDateString(loc(), { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: tz }); }
  function dayLine(d) {
    var out = [];
    if (d.clock_in) { out.push(t("at_in_at", { x: t12(d.clock_in) }) + (d.late ? ' · <b class="at-bad">' + t("at_late_n", { n: d.late }) + "</b>" : ' · <span class="at-good">' + t("at_ontime") + "</span>")); } else out.push(t("at_notin"));
    if (d.clock_out) out.push(t("at_out_at", { x: t12(d.clock_out) }) + (d.early ? ' · <b class="at-bad">' + t("at_early_n", { n: d.early }) + "</b>" : "") + (d.overtime ? " · " + t("at_ot_n", { n: d.overtime }) : ""));
    return out.map(function (x) { return "<div>" + x + "</div>"; }).join("");
  }
  function clockCard() {
    var me = D.me;
    if (!me || !me.employee) return '<section class="card"><p class="note">' + t("at_notlinked") + "</p></section>";
    var e = me.employee, d = me.today, can = !d.clock_in ? "in" : (!d.clock_out ? "out" : "");
    var hol = d.holiday ? '<p class="note">' + esc(d.holiday) + "</p>" : "";
    return '<section class="card at-clock"><div class="at-time" id="atTime">' + esc(nowText()) + '</div><div class="at-date" id="atDate">' + esc(dateText()) + "</div>" +
      '<p class="note">' + t("at_shift", { a: t12(e.shift_start), b: t12(e.shift_end) }) + (e.grace_minutes ? " · " + t("at_grace", { n: e.grace_minutes }) : "") + "</p>" + hol +
      '<div class="at-line">' + dayLine(d) + "</div>" +
      (can ? '<button class="btn btn-main at-big" type="button" data-act="atclock" data-v="' + can + '">' + ic("clock") + "<span>" + t(can === "in" ? "at_in" : "at_out") + "</span></button>" : '<p class="note"><b>' + t("at_done") + "</b></p>") +
      '<p class="note at-fine">' + t("at_need_conn") + "</p></section>";
  }
  A.act("atclock", function (el) {
    var action = el.getAttribute("data-v");
    openCamera(t("at_face_title"), t("at_face_hint"), function (blob) {
      var fd = new FormData(); fd.append("action", action); fd.append("photo", blob, "face.jpg");
      return A.api.upload("attendance/clock", fd).then(function (r) {
        var d = (r && r.today) || {}; A.toast(t(action === "in" ? "at_clocked_in" : "at_clocked_out", { x: t12(action === "in" ? d.clock_in : d.clock_out) })); A.reload(true);
      });
    });
  });
  function board() {
    var b = D.board; if (!b) return "";
    if (!b.rows.length) return '<section class="card"><h3>' + t("at_empty_t") + '</h3><p class="note">' + t("at_empty_p") + '</p><div class="actions"><button class="btn btn-main" type="button" data-act="atsamp" data-v="load">' + t("su_load") + '</button><button class="btn btn-line" type="button" data-act="atemp" data-id="0">' + ic("plus") + t("pe_add") + "</button></div></section>";
    var c = b.counts, keys = ["present", "late", "absent", "leave", "pending"];
    var rows = b.rows.length ? b.rows.map(function (r) {
      var d = r.day, sub = d.clock_in ? t12(d.clock_in) + (d.clock_out ? " – " + t12(d.clock_out) : "") + (d.late ? " · " + t("at_late_n", { n: d.late }) : "") : esc(r.position || "");
      return '<button class="orow-card at-row" type="button" data-act="atopen" data-id="' + r.id + '"><span class="avatar">' + esc(String(nm(r) || "?").charAt(0)) + '</span><span class="grow"><b>' + esc(nm(r)) + (r.is_sample ? ' <span class="cchip">' + t("pe_sample") + "</span>" : "") + '</b><span class="sub">' + sub + "</span></span>" + chip(d.status) + "</button>";
    }).join("") : '<p class="note">' + t("at_no_people") + "</p>";
    return '<h4 class="optlbl">' + t("at_office_today") + '</h4><div class="at-counts">' + keys.map(function (k) { return '<div class="at-c ' + k + '"><b>' + c[k] + "</b><small>" + t("c_" + k) + "</small></div>"; }).join("") + "</div>" + rows;
  }
  A.act("atopen", function (el) { A.ui.attemp = +el.getAttribute("data-id"); A.ui.atttab = "records"; A.saveUI(); A.reload(true); });

  /* ---------------- records ---------------- */
  function summary(m) {
    var T = m.totals, cells = [["at_present_d", T.present], ["at_late_d", T.late_days], ["at_late_m", T.late_minutes], ["at_early_m", T.early_minutes], ["at_absent_d", T.absent], ["at_leave_d", T.leave_paid + T.leave_unpaid + T.excused], ["at_ot_m", T.overtime_minutes], ["at_wd", m.working_days]];
    return '<div class="at-sum">' + cells.map(function (c) { return "<div><b>" + c[1] + "</b><small>" + t(c[0]) + "</small></div>"; }).join("") + "</div>";
  }
  function payCard(m) {
    var p = m.pay; if (!p) return "";
    var line = function (k, v) { return '<div class="at-pl"><span>' + t(k) + "</span><b>" + money(v, p.currency) + "</b></div>"; };
    return '<section class="card"><h3>' + t("at_pay") + '</h3><p class="note">' + t("at_rate", { d: money(p.daily_rate, p.currency), m: money(p.minute_rate, p.currency) }) + "</p>" +
      line("at_gross", p.gross) + line("d_late", -p.deductions.late) + line("d_early", -p.deductions.early) + line("d_absent", -p.deductions.absent) + line("d_unpaid", -p.deductions.unpaid_leave) + '<div class="at-pl at-net"><span>' + t("at_net") + "</span><b>" + money(p.net, p.currency) + "</b></div></section>";
  }
  function dayRow(d, manage) {
    var bits = [];
    if (d.clock_in) bits.push(t12(d.clock_in) + (d.clock_out ? " – " + t12(d.clock_out) : ""));
    if (d.late) bits.push('<span class="at-bad">' + t("at_late_n", { n: d.late }) + "</span>");
    if (d.early) bits.push('<span class="at-bad">' + t("at_early_n", { n: d.early }) + "</span>");
    if (d.overtime) bits.push(t("at_ot_n", { n: d.overtime }));
    if (d.no_clock_out) bits.push('<span class="at-bad">' + t("at_noout") + "</span>");
    if (d.holiday) bits.push(esc(d.holiday));
    if (d.leave && d.leave.reason) bits.push(esc(d.leave.reason));
    if (d.note) bits.push(esc(d.note));
    var dim = d.status === "rest" || d.status === "before" || d.status === "upcoming" || d.status === "holiday";
    var tag = manage && d.status !== "before" ? "button" : "div";
    return "<" + tag + ' class="orow-card at-row' + (dim ? " dim" : "") + '"' + (tag === "button" ? ' type="button" data-act="atday" data-date="' + d.date + '"' : "") + '><span class="grow"><b>' + esc(dayLabel(d.date)) + '</b><span class="sub">' + (bits.join(" · ") || "&nbsp;") + "</span></span>" + chip(d.status) + "</" + tag + ">";
  }
  function records() {
    var R = D.rec, manage = A.can("attendance.manage");
    if (!R) return '<p class="note">' + t("at_notlinked") + "</p>";
    var picker = manage ? '<div class="at-pick"><label for="atEmp" class="sr">' + t("at_employee") + '</label><select id="atEmp">' + D.emps.employees.filter(function (x) { return x.status === "active"; }).map(function (x) { return '<option value="' + x.id + '"' + (x.id === R.employee.id ? " selected" : "") + ">" + esc(nm(x)) + "</option>"; }).join("") + "</select></div>" : "";
    var head = '<div class="msel"><button class="mo-circle" type="button" data-act="atmonth" data-d="-1" aria-label="-">' + ic("back", "flip") + "</button><b>" + esc(monthLabel(R.month)) + '</b><button class="mo-circle" type="button" data-act="atmonth" data-d="1" aria-label="+">' + ic("back", "at-fwd") + "</button></div>";
    return picker + head + summary(R) + payCard(R) + R.days.map(function (d) { return dayRow(d, manage); }).join("");
  }
  A.act("atmonth", function (el) { A.ui.attmonth = shift(month(), +el.getAttribute("data-d")); A.saveUI(); A.reload(true); });
  document.addEventListener("change", function (e) { if (e.target && e.target.id === "atEmp") { A.ui.attemp = +e.target.value; A.saveUI(); A.reload(true); } });

  /* the day sheet: photos, the reference photo, and HR's correction form */
  var DAY = null;
  A.act("atday", function (el) {
    var date = el.getAttribute("data-date"), d = D.rec.days.filter(function (x) { return x.date === date; })[0]; if (!d) return; DAY = d;
    var emp = D.rec.employee, shot = function (id, label, has) { return '<figure class="at-fig"><div class="at-img" id="' + id + '">' + (has ? "" : "<span>" + t("at_no_photo") + "</span>") + "</div><figcaption>" + label + "</figcaption></figure>"; };
    var fixed = d.in_method === "manual" || d.out_method === "manual";
    var form = '<form id="dayForm" novalidate><h4 class="optlbl">' + t("at_fix") + '</h4><div><label for="dSt">' + t("at_status") + '</label><select id="dSt"><option value="present">' + t("at_s_present") + '</option><option value="absent">' + t("at_s_absent") + '</option><option value="excused">' + t("at_s_excused") + '</option><option value="clear">' + t("at_s_clear") + "</option></select></div>" +
      '<div class="pair"><div><label for="dIn">' + t("at_ci") + '</label><input id="dIn" type="time" value="' + esc(d.clock_in || "") + '"></div><div><label for="dOut">' + t("at_co") + '</label><input id="dOut" type="time" value="' + esc(d.clock_out || "") + '"></div></div>' +
      '<div><label for="dNote">' + t("at_note") + '</label><input id="dNote" type="text" maxlength="300"><div class="err" id="dNoteErr"></div></div><div class="err" id="formErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("at_save") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("at_cancel") + "</button></div></form>";
    A.sheet(nm(emp) + " · " + dayLabel(date), '<p class="sub">' + chip(d.status) + (fixed ? " · " + t("at_method_manual") : "") + '</p><h4 class="optlbl">' + t("at_photos") + '</h4><div class="at-figs">' + shot("dFin", t("at_in"), d.in_photo) + shot("dFout", t("at_out"), d.out_photo) + shot("dFref", t("at_ref"), emp.has_face) + "</div>" + form);
    var st = document.getElementById("dSt"); st.value = d.status === "absent" ? "absent" : d.status === "excused" ? "excused" : d.clock_in ? "present" : "present";
    function show(id, path) { A.api.blobUrl(path).then(function (u) { var b = document.getElementById(id); if (b) b.innerHTML = '<img alt="" src="' + u + '">'; }, function () {}); }
    if (d.in_photo) show("dFin", "attendance/records/" + d.record_id + "/photo/in");
    if (d.out_photo) show("dFout", "attendance/records/" + d.record_id + "/photo/out");
    if (emp.has_face) show("dFref", "attendance/employees/" + emp.id + "/face");
  });
  A.form("dayForm", function () {
    var st = document.getElementById("dSt").value, note = document.getElementById("dNote").value.trim(), er = document.getElementById("formErr"); er.textContent = "";
    if (note.length < 3) { document.getElementById("dNoteErr").textContent = t("at_note"); return; }
    var body = { employee_id: D.rec.employee.id, date: DAY.date, status: st, note: note, clock_in: document.getElementById("dIn").value || null, clock_out: document.getElementById("dOut").value || null };
    A.api.write("PUT", "attendance/records", body, { online: true }).then(function () { A.closeSheet(); A.toast(t("at_saved")); A.reload(true); }, function (e) { er.textContent = msg(e); });
  });

  /* ---------------- people ---------------- */
  function people() {
    var list = D.emps.employees;
    if (!list.length) return '<p class="note">' + t("at_no_people") + "</p>" + addBtn();
    return list.map(function (x) {
      var sub = esc(x.position || "") + (x.employee_no ? " · " + esc(x.employee_no) : "") + (x.monthly_salary !== undefined ? " · " + money(x.monthly_salary, x.currency) : "") + (x.linked ? " · " + t("pe_linked") : "") + (x.status !== "active" ? " · " + t("pe_ended") : "");
      return '<button class="orow-card at-row" type="button" data-act="atemp" data-id="' + x.id + '"><span class="avatar">' + esc(String(nm(x) || "?").charAt(0)) + '</span><span class="grow"><b>' + esc(nm(x)) + (x.is_sample ? ' <span class="cchip">' + t("pe_sample") + "</span>" : "") + '</b><span class="sub">' + sub + "</span></span>" + ic("chev", "ios-chev") + "</button>";
    }).join("") + addBtn();
  }
  function addBtn() { return '<button class="btn btn-main fab" type="button" data-act="atemp" data-id="0">' + ic("plus") + t("pe_add") + "</button>"; }
  var EMP = null;
  function f(id, label, val, type, attrs) { return '<div><label for="' + id + '">' + label + '</label><input id="' + id + '" type="' + (type || "text") + '" value="' + esc(val === null || val === undefined ? "" : val) + '" ' + (attrs || "") + '><div class="err" id="' + id + 'Err"></div></div>'; }
  A.act("atemp", function (el) {
    var id = +el.getAttribute("data-id"), x = id ? D.emps.employees.filter(function (e) { return e.id === id; })[0] : null, df = D.emps.defaults || {}; EMP = x;
    var days = x ? x.work_days : (df.work_days || "1,2,3,4,5").split(",").map(Number);
    var users = '<option value="">' + t("pe_nolink") + "</option>" + D.emps.users.map(function (u) { var mine = x && x.user_id === u.id; return '<option value="' + u.id + '"' + (mine ? " selected" : "") + (u.taken && !mine ? " disabled" : "") + ">" + esc(u.name) + (u.taken && !mine ? " (" + t("pe_taken") + ")" : "") + "</option>"; }).join("");
    var body = '<form id="empForm" novalidate>' + f("eEn", t("pe_name_en"), x && x.name_en, "text", 'maxlength="150"') + f("eAr", t("pe_name_ar"), x && x.name_ar, "text", 'maxlength="150" dir="rtl"') +
      '<div class="pair">' + f("eNo", t("pe_no"), x && x.employee_no, "text", 'maxlength="30"') + f("ePos", t("pe_pos"), x && x.position, "text", 'maxlength="100"') + "</div>" +
      '<div class="pair">' + f("eDept", t("pe_dept"), x ? x.department : "Office", "text", 'maxlength="100"') + f("ePhone", t("pe_phone"), x && x.phone, "tel", 'dir="ltr"') + "</div>" +
      '<div class="pair">' + f("eHired", t("pe_hired"), x && x.hired_on, "date") + (A.can("attendance.salary") ? f("eSal", t("pe_salary") + " (" + ((x && x.currency) || D.emps.currency) + ")", x ? x.monthly_salary : "", "number", 'min="0" step="0.01" inputmode="decimal"') : "") + "</div>" +
      '<div class="pair">' + f("eStart", t("pe_start"), x ? x.shift_start : df.shift_start || "08:00", "time") + f("eEnd", t("pe_end"), x ? x.shift_end : df.shift_end || "17:00", "time") + "</div>" +
      '<div class="pair">' + f("eBrk", t("pe_break"), x ? x.break_minutes : df.break_minutes, "number", 'min="0" max="240"') + f("eGrace", t("pe_grace"), x ? x.grace_minutes : df.grace_minutes, "number", 'min="0" max="120"') + "</div>" +
      '<div><label>' + t("pe_days") + '</label><div class="at-days">' + [1, 2, 3, 4, 5, 6, 7].map(function (n) { return '<label class="at-d"><input type="checkbox" class="wd" value="' + n + '"' + (days.indexOf(n) > -1 ? " checked" : "") + "><span>" + t("wd" + n) + "</span></label>"; }).join("") + "</div></div>" +
      '<div><label for="eUser">' + t("pe_link") + '</label><select id="eUser">' + users + "</select></div>" +
      (x ? '<div><label for="eSt">' + t("pe_status") + '</label><select id="eSt"><option value="active"' + (x.status === "active" ? " selected" : "") + ">" + t("pe_active") + '</option><option value="ended"' + (x.status === "ended" ? " selected" : "") + ">" + t("pe_ended") + "</option></select></div>" : "") +
      '<div><label for="eNotes">' + t("pe_notes") + '</label><textarea id="eNotes" rows="2" maxlength="1000">' + esc((x && x.notes) || "") + "</textarea></div>" +
      (x ? '<h4 class="optlbl">' + t("pe_face") + '</h4><div class="at-ref"><div class="at-img" id="eFace">' + (x.has_face ? "" : "<span>" + t("at_no_photo") + "</span>") + '</div><button class="btn btn-line btn-sm" type="button" data-act="atface">' + ic("check") + t("pe_face_set") + "</button></div>" : "") +
      '<div class="err" id="formErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("at_save") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("at_cancel") + "</button></div></form>";
    A.sheet(x ? nm(x) : t("pe_add"), body);
    if (x && x.has_face) A.api.blobUrl("attendance/employees/" + x.id + "/face").then(function (u) { var b = document.getElementById("eFace"); if (b) b.innerHTML = '<img alt="" src="' + u + '">'; }, function () {});
  });
  A.act("atface", function () {
    var x = EMP; if (!x) return;
    openCamera(t("pe_face"), t("at_face_hint"), function (blob) {
      var fd = new FormData(); fd.append("photo", blob, "face.jpg");
      return A.api.upload("attendance/employees/" + x.id + "/face", fd).then(function () { A.toast(t("pe_face_ok")); A.reload(true); });
    });
  });
  A.form("empForm", function () {
    var v = function (id) { return document.getElementById(id) ? document.getElementById(id).value.trim() : null; }, er = document.getElementById("formErr"); er.textContent = "";
    var body = { name_en: v("eEn"), name_ar: v("eAr"), employee_no: v("eNo"), position: v("ePos"), department: v("eDept"), phone: v("ePhone"), hired_on: v("eHired") || null, shift_start: v("eStart"), shift_end: v("eEnd"), break_minutes: +v("eBrk") || 0, grace_minutes: +v("eGrace") || 0,
      work_days: Array.prototype.slice.call(document.querySelectorAll(".wd:checked")).map(function (c) { return +c.value; }), user_id: v("eUser") ? +v("eUser") : null, notes: v("eNotes") };
    if (document.getElementById("eSal")) body.monthly_salary = +v("eSal") || 0;
    if (document.getElementById("eSt")) body.status = v("eSt");
    if (!body.name_en) { document.getElementById("eEnErr").textContent = t("pe_name_en"); return; }
    var call = EMP ? A.api.write("PATCH", "attendance/employees/" + EMP.id, body, { online: true }) : A.api.write("POST", "attendance/employees", body, { online: true });
    call.then(function () { A.closeSheet(); A.toast(t("at_saved")); A.reload(true); }, function (e) { A.showErrors(e.errors, "e"); er.textContent = msg(e); });
  });

  /* ---------------- payroll ---------------- */
  function payroll() {
    var P = D.pay, head = '<div class="msel"><button class="mo-circle" type="button" data-act="atmonth" data-d="-1" aria-label="-">' + ic("back", "flip") + "</button><b>" + esc(monthLabel(P.month)) + '</b><button class="mo-circle" type="button" data-act="atmonth" data-d="1" aria-label="+">' + ic("back", "at-fwd") + "</button></div>";
    var tot = '<div class="edu-tot"><div><small>' + t("pr_total") + "</small><b>" + money(P.sum.net, P.currency) + "</b></div><div><small>" + t("pr_ded") + "</small><b>" + money(P.sum.deductions, P.currency) + "</b></div></div>";
    var rows = P.rows.map(function (r) {
      var T = r.totals, d = r.pay.deductions;
      return '<section class="card at-prow"><div class="at-ptop"><b>' + esc(nm(r.employee)) + "</b><b>" + money(r.pay.net, P.currency) + '</b></div><p class="sub">' + esc(r.employee.position || "") + '</p><div class="at-pgrid">' +
        "<span>" + t("d_late") + ": " + T.late_minutes + " min · " + money(d.late, P.currency) + "</span><span>" + t("d_early") + ": " + T.early_minutes + " min · " + money(d.early, P.currency) + "</span>" +
        "<span>" + t("d_absent") + ": " + T.absent + " · " + money(d.absent, P.currency) + "</span><span>" + t("d_unpaid") + ": " + T.leave_unpaid + " · " + money(d.unpaid_leave, P.currency) + "</span></div></section>";
    }).join("");
    return head + tot + (rows || '<p class="note">' + t("at_no_people") + "</p>") + (P.rows.length ? '<div class="actions"><button class="btn btn-line" type="button" data-act="atcsv">' + ic("download") + t("pr_csv") + "</button></div>" : "");
  }
  A.act("atcsv", function () {
    var P = D.pay, q = function (v) { v = String(v === null || v === undefined ? "" : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    var rows = [["Name", "Position", "Working days", "Days present", "Late minutes", "Early minutes", "Absent days", "Unpaid leave days", "Gross", "Late deduction", "Early deduction", "Absent deduction", "Unpaid leave deduction", "Net"]];
    P.rows.forEach(function (r) { var T = r.totals, d = r.pay.deductions; rows.push([r.employee.name_en, r.employee.position, r.working_days, T.present, T.late_minutes, T.early_minutes, T.absent, T.leave_unpaid, r.pay.gross, d.late, d.early, d.absent, d.unpaid_leave, r.pay.net]); });
    var csv = "﻿" + rows.map(function (r) { return r.map(q).join(","); }).join("\r\n"), url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" })), a = document.createElement("a");
    a.href = url; a.download = "payroll-" + P.month + ".csv"; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
  });

  /* ---------------- leaves, holidays, samples ---------------- */
  function empName(id) { var x = D.emps.employees.filter(function (e) { return e.id === id; })[0]; return x ? nm(x) : "-"; }
  function setup() {
    var S = D.setup, samples = D.emps.employees.some(function (x) { return x.is_sample; });
    var smp = '<section class="card"><h3>' + t("su_samples") + '</h3><p class="note">' + t("su_samples_t") + '</p><div class="actions">' + (samples ? '<button class="btn btn-line" type="button" data-act="atsamp" data-v="remove">' + t("su_remove") + "</button>" : '<button class="btn btn-main" type="button" data-act="atsamp" data-v="load">' + t("su_load") + "</button>") + "</div></section>";
    var lv = '<section class="card"><h3>' + t("su_leaves") + '</h3>' + (S.leaves.length ? S.leaves.map(function (l) {
      return '<div class="at-li"><span class="grow"><b>' + esc(empName(l.employee_id)) + '</b><span class="sub">' + esc(A.dateOnly(l.start_date + "T12:00:00")) + (l.end_date !== l.start_date ? " – " + esc(A.dateOnly(l.end_date + "T12:00:00")) : "") + " · " + t("lv_" + l.kind) + " · " + t(l.paid ? "lv_paid" : "lv_unp") + (l.reason ? " · " + esc(l.reason) : "") + '</span></span><button class="btn btn-line btn-sm" type="button" data-act="atdel" data-k="leaves" data-id="' + l.id + '">' + t("at_remove") + "</button></div>";
    }).join("") : '<p class="note">' + t("su_none_l") + "</p>") + '<div class="actions"><button class="btn btn-main" type="button" data-act="atleave">' + ic("plus") + t("su_add_leave") + "</button></div></section>";
    var hd = '<section class="card"><h3>' + t("su_holidays") + '</h3>' + (S.holidays.length ? S.holidays.map(function (h) {
      return '<div class="at-li"><span class="grow"><b>' + esc(ar() && h.name_ar ? h.name_ar : h.name_en) + '</b><span class="sub">' + esc(A.dateOnly(h.date + "T12:00:00")) + '</span></span><button class="btn btn-line btn-sm" type="button" data-act="atdel" data-k="holidays" data-id="' + h.id + '">' + t("at_remove") + "</button></div>";
    }).join("") : '<p class="note">' + t("su_none_h") + "</p>") + '<div class="actions"><button class="btn btn-main" type="button" data-act="athol">' + ic("plus") + t("su_add_hol") + "</button></div></section>";
    return smp + lv + hd;
  }
  A.act("atsamp", function (el) {
    var load = el.getAttribute("data-v") === "load"; if (!load && !confirm(t("at_sure"))) return; el.disabled = true;
    A.api.write(load ? "POST" : "DELETE", "attendance/samples", {}, { online: true }).then(function () { A.toast(t(load ? "su_loaded" : "su_removed")); A.reload(true); }, function (e) { el.disabled = false; A.toast(msg(e)); });
  });
  A.act("atdel", function (el) { el.disabled = true; A.api.write("DELETE", "attendance/" + el.getAttribute("data-k") + "/" + el.getAttribute("data-id"), {}, { online: true }).then(function () { A.toast(t("at_removed")); A.reload(true); }, function (e) { el.disabled = false; A.toast(msg(e)); }); });
  A.act("atleave", function () {
    var today = new Date().toISOString().slice(0, 10);
    A.sheet(t("su_add_leave"), '<form id="leaveForm" novalidate><div><label for="lE">' + t("at_employee") + '</label><select id="lE">' + D.emps.employees.filter(function (x) { return x.status === "active"; }).map(function (x) { return '<option value="' + x.id + '">' + esc(nm(x)) + "</option>"; }).join("") + "</select></div>" +
      '<div class="pair">' + f("lS", t("lv_from"), today, "date") + f("lT", t("lv_to"), today, "date") + '</div><div><label for="lK">' + t("lv_kind") + '</label><select id="lK">' + ["annual", "sick", "emergency", "unpaid", "other"].map(function (k) { return '<option value="' + k + '">' + t("lv_" + k) + "</option>"; }).join("") + "</select></div>" +
      f("lR", t("lv_reason"), "", "text", 'maxlength="300"') + '<div class="err" id="formErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("at_save") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("at_cancel") + "</button></div></form>");
  });
  A.form("leaveForm", function () {
    var er = document.getElementById("formErr"), v = function (i) { return document.getElementById(i).value; }; er.textContent = "";
    A.api.write("POST", "attendance/leaves", { employee_id: +v("lE"), start_date: v("lS"), end_date: v("lT"), kind: v("lK"), reason: v("lR").trim() || null }, { online: true }).then(function () { A.closeSheet(); A.toast(t("at_saved")); A.reload(true); }, function (e) { er.textContent = msg(e); });
  });
  A.act("athol", function () {
    A.sheet(t("su_add_hol"), '<form id="holForm" novalidate>' + f("hD", t("hol_date"), "", "date") + f("hE", t("hol_name_en"), "", "text", 'maxlength="100"') + f("hA", t("hol_name_ar"), "", "text", 'maxlength="100" dir="rtl"') +
      '<div class="err" id="formErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit">' + t("at_save") + '</button><button class="btn btn-line" type="button" data-act="close">' + t("at_cancel") + "</button></div></form>");
  });
  A.form("holForm", function () {
    var er = document.getElementById("formErr"), v = function (i) { return document.getElementById(i).value.trim(); }; er.textContent = "";
    A.api.write("POST", "attendance/holidays", { date: v("hD"), name_en: v("hE"), name_ar: v("hA") || null }, { online: true }).then(function () { A.closeSheet(); A.toast(t("at_saved")); A.reload(true); }, function (e) { er.textContent = msg(e); });
  });

  /* ---------------- page ---------------- */
  A.act("attab", function (el) { A.ui.atttab = el.getAttribute("data-v"); A.saveUI(); A.reload(true); });
  function render(d) {
    D = d; var cur = tab();
    var tabs = '<div class="filters" role="group">' + TABS.filter(tabOk).map(function (k) { return '<button class="fbtn" type="button" data-act="attab" data-v="' + k + '" aria-pressed="' + (cur === k) + '">' + t("tb_" + k) + "</button>"; }).join("") + "</div>";
    var body = cur === "today" ? clockCard() + board() : cur === "records" ? records() : cur === "people" ? people() : cur === "payroll" ? payroll() : setup();
    return '<div class="head"><h1 class="title">' + t("at_title") + '</h1><p class="lead">' + t("at_lead") + "</p></div>" + tabs + '<div class="at-body">' + body + "</div>";
  }
  function after() {
    if (clockTimer) { clearInterval(clockTimer); clockTimer = null; }
    if (document.getElementById("atTime")) clockTimer = setInterval(function () { var a = document.getElementById("atTime"); if (!a) { clearInterval(clockTimer); clockTimer = null; return; } a.textContent = nowText(); var b = document.getElementById("atDate"); if (b) b.textContent = dateText(); }, 1000);
  }
  function load() {
    var cur = tab(), manage = A.can("attendance.manage"), base = {}, calls = [];
    var out = function (o) { return Promise.resolve({ data: o }); };
    var mePromise = A.can("attendance.clock") ? A.api.get("attendance/me", { cache: false }).then(function (r) { var m = r.data; base.me = m; if (m && m.now) { base.offset = new Date(m.now).getTime() - Date.now(); tz = m.timezone || tz; } }) : Promise.resolve();
    if (cur === "today") calls.push(mePromise, manage ? A.api.get("attendance/board", { cache: false }).then(function (r) { base.board = r.data; }) : Promise.resolve());
    else if (cur === "records") {
      calls.push(manage ? A.api.get("attendance/employees", { cache: false }).then(function (r) {
        base.emps = r.data; var act = r.data.employees.filter(function (x) { return x.status === "active"; }), id = A.ui.attemp, hit = act.filter(function (x) { return x.id === id; })[0] || act[0];
        if (!hit) { base.rec = null; return; }
        return A.api.get("attendance/records?employee_id=" + hit.id + "&month=" + month(), { cache: false }).then(function (rr) { base.rec = rr.data; });
      }) : A.api.get("attendance/records?month=" + month(), { cache: false }).then(function (r) { base.rec = r.data; }, function () { base.rec = null; }));
    } else if (cur === "people") calls.push(A.api.get("attendance/employees", { cache: false }).then(function (r) { base.emps = r.data; }));
    else if (cur === "payroll") calls.push(A.api.get("attendance/payroll?month=" + month(), { cache: false }).then(function (r) { base.pay = r.data; }));
    else calls.push(A.api.get("attendance/employees", { cache: false }).then(function (r) { base.emps = r.data; }), A.api.get("attendance/leaves?month=" + month(), { cache: false }).then(function (r) { base.leaves = r.data; }), A.api.get("attendance/holidays", { cache: false }).then(function (r) { base.holidays = r.data; }));
    return Promise.all(calls).then(function () { if (cur === "setup") base.setup = { leaves: base.leaves, holidays: base.holidays }; return out(base); });
  }
  A.start({ page: "attendance", nav: "attendance", title: "at_title", needsConn: true, shell: true, load: load, render: render, after: after });
})();
