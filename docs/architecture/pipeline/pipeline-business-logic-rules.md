# Pipeline Business Logic Rules

## Overview
This document defines the comprehensive business rules and workflow logic for the MakeDealCRM pipeline system. These rules ensure consistent deal progression, automated workflows, and effective solo dealmaker management.

## 1. Stage Transition Rules

### 1.1 Valid Stage Transitions

#### Forward Transitions (Progression)
- **Sourcing** → Screening, Unavailable
- **Screening** → Analysis & Outreach, Unavailable
- **Analysis & Outreach** → Due Diligence, Unavailable
- **Due Diligence** → Valuation & Structuring, Unavailable
- **Valuation & Structuring** → LOI/Negotiation, Unavailable
- **LOI/Negotiation** → Financing, Closing (if no financing needed), Unavailable
- **Financing** → Closing, Unavailable
- **Closing** → Closed/Owned - 90-Day Plan, Unavailable
- **Closed/Owned - 90-Day Plan** → Closed/Owned - Stable Operations
- **Closed/Owned - Stable Operations** → (terminal stage)
- **Unavailable** → Any previous stage (reactivation)

#### Backward Transitions (Regression)
- Any stage → Previous stages (with reason required)
- Not allowed: Terminal stages backwards (Closed/Owned stages)
- Special case: Unavailable → Any stage (deal reactivation)

### 1.2 Required Fields by Stage

#### Sourcing
- **Required**: deal_name, source_type
- **Optional**: initial_contact, broker_info
- **Auto-populated**: created_date, stage_entered_date

#### Screening
- **Required**: industry, ttm_revenue (estimated ok)
- **Validation**: ttm_revenue > 0
- **Recommended**: location, employee_count

#### Analysis & Outreach
- **Required**: primary_contact, initial_thesis
- **Validation**: At least one contact with valid email
- **Auto-action**: Send introduction email template

#### Due Diligence
- **Required**: nda_signed (boolean), nda_date
- **Required**: ttm_ebitda OR sde (at least one)
- **Validation**: nda_date <= today
- **Auto-action**: Apply DD checklist template

#### Valuation & Structuring
- **Required**: asking_price, proposed_valuation, target_multiple
- **Validation**: All values > 0
- **Calculation**: Verify proposed_valuation ≈ (ttm_ebitda OR sde) × target_multiple (±10%)

#### LOI/Negotiation
- **Required**: loi_submitted_date, loi_expiry_date, proposed_terms
- **Validation**: loi_expiry_date > loi_submitted_date
- **Alert**: 5 days before LOI expiry

#### Financing
- **Required**: capital_stack_complete (equity + senior_debt + seller_note)
- **Validation**: capital_stack_total >= proposed_valuation
- **Required**: lender_contact (if senior_debt > 0)

#### Closing
- **Required**: expected_close_date, attorney_contact
- **Auto-action**: Create closing checklist
- **Alert**: Daily reminders 7 days before close

#### Closed/Owned - 90-Day Plan
- **Required**: actual_close_date, final_purchase_price
- **Auto-action**: Create 90-day integration checklist
- **Calculation**: Days since close counter

#### Closed/Owned - Stable Operations
- **Required**: integration_complete (boolean)
- **Auto-populated**: transition after 90 days OR manual override

### 1.3 Automatic Stage Actions

```javascript
stageActions = {
  'sourcing': {
    onEnter: ['setCreatedDate', 'assignToOwner'],
    onExit: ['validateRequiredFields']
  },
  'screening': {
    onEnter: ['startStageTimer', 'checkDuplicates'],
    onExit: ['saveScreeningNotes']
  },
  'analysis_outreach': {
    onEnter: ['sendIntroEmail', 'createContactTasks'],
    onExit: ['logOutreachComplete']
  },
  'due_diligence': {
    onEnter: ['applyDDChecklist', 'notifyTeam', 'createDataRoom'],
    onExit: ['validateDDCompletion', 'generateDDReport']
  },
  'valuation_structuring': {
    onEnter: ['runValuationCalculator', 'pullComparables'],
    onExit: ['lockFinancials', 'prepareTermSheet']
  },
  'loi_negotiation': {
    onEnter: ['draftLOI', 'setExpiryReminder', 'notifyAttorney'],
    onExit: ['saveFinalTerms']
  },
  'financing': {
    onEnter: ['contactLenders', 'prepareFinancialPackage'],
    onExit: ['confirmFunding']
  },
  'closing': {
    onEnter: ['createClosingChecklist', 'scheduleWireTransfer'],
    onExit: ['recordFinalNumbers', 'archiveDocuments']
  },
  'owned_90_day': {
    onEnter: ['start90DayTimer', 'createIntegrationPlan'],
    onExit: ['generateIntegrationReport']
  }
}
```

## 2. Work-In-Progress (WIP) Limit Logic

### 2.1 Default WIP Limits

```javascript
defaultWIPLimits = {
  'sourcing': 20,          // Can track many potentials
  'screening': 10,         // Focus on quality screening
  'analysis_outreach': 5,  // Active conversations limit
  'due_diligence': 2,      // Intensive work phase
  'valuation_structuring': 3,
  'loi_negotiation': 2,    // Serious negotiations only
  'financing': 1,          // Single focus for funding
  'closing': 1,            // One deal at a time
  'owned_90_day': 3,       // Multiple portfolio companies
  'owned_stable': 10       // Larger portfolio possible
}
```

### 2.2 WIP Limit Behavior

#### When Limit is Reached
1. **Warning Display**: Yellow banner on pipeline view
2. **Soft Block**: Modal warning when trying to move deal into full stage
3. **Override Option**: "Proceed anyway" with required reason
4. **Notification**: Email alert to user about WIP breach
5. **Dashboard Alert**: Red indicator on stage card

#### Override Rules
- **Admin Override**: Always allowed with audit log
- **User Override**: Allowed with manager approval workflow
- **Temporary Override**: 48-hour grace period for urgent deals
- **Force Override**: CEO/Owner role can always override

#### WIP Calculation
```javascript
function calculateWIP(stage) {
  activeDeals = getDealsInStage(stage);
  // Exclude deals marked as "on hold" or "delegated"
  activeDeals = activeDeals.filter(deal => 
    deal.status !== 'on_hold' && 
    deal.assigned_to === currentUser
  );
  return activeDeals.length;
}
```

### 2.3 Dynamic WIP Adjustments

- **Time-based**: Increase limits during high-activity seasons
- **Performance-based**: Higher limits for users with good close rates
- **Stage-specific**: Different limits for different deal types
- **Resource-based**: Adjust based on team size changes

## 3. Stale Deal Detection Rules

### 3.1 Time Thresholds by Stage

```javascript
staleThresholds = {
  'sourcing': {
    warning: 30,  // days - orange
    critical: 60, // days - red
    action: 'move_to_unavailable'
  },
  'screening': {
    warning: 14,
    critical: 30,
    action: 'send_reminder'
  },
  'analysis_outreach': {
    warning: 7,
    critical: 21,
    action: 'escalate_to_manager'
  },
  'due_diligence': {
    warning: 45,
    critical: 90,
    action: 'review_meeting'
  },
  'valuation_structuring': {
    warning: 14,
    critical: 30,
    action: 'refresh_valuation'
  },
  'loi_negotiation': {
    warning: 7,
    critical: 14,
    action: 'check_loi_expiry'
  },
  'financing': {
    warning: 30,
    critical: 60,
    action: 'contact_lender'
  },
  'closing': {
    warning: 7,
    critical: 14,
    action: 'urgent_escalation'
  },
  'owned_90_day': {
    warning: null, // No warning during integration
    critical: 120, // Alert if stuck past 90 days
    action: 'integration_review'
  },
  'owned_stable': {
    warning: 180,  // Semi-annual review
    critical: 365, // Annual review required
    action: 'portfolio_review'
  }
}
```

### 3.2 Stale Deal Indicators

#### Visual Indicators
- **Green**: 0 to warning threshold
- **Orange**: Warning to critical threshold  
- **Red**: Beyond critical threshold
- **Flashing Red**: 2x critical threshold
- **Grey**: On hold/Paused status

#### Stale Deal Calculations
```javascript
function calculateStaleness(deal) {
  lastActivity = Math.max(
    deal.modified_date,
    deal.last_email_date,
    deal.last_note_date,
    deal.last_task_completed_date
  );
  
  daysSinceActivity = (now - lastActivity) / (1000 * 60 * 60 * 24);
  threshold = staleThresholds[deal.stage];
  
  return {
    days: daysSinceActivity,
    status: daysSinceActivity < threshold.warning ? 'fresh' :
            daysSinceActivity < threshold.critical ? 'warning' : 'critical',
    action_required: daysSinceActivity >= threshold.critical
  };
}
```

### 3.3 Escalation Actions

1. **Email Notifications**
   - Warning: Weekly digest of stale deals
   - Critical: Daily individual alerts
   - Auto-escalation: CC manager after 2x critical

2. **Task Generation**
   - Auto-create "Review stale deal" task
   - Priority increases with staleness
   - Recurring until addressed

3. **Pipeline Actions**
   - Auto-flag for weekly review meeting
   - Block new deals if too many stale
   - Suggest bulk actions (close/pause)

## 4. Automation Features

### 4.1 Auto-Move Rules

#### Condition-Based Movement
```javascript
autoMoveRules = [
  {
    name: 'Auto-progress after NDA',
    condition: 'nda_signed === true && stage === "analysis_outreach"',
    action: 'moveToStage("due_diligence")',
    delay: '24_hours'
  },
  {
    name: 'Auto-close stale sourcing',
    condition: 'daysSinceActivity > 90 && stage === "sourcing"',
    action: 'moveToStage("unavailable")',
    notification: true
  },
  {
    name: 'Graduate from 90-day plan',
    condition: 'daysInStage >= 90 && stage === "owned_90_day"',
    action: 'moveToStage("owned_stable")',
    manual_override: true
  },
  {
    name: 'Financing timeout',
    condition: 'financing_declined === true',
    action: 'moveToStage("unavailable")',
    reason_required: true
  }
];
```

#### Smart Movement Logic
- Check dependencies before moving
- Validate required fields
- Preserve historical data
- Log all automatic movements
- Allow undo within 24 hours

### 4.2 Stage-Specific Email Templates

#### Template Structure
```javascript
emailTemplates = {
  'screening': {
    'initial_interest': {
      trigger: 'manual',
      to: '{{primary_contact}}',
      subject: 'Interest in {{company_name}}',
      body: 'template/screening_interest.html',
      attachments: ['one_pager.pdf']
    }
  },
  'analysis_outreach': {
    'nda_request': {
      trigger: 'on_stage_enter',
      delay: '2_days',
      condition: 'nda_signed !== true'
    },
    'follow_up': {
      trigger: 'no_response',
      delay: '7_days',
      max_sends: 3
    }
  },
  'due_diligence': {
    'document_request': {
      trigger: 'checklist_created',
      includes: 'document_checklist'
    },
    'dd_update': {
      trigger: 'weekly',
      to: '{{deal_team}}'
    }
  },
  'loi_negotiation': {
    'loi_submission': {
      trigger: 'manual',
      requires_approval: true,
      track_opens: true
    },
    'loi_expiry_warning': {
      trigger: 'scheduled',
      before_expiry: '5_days'
    }
  }
};
```

#### Email Automation Rules
1. **Triggers**: Stage entry, time-based, event-based
2. **Conditions**: Field values, time delays, response tracking
3. **Personalization**: Dynamic fields, conditional content
4. **Tracking**: Opens, clicks, responses
5. **Compliance**: Unsubscribe links, data privacy

### 4.3 Task Generation on Stage Entry

#### Automatic Task Templates
```javascript
stageTasks = {
  'screening': [
    {
      name: 'Research company background',
      due: '+3 days',
      priority: 'normal',
      checklist: ['Check website', 'LinkedIn research', 'News search']
    },
    {
      name: 'Estimate initial valuation',
      due: '+5 days',
      priority: 'high'
    }
  ],
  'due_diligence': [
    {
      name: 'Create DD checklist',
      due: 'immediate',
      priority: 'critical',
      auto_complete: false
    },
    {
      name: 'Schedule management calls',
      due: '+7 days',
      priority: 'high'
    },
    {
      name: 'Review financial statements',
      due: '+14 days',
      priority: 'high',
      subtasks: ['3 years P&L', 'Balance sheets', 'Cash flow']
    }
  ],
  'closing': [
    {
      name: 'Final attorney review',
      due: '-7 days from close',
      priority: 'critical'
    },
    {
      name: 'Wire transfer setup',
      due: '-3 days from close',
      priority: 'critical'
    },
    {
      name: 'Insurance binding',
      due: '-1 day from close',
      priority: 'critical'
    }
  ]
};
```

#### Task Assignment Logic
1. **Default**: Assign to deal owner
2. **Role-based**: Assign to specialist (attorney, accountant)
3. **Workload-based**: Balance across team members
4. **Skill-based**: Match task type to user expertise
5. **Availability-based**: Check user calendar

## 5. Additional Business Rules

### 5.1 Data Validation Rules

```javascript
validationRules = {
  financial: {
    'ttm_revenue': 'value > 0 && value < 1000000000',
    'ttm_ebitda': 'value < ttm_revenue',
    'asking_price': 'value > 0',
    'multiple': 'value > 0 && value < 20',
    'capital_stack': 'sum(equity, senior_debt, seller_note) === proposed_valuation'
  },
  dates: {
    'nda_date': 'date <= today',
    'loi_expiry': 'date > loi_submitted_date',
    'expected_close': 'date > today'
  },
  relationships: {
    'primary_contact': 'required after screening',
    'attorney_contact': 'required for closing',
    'lender_contact': 'required if senior_debt > 0'
  }
};
```

### 5.2 Notification Rules

```javascript
notificationRules = {
  immediate: [
    'Deal moved to closing',
    'LOI accepted',
    'Financing approved',
    'WIP limit exceeded'
  ],
  daily_digest: [
    'Stale deal warnings',
    'Upcoming task deadlines',
    'New deals in pipeline'
  ],
  weekly_summary: [
    'Pipeline metrics',
    'Closed deals',
    'Team performance'
  ],
  configurable: {
    'stage_changes': 'user_preference',
    'task_assignments': 'user_preference',
    'email_tracking': 'deal_specific'
  }
};
```

### 5.3 Permission Rules

```javascript
permissionRules = {
  stage_transitions: {
    forward: 'deal_owner || admin',
    backward: 'deal_owner && reason_required',
    skip_stage: 'admin_only'
  },
  financial_data: {
    view: 'deal_team || admin',
    edit: 'deal_owner || CFO',
    approve: 'executive_only'
  },
  deal_actions: {
    delete: 'admin_only',
    merge: 'deal_owner || admin',
    archive: 'deal_owner after closed',
    reactivate: 'any_user'
  }
};
```

## 6. Performance Metrics

### 6.1 Pipeline Velocity Metrics

```javascript
velocityMetrics = {
  average_time_in_stage: {
    calculation: 'mean(all_deals.time_in_stage)',
    benchmark: stageThresholds,
    alert_if: 'value > benchmark * 1.5'
  },
  conversion_rates: {
    stage_to_stage: 'count(moved_forward) / count(entered_stage)',
    overall: 'count(closed_won) / count(created)',
    by_source: 'group_by(source_type)'
  },
  cycle_time: {
    sourcing_to_close: 'sum(all_stage_times)',
    by_deal_size: 'group_by(deal_value_bucket)',
    trend: 'monthly_comparison'
  }
};
```

### 6.2 Automation Effectiveness

```javascript
automationMetrics = {
  auto_moves: {
    success_rate: 'count(successful) / count(attempted)',
    manual_overrides: 'count(overridden) / count(successful)',
    time_saved: 'sum(automated_time) - sum(manual_time)'
  },
  email_performance: {
    open_rates: 'by_template_and_stage',
    response_rates: 'responses / sends',
    conversion_impact: 'closed_with_automation vs closed_without'
  },
  task_completion: {
    on_time_rate: 'completed_before_due / total_tasks',
    automation_created: 'auto_tasks / total_tasks',
    average_completion_time: 'by_task_type'
  }
};
```

## Implementation Priority

1. **Phase 1**: Core stage transitions and validation
2. **Phase 2**: WIP limits and stale deal detection  
3. **Phase 3**: Basic automation (auto-move, email templates)
4. **Phase 4**: Advanced automation (AI-driven suggestions)
5. **Phase 5**: Analytics and optimization

## Testing Requirements

- Unit tests for all validation rules
- Integration tests for stage transitions
- Load tests for automation triggers
- User acceptance testing for workflows
- Performance benchmarks for large pipelines