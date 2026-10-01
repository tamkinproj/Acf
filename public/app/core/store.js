// A tiny observable store: get(), set(patch), subscribe(fn). Used for session and sync state.
export function createStore(initial) {
  let state = { ...initial };
  const subs = new Set();
  return {
    get: () => state,
    set(patch) { state = { ...state, ...(typeof patch === 'function' ? patch(state) : patch) }; subs.forEach((fn) => fn(state)); },
    subscribe(fn) { subs.add(fn); return () => subs.delete(fn); },
  };
}
