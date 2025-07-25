# Task 19 - Testing Checklist & Issue Resolution Guide

## Quick Test URLs

### Primary Test Pages
1. **Pipeline View**: `/SuiteCRM/index.php?module=Deals&action=pipeline`
2. **Financial Dashboard**: `/SuiteCRM/index.php?module=Deals&action=financialdashboard`
3. **mdeal_Deals Pipeline**: `/SuiteCRM/index.php?module=mdeal_Deals&action=pipeline`
4. **Automated Tests**: `/custom/modules/Deals/tests/Task19_BrowserTest.html`

## Pre-Test Setup

### 1. Clear Cache & Rebuild
```bash
# From SuiteCRM Admin
Admin > Repair > Quick Repair and Rebuild
Admin > Repair > Rebuild JS Files
Admin > Repair > Clear Additional Cache
```

### 2. Browser Setup
- Clear browser cache (Ctrl+F5)
- Open Developer Console (F12)
- Enable "Preserve Log" in Network tab
- Monitor Console for errors

### 3. Database Verification
```sql
-- Check if pipeline tables exist
SHOW TABLES LIKE '%pipeline%';
SHOW TABLES LIKE '%checklist%';
SHOW TABLES LIKE '%state_management%';

-- Verify pipeline_stage column
DESCRIBE opportunities;
```

## Testing Checklist

### 🔍 Phase 1: Basic Functionality

#### Pipeline View Loading
- [ ] Navigate to Deals > Pipeline
- [ ] Page loads without PHP errors
- [ ] Kanban board structure visible
- [ ] All pipeline stages display
- [ ] Deal cards show in correct stages
- [ ] No JavaScript console errors

#### Visual Elements
- [ ] CSS styles applied correctly
- [ ] Cards have proper styling
- [ ] Stage columns aligned properly
- [ ] Responsive layout works
- [ ] Focus priority badges visible
- [ ] Time-in-stage displays

### 🖱️ Phase 2: Interactive Features

#### Drag and Drop
- [ ] Can pick up deal card
- [ ] Drag ghost/preview appears
- [ ] Can drop in different stage
- [ ] Database updates on drop
- [ ] Stage counters update
- [ ] Undo notification appears

#### Card Interactions
- [ ] Click card opens detail view
- [ ] Hover shows additional info
- [ ] Focus flag toggle works
- [ ] Quick actions menu works
- [ ] Edit inline (if enabled)

### 📱 Phase 3: Mobile/Touch Testing

#### Touch Interactions
- [ ] Touch and hold initiates drag
- [ ] Scroll still works normally
- [ ] Pinch zoom (if enabled)
- [ ] Stage horizontal scroll
- [ ] Card tap opens details
- [ ] Touch feedback animations

### 🔧 Phase 4: Advanced Features

#### State Management
- [ ] Filter settings persist
- [ ] Sort order maintains
- [ ] Collapsed stages remember
- [ ] User preferences save
- [ ] Session state recovers

#### Performance
- [ ] Load time < 3 seconds
- [ ] Smooth animations
- [ ] No memory leaks
- [ ] Handles 100+ deals
- [ ] Lazy loading works

### 🌐 Phase 5: API/AJAX Testing

#### API Endpoints
- [ ] GET stages loads
- [ ] GET deals by stage
- [ ] POST move deal
- [ ] PUT update deal
- [ ] State sync works

#### Error Handling
- [ ] Network failure handled
- [ ] Invalid moves rejected
- [ ] Permission errors shown
- [ ] Timeout recovery
- [ ] Retry mechanism works

## Common Issues & Solutions

### Issue: Pipeline View Not Loading

**Symptoms:**
- 404 error or blank page
- "Action not found" error

**Solutions:**
1. Check action_view_map.php exists
2. Verify module registration
3. Run Quick Repair and Rebuild
4. Check file permissions

### Issue: No Drag and Drop

**Symptoms:**
- Can't pick up cards
- No drag preview

**Solutions:**
1. Check pipeline.js loaded
2. Verify jQuery/jQuery UI loaded
3. Check for JS errors
4. Test in different browser

### Issue: Styles Not Applied

**Symptoms:**
- Broken layout
- Missing colors/formatting

**Solutions:**
1. Check CSS files loaded
2. Clear browser cache
3. Check for CSS conflicts
4. Verify asset paths

### Issue: AJAX Errors

**Symptoms:**
- "Error updating deal" messages
- Changes don't save

**Solutions:**
1. Check API endpoints accessible
2. Verify CSRF tokens
3. Check user permissions
4. Monitor Network tab

### Issue: Mobile Not Working

**Symptoms:**
- Can't drag on touch devices
- Layout broken on mobile

**Solutions:**
1. Check touch event handlers
2. Verify responsive CSS
3. Test viewport meta tag
4. Check mobile detection

## Performance Testing

### Load Time Metrics
```javascript
// Run in console after page load
performance.timing.loadEventEnd - performance.timing.navigationStart
```

### Memory Usage
```javascript
// Check memory usage
if (performance.memory) {
    console.log('Used JS Heap:', (performance.memory.usedJSHeapSize / 1048576).toFixed(2) + ' MB');
}
```

### Network Requests
- Check Network tab for failed requests
- Look for 404s on assets
- Verify API response times
- Check for unnecessary requests

## Debug Mode

### Enable Debug Output
```javascript
// Run in console to enable debug mode
if (window.PipelineView) {
    PipelineView.debug = true;
}
if (window.StateManager) {
    StateManager.debug = true;
}
```

### Check State
```javascript
// Inspect current state
if (window.StateManager) {
    console.log('Current State:', StateManager.getState());
}
```

## Production Readiness Checklist

### Security
- [ ] XSS protection verified
- [ ] CSRF tokens working
- [ ] SQL injection prevented
- [ ] File upload restricted
- [ ] Permission checks enforced

### Performance
- [ ] Database indexes created
- [ ] Caching implemented
- [ ] Assets minified
- [ ] Gzip enabled
- [ ] CDN configured (if applicable)

### Monitoring
- [ ] Error logging enabled
- [ ] Performance metrics tracked
- [ ] User analytics setup
- [ ] Alerts configured
- [ ] Backup system tested

## Sign-off Criteria

### Must Pass
- ✅ Pipeline view loads
- ✅ Drag and drop works
- ✅ No console errors
- ✅ Data saves correctly
- ✅ Responsive design works

### Should Pass
- ✅ All features functional
- ✅ Performance acceptable
- ✅ Mobile support works
- ✅ Accessibility standards met
- ✅ Cross-browser compatible

### Nice to Have
- ✅ Animations smooth
- ✅ Offline support
- ✅ PWA features
- ✅ Advanced analytics
- ✅ A/B testing ready

## Contact for Issues

If critical issues found:
1. Document with screenshots
2. Check browser console
3. Note reproduction steps
4. Check server error logs
5. Review test reports

## Test Completion

**Date Tested:** _________________
**Tested By:** ___________________
**Environment:** _________________
**Result:** [ ] PASS [ ] FAIL

**Notes:**
_________________________________
_________________________________
_________________________________