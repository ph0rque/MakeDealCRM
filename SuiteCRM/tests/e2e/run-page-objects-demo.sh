#!/bin/bash

# Page Objects Demo Test Runner
# This script demonstrates how to run tests using the Page Object Models

echo "🚀 Starting MakeDealCRM Page Objects Demo Tests"
echo "================================================"

# Set base directory
BASE_DIR=$(dirname "$0")
cd "$BASE_DIR"

# Check if Playwright is installed
if ! command -v npx playwright &> /dev/null; then
    echo "❌ Playwright not found. Please install it first:"
    echo "   npm install -g @playwright/test"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Start the application if not running
echo "🔄 Checking if application is running on http://localhost:8080..."
if ! curl -s http://localhost:8080 &> /dev/null; then
    echo "🐳 Starting MakeDealCRM with Docker Compose..."
    cd ../../../
    docker-compose up -d
    
    # Wait for application to be ready
    echo "⏳ Waiting for application to be ready..."
    timeout=60
    while ! curl -s http://localhost:8080 &> /dev/null && [ $timeout -gt 0 ]; do
        sleep 2
        ((timeout-=2))
        echo "   Waiting... (${timeout}s remaining)"
    done
    
    if [ $timeout -le 0 ]; then
        echo "❌ Application failed to start within 60 seconds"
        exit 1
    fi
    
    cd "$BASE_DIR"
else
    echo "✅ Application is already running"
fi

# Set environment variables
export BASE_URL=http://localhost:8080
export HEADLESS=false

echo ""
echo "🧪 Running Page Objects Example Test..."
echo "======================================="

# Run the page objects example test
npx playwright test examples/page-objects-example.spec.js \
    --config=playwright.config.js \
    --headed \
    --timeout=30000 \
    --workers=1 \
    2>&1 | tee test-output.log

# Check test results
if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo ""
    echo "✅ All tests passed successfully!"
    echo ""
    echo "📊 Test Report Generated:"
    echo "   HTML Report: playwright-report/index.html"
    echo "   Test Results: test-results/"
    echo ""
    echo "🔍 To view the HTML report, run:"
    echo "   npx playwright show-report"
else
    echo ""
    echo "❌ Some tests failed. Check the output above for details."
    echo ""
    echo "🔍 Debug information:"
    echo "   Screenshots: test-results/"
    echo "   Videos: test-results/"
    echo "   Traces: test-results/"
    echo ""
    echo "To debug failed tests, run:"
    echo "   npx playwright test --debug"
fi

echo ""
echo "📚 Page Objects Documentation:"
echo "   README: page-objects/README.md"
echo "   TypeScript Types: page-objects/types.d.ts"
echo ""

# Optional: Run specific page object tests
read -p "🤔 Would you like to run specific module tests? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "Available test modules:"
    echo "1. Deal Management Tests"
    echo "2. Contact Management Tests"
    echo "3. Document Management Tests"
    echo "4. Checklist Tests"
    echo "5. Pipeline Drag & Drop Tests"
    echo "6. Navigation Tests"
    echo ""
    
    read -p "Enter module number (1-6): " -n 1 -r module
    echo
    
    case $module in
        1)
            echo "🏢 Running Deal Management Tests..."
            npx playwright test examples/page-objects-example.spec.js -g "deal workflow" --headed
            ;;
        2)
            echo "👥 Running Contact Management Tests..."
            npx playwright test examples/page-objects-example.spec.js -g "contact" --headed
            ;;
        3)
            echo "📄 Running Document Management Tests..."
            npx playwright test examples/page-objects-example.spec.js -g "document" --headed
            ;;
        4)
            echo "✅ Running Checklist Tests..."
            npx playwright test examples/page-objects-example.spec.js -g "checklist" --headed
            ;;
        5)
            echo "🎯 Running Pipeline Tests..."
            npx playwright test examples/page-objects-example.spec.js -g "pipeline" --headed
            ;;
        6)
            echo "🧭 Running Navigation Tests..."
            npx playwright test examples/page-objects-example.spec.js -g "navigation" --headed
            ;;
        *)
            echo "Invalid selection. Skipping..."
            ;;
    esac
fi

echo ""
echo "🎉 Page Objects Demo Complete!"
echo ""
echo "Next steps:"
echo "- Review the page object implementations in page-objects/"
echo "- Check out the example test file: examples/page-objects-example.spec.js"
echo "- Read the documentation: page-objects/README.md"
echo "- Start writing your own tests using these page objects!"
echo ""