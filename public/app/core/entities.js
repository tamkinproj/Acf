// The client-side registry of replicated entities. A future module (Aytam, Donations, ...) calls registerEntity()
// before the database opens; the sync engine, outbox and UI need no other change.
//
//   name     - entity name on the server (same as /api/sync/schema)
//   indexes  - extra Dexie indexes (the primary key `id` is always present)
//   derive   - optional async (row, tx-helpers) => fields computed locally for display (server stays authoritative)
export const entities = {};

export function registerEntity(name, { indexes = '', derive = null, label = name } = {}) {
  entities[name] = { name, indexes, derive, label };
}

registerEntity('foundations', { label: 'Foundation profile' });
registerEntity('settings', { indexes: 'key', label: 'Settings' });
registerEntity('users', { indexes: 'role_id', label: 'Users' });
registerEntity('roles', { indexes: 'key', label: 'Roles' });
registerEntity('devices', { label: 'Devices' });
// Places carry a derived tree path. The server computes the authoritative value; computing the same thing locally lets
// the tree render correctly while offline (the server's value replaces it on the next sync).
registerEntity('locations', {
  indexes: 'parent_id, level',
  label: 'Places',
  derive: async (row, { get }) => {
    const parent = row.parent_id ? await get('locations', row.parent_id) : null;
    return { path: `${parent?.path ?? '/'}${row.id}/`, depth: parent ? parent.depth + 1 : 0 };
  },
});
registerEntity('audit_logs', { indexes: 'occurred_at', label: 'Activity' });

export const entityNames = () => Object.keys(entities);
