import { test as base, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { DealsPage } from '../page-objects/DealsPage';
import { PipelinePage } from '../page-objects/PipelinePage';
import { ChecklistsPage } from '../page-objects/ChecklistsPage';
import { StakeholdersPage } from '../page-objects/StakeholdersPage';
import { FinancialPage } from '../page-objects/FinancialPage';

// Define custom test fixtures
type TestFixtures = {
  loginPage: LoginPage;
  dashboardPage: DashboardPage;
  dealsPage: DealsPage;
  pipelinePage: PipelinePage;
  checklistsPage: ChecklistsPage;
  stakeholdersPage: StakeholdersPage;
  financialPage: FinancialPage;
  authenticatedPage: void;
};

// Extend base test with custom fixtures
export const test = base.extend<TestFixtures>({
  // Page object fixtures
  loginPage: async ({ page }, use) => {
    await use(new LoginPage(page));
  },

  dashboardPage: async ({ page }, use) => {
    await use(new DashboardPage(page));
  },

  dealsPage: async ({ page }, use) => {
    await use(new DealsPage(page));
  },

  pipelinePage: async ({ page }, use) => {
    await use(new PipelinePage(page));
  },

  checklistsPage: async ({ page }, use) => {
    await use(new ChecklistsPage(page));
  },

  stakeholdersPage: async ({ page }, use) => {
    await use(new StakeholdersPage(page));
  },

  financialPage: async ({ page }, use) => {
    await use(new FinancialPage(page));
  },

  // Auto-login fixture
  authenticatedPage: [async ({ page, loginPage }, use) => {
    // Login before each test
    await loginPage.goto();
    await loginPage.login(
      process.env.SUITE_USERNAME || 'admin',
      process.env.SUITE_PASSWORD || 'admin'
    );
    
    // Wait for dashboard to load
    await expect(page).toHaveURL(/.*index\.php.*/, { timeout: 30000 });
    
    // Use the authenticated page
    await use();
    
    // Logout after test (optional)
    // await page.goto('/index.php?module=Users&action=Logout');
  }, { auto: true }], // This fixture runs automatically for tests that use it
});

// Re-export expect for convenience
export { expect } from '@playwright/test';

// Custom test annotations
export const describe = {
  smoke: (title: string, fn: Function) => base.describe(`@smoke ${title}`, fn),
  regression: (title: string, fn: Function) => base.describe(`@regression ${title}`, fn),
  critical: (title: string, fn: Function) => base.describe(`@critical ${title}`, fn),
  feature: (feature: string) => (title: string, fn: Function) => 
    base.describe(`@feature-${feature} ${title}`, fn),
};