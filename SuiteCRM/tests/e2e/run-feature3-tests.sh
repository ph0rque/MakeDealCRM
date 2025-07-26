#!/bin/bash

# Feature 3: Personal Due-Diligence Checklists - E2E Test Runner
# This script runs the E2E tests for checklist template creation and application

echo "🧪 Running Feature 3: Personal Due-Diligence Checklists E2E Tests"
echo "================================================================="

# Set script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Check if playwright is installed
if ! command -v npx playwright &> /dev/null; then
    echo "🎭 Installing Playwright browsers..."
    npx playwright install
fi

# Set environment variables for testing
export BASE_URL="${BASE_URL:-http://localhost:8080}"
export SUITE_USERNAME="${SUITE_USERNAME:-admin}"
export SUITE_PASSWORD="${SUITE_PASSWORD:-admin123}"

echo "🔧 Test Configuration:"
echo "   Base URL: $BASE_URL"
echo "   Username: $SUITE_USERNAME"
echo "   Test File: deals/feature3-checklist-due-diligence.spec.js"
echo ""

# Check if SuiteCRM is accessible
echo "🏥 Checking SuiteCRM accessibility..."
if curl -f -s "$BASE_URL" > /dev/null; then
    echo "✅ SuiteCRM is accessible at $BASE_URL"
else
    echo "❌ ERROR: Cannot access SuiteCRM at $BASE_URL"
    echo "   Please ensure SuiteCRM is running and accessible"
    exit 1
fi

echo ""
echo "🚀 Starting Feature 3 E2E Tests..."
echo ""

# Run the specific test file
npx playwright test deals/feature3-checklist-due-diligence.spec.js \
    --reporter=html \
    --reporter=line \
    --timeout=60000 \
    --retries=1 \
    "$@"

TEST_RESULT=$?

echo ""
echo "📊 Test Results:"
if [ $TEST_RESULT -eq 0 ]; then
    echo "✅ All Feature 3 tests passed!"
else
    echo "❌ Some Feature 3 tests failed (exit code: $TEST_RESULT)"
fi

echo ""
echo "📁 Test artifacts:"
echo "   - HTML Report: playwright-report/index.html"
echo "   - Screenshots: test-results/ (if any failures)"
echo "   - Videos: test-results/ (if enabled)"

echo ""
echo "🔧 Useful commands:"
echo "   View report:     npx playwright show-report"
echo "   Run with UI:     ./run-feature3-tests.sh --ui"
echo "   Run specific:    ./run-feature3-tests.sh -g 'Test Case 3.1'"
echo "   Debug mode:      ./run-feature3-tests.sh --debug"

exit $TEST_RESULT