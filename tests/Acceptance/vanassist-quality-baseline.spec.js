const { test, expect } = require('@playwright/test');

const paths = ['/', '/find', '/stays', '/providers'];

for (const path of paths) {
  test(`VanAssist accessibility and performance baseline ${path}`, async ({ page }) => {
    const started = Date.now();
    const response = await page.goto(path, { waitUntil: 'networkidle' });
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('main')).toHaveCount(1);
    await expect(page.locator('h1:visible')).toHaveCount(1);
    await expect(page.locator('html')).toHaveAttribute('lang', /en-AU/i);
    expect(await page.locator('img:not([alt])').count()).toBe(0);
    expect(await page.locator('input:not([type="hidden"]):not([aria-label]):not([aria-labelledby])').evaluateAll(
      (nodes) => nodes.filter((node) => !node.labels || node.labels.length === 0).length,
    )).toBe(0);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);

    const metrics = await page.evaluate(() => {
      const nav = performance.getEntriesByType('navigation')[0];
      const bytes = performance.getEntriesByType('resource').reduce((sum, item) => sum + (item.transferSize || 0), 0);
      return { domContentLoaded: nav?.domContentLoadedEventEnd || 0, transferredBytes: bytes };
    });
    expect(Date.now() - started).toBeLessThan(5000);
    expect(metrics.domContentLoaded).toBeLessThan(4000);
    expect(metrics.transferredBytes).toBeLessThan(3 * 1024 * 1024);
  });
}
