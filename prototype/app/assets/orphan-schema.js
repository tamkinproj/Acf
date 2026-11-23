/* Every piece of information kept about an orphan, grouped, with English and Arabic labels.
   Used by the staff Add/Edit form, the profile page, the application review page and the public application form.
   The keys match insania-backend/config/orphan_fields.php.
   t: text | long | date | num | dec | tel | sel      staff:1 = only the foundation fills it in (not on the public form)
   req:1 = required on the public form      o = options [value, English, Arabic] */
(function () {
  var LEVEL = [["", "-", "-"], ["none", "Not studying", "لا يدرس"], ["kindergarten", "Kindergarten", "الروضة"], ["primary", "Primary", "الابتدائية"], ["intermediate", "Intermediate", "المتوسطة"], ["secondary", "Secondary", "الثانوية"], ["vocational", "Vocational", "مهني"], ["university", "University", "الجامعة"]];
  var RATE = [["", "-", "-"], ["weak", "Weak ★", "ضعيف ★"], ["average", "Average ★ ★", "متوسط ★ ★"], ["good", "Good ★ ★ ★", "جيد ★ ★ ★"], ["very_good", "Very good ★ ★ ★ ★", "جيد جدًا ★ ★ ★ ★"], ["excellent", "Excellent ★ ★ ★ ★ ★", "ممتاز ★ ★ ★ ★ ★"]];
  var MARITAL = [["", "-", "-"], ["Widow", "Widow", "أرملة"], ["Married", "Married", "متزوجة"], ["Divorced", "Divorced", "مطلقة"], ["Other", "Other", "أخرى"]];
  function f(k, en, ar, t, x) { var o = { k: k, en: en, ar: ar, t: t || "text" }; for (var i in x) o[i] = x[i]; return o; }

  var RELATION = [["", "-", "-"], ["Mother", "Mother", "الأم"], ["Father", "Father", "الأب"], ["Grandfather", "Grandfather", "الجد"], ["Grandmother", "Grandmother", "الجدة"], ["Uncle", "Uncle", "العم / الخال"], ["Aunt", "Aunt", "العمة / الخالة"], ["Brother", "Brother", "الأخ"], ["Sister", "Sister", "الأخت"], ["Other", "Other", "أخرى"]];
  /* The names the way the Dar Al Ber form asks for them: first, father, grandfather, family - Arabic row, then English row.
     who: child | mother | resp. The columns are child_first_ar ... resp_family_en (the full names are kept too, joined from these). */
  function names(who, en, ar) {
    var out = [], L = [["ar", "(بالعربية)", "(Arabic)", "rtl"], ["en", "(بالانجليزية)", "(English)", "ltr"]];
    L.forEach(function (l) {
      [["first", 0], ["father", 1], ["grand", 2], ["family", 3]].forEach(function (p) {
        out.push(f(who + "_" + p[0] + "_" + l[0], en[p[1]] + " " + l[2], ar[p[1]] + " " + l[1], "text", { dir: l[3] }));
      });
    });
    return out;
  }

  /* The first three groups are the four steps of the Dar Al Ber form (the fourth, the papers, is the Attachments card). */
  window.ORPHAN_SCHEMA = [
    { id: "basic", en: "Basic data", ar: "البيانات الاساسية", fields: names("child", ["Orphan first name", "Orphan's father name", "Orphan's grandfather name", "Grandfather / family name"], ["الاسم الاول لليتيم", "اسم والد اليتيم", "اسم جد اليتيم", "اسم جد اليتيم/العائلة"]).concat([
      f("gender", "Gender", "النوع", "sel", { req: 1, o: [["", "-", "-"], ["m", "Male", "ذكر"], ["f", "Female", "أنثى"]] }),
      f("birth_country", "Place of birth (country)", "محل الميلاد", "text"),
      f("residence_country", "Country of residence", "دولة الاقامة", "text"),
      f("city", "City of residence", "مدينة الاقامة", "text"),
      f("birth_date", "Date of birth", "تاريخ الميلاد", "date", { req: 1 }),
      f("address_ar", "Address (Arabic)", "العنوان (بالعربية)", "long", { dir: "rtl" }),
      f("address_en", "Address (English)", "العنوان (بالانجليزية)", "long", { dir: "ltr" }),
      f("health_status", "Health status", "الحالة الصحية", "sel", { o: [["", "-", "-"], ["good", "Good", "جيدة"], ["needs_followup", "Needs follow-up", "تحتاج متابعة"]] }),
      f("study_level", "Study stage", "المرحلة الدراسية", "sel", { o: LEVEL }),
      f("reason_not_studying", "Reason for not studying, if any", "سبب عدم الدراسة ان وجد", "long"),
      f("grade", "School grade", "الصف الدراسي", "text")
    ]) },
    { id: "family", en: "Family data", ar: "بيانات العائلة", fields: names("mother", ["Mother first name", "Mother's father name", "Mother's grandfather name", "Grandfather / family name"], ["الاسم الاول للأم", "اسم والد الأم", "اسم جد الأم", "اسم جد الأم/العائلة"]).concat([
      f("mother_marital_status", "Mother's marital status", "الحالة الاجتماعية للأم", "sel", { o: MARITAL }),
      f("mother_works", "Mother's work", "عمل الأم", "sel", { o: [["", "-", "-"], ["yes", "Works", "تعمل"], ["no", "Does not work", "لا تعمل"]] }),
      f("mother_salary", "Mother's salary, if she works", "راتب الام ان كانت تعمل", "dec"),
      f("father_death_date", "Father's date of death", "تاريخ وفاة الأب", "date"),
      f("brothers", "Number of brothers", "عدد الأشقاء الذكور", "num"),
      f("sisters", "Number of sisters", "عدد الأشقاء الاناث", "num"),
      f("aided_siblings", "Total brothers and sisters receiving aid", "اجمالي عدد الاشقاء الذين يتلقون المساعدات ذكور و اناث", "num"),
      f("other_aid", "Other sources of aid", "مصادر اخرى للمساعدات", "long")
    ]) },
    { id: "responsible", en: "Direct responsible person", ar: "بيانات المسؤول المباشر", fields: names("resp", ["Responsible person first name", "Responsible person's father name", "Responsible person's grandfather name", "Grandfather / family name"], ["الاسم الاول للمسؤول المباشر", "اسم والد المسؤول المباشر", "اسم جد المسؤول المباشر", "اسم جد المسؤول المباشر/العائلة"]).concat([
      f("responsible_relation", "Relation of the responsible person to the orphan", "علاقة المسؤول المباشر باليتيم", "sel", { o: RELATION }),
      f("guardian_phone", "Responsible person's phone", "هاتف المسؤول المباشر", "tel", { req: 1, dir: "ltr" })
    ]) },
    { id: "more", en: "More details", ar: "تفاصيل أخرى", fields: [
      f("nationality", "Nationality", "الجنسية", "text"),
      f("health_details", "Health details", "تفاصيل الحالة الصحية", "long"),
      f("madrasa_grade", "Madrasa grade", "صف المدرسة الدينية", "text"),
      f("quran_memorization", "Quran memorization", "حفظ القرآن", "text"),
      f("responsible_since", "Responsible since", "مسؤول منذ", "date"),
      f("responsible_person", "Responsible organization", "الجهة المسؤولة", "text", { staff: 1 }),
      f("channel_label", "Channel", "القناة", "text", { staff: 1 }),
      f("source_label", "Source", "المصدر", "text", { staff: 1 }),
      f("sponsor_name", "Sponsor name", "اسم الكفيل", "text", { staff: 1 }),
      f("sponsor_file_no", "Sponsor file no.", "رقم ملف الكفيل", "text", { staff: 1, dir: "ltr" }),
      f("performance", "Performance", "الأداء", "sel", { staff: 1, o: RATE }),
      f("islamic_commitment", "Commitment to Islamic teachings", "الالتزام بالتعاليم الإسلامية", "sel", { staff: 1, o: RATE })
    ] },
    { id: "bank", en: "Bank account", ar: "الحساب البنكي", note: { en: "Only the last 4 digits of the account number are kept.", ar: "يُحفظ آخر 4 أرقام فقط من رقم الحساب." }, fields: [
      f("bank_holder", "Account name", "اسم صاحب الحساب", "text"),
      f("bank_name", "Bank (AUB / Hello Money)", "البنك (AUB / Hello Money)", "text"),
      f("bank_last4", "Account number (last 4 digits)", "رقم الحساب (آخر 4 أرقام)", "text", { dir: "ltr", max: 4 })
    ] },
    { id: "notes", en: "Foundation notes", ar: "ملاحظات المؤسسة", staff: 1, fields: [
      f("notable_changes", "Notable changes this year", "تغييرات ملحوظة هذا العام", "long", { staff: 1 }),
      f("organization_comment", "Organization comment", "تعليق المؤسسة", "long", { staff: 1 }),
      f("message_to_sponsor", "Message", "رسالة", "long", { staff: 1 }),
      f("delay_reason", "Delay reason", "سبب التأخير", "text", { staff: 1 }),
      f("expected_send_date", "Expected send date", "تاريخ الإرسال المتوقع", "date", { staff: 1 }),
      f("photo_links", "Photo links (from the update form)", "روابط الصور (من نموذج التحديث)", "long", { staff: 1, dir: "ltr" })
    ] }
  ];
  window.ORPHAN_SCHEMA_STATUS = [["active", "Active", "نشط"], ["pending", "Pending", "معلّق"], ["ended", "Ended", "منتهٍ"]];
})();
