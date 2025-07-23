#!/bin/bash

# Run Duplicate Detection PHPUnit Tests
# This script runs the comprehensive duplicate detection test suite

echo "==================================="
echo "Running Duplicate Detection Tests"
echo "==================================="

# Change to the SuiteCRM root directory
cd "$(dirname "$0")/../../../../.." || exit 1

# Check if PHPUnit is available
if ! command -v vendor/bin/phpunit &> /dev/null; then
    echo "PHPUnit not found. Installing dependencies..."
    composer install --no-interaction
fi

# Create results directory if it doesn't exist
mkdir -p tests/unit/phpunit/modules/Deals/test-results

# Run the tests with the specific configuration
echo "Running PHPUnit tests..."
vendor/bin/phpunit \
    -c tests/unit/phpunit/modules/Deals/phpunit-duplicate-detection.xml \
    --testdox \
    --colors=always

# Check test results
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ All tests passed successfully!"
    echo ""
    
    # Show coverage summary if generated
    if [ -f "tests/unit/phpunit/modules/Deals/coverage-report/index.html" ]; then
        echo "📊 Code coverage report generated at:"
        echo "   tests/unit/phpunit/modules/Deals/coverage-report/index.html"
    fi
else
    echo ""
    echo "❌ Some tests failed. Check the output above for details."
    exit 1
fi

# Optional: Run specific test methods
if [ "$1" == "--verbose" ]; then
    echo ""
    echo "Running specific test scenarios with detailed output..."
    
    # Test SQL injection prevention
    echo ""
    echo "🛡️ Testing SQL Injection Prevention..."
    vendor/bin/phpunit \
        -c tests/unit/phpunit/modules/Deals/phpunit-duplicate-detection.xml \
        --filter testSQLInjectionPrevention \
        --verbose
    
    # Test performance
    echo ""
    echo "⚡ Testing Performance..."
    vendor/bin/phpunit \
        -c tests/unit/phpunit/modules/Deals/phpunit-duplicate-detection.xml \
        --filter testPerformance \
        --verbose
    
    # Test edge cases
    echo ""
    echo "🔍 Testing Edge Cases..."
    vendor/bin/phpunit \
        -c tests/unit/phpunit/modules/Deals/phpunit-duplicate-detection.xml \
        --filter "testSpecialCharacterHandling|testWhitespaceNormalization" \
        --verbose
fi

echo ""
echo "Test run completed!"