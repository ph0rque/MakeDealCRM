#!/bin/bash

# Financial Hub E2E Test Runner
# Runs Feature 5 tests for At-a-Glance Financial & Valuation Hub

set -e

echo "🏦 Starting Financial Hub E2E Tests..."
echo "======================================"

# Check if docker containers are running
if ! docker ps | grep -q "suitecrm"; then
    echo "⚠️  SuiteCRM container not running. Starting docker services..."
    cd ../../../
    docker-compose up -d
    echo "⏳ Waiting for services to be ready..."
    sleep 30
    cd tests/e2e
fi

# Check environment
echo "🔍 Checking test environment..."
node scripts/check-env.js

# Install browsers if needed
echo "🌐 Installing browsers if needed..."
npx playwright install --with-deps chromium

# Run financial hub tests
echo "🧪 Running Financial Hub Tests..."

# Run main test case
echo ""
echo "📊 Test Case 5.1: Financial Hub What-if Calculator Integration"
echo "============================================================="
npx playwright test deals/financial-hub.spec.js --grep "Test Case 5.1" --reporter=list

# Run accessibility tests
echo ""
echo "♿ Accessibility Tests"
echo "===================="
npx playwright test deals/financial-hub.spec.js --grep "Accessibility" --reporter=list

# Run error handling tests
echo ""
echo "🚨 Error Handling Tests"
echo "======================"
npx playwright test deals/financial-hub.spec.js --grep "Error Handling" --reporter=list

# Run performance tests
echo ""
echo "⚡ Performance Tests"
echo "==================="
npx playwright test deals/financial-hub.spec.js --grep "Performance" --reporter=list

# Generate HTML report
echo ""
echo "📋 Generating test report..."
npx playwright show-report --port 9323 > /dev/null 2>&1 &
REPORT_PID=$!

echo ""
echo "✅ Financial Hub Tests Complete!"
echo "================================"
echo ""
echo "📊 Test Results:"
echo "  - HTML Report: http://localhost:9323"
echo "  - Screenshots: test-results/screenshots/"
echo "  - Videos: test-results/videos/"
echo ""

# Check if any tests failed
if [ $? -eq 0 ]; then
    echo "🎉 All tests passed successfully!"
    echo ""
    echo "💡 Next steps:"
    echo "  1. Review test report at http://localhost:9323"
    echo "  2. Check screenshots for visual verification"
    echo "  3. Verify financial calculations are working correctly"
    echo "  4. Test with real data in staging environment"
else
    echo "❌ Some tests failed. Check the report for details."
    echo ""
    echo "🔧 Troubleshooting:"
    echo "  1. Check if SuiteCRM is properly configured"
    echo "  2. Verify financial hub feature is enabled"
    echo "  3. Check browser console for JavaScript errors"
    echo "  4. Review failed test screenshots"
    exit 1
fi

# Keep report server running for a while
echo "📊 Report server will run for 5 minutes. Press Ctrl+C to stop early."
sleep 300
kill $REPORT_PID 2>/dev/null || true

echo "🏁 Test run complete. Report server stopped."