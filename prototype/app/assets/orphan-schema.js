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

  window.ORPHAN_SCHEMA = [
    { id: "info", en: "Information", ar: "المعلومات", fields: [
      f("name_en", "Full name (English)", "الاسم الكامل (بالإنجليزية)", "text", { dir: "ltr" }),
      f("name_ar", "Orphan name (Arabic)", "اسم اليتيم (بالعربية)", "text", { dir: "rtl" }),
      f("gender", "Gender", "الجنس", "sel", { req: 1, o: [["", "-", "-"], ["m", "Male", "ذكر"], ["f", "Female", "أنثى"]] }),
      f("birth_date", "Date of birth", "تاريخ الميلاد", "date", { req: 1 }),
      f("nationality", "Nationality", "الجنسية", "text"),
      f("channel_label", "Channel", "القناة", "text", { staff: 1 }),
      f("source_label", "Source", "المصدر", "text", { staff: 1 }),
      f("sponsor_name", "Sponsor name", "اسم الكفيل", "text", { staff: 1 }),
      f("sponsor_file_no", "Sponsor file no.", "رقم ملف الكفيل", "text", { staff: 1, dir: "ltr" })
    ] },
    { id: "study", en: "Health and study", ar: "الصحة والدراسة", fields: [
      f("health_status", "Health status", "الحالة الصحية", "sel", { o: [["", "-", "-"], ["good", "Good", "جيدة"], ["needs_followup", "Needs follow-up", "تحتاج متابعة"]] }),
      f("health_details", "Health details", "تفاصيل الحالة الصحية", "long"),
      f("study_level", "Study level", "المرحلة الدراسية", "sel", { o: LEVEL }),
      f("grade", "Grade", "الصف", "text"),
      f("madrasa_grade", "Madrasa grade", "صف المدرسة الدينية", "text"),
      f("reason_not_studying", "Reason for not studying", "سبب عدم الدراسة", "long"),
      f("quran_memorization", "Quran memorization", "حفظ القرآن", "text"),
      f("performance", "Performance", "الأداء", "sel", { staff: 1, o: RATE }),
      f("islamic_commitment", "Commitment to Islamic teachings", "الالتزام بالتعاليم الإسلامية", "sel", { staff: 1, o: RATE })
    ] },
    { id: "place", en: "Where the child lives", ar: "مكان السكن", fields: [
      f("birth_country", "Birth country", "بلد الميلاد", "text"),
      f("residence_country", "Residence country", "بلد الإقامة", "text"),
      f("city", "City", "المدينة", "text"),
      f("address_ar", "Address (Arabic)", "العنوان (بالعربية)", "long", { dir: "rtl" }),
      f("address_en", "Address (English)", "العنوان (بالإنجليزية)", "long", { dir: "ltr" })
    ] },
    { id: "family", en: "Mother and family", ar: "الأم والأسرة", fields: [
      f("mother_name_ar", "Mother name (Arabic)", "اسم الأم (بالعربية)", "text", { dir: "rtl" }),
      f("mother_name", "Mother name (English)", "اسم الأم (بالإنجليزية)", "text", { dir: "ltr" }),
      f("mother_marital_status", "Mother marital status", "الحالة الاجتماعية للأم", "sel", { o: MARITAL }),
      f("mother_works", "Mother works", "هل تعمل الأم", "sel", { o: [["", "-", "-"], ["yes", "Yes", "نعم"], ["no", "No", "لا"]] }),
      f("mother_salary", "Mother salary", "راتب الأم", "dec"),
      f("father_death_date", "Father death date", "تاريخ وفاة الأب", "date"),
      f("brothers", "Number of brothers", "عدد الإخوة", "num"),
      f("sisters", "Number of sisters", "عدد الأخوات", "num"),
      f("aided_siblings", "Aided brothers and sisters", "الإخوة والأخوات المكفولون", "num"),
      f("other_aid", "Other aid", "مساعدات أخرى", "long")
    ] },
    { id: "guardian", en: "Guardian and responsible person", ar: "الوصي والمسؤول", fields: [
      f("guardian_name", "Guardian", "الوصي", "text", { req: 1 }),
      f("guardian_relation", "Relation to orphan", "صلة القرابة باليتيم", "text"),
      f("guardian_phone", "Guardian phone", "هاتف الوصي", "tel", { req: 1, dir: "ltr" }),
      f("responsible_person", "Responsible person", "الجهة المسؤولة", "text", { staff: 1 }),
      f("responsible_name_ar", "Responsible person (Arabic)", "المسؤول (بالعربية)", "text", { dir: "rtl" }),
      f("responsible_name_en", "Responsible person (English)", "المسؤول (بالإنجليزية)", "text", { dir: "ltr" }),
      f("responsible_relation", "Responsible person relation", "صلة المسؤول", "text"),
      f("responsible_since", "Since", "منذ", "date")
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
      f("expected_send_date", "Expected send date", "تاريخ الإرسال المتوقع", "date", { staff: 1 })
    ] }
  ];
  window.ORPHAN_SCHEMA_STATUS = [["active", "Active", "نشط"], ["pending", "Pending", "معلّق"], ["ended", "Ended", "منتهٍ"]];
})();
