const $ = (id) => document.getElementById(id);
const send = (m) => new Promise((res) => chrome.runtime.sendMessage(m, (r) => res(r || { ok: false, error: "No answer from the extension." })));
let timer = 0;
function err(t) { $("err").textContent = t || ""; }
function when(ts) { return ts ? new Date(ts).toLocaleString() : ""; }
/* The pictures and papers the applicant uploaded, as small previews; tap one to open it large in a new tab. */
const pics = {};
function dataUrl(d) { return "data:" + d.type + ";base64," + d.b64; }
function openLarge(d) {
  const bin = atob(d.b64), u = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
  window.open(URL.createObjectURL(new Blob([u], { type: d.type })), "_blank");
}
async function showGallery(a) {
  const box = $("galBox"), g = $("gal"); g.textContent = "";
  const list = (a && a.gallery) || []; box.hidden = !a;
  if (a && !list.length) { g.innerHTML = '<p class="note">Nothing was uploaded with this application.</p>'; return; }
  const cells = list.map((x) => {
    const c = document.createElement("button"); c.type = "button"; c.className = "thumb"; c.title = x.label;
    c.innerHTML = '<span class="ph"></span><small></small>'; c.lastChild.textContent = x.label; g.appendChild(c); return c;
  });
  let next = 0;
  const worker = async () => {
    while (next < list.length) {
      const i = next++, x = list[i], c = cells[i];
      let d = pics[x.path]; if (!d) { const r = await send({ type: "file", path: x.path }); if (!r.ok) { c.classList.add("bad"); c.firstChild.textContent = "!"; c.title = x.label + ": " + r.error; continue; } d = pics[x.path] = r.data; }
      c.firstChild.style.backgroundImage = 'url("' + dataUrl(d) + '")'; c.onclick = () => openLarge(d);
    }
  };
  await Promise.all([worker(), worker(), worker()]);
}
function showActive(a, synced) {
  $("active").innerHTML = a ? "<b></b><small></small>" : "No orphan chosen yet.";
  showGallery(a);
  if (a) { $("active").firstChild.textContent = (a.name_ar || a.name_en) + " · " + (a.ref || a.external_no); $("active").lastChild.textContent = (a.name_en || "") + " · synced " + when(synced); }
}
async function load(q) {
  const r = await send({ type: "list", q }); if (!r.ok) return handle(r);
  $("list").textContent = "";
  if (!r.data.length) { $("list").innerHTML = '<p class="note">' + (q ? "No new application matches." : "No new applications waiting.") + "</p>"; return; }
  r.data.forEach((c) => {
    const b = document.createElement("button"); b.className = "item"; b.type = "button";
    b.textContent = (c.name_ar || c.name_en || c.reference);
    const s = document.createElement("small"); s.textContent = [c.reference, c.name_ar ? c.name_en : "", c.phone, c.when ? new Date(c.when).toLocaleDateString() : ""].filter(Boolean).join(" · "); b.appendChild(s);
    b.onclick = async () => { b.disabled = true; const x = await send({ type: "choose", id: c.id }); b.disabled = false; if (!x.ok) return handle(x); err(""); showActive(x.data, Date.now()); };
    $("list").appendChild(b);
  });
}
function handle(r) { if (r.code === "AUTH") return render(); err(r.error); }
async function render() {
  const r = await send({ type: "state" }); const s = r.data;
  $("vLogin").hidden = s.signedIn; $("vMain").hidden = !s.signedIn;
  $("who").textContent = s.signedIn ? "Signed in" + (s.user && s.user.name ? " as " + s.user.name : "") : "Not signed in";
  if (s.signedIn) { showActive(s.active, s.synced); load(""); }
}
$("go").onclick = async () => {
  err(""); $("go").disabled = true;
  const r = await send({ type: "login", login: $("login").value.trim(), password: $("pass").value });
  $("go").disabled = false; $("pass").value = "";
  if (!r.ok) return err(r.error); render();
};
$("pass").onkeydown = (e) => { if (e.key === "Enter") $("go").click(); };
$("out").onclick = async () => { await send({ type: "logout" }); render(); };
$("sync").onclick = async () => { err(""); $("sync").disabled = true; const r = await send({ type: "sync" }); $("sync").disabled = false; if (!r.ok && !r.code) { /* the chosen one may be approved already */ } else if (!r.ok) return handle(r); if (r.ok && r.data) showActive(r.data, Date.now()); load($("q").value.trim()); };
$("q").oninput = () => { clearTimeout(timer); timer = setTimeout(() => load($("q").value.trim()), 350); };
render();
