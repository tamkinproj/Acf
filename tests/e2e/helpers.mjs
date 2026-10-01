// Shared bits for browser checks. Sign in through the real screens, whatever the first screen turns out to be.
import { chromium } from 'playwright-core';

export const launch = () => chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });

/**
 * Sign in as `email` and get past the one-time screens. A Foundation Admin claims the server's device; someone who cannot
 * register devices pastes `deviceToken` (the dev server prints some). Platform admins need neither.
 */
export async function signIn(page, url, email, password, { deviceToken } = {}) {
  await page.goto(`${url}/`);
  await page.waitForSelector('[data-login]');
  await page.fill('#f-email', email);
  await page.fill('#f-password', password);
  await page.click('[data-login] button[type=submit]');
  const first = await Promise.race([
    page.waitForSelector('.frame', { timeout: 15000 }).then(() => 'app'),
    page.waitForSelector('[data-choices] [data-paste]', { timeout: 15000 }).then(() => 'device'),
  ]);
  if (first === 'device') {
    if (deviceToken) {
      await page.fill('[data-paste] #f-token', deviceToken);
      await page.click('[data-paste] button[type=submit]');
    } else {
      await page.click('[data-claim]');
    }
    await page.waitForSelector('.frame', { timeout: 15000 });
  }
  try { await page.getByRole('button', { name: 'Not now' }).click({ timeout: 4000 }); } catch { /* no PIN prompt */ }
  await page.waitForTimeout(300);
}

/** Open a hash route and wait for the screen to settle. */
export async function go(page, url, route) {
  await page.goto(`${url}/#/${route}`);
  await page.waitForTimeout(700);
}
