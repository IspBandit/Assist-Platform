const { test, expect } = require('@playwright/test');
const path = require('node:path');

async function expectElementStartsInViewport(locator, viewportHeight) {
  const box = await locator.evaluate((element) => {
    const rect = element.getBoundingClientRect();
    return { top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height };
  });
  expect(box.width, 'element has a rendered width').toBeGreaterThan(0);
  expect(box.height, 'element has a rendered height').toBeGreaterThan(0);
  expect(box.top, 'element starts below the top edge').toBeGreaterThanOrEqual(0);
  expect(box.top, 'element starts in the first viewport').toBeLessThan(viewportHeight);
}

test('VanAssist homepage keeps the core journey accessible and Ask first', async ({ page }, testInfo) => {
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
  const expectedHero = testInfo.project.name.includes('mobile')
    ? 'vanassist-coastal-hero-mobile-v1.webp'
    : 'vanassist-coastal-hero-desktop-v1.webp';
  expect(await heroImage.evaluate((image) => image.currentSrc)).toContain(expectedHero);

  const isMobile = testInfo.project.name.includes('mobile');
  const headline = isMobile ? page.locator('.mobile-hero-intro .mobile-hero-title') : page.locator('.hero-copy h1');
  const askVanAssist = page.locator('.ask-vanassist-home');
  const structuredSearch = page.locator('.hero-search-panel .structured-search-form');
  await expect(headline).toContainText(/Your travel\s+companion\./i);
  await expect(askVanAssist).toBeVisible();
  await expect(page.getByLabel('What do you need help finding?')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Find the right help' })).toBeVisible();
  await expect(page.locator('details.ask-structured-fallback')).not.toHaveAttribute('open', '');
  await expect(structuredSearch).toBeAttached();
  await expect(page.getByLabel('Service category')).toBeAttached();
  await expect(page.getByLabel('Town, suburb or postcode')).toBeAttached();
  await expectElementStartsInViewport(headline, viewport.height);
  await expectElementStartsInViewport(askVanAssist, viewport.height);
  // Closed structured fallback must not steal the first viewport on phones.
  if (isMobile) {
    const preferCategory = page.locator('details.ask-structured-fallback > summary');
    await expect(preferCategory).toBeVisible();
    const preferBox = await preferCategory.boundingBox();
    expect(preferBox?.y ?? 0).toBeGreaterThan(80);
  }

  const overflow = await page.evaluate(() => ({
    body: document.body.scrollWidth - window.innerWidth,
    document: document.documentElement.scrollWidth - window.innerWidth,
  }));
  expect(overflow.body, 'body horizontal overflow in pixels').toBeLessThanOrEqual(1);
  expect(overflow.document, 'document horizontal overflow in pixels').toBeLessThanOrEqual(1);

  const quickActions = page.getByRole('navigation', { name: 'Browse VanAssist directly' });
  await expect(quickActions).toBeVisible();
  await expect(quickActions.getByRole('link')).toHaveCount(4);

  if (isMobile) {
    const topGap = await page.evaluate(() => {
      const header = document.querySelector('.site-header');
      const heroCopy = document.querySelector('.hero--visual .mobile-hero-intro');
      if (!header || !heroCopy) return null;
      return heroCopy.getBoundingClientRect().top - header.getBoundingClientRect().bottom;
    });
    expect(topGap, 'mobile header-to-hero gap is measurable').not.toBeNull();
    expect(topGap, 'mobile header-to-hero gap in pixels').toBeGreaterThanOrEqual(-1);
    expect(topGap, 'mobile header-to-hero gap in pixels').toBeLessThanOrEqual(64);

    const primarySearchButton = page.getByRole('button', { name: 'Find the right help' });
    await expect(primarySearchButton).toBeVisible();
    const primarySearchBox = await primarySearchButton.evaluate((element) => {
      const rect = element.getBoundingClientRect();
      return { bottom: rect.bottom, width: rect.width, height: rect.height };
    });
    expect(primarySearchBox.width, 'mobile primary submit has a rendered width').toBeGreaterThan(0);
    expect(primarySearchBox.height, 'mobile primary submit has a rendered height').toBeGreaterThan(0);
    expect(
      primarySearchBox.bottom,
      'mobile primary submit is fully visible in the first viewport',
    ).toBeLessThanOrEqual(viewport.height);

    const askBox = await askVanAssist.boundingBox();
    const quickActionsBox = await quickActions.boundingBox();
    expect(askBox, 'Ask VanAssist has a rendered box').not.toBeNull();
    expect(quickActionsBox, 'mobile quick actions have a rendered box').not.toBeNull();
    expect(
      quickActionsBox.y,
      'direct quick actions follow Ask VanAssist on phone',
    ).toBeGreaterThanOrEqual(askBox.y + askBox.height - 1);
  }

  const installButton = page.locator('[data-install-app]:visible').first();
  await expect(installButton).toBeVisible();
  await expect(installButton).toContainText(isMobile ? 'Save VanAssist to your phone' : 'Save VanAssist before you go');
  await expectElementStartsInViewport(installButton, viewport.height);

  if (process.env.PLAYWRIGHT_SCREENSHOT_DIR) {
    const screenshotPath = path.join(
      process.env.PLAYWRIGHT_SCREENSHOT_DIR,
      `vanassist-home-${testInfo.project.name}.png`,
    );
    await page.screenshot({ path: screenshotPath });
  }

  await installButton.scrollIntoViewIfNeeded();
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
  expect(workerResponse.headers()['content-type']).toMatch(/^(application|text)\/javascript(?:;|$)/);
  // A root-level worker has root scope without Service-Worker-Allowed.
  const scope = await page.evaluate(async () => {
    const registration = await navigator.serviceWorker.register('/service-worker.js');
    return registration.scope;
  });
  expect(scope).toBe(new URL('/', page.url()).href);
  expect(await workerResponse.text()).toContain("self.addEventListener('fetch'");
});
