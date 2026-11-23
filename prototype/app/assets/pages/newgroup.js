(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, picked = {}, filter = "", tab = "staff", ALL = { staff: [], orphan: [], family: [] };

  A.strings({
    grp_title: "New group", grp_name: "Group name", grp_name_ph: "For example: Field team", grp_members: "Choose who is in the group", grp_none: "Choose at least one person.", grp_create: "Create group", grp_n: "{n} selected",
    grp_note: "You can change the name, add or remove people and set a cover photo and theme later, in the group's settings.", grp_search: "Search people", grp_back: "Back", grp_busy: "Creating…", grp_t_staff: "Staff", grp_t_orphan: "Orphans", grp_t_family: "Families", grp_k_orphan: "Orphan", grp_k_family: "Family", grp_guest_note: "Orphans and families see only this group: not the General chat, not private chats, not the records shared in the office."
  }, {
    grp_title: "مجموعة جديدة", grp_name: "اسم المجموعة", grp_name_ph: "مثال: فريق الميدان", grp_members: "اختر من في المجموعة", grp_none: "اختر شخصًا واحدًا على الأقل.", grp_create: "إنشاء المجموعة", grp_n: "تم اختيار {n}",
    grp_note: "يمكنك لاحقًا تغيير الاسم وإضافة الأشخاص أو إزالتهم وتعيين صورة الغلاف والمظهر من إعدادات المجموعة.", grp_search: "ابحث عن الأشخاص", grp_back: "رجوع", grp_busy: "جارٍ الإنشاء…", grp_t_staff: "الموظفون", grp_t_orphan: "الأيتام", grp_t_family: "الأسر", grp_k_orphan: "يتيم", grp_k_family: "أسرة", grp_guest_note: "الأيتام والأسر يرون هذه المجموعة فقط: لا المحادثة العامة ولا المحادثات الخاصة ولا السجلات المشاركة في المكتب."
  });

  function nrm(x) { return String(x || "").toLowerCase().replace(/[ً-ٟـ]/g, "").replace(/[آأإ]/g, "ا").replace(/ى/g, "ي").replace(/ة/g, "ه").trim(); }
  function list(people) {
    var f = nrm(filter);
    return people.filter(function (p) { return !f || nrm(p.name + " " + (p.username || "")).indexOf(f) > -1; }).map(function (p) {
      var on = !!picked[p.id];
      return '<button class="ios-row no-tile pick' + (on ? " on" : "") + '" type="button" data-act="pickp" data-id="' + p.id + '" role="checkbox" aria-checked="' + on + '">' + A.avatar(p, "avatar") + '<span class="grow"><b>' + esc(p.name) + '</b><span class="sub">' + esc((p.guest ? t("grp_k_" + p.kind) : p.role_label) + (p.department ? " · " + p.department : "")) + '</span></span><span class="tick">' + (on ? ic("check") : "") + "</span></button>";
    }).join("") || '<p class="note" style="padding:1rem">' + t("none") + "</p>";
  }
  var PEOPLE = [];
  function count() { return Object.keys(picked).length; }
  function tabs() {
    var n = function (k) { return ALL[k].filter(function (p) { return picked[p.id]; }).length; };
    return '<div class="filters ng-tabs" role="tablist">' + ["staff", "orphan", "family"].filter(function (k) { return k === "staff" || ALL[k].length; }).map(function (k) {
      return '<button class="fbtn" type="button" role="tab" data-act="ngtab" data-v="' + k + '" aria-pressed="' + (tab === k) + '">' + esc(t("grp_t_" + k)) + (n(k) ? " · " + n(k) : "") + "</button>"; }).join("") + "</div>" +
      (tab !== "staff" ? '<p class="note ng-gnote">' + esc(t("grp_guest_note")) + "</p>" : "");
  }
  function render(d) {
    ALL = d; var people = ALL[tab]; PEOPLE = people;
    return '<div class="dp-bar"><a class="dp-back" href="chat.html" aria-label="' + t("grp_back") + '">' + ic("back", "flip") + '</a><span class="dp-title">' + t("grp_title") + '</span><span class="dp-act ghost"></span></div>' +
      '<div class="addwrap"><form id="grpForm" novalidate><section class="fpage"><div><label for="g_title">' + t("grp_name") + '</label><input id="g_title" type="text" maxlength="60" placeholder="' + esc(t("grp_name_ph")) + '" autocomplete="off"><div class="err" id="g_titleErr"></div></div>' +
      '<p class="note" style="margin:.8rem 0 0">' + t("grp_note") + '</p></section>' +
      '<section class="dp-sec"><div class="ios-head"><h3>' + t("grp_members") + '</h3><span class="note" id="gCount">' + t("grp_n", { n: count() }) + '</span></div><div class="search" style="margin-bottom:.6rem">' + ic("search") + '<input id="gq" type="search" placeholder="' + esc(t("grp_search")) + '" aria-label="' + esc(t("grp_search")) + '" autocomplete="off"></div><div id="gTabs">' + tabs() + "</div>" +
      '<div class="ios-group" id="gList">' + list(people) + '</div><div class="err" id="g_membersErr"></div></section>' +
      '<div class="err" id="formErr" role="alert"></div><div class="actions addwrap-actions"><a class="btn btn-line" href="chat.html">' + t("cancel") + '</a><button class="btn btn-main" type="submit">' + t("grp_create") + "</button></div></form></div>";
  }
  A.act("pickp", function (el) {
    var id = el.getAttribute("data-id"); if (picked[id]) delete picked[id]; else picked[id] = 1;
    document.getElementById("gList").innerHTML = list(PEOPLE); document.getElementById("gCount").textContent = t("grp_n", { n: count() }); document.getElementById("gTabs").innerHTML = tabs();
  });
  A.act("ngtab", function (el) { tab = el.getAttribute("data-v"); PEOPLE = ALL[tab]; document.getElementById("gTabs").innerHTML = tabs(); document.getElementById("gList").innerHTML = list(PEOPLE); });
  A.form("grpForm", function () {
    Array.prototype.forEach.call(document.querySelectorAll(".addwrap .err"), function (e) { e.textContent = ""; });
    var title = document.getElementById("g_title").value.trim();
    if (title.length < 2) { document.getElementById("g_titleErr").textContent = t("required"); return; }
    if (!count()) { document.getElementById("g_membersErr").textContent = t("grp_none"); return; }
    var btn = document.querySelector("#grpForm button[type=submit]"); btn.disabled = true; btn.textContent = t("grp_busy");
    A.api.write("POST", "chat/groups", { title: title, user_ids: Object.keys(picked).map(Number) }, { online: true }).then(function (r) { location.replace("room.html?c=" + (r.data || r).id); }, function (e) {
      btn.disabled = false; btn.textContent = t("grp_create");
      var m = (e.errors && e.errors.user_ids && e.errors.user_ids[0]) || (e.errors && e.errors.title && e.errors.title[0]);
      document.getElementById("formErr").textContent = m || (e.offline ? t("need_conn") : A.msg(e));
    });
  });
  document.addEventListener("input", function (e) { if (e.target && e.target.id === "gq") { filter = e.target.value; document.getElementById("gList").innerHTML = list(PEOPLE); } });

  A.start({ page: "newgroup", nav: "chat", title: "grp_title", perm: "chat.use", needsConn: true, shell: true, bare: true, load: function () {   // staff, and the orphans and families who have a sign-in account
    return Promise.all([A.api.get("chat/people", { cache: false }), A.api.get("chat/people?guests=1", { cache: false }).catch(function () { return { data: [] }; })]).then(function (r) {
      var g = r[1].data || []; return { data: { staff: r[0].data, orphan: g.filter(function (p) { return p.kind === "orphan"; }), family: g.filter(function (p) { return p.kind === "family"; }) } };
    });
  }, render: render });
})();
