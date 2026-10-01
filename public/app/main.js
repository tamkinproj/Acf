import { $, html } from './core/util.js';
import { session } from './auth/session.js';
import * as sessionApi from './auth/session.js';
import { onLocalWrite } from './sync/outbox.js';
import * as engine from './sync/engine.js';
import { toast } from './core/ui.js';
import { watchIdle } from './auth/idle.js';
import { hasPin } from './auth/pin.js';
import { deviceScreen, loginScreen, lockScreen, passwordScreen, pinSetupSheet } from './views/auth.js';
import { mountShell } from './views/shell.js';
import { applyTheme } from './core/theme.js';

applyTheme();

// Entry point. Chooses what to show from the session state; screens never decide that themselves.
const app = document.getElementById('app');
let current = { key: null, cleanup: null };
let stopIdle = null;
let nagged = false;

function show(key, render) {
  if (current.key === key) return;
  current.cleanup?.();
  stopIdle?.(); stopIdle = null;
  app.className = '';
  app.removeAttribute('role');
  app.replaceChildren();
  const cleanup = render(app);
  current = { key, cleanup: typeof cleanup === 'function' ? cleanup : null };
}

function route() {
  const s = session.get();
  switch (s.status) {
    case 'anon': show('anon', loginScreen); break;
    case 'needs-device': show('device', deviceScreen); break;
    case 'locked': show('locked', lockScreen); break;
    case 'active':
      if (s.mustChangePassword) { show('password', (el) => passwordScreen(el, () => sessionApi.boot().then(route))); break; }
      show(`active:${s.user?.id}`, (el) => {
        const unmount = mountShell(el);
        stopIdle = watchIdle({ minutes: () => session.get().idleMinutes || 15, onIdle: async () => {
          if (await hasPin() || navigator.onLine !== false) sessionApi.lock();
        } });
        if (!s.hasPin && !nagged) { nagged = true; setTimeout(() => pinSetupSheet(), 1200); }
        return unmount;
      });
      break;
    default: break;   // booting: the splash from the server-rendered page stays up
  }
}

onLocalWrite(() => engine.kick(1200));
engine.onSyncNotice(({ kind, text }) => toast(text, kind === 'bad' ? 'bad' : 'ok'));
session.subscribe(route);
sessionApi.boot().then(route).catch((e) => {
  console.error(e);
  app.innerHTML = html`<div class="boot"><p>Something went wrong while starting.</p><p class="boot-note">${e.message}</p></div>`.toString();
});
