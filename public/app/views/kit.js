import { live } from '../core/db.js';

/** Per-screen helpers. Everything registered here is released when the screen is left. */
export function kit(ctx) {
  const stops = [];
  return {
    root: ctx.root,
    /** Re-run a local-database query whenever its data changes (edits, pulls, other tabs) and hand the result to cb. */
    live(querier, cb) { stops.push(live(querier, cb)); },
    /** Event delegation: handler runs for matching descendants, even after the screen re-renders. */
    on(event, selector, handler) {
      ctx.root.addEventListener(event, (e) => { const t = e.target.closest(selector); if (t && ctx.root.contains(t)) handler(e, t); });
    },
    render(h) { ctx.root.innerHTML = h.toString(); },
    cleanup() { stops.splice(0).forEach((stop) => stop()); },
  };
}

