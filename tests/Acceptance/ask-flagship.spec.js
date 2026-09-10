const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Ask VanAssist flagship journey', () => {
  test.skip(!process.env.PLAYWRIGHT_EXPECT_ASK, 'Requires an isolated environment with Ask enabled');

  test('Ask is the primary homepage search with structured fallback', async ({ page }, testInfo) => {
    const response = await page.goto('/', { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await expect(page.locator('body')).toHaveAttribute('data-brand', 'vanassist');

    const ask = page.getByRole('complementary', { name: /Ask VanAssist/i });
    await expect(ask).toBeVisible();
    await expect(ask.getByLabel('What do you need help finding?')).toBeVisible();
    await expect(ask.getByRole('button', { name: 'Find the right help' })).toBeVisible();

    const structured = page.locator('details.ask-structured-fallback');
    await expect(structured).toBeVisible();
    await expect(structured).not.toHaveAttribute('open', '');
    await structured.locator(':scope > summary').click();
    await expect(page.getByLabel('Service category')).toBeVisible();
    await expect(page.getByLabel('Town, suburb or postcode')).toBeVisible();

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow).toBeLessThanOrEqual(1);
    await page.screenshot({
      path: path.join(
        process.cwd(),
        'docs/evidence/flagship-ask-three-site-audit-2026-09-09/screenshots',
        `ask-home-${testInfo.project.name}.png`,
      ),
      fullPage: true,
    });
  });

  test('Ask explains emergency limits and keeps directory results secondary', async ({ page }) => {
    await page.goto('/ask?q=I%20smell%20gas%20in%20my%20caravan%20near%20Roma', {
      waitUntil: 'networkidle',
    });

    const alert = page.getByRole('alert');
    await expect(alert).toContainText('Triple Zero (000)');
    await expect(alert).toContainText(/not emergency dispatch|not.*diagnosis/i);
    await expect(page.getByRole('link', { name: 'Use category search' })).toBeVisible();
  });

  test('Ask remains keyboard reachable with a visible focus indicator', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const input = page.getByLabel('What do you need help finding?');
    await input.focus();
    await expect(input).toBeFocused();

    const focus = await input.evaluate((element) => {
      const style = getComputedStyle(element);
      return { outline: style.outlineStyle, boxShadow: style.boxShadow };
    });
    expect(focus.outline !== 'none' || focus.boxShadow !== 'none').toBeTruthy();
  });
});
