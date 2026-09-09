const { test, expect } = require('@playwright/test');

const sites = [
  {
    name: 'VanAssist',
    origin: process.env.PLAYWRIGHT_VANASSIST_URL,
    brand: 'vanassist',
    routes: ['/', '/providers', '/services', '/regions', '/stays', '/request-assistance', '/service-runs', '/rules', '/help', '/contact', '/privacy-policy', '/terms-of-use'],
  },
  {
    name: 'TowSmart',
    origin: process.env.PLAYWRIGHT_TOWSMART_URL,
    brand: 'towsmart',
    routes: ['/', '/calculator', '/tow-guide', '/checklist', '/providers', '/rules', '/help', '/contact', '/privacy-policy', '/terms-of-use'],
  },
  {
    name: 'TrailerWise',
    origin: process.env.PLAYWRIGHT_TRAILERWISE_URL,
    brand: 'trailerwise',
    routes: ['/', '/providers', '/services', '/marketplace', '/rules', '/help', '/contact', '/privacy-policy', '/terms-of-use'],
  },
];

for (const site of sites) {
  test(`${site.name} critical public routes render without browser failures`, async ({ page }) => {
    test.setTimeout(120_000);
    test.skip(!site.origin, `Set the ${site.name} acceptance URL`);
    const failures = [];
    page.on('console', (message) => {
      if (message.type() === 'error') failures.push(`console: ${message.text()}`);
    });
    page.on('requestfailed', (request) => {
      const errorText = request.failure()?.errorText || '';
      if (request.resourceType() === 'image' && /ABORTED/i.test(errorText)) {
        return;
      }
      failures.push(`request: ${request.url()} ${errorText}`);
    });

    for (const path of site.routes) {
      const response = await page.goto(`${site.origin}${path}`, { waitUntil: 'load' });
      expect(response?.status(), `${site.name} ${path}`).toBe(200);
      await expect(page.locator('body')).toHaveAttribute('data-brand', site.brand);
      await expect(page.locator('main h1:visible').first(), `${site.name} ${path} has a primary heading`).toBeVisible();
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
      );
      expect(overflow, `${site.name} ${path} horizontal overflow`).toBeLessThanOrEqual(1);
    }

    expect(failures, failures.join('\n')).toEqual([]);
  });
}

test('brand metadata, robots, sitemap and health endpoints are coherent', async ({ request }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop-1440x900', 'HTTP metadata matrix runs once per candidate');
  for (const site of sites) {
    test.skip(!site.origin, `Set the ${site.name} acceptance URL`);
    for (const path of ['/healthz', '/readyz', '/robots.txt', '/sitemap.xml']) {
      const response = await request.get(`${site.origin}${path}`);
      expect(response.status(), `${site.name} ${path}`).toBe(200);
    }
  }
});
