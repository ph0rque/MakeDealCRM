/**
 * TypeScript definitions for Page Object Models
 */

import { Page, ElementHandle } from '@playwright/test';

export interface DealData {
  name?: string;
  status?: string;
  source?: string;
  dealValue?: string;
  ttmRevenue?: string;
  ttmEbitda?: string;
  targetMultiple?: string;
  askingPrice?: string;
  description?: string;
  assignedUserId?: string;
}

export interface ContactData {
  firstName?: string;
  lastName?: string;
  title?: string;
  department?: string;
  email?: string;
  phoneWork?: string;
  phoneMobile?: string;
  addressStreet?: string;
  addressCity?: string;
  addressState?: string;
  addressPostalCode?: string;
  addressCountry?: string;
  role?: string;
  isDecisionMaker?: boolean;
  isInfluencer?: boolean;
  stakeholderType?: string;
  description?: string;
  leadSource?: string;
  assignedUserId?: string;
}

export interface DocumentData {
  name?: string;
  filePath?: string;
  category?: string;
  subcategory?: string;
  status?: string;
  revision?: string;
  type?: string;
  description?: string;
  expirationDate?: string;
  tags?: string[];
}

export interface ChecklistTemplateData {
  name?: string;
  description?: string;
  category?: string;
  type?: string;
  items?: ChecklistItemData[];
}

export interface ChecklistItemData {
  title: string;
  description?: string;
  order?: number;
  required?: boolean;
  dueDays?: number;
}

export interface ChecklistData {
  dealId?: string;
  contactId?: string;
  accountId?: string;
  sendReminders?: boolean;
  reminderDays?: number;
  notificationEmails?: string;
}

export interface PipelineFilters {
  owner?: string;
  dateRange?: string;
  valueRange?: string;
  search?: string;
}

export interface StageData {
  name: string;
  count: number;
  value: string;
  dataStage: string;
  wipLimit?: string;
}

export interface DealCardData {
  title: string;
  amount: string;
  company?: string;
  owner?: string;
  daysInStage?: number;
  isStale?: boolean;
  isAtRisk?: boolean;
  id: string;
}

export interface ChecklistProgress {
  text: string;
  percentage: number;
}

export interface ChecklistItem {
  title: string;
  completed: boolean;
  required?: boolean;
  overdue?: boolean;
  completedBy?: string;
  completedDate?: string;
}

export interface EmailData {
  to?: string;
  subject?: string;
  body?: string;
}

export interface CallData {
  subject?: string;
  duration?: string;
  description?: string;
}

export declare class BasePage {
  page: Page;
  baseURL: string;
  
  constructor(page: Page);
  navigate(path?: string): Promise<void>;
  waitForPageLoad(): Promise<void>;
  waitForElement(selector: string, timeout?: number): Promise<void>;
  clickElement(selector: string): Promise<void>;
  fillField(selector: string, value: string): Promise<void>;
  selectOption(selector: string, value: string): Promise<void>;
  getText(selector: string): Promise<string>;
  isVisible(selector: string): Promise<boolean>;
  takeScreenshot(name: string): Promise<void>;
  handleAlert(accept?: boolean, text?: string): void;
  waitForNetworkIdle(): Promise<void>;
  getAllTexts(selector: string): Promise<string[]>;
  isEnabled(selector: string): Promise<boolean>;
  pressKey(key: string): Promise<void>;
  uploadFile(selector: string, filePath: string): Promise<void>;
  scrollToElement(selector: string): Promise<void>;
  getAttribute(selector: string, attribute: string): Promise<string>;
}

export declare class LoginPage extends BasePage {
  goto(): Promise<void>;
  login(username: string, password: string): Promise<void>;
  loginAsAdmin(): Promise<void>;
  isLoggedIn(): Promise<boolean>;
  getErrorMessage(): Promise<string>;
  clickForgotPassword(): Promise<void>;
  setRememberMe(remember?: boolean): Promise<void>;
  selectLanguage(language: string): Promise<void>;
  selectTheme(theme: string): Promise<void>;
  logout(): Promise<void>;
  isOnLoginPage(): Promise<boolean>;
  getPageTitle(): Promise<string>;
}

export declare class DealPage extends BasePage {
  goto(): Promise<void>;
  createDeal(dealData: DealData): Promise<void>;
  editDeal(): Promise<void>;
  saveDeal(): Promise<void>;
  deleteDeal(): Promise<void>;
  searchDeals(searchTerm: string): Promise<void>;
  advancedSearch(searchCriteria: any): Promise<void>;
  filterByStage(stage: string): Promise<void>;
  getDealCount(): Promise<number>;
  openDeal(dealName: string): Promise<void>;
  getDealTitle(): Promise<string>;
  isDuplicateWarningShown(): Promise<boolean>;
  getFieldValue(fieldLabel: string): Promise<string>;
  addContact(contactName: string): Promise<void>;
  sendEmail(emailData: EmailData): Promise<void>;
  logNote(noteText: string): Promise<void>;
  hasAtRiskIndicator(): Promise<boolean>;
  getStageProgress(): Promise<number>;
  massUpdate(dealIds: string[], updateData: any): Promise<void>;
  exportDeals(): Promise<any>;
  getSummaryStats(): Promise<any>;
}

export declare class ContactPage extends BasePage {
  goto(): Promise<void>;
  createContact(contactData: ContactData): Promise<void>;
  searchContacts(searchTerm: string): Promise<void>;
  openContact(contactName: string): Promise<void>;
  editContact(): Promise<void>;
  deleteContact(): Promise<void>;
  linkToAccount(accountName: string): Promise<void>;
  addToDeal(dealName: string): Promise<void>;
  removeFromDeal(dealName: string): Promise<void>;
  sendEmail(emailData: EmailData): Promise<void>;
  logCall(callData: CallData): Promise<void>;
  getContactFullName(): Promise<string>;
  getRelatedDealsCount(): Promise<number>;
  isDecisionMaker(): Promise<boolean>;
  isInfluencer(): Promise<boolean>;
  getContactRole(): Promise<string>;
  massUpdateContacts(contactIds: string[], updateData: any): Promise<void>;
  getFieldValue(fieldLabel: string): Promise<string>;
  validateRequiredFields(): Promise<boolean>;
}

export declare class DocumentPage extends BasePage {
  goto(): Promise<void>;
  uploadDocument(documentData: DocumentData): Promise<void>;
  createFromTemplate(templateName: string, documentData: DocumentData): Promise<void>;
  waitForUploadComplete(): Promise<void>;
  searchDocuments(searchTerm: string): Promise<void>;
  filterDocuments(filters: any): Promise<void>;
  openDocument(documentName: string): Promise<void>;
  downloadDocument(): Promise<any>;
  previewDocument(): Promise<void>;
  closePreview(): Promise<void>;
  checkOutDocument(): Promise<void>;
  checkInDocument(checkInData: any): Promise<void>;
  linkToDeal(dealName: string): Promise<void>;
  linkToContact(contactName: string): Promise<void>;
  addTag(tag: string): Promise<void>;
  removeTag(tag: string): Promise<void>;
  getDocumentTags(): Promise<string[]>;
  getVersionHistory(): Promise<any[]>;
  restoreVersion(versionNumber: string): Promise<void>;
  addMetadata(key: string, value: string): Promise<void>;
  massDeleteDocuments(documentIds: string[]): Promise<void>;
  getDocumentStatus(): Promise<string>;
  getFieldValue(fieldLabel: string): Promise<string>;
}

export declare class ChecklistPage extends BasePage {
  gotoTemplates(): Promise<void>;
  gotoChecklists(): Promise<void>;
  createTemplate(templateData: ChecklistTemplateData): Promise<void>;
  applyTemplate(templateName: string, checklistData: ChecklistData): Promise<void>;
  completeItem(itemIndex: number, notes?: string): Promise<void>;
  uncompleteItem(itemIndex: number): Promise<void>;
  getChecklistProgress(): Promise<ChecklistProgress>;
  getChecklistItems(): Promise<ChecklistItem[]>;
  filterChecklists(filters: any): Promise<void>;
  previewTemplate(templateName: string): Promise<void>;
  closePreview(): Promise<void>;
  duplicateTemplate(templateName: string, newName: string): Promise<void>;
  searchTemplates(searchTerm: string): Promise<void>;
  getCompletionStats(): Promise<any>;
  massCompleteItems(itemIndices: number[]): Promise<void>;
  addItemToChecklist(itemData: ChecklistItemData): Promise<void>;
  removeItemFromChecklist(itemIndex: number): Promise<void>;
  hasOverdueItems(): Promise<boolean>;
  getOverdueItemsCount(): Promise<number>;
}

export declare class PipelinePage extends BasePage {
  goto(): Promise<void>;
  getStages(): Promise<StageData[]>;
  getDealsInStage(stageName: string): Promise<DealCardData[]>;
  getStageElement(stageName: string): Promise<ElementHandle>;
  getDealCard(dealName: string): Promise<ElementHandle>;
  dragDealToStage(dealName: string, targetStageName: string): Promise<void>;
  moveDealMobile(dealName: string, targetStageName: string): Promise<void>;
  handleWipWarning(proceed?: boolean): Promise<void>;
  filterDeals(filters: PipelineFilters): Promise<void>;
  clearFilters(): Promise<void>;
  changeViewMode(viewMode: string): Promise<void>;
  getSummaryStats(): Promise<any>;
  isStageAtWipLimit(stageName: string): Promise<boolean>;
  getStaleDeals(): Promise<any[]>;
  quickEditDeal(dealName: string): Promise<void>;
  addDealToStage(stageName: string): Promise<void>;
  canTransitionToStage(dealName: string, targetStageName: string): Promise<boolean>;
  getStageMetrics(stageName: string): Promise<any>;
  selectDealWithKeyboard(dealName: string): Promise<void>;
  moveDealWithKeyboard(direction: string): Promise<void>;
  isLoading(): Promise<boolean>;
  waitForPipelineLoad(): Promise<void>;
  getNotificationMessage(): Promise<string>;
  hasNotification(type: string): Promise<boolean>;
  getAriaAnnouncement(): Promise<string>;
  openKeyboardShortcuts(): Promise<void>;
}

export declare class NavigationComponent extends BasePage {
  navigateToDeals(): Promise<void>;
  navigateToAccounts(): Promise<void>;
  navigateToContacts(): Promise<void>;
  navigateToOpportunities(): Promise<void>;
  navigateToLeads(): Promise<void>;
  hoverOverMenu(selector: string): Promise<void>;
  openQuickCreate(): Promise<void>;
  quickCreate(moduleName: string): Promise<void>;
  globalSearch(searchTerm: string): Promise<void>;
  openUserMenu(): Promise<void>;
  goToProfile(): Promise<void>;
  goToPreferences(): Promise<void>;
  logout(): Promise<void>;
  isNavigationVisible(): Promise<boolean>;
  toggleMobileMenu(): Promise<void>;
  isMobileMenuOpen(): Promise<boolean>;
  getNotificationCount(): Promise<number>;
  openNotifications(): Promise<void>;
  isLoggedIn(): Promise<boolean>;
  getCurrentModule(): Promise<string>;
  navigateToModule(menuName: string, moduleName: string): Promise<void>;
}