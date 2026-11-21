/* The app's connection to the server, built to keep working with no signal.

   READS   try the server; if there is no connection, show the copy saved on this phone (marked "stale").
   WRITES  try the server; if there is no connection, save the change in the OUTBOX on this phone and show it
           straight away. The outbox is sent, in order, as soon as the connection is back. Every change carries a
           one-time key (Idempotency-Key), so a change sent twice by accident is still done once on the server.
   SESSION stays on this phone after the first sign-in, so the app opens offline. Only signing in needs internet.

   Rules of safety: account administration, passwords and partner keys are never queued (see opts.online). */
(function () {
  "use strict";
  var SK = "acf_session", OB = "acf_outbox", CP = "acf_cache:", META = "acf_meta";
  var listeners = [], syncing = false, timer = null;

  function base() {
    try { var b = localStorage.getItem("acf_api_base"); if (b) return b.replace(/\/?$/, "/"); } catch (e) {}
    return new URL("../server/api/", location.href).href; // the app lives in /app/, the server in /server/
  }
  function rd(k, d) { try { var v = JSON.parse(localStorage.getItem(k)); return v === null || v === undefined ? d : v; } catch (e) { return d; } }
  function wr(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } }
  function del(k) { try { localStorage.removeItem(k); } catch (e) {} }
  function uid() {
    var a = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.prototype.map.call(a, function (b) { return ("0" + b.toString(16)).slice(-2); }).join("");
  }

  /* ---------- session ---------- */
  function session() { return rd(SK, null); }
  function user() { var s = session(); return s && s.user; }
  function can(p) { var u = user(); return !!(u && u.permissions && u.permissions.indexOf(p) > -1); }
  function setSession(token, u) {
    var prev = session();
    // A different person on the same phone must never see the previous person's saved data or unsent changes.
    if (prev && prev.user && prev.user.id !== u.id) { wipeCache(); del(OB); }
    wr(SK, { token: token, user: u, at: Date.now() });
  }
  function updateUser(u) { var s = session(); if (s) { s.user = u; wr(SK, s); } }

  /* ---------- cache ---------- */
  function ckey(path) { var u = user(); return CP + (u ? u.id : 0) + ":" + path; }
  function putCache(path, payload) { wr(ckey(path), { at: Date.now(), payload: payload }); }
  function getCache(path) { return rd(ckey(path), null); }
  function wipeCache() {
    try { Object.keys(localStorage).forEach(function (k) { if (k.indexOf(CP) === 0) localStorage.removeItem(k); }); } catch (e) {}
  }
  /** Changes every saved copy whose address starts with `prefix`, so a queued change shows on screen at once. */
  function patchCache(prefix, fn) {
    var u = user(); if (!u) return;
    var start = CP + u.id + ":" + prefix;
    try {
      Object.keys(localStorage).forEach(function (k) {
        if (k.indexOf(start) !== 0) return;
        var c = rd(k, null); if (!c) return;
        var out = fn(c.payload, k.slice((CP + u.id + ":").length));
        if (out !== undefined) { c.payload = out; wr(k, c); }
      });
    } catch (e) {}
  }

  /* ---------- outbox ---------- */
  function outbox() { return rd(OB, []); }
  function mine() { var u = user(); return outbox().filter(function (o) { return u && o.userId === u.id; }); }
  function saveOutbox(a) { wr(OB, a); }
  function counts() {
    var m = mine(), failed = m.filter(function (o) { return o.status === "failed"; }).length;
    return { waiting: m.length - failed, failed: failed, total: m.length };
  }
  function state() {
    var c = counts(), meta = rd(META, {});
    return { online: navigator.onLine !== false, waiting: c.waiting, failed: c.failed, syncing: syncing, lastSync: meta.lastSync || null, expired: !!meta.expired };
  }
  function emit(extra) { var s = state(); if (extra) for (var k in extra) s[k] = extra[k]; listeners.forEach(function (f) { try { f(s); } catch (e) {} }); }
  function onChange(f) { listeners.push(f); }
  function setMeta(o) { var m = rd(META, {}); for (var k in o) m[k] = o[k]; wr(META, m); }

  /* ---------- network ---------- */
  function request(method, path, body, extra) {
    var s = session(), ctl = window.AbortController ? new AbortController() : null;
    var to = setTimeout(function () { if (ctl) ctl.abort(); }, (extra && extra.timeout) || 9000);
    var headers = { Accept: "application/json" };
    if (s && s.token) headers.Authorization = "Bearer " + s.token;
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (extra && extra.key) headers["Idempotency-Key"] = extra.key;
    return fetch(base() + path, { method: method, headers: headers, body: body === undefined ? undefined : JSON.stringify(body), signal: ctl ? ctl.signal : undefined, credentials: "omit" })
      .then(function (r) {
        clearTimeout(to);
        return r.text().then(function (txt) {
          var j = null; try { j = JSON.parse(txt); } catch (e) {}
          if (r.status === 401 && s) { setMeta({ expired: true }); emit(); }
          return { status: r.status, ok: r.ok, json: j, text: txt };
        });
      }, function (e) { clearTimeout(to); var err = new Error("network"); err.network = true; throw err; });
  }
  function message(res) {
    var j = res && res.json;
    if (j && j.errors) { var f = Object.keys(j.errors)[0]; if (f && j.errors[f] && j.errors[f][0]) return j.errors[f][0]; }
    return (j && j.message) || "Something went wrong.";
  }
  function fail(res) { var e = new Error(message(res)); e.status = res.status; e.code = res.json && res.json.code; e.errors = res.json && res.json.errors; return e; }

  /* ---------- public: sign in / out ---------- */
  function login(email, password) {
    return request("POST", "login", { email: email, password: password, device_name: "staff-app" }).then(function (res) {
      if (!res.ok) throw fail(res);
      setSession(res.json.data.token, res.json.data.user); setMeta({ expired: false });
      return res.json.data.user;
    });
  }
  function logout() {
    var s = session();
    var p = s && navigator.onLine !== false ? request("POST", "logout", undefined, { timeout: 4000 }).catch(function () {}) : Promise.resolve();
    return p.then(function () { del(SK); wipeCache(); setMeta({ expired: false }); });
  }
  /** Refreshes who I am and what I may do (a role may have changed). Quiet when offline. */
  function refreshMe() {
    if (!session() || navigator.onLine === false) return Promise.resolve(null);
    return request("GET", "me").then(function (res) { if (res.ok) { updateUser(res.json.data); setMeta({ expired: false }); return res.json.data; } return null; }, function () { return null; });
  }

  /* ---------- public: read ---------- */
  /** Resolves { data, meta, stale, at }. opts.cache=false for sensitive screens that must never be saved on the phone. */
  function get(path, opts) {
    opts = opts || {};
    var useCache = opts.cache !== false;
    if (navigator.onLine === false && !opts.forceNet) return new Promise(function (res, rej) { try { res(fromCache()); } catch (e) { rej(e); } });
    return request("GET", path).then(function (res) {
      if (res.ok && res.json) {
        var p = { data: res.json.data, meta: res.json.meta || {} };
        if (useCache) putCache(path, p);
        return { data: p.data, meta: p.meta, stale: false, at: Date.now() };
      }
      throw fail(res);
    }, function (e) { if (e.network) return fromCache(e); throw e; });
    function fromCache(e) {
      var c = useCache ? getCache(path) : null;
      if (c) return { data: c.payload.data, meta: c.payload.meta, stale: true, at: c.at };
      var err = new Error("offline"); err.offline = true; throw err;
    }
  }
  /** All pages of a list, up to a safe limit. */
  function getAll(path, opts) {
    var sep = path.indexOf("?") > -1 ? "&" : "?";
    return get(path + sep + "per_page=100", opts);
  }

  /** Every page of a list, merged into one result (stale if any page came from the phone). */
  function getEvery(path, opts) {
    var sep = path.indexOf("?") > -1 ? "&" : "?", first = path + sep + "per_page=100";
    return get(first, opts).then(function (r) {
      var last = (r.meta && r.meta.last_page) || 1, rest = [];
      for (var p = 2; p <= last && p <= 30; p++) rest.push(get(first + "&page=" + p, opts));
      return Promise.all(rest).then(function (more) {
        var data = r.data.slice(), stale = r.stale;
        more.forEach(function (m) { data = data.concat(m.data); stale = stale || m.stale; });
        return { data: data, meta: r.meta, stale: stale, at: r.at };
      });
    });
  }
  /** Quietly saves these pages on the phone so they open offline later. Skips ones saved in the last `maxAge` ms. */
  function prefetch(paths, maxAge) {
    var i = 0;
    function next() {
      if (i >= paths.length || navigator.onLine === false) return Promise.resolve();
      var p = paths[i++], c = getCache(p);
      if (c && Date.now() - c.at < (maxAge || 600000)) return next();
      return request("GET", p).then(function (res) { if (res.status === 429) { i = paths.length; return; } if (res.ok && res.json) putCache(p, { data: res.json.data, meta: res.json.meta || {} }); }, function () {}).then(next);
    }
    return next();
  }
  function setPrefs(p) { var s = session(); if (s) { s.user.preferences = Object.assign({}, s.user.preferences, p); wr(SK, s); } }

  /** Sends files (never queued: it needs a connection). Resolves the envelope's data, rejects with the server's message. */
  function upload(path, formData) {
    if (navigator.onLine === false) { var e = new Error("This needs a connection."); e.offline = true; return Promise.reject(e); }
    var s = session(), headers = { Accept: "application/json" }; if (s && s.token) headers.Authorization = "Bearer " + s.token;
    return fetch(base() + path, { method: "POST", headers: headers, body: formData, credentials: "omit" }).then(function (r) {
      return r.text().then(function (txt) { var j = null; try { j = JSON.parse(txt); } catch (x) {} if (!r.ok) { var fk = j && j.errors && Object.keys(j.errors)[0], first = fk && j.errors[fk] && j.errors[fk][0]; var er = new Error(first || (j && j.message) || "Upload failed."); er.status = r.status; er.code = j && j.code; er.errors = j && j.errors; throw er; } return j && j.data; });
    }, function () { var er = new Error("network"); er.network = true; er.offline = true; er.message = "This needs a connection."; throw er; });
  }

  /** Fetches a private file (a photo) with the sign-in token and resolves a blob: address an <img> can show. Never saved on the phone. */
  function blobUrl(path) {
    if (navigator.onLine === false) return Promise.reject(new Error("offline"));
    var s = session(), headers = {}; if (s && s.token) headers.Authorization = "Bearer " + s.token;
    return fetch(base() + path, { headers: headers, credentials: "omit" }).then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.blob(); }).then(function (b) { return URL.createObjectURL(b); });
  }

  /* ---------- public: write ---------- */
  /** opts: { label, optimistic: function(), online: true (never queue) }  -> { queued, data } */
  function write(method, path, body, opts) {
    opts = opts || {};
    var key = uid();
    function queue() {
      var u = user(); if (!u) throw new Error("Not signed in.");
      var a = outbox(); a.push({ id: uid(), key: key, userId: u.id, method: method, path: path, body: body, label: opts.label || path, status: "waiting", tries: 0, at: Date.now() });
      if (!wr(OB, a)) { var e = new Error("This phone is out of space. Free some space and try again."); throw e; }
      if (opts.optimistic) try { opts.optimistic(); } catch (e2) {}
      emit(); schedule();
      return { queued: true };
    }
    if (opts.online) {
      if (navigator.onLine === false) { var e = new Error("This needs a connection."); e.offline = true; return Promise.reject(e); }
      return request(method, path, body).then(function (res) { if (!res.ok) throw fail(res); return { queued: false, data: res.json && res.json.data }; },
        function (err) { if (err.network) { var e2 = new Error("This needs a connection."); e2.offline = true; throw e2; } throw err; });
    }
    // If other changes are already waiting, this one waits behind them so the order is kept.
    if (navigator.onLine === false || counts().waiting > 0) { try { return Promise.resolve(queue()); } catch (e3) { return Promise.reject(e3); } }
    return request(method, path, body, { key: key }).then(function (res) {
      if (!res.ok) throw fail(res);
      if (opts.optimistic) try { opts.optimistic(); } catch (e4) {}
      return { queued: false, data: res.json && res.json.data };
    }, function (err) {
      if (err.network) { try { return queue(); } catch (e5) { throw e5; } }
      throw err;
    });
  }

  /* ---------- sync ---------- */
  function sync() {
    if (syncing || navigator.onLine === false || !session()) return Promise.resolve(0);
    var u = user(); syncing = true; emit();
    var done = 0, stop = false;
    function next() {
      if (stop) return Promise.resolve();
      var a = outbox(), item = null;
      for (var i = 0; i < a.length; i++) { if (a[i].userId === u.id && a[i].status === "waiting") { item = a[i]; break; } }
      if (!item) return Promise.resolve();
      return request(item.method, item.path, item.body, { key: item.key }).then(function (res) {
        var all = outbox(), idx = -1; all.forEach(function (o, n) { if (o.id === item.id) idx = n; });
        if (idx < 0) return next();
        if (res.ok) { all.splice(idx, 1); saveOutbox(all); done++; return next(); }
        if (res.status === 401) { stop = true; return; }
        if (res.status === 409 && res.json && res.json.code === "REQUEST_IN_PROGRESS") { stop = true; return; }
        if (res.status >= 500 || res.status === 429) { all[idx].tries++; if (all[idx].tries >= 8) { all[idx].status = "failed"; all[idx].error = message(res); } saveOutbox(all); stop = true; return; }
        all[idx].status = "failed"; all[idx].error = message(res); saveOutbox(all); return next();
      }, function () { stop = true; });
    }
    return next().then(function () {
      syncing = false;
      if (counts().total === 0 || done) setMeta({ lastSync: Date.now() });
      emit({ synced: done });
      if (counts().waiting > 0 && !stop) schedule();
      return done;
    });
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(function () { sync(); }, 1500); }
  function discard(id) { saveOutbox(outbox().filter(function (o) { return o.id !== id; })); emit(); }
  function retry(id) { var a = outbox(); a.forEach(function (o) { if (o.id === id) { o.status = "waiting"; o.tries = 0; delete o.error; } }); saveOutbox(a); emit(); sync(); }

  window.addEventListener("online", function () { emit(); sync(); refreshMe(); });
  window.addEventListener("offline", function () { emit(); });
  document.addEventListener("visibilitychange", function () { if (!document.hidden) { emit(); sync(); } });
  setInterval(function () { if (counts().waiting > 0) sync(); }, 30000);
  window.addEventListener("load", function () { setTimeout(sync, 600); });

  window.ACFAPI = {
    base: base, session: session, user: user, can: can, blobUrl: blobUrl, login: login, logout: logout, refreshMe: refreshMe,
    get: get, getAll: getAll, upload: upload, getEvery: getEvery, prefetch: prefetch, setPrefs: setPrefs, updateUser: updateUser, write: write, patchCache: patchCache, wipeCache: wipeCache,
    outbox: mine, counts: counts, state: state, onChange: onChange, sync: sync, discard: discard, retry: retry,
    expired: function () { return !!rd(META, {}).expired; }, clearExpired: function () { setMeta({ expired: false }); }
  };
})();
