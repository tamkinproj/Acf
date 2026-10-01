// Starts a throw-away copy of the system (installed, with demo data) and keeps it running. For looking at screens and for browser checks.
//   node dev-server.mjs            -> prints {"url": ..., "accounts": ...} then stays up until killed
// Re-run it to pick up code changes: it serves a COPY of the project made at start-up.
import { startBackend, DEMO, ADMIN, PLATFORM } from '../js/backend.mjs';

const be = await startBackend({ demo: true });
console.log(JSON.stringify({
  url: be.url, dir: be.dir,
  platform: PLATFORM, foundationAdmin: ADMIN, demoPassword: DEMO.password, demo: DEMO.accounts, deviceTokens: be.deviceTokens,
  note: 'Demo Foundation: admin@demo.test / mushrif@demo.test / worker@demo.test (password = demoPassword). Test Foundation: admin@test.example.',
}));
const stop = () => { be.stop(); process.exit(0); };
process.on('SIGTERM', stop);
process.on('SIGINT', stop);
setInterval(() => {}, 1 << 30);
