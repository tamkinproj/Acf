(function () {
  /* Files (President): every file uploaded in the offices, in folders with names people know. Open, save, download a folder,
     and download a backup of everything (files and records) to keep offline. Nothing here changes or deletes a file. */
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  A.strings({
    fm_title: "Files", fm_sub: "Everything uploaded in the offices", fm_total: "{n} files · {s}", fm_search: "Search all files", fm_root: "Files",
    fm_empty: "Nothing here yet.", fm_none: "No files match.", fm_count: "{n} files", fm_count1: "1 file", fm_by: "by {n}",
    fm_zip: "Download folder", fm_zip_all: "Download all", fm_sort_name: "Name", fm_sort_new: "Newest", fm_sort_big: "Largest",
    fm_open: "Open", fm_save: "Save", fm_saving: "Preparing…", fm_part: "Saving part {p} of {n}…", fm_saved: "Saved", fm_saved_parts: "Saved {n} ZIP files. Keep them together.",
    fm_bk_title: "Backup for offline", fm_bk_sub: "Every file and every record in one download, to keep on a computer or a USB drive.", fm_bk_btn: "Download backup",
    fm_bk_what: "What is inside", fm_bk_files: "files: every photo, document, voice message and form, in the same folders as here", fm_bk_data: "data: the records as spreadsheets (orphans, families, sponsors, payments, applications, staff, attendance, education, projects, activity)",
    fm_bk_not: "Not inside: passwords, sign-in keys, and private chats between two people.", fm_bk_safe: "Keep it safe: it holds personal details of orphans, families and staff. A computer is best: a large backup comes as several ZIP files.",
    fm_bk_pass: "Your password", fm_bk_pass_hint: "Type your own password to confirm.", fm_bk_go: "Download backup now", fm_bk_done: "Backup saved ({n} ZIP files). Keep them together in a safe place.", fm_bk_done1: "Backup saved. Keep it in a safe place.",
    fm_offline: "Opening and saving files needs a connection.", fm_where: "In {w}", fm_readonly: "Files that belong to a record (an orphan, an application) are changed on that record's page.",
    fs_orphans: "Orphans", fs_applications: "Applications", fs_chat: "Group chats", fs_attendance: "Attendance", fs_staff: "Staff photos", fs_forms: "Report forms", fs_website: "Website images"
  }, {
    fm_title: "الملفات", fm_sub: "كل ما رُفع في المكاتب", fm_total: "{n} ملفًا · {s}", fm_search: "ابحث في كل الملفات", fm_root: "الملفات",
    fm_empty: "لا شيء هنا بعد.", fm_none: "لا ملفات مطابقة.", fm_count: "{n} ملفًا", fm_count1: "ملف واحد", fm_by: "رفعه {n}",
    fm_zip: "تنزيل المجلد", fm_zip_all: "تنزيل الكل", fm_sort_name: "الاسم", fm_sort_new: "الأحدث", fm_sort_big: "الأكبر",
    fm_open: "فتح", fm_save: "حفظ", fm_saving: "جارٍ التجهيز…", fm_part: "جارٍ حفظ الجزء {p} من {n}…", fm_saved: "تم الحفظ", fm_saved_parts: "حُفظت {n} ملفات ZIP. احتفظ بها معًا.",
    fm_bk_title: "نسخة احتياطية للحفظ دون اتصال", fm_bk_sub: "كل الملفات وكل السجلات في تنزيل واحد، للحفظ على حاسوب أو ذاكرة USB.", fm_bk_btn: "تنزيل نسخة احتياطية",
    fm_bk_what: "ما بداخلها", fm_bk_files: "files: كل صورة ومستند ورسالة صوتية ونموذج، في نفس المجلدات هنا", fm_bk_data: "data: السجلات كجداول (الأيتام، الأسر، الكفلاء، المدفوعات، الطلبات، الموظفون، الحضور، التعليم، المشاريع، النشاط)",
    fm_bk_not: "ليس بداخلها: كلمات المرور، مفاتيح الدخول، والمحادثات الخاصة بين شخصين.", fm_bk_safe: "احفظها في مكان آمن: فيها بيانات شخصية للأيتام والأسر والموظفين. الحاسوب أفضل: النسخة الكبيرة تأتي في عدة ملفات ZIP.",
    fm_bk_pass: "كلمة المرور الخاصة بك", fm_bk_pass_hint: "اكتب كلمة مرورك للتأكيد.", fm_bk_go: "نزّل النسخة الآن", fm_bk_done: "حُفظت النسخة الاحتياطية ({n} ملفات ZIP). احتفظ بها معًا في مكان آمن.", fm_bk_done1: "حُفظت النسخة الاحتياطية. احتفظ بها في مكان آمن.",
    fm_offline: "فتح الملفات وحفظها يحتاج اتصالًا.", fm_where: "في {w}", fm_readonly: "الملفات التابعة لسجل (يتيم، طلب) تُغيَّر من صفحة ذلك السجل.",
    fs_orphans: "الأيتام", fs_applications: "الطلبات", fs_chat: "المحادثات الجماعية", fs_attendance: "الحضور", fs_staff: "صور الموظفين", fs_forms: "نماذج التقارير", fs_website: "صور الموقع"
  });
  var SECTION_ICON = { orphans: "users", applications: "file", chat: "chat", attendance: "clock", staff: "users", forms: "file", website: "globe" };
  var FIXED_AR = { "Face photos": "صور الوجوه", "No partner": "بدون شريك", "General": "العام" };
  var KIND_ICON = { pdf: "file", audio: "mic", other: "file", image: "camera" };
  var folder = "", q = "", sort = "name", THUMBS = {}, LAST = null, busy = false, observer = null;

  function readUrl() { var u = new URLSearchParams(location.search); folder = (u.get("f") || "").replace(/[^a-z0-9_\/-]/g, ""); q = u.get("q") || ""; }
  function size(b) { return b < 1024 ? b + " B" : b < 1048576 ? Math.round(b / 1024) + " KB" : b < 1073741824 ? (b / 1048576).toFixed(b < 10485760 ? 1 : 0) + " MB" : (b / 1073741824).toFixed(1) + " GB"; }
  function count(n) { return n === 1 ? t("fm_count1") : t("fm_count", { n: n }); }
  function label(id, name, depth) {
    if (depth === 0 && t("fs_" + id) !== "fs_" + id) return t("fs_" + id);
    return A.lang() === "ar" && FIXED_AR[name] ? FIXED_AR[name] : name;
  }
  function href(f, qq) { var p = []; if (f) p.push("f=" + encodeURIComponent(f).replace(/%2F/g, "/")); if (qq) p.push("q=" + encodeURIComponent(qq)); return "files.html" + (p.length ? "?" + p.join("&") : ""); }
  function go(f, qq) { folder = f || ""; q = qq || ""; try { history.pushState(null, "", href(folder, q)); } catch (e) {} A.reload(false); }
  window.addEventListener("popstate", function () { readUrl(); A.reload(false); });

  function crumbs(d) {
    var out = '<nav class="fm-crumbs" aria-label="' + esc(t("fm_root")) + '"><a href="files.html" data-act="fmgo" data-f="">' + ic("box") + "<span>" + esc(t("fm_root")) + "</span></a>", path = [];
    (d.crumbs || []).forEach(function (c, i) {
      path.push(c.id); var last = i === d.crumbs.length - 1, nm = esc(label(c.id, c.name, i));
      out += '<span class="fm-sep" aria-hidden="true">›</span>' + (last ? '<b aria-current="page">' + nm + "</b>" : '<a href="' + href(path.join("/")) + '" data-act="fmgo" data-f="' + esc(path.join("/")) + '">' + nm + "</a>");
    });
    return out + "</nav>";
  }
  function folderRow(f, depth, base) {
    var path = (base ? base + "/" : "") + f.id, sub = f.count ? count(f.count) + " · " + size(f.size) + (f.at_iso ? " · " + A.dateOnly(f.at_iso) : "") : t("fm_empty");
    return '<a class="item fm-folder" href="' + href(path) + '" data-act="fmgo" data-f="' + esc(path) + '"><span class="avatar fm-fi' + (depth === 0 ? " sec" : "") + '">' + ic(depth === 0 ? SECTION_ICON[f.id] || "folder" : "folder") + '</span><span class="grow"><b dir="auto">' + esc(label(f.id, f.name, depth)) + '</b><span class="sub">' + esc(sub) + "</span></span>" + ic("chev", "flip") + "</a>";
  }
  function fileRow(f, where) {
    var meta = size(f.size) + " · " + A.dateOnly(f.at) + (f.by ? " · " + t("fm_by", { n: f.by }) : "");
    var thumb = f.kind === "image" ? '<span class="fm-th" data-thumb="' + esc(f.ref) + '"></span>' : '<span class="avatar fm-fi k-' + f.kind + '">' + ic(KIND_ICON[f.kind] || "file") + "</span>";
    return '<div class="item fm-file"><button type="button" class="fm-open" data-act="fmopen" data-ref="' + esc(f.ref) + '">' + thumb + '<span class="grow"><b dir="auto">' + esc(f.name) + '</b><span class="sub">' + esc(meta) + "</span>" +
      (where && f.where ? '<span class="sub fm-in" dir="auto">' + esc(t("fm_where", { w: f.where })) + "</span>" : "") + "</span></button>" +
      '<button type="button" class="dp-act fm-dl" data-act="fmsave" data-ref="' + esc(f.ref) + '" aria-label="' + esc(t("fm_save") + " " + f.name) + '">' + ic("download") + "</button></div>";
  }
  function sorted(files) {
    var a = files.slice();
    if (sort === "new") a.sort(function (x, y) { return x.at < y.at ? 1 : x.at > y.at ? -1 : 0; });
    else if (sort === "big") a.sort(function (x, y) { return y.size - x.size; });
    return a;
  }
  function render(d) {
    LAST = d;
    var head = '<div class="head"><h1 class="title">' + esc(t("fm_title")) + "</h1></div>";
    var search = '<form class="search fm-search" id="fmSearch" role="search">' + ic("search") + '<input id="fmQ" type="search" placeholder="' + esc(t("fm_search")) + '" value="' + esc(q) + '" aria-label="' + esc(t("fm_search")) + '" autocomplete="off"></form>';
    if (d.q !== undefined) {
      return head + search + crumbs({ crumbs: [] }) + '<div class="list fm-list">' + (d.files.length ? d.files.map(function (f) { return fileRow(f, true); }).join("") : '<p class="note">' + esc(t("fm_none")) + "</p>") + "</div>";
    }
    var root = !d.folder, depth = d.crumbs ? d.crumbs.length : 0;
    var top = root ? '<p class="note fm-total">' + esc(t("fm_sub")) + " · " + esc(t("fm_total", { n: d.total.count, s: size(d.total.size) })) + "</p>" +
      '<div class="card fm-backup"><span class="avatar fm-bk-i">' + ic("download") + '</span><div class="grow"><b>' + esc(t("fm_bk_title")) + '</b><p class="sub">' + esc(t("fm_bk_sub")) + '</p></div><button type="button" class="btn btn-main" data-act="fmbackup">' + esc(t("fm_bk_btn")) + "</button></div>" : "";
    var tools = "";
    var any = d.files.length || d.folders.some(function (f) { return f.count; });
    if (!root && any) tools = '<div class="fm-tools"><button type="button" class="btn btn-line fm-zip" data-act="fmzip">' + ic("download") + "<span>" + esc(t("fm_zip")) + "</span></button>" +
      (d.files.length > 1 ? '<div class="filters fm-sort" role="group">' + ["name", "new", "big"].map(function (k) { return '<button class="fbtn" type="button" data-act="fmsort" data-v="' + k + '" aria-pressed="' + (sort === k) + '">' + esc(t("fm_sort_" + (k === "new" ? "new" : k === "big" ? "big" : "name"))) + "</button>"; }).join("") + "</div>" : "") + "</div>";
    var folders = d.folders.length ? '<div class="list fm-list">' + d.folders.map(function (f) { return folderRow(f, depth, d.folder); }).join("") + "</div>" : "";
    var files = d.files.length ? '<div class="list fm-list fm-files">' + sorted(d.files).map(function (f) { return fileRow(f); }).join("") + "</div>" : "";
    var empty = !folders && !files ? '<p class="note">' + esc(t("fm_empty")) + "</p>" : "";
    return head + top + search + (root ? "" : crumbs(d)) + tools + folders + files + empty + (root ? '<p class="note fm-ro">' + esc(t("fm_readonly")) + "</p>" : "");
  }

  /* photos show small pictures as they come into view (one at a time, not the whole folder at once) */
  function thumbs() {
    if (observer) observer.disconnect();
    var els = document.querySelectorAll("[data-thumb]"); if (!els.length) return;
    var show = function (el) {
      var ref = el.getAttribute("data-thumb"); if (el.style.backgroundImage) return;
      var put = function (u) { el.style.backgroundImage = 'url("' + u + '")'; el.classList.add("on"); };
      if (THUMBS[ref]) { put(THUMBS[ref]); return; }
      A.api.blobUrl("files/file?ref=" + encodeURIComponent(ref)).then(function (u) { THUMBS[ref] = u; put(u); }, function () {});
    };
    if (!("IntersectionObserver" in window)) { Array.prototype.forEach.call(els, show); return; }
    observer = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { observer.unobserve(e.target); show(e.target); } }); }, { rootMargin: "200px" });
    Array.prototype.forEach.call(els, function (el) { observer.observe(el); });
  }
  function after() {
    thumbs();
    var f = document.getElementById("fmSearch");
    if (f) f.addEventListener("submit", function (e) { e.preventDefault(); var v = document.getElementById("fmQ").value.trim(); go(v.length >= 2 ? "" : folder, v.length >= 2 ? v : ""); });
    var inp = document.getElementById("fmQ");
    if (inp) inp.addEventListener("search", function () { if (!inp.value && q) go("", ""); });
  }

  function save(blob, headers, fallback) {
    // the server sends the name with the Arabic in it (filename*=UTF-8''...), and a plain one for old programs
    var cd = (headers && headers.get("Content-Disposition")) || "", u = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i), m = cd.match(/filename\s*=\s*"?([^";]+)"?/i), name = fallback;
    if (u) { try { name = decodeURIComponent(u[1].trim()); } catch (e) { name = m ? m[1] : fallback; } } else if (m) name = m[1];
    var url = URL.createObjectURL(blob), a = document.createElement("a"); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    return name;
  }
  function find(ref) {
    var all = (LAST && LAST.files) || []; for (var i = 0; i < all.length; i++) if (all[i].ref === ref) return all[i]; return null;
  }
  /* parts one after another: a big folder or backup comes as several ZIP files */
  function parts(path, body, onPart) {
    var n = 1, saved = 0;
    var one = function (p) {
      onPart(p, n);
      return A.api.file(path + (path.indexOf("?") < 0 ? "?" : "&") + "part=" + p, body).then(function (r) {
        n = parseInt(r.headers.get("X-Parts"), 10) || 1; save(r.blob, r.headers, "files.zip"); saved++;
        return p < n ? new Promise(function (ok) { setTimeout(ok, 900); }).then(function () { return one(p + 1); }) : saved;
      });
    };
    return one(1);
  }

  A.act("fmgo", function (el, e) { if (e) e.preventDefault(); go(el.getAttribute("data-f") || "", ""); });
  A.act("fmsort", function (el) { sort = el.getAttribute("data-v"); A.paint(); });
  A.act("fmsave", function (el) {
    var ref = el.getAttribute("data-ref"), f = find(ref);
    if (navigator.onLine === false) { A.toast(t("fm_offline")); return; }
    el.disabled = true;
    A.api.file("files/file?ref=" + encodeURIComponent(ref) + "&download=1").then(function (r) { el.disabled = false; save(r.blob, r.headers, f ? f.name : "file"); A.toast(t("fm_saved")); }, function (er) { el.disabled = false; A.toast(A.msg(er)); });
  });
  A.act("fmopen", function (el) {
    var ref = el.getAttribute("data-ref"), f = find(ref) || { name: "", kind: "other", ref: ref };
    if (navigator.onLine === false && !THUMBS[ref]) { A.toast(t("fm_offline")); return; }
    var meta = size(f.size || 0) + (f.at ? " · " + A.dateOnly(f.at) : "") + (f.by ? " · " + t("fm_by", { n: f.by }) : "");
    A.sheet(f.name, '<div class="fm-prev" id="fmPrev"><p class="note">' + esc(t("loading")) + "</p></div>" + '<p class="note" dir="auto">' + esc(meta) + (f.where ? "<br>" + esc(t("fm_where", { w: f.where })) : "") + "</p>" +
      '<div class="actions"><button type="button" class="btn btn-main" data-act="fmsave" data-ref="' + esc(ref) + '">' + ic("download") + " " + esc(t("fm_save")) + "</button>" +
      (f.kind === "pdf" ? '<button type="button" class="btn btn-line" data-act="fmtab" data-ref="' + esc(ref) + '">' + esc(t("fm_open")) + "</button>" : "") + "</div>");
    if (f.kind === "other") { document.getElementById("fmPrev").innerHTML = '<span class="avatar fm-fi big">' + ic("file") + "</span>"; return; }
    var got = THUMBS[ref] ? Promise.resolve(THUMBS[ref]) : A.api.blobUrl("files/file?ref=" + encodeURIComponent(ref)).then(function (u) { THUMBS[ref] = u; return u; });
    got.then(function (u) {
      var box = document.getElementById("fmPrev"); if (!box) return;
      if (f.kind === "image") box.innerHTML = '<img alt="" src="' + esc(u) + '">';
      else if (f.kind === "audio") box.innerHTML = '<audio controls preload="metadata" src="' + esc(u) + '"></audio>';
      else box.innerHTML = '<span class="avatar fm-fi big k-pdf">' + ic("file") + "</span>";
    }, function () { var box = document.getElementById("fmPrev"); if (box) box.innerHTML = '<p class="note">' + esc(t("fm_offline")) + "</p>"; });
  });
  A.act("fmtab", function (el) {
    var ref = el.getAttribute("data-ref"), w = window.open("", "_blank");
    var go2 = function (u) { if (w) w.location.href = u; else window.open(u, "_blank", "noopener"); };
    if (THUMBS[ref]) { go2(THUMBS[ref]); return; }
    A.api.blobUrl("files/file?ref=" + encodeURIComponent(ref)).then(function (u) { THUMBS[ref] = u; go2(u); }, function () { if (w) w.close(); A.toast(t("fm_offline")); });
  });
  A.act("fmzip", function (el) {
    if (busy) return; if (navigator.onLine === false) { A.toast(t("fm_offline")); return; }
    busy = true; el.disabled = true; var span = el.querySelector("span"), was = span.textContent;
    parts("files/zip?folder=" + encodeURIComponent(folder).replace(/%2F/g, "/"), null, function (p, n) { span.textContent = n > 1 ? t("fm_part", { p: p, n: n }) : t("fm_saving"); }).then(function (n) {
      busy = false; el.disabled = false; span.textContent = was; A.toast(n > 1 ? t("fm_saved_parts", { n: n }) : t("fm_saved"));
    }, function (er) { busy = false; el.disabled = false; span.textContent = was; A.toast(A.msg(er)); });
  });
  A.act("fmbackup", function () {
    A.sheet(t("fm_bk_title"), '<form id="fmBk" novalidate><p class="note">' + esc(t("fm_bk_sub")) + '</p><h4 class="optlbl">' + esc(t("fm_bk_what")) + '</h4><ul class="fm-bk-list"><li dir="auto">' + esc(t("fm_bk_files")) + '</li><li dir="auto">' + esc(t("fm_bk_data")) + "</li></ul>" +
      '<p class="note">' + esc(t("fm_bk_not")) + '</p><p class="banner warn">' + esc(t("fm_bk_safe")) + "</p>" +
      A.field("fmPass", esc(t("fm_bk_pass")), '<p class="note">' + esc(t("fm_bk_pass_hint")) + "</p>", "password", 'autocomplete="current-password" name="confirm_password"') +
      '<div class="err" id="fmBkErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit" id="fmBkGo">' + ic("download") + " " + esc(t("fm_bk_go")) + '</button><button class="btn btn-line" type="button" data-act="close">' + esc(t("cancel")) + "</button></div></form>");
  });
  A.form("fmBk", function (form) {
    var pass = document.getElementById("fmPass").value, err = document.getElementById("fmBkErr"), btn = document.getElementById("fmBkGo");
    if (busy) return; err.textContent = "";
    if (!pass) { err.textContent = t("fm_bk_pass_hint"); return; }
    if (navigator.onLine === false) { err.textContent = t("fm_offline"); return; }
    busy = true; btn.disabled = true;
    parts("files/backup", { confirm_password: pass }, function (p, n) { btn.textContent = n > 1 ? t("fm_part", { p: p, n: n }) : t("fm_saving"); }).then(function (n) {
      busy = false; A.closeSheet(); A.toast(n > 1 ? t("fm_bk_done", { n: n }) : t("fm_bk_done1"));
    }, function (er) { busy = false; btn.disabled = false; btn.innerHTML = ic("download") + " " + esc(t("fm_bk_go")); err.textContent = A.msg(er); });
  });

  readUrl();
  A.start({ page: "files", nav: "files", title: "n_files", perm: "files.all", shell: true, skel: "list",
    load: function () { return A.api.get(q.length >= 2 ? "files?q=" + encodeURIComponent(q) : "files" + (folder ? "?folder=" + encodeURIComponent(folder).replace(/%2F/g, "/") : "")); },
    render: render, after: after });
})();
