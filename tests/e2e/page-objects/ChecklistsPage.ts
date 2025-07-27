import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class ChecklistsPage extends BasePage {
  // Selectors
  private readonly checklistContainer = '.checklist-container, #checklist-panel';
  private readonly checklistItems = '.checklist-item, .checklist-task';
  private readonly checkboxes = 'input[type="checkbox"].checklist-checkbox';
  private readonly addItemButton = '.add-checklist-item, [data-action="add-item"]';
  private readonly newItemInput = 'input[name="checklist_item_name"]';
  private readonly saveItemButton = '.save-item-btn, [data-action="save-item"]';
  private readonly deleteItemButton = '.delete-item-btn, [data-action="delete-item"]';
  private readonly editItemButton = '.edit-item-btn, [data-action="edit-item"]';
  private readonly checklistTemplate = 'select[name="checklist_template"]';
  private readonly applyTemplateButton = '.apply-template-btn';
  private readonly progressBar = '.checklist-progress, .progress-bar';
  private readonly progressText = '.progress-text, .progress-percentage';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to checklists (usually within deal detail view)
   */
  async gotoChecklistTab() {
    const checklistTab = 'a[href*="checklist"], .tab-checklist';
    await this.clickWithRetry(checklistTab);
    await this.waitForElement(this.checklistContainer);
  }

  /**
   * Add a new checklist item
   */
  async addChecklistItem(itemName: string, description?: string) {
    await this.clickWithRetry(this.addItemButton);
    await this.fillWithRetry(this.newItemInput, itemName);
    
    if (description) {
      const descriptionInput = 'textarea[name="checklist_item_description"]';
      if (await this.elementExists(descriptionInput)) {
        await this.fillWithRetry(descriptionInput, description);
      }
    }
    
    await this.clickWithRetry(this.saveItemButton);
    await this.waitForPageLoad();
  }

  /**
   * Check/uncheck a checklist item
   */
  async toggleChecklistItem(itemName: string) {
    const itemCheckbox = `${this.checklistItems}:has-text("${itemName}") ${this.checkboxes}`;
    await this.clickWithRetry(itemCheckbox);
    await this.wait(500); // Wait for state change
  }

  /**
   * Check if checklist item is completed
   */
  async isItemCompleted(itemName: string): Promise<boolean> {
    const itemCheckbox = `${this.checklistItems}:has-text("${itemName}") ${this.checkboxes}`;
    return await this.page.locator(itemCheckbox).isChecked();
  }

  /**
   * Delete a checklist item
   */
  async deleteChecklistItem(itemName: string) {
    const itemRow = `${this.checklistItems}:has-text("${itemName}")`;
    const deleteButton = `${itemRow} ${this.deleteItemButton}`;
    
    await this.clickWithRetry(deleteButton);
    await this.handleDialog(true); // Accept confirmation
    await this.waitForPageLoad();
  }

  /**
   * Edit a checklist item
   */
  async editChecklistItem(oldName: string, newName: string, newDescription?: string) {
    const itemRow = `${this.checklistItems}:has-text("${oldName}")`;
    const editButton = `${itemRow} ${this.editItemButton}`;
    
    await this.clickWithRetry(editButton);
    
    // Update the name
    const nameInput = 'input[name="checklist_item_name"]';
    await this.clearInput(nameInput);
    await this.fillWithRetry(nameInput, newName);
    
    if (newDescription) {
      const descriptionInput = 'textarea[name="checklist_item_description"]';
      if (await this.elementExists(descriptionInput)) {
        await this.clearInput(descriptionInput);
        await this.fillWithRetry(descriptionInput, newDescription);
      }
    }
    
    await this.clickWithRetry(this.saveItemButton);
    await this.waitForPageLoad();
  }

  /**
   * Get all checklist items
   */
  async getAllChecklistItems(): Promise<Array<{
    name: string;
    completed: boolean;
    description?: string;
  }>> {
    const items = await this.page.$$eval(
      this.checklistItems,
      (elements) => elements.map(el => {
        const nameEl = el.querySelector('.item-name, .checklist-item-name');
        const descEl = el.querySelector('.item-description, .checklist-item-description');
        const checkbox = el.querySelector('input[type="checkbox"]') as HTMLInputElement;
        
        return {
          name: nameEl?.textContent?.trim() || '',
          completed: checkbox?.checked || false,
          description: descEl?.textContent?.trim() || undefined
        };
      })
    );
    
    return items.filter(item => item.name);
  }

  /**
   * Get checklist completion percentage
   */
  async getCompletionPercentage(): Promise<number> {
    if (await this.elementExists(this.progressText)) {
      const progressText = await this.getTextContent(this.progressText);
      const match = progressText.match(/(\d+)%/);
      return match ? parseInt(match[1]) : 0;
    }
    
    // Calculate manually if no progress text
    const items = await this.getAllChecklistItems();
    if (items.length === 0) return 0;
    
    const completedCount = items.filter(item => item.completed).length;
    return Math.round((completedCount / items.length) * 100);
  }

  /**
   * Apply a checklist template
   */
  async applyTemplate(templateName: string) {
    if (await this.elementExists(this.checklistTemplate)) {
      await this.selectOption(this.checklistTemplate, templateName);
      await this.clickWithRetry(this.applyTemplateButton);
      await this.waitForPageLoad();
    }
  }

  /**
   * Get available templates
   */
  async getAvailableTemplates(): Promise<string[]> {
    if (await this.elementExists(this.checklistTemplate)) {
      const options = await this.page.$$eval(
        `${this.checklistTemplate} option`,
        options => options.map(opt => opt.textContent?.trim() || '').filter(Boolean)
      );
      return options;
    }
    return [];
  }

  /**
   * Reorder checklist items (if drag-and-drop is supported)
   */
  async reorderItem(itemName: string, targetPosition: number) {
    const sourceItem = this.page.locator(`${this.checklistItems}:has-text("${itemName}")`);
    const targetItem = this.page.locator(this.checklistItems).nth(targetPosition);
    
    if (await sourceItem.isVisible() && await targetItem.isVisible()) {
      await sourceItem.dragTo(targetItem);
      await this.wait(1000); // Wait for reorder to complete
    }
  }

  /**
   * Bulk check/uncheck items
   */
  async bulkToggleItems(action: 'check' | 'uncheck') {
    const allCheckboxes = await this.page.locator(this.checkboxes).all();
    
    for (const checkbox of allCheckboxes) {
      const isChecked = await checkbox.isChecked();
      
      if (action === 'check' && !isChecked) {
        await checkbox.click();
      } else if (action === 'uncheck' && isChecked) {
        await checkbox.click();
      }
      
      await this.wait(200); // Small delay between actions
    }
  }

  /**
   * Export checklist
   */
  async exportChecklist() {
    const exportButton = '.export-checklist-btn, [data-action="export"]';
    if (await this.elementExists(exportButton)) {
      await this.clickWithRetry(exportButton);
      await this.wait(2000); // Wait for download
    }
  }

  /**
   * Import checklist
   */
  async importChecklist(filePath: string) {
    const importButton = '.import-checklist-btn, [data-action="import"]';
    const fileInput = 'input[type="file"][name="checklist_import"]';
    
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
   * Filter checklist items
   */
  async filterItems(filter: 'all' | 'completed' | 'pending') {
    const filterSelect = 'select[name="checklist_filter"]';
    if (await this.elementExists(filterSelect)) {
      await this.selectOption(filterSelect, filter);
      await this.waitForPageLoad();
    }
  }

  /**
   * Search checklist items
   */
  async searchItems(searchTerm: string) {
    const searchInput = 'input[name="checklist_search"]';
    if (await this.elementExists(searchInput)) {
      await this.fillWithRetry(searchInput, searchTerm);
      await this.pressKey('Enter');
      await this.waitForPageLoad();
    }
  }

  /**
   * Check if checklist is empty
   */
  async isChecklistEmpty(): Promise<boolean> {
    const itemCount = await this.page.locator(this.checklistItems).count();
    return itemCount === 0;
  }
}