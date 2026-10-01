// Light / dark / follow-the-device. A per-browser preference, so localStorage is the right home (and failing is harmless).
const KEY = 'fdn-theme';
export const getTheme = () => { try { return localStorage.getItem(KEY) || 'auto'; } catch { return 'auto'; } };
export function setTheme(mode) {
  try { mode === 'auto' ? localStorage.removeItem(KEY) : localStorage.setItem(KEY, mode); } catch { /* private mode */ }
  applyTheme();
}
export function applyTheme() {
  const mode = getTheme();
  if (mode === 'auto') document.documentElement.removeAttribute('data-theme'); else document.documentElement.dataset.theme = mode;
}
