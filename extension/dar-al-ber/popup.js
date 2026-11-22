const $ = (id) => document.getElementById(id);
const send = (m) => new Promise((res) => chrome.runtime.sendMessage(m, (r) => res(r || { ok: false, error: "No answer from the extension." })));
let timer = 0;
function err(t) { $("err").textContent = t || ""; }
function when(ts) { return ts ? new Date(ts).toLocaleString() : ""; }
function showActive(a, synced) {
  $("active").innerHTML = a ? "<b></b><small></small>" : "No orphan chosen yet.";
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
  $("vLogin").hidden = s.signedIn; $("vMain").hidden = !s.signedIn; $("base").value = s.base;
  $("who").textContent = s.signedIn ? "Signed in" + (s.user && s.user.name ? " as " + s.user.name : "") : "Not signed in";
  if (s.signedIn) { showActive(s.active, s.synced); load(""); }
}
$("go").onclick = async () => {
  err(""); const base = $("base").value.trim().replace(/\/+$/, "");
  try { const u = new URL(base); const origin = u.origin + "/*";
    if (!/^https:\/\/(www\.)?alinsania\.com$/.test(u.origin) && !(await chrome.permissions.request({ origins: [origin] }))) return err("Allow the extension to reach that address.");
  } catch (e) { return err("The server address is not valid."); }
  $("go").disabled = true;
  const r = await send({ type: "login", base, login: $("login").value.trim(), password: $("pass").value });
  $("go").disabled = false; $("pass").value = "";
  if (!r.ok) return err(r.error); render();
};
$("pass").onkeydown = (e) => { if (e.key === "Enter") $("go").click(); };
$("out").onclick = async () => { await send({ type: "logout" }); render(); };
$("sync").onclick = async () => { err(""); $("sync").disabled = true; const r = await send({ type: "sync" }); $("sync").disabled = false; if (!r.ok && !r.code) { /* the chosen one may be approved already */ } else if (!r.ok) return handle(r); if (r.ok && r.data) showActive(r.data, Date.now()); load($("q").value.trim()); };
$("q").oninput = () => { clearTimeout(timer); timer = setTimeout(() => load($("q").value.trim()), 350); };
render();
