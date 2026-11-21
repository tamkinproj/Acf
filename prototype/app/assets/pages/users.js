(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, list = [];

  A.strings({
    users_title: "Accounts", users_note: "Account administration needs an internet connection, for safety. Nothing here is saved on the phone.", add_user: "Add office user",
    u_confirm: "Your own password", u_confirm_hint: "Type your own password to confirm this change.", f_uuser: "Username", f_uuser_hint: "Used to sign in. Leave empty to make one from the email.", f_uname: "Full name", f_uemail: "Email", f_urole: "Role", f_udept: "Department", f_upass: "Password", gen_pass: "Generate", f_uactive: "Account is active",
    pass_hint: "At least 12 characters with upper and lower case and a number. Tell the person privately; they should change it.",
    locked_pres: "The President Admin account is managed by the System Admin.", role_system_admin: "System Admin", role_admin_maintenance: "Admin Maintenance", role_security_admin: "Security Admin", role_president_admin: "President Admin", role_staff: "Staff", role_user: "User (family)",
    dept_aytam: "Aytam", dept_finance: "Finance", dept_projects: "Projects", dept_relief: "Relief", dept_education: "Education", acc_title: "What this account can open", acc_note: "Starts from the department's usual access. Tick or untick to change it for this person only.", acc_custom: "custom access", acc_reset: "Back to the department's access", acc_g_orphans: "Orphans and applications", acc_g_finance: "Payments and reports", acc_g_projects: "Projects", acc_g_education: "Education", acc_g_attendance: "HR and attendance", acc_g_chat: "Chat", acc_g_work: "Work tracker", p_work_view: "See how staff use the app (work tracker)", p_children_view: "See orphan records", p_children_create: "Add orphans", p_children_update: "Edit orphans", p_bank_verify: "Check bank accounts", p_documents_view: "See documents", p_documents_upload: "Upload documents", p_orphans_accounts: "Orphan sign-in accounts", p_applications_review: "Review applications", p_payments_view: "See payments", p_payments_mark_cash: "Record cash received", p_reports_view: "See reports", p_reports_export: "Export reports", p_projects_view: "See projects", p_projects_manage: "Manage projects", p_education_view: "See Education", p_education_manage: "Manage teachers, institutions and curriculum", p_education_salary: "See and record teacher salaries", p_attendance_clock: "Clock in and out", p_attendance_manage: "Manage attendance (employees, corrections, leaves)", p_attendance_salary: "See salaries and payroll", p_chat_use: "Use the office chat", wz_create_user: "Create account", wz_who: "Who is it?", wz_role: "Role and department", wz_access: "What they can open", wz_signin: "Password and review", wz_features: "{n} features", wz_need_pass: "Give the account a password.", wz_need_confirm: "Type your own password to confirm.", dept_hr: "Human resources", dept_administration: "Administration",
    inactive: "Switched off", active: "Active", reset_pass: "Set a new password", revoke: "End all sessions", user_saved: "Saved", user_added: "Account created", sessions_ended: "Sessions ended", pass_changed: "Password changed",
    family_note: "Family accounts are linked to a guardian record. They are created in the next phase, with the people records.", save_changes: "Save changes", edit_user: "Edit account", you: "you", locked: "Cannot be changed here"
  }, {
    users_title: "الحسابات", users_note: "إدارة الحسابات تحتاج اتصالًا بالإنترنت للأمان. لا يُحفظ شيء هنا على الهاتف.", add_user: "إضافة مستخدم للمكتب",
    u_confirm: "كلمة مرورك أنت", u_confirm_hint: "اكتب كلمة مرورك أنت لتأكيد هذا التغيير.", f_uuser: "اسم المستخدم", f_uuser_hint: "يُستخدم لتسجيل الدخول. اتركه فارغًا لإنشائه من البريد.", f_uname: "الاسم الكامل", f_uemail: "البريد الإلكتروني", f_urole: "الدور", f_udept: "القسم", f_upass: "كلمة المرور", gen_pass: "توليد", f_uactive: "الحساب نشط",
    pass_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. أبلغ الشخص بها بسرية؛ عليه تغييرها.",
    locked_pres: "حساب رئيس الإدارة يديره مدير النظام.", role_system_admin: "مدير النظام", role_admin_maintenance: "مشرف الصيانة", role_security_admin: "مدير الأمان", role_president_admin: "رئيس الإدارة", role_staff: "موظف", role_user: "مستخدم (عائلة)",
    dept_aytam: "أيتام", dept_finance: "المالية", dept_projects: "المشاريع", dept_relief: "الإغاثة", dept_education: "التعليم", acc_title: "ما يمكن لهذا الحساب فتحه", acc_note: "يبدأ من صلاحيات القسم المعتادة. فعّل أو ألغِ لتغيير ذلك لهذا الشخص فقط.", acc_custom: "صلاحيات مخصصة", acc_reset: "العودة إلى صلاحيات القسم", acc_g_orphans: "الأيتام والطلبات", acc_g_finance: "المدفوعات والتقارير", acc_g_projects: "المشاريع", acc_g_education: "التعليم", acc_g_attendance: "الموارد البشرية والحضور", acc_g_chat: "المحادثة", acc_g_work: "متابعة العمل", p_work_view: "رؤية كيف يستخدم الموظفون التطبيق (متابعة العمل)", p_children_view: "عرض سجلات الأيتام", p_children_create: "إضافة أيتام", p_children_update: "تعديل الأيتام", p_bank_verify: "التحقق من الحسابات البنكية", p_documents_view: "عرض المستندات", p_documents_upload: "رفع المستندات", p_orphans_accounts: "حسابات دخول الأيتام", p_applications_review: "مراجعة الطلبات", p_payments_view: "عرض المدفوعات", p_payments_mark_cash: "تسجيل المبالغ النقدية", p_reports_view: "عرض التقارير", p_reports_export: "تصدير التقارير", p_projects_view: "عرض المشاريع", p_projects_manage: "إدارة المشاريع", p_education_view: "عرض التعليم", p_education_manage: "إدارة المعلمين والمؤسسات والمنهج", p_education_salary: "عرض رواتب المعلمين وتسجيلها", p_attendance_clock: "تسجيل الحضور والانصراف", p_attendance_manage: "إدارة الحضور (الموظفون والتصحيح والإجازات)", p_attendance_salary: "عرض الرواتب وكشف الرواتب", p_chat_use: "استخدام محادثة المكتب", wz_create_user: "إنشاء الحساب", wz_who: "من هو؟", wz_role: "الدور والقسم", wz_access: "ما يمكنه فتحه", wz_signin: "كلمة المرور والمراجعة", wz_features: "{n} ميزات", wz_need_pass: "اختر كلمة مرور للحساب.", wz_need_confirm: "اكتب كلمة مرورك أنت للتأكيد.", dept_hr: "الموارد البشرية", dept_administration: "الإدارة",
    inactive: "موقوف", active: "نشط", reset_pass: "تعيين كلمة مرور جديدة", revoke: "إنهاء كل الجلسات", user_saved: "تم الحفظ", user_added: "تم إنشاء الحساب", sessions_ended: "أُنهيت الجلسات", pass_changed: "تم تغيير كلمة المرور",
    family_note: "حسابات العائلات مرتبطة بسجل وصي. تُنشأ في المرحلة التالية مع سجلات الأشخاص.", save_changes: "حفظ التغييرات", edit_user: "تعديل الحساب", you: "أنت", locked: "لا يمكن تغييره هنا"
  });

  var ROLES = ["admin_maintenance", "security_admin", "staff"];   // President Admin: only the System Admin makes and changes it
  var DEPTS = ["aytam", "finance", "projects", "relief", "education", "hr", "administration"];

  function render(rows) {
    list = rows; var me = A.user();
    return '<div class="head"><h1 class="title">' + t("users_title") + '</h1></div><p class="note">' + t("users_note") + '</p><div class="list">' + rows.map(function (u) {
      var meta = t("role_" + u.role) + (u.department ? " · " + t("dept_" + u.department) : "") + (u.access_custom ? " · " + t("acc_custom") : "");
      return '<button class="item" type="button" data-focus="' + u.id + '" data-act="edituser" data-id="' + u.id + '">' + A.avatar(u, "avatar") + '<span class="grow"><b>' + esc(u.name) + (me && me.id === u.id ? " (" + t("you") + ")" : "") + '</b><span class="sub"><span dir="ltr">@' + esc(u.username || "") + "</span> · " + esc(meta) + '</span></span><span class="right"><span class="badge ' + (u.active ? "" : "warn") + '">' + t(u.active ? "active" : "inactive") + "</span></span></button>";
    }).join("") + '</div><button class="btn btn-main fab" data-act="adduser">' + ic("plus") + t("add_user") + "</button>";
  }
  function roleOptions(sel, withUser) {
    return ROLES.concat(withUser ? ["user"] : []).map(function (r) { return '<option value="' + r + '"' + (r === sel ? " selected" : "") + ">" + t("role_" + r) + "</option>"; }).join("");
  }
  function deptOptions(sel) { return DEPTS.map(function (d) { return '<option value="' + d + '"' + (d === sel ? " selected" : "") + ">" + t("dept_" + d) + "</option>"; }).join(""); }
  function genPass() {
    // random, with the three kinds of characters, and never a run of one character or a plain sequence (the server refuses those)
    var U = "ABCDEFGHJKLMNPQRSTUVWXYZ", L = "abcdefghijkmnopqrstuvwxyz", D = "23456789", all = U + L + D, SEQ = /0123|1234|2345|3456|4567|5678|6789|abcd|bcde|cdef|qwer|asdf|zxcv/i, out;
    do {
      var a = new Uint8Array(16); crypto.getRandomValues(a);
      out = [U[a[0] % U.length], L[a[1] % L.length], D[a[2] % D.length]]; for (var i = 3; i < 16; i++) out.push(all[a[i] % all.length]);
      out = out.join("");
    } while (/(.)\1{3,}/.test(out) || SEQ.test(out));
    return out;
  }
  // the signed-in person's own password, asked again for changes to accounts
  function confirmField() { return A.field("u_confirm_password", t("u_confirm"), '<p class="note" style="margin:.4rem 0 0">' + t("u_confirm_hint") + "</p>", "password", 'dir="ltr" autocomplete="current-password"'); }

  /* What this account can open (President only, staff accounts): ticks start from the department's usual access. */
  var CAT = null, accDirty = false;
  function isPres() { var me = A.user(); return !!me && me.role === "president_admin"; }
  function accHtml() { return isPres() ? '<div id="accBox"></div>' : ""; }
  function accFill(ticked) {
    var box = document.getElementById("accBox"); if (!box || !CAT) return;
    box.innerHTML = '<h4 class="optlbl">' + t("acc_title") + '</h4><p class="note">' + t("acc_note") + "</p>" + CAT.groups.map(function (g) {
      return '<div class="acc-g"><b>' + t("acc_g_" + g.key) + "</b>" + g.permissions.map(function (k) {
        return '<label class="acc-r"><input type="checkbox" class="acc" value="' + k + '"' + (ticked.indexOf(k) > -1 ? " checked" : "") + "><span>" + t("p_" + k.replace(".", "_")) + "</span></label>";
      }).join("") + "</div>";
    }).join("") + '<div class="actions"><button class="btn btn-line btn-sm" type="button" data-act="accreset">' + t("acc_reset") + "</button></div>";
    accDirty = false;
  }
  function accLoad(ticked) { A.api.get("users/access-catalog", { cache: false }).then(function (r) { CAT = r.data; accFill(ticked()); }, function () {}); }
  function accDefaults() { var d = document.getElementById("u_department_key"); return CAT && d ? (CAT.defaults[d.value] || []) : []; }
  function accTicked() { return Array.prototype.slice.call(document.querySelectorAll("#accBox .acc:checked")).map(function (c) { return c.value; }); }
  function accShow(sel) { var box = document.getElementById("accBox"); if (box) box.style.display = sel.value === "staff" ? "" : "none"; }
  A.act("accreset", function () { accFill(accDefaults()); });
  document.addEventListener("change", function (e) {
    var el = e.target; if (!el || !el.classList) return;
    if (el.classList.contains("acc")) accDirty = true;
    else if (el.id === "u_department_key" && document.getElementById("accBox")) { accFill(accDefaults()); }
  });
  function toggleDept(sel) {
    var box = document.getElementById("deptBox"); if (box) box.style.display = sel.value === "staff" ? "" : "none"; accShow(sel);
    var st = document.getElementById("wzAcc"); if (st) { if (sel.value === "staff" && isPres()) st.removeAttribute("data-skip"); else st.setAttribute("data-skip", ""); A.wizardRefresh(document.getElementById("userForm")); }
  }
  function clearErr() { Array.prototype.forEach.call(document.querySelectorAll("#sheetRoot .err"), function (e) { e.textContent = ""; }); }
  function v(id) { return document.getElementById(id).value.trim(); }
  function fail(e) { if (!(e.errors && A.showErrors(e.errors, "u_"))) document.getElementById("formErr").textContent = e.offline ? t("need_conn") : A.msg(e); A.wizardReveal(document.getElementById("userForm")); }

  function adduser() {
    var passField = A.field("u_password", t("f_upass"), '<div class="actions" style="margin-top:.4rem"><button class="btn btn-line btn-sm" type="button" data-act="genpass">' + t("gen_pass") + '</button></div><p class="note" style="margin:.4rem 0 0">' + t("pass_hint") + "</p>", "text", 'dir="ltr" autocomplete="off"');
    A.sheet(t("add_user"), '<form id="userForm" novalidate>' +
      '<section class="wz-step" data-title="' + esc(t("wz_who")) + '">' + A.field("u_name", t("f_uname")) + A.field("u_email", t("f_uemail"), "", "email", 'dir="ltr" autocomplete="off" inputmode="email" autocapitalize="none"') +
      A.field("u_username", t("f_uuser"), '<p class="note" style="margin:.4rem 0 0">' + t("f_uuser_hint") + "</p>", "text", 'dir="ltr" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="off"') + "</section>" +
      '<section class="wz-step" data-title="' + esc(t("wz_role")) + '"><div><label for="u_role">' + t("f_urole") + '</label><select id="u_role">' + roleOptions("staff", false) + '</select><div class="err" id="u_roleErr"></div></div>' +
      '<div id="deptBox"><label for="u_department_key">' + t("f_udept") + '</label><select id="u_department_key">' + deptOptions("aytam") + '</select><div class="err" id="u_department_keyErr"></div></div></section>' +
      '<section class="wz-step" id="wzAcc" data-title="' + esc(t("wz_access")) + '"' + (isPres() ? "" : " data-skip") + ">" + accHtml() + "</section>" +
      '<section class="wz-step" data-title="' + esc(t("wz_signin")) + '"><div class="wz-review" id="wzReview"></div>' + passField + confirmField() + "</section>" +
      '<div class="err" id="formErr" role="alert"></div></form>');
    var form = document.getElementById("userForm"), sel = document.getElementById("u_role"); sel.addEventListener("change", function () { toggleDept(sel); });
    var m = location.search.match(/[?&]role=([a-z_]+)/), d = location.search.match(/[?&]dept=([a-z]+)/);
    if (!adduser.done && m && ROLES.indexOf(m[1]) > -1) { sel.value = m[1]; if (d && DEPTS.indexOf(d[1]) > -1) document.getElementById("u_department_key").value = d[1]; }
    adduser.done = true;
    A.wizard(form, {
      create: "wz_create_user",
      validate: function (i, step) {
        if (!step.querySelector("#u_name")) return true;
        var ok = true; if (!v("u_name")) { document.getElementById("u_nameErr").textContent = t("required"); ok = false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v("u_email"))) { document.getElementById("u_emailErr").textContent = t("wz_bad_email"); ok = false; }
        return ok;
      },
      onShow: function (i, step) {
        var box = step.querySelector("#wzReview"); if (!box) return;
        var role = v("u_role"), row = function (k, val) { return "<div><small>" + esc(t(k)) + "</small><b>" + esc(val) + "</b></div>"; };
        box.innerHTML = row("f_uname", v("u_name")) + row("f_uemail", v("u_email")) + row("f_urole", t("role_" + role)) +
          (role === "staff" ? row("f_udept", t("dept_" + v("u_department_key"))) + (document.querySelector("#accBox .acc") ? row("acc_title", t("wz_features", { n: accTicked().length })) : "") : "");
      }
    });
    toggleDept(sel); accLoad(accDefaults);
  }
  A.act("adduser", adduser);
  A.act("genpass", function () { document.getElementById("u_password").value = genPass(); });
  A.form("userForm", function () {
    clearErr();
    var body = { name: v("u_name"), email: v("u_email"), role: v("u_role"), password: v("u_password"), confirm_password: document.getElementById("u_confirm_password").value }; if (v("u_username")) body.username = v("u_username");
    if (!body.name) { document.getElementById("u_nameErr").textContent = t("required"); A.wizardReveal(document.getElementById("userForm")); return; }
    if (!body.password) { document.getElementById("u_passwordErr").textContent = t("wz_need_pass"); return; }
    if (!body.confirm_password) { document.getElementById("u_confirm_passwordErr").textContent = t("wz_need_confirm"); return; }
    if (body.role === "staff") { body.department_key = v("u_department_key"); if (document.querySelector("#accBox .acc")) body.access = accTicked(); }
    var btn = document.querySelector("#userForm button[type=submit]"); btn.disabled = true;
    A.api.write("POST", "users", body, { online: true }).then(function () { A.closeSheet(); A.toast(t("user_added")); A.reload(true); }, function (e) { btn.disabled = false; fail(e); });
  });

  A.act("edituser", function (el) {
    var u = list.filter(function (x) { return x.id === parseInt(el.getAttribute("data-id"), 10); })[0], me = A.user();
    if (!u) return;
    if (u.role === "system_admin" || u.role === "president_admin") { A.toast(t(u.role === "president_admin" ? "locked_pres" : "locked")); return; }
    var self = me && me.id === u.id, fixedRole = self || u.role === "user";
    A.sheet(t("edit_user"), '<form id="editForm" data-id="' + u.id + '" novalidate><div class="prof-head">' + A.avatar(u, "big") + '<div class="grow"><b>' + esc(u.name) + '</b><span class="note" style="display:block">' + esc(u.email) + "</span></div></div>" +
      A.field("u_username", t("f_uuser"), "", "text", 'dir="ltr" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="off" value="' + esc(u.username || "") + '"') +
      '<div><label for="u_role">' + t("f_urole") + '</label><select id="u_role"' + (fixedRole ? " disabled" : "") + ">" + roleOptions(u.role, u.role === "user") + '</select><div class="err" id="u_roleErr"></div></div>' +
      '<div id="deptBox"><label for="u_department_key">' + t("f_udept") + '</label><select id="u_department_key">' + deptOptions(u.department || "aytam") + '</select><div class="err" id="u_department_keyErr"></div></div>' + (u.role === "staff" ? accHtml() : "") +
      (self ? "" : '<div class="setrow"><b>' + t("f_uactive") + '</b><button class="switch" type="button" role="switch" id="u_active" aria-checked="' + !!u.active + '" data-act="flip"></button></div>') +
      confirmField() + A.formEnd("save_changes") + '</form><div class="actions" style="margin-top:1rem"><button class="btn btn-line btn-sm" type="button" data-act="newpass" data-id="' + u.id + '">' + t("reset_pass") + '</button>' +
      '<button class="btn btn-line btn-sm" type="button" data-act="endsessions" data-id="' + u.id + '">' + t("revoke") + "</button>" + A.shareBtn("user", u.id) + "</div>");
    var sel = document.getElementById("u_role"); sel.addEventListener("change", function () { toggleDept(sel); }); toggleDept(sel);
    if (u.role === "staff") accLoad(function () { return u.access || []; });
  });
  A.act("flip", function (el) { el.setAttribute("aria-checked", String(el.getAttribute("aria-checked") !== "true")); });
  A.form("editForm", function (f) {
    clearErr();
    var id = f.getAttribute("data-id"), u = list.filter(function (x) { return String(x.id) === id; })[0], body = {};
    var role = v("u_role"); if (role !== u.role) body.role = role;
    if (v("u_username") && v("u_username").toLowerCase() !== (u.username || "")) body.username = v("u_username");
    if (role === "staff") body.department_key = v("u_department_key");
    if (role === "staff" && accDirty && document.querySelector("#accBox .acc")) body.access = accTicked();
    var act = document.getElementById("u_active"); if (act && (act.getAttribute("aria-checked") === "true") !== !!u.active) body.active = act.getAttribute("aria-checked") === "true";
    if (!Object.keys(body).length) { A.closeSheet(); return; }
    body.confirm_password = document.getElementById("u_confirm_password").value;
    if (body.role === undefined && role === "staff" && body.department_key === u.department) delete body.department_key;
    A.api.write("PATCH", "users/" + id, body, { online: true }).then(function () { A.closeSheet(); A.toast(t("user_saved")); A.reload(true); }, fail);
  });
  A.act("endsessions", function (el) {
    A.api.write("POST", "users/" + el.getAttribute("data-id") + "/revoke-tokens", {}, { online: true }).then(function () { A.closeSheet(); A.toast(t("sessions_ended")); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });
  A.act("newpass", function (el) {
    var id = el.getAttribute("data-id");
    A.sheet(t("reset_pass"), '<form id="passForm" data-id="' + id + '" novalidate>' + confirmField() + A.field("u_password", t("f_upass"), '<div class="actions" style="margin-top:.4rem"><button class="btn btn-line btn-sm" type="button" data-act="genpass">' + t("gen_pass") + '</button></div><p class="note" style="margin:.4rem 0 0">' + t("pass_hint") + "</p>", "text", 'dir="ltr" autocomplete="off"') + A.formEnd() + "</form>");
  });
  A.form("passForm", function (f) {
    clearErr();
    A.api.write("POST", "users/" + f.getAttribute("data-id") + "/password", { password: v("u_password"), confirm_password: document.getElementById("u_confirm_password").value }, { online: true }).then(function () { A.closeSheet(); A.toast(t("pass_changed")); }, fail);
  });

  function openFromLink() { if (/[?&]add=1/.test(location.search) && !openFromLink.done) { openFromLink.done = true; adduser(); } }
  A.start({ page: "users", nav: "users", title: "n_users", needsConn: true, perm: "users.manage", shell: true, load: function () { return A.api.get("users", { cache: false }); }, render: render, after: openFromLink });
})();
