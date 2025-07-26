const BasePage = require('./BasePage');
const path = require('path');

/**
 * DocumentPage - Page object for document upload and management
 */
class DocumentPage extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      // List view
      createButton: 'a:has-text("Create")',
      uploadButton: 'a:has-text("Upload")',
      listViewTable: '.list-view-rounded-corners',
      searchInput: 'input[name="basic_search"]',
      searchButton: 'input[value="Search"]',
      
      // Form fields
      documentNameInput: 'input[name="document_name"]',
      fileNameInput: 'input[name="filename"]',
      fileUploadInput: 'input[type="file"]',
      categorySelect: 'select[name="category_id"]',
      subcategorySelect: 'select[name="subcategory_id"]',
      statusSelect: 'select[name="status_id"]',
      revisionInput: 'input[name="revision"]',
      documentTypeSelect: 'select[name="doc_type"]',
      templateTypeSelect: 'select[name="template_type"]',
      isTemplateCheckbox: 'input[name="is_template"]',
      activeCheckbox: 'input[name="active_date"]',
      expDateInput: 'input[name="exp_date"]',
      descriptionTextarea: 'textarea[name="description"]',
      relatedDocumentSelect: 'select[name="related_doc_id"]',
      contractStatusSelect: 'select[name="contract_status"]',
      contractValueInput: 'input[name="contract_value"]',
      
      // Buttons
      saveButton: 'input[value="Save"]',
      cancelButton: 'input[value="Cancel"]',
      editButton: 'input[value="Edit"]',
      deleteButton: 'input[value="Delete"]',
      downloadButton: 'a:has-text("Download")',
      viewButton: 'a:has-text("View")',
      checkInButton: 'button:has-text("Check In")',
      checkOutButton: 'button:has-text("Check Out")',
      
      // Detail view
      detailViewTitle: 'h2',
      fieldLabel: '.field-label',
      fieldValue: '.field-value',
      documentViewer: '.document-viewer',
      documentPreview: '.document-preview',
      
      // Version control
      versionHistoryTable: '.version-history-table',
      versionNumber: '.version-number',
      versionDate: '.version-date',
      versionAuthor: '.version-author',
      versionComment: '.version-comment',
      restoreVersionButton: 'button:has-text("Restore")',
      
      // Categories and tags
      tagsInput: 'input[name="tags"]',
      tagsList: '.tags-list',
      tagItem: '.tag-item',
      removeTagButton: '.remove-tag',
      
      // Related records
      relatedDealsSubpanel: 'a:has-text("Related Deals")',
      relatedContactsSubpanel: 'a:has-text("Related Contacts")',
      linkToDealButton: 'button:has-text("Link to Deal")',
      linkToContactButton: 'button:has-text("Link to Contact")',
      
      // Document templates
      templateGallery: '.template-gallery',
      templateCard: '.template-card',
      useTemplateButton: 'button:has-text("Use Template")',
      
      // Search and filters
      documentTypeFilter: 'select[name="doc_type_filter"]',
      categoryFilter: 'select[name="category_filter"]',
      statusFilter: 'select[name="status_filter"]',
      dateRangeFrom: 'input[name="date_from"]',
      dateRangeTo: 'input[name="date_to"]',
      
      // Bulk actions
      massSelectAll: 'input[name="massall"]',
      massSelect: 'input[name="mass[]"]',
      actionSelect: 'select[name="action_select"]',
      goButton: 'input[value="Go"]',
      
      // Validation
      errorMessage: '.error-message',
      successMessage: '.alert-success',
      uploadProgress: '.upload-progress',
      uploadProgressBar: '.upload-progress-bar',
      
      // Preview modal
      previewModal: '#documentPreviewModal',
      previewModalClose: '#documentPreviewModal .close',
      previewIframe: '#documentPreviewModal iframe',
      
      // Metadata
      metadataPanel: '.metadata-panel',
      addMetadataButton: 'button:has-text("Add Metadata")',
      metadataKeyInput: 'input[name="metadata_key"]',
      metadataValueInput: 'input[name="metadata_value"]'
    };
  }

  /**
   * Navigate to Documents list view
   */
  async goto() {
    await this.navigate('/index.php?module=Documents&action=index');
    await this.waitForElement(this.selectors.listViewTable);
  }

  /**
   * Upload a new document
   * @param {Object} documentData - The document data
   */
  async uploadDocument(documentData) {
    await this.clickElement(this.selectors.uploadButton);
    await this.waitForElement(this.selectors.documentNameInput);
    
    // Fill document information
    if (documentData.name) await this.fillField(this.selectors.documentNameInput, documentData.name);
    if (documentData.category) await this.selectOption(this.selectors.categorySelect, documentData.category);
    if (documentData.subcategory) await this.selectOption(this.selectors.subcategorySelect, documentData.subcategory);
    if (documentData.status) await this.selectOption(this.selectors.statusSelect, documentData.status);
    if (documentData.revision) await this.fillField(this.selectors.revisionInput, documentData.revision);
    if (documentData.description) await this.fillField(this.selectors.descriptionTextarea, documentData.description);
    
    // Upload file
    if (documentData.filePath) {
      await this.uploadFile(this.selectors.fileUploadInput, documentData.filePath);
      await this.waitForUploadComplete();
    }
    
    // Set document type
    if (documentData.type) await this.selectOption(this.selectors.documentTypeSelect, documentData.type);
    
    // Set expiration date if provided
    if (documentData.expirationDate) await this.fillField(this.selectors.expDateInput, documentData.expirationDate);
    
    // Add tags
    if (documentData.tags && documentData.tags.length > 0) {
      for (const tag of documentData.tags) {
        await this.addTag(tag);
      }
    }
    
    // Save
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Create document from template
   * @param {string} templateName - The template name
   * @param {Object} documentData - The document data
   */
  async createFromTemplate(templateName, documentData) {
    await this.clickElement(this.selectors.createButton);
    await this.waitForElement(this.selectors.templateGallery);
    
    // Select template
    await this.clickElement(`${this.selectors.templateCard}:has-text("${templateName}") ${this.selectors.useTemplateButton}`);
    await this.waitForElement(this.selectors.documentNameInput);
    
    // Fill document details
    if (documentData.name) await this.fillField(this.selectors.documentNameInput, documentData.name);
    if (documentData.description) await this.fillField(this.selectors.descriptionTextarea, documentData.description);
    
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Wait for upload to complete
   */
  async waitForUploadComplete() {
    if (await this.isVisible(this.selectors.uploadProgress)) {
      await this.page.waitForSelector(this.selectors.uploadProgressBar + '[style*="width: 100%"]', {
        timeout: 30000
      });
    }
  }

  /**
   * Search for documents
   * @param {string} searchTerm - The search term
   */
  async searchDocuments(searchTerm) {
    await this.fillField(this.selectors.searchInput, searchTerm);
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Filter documents
   * @param {Object} filters - The filter criteria
   */
  async filterDocuments(filters) {
    if (filters.type) await this.selectOption(this.selectors.documentTypeFilter, filters.type);
    if (filters.category) await this.selectOption(this.selectors.categoryFilter, filters.category);
    if (filters.status) await this.selectOption(this.selectors.statusFilter, filters.status);
    if (filters.dateFrom) await this.fillField(this.selectors.dateRangeFrom, filters.dateFrom);
    if (filters.dateTo) await this.fillField(this.selectors.dateRangeTo, filters.dateTo);
    
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Open a document by name
   * @param {string} documentName - The document name
   */
  async openDocument(documentName) {
    await this.clickElement(`a:has-text("${documentName}")`);
    await this.waitForElement(this.selectors.detailViewTitle);
  }

  /**
   * Download document
   */
  async downloadDocument() {
    const downloadPromise = this.page.waitForEvent('download');
    await this.clickElement(this.selectors.downloadButton);
    const download = await downloadPromise;
    return download;
  }

  /**
   * Preview document
   */
  async previewDocument() {
    await this.clickElement(this.selectors.viewButton);
    await this.waitForElement(this.selectors.previewModal);
  }

  /**
   * Close preview modal
   */
  async closePreview() {
    await this.clickElement(this.selectors.previewModalClose);
    await this.page.waitForTimeout(300); // Wait for modal animation
  }

  /**
   * Check out document for editing
   */
  async checkOutDocument() {
    await this.clickElement(this.selectors.checkOutButton);
    await this.waitForPageLoad();
  }

  /**
   * Check in document after editing
   * @param {Object} checkInData - The check-in data
   */
  async checkInDocument(checkInData) {
    await this.clickElement(this.selectors.checkInButton);
    await this.waitForElement('textarea[name="version_comment"]');
    
    if (checkInData.comment) {
      await this.fillField('textarea[name="version_comment"]', checkInData.comment);
    }
    
    if (checkInData.newFilePath) {
      await this.uploadFile('input[type="file"]', checkInData.newFilePath);
      await this.waitForUploadComplete();
    }
    
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Link document to a deal
   * @param {string} dealName - The deal name
   */
  async linkToDeal(dealName) {
    await this.clickElement(this.selectors.linkToDealButton);
    await this.waitForElement('.modal-dialog');
    
    await this.fillField('.modal-dialog input[name="search"]', dealName);
    await this.clickElement('.modal-dialog button:has-text("Search")');
    await this.page.waitForTimeout(1000);
    
    await this.clickElement(`input[type="checkbox"][data-name="${dealName}"]`);
    await this.clickElement('.modal-dialog button:has-text("Select")');
    await this.waitForPageLoad();
  }

  /**
   * Link document to a contact
   * @param {string} contactName - The contact name
   */
  async linkToContact(contactName) {
    await this.clickElement(this.selectors.linkToContactButton);
    await this.waitForElement('.modal-dialog');
    
    await this.fillField('.modal-dialog input[name="search"]', contactName);
    await this.clickElement('.modal-dialog button:has-text("Search")');
    await this.page.waitForTimeout(1000);
    
    await this.clickElement(`input[type="checkbox"][data-name="${contactName}"]`);
    await this.clickElement('.modal-dialog button:has-text("Select")');
    await this.waitForPageLoad();
  }

  /**
   * Add a tag to document
   * @param {string} tag - The tag to add
   */
  async addTag(tag) {
    await this.fillField(this.selectors.tagsInput, tag);
    await this.pressKey('Enter');
    await this.page.waitForTimeout(300);
  }

  /**
   * Remove a tag
   * @param {string} tag - The tag to remove
   */
  async removeTag(tag) {
    const tagElement = await this.page.$(`${this.selectors.tagItem}:has-text("${tag}")`);
    if (tagElement) {
      const removeButton = await tagElement.$(this.selectors.removeTagButton);
      if (removeButton) {
        await removeButton.click();
        await this.page.waitForTimeout(300);
      }
    }
  }

  /**
   * Get document tags
   * @returns {Promise<string[]>} Array of tags
   */
  async getDocumentTags() {
    return await this.getAllTexts(this.selectors.tagItem);
  }

  /**
   * Get version history
   * @returns {Promise<Object[]>} Array of version history entries
   */
  async getVersionHistory() {
    const versions = [];
    const versionRows = await this.page.$$(`${this.selectors.versionHistoryTable} tbody tr`);
    
    for (const row of versionRows) {
      const version = {
        number: await row.$eval(this.selectors.versionNumber, el => el.textContent.trim()),
        date: await row.$eval(this.selectors.versionDate, el => el.textContent.trim()),
        author: await row.$eval(this.selectors.versionAuthor, el => el.textContent.trim()),
        comment: await row.$eval(this.selectors.versionComment, el => el.textContent.trim())
      };
      versions.push(version);
    }
    
    return versions;
  }

  /**
   * Restore a specific version
   * @param {string} versionNumber - The version number to restore
   */
  async restoreVersion(versionNumber) {
    const versionRow = await this.page.$(`tr:has(${this.selectors.versionNumber}:has-text("${versionNumber}"))`);
    if (versionRow) {
      const restoreButton = await versionRow.$(this.selectors.restoreVersionButton);
      if (restoreButton) {
        await this.handleAlert(true);
        await restoreButton.click();
        await this.waitForPageLoad();
      }
    }
  }

  /**
   * Add metadata to document
   * @param {string} key - The metadata key
   * @param {string} value - The metadata value
   */
  async addMetadata(key, value) {
    await this.clickElement(this.selectors.addMetadataButton);
    await this.fillField(this.selectors.metadataKeyInput, key);
    await this.fillField(this.selectors.metadataValueInput, value);
    await this.pressKey('Enter');
    await this.page.waitForTimeout(300);
  }

  /**
   * Mass delete documents
   * @param {string[]} documentIds - Array of document IDs
   */
  async massDeleteDocuments(documentIds) {
    // Select documents
    for (const documentId of documentIds) {
      await this.clickElement(`input[name="mass[]"][value="${documentId}"]`);
    }
    
    // Select delete action
    await this.selectOption(this.selectors.actionSelect, 'delete');
    await this.handleAlert(true);
    await this.clickElement(this.selectors.goButton);
    await this.waitForPageLoad();
  }

  /**
   * Get document status
   * @returns {Promise<string>} The document status
   */
  async getDocumentStatus() {
    return await this.getFieldValue('Status');
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
}

module.exports = DocumentPage;