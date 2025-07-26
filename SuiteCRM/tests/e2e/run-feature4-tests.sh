#!/bin/bash

# Feature 4: Simplified Stakeholder Tracking E2E Tests
# Script to run stakeholder tracking tests with proper configuration

echo "🚀 Starting Feature 4: Simplified Stakeholder Tracking E2E Tests"
echo "=============================================================="

# Set environment variables if not already set
export BASE_URL=${BASE_URL:-"http://localhost:8080"}
export SUITE_USERNAME=${SUITE_USERNAME:-"admin"}
export SUITE_PASSWORD=${SUITE_PASSWORD:-"admin123"}

echo "Configuration:"
echo "- Base URL: $BASE_URL"
echo "- Username: $SUITE_USERNAME"
echo ""

# Check if Playwright is installed
if ! command -v npx &> /dev/null; then
    echo "❌ Error: npx not found. Please install Node.js and npm first."
    exit 1
fi

# Check if playwright is installed locally
if ! npx playwright --version &> /dev/null; then
    echo "📦 Installing Playwright..."
    npm install @playwright/test
    npx playwright install
fi

# Create test results directory
mkdir -p test-results/feature4
mkdir -p test-results/screenshots

echo "🧪 Running Feature 4 stakeholder tracking tests..."
echo ""

# Run the main test suite
npx playwright test deals/feature4-stakeholder-tracking.spec.js \
    --reporter=html,line,junit \
    --output-dir=test-results/feature4 \
    --max-failures=1 \
    --workers=1

# Check exit code
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ All Feature 4 tests passed successfully!"
    echo ""
    echo "📊 View detailed results:"
    echo "- HTML Report: npx playwright show-report"
    echo "- Screenshots: test-results/screenshots/"
    echo "- Test artifacts: test-results/feature4/"
else
    echo ""
    echo "❌ Some Feature 4 tests failed."
    echo "📊 View failure details:"
    echo "- HTML Report: npx playwright show-report" 
    echo "- Screenshots: test-results/screenshots/"
    echo "- Full logs: test-results/feature4/"
    
    # Show recent screenshot if available
    LATEST_SCREENSHOT=$(find test-results -name "*.png" -type f -exec ls -t {} + | head -n 1)
    if [ -n "$LATEST_SCREENSHOT" ]; then
        echo "- Latest screenshot: $LATEST_SCREENSHOT"
    fi
    
    exit 1
fi

echo ""
echo "🎯 Feature 4 Test Summary:"
echo "- ✅ Test Case 4.1: Stakeholder Role Assignment and Verification"
echo "- ✅ Stakeholder relationship persistence"
echo "- ✅ Multiple stakeholders with different roles"
echo "- ✅ Role assignment validation and error handling"
echo ""
echo "📝 Next Steps:"
echo "1. Review test results in HTML report"
echo "2. Check any failed tests and update selectors if needed"
echo "3. Run cleanup test manually if needed: npx playwright test -g 'Cleanup'"
echo "4. Proceed to Feature 5 tests if all pass"