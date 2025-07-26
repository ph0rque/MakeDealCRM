const BasePage = require('./BasePage');

/**
 * ContactPage - Page object for contact management within deals
 */
class ContactPage extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      // List view
      createButton: 'a:has-text("Create")',
      listViewTable: '.list-view-rounded-corners',
      searchInput: 'input[name="basic_search"]',
      searchButton: 'input[value="Search"]',
      
      // Form fields
      firstNameInput: 'input[name="first_name"]',
      lastNameInput: 'input[name="last_name"]',
      titleInput: 'input[name="title"]',
      departmentInput: 'input[name="department"]',
      accountSelect: 'select[name="account_id"]',
      accountInput: 'input[name="account_name"]',
      accountSelectButton: 'button[data-toggle="modal"][data-target*="account"]',
      emailInput: 'input[name="email1"]',
      phoneWorkInput: 'input[name="phone_work"]',
      phoneMobileInput: 'input[name="phone_mobile"]',
      addressStreetInput: 'textarea[name="primary_address_street"]',
      addressCityInput: 'input[name="primary_address_city"]',
      addressStateInput: 'input[name="primary_address_state"]',
      addressPostalCodeInput: 'input[name="primary_address_postalcode"]',
      addressCountryInput: 'input[name="primary_address_country"]',
      descriptionTextarea: 'textarea[name="description"]',
      assignedUserSelect: 'select[name="assigned_user_id"]',
      leadSourceSelect: 'select[name="lead_source"]',
      
      // Role/stakeholder fields
      roleSelect: 'select[name="role_c"]',
      decisionMakerCheckbox: 'input[name="decision_maker_c"]',
      influencerCheckbox: 'input[name="influencer_c"]',
      stakeholderTypeSelect: 'select[name="stakeholder_type_c"]',
      
      // Buttons
      saveButton: 'input[value="Save"]',
      saveAndNewButton: 'input[value="Save and New"]',
      cancelButton: 'input[value="Cancel"]',
      editButton: 'input[value="Edit"]',
      deleteButton: 'input[value="Delete"]',
      
      // Detail view
      detailViewTitle: 'h2',
      fieldLabel: '.field-label',
      fieldValue: '.field-value',
      
      // Subpanels
      dealsSubpanel: 'a:has-text("Deals")',
      activitiesSubpanel: 'a:has-text("Activities")',
      historySubpanel: 'a:has-text("History")',
      documentsSubpanel: 'a:has-text("Documents")',
      emailsSubpanel: 'a:has-text("Emails")',
      
      // Quick actions
      composeEmailButton: 'button:has-text("Compose Email")',
      logCallButton: 'button:has-text("Log Call")',
      scheduleMeetingButton: 'button:has-text("Schedule Meeting")',
      
      // Related deals section
      relatedDealsTable: '.related-deals-table',
      addToDealButton: 'button:has-text("Add to Deal")',
      removeFromDealButton: 'button:has-text("Remove from Deal")',
      
      // Search/select modals
      searchModal: '.modal-dialog',
      modalSearchInput: '.modal-dialog input[name="search"]',
      modalSearchButton: '.modal-dialog button:has-text("Search")',
      modalSelectButton: '.modal-dialog button:has-text("Select")',
      modalCancelButton: '.modal-dialog button:has-text("Cancel")',
      
      // Validation
      errorMessage: '.error-message',
      successMessage: '.alert-success',
      requiredFieldError: '.required-error',
      
      // Mass actions
      massSelectAll: 'input[name="massall"]',
      massSelect: 'input[name="mass[]"]',
      actionSelect: 'select[name="action_select"]',
      goButton: 'input[value="Go"]'
    };
  }

  /**
   * Navigate to Contacts list view
   */
  async goto() {
    await this.navigate('/index.php?module=Contacts&action=index');
    await this.waitForElement(this.selectors.listViewTable);
  }

  /**
   * Create a new contact
   * @param {Object} contactData - The contact data
   */
  async createContact(contactData) {
    await this.clickElement(this.selectors.createButton);
    await this.waitForElement(this.selectors.firstNameInput);
    
    // Fill name information
    if (contactData.firstName) await this.fillField(this.selectors.firstNameInput, contactData.firstName);
    if (contactData.lastName) await this.fillField(this.selectors.lastNameInput, contactData.lastName);
    if (contactData.title) await this.fillField(this.selectors.titleInput, contactData.title);
    if (contactData.department) await this.fillField(this.selectors.departmentInput, contactData.department);
    
    // Fill contact information
    if (contactData.email) await this.fillField(this.selectors.emailInput, contactData.email);
    if (contactData.phoneWork) await this.fillField(this.selectors.phoneWorkInput, contactData.phoneWork);
    if (contactData.phoneMobile) await this.fillField(this.selectors.phoneMobileInput, contactData.phoneMobile);
    
    // Fill address information
    if (contactData.addressStreet) await this.fillField(this.selectors.addressStreetInput, contactData.addressStreet);
    if (contactData.addressCity) await this.fillField(this.selectors.addressCityInput, contactData.addressCity);
    if (contactData.addressState) await this.fillField(this.selectors.addressStateInput, contactData.addressState);
    if (contactData.addressPostalCode) await this.fillField(this.selectors.addressPostalCodeInput, contactData.addressPostalCode);
    if (contactData.addressCountry) await this.fillField(this.selectors.addressCountryInput, contactData.addressCountry);
    
    // Fill stakeholder information
    if (contactData.role) await this.selectOption(this.selectors.roleSelect, contactData.role);
    if (contactData.isDecisionMaker) await this.clickElement(this.selectors.decisionMakerCheckbox);
    if (contactData.isInfluencer) await this.clickElement(this.selectors.influencerCheckbox);
    if (contactData.stakeholderType) await this.selectOption(this.selectors.stakeholderTypeSelect, contactData.stakeholderType);
    
    // Additional fields
    if (contactData.description) await this.fillField(this.selectors.descriptionTextarea, contactData.description);
    if (contactData.leadSource) await this.selectOption(this.selectors.leadSourceSelect, contactData.leadSource);
    if (contactData.assignedUserId) await this.selectOption(this.selectors.assignedUserSelect, contactData.assignedUserId);
    
    // Save
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Search for contacts
   * @param {string} searchTerm - The search term
   */
  async searchContacts(searchTerm) {
    await this.fillField(this.selectors.searchInput, searchTerm);
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Open a contact by name
   * @param {string} contactName - The contact name
   */
  async openContact(contactName) {
    await this.clickElement(`a:has-text("${contactName}")`);
    await this.waitForElement(this.selectors.detailViewTitle);
  }

  /**
   * Edit current contact
   */
  async editContact() {
    await this.clickElement(this.selectors.editButton);
    await this.waitForElement(this.selectors.firstNameInput);
  }

  /**
   * Delete current contact
   */
  async deleteContact() {
    await this.handleAlert(true); // Pre-setup alert handler
    await this.clickElement(this.selectors.deleteButton);
    await this.waitForPageLoad();
  }

  /**
   * Link contact to an account
   * @param {string} accountName - The account name
   */
  async linkToAccount(accountName) {
    await this.clickElement(this.selectors.accountSelectButton);
    await this.waitForElement(this.selectors.searchModal);
    
    await this.fillField(this.selectors.modalSearchInput, accountName);
    await this.clickElement(this.selectors.modalSearchButton);
    await this.page.waitForTimeout(1000);
    
    // Select first result
    await this.clickElement('input[type="radio"]:first-child');
    await this.clickElement(this.selectors.modalSelectButton);
  }

  /**
   * Add contact to a deal
   * @param {string} dealName - The deal name
   */
  async addToDeal(dealName) {
    await this.clickElement(this.selectors.addToDealButton);
    await this.waitForElement(this.selectors.searchModal);
    
    await this.fillField(this.selectors.modalSearchInput, dealName);
    await this.clickElement(this.selectors.modalSearchButton);
    await this.page.waitForTimeout(1000);
    
    // Select the deal
    await this.clickElement(`input[type="checkbox"][data-name="${dealName}"]`);
    await this.clickElement(this.selectors.modalSelectButton);
    await this.waitForPageLoad();
  }

  /**
   * Remove contact from a deal
   * @param {string} dealName - The deal name
   */
  async removeFromDeal(dealName) {
    const dealRow = await this.page.$(`tr:has-text("${dealName}")`);
    if (dealRow) {
      const removeButton = await dealRow.$(this.selectors.removeFromDealButton);
      if (removeButton) {
        await removeButton.click();
        await this.handleAlert(true);
        await this.waitForPageLoad();
      }
    }
  }

  /**
   * Send email to contact
   * @param {Object} emailData - The email data
   */
  async sendEmail(emailData) {
    await this.clickElement(this.selectors.composeEmailButton);
    await this.waitForElement('#composeEmail');
    
    if (emailData.subject) await this.fillField('input[name="subject"]', emailData.subject);
    if (emailData.body) await this.fillField('textarea[name="body"]', emailData.body);
    
    await this.clickElement('button:has-text("Send")');
    await this.waitForPageLoad();
  }

  /**
   * Log a call
   * @param {Object} callData - The call data
   */
  async logCall(callData) {
    await this.clickElement(this.selectors.logCallButton);
    await this.waitForElement('input[name="name"]');
    
    if (callData.subject) await this.fillField('input[name="name"]', callData.subject);
    if (callData.duration) await this.fillField('input[name="duration_minutes"]', callData.duration);
    if (callData.description) await this.fillField('textarea[name="description"]', callData.description);
    
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Get contact's full name from detail view
   * @returns {Promise<string>} The full name
   */
  async getContactFullName() {
    return await this.getText(this.selectors.detailViewTitle);
  }

  /**
   * Get related deals count
   * @returns {Promise<number>} The number of related deals
   */
  async getRelatedDealsCount() {
    await this.clickElement(this.selectors.dealsSubpanel);
    await this.page.waitForTimeout(500);
    
    const dealRows = await this.page.$$(`${this.selectors.relatedDealsTable} tbody tr`);
    return dealRows.length;
  }

  /**
   * Check if contact is a decision maker
   * @returns {Promise<boolean>} True if decision maker
   */
  async isDecisionMaker() {
    const checkbox = await this.page.$(this.selectors.decisionMakerCheckbox);
    return await checkbox.isChecked();
  }

  /**
   * Check if contact is an influencer
   * @returns {Promise<boolean>} True if influencer
   */
  async isInfluencer() {
    const checkbox = await this.page.$(this.selectors.influencerCheckbox);
    return await checkbox.isChecked();
  }

  /**
   * Get contact's role
   * @returns {Promise<string>} The role
   */
  async getContactRole() {
    return await this.getFieldValue('Role');
  }

  /**
   * Mass update contacts
   * @param {string[]} contactIds - Array of contact IDs
   * @param {Object} updateData - The data to update
   */
  async massUpdateContacts(contactIds, updateData) {
    // Select contacts
    for (const contactId of contactIds) {
      await this.clickElement(`input[name="mass[]"][value="${contactId}"]`);
    }
    
    // Select mass update action
    await this.selectOption(this.selectors.actionSelect, 'mass_update');
    await this.clickElement(this.selectors.goButton);
    
    // Update fields
    if (updateData.assignedUserId) {
      await this.selectOption(this.selectors.assignedUserSelect, updateData.assignedUserId);
    }
    if (updateData.leadSource) {
      await this.selectOption(this.selectors.leadSourceSelect, updateData.leadSource);
    }
    
    await this.clickElement('input[value="Update"]');
    await this.waitForPageLoad();
  }

  /**
   * Get field value from detail view
   * @param {string} fieldLabel - The field label
   * @returns {Promise<string>} The field value
   */
  async getFieldValue(fieldLabel) {
    const fieldLabelElement = await this.page.$(`${this.selectors.fieldLabel}:has-text("${fieldLabel}")`);
    if (fieldLabelElement) {
      const fieldContainer = await fieldLabelElement.$('xpath=..');
      const valueElement = await fieldContainer.$(this.selectors.fieldValue);
      return await valueElement.textContent();
    }
    return '';
  }

  /**
   * Validate required fields
   * @returns {Promise<boolean>} True if all required fields are filled
   */
  async validateRequiredFields() {
    const errors = await this.page.$$(this.selectors.requiredFieldError);
    return errors.length === 0;
  }
}

module.exports = ContactPage;