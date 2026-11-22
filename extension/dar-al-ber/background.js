/* Talks to the ACF system for the popup and the portal page. The sign-in token stays here, in the extension's own storage. */
const DEFAULT_BASE = "https://alinsania.com";
const S = { get: (k) => chrome.storage.local.get(k), set: (o) => chrome.storage.local.set(o), del: (k) => chrome.storage.local.remove(k) };
const apiUrl = (base) => String(base || DEFAULT_BASE).replace(/\/+$/, "") + "/server/api/";

async function call(path, opt) {
  opt = opt || {};
  const s = await S.get(["base", "token", "device"]);
  const login = path === "login";
  if (!login && !s.token) throw { code: "AUTH", message: "Sign in first." };
  const h = { Accept: "application/json" };
  if (s.token && !login) h.Authorization = "Bearer " + s.token;
  if (s.device) h["X-Device-Token"] = s.device;
  if (opt.body) h["Content-Type"] = "application/json";
  let r;
  try { r = await fetch(apiUrl(s.base) + path, { method: opt.method || "GET", headers: h, body: opt.body ? JSON.stringify(opt.body) : undefined }); }
  catch (e) { throw { message: "Cannot reach the system. Check the internet and the server address." }; }
  if (r.status === 401 && !login) { await S.del(["token", "user"]); throw { code: "AUTH", message: "Your sign-in ended. Sign in again." }; }
  if (opt.binary) {
    if (!r.ok) throw { message: "The file is not available (" + r.status + ")." };
    const buf = new Uint8Array(await r.arrayBuffer());
    let bin = ""; for (let i = 0; i < buf.length; i += 0x8000) bin += String.fromCharCode.apply(null, buf.subarray(i, i + 0x8000));
    return { type: (r.headers.get("content-type") || "application/octet-stream").split(";")[0], b64: btoa(bin) };
  }
  let j = null; try { j = await r.json(); } catch (e) {}
  if (!r.ok || !j || j.success === false) {
    const first = j && j.errors ? Object.values(j.errors)[0] : null;
    throw { message: (first && first[0]) || (j && j.message) || (r.status === 429 ? "Too many requests (429). Wait a minute and try again." : "The system answered with an error (" + r.status + ").") };
  }
  return j;
}

const handlers = {
  async state() { const s = await S.get(["base", "token", "user", "active", "synced"]); return { base: s.base || DEFAULT_BASE, signedIn: !!s.token, user: s.user || null, active: s.active || null, synced: s.synced || null }; },
  async login(m) {
    const base = String(m.base || DEFAULT_BASE).trim().replace(/\/+$/, "");
    await S.set({ base }); await S.del(["device"]);
    const j = await call("login", { method: "POST", body: { login: m.login, password: m.password, device_name: "Chrome extension", trust_device: true } });
    await S.set({ token: j.data.token, user: { name: j.data.user && (j.data.user.name || j.data.user.username), role: j.data.user && j.data.user.role }, device: j.data.device_token || "" });
    return true;
  },
  async logout() { try { await call("logout", { method: "POST" }); } catch (e) {} await S.del(["token", "user", "device", "active", "synced"]); return true; },
  async list(m) { const j = await call("children?per_page=30&q=" + encodeURIComponent(m.q || "")); return j.data; },
  async choose(m) { const j = await call("children/" + encodeURIComponent(m.id)); await S.set({ active: j.data, synced: Date.now() }); return j.data; },
  async sync() { const s = await S.get(["active"]); if (!s.active) return null; const j = await call("children/" + s.active.id); await S.set({ active: j.data, synced: Date.now() }); return j.data; },
  async file(m) { return call(String(m.path), { binary: true }); },
};

chrome.runtime.onMessage.addListener((m, _s, reply) => {
  const f = handlers[m && m.type];
  if (!f) return false;
  f(m).then((data) => reply({ ok: true, data }), (e) => reply({ ok: false, error: (e && e.message) || "Something went wrong.", code: e && e.code }));
  return true;
});
