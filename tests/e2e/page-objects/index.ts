// Export all page objects for easy importing
export { BasePage } from './BasePage';
export { LoginPage } from './LoginPage';
export { DashboardPage } from './DashboardPage';
export { DealsPage } from './DealsPage';
export { PipelinePage } from './PipelinePage';
export { ChecklistsPage } from './ChecklistsPage';
export { StakeholdersPage } from './StakeholdersPage';
export { FinancialPage } from './FinancialPage';

// Type definitions for page objects
export type PageObjects = {
  loginPage: LoginPage;
  dashboardPage: DashboardPage;
  dealsPage: DealsPage;
  pipelinePage: PipelinePage;
  checklistsPage: ChecklistsPage;
  stakeholdersPage: StakeholdersPage;
  financialPage: FinancialPage;
};