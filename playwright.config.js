const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/Acceptance',
  outputDir: './storage/cache/playwright-results',
  reporter: 'line',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8080',
    // Exercise the public browser journey without being rejected as an obvious
    // HeadlessChrome scraper, while keeping acceptance traffic out of analytics.
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 AssistPlatformAcceptance/1.0',
    extraHTTPHeaders: { 'X-Assist-Synthetic': '1' },
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'desktop-1440x900',
      use: { viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'mobile-390x844',
      use: {
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
      },
    },
  ],
});
