import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class StakeholdersPage extends BasePage {
  // Selectors
  private readonly stakeholdersContainer = '.stakeholders-container, #stakeholders-panel';
  private readonly stakeholdersList = '.stakeholders-list, .stakeholder-items';
  private readonly stakeholderItem = '.stakeholder-item, .stakeholder-row';
  private readonly addStakeholderButton = '.add-stakeholder-btn, [data-action="add-stakeholder"]';
  private readonly stakeholderForm = '.stakeholder-form, #stakeholder-edit-form';
  private readonly contactSelect = 'select[name="contact_id"], select[name="stakeholder_contact"]';
  private readonly roleSelect = 'select[name="role"], select[name="stakeholder_role"]';
  private readonly influenceSelect = 'select[name="influence_level"]';
  private readonly primaryCheckbox = 'input[name="is_primary"]';
  private readonly saveStakeholderButton = '.save-stakeholder-btn, input[value="Save"]';
  private readonly editButton = '.edit-stakeholder-btn, [data-action="edit"]';
  private readonly deleteButton = '.delete-stakeholder-btn, [data-action="delete"]';
  private readonly bulkActionsSelect = 'select[name="bulk_action"]';
  private readonly bulkExecuteButton = '.bulk-execute-btn';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to stakeholders tab (usually within deal detail view)
   */
  async gotoStakeholdersTab() {
    const stakeholdersTab = 'a[href*="stakeholder"], .tab-stakeholders';
    await this.clickWithRetry(stakeholdersTab);
    await this.waitForElement(this.stakeholdersContainer);
  }

  /**
   * Add a new stakeholder
   */
  async addStakeholder(stakeholderData: {
    contactName?: string;
    contactId?: string;
    role: string;
    influenceLevel?: string;
    isPrimary?: boolean;
    notes?: string;
  }) {
    await this.clickWithRetry(this.addStakeholderButton);
    await this.waitForElement(this.stakeholderForm);
    
    // Select contact
    if (stakeholderData.contactId) {
      await this.selectOption(this.contactSelect, stakeholderData.contactId);
    } else if (stakeholderData.contactName) {
      // If contact select is searchable
      const contactInput = 'input[name="contact_name"]';
      if (await this.elementExists(contactInput)) {
        await this.fillWithRetry(contactInput, stakeholderData.contactName);
        await this.wait(1000); // Wait for autocomplete
        const firstOption = '.autocomplete-option:first-child';
        if (await this.elementExists(firstOption)) {
          await this.clickWithRetry(firstOption);
        }
      }
    }
    
    // Set role
    await this.selectOption(this.roleSelect, stakeholderData.role);
    
    // Set influence level
    if (stakeholderData.influenceLevel && await this.elementExists(this.influenceSelect)) {
      await this.selectOption(this.influenceSelect, stakeholderData.influenceLevel);
    }
    
    // Set primary stakeholder
    if (stakeholderData.isPrimary && await this.elementExists(this.primaryCheckbox)) {
      await this.clickWithRetry(this.primaryCheckbox);
    }
    
    // Add notes
    if (stakeholderData.notes) {
      const notesTextarea = 'textarea[name="notes"], textarea[name="stakeholder_notes"]';
      if (await this.elementExists(notesTextarea)) {
        await this.fillWithRetry(notesTextarea, stakeholderData.notes);
      }
    }
    
    // Save stakeholder
    await this.clickWithRetry(this.saveStakeholderButton);
    await this.waitForPageLoad();
  }

  /**
   * Edit an existing stakeholder
   */
  async editStakeholder(contactName: string, updates: {
    role?: string;
    influenceLevel?: string;
    isPrimary?: boolean;
    notes?: string;
  }) {
    const stakeholderRow = `${this.stakeholderItem}:has-text("${contactName}")`;
    const editBtn = `${stakeholderRow} ${this.editButton}`;
    
    await this.clickWithRetry(editBtn);
    await this.waitForElement(this.stakeholderForm);
    
    // Update role
    if (updates.role) {
      await this.selectOption(this.roleSelect, updates.role);
    }
    
    // Update influence level
    if (updates.influenceLevel && await this.elementExists(this.influenceSelect)) {
      await this.selectOption(this.influenceSelect, updates.influenceLevel);
    }
    
    // Update primary status
    if (updates.isPrimary !== undefined && await this.elementExists(this.primaryCheckbox)) {
      const isChecked = await this.page.locator(this.primaryCheckbox).isChecked();
      if (updates.isPrimary !== isChecked) {
        await this.clickWithRetry(this.primaryCheckbox);
      }
    }
    
    // Update notes
    if (updates.notes) {
      const notesTextarea = 'textarea[name="notes"], textarea[name="stakeholder_notes"]';
      if (await this.elementExists(notesTextarea)) {
        await this.clearInput(notesTextarea);
        await this.fillWithRetry(notesTextarea, updates.notes);
      }
    }
    
    // Save changes
    await this.clickWithRetry(this.saveStakeholderButton);
    await this.waitForPageLoad();
  }

  /**
   * Delete a stakeholder
   */
  async deleteStakeholder(contactName: string) {
    const stakeholderRow = `${this.stakeholderItem}:has-text("${contactName}")`;
    const deleteBtn = `${stakeholderRow} ${this.deleteButton}`;
    
    await this.clickWithRetry(deleteBtn);
    await this.handleDialog(true); // Accept confirmation
    await this.waitForPageLoad();
  }

  /**
   * Get all stakeholders
   */
  async getAllStakeholders(): Promise<Array<{
    name: string;
    role: string;
    influenceLevel?: string;
    isPrimary: boolean;
    email?: string;
  }>> {
    const stakeholders = await this.page.$$eval(
      this.stakeholderItem,
      (elements) => elements.map(el => {
        const nameEl = el.querySelector('.stakeholder-name, .contact-name');
        const roleEl = el.querySelector('.stakeholder-role, .role');
        const influenceEl = el.querySelector('.influence-level');
        const primaryEl = el.querySelector('.primary-indicator, .is-primary');
        const emailEl = el.querySelector('.stakeholder-email, .email');
        
        return {
          name: nameEl?.textContent?.trim() || '',
          role: roleEl?.textContent?.trim() || '',
          influenceLevel: influenceEl?.textContent?.trim() || undefined,
          isPrimary: !!primaryEl,
          email: emailEl?.textContent?.trim() || undefined
        };
      })
    );
    
    return stakeholders.filter(stakeholder => stakeholder.name);
  }

  /**
   * Get stakeholder by name
   */
  async getStakeholder(contactName: string): Promise<{
    name: string;
    role: string;
    influenceLevel?: string;
    isPrimary: boolean;
    email?: string;
  } | null> {
    const stakeholders = await this.getAllStakeholders();
    return stakeholders.find(s => s.name === contactName) || null;
  }

  /**
   * Check if stakeholder exists
   */
  async stakeholderExists(contactName: string): Promise<boolean> {
    return await this.elementExists(`${this.stakeholderItem}:has-text("${contactName}")`);
  }

  /**
   * Get primary stakeholder
   */
  async getPrimaryStakeholder(): Promise<string | null> {
    const stakeholders = await this.getAllStakeholders();
    const primary = stakeholders.find(s => s.isPrimary);
    return primary?.name || null;
  }

  /**
   * Set stakeholder as primary
   */
  async setPrimaryStakeholder(contactName: string) {
    const stakeholderRow = `${this.stakeholderItem}:has-text("${contactName}")`;
    const primaryCheckbox = `${stakeholderRow} input[name="is_primary"]`;
    
    if (await this.elementExists(primaryCheckbox)) {
      const isChecked = await this.page.locator(primaryCheckbox).isChecked();
      if (!isChecked) {
        await this.clickWithRetry(primaryCheckbox);
        await this.waitForPageLoad();
      }
    }
  }

  /**
   * Filter stakeholders by role
   */
  async filterByRole(role: string) {
    const roleFilter = 'select[name="role_filter"]';
    if (await this.elementExists(roleFilter)) {
      await this.selectOption(roleFilter, role);
      await this.waitForPageLoad();
    }
  }

  /**
   * Filter stakeholders by influence level
   */
  async filterByInfluence(influenceLevel: string) {
    const influenceFilter = 'select[name="influence_filter"]';
    if (await this.elementExists(influenceFilter)) {
      await this.selectOption(influenceFilter, influenceLevel);
      await this.waitForPageLoad();
    }
  }

  /**
   * Search stakeholders
   */
  async searchStakeholders(searchTerm: string) {
    const searchInput = 'input[name="stakeholder_search"]';
    if (await this.elementExists(searchInput)) {
      await this.fillWithRetry(searchInput, searchTerm);
      await this.pressKey('Enter');
      await this.waitForPageLoad();
    }
  }

  /**
   * Bulk select stakeholders
   */
  async selectStakeholders(contactNames: string[]) {
    for (const name of contactNames) {
      const checkbox = `${this.stakeholderItem}:has-text("${name}") input[type="checkbox"]`;
      if (await this.elementExists(checkbox)) {
        await this.clickWithRetry(checkbox);
      }
    }
  }

  /**
   * Perform bulk action
   */
  async performBulkAction(action: string) {
    await this.selectOption(this.bulkActionsSelect, action);
    await this.clickWithRetry(this.bulkExecuteButton);
    await this.handleDialog(true); // Accept confirmation
    await this.waitForPageLoad();
  }

  /**
   * Export stakeholders
   */
  async exportStakeholders() {
    const exportButton = '.export-stakeholders-btn, [data-action="export"]';
    if (await this.elementExists(exportButton)) {
      await this.clickWithRetry(exportButton);
      await this.wait(2000); // Wait for download
    }
  }

  /**
   * Import stakeholders
   */
  async importStakeholders(filePath: string) {
    const importButton = '.import-stakeholders-btn, [data-action="import"]';
    const fileInput = 'input[type="file"][name="stakeholders_import"]';
    
    if (await this.elementExists(importButton)) {
      await this.clickWithRetry(importButton);
      await this.uploadFile(fileInput, filePath);
      
      const confirmButton = '.confirm-import-btn';
      if (await this.elementExists(confirmButton)) {
        await this.clickWithRetry(confirmButton);
        await this.waitForPageLoad();
      }
    }
  }

  /**
   * Get stakeholders count by role
   */
  async getStakeholdersCountByRole(): Promise<Record<string, number>> {
    const stakeholders = await this.getAllStakeholders();
    const counts: Record<string, number> = {};
    
    stakeholders.forEach(stakeholder => {
      counts[stakeholder.role] = (counts[stakeholder.role] || 0) + 1;
    });
    
    return counts;
  }

  /**
   * Check if stakeholders list is empty
   */
  async isStakeholdersEmpty(): Promise<boolean> {
    const itemCount = await this.page.locator(this.stakeholderItem).count();
    return itemCount === 0;
  }
}