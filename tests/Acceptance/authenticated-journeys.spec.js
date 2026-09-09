const { test, expect } = require('@playwright/test');

const base = process.env.PLAYWRIGHT_VANASSIST_URL;
const password = process.env.PLAYWRIGHT_TEST_PASSWORD;

for (const role of ['customer', 'provider']) {
  test(`${role} test account reaches only its owned workspace`, async ({ page }) => {
    const email = process.env[`PLAYWRIGHT_${role.toUpperCase()}_EMAIL`];
    test.skip(!base || !email || !password, `Supply isolated ${role} test credentials`);

    await page.goto(`${base}/login`);
    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/password/i).fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();

    const target = role === 'provider' ? '/provider' : '/account';
    await page.goto(`${base}${target}`, { waitUntil: 'networkidle' });
    await expect(page).toHaveURL(new RegExp(`${target.replace('/', '\\/')}`));
    await expect(page.locator('h1').first()).toBeVisible();

    if (role === 'customer') {
      const denied = await page.request.get(`${base}/provider`, { maxRedirects: 0 });
      expect([302, 403]).toContain(denied.status());
    }
  });
}
