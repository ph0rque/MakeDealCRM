const BasePage = require('./BasePage');

/**
 * ChecklistPage - Page object for checklist template creation and application
 */
class ChecklistPage extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      // List view
      createTemplateButton: 'a:has-text("Create Template")',
      templatesListView: '.templates-list-view',
      checklistsListView: '.checklists-list-view',
      searchInput: 'input[name="basic_search"]',
      searchButton: 'input[value="Search"]',
      
      // Template form fields
      templateNameInput: 'input[name="template_name"]',
      templateDescriptionTextarea: 'textarea[name="template_description"]',
      templateCategorySelect: 'select[name="template_category"]',
      templateTypeSelect: 'select[name="checklist_type"]',
      isActiveCheckbox: 'input[name="is_active"]',
      
      // Checklist items
      addItemButton: 'button:has-text("Add Item")',
      checklistItemContainer: '.checklist-item',
      itemTitleInput: 'input[name="item_title[]"]',
      itemDescriptionInput: 'input[name="item_description[]"]',
      itemOrderInput: 'input[name="item_order[]"]',
      itemRequiredCheckbox: 'input[name="item_required[]"]',
      itemDueDaysInput: 'input[name="item_due_days[]"]',
      removeItemButton: '.remove-item',
      moveItemUpButton: '.move-up',
      moveItemDownButton: '.move-down',
      
      // Buttons
      saveTemplateButton: 'input[value="Save Template"]',
      saveChecklistButton: 'input[value="Save Checklist"]',
      cancelButton: 'input[value="Cancel"]',
      editButton: 'input[value="Edit"]',
      deleteButton: 'input[value="Delete"]',
      duplicateButton: 'input[value="Duplicate"]',
      applyTemplateButton: 'button:has-text("Apply Template")',
      
      // Detail view
      detailViewTitle: 'h2',
      checklistProgress: '.checklist-progress',
      progressBar: '.progress-bar',
      progressText: '.progress-text',
      
      // Checklist execution
      checklistTable: '.checklist-table',
      checklistRow: '.checklist-row',
      checkboxComplete: 'input[type="checkbox"].item-complete',
      completedByText: '.completed-by',
      completedDateText: '.completed-date',
      itemNotes: 'textarea.item-notes',
      saveNotesButton: 'button.save-notes',
      
      // Status indicators
      overdueIndicator: '.overdue-indicator',
      upcomingIndicator: '.upcoming-indicator',
      completedIndicator: '.completed-indicator',
      
      // Template gallery
      templateGallery: '.template-gallery',
      templateCard: '.template-card',
      templateTitle: '.template-title',
      templateDescription: '.template-description',
      useTemplateButton: '.use-template',
      previewTemplateButton: '.preview-template',
      
      // Filters
      statusFilter: 'select[name="status_filter"]',
      categoryFilter: 'select[name="category_filter"]',
      assignedUserFilter: 'select[name="assigned_user_filter"]',
      dueDateFilter: 'input[name="due_date_filter"]',
      
      // Related records
      relatedDealSelect: 'select[name="related_deal_id"]',
      relatedContactSelect: 'select[name="related_contact_id"]',
      relatedAccountSelect: 'select[name="related_account_id"]',
      
      // Bulk actions
      massSelectAll: 'input[name="massall"]',
      massSelect: 'input[name="mass[]"]',
      actionSelect: 'select[name="action_select"]',
      goButton: 'input[value="Go"]',
      
      // Notifications
      reminderCheckbox: 'input[name="send_reminders"]',
      reminderDaysInput: 'input[name="reminder_days"]',
      notificationEmailsInput: 'input[name="notification_emails"]',
      
      // Validation
      errorMessage: '.error-message',
      successMessage: '.alert-success',
      requiredFieldError: '.required-error',
      
      // Preview modal
      previewModal: '#checklistPreviewModal',
      previewModalClose: '#checklistPreviewModal .close',
      previewContent: '.preview-content',
      
      // Reports
      completionRateText: '.completion-rate',
      averageTimeText: '.average-completion-time',
      overdueCountText: '.overdue-count'
    };
  }

  /**
   * Navigate to Checklist Templates list view
   */
  async gotoTemplates() {
    await this.navigate('/index.php?module=ChecklistTemplates&action=index');
    await this.waitForElement(this.selectors.templatesListView);
  }

  /**
   * Navigate to Checklists list view
   */
  async gotoChecklists() {
    await this.navigate('/index.php?module=Checklists&action=index');
    await this.waitForElement(this.selectors.checklistsListView);
  }

  /**
   * Create a new checklist template
   * @param {Object} templateData - The template data
   */
  async createTemplate(templateData) {
    await this.clickElement(this.selectors.createTemplateButton);
    await this.waitForElement(this.selectors.templateNameInput);
    
    // Fill template information
    if (templateData.name) await this.fillField(this.selectors.templateNameInput, templateData.name);
    if (templateData.description) await this.fillField(this.selectors.templateDescriptionTextarea, templateData.description);
    if (templateData.category) await this.selectOption(this.selectors.templateCategorySelect, templateData.category);
    if (templateData.type) await this.selectOption(this.selectors.templateTypeSelect, templateData.type);
    
    // Add checklist items
    if (templateData.items && templateData.items.length > 0) {
      for (let i = 0; i < templateData.items.length; i++) {
        if (i > 0) {
          await this.clickElement(this.selectors.addItemButton);
          await this.page.waitForTimeout(300);
        }
        
        const item = templateData.items[i];
        const itemIndex = i;
        
        await this.fillField(`${this.selectors.itemTitleInput}:nth-of-type(${itemIndex + 1})`, item.title);
        
        if (item.description) {
          await this.fillField(`${this.selectors.itemDescriptionInput}:nth-of-type(${itemIndex + 1})`, item.description);
        }
        
        if (item.order) {
          await this.fillField(`${this.selectors.itemOrderInput}:nth-of-type(${itemIndex + 1})`, item.order.toString());
        }
        
        if (item.required) {
          await this.clickElement(`${this.selectors.itemRequiredCheckbox}:nth-of-type(${itemIndex + 1})`);
        }
        
        if (item.dueDays) {
          await this.fillField(`${this.selectors.itemDueDaysInput}:nth-of-type(${itemIndex + 1})`, item.dueDays.toString());
        }
      }
    }
    
    // Save template
    await this.clickElement(this.selectors.saveTemplateButton);
    await this.waitForPageLoad();
  }

  /**
   * Apply template to create a checklist
   * @param {string} templateName - The template name
   * @param {Object} checklistData - Additional checklist data
   */
  async applyTemplate(templateName, checklistData) {
    await this.gotoChecklists();
    await this.clickElement(this.selectors.applyTemplateButton);
    await this.waitForElement(this.selectors.templateGallery);
    
    // Select template
    await this.clickElement(`${this.selectors.templateCard}:has-text("${templateName}") ${this.selectors.useTemplateButton}`);
    await this.waitForElement(this.selectors.relatedDealSelect);
    
    // Link to related records
    if (checklistData.dealId) await this.selectOption(this.selectors.relatedDealSelect, checklistData.dealId);
    if (checklistData.contactId) await this.selectOption(this.selectors.relatedContactSelect, checklistData.contactId);
    if (checklistData.accountId) await this.selectOption(this.selectors.relatedAccountSelect, checklistData.accountId);
    
    // Set notifications
    if (checklistData.sendReminders) {
      await this.clickElement(this.selectors.reminderCheckbox);
      if (checklistData.reminderDays) {
        await this.fillField(this.selectors.reminderDaysInput, checklistData.reminderDays.toString());
      }
      if (checklistData.notificationEmails) {
        await this.fillField(this.selectors.notificationEmailsInput, checklistData.notificationEmails);
      }
    }
    
    await this.clickElement(this.selectors.saveChecklistButton);
    await this.waitForPageLoad();
  }

  /**
   * Complete a checklist item
   * @param {number} itemIndex - The item index (0-based)
   * @param {string} notes - Optional notes
   */
  async completeItem(itemIndex, notes = '') {
    const checkbox = await this.page.$(`${this.selectors.checklistRow}:nth-child(${itemIndex + 1}) ${this.selectors.checkboxComplete}`);
    
    if (checkbox && !(await checkbox.isChecked())) {
      await checkbox.check();
      
      if (notes) {
        await this.fillField(`${this.selectors.checklistRow}:nth-child(${itemIndex + 1}) ${this.selectors.itemNotes}`, notes);
        await this.clickElement(`${this.selectors.checklistRow}:nth-child(${itemIndex + 1}) ${this.selectors.saveNotesButton}`);
      }
      
      await this.page.waitForTimeout(500);
    }
  }

  /**
   * Uncomplete a checklist item
   * @param {number} itemIndex - The item index (0-based)
   */
  async uncompleteItem(itemIndex) {
    const checkbox = await this.page.$(`${this.selectors.checklistRow}:nth-child(${itemIndex + 1}) ${this.selectors.checkboxComplete}`);
    
    if (checkbox && await checkbox.isChecked()) {
      await checkbox.uncheck();
      await this.page.waitForTimeout(500);
    }
  }

  /**
   * Get checklist progress
   * @returns {Promise<Object>} Progress information
   */
  async getChecklistProgress() {
    const progressText = await this.getText(this.selectors.progressText);
    const progressBar = await this.page.$(this.selectors.progressBar);
    const progressStyle = await progressBar.getAttribute('style');
    const progressMatch = progressStyle.match(/width:\s*(\d+)%/);
    
    return {
      text: progressText,
      percentage: progressMatch ? parseInt(progressMatch[1]) : 0
    };
  }

  /**
   * Get checklist items
   * @returns {Promise<Object[]>} Array of checklist items
   */
  async getChecklistItems() {
    const items = [];
    const rows = await this.page.$$(this.selectors.checklistRow);
    
    for (const row of rows) {
      const item = {
        title: await row.$eval('.item-title', el => el.textContent.trim()),
        completed: await row.$eval(this.selectors.checkboxComplete, el => el.checked),
        required: await row.$eval('.required-indicator', el => el !== null).catch(() => false),
        overdue: await row.$eval(this.selectors.overdueIndicator, el => el !== null).catch(() => false)
      };
      
      // Get completion details if completed
      if (item.completed) {
        item.completedBy = await row.$eval(this.selectors.completedByText, el => el.textContent.trim()).catch(() => '');
        item.completedDate = await row.$eval(this.selectors.completedDateText, el => el.textContent.trim()).catch(() => '');
      }
      
      items.push(item);
    }
    
    return items;
  }

  /**
   * Filter checklists
   * @param {Object} filters - Filter criteria
   */
  async filterChecklists(filters) {
    if (filters.status) await this.selectOption(this.selectors.statusFilter, filters.status);
    if (filters.category) await this.selectOption(this.selectors.categoryFilter, filters.category);
    if (filters.assignedUser) await this.selectOption(this.selectors.assignedUserFilter, filters.assignedUser);
    if (filters.dueDate) await this.fillField(this.selectors.dueDateFilter, filters.dueDate);
    
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Preview template
   * @param {string} templateName - The template name
   */
  async previewTemplate(templateName) {
    const templateCard = await this.page.$(`${this.selectors.templateCard}:has-text("${templateName}")`);
    if (templateCard) {
      const previewButton = await templateCard.$(this.selectors.previewTemplateButton);
      if (previewButton) {
        await previewButton.click();
        await this.waitForElement(this.selectors.previewModal);
      }
    }
  }

  /**
   * Close preview modal
   */
  async closePreview() {
    await this.clickElement(this.selectors.previewModalClose);
    await this.page.waitForTimeout(300);
  }

  /**
   * Duplicate template
   * @param {string} templateName - The template name to duplicate
   * @param {string} newName - The new template name
   */
  async duplicateTemplate(templateName, newName) {
    await this.gotoTemplates();
    await this.searchTemplates(templateName);
    await this.clickElement(`a:has-text("${templateName}")`);
    await this.waitForElement(this.selectors.duplicateButton);
    
    await this.clickElement(this.selectors.duplicateButton);
    await this.waitForElement(this.selectors.templateNameInput);
    
    await this.fillField(this.selectors.templateNameInput, newName);
    await this.clickElement(this.selectors.saveTemplateButton);
    await this.waitForPageLoad();
  }

  /**
   * Search for templates
   * @param {string} searchTerm - The search term
   */
  async searchTemplates(searchTerm) {
    await this.fillField(this.selectors.searchInput, searchTerm);
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Get completion statistics
   * @returns {Promise<Object>} Completion statistics
   */
  async getCompletionStats() {
    return {
      completionRate: await this.getText(this.selectors.completionRateText),
      averageTime: await this.getText(this.selectors.averageTimeText),
      overdueCount: await this.getText(this.selectors.overdueCountText)
    };
  }

  /**
   * Mass complete checklist items
   * @param {number[]} itemIndices - Array of item indices to complete
   */
  async massCompleteItems(itemIndices) {
    for (const index of itemIndices) {
      await this.completeItem(index);
    }
  }

  /**
   * Add item to existing checklist
   * @param {Object} itemData - The item data
   */
  async addItemToChecklist(itemData) {
    await this.clickElement(this.selectors.editButton);
    await this.waitForElement(this.selectors.addItemButton);
    
    await this.clickElement(this.selectors.addItemButton);
    await this.page.waitForTimeout(300);
    
    // Fill new item data
    const newItemIndex = await this.page.$$(this.selectors.checklistItemContainer).then(items => items.length - 1);
    
    await this.fillField(`${this.selectors.itemTitleInput}:nth-of-type(${newItemIndex + 1})`, itemData.title);
    
    if (itemData.description) {
      await this.fillField(`${this.selectors.itemDescriptionInput}:nth-of-type(${newItemIndex + 1})`, itemData.description);
    }
    
    if (itemData.required) {
      await this.clickElement(`${this.selectors.itemRequiredCheckbox}:nth-of-type(${newItemIndex + 1})`);
    }
    
    await this.clickElement(this.selectors.saveChecklistButton);
    await this.waitForPageLoad();
  }

  /**
   * Remove item from checklist
   * @param {number} itemIndex - The item index to remove
   */
  async removeItemFromChecklist(itemIndex) {
    await this.clickElement(this.selectors.editButton);
    await this.waitForElement(this.selectors.removeItemButton);
    
    const removeButton = await this.page.$(`${this.selectors.checklistItemContainer}:nth-child(${itemIndex + 1}) ${this.selectors.removeItemButton}`);
    if (removeButton) {
      await removeButton.click();
      await this.handleAlert(true);
      await this.page.waitForTimeout(300);
      
      await this.clickElement(this.selectors.saveChecklistButton);
      await this.waitForPageLoad();
    }
  }

  /**
   * Check if checklist has overdue items
   * @returns {Promise<boolean>} True if has overdue items
   */
  async hasOverdueItems() {
    return await this.isVisible(this.selectors.overdueIndicator);
  }

  /**
   * Get overdue items count
   * @returns {Promise<number>} Number of overdue items
   */
  async getOverdueItemsCount() {
    const overdueItems = await this.page.$$(this.selectors.overdueIndicator);
    return overdueItems.length;
  }
}

module.exports = ChecklistPage;