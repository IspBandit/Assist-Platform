const { test, expect } = require('@playwright/test');
const path = require('node:path');
const fs = require('node:fs');

// Read-only production journeys. Never submit a claim or contact a real provider.
// Explicit opt-in prevents ordinary local acceptance from hitting production.
test.skip(process.env.ASSIST_SALE_LIVE !== '1', 'Set ASSIST_SALE_LIVE=1 for authorised live acceptance');

// Explicit candidate overlay: reports must distinguish this from deployed code.
test.beforeEach(async ({ context }) => {
  if (process.env.ASSIST_CANDIDATE_WORKER === '1') {
    await context.route('**/service-worker.js', route => route.fulfill({
      contentType: 'text/javascript',
      body: fs.readFileSync(path.join(__dirname, '../../public/service-worker.js'), 'utf8'),
    }));
  }
});

async function capture(page, testInfo, name) {
  await page.waitForLoadState('networkidle');
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  await expect(page.locator('main')).toBeVisible();
  await expect(page.locator('.site-header')).not.toContainText(/LocalTorque|Polaris/);
  await expect(page.locator('.site-footer')).not.toContainText(/LocalTorque|Polaris/);
  if (process.env.PLAYWRIGHT_SCREENSHOT_DIR) {
    await page.screenshot({ path: path.join(process.env.PLAYWRIGHT_SCREENSHOT_DIR, `${name}-${testInfo.project.name}.png`) });
  }
}

for (const brand of ['vanassist', 'towsmart', 'trailerwise']) {
  const origin = `https://${brand}.com.au`;
  test(`${brand}: homepage, directory, provider and public health`, async ({ page, request }, testInfo) => {
    test.setTimeout(90000);
    expect((await page.goto(origin, { waitUntil: 'networkidle' })).status()).toBe(200);
    await expect(page.locator('body')).toHaveAttribute('data-brand', brand);
    await capture(page, testInfo, `${brand}-home`);
    await page.goto(`${origin}/providers`, { waitUntil: 'networkidle' });
    await expect(page.getByRole('button', { name: 'Search directory', exact: true })).toBeVisible();
    await page.locator('input[name="q"]').fill('electrical');
    await page.getByRole('button', { name: 'Search directory', exact: true }).click();
    await expect(page).toHaveURL(/q=electrical/);
    const details = page.locator('main article a[href*="/providers/"]').first();
    await expect(details).toBeVisible();
    await capture(page, testInfo, `${brand}-results`);
    await details.click();
    await expect(page).toHaveURL(/\/providers\/.+/);
    await capture(page, testInfo, `${brand}-profile`);
    for (const route of ['/healthz', '/readyz']) {
      expect((await request.get(origin + route)).status()).toBe(200);
    }
    expect((await request.get(origin + '/install')).status()).toBe(403);
  });
}

test('TowSmart custom calculation displays results and guidance', async ({ page }, testInfo) => {
  await page.goto('https://towsmart.com.au/calculator', { waitUntil: 'networkidle' });
  const values = { vehicle_name: 'Acceptance vehicle', vehicle_kerb_mass: '2200', vehicle_gvm: '3200', vehicle_gcm: '6000', vehicle_max_braked_towing: '3000', vehicle_max_towball: '300', trailer_name: 'Acceptance caravan', trailer_tare_mass: '1800', trailer_atm: '2500', trailer_gtm: '2300', trailer_tare_ball_mass: '180', passengers_mass: '150', trailer_cargo_mass: '200' };
  for (const [name, value] of Object.entries(values)) await page.locator(`[name="${name}"]`).fill(value);
  await page.getByRole('button', { name: 'Calculate my combination', exact: true }).click();
  await expect(page.locator('main')).toContainText('Informational estimate only');
  await expect(page.locator('main')).toContainText(/result|within|remaining|exceed/i);
  await expect(page.locator('main')).toContainText('4350 kg');
  await capture(page, testInfo, 'towsmart-calculation');
  await page.locator('input[type="number"][name="vehicle_gvm"]').fill('2400');
  await page.getByRole('button', { name: 'Calculate my combination', exact: true }).click();
  await expect(page.locator('main')).toContainText(/exceed/i);
  await capture(page, testInfo, 'towsmart-over-limit');
});

test('VanAssist Ask returns a named provider and an actionable profile', async ({ page }, testInfo) => {
  await page.goto('https://vanassist.com.au/', { waitUntil: 'networkidle' });
  await page.getByRole('textbox', { name: 'Ask VanAssist', exact: true }).fill('Battery World Greenslopes');
  await page.getByRole('textbox', { name: 'Ask VanAssist', exact: true }).press('Enter');
  await expect(page.locator('main')).toContainText('Battery World Greenslopes');
  const profile = page.locator('main a[href*="/providers/"]').first();
  await expect(profile).toBeVisible();
  await capture(page, testInfo, 'vanassist-ask');
});

test('VanAssist stays and three-brand legal pages remain accessible', async ({ page }, testInfo) => {
  test.setTimeout(90000);
  expect((await page.goto('https://vanassist.com.au/stays', { waitUntil: 'networkidle' })).status()).toBe(200);
  await expect(page.locator('main')).toContainText(/stay|camp|park/i);
  await capture(page, testInfo, 'vanassist-stays');
  for (const brand of ['vanassist', 'towsmart', 'trailerwise']) {
    await page.goto(`https://${brand}.com.au/`, { waitUntil: 'networkidle' });
    for (const label of ['Privacy', 'Terms']) {
      const href = await page.locator('.site-footer').getByRole('link', { name: label, exact: true }).getAttribute('href');
      expect((await page.goto(href, { waitUntil: 'networkidle' })).status()).toBe(200);
      await expect(page.locator('main')).toContainText(new RegExp(label, 'i'));
    }
  }
});
