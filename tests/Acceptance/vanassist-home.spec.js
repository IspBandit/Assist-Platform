const { test, expect } = require('@playwright/test');
const path = require('node:path');

async function expectElementStartsInViewport(locator, viewportHeight) {
  const box = await locator.boundingBox();
  expect(box, 'element has a rendered bounding box').not.toBeNull();
  expect(box.y, 'element starts below the top edge').toBeGreaterThanOrEqual(0);
  expect(box.y, 'element starts in the first viewport').toBeLessThan(viewportHeight);
}

test('VanAssist homepage keeps the core journey in the first viewport', async ({ page }, testInfo) => {
  const viewport = page.viewportSize();
  expect(viewport).not.toBeNull();

  const response = await page.goto('/', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  await expect(page.locator('body')).toHaveAttribute('data-brand', 'vanassist');

  const wordmark = page.locator('.site-header .brand[aria-label="VanAssist home"] .vanassist-road-wordmark');
  await expect(wordmark).toBeVisible();
  await expect(wordmark).toHaveJSProperty('complete', true);
  expect(await wordmark.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);

  const heroImage = page.locator('.hero--visual .hero-media img');
  await expect(heroImage).toBeVisible();
  await expect(heroImage).toHaveJSProperty('complete', true);
  expect(await heroImage.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);
  const expectedHero = testInfo.project.name.startsWith('mobile')
    ? 'vanassist-coastal-hero-mobile-v1.webp'
    : 'vanassist-coastal-hero-desktop-v1.webp';
  expect(await heroImage.evaluate((image) => image.currentSrc)).toContain(expectedHero);

  const headline = page.getByRole('heading', { level: 1 });
  const search = page.locator('.hero-search-panel .search-card');
  await expect(headline).toContainText(/Your travel\s+companion\./i);
  await expect(search).toBeVisible();
  await expect(page.getByLabel('Service category')).toBeVisible();
  await expect(page.getByLabel('Town, suburb or postcode')).toBeVisible();
  await expectElementStartsInViewport(headline, viewport.height);
  await expectElementStartsInViewport(search, viewport.height);

  const overflow = await page.evaluate(() => ({
    body: document.body.scrollWidth - window.innerWidth,
    document: document.documentElement.scrollWidth - window.innerWidth,
  }));
  expect(overflow.body, 'body horizontal overflow in pixels').toBeLessThanOrEqual(1);
  expect(overflow.document, 'document horizontal overflow in pixels').toBeLessThanOrEqual(1);

  if (testInfo.project.name.startsWith('mobile')) {
    const topGap = await page.evaluate(() => {
      const header = document.querySelector('.site-header');
      const heroCopy = document.querySelector('.hero--visual .hero-copy');
      if (!header || !heroCopy) return null;
      return heroCopy.getBoundingClientRect().top - header.getBoundingClientRect().bottom;
    });
    expect(topGap, 'mobile header-to-hero gap is measurable').not.toBeNull();
    expect(topGap, 'mobile header-to-hero gap in pixels').toBeGreaterThanOrEqual(-1);
    expect(topGap, 'mobile header-to-hero gap in pixels').toBeLessThanOrEqual(64);

    const primarySearchButton = page.getByRole('button', { name: 'Show nearby help' });
    await expect(primarySearchButton).toBeVisible();
    const primarySearchBox = await primarySearchButton.boundingBox();
    expect(primarySearchBox, 'mobile primary submit has a rendered box').not.toBeNull();
    expect(
      primarySearchBox.y + primarySearchBox.height,
      'mobile primary submit is fully visible in the first viewport',
    ).toBeLessThanOrEqual(viewport.height);
  }

  const installButton = page.getByRole('button', { name: /Save VanAssist before you go/i });
  await expect(installButton).toBeVisible();
  await expectElementStartsInViewport(installButton, viewport.height);

  if (process.env.PLAYWRIGHT_SCREENSHOT_DIR) {
    const screenshotPath = path.join(
      process.env.PLAYWRIGHT_SCREENSHOT_DIR,
      `vanassist-home-${testInfo.project.name}.png`,
    );
    await page.screenshot({ path: screenshotPath });
  }

  await installButton.click();
  await expect(page.getByRole('dialog', { name: /Save VanAssist to your phone/i })).toBeVisible();
});

test('VanAssist manifest and service worker are reachable', async ({ page, request }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const manifestHref = await page.locator('link[rel="manifest"]').getAttribute('href');
  expect(manifestHref).toBeTruthy();

  const manifestResponse = await request.get(manifestHref);
  expect(manifestResponse.status()).toBe(200);
  expect(manifestResponse.headers()['content-type']).toContain('application/manifest+json');
  const manifest = await manifestResponse.json();
  expect(manifest.short_name).toBe('VanAssist');
  expect(manifest.start_url).toBeTruthy();
  expect(manifest.icons?.length).toBeGreaterThan(0);

  const workerResponse = await request.get('/service-worker.js');
  expect(workerResponse.status()).toBe(200);
  expect(workerResponse.headers()['content-type']).toContain('application/javascript');
  expect(workerResponse.headers()['service-worker-allowed']).toBe('/');
  expect(await workerResponse.text()).toContain("self.addEventListener('fetch'");
});
