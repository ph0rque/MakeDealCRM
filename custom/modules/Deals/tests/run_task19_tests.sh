#!/bin/bash
# Task 19 Comprehensive Test Runner
# This script runs all tests to verify feature functionality after migrations

echo "=================================="
echo "Task 19 - Comprehensive Testing"
echo "=================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base paths
BASE_DIR="/Users/andrewgauntlet/Desktop/MakeDealCRM"
CUSTOM_DIR="$BASE_DIR/custom"
SUITECRM_DIR="$BASE_DIR/SuiteCRM"

# Test counters
PASSED=0
FAILED=0
WARNINGS=0

# Function to check if file exists
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} File exists: $2"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} File missing: $2"
        ((FAILED++))
        return 1
    fi
}

# Function to check if directory exists
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} Directory exists: $2"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} Directory missing: $2"
        ((FAILED++))
        return 1
    fi
}

# Function to check file content
check_content() {
    if grep -q "$2" "$1" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} Content found in $3: $2"
        ((PASSED++))
        return 0
    else
        echo -e "${YELLOW}⚠${NC} Content not found in $3: $2"
        ((WARNINGS++))
        return 1
    fi
}

echo "1. Checking Core Pipeline Files"
echo "==============================="
check_file "$CUSTOM_DIR/modules/Deals/views/view.pipeline.php" "Pipeline View"
check_file "$CUSTOM_DIR/modules/Deals/tpls/pipeline.tpl" "Pipeline Template"
check_file "$CUSTOM_DIR/modules/Deals/action_view_map.php" "Action View Map"
check_file "$CUSTOM_DIR/modules/mdeal_Deals/views/view.pipeline.php" "mdeal_Deals Pipeline View"
echo ""

echo "2. Checking CSS Assets"
echo "====================="
check_file "$CUSTOM_DIR/modules/Deals/css/pipeline.css" "Pipeline CSS"
check_file "$CUSTOM_DIR/modules/Deals/css/progress-indicators.css" "Progress Indicators CSS"
check_file "$CUSTOM_DIR/modules/Deals/css/stakeholder-badges.css" "Stakeholder Badges CSS"
check_file "$CUSTOM_DIR/modules/Deals/css/financial-dashboard.css" "Financial Dashboard CSS"
check_file "$CUSTOM_DIR/modules/Deals/css/wip-limits.css" "WIP Limits CSS"
check_file "$CUSTOM_DIR/modules/Deals/css/theme-integration.css" "Theme Integration CSS"
echo ""

echo "3. Checking JavaScript Files"
echo "==========================="
check_file "$CUSTOM_DIR/modules/Deals/js/pipeline.js" "Pipeline JS"
check_file "$CUSTOM_DIR/modules/Deals/js/state-manager.js" "State Manager JS"
check_file "$CUSTOM_DIR/modules/Deals/js/progress-indicators.js" "Progress Indicators JS"
check_file "$CUSTOM_DIR/modules/Deals/js/stakeholder-integration.js" "Stakeholder Integration JS"
check_file "$CUSTOM_DIR/modules/Deals/js/financial-dashboard-init.js" "Financial Dashboard JS"
check_file "$CUSTOM_DIR/modules/Deals/js/asset-loader.js" "Asset Loader JS"
echo ""

echo "4. Checking API Files"
echo "===================="
check_file "$CUSTOM_DIR/modules/Deals/api/PipelineApi.php" "Pipeline API"
check_file "$CUSTOM_DIR/modules/Deals/api/OptimizedPipelineApi.php" "Optimized Pipeline API"
check_file "$CUSTOM_DIR/modules/Deals/api/StakeholderIntegrationApi.php" "Stakeholder API"
check_file "$CUSTOM_DIR/modules/Deals/api/TemplateApi.php" "Template API"
check_file "$CUSTOM_DIR/modules/Deals/api/StateSync.php" "State Sync API"
echo ""

echo "5. Checking Database Migration Files"
echo "==================================="
check_file "$CUSTOM_DIR/database/migrations/001_create_pipeline_stages_table.sql" "Pipeline Stages Migration"
check_file "$CUSTOM_DIR/database/migrations/002_create_deals_pipeline_tracking_table.sql" "Pipeline Tracking Migration"
check_file "$CUSTOM_DIR/database/migrations/003_add_pipeline_stage_to_deals.sql" "Pipeline Stage Field Migration"
echo ""

echo "6. Checking Module Structure"
echo "==========================="
check_dir "$CUSTOM_DIR/modules/Deals" "Deals Module"
check_dir "$CUSTOM_DIR/modules/Pipelines" "Pipelines Module"
check_dir "$CUSTOM_DIR/modules/mdeal_Deals" "mdeal_Deals Module"
check_dir "$CUSTOM_DIR/Extension/modules/Deals" "Deals Extension"
echo ""

echo "7. Checking Critical File Contents"
echo "================================="
if [ -f "$CUSTOM_DIR/modules/Deals/views/view.pipeline.php" ]; then
    check_content "$CUSTOM_DIR/modules/Deals/views/view.pipeline.php" "ViewPipeline" "Pipeline View Class"
    check_content "$CUSTOM_DIR/modules/Deals/views/view.pipeline.php" "loadPipelineAssets" "Asset Loading Function"
fi

if [ -f "$CUSTOM_DIR/modules/Deals/js/pipeline.js" ]; then
    check_content "$CUSTOM_DIR/modules/Deals/js/pipeline.js" "PipelineManager" "Pipeline Manager Class"
    check_content "$CUSTOM_DIR/modules/Deals/js/pipeline.js" "dragstart\|Sortable" "Drag & Drop Handler"
fi

if [ -f "$CUSTOM_DIR/modules/Deals/api/PipelineApi.php" ]; then
    check_content "$CUSTOM_DIR/modules/Deals/api/PipelineApi.php" "class PipelineApi" "Pipeline API Class"
fi
echo ""

echo "8. Checking Module Registration"
echo "=============================="
if [ -f "$CUSTOM_DIR/Extension/application/Ext/Include/pipelines_module.php" ]; then
    echo -e "${GREEN}✓${NC} Pipelines module registration found"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠${NC} Pipelines module registration might be missing"
    ((WARNINGS++))
fi

if [ -f "$CUSTOM_DIR/modules/Deals/Menu.php" ]; then
    check_content "$CUSTOM_DIR/modules/Deals/Menu.php" "Pipeline" "Pipeline Menu Item"
fi
echo ""

echo "9. Running PHP Syntax Checks"
echo "==========================="
# Check PHP files for syntax errors
PHP_FILES=(
    "$CUSTOM_DIR/modules/Deals/views/view.pipeline.php"
    "$CUSTOM_DIR/modules/Deals/api/PipelineApi.php"
    "$CUSTOM_DIR/modules/Deals/api/OptimizedPipelineApi.php"
    "$CUSTOM_DIR/modules/Deals/controller.php"
)

for file in "${PHP_FILES[@]}"; do
    if [ -f "$file" ]; then
        php -l "$file" > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓${NC} PHP syntax OK: $(basename $file)"
            ((PASSED++))
        else
            echo -e "${RED}✗${NC} PHP syntax error: $(basename $file)"
            ((FAILED++))
        fi
    fi
done
echo ""

echo "10. Checking File Permissions"
echo "============================"
# Check if web server can read critical files
WEB_USER="www-data"  # Adjust based on your system
CRITICAL_FILES=(
    "$CUSTOM_DIR/modules/Deals/css/pipeline.css"
    "$CUSTOM_DIR/modules/Deals/js/pipeline.js"
    "$CUSTOM_DIR/modules/Deals/views/view.pipeline.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ] && [ -r "$file" ]; then
        echo -e "${GREEN}✓${NC} File readable: $(basename $file)"
        ((PASSED++))
    else
        echo -e "${YELLOW}⚠${NC} File permission issue: $(basename $file)"
        ((WARNINGS++))
    fi
done
echo ""

echo "=================================="
echo "Test Results Summary"
echo "=================================="
echo -e "${GREEN}Passed:${NC} $PASSED"
echo -e "${RED}Failed:${NC} $FAILED"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS"
echo ""

TOTAL=$((PASSED + FAILED + WARNINGS))
SUCCESS_RATE=$((PASSED * 100 / TOTAL))

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All critical tests passed!${NC}"
    echo "Success rate: $SUCCESS_RATE%"
    echo ""
    echo "Next steps:"
    echo "1. Run the PHP test script: php $CUSTOM_DIR/modules/Deals/tests/Task19_ComprehensiveTest.php"
    echo "2. Open the browser test page: $CUSTOM_DIR/modules/Deals/tests/Task19_BrowserTest.html"
    echo "3. Navigate to Deals > Pipeline in SuiteCRM to test manually"
else
    echo -e "${RED}✗ Some tests failed!${NC}"
    echo "Success rate: $SUCCESS_RATE%"
    echo ""
    echo "Please fix the failed tests before proceeding."
    echo "Common fixes:"
    echo "1. Run Quick Repair and Rebuild in SuiteCRM Admin"
    echo "2. Check file permissions (should be readable by web server)"
    echo "3. Run database migrations if tables are missing"
    echo "4. Verify all files were deployed correctly"
fi

echo ""
echo "Manual verification checklist:"
echo "- [ ] Pipeline view loads without errors"
echo "- [ ] Drag and drop works between stages"
echo "- [ ] No JavaScript console errors"
echo "- [ ] All AJAX calls complete successfully"
echo "- [ ] CSS styles are applied correctly"
echo "- [ ] Module permissions are correct"
echo "- [ ] Financial dashboard displays (if enabled)"
echo "- [ ] Stakeholder badges show correctly"
echo "- [ ] Export functionality works"
echo "- [ ] Mobile/responsive view works"

exit $FAILED