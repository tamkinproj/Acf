(function () {
  var A = ACF, t = A.t, $ = A.$;

  A.strings({
    wel_title: "Welcome", wel_lead: "Choose your own password to start. Type the temporary password you were given as the current password.", wel_btn: "Save and continue", wel_saving: "Saving…", pw_cur: "Current password", pw_new: "New password", pw_again: "New password again", pw_hint: "At least 12 characters with upper and lower case and a number. No common words, and not your name or username. Your other devices will be signed out.", pw_same: "The two new passwords are not the same.", pw_conn: "Changing the password needs a connection."
  }, {
    wel_title: "أهلًا بك", wel_lead: "اختر كلمة مرورك الخاصة للبدء. اكتب كلمة المرور المؤقتة التي أُعطيت لك في خانة كلمة المرور الحالية.", wel_btn: "حفظ ومتابعة", wel_saving: "جارٍ الحفظ…", pw_cur: "كلمة المرور الحالية", pw_new: "كلمة المرور الجديدة", pw_again: "أعد كتابة كلمة المرور الجديدة", pw_hint: "12 حرفًا على الأقل مع حروف كبيرة وصغيرة ورقم. سيتم تسجيل خروج أجهزتك الأخرى.", pw_same: "كلمتا المرور الجديدتان غير متطابقتين.", pw_conn: "تغيير كلمة المرور يحتاج اتصالًا."
  });

  A.form("welForm", function () {
    var cur = $("#wCur").value, pw = $("#wNew").value, again = $("#wAgain").value, err = $("#welErr"), btn = $("#welForm button[type=submit]");
    err.textContent = "";
    if (!cur || !pw) { err.textContent = t("login_err"); return; }
    if (pw !== again) { err.textContent = t("pw_same"); return; }
    if (navigator.onLine === false) { err.textContent = t("pw_conn"); return; }
    btn.disabled = true; btn.textContent = t("wel_saving");
    A.api.write("POST", "me/password", { current_password: cur, password: pw }, { online: true }).then(function () {
      return A.api.refreshMe();
    }).then(function () { location.replace("dashboard.html"); }, function (e) {
      btn.disabled = false; btn.textContent = t("wel_btn");
      var first = e.errors && Object.keys(e.errors).length ? e.errors[Object.keys(e.errors)[0]][0] : null;
      err.textContent = first || (e.offline ? t("pw_conn") : A.msg(e));
    });
  });

  A.start({ page: "welcome", title: "wel_title", shell: false });
})();
