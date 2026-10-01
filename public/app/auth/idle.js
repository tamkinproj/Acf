// Locks the app after a period of inactivity (the length is a setting). Cheap: a few passive listeners and one timer.
export function watchIdle({ minutes, onIdle }) {
  let last = Date.now();
  const bump = () => { last = Date.now(); };
  const events = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'visibilitychange'];
  events.forEach((e) => globalThis.addEventListener(e, bump, { passive: true }));
  const timer = setInterval(() => { if (Date.now() - last > minutes() * 60000) { last = Date.now(); onIdle(); } }, 15000);
  return () => { events.forEach((e) => globalThis.removeEventListener(e, bump)); clearInterval(timer); };
}
