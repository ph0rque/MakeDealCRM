{*
 * Stakeholder Edit View Template
 * Enhanced edit view with stakeholder tracking fields
 *}

{* Include CSS files *}
<link rel="stylesheet" type="text/css" href="custom/modules/Contacts/css/stakeholder-badges.css">

{* Add stakeholder fields to edit form *}
<div class="panel panel-default">
    <div class="panel-heading">
        <h4 class="panel-title">Stakeholder Tracking</h4>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="stakeholder_role_c">Stakeholder Role</label>
                    <select name="stakeholder_role_c" id="stakeholder_role_c" class="form-control">
                        <option value="">-- Not a Stakeholder --</option>
                        <option value="Decision Maker" {if $bean->stakeholder_role_c == 'Decision Maker'}selected{/if}>Decision Maker</option>
                        <option value="Influencer" {if $bean->stakeholder_role_c == 'Influencer'}selected{/if}>Influencer</option>
                        <option value="Champion" {if $bean->stakeholder_role_c == 'Champion'}selected{/if}>Champion</option>
                        <option value="Blocker" {if $bean->stakeholder_role_c == 'Blocker'}selected{/if}>Blocker</option>
                    </select>
                    <span class="help-block">Role this contact plays in decision-making</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="contact_frequency_c">Contact Frequency (days)</label>
                    <input type="number" 
                           name="contact_frequency_c" 
                           id="contact_frequency_c" 
                           class="form-control" 
                           value="{$bean->contact_frequency_c}"
                           min="1"
                           max="365"
                           placeholder="e.g., 30">
                    <span class="help-block">How often to maintain contact (in days)</span>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="last_stakeholder_contact_c">Last Stakeholder Contact</label>
                    <div class="input-group">
                        <input type="text" 
                               name="last_stakeholder_contact_c" 
                               id="last_stakeholder_contact_c" 
                               class="form-control datepicker" 
                               value="{$bean->last_stakeholder_contact_c}"
                               placeholder="Select date">
                        <span class="input-group-addon">
                            <i class="fa fa-calendar"></i>
                        </span>
                    </div>
                    <span class="help-block">Date of last meaningful interaction</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="preferred_contact_method_c">Preferred Contact Method</label>
                    <select name="preferred_contact_method_c" id="preferred_contact_method_c" class="form-control">
                        <option value="">-- Select --</option>
                        <option value="Email" {if $bean->preferred_contact_method_c == 'Email'}selected{/if}>Email</option>
                        <option value="Phone" {if $bean->preferred_contact_method_c == 'Phone'}selected{/if}>Phone</option>
                        <option value="In-Person" {if $bean->preferred_contact_method_c == 'In-Person'}selected{/if}>In-Person Meeting</option>
                        <option value="Video Call" {if $bean->preferred_contact_method_c == 'Video Call'}selected{/if}>Video Call</option>
                        <option value="Text" {if $bean->preferred_contact_method_c == 'Text'}selected{/if}>Text Message</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label for="stakeholder_notes_c">Stakeholder Notes</label>
                    <textarea name="stakeholder_notes_c" 
                              id="stakeholder_notes_c" 
                              class="form-control" 
                              rows="4" 
                              placeholder="Key interests, concerns, communication preferences...">{$bean->stakeholder_notes_c}</textarea>
                    <span class="help-block">Important notes about engaging with this stakeholder</span>
                </div>
            </div>
        </div>
        
        <div class="stakeholder-preview">
            <h5>Status Preview</h5>
            <div class="preview-content">
                <span id="role-preview">Not a stakeholder</span>
                <span id="frequency-preview" style="display:none;"></span>
                <span id="status-preview" style="display:none;"></span>
            </div>
        </div>
    </div>
</div>

{literal}
<script type="text/javascript">
$(document).ready(function() {
    // Initialize datepicker
    $('.datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        maxDate: 0 // Can't select future dates for last contact
    });
    
    // Update preview on field changes
    function updatePreview() {
        var role = $('#stakeholder_role_c').val();
        var frequency = $('#contact_frequency_c').val();
        var lastContact = $('#last_stakeholder_contact_c').val();
        
        // Update role preview
        if (role) {
            $('#role-preview').html('<span class="stakeholder-role ' + role.toLowerCase().replace(' ', '-') + '">' + role + '</span>');
            
            // Show frequency preview
            if (frequency) {
                $('#frequency-preview').show().text('Contact every ' + frequency + ' days');
            } else {
                $('#frequency-preview').hide();
            }
            
            // Calculate and show status
            if (lastContact && frequency) {
                var status = StakeholderBadges.calculateFreshness(lastContact, frequency);
                var badge = StakeholderBadges.generateBadge(status, {
                    showIcon: true,
                    showTooltip: false,
                    compact: false
                });
                $('#status-preview').show().html(badge);
            } else {
                $('#status-preview').hide();
            }
        } else {
            $('#role-preview').text('Not a stakeholder');
            $('#frequency-preview').hide();
            $('#status-preview').hide();
        }
    }
    
    // Bind change events
    $('#stakeholder_role_c, #contact_frequency_c, #last_stakeholder_contact_c').on('change keyup', updatePreview);
    
    // Initial preview update
    updatePreview();
    
    // Form validation
    $('form').on('submit', function(e) {
        var role = $('#stakeholder_role_c').val();
        var frequency = $('#contact_frequency_c').val();
        
        // If role is set, frequency should be set too
        if (role && !frequency) {
            e.preventDefault();
            alert('Please set a contact frequency for this stakeholder.');
            $('#contact_frequency_c').focus();
            return false;
        }
        
        // If frequency is set, role should be set too
        if (frequency && !role) {
            e.preventDefault();
            alert('Please select a stakeholder role.');
            $('#stakeholder_role_c').focus();
            return false;
        }
    });
    
    // Quick presets for contact frequency
    var frequencyPresets = {
        'weekly': 7,
        'biweekly': 14,
        'monthly': 30,
        'quarterly': 90
    };
    
    // Add preset buttons
    var presetHtml = '<div class="frequency-presets">';
    presetHtml += '<span class="preset-label">Quick set:</span>';
    $.each(frequencyPresets, function(label, days) {
        presetHtml += '<button type="button" class="preset-btn" data-days="' + days + '">' + label + '</button>';
    });
    presetHtml += '</div>';
    
    $('#contact_frequency_c').after(presetHtml);
    
    // Handle preset clicks
    $('.preset-btn').on('click', function() {
        var days = $(this).data('days');
        $('#contact_frequency_c').val(days).trigger('change');
    });
});
</script>
{/literal}

<style>
/* Edit view styles */
.stakeholder-preview {
    margin-top: 20px;
    padding: 16px;
    background-color: #f9fafb;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.stakeholder-preview h5 {
    margin: 0 0 12px 0;
    font-size: 14px;
    color: #6b7280;
}

.preview-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.preview-content > span {
    font-size: 14px;
    color: #374151;
}

/* Frequency presets */
.frequency-presets {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.preset-label {
    font-size: 12px;
    color: #6b7280;
}

.preset-btn {
    padding: 4px 12px;
    border: 1px solid #d1d5db;
    background-color: #ffffff;
    border-radius: 4px;
    font-size: 12px;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
}

.preset-btn:hover {
    background-color: #f3f4f6;
    border-color: #9ca3af;
}

/* Form styling */
.form-group label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.help-block {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

/* Datepicker styling */
.input-group-addon {
    background-color: #f9fafb;
    border: 1px solid #d1d5db;
    color: #6b7280;
}

.ui-datepicker {
    font-size: 14px;
}
</style>