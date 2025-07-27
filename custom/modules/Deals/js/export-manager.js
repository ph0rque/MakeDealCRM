/**
 * Due Diligence Export Manager
 * Handles front-end interactions for PDF and Excel exports
 */

var DueDiligenceExportManager = {
    
    /**
     * Initialize the export manager
     */
    init: function() {
        this.bindEvents();
        this.loadTemplates();
    },
    
    /**
     * Bind event handlers
     */
    bindEvents: function() {
        // Single export buttons
        $(document).on('click', '.export-pdf-btn', this.handleSinglePDFExport.bind(this));
        $(document).on('click', '.export-excel-btn', this.handleSingleExcelExport.bind(this));
        
        // Batch export buttons
        $(document).on('click', '.batch-export-btn', this.showBatchExportDialog.bind(this));
        
        // Export dialog events
        $(document).on('click', '#export-preview-btn', this.showExportPreview.bind(this));
        $(document).on('click', '#export-confirm-btn', this.executeExport.bind(this));
        $(document).on('change', '#export-template-select', this.updateTemplatePreview.bind(this));
        
        // Export options events
        $(document).on('change', '.export-option-checkbox', this.updateExportPreview.bind(this));
        $(document).on('change', '#export-format-select', this.toggleFormatOptions.bind(this));
    },
    
    /**
     * Handle single PDF export
     */
    handleSinglePDFExport: function(e) {
        e.preventDefault();
        
        var dealId = $(e.target).data('deal-id');
        if (!dealId) {
            this.showError('Deal ID not found');
            return;
        }
        
        this.showExportDialog(dealId, 'pdf');
    },
    
    /**
     * Handle single Excel export
     */
    handleSingleExcelExport: function(e) {
        e.preventDefault();
        
        var dealId = $(e.target).data('deal-id');
        if (!dealId) {
            this.showError('Deal ID not found');
            return;
        }
        
        this.showExportDialog(dealId, 'excel');
    },
    
    /**
     * Show batch export dialog
     */
    showBatchExportDialog: function(e) {
        e.preventDefault();
        
        var selectedDeals = this.getSelectedDeals();
        if (selectedDeals.length === 0) {
            this.showError('Please select deals to export');
            return;
        }
        
        if (selectedDeals.length > 50) {
            this.showError('Batch export limited to 50 deals maximum');
            return;
        }
        
        this.showExportDialog(selectedDeals, 'batch');
    },
    
    /**
     * Show export dialog
     */
    showExportDialog: function(dealData, exportType) {
        var self = this;
        
        // Create dialog HTML
        var dialogHtml = this.buildExportDialogHTML(dealData, exportType);
        
        // Show dialog
        var dialog = $(dialogHtml).dialog({
            title: exportType === 'batch' ? 'Batch Export Deals' : 'Export Deal Report',
            modal: true,
            width: 600,
            height: 500,
            resizable: true,
            close: function() {
                $(this).remove();
            },
            buttons: {
                'Preview': {
                    id: 'export-preview-btn',
                    text: 'Preview',
                    class: 'btn btn-secondary',
                    click: function() {
                        self.showExportPreview();
                    }
                },
                'Export': {
                    id: 'export-confirm-btn',
                    text: 'Export',
                    class: 'btn btn-primary',
                    click: function() {
                        self.executeExport();
                    }
                },
                'Cancel': {
                    text: 'Cancel',
                    class: 'btn btn-default',
                    click: function() {
                        $(this).dialog('close');
                    }
                }
            }
        });
        
        // Store export data
        dialog.data('export-data', dealData);
        dialog.data('export-type', exportType);
        
        // Initialize dialog
        this.initializeExportDialog(dialog);
    },
    
    /**
     * Build export dialog HTML
     */
    buildExportDialogHTML: function(dealData, exportType) {
        var isMultiple = Array.isArray(dealData);
        var dealCount = isMultiple ? dealData.length : 1;
        
        return `
            <div id="export-dialog" class="export-dialog">
                <div class="export-summary">
                    <h4>Export Summary</h4>
                    <p><strong>Deals to export:</strong> ${dealCount}</p>
                    ${!isMultiple ? '<p><strong>Deal ID:</strong> ' + dealData + '</p>' : ''}
                </div>
                
                <div class="export-options">
                    <div class="form-group">
                        <label for="export-format-select">Export Format:</label>
                        <select id="export-format-select" class="form-control">
                            <option value="pdf">PDF Report</option>
                            <option value="excel">Excel Spreadsheet</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export-template-select">Template:</label>
                        <select id="export-template-select" class="form-control">
                            <option value="standard">Standard Report</option>
                            <option value="executive">Executive Summary</option>
                            <option value="detailed">Detailed Analysis</option>
                        </select>
                    </div>
                    
                    <div class="export-content-options">
                        <h5>Include Sections:</h5>
                        <div class="checkbox-group">
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="include_progress" checked> Progress Analysis
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="include_file_requests" checked> File Requests
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="include_notes" checked> Notes & Comments
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="branding" checked> Company Branding
                            </label>
                        </div>
                    </div>
                    
                    <div id="pdf-options" class="format-specific-options">
                        <h5>PDF Options:</h5>
                        <div class="form-group">
                            <label for="pdf-orientation">Orientation:</label>
                            <select id="pdf-orientation" class="form-control">
                                <option value="portrait">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pdf-watermark">Watermark (optional):</label>
                            <input type="text" id="pdf-watermark" class="form-control" 
                                   placeholder="e.g., CONFIDENTIAL">
                        </div>
                    </div>
                    
                    <div id="excel-options" class="format-specific-options" style="display: none;">
                        <h5>Excel Options:</h5>
                        <div class="checkbox-group">
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="separate_sheets" checked> Separate Sheets by Section
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="include_charts" checked> Include Charts
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" class="export-option-checkbox" 
                                       name="include_formulas" checked> Include Formulas
                            </label>
                        </div>
                    </div>
                </div>
                
                <div id="export-preview" class="export-preview" style="display: none;">
                    <h5>Export Preview:</h5>
                    <div id="preview-content">
                        <!-- Preview content will be loaded here -->
                    </div>
                </div>
                
                <div id="export-progress" class="export-progress" style="display: none;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="progress-text">Preparing export...</p>
                </div>
            </div>
        `;
    },
    
    /**
     * Initialize export dialog
     */
    initializeExportDialog: function(dialog) {
        // Set default options
        this.toggleFormatOptions();
        
        // Load template preview
        this.updateTemplatePreview();
    },
    
    /**
     * Toggle format-specific options
     */
    toggleFormatOptions: function() {
        var format = $('#export-format-select').val();
        
        $('.format-specific-options').hide();
        $('#' + format + '-options').show();
        
        this.updateExportPreview();
    },
    
    /**
     * Update template preview
     */
    updateTemplatePreview: function() {
        var template = $('#export-template-select').val();
        
        // TODO: Load template preview image or description
        console.log('Template changed to:', template);
        
        this.updateExportPreview();
    },
    
    /**
     * Update export preview
     */
    updateExportPreview: function() {
        // Update preview based on current options
        var options = this.getExportOptions();
        
        // TODO: Show real-time preview of what will be included
        console.log('Export options updated:', options);
    },
    
    /**
     * Show export preview
     */
    showExportPreview: function() {
        var dialog = $('#export-dialog').closest('.ui-dialog-content');
        var dealData = dialog.data('export-data');
        var exportType = dialog.data('export-type');
        var options = this.getExportOptions();
        
        var dealId = Array.isArray(dealData) ? dealData[0] : dealData;
        
        $.ajax({
            url: 'index.php?module=Deals&action=export',
            method: 'POST',
            data: {
                action: 'preview',
                deal_id: dealId,
                ...options
            },
            success: function(response) {
                if (response.success) {
                    this.displayPreview(response.preview);
                } else {
                    this.showError(response.error);
                }
            }.bind(this),
            error: function() {
                this.showError('Failed to load preview');
            }.bind(this)
        });
    },
    
    /**
     * Display export preview
     */
    displayPreview: function(previewData) {
        var previewHtml = `
            <div class="preview-summary">
                <p><strong>Estimated pages:</strong> ${previewData.estimated_pages}</p>
                <p><strong>File size:</strong> ~${previewData.file_size_estimate.pdf_mb} MB (PDF) / 
                   ~${previewData.file_size_estimate.excel_mb} MB (Excel)</p>
            </div>
            <div class="preview-sections">
                <h6>Sections to include:</h6>
                <ul>
                    ${previewData.sections.map(section => 
                        `<li class="${section.included ? 'included' : 'excluded'}">
                            ${section.included ? '✓' : '✗'} ${section.name}
                         </li>`
                    ).join('')}
                </ul>
            </div>
        `;
        
        $('#preview-content').html(previewHtml);
        $('#export-preview').show();
    },
    
    /**
     * Execute export
     */
    executeExport: function() {
        var dialog = $('#export-dialog').closest('.ui-dialog-content');
        var dealData = dialog.data('export-data');
        var exportType = dialog.data('export-type');
        var options = this.getExportOptions();
        
        this.showProgress();
        
        var requestData = {
            ...options
        };
        
        if (exportType === 'batch') {
            requestData.action = 'batchExport';
            requestData.deal_ids = dealData.join(',');
            requestData.format = options.format;
        } else {
            requestData.action = options.format === 'pdf' ? 'exportToPDF' : 'exportToExcel';
            requestData.deal_id = dealData;
        }
        
        // Use the correct action endpoint
        var actionUrl = 'index.php?module=Deals&action=';
        if (options.format === 'pdf') {
            actionUrl += 'exportPDF';
        } else {
            actionUrl += 'exportExcel';
        }
        
        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(response) {
                this.hideProgress();
                
                if (response.success && response.download_url) {
                    // Trigger download
                    window.location.href = response.download_url;
                    this.showSuccess('Export generated successfully');
                    
                    // Log export
                    this.logExport(requestData);
                } else {
                    this.showError(response.message || 'Export failed');
                }
                
                dialog.dialog('close');
            }.bind(this),
            error: function(xhr) {
                this.hideProgress();
                
                var errorMessage = 'Export failed';
                try {
                    var response = JSON.parse(xhr.responseText);
                    errorMessage = response.error || errorMessage;
                } catch (e) {
                    // Ignore parsing error
                }
                
                this.showError(errorMessage);
            }.bind(this)
        });
    },
    
    /**
     * Handle single export response
     */
    handleSingleExportResponse: function(blob, xhr) {
        // Create download link for the blob
        var filename = this.getFilenameFromResponse(xhr);
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        this.showSuccess('Export completed successfully');
    },
    
    /**
     * Handle batch export response
     */
    handleBatchExportResponse: function(response) {
        if (response.success) {
            var summary = response.summary;
            var message = `Batch export completed:\n` +
                         `Total: ${summary.total}\n` +
                         `Successful: ${summary.successful}\n` +
                         `Failed: ${summary.failed}\n` +
                         `Success Rate: ${summary.success_rate}%`;
            
            this.showSuccess(message);
            
            // Show download links if available
            if (response.download_links && response.download_links.length > 0) {
                this.showDownloadLinks(response.download_links);
            }
        } else {
            this.showError(response.error);
        }
    },
    
    /**
     * Show download links for batch export
     */
    showDownloadLinks: function(downloadLinks) {
        var linksHtml = '<div class="download-links"><h5>Download Links:</h5><ul>';
        
        downloadLinks.forEach(function(link, index) {
            if (link) {
                linksHtml += `<li><a href="${link}" target="_blank">Download Deal ${index + 1}</a></li>`;
            }
        });
        
        linksHtml += '</ul></div>';
        
        $(linksHtml).dialog({
            title: 'Download Exported Files',
            modal: true,
            width: 400,
            height: 300,
            buttons: {
                'Close': function() {
                    $(this).dialog('close');
                }
            }
        });
    },
    
    /**
     * Get export options from form
     */
    getExportOptions: function() {
        var options = {};
        
        // Basic options
        options.format = $('#export-format-select').val();
        options.template = $('#export-template-select').val();
        
        // Checkbox options
        $('.export-option-checkbox').each(function() {
            options[$(this).attr('name')] = $(this).is(':checked');
        });
        
        // Format-specific options
        if (options.format === 'pdf') {
            options.orientation = $('#pdf-orientation').val();
            options.watermark = $('#pdf-watermark').val();
        } else if (options.format === 'excel') {
            options.excel_format = 'xlsx';
        }
        
        return options;
    },
    
    /**
     * Get selected deals from list view
     */
    getSelectedDeals: function() {
        var selected = [];
        
        // Check for SuiteCRM's standard checkbox selection
        $('input[name="mass[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        
        return selected;
    },
    
    /**
     * Get filename from response headers
     */
    getFilenameFromResponse: function(xhr) {
        var disposition = xhr.getResponseHeader('Content-Disposition');
        var filename = 'export.pdf';
        
        if (disposition && disposition.indexOf('filename=') !== -1) {
            var filenameMatch = disposition.match(/filename="?([^"]+)"?/);
            if (filenameMatch) {
                filename = filenameMatch[1];
            }
        }
        
        return filename;
    },
    
    /**
     * Show progress indicator
     */
    showProgress: function() {
        $('#export-progress').show();
        $('.ui-dialog-buttonpane button').prop('disabled', true);
        
        // Animate progress bar
        var progress = 0;
        var interval = setInterval(function() {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            
            $('.progress-bar').css('width', progress + '%');
        }, 500);
        
        // Store interval ID for cleanup
        $('#export-dialog').data('progress-interval', interval);
    },
    
    /**
     * Hide progress indicator
     */
    hideProgress: function() {
        var interval = $('#export-dialog').data('progress-interval');
        if (interval) {
            clearInterval(interval);
        }
        
        $('#export-progress').hide();
        $('.ui-dialog-buttonpane button').prop('disabled', false);
        $('.progress-bar').css('width', '0%');
    },
    
    /**
     * Load available templates
     */
    loadTemplates: function() {
        $.ajax({
            url: 'index.php?module=Deals&action=export',
            method: 'POST',
            data: {
                action: 'getTemplates'
            },
            success: function(response) {
                if (response.success && response.templates) {
                    this.populateTemplateSelect(response.templates);
                }
            }.bind(this),
            error: function() {
                console.warn('Failed to load export templates');
            }
        });
    },
    
    /**
     * Populate template select dropdown
     */
    populateTemplateSelect: function(templates) {
        var options = '';
        
        Object.keys(templates).forEach(function(key) {
            var template = templates[key];
            options += `<option value="${key}" title="${template.description}">
                ${template.name}
            </option>`;
        });
        
        if (options) {
            $('#export-template-select').html(options);
        }
    },
    
    /**
     * Show success message
     */
    showSuccess: function(message) {
        if (typeof SUGAR !== 'undefined' && SUGAR.App && SUGAR.App.alert) {
            SUGAR.App.alert.show('export-success', {
                level: 'success',
                messages: message
            });
        } else {
            alert('Success: ' + message);
        }
    },
    
    /**
     * Show error message
     */
    showError: function(message) {
        if (typeof SUGAR !== 'undefined' && SUGAR.App && SUGAR.App.alert) {
            SUGAR.App.alert.show('export-error', {
                level: 'error',
                messages: message
            });
        } else {
            alert('Error: ' + message);
        }
    }
};

// Initialize when document is ready
$(document).ready(function() {
    DueDiligenceExportManager.init();
});