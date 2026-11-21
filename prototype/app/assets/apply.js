/* The public application form (no sign-in). Built from the same field list the staff use (orphan-schema.js), minus what only the foundation fills in. */
(function () {
  var S = window.ORPHAN_SCHEMA || [], root = document.getElementById("apply"), API = new URL("../server/api/", location.href).href;
  var lang = "en"; try { if (localStorage.getItem("acf_lang") === "ar") lang = "ar"; } catch (e) {}
  var files = [], sending = false, MAXP = 2;
  var T = {
    title: ["Application for orphan support", "طلب كفالة يتيم"], org: ["Al Insania Charity Foundation Inc. · Pagadian City, Philippines", "مؤسسة الإنسانية الخيرية · باغاديان، الفلبين"],
    intro: ["Please fill in as much as you can. Fields marked * are required. Your answers and photos are private: only the foundation's Aytam team can see them, and sending this form does not by itself mean support is approved.", "يرجى تعبئة أكبر قدر ممكن من المعلومات. الحقول المعلّمة بـ * إلزامية. إجاباتك وصورك خاصة: لا يراها إلا فريق الأيتام في المؤسسة، وإرسال هذا النموذج لا يعني بحد ذاته الموافقة على الكفالة."],
    photos: ["Photos of the child (up to 2)", "صور اليتيم (حتى صورتين)"], addphoto: ["Add a photo", "أضف صورة"], remove: ["Remove", "إزالة"],
    send: ["Send application", "إرسال الطلب"], sending: ["Sending…", "جارٍ الإرسال…"], req: ["required", "إلزامي"], lang: ["العربية", "English"],
    fix: ["Please check the highlighted fields.", "يرجى مراجعة الحقول المظللة."], need: ["This field is required.", "هذا الحقل إلزامي."], name_need: ["Enter the child's name in English or Arabic.", "أدخل اسم الطفل بالإنجليزية أو بالعربية."],
    thanks: ["Thank you. Your application was received.", "شكرًا لك. تم استلام طلبك."], ref: ["Your reference number", "رقمك المرجعي"], keep: ["Please keep this number. The foundation will contact the guardian on the phone number you gave.", "يرجى الاحتفاظ بهذا الرقم. ستتواصل المؤسسة مع الوصي على رقم الهاتف الذي أدخلته."],
    another: ["Send another application", "إرسال طلب آخر"], closed: ["The application form is closed for now.", "نموذج التقديم مغلق حاليًا."], net: ["Could not reach the server. Check your connection and try again.", "تعذر الوصول إلى الخادم. تحقق من الاتصال وحاول مرة أخرى."],
    busy: ["Too many applications were sent from this connection. Please try again later.", "أُرسل عدد كبير من الطلبات من هذا الاتصال. يرجى المحاولة لاحقًا."], privacy: ["Only the last 4 digits of a bank account are asked for. Never send full card or account numbers, or passwords.", "يُطلب آخر 4 أرقام فقط من الحساب البنكي. لا ترسل أرقام البطاقات أو الحسابات كاملة ولا كلمات المرور."]
  };
  function tx(k) { return T[k][lang === "ar" ? 1 : 0]; }
  function lab(x) { return lang === "ar" ? x.ar : x.en; }
  function esc(s) { return String(s === null || s === undefined ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }

  function control(f) {
    var id = "a_" + f.k, dir = f.dir ? ' dir="' + f.dir + '"' : "", inp;
    if (f.t === "sel") inp = '<select id="' + id + '">' + f.o.map(function (o) { return '<option value="' + esc(o[0]) + '">' + esc(lang === "ar" ? o[2] : o[1]) + "</option>"; }).join("") + "</select>";
    else if (f.t === "long") inp = '<textarea id="' + id + '" rows="3"' + dir + ' maxlength="1000"></textarea>';
    else inp = '<input id="' + id + '" type="' + (f.t === "date" ? "date" : f.t === "num" || f.t === "dec" ? "number" : f.t === "tel" ? "tel" : "text") + '"' + dir + (f.t === "num" ? ' min="0" max="30" inputmode="numeric"' : "") + (f.t === "dec" ? ' min="0" step="0.01" inputmode="decimal"' : "") + (f.t === "tel" ? ' inputmode="tel"' : "") + (f.max ? ' maxlength="' + f.max + '"' : ' maxlength="150"') + (f.k === "bank_last4" ? ' inputmode="numeric" pattern="[0-9]*"' : "") + ">";
    var req = f.req || f.k === "name_en" || f.k === "name_ar";
    return '<div class="afield" data-k="' + f.k + '"><label for="' + id + '">' + esc(lab(f)) + (f.req ? ' <span class="star" aria-label="' + tx("req") + '">*</span>' : "") + "</label>" + inp + '<div class="err" id="' + id + 'Err"></div></div>';
  }
  function keepValues() { var v = {}; S.forEach(function (g) { g.fields.forEach(function (f) { var el = document.getElementById("a_" + f.k); if (el) v[f.k] = el.value; }); }); return v; }

  function form(vals) {
    vals = vals || {};
    var groups = S.map(function (g) {
      var fields = g.fields.filter(function (f) { return !f.staff; });
      if (!fields.length) return "";
      return '<section class="card asec"><h2>' + esc(lab(g)) + "</h2>" + (g.note ? '<p class="note" style="margin-top:0">' + esc(lab(g.note)) + "</p>" : "") + fields.map(control).join("") + "</section>";
    }).join("");
    var ph = '<section class="card asec"><h2>' + tx("photos") + '</h2><div class="aphotos" id="aph"></div><button class="btn btn-line" type="button" id="addph">' + tx("addphoto") + '</button><input id="phin" type="file" accept="image/*" hidden><div class="err" id="a_photosErr"></div></section>';
    root.innerHTML = '<header class="ahead"><div class="alogo" role="img" aria-label="Al Insania"></div><div><h1>' + tx("title") + '</h1><p class="note">' + tx("org") + '</p></div><button class="btn btn-line btn-sm" type="button" id="alang">' + tx("lang") + "</button></header>" +
      '<p class="card ainfo">' + tx("intro") + "</p>" +
      '<form id="aform" novalidate autocomplete="off">' + groups + ph + '<p class="note ainfo2">' + tx("privacy") + '</p>' +
      '<div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" id="a_website" tabindex="-1" autocomplete="off"></label></div>' +
      '<div class="err aerr" id="a_formErr" role="alert"></div><div class="asend"><button class="btn btn-main" type="submit" id="asubmit">' + tx("send") + "</button></div></form>";
    Object.keys(vals).forEach(function (k) { var el = document.getElementById("a_" + k); if (el && vals[k] !== undefined) el.value = vals[k]; });
    photos();
    document.documentElement.lang = lang; document.documentElement.dir = lang === "ar" ? "rtl" : "ltr";
    document.title = tx("title") + " | Al Insania";
    document.getElementById("alang").onclick = function () { var v = keepValues(); lang = lang === "ar" ? "en" : "ar"; try { localStorage.setItem("acf_lang", lang); } catch (e) {} form(v); };
    document.getElementById("aform").onsubmit = submit;
    document.getElementById("addph").onclick = function () { if (files.length < MAXP) document.getElementById("phin").click(); };
    document.getElementById("phin").onchange = function (e) { var f = e.target.files[0]; e.target.value = ""; if (f && files.length < MAXP) { files.push(f); photos(); } };
  }
  function photos() {
    var box = document.getElementById("aph"); if (!box) return;
    box.innerHTML = files.map(function (f, i) { return '<div class="aph"><img alt="" src="' + URL.createObjectURL(f) + '"><button type="button" data-i="' + i + '" aria-label="' + tx("remove") + '">&times;</button></div>'; }).join("");
    Array.prototype.forEach.call(box.querySelectorAll("button"), function (b) { b.onclick = function () { files.splice(parseInt(b.getAttribute("data-i"), 10), 1); photos(); }; });
    document.getElementById("addph").style.display = files.length >= MAXP ? "none" : "";
  }

  function clearErrors() { Array.prototype.forEach.call(root.querySelectorAll(".err"), function (e) { e.textContent = ""; }); }
  function val(k) { var el = document.getElementById("a_" + k); return el ? el.value.trim() : ""; }
  function submit(e) {
    e.preventDefault(); if (sending) return; clearErrors();
    var bad = [];
    if (!val("name_en") && !val("name_ar")) bad.push(["name_en", tx("name_need")]);
    S.forEach(function (g) { g.fields.forEach(function (f) { if (f.req && !val(f.k)) bad.push([f.k, tx("need")]); }); });
    if (bad.length) { bad.forEach(function (b) { var er = document.getElementById("a_" + b[0] + "Err"); if (er) er.textContent = b[1]; }); document.getElementById("a_formErr").textContent = tx("fix"); var first = document.getElementById("a_" + bad[0][0]); if (first) first.scrollIntoView({ block: "center" }); return; }
    var fd = new FormData();
    S.forEach(function (g) { g.fields.forEach(function (f) { if (!f.staff && val(f.k) !== "") fd.append(f.k, val(f.k)); }); });
    fd.append("website", document.getElementById("a_website").value);
    files.forEach(function (f) { fd.append("photos[]", f); });
    var btn = document.getElementById("asubmit"); sending = true; btn.disabled = true; btn.textContent = tx("sending");
    fetch(API + "public/apply", { method: "POST", headers: { Accept: "application/json" }, body: fd, credentials: "omit" }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { r: r, j: j }; }); }).then(function (x) {
      sending = false; btn.disabled = false; btn.textContent = tx("send");
      if (x.r.status === 201 || x.r.ok) return done(x.j.data && x.j.data.reference);
      var er = document.getElementById("a_formErr");
      if (x.r.status === 429) { er.textContent = tx("busy"); return; }
      if (x.r.status === 503) { er.textContent = tx("closed"); return; }
      if (x.j && x.j.errors) {
        var shown = false; Object.keys(x.j.errors).forEach(function (k) { var key = k.replace(/\.\d+$/, "").replace(/\./g, "_"); var d = document.getElementById("a_" + key + "Err"); if (d) { d.textContent = x.j.errors[k][0]; shown = true; } });
        er.textContent = tx("fix"); var f1 = root.querySelector(".err:not(:empty)"); if (f1) f1.scrollIntoView({ block: "center" }); return;
      }
      er.textContent = (x.j && x.j.message) || tx("net");
    }, function () { sending = false; btn.disabled = false; btn.textContent = tx("send"); document.getElementById("a_formErr").textContent = tx("net"); });
  }
  function done(ref) {
    files = []; window.scrollTo(0, 0);
    root.innerHTML = '<header class="ahead"><div class="alogo" role="img" aria-label="Al Insania"></div><div><h1>' + tx("thanks") + "</h1></div></header>" +
      '<section class="card asec adone"><p>' + tx("ref") + '</p><div class="aref" dir="ltr">' + esc(ref || "") + "</div><p class=\"note\">" + tx("keep") + '</p><button class="btn btn-line" type="button" id="anew">' + tx("another") + "</button></section>";
    document.getElementById("anew").onclick = function () { form(); };
  }

  /* start: is the form open? */
  fetch(API + "public/apply", { headers: { Accept: "application/json" }, credentials: "omit" }).then(function (r) { return r.json(); }).then(function (j) {
    MAXP = (j.data && j.data.max_photos) || 2;
    if (j.data && j.data.open === false) { root.innerHTML = '<section class="card asec adone"><h1>' + tx("closed") + "</h1></section>"; return; }
    form();
  }, function () { form(); });
})();
