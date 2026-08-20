const { test, expect } = require('@playwright/test');
const path = require('node:path');

test('public documentation is responsive, searchable and audience-safe', async ({ page }, testInfo) => {
  const response = await page.goto('/help', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);

  await expect(page.getByRole('heading', { level: 1, name: 'Help and documentation' })).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Documentation sections' })).toBeVisible();
  await expect(page.getByRole('search')).toBeVisible();
  await expect(page.getByLabel('Search documentation')).toBeVisible();
  await expect(page.getByLabel('Audience')).toBeVisible();
  await expect(page.getByLabel('Brand')).toBeVisible();
  await expect(page.getByLabel('Module')).toBeVisible();
  await expect(page.getByLabel('Version')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Administrator Guide' })).toHaveCount(0);

  const overflow = await page.evaluate(() => ({
    body: document.body.scrollWidth - innerWidth,
    document: document.documentElement.scrollWidth - innerWidth,
  }));
  expect(overflow.body, 'documentation body horizontal overflow in pixels').toBeLessThanOrEqual(1);
  expect(overflow.document, 'documentation document horizontal overflow in pixels').toBeLessThanOrEqual(1);

  const resultCards = page.locator('.docs-result-card');
  expect(await resultCards.count()).toBeGreaterThan(0);
  const firstCard = await resultCards.first().boundingBox();
  expect(firstCard).not.toBeNull();
  if (testInfo.project.name.startsWith('mobile')) {
    expect(firstCard.width, 'mobile result card uses the single-column width').toBeGreaterThan(300);
    for (const control of await page.locator('.docs-search input, .docs-search select, .docs-search button').all()) {
      const box = await control.boundingBox();
      expect(box, 'mobile search control has a rendered box').not.toBeNull();
      expect(box.height, 'mobile search control touch target height').toBeGreaterThanOrEqual(44);
    }
  } else {
    expect(firstCard.width, 'desktop result card remains in a multi-column grid').toBeLessThan(500);
  }

  await page.getByLabel('Search documentation').fill('garage');
  await page.getByRole('button', { name: 'Search guides' }).click();
  await expect(page).toHaveURL(/\/help\?q=garage/);
  await expect(page.getByRole('link', { name: 'Account and My Garage' })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: /matching article/i })).toBeVisible();

  if (process.env.PLAYWRIGHT_SCREENSHOT_DIR) {
    await page.goto('/help', { waitUntil: 'networkidle' });
    await page.screenshot({
      path: path.join(
        process.env.PLAYWRIGHT_SCREENSHOT_DIR,
        `documentation-public-${testInfo.project.name}.png`,
      ),
    });
  }
});

test('public documentation article has usable landmarks and keyboard focus', async ({ page }) => {
  const response = await page.goto('/help/customer-guide/account-and-garage', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);

  await expect(page.getByRole('heading', { level: 1, name: 'Account and My Garage' })).toBeVisible();
  await expect(page.getByRole('complementary', { name: 'Article information' })).toBeVisible();
  await expect(page.locator('.docs-article')).toBeVisible();
  expect(await page.getByRole('heading', { level: 1 }).count()).toBe(1);

  await page.getByRole('link', { name: 'Documentation', exact: true }).focus();
  const focus = await page.getByRole('link', { name: 'Documentation', exact: true }).evaluate((element) => {
    const style = getComputedStyle(element);
    return { outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth };
  });
  expect(focus.outlineStyle).not.toBe('none');
  expect(Number.parseFloat(focus.outlineWidth)).toBeGreaterThanOrEqual(2);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  expect(overflow, 'documentation article horizontal overflow in pixels').toBeLessThanOrEqual(1);
});
