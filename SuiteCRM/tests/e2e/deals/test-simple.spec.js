const { test, expect } = require('@playwright/test');

test('simple test', async ({ page }) => {
  await page.goto('http://localhost:8080');
  await expect(page).toHaveTitle(/DealMakerCRM/);
});