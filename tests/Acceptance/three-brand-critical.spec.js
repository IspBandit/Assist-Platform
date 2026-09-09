const { test, expect } = require('@playwright/test');

const origins = {
  vanassist: process.env.PLAYWRIGHT_VANASSIST_URL,
  towsmart: process.env.PLAYWRIGHT_TOWSMART_URL,
  trailerwise: process.env.PLAYWRIGHT_TRAILERWISE_URL,
};

test('TowSmart calculator remains usable and correctly branded', async ({ page }) => {
  test.skip(!origins.towsmart, 'Set PLAYWRIGHT_TOWSMART_URL for multi-brand acceptance');
  const response = await page.goto(`${origins.towsmart}/calculator`, { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  await expect(page.locator('body')).toHaveAttribute('data-brand', 'towsmart');
  await expect(page.getByRole('heading', { level: 1, name: /Build your real towing combination/i })).toBeVisible();
  await expect(page.getByLabel('Vehicle description')).toBeVisible();
  await expect(page.getByLabel('Trailer description')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Calculate my combination' })).toBeVisible();
  await expectNoOverflow(page);
});

test('TrailerWise marketplace filters and empty state remain usable', async ({ page }) => {
  test.skip(!origins.trailerwise, 'Set PLAYWRIGHT_TRAILERWISE_URL for multi-brand acceptance');
  const response = await page.goto(`${origins.trailerwise}/marketplace?q=unlikely-audit-value-93847`, {
    waitUntil: 'networkidle',
  });
  expect(response?.status()).toBe(200);
  await expect(page.locator('body')).toHaveAttribute('data-brand', 'trailerwise');
  await expect(page.getByRole('heading', { level: 1, name: 'Trailer marketplace' })).toBeVisible();
  await expect(page.getByLabel('Search')).toHaveValue('unlikely-audit-value-93847');
  await expect(page.getByText('No trailers match those filters yet.')).toBeVisible();
  await expectNoOverflow(page);
});

test('disabled VanAssist modules fail closed on other public brands', async ({ request }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop-1440x900', 'HTTP denial matrix runs once per candidate');
  test.skip(!origins.towsmart || !origins.trailerwise, 'Set both secondary brand URLs');
  for (const origin of [origins.towsmart, origins.trailerwise]) {
    for (const path of ['/ask?q=help%20near%20Roma', '/request-assistance', '/service-runs', '/stays', '/caravan-parks/apply']) {
      const response = await request.get(`${origin}${path}`, { maxRedirects: 0 });
      expect(response.status(), `${origin}${path}`).toBe(404);
    }
  }
});

async function expectNoOverflow(page) {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - window.innerWidth,
  );
  expect(overflow).toBeLessThanOrEqual(1);
}
