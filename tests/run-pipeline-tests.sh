#!/bin/bash

# Pipeline Feature Test Runner
# Runs all test suites for the pipeline feature

echo "🧪 MakeDeal CRM - Pipeline Feature Test Suite"
echo "============================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if PHPUnit is installed
if ! command -v ./vendor/bin/phpunit &> /dev/null; then
    echo -e "${YELLOW}PHPUnit not found. Installing...${NC}"
    composer require --dev phpunit/phpunit ^10.3
fi

# Check if Playwright is installed
if ! command -v npx playwright &> /dev/null; then
    echo -e "${YELLOW}Playwright not found. Installing...${NC}"
    npm install -D @playwright/test
    npx playwright install
fi

# Function to run test suite
run_test_suite() {
    local suite_name=$1
    local test_path=$2
    
    echo -e "\n${YELLOW}Running ${suite_name}...${NC}"
    
    if [[ $suite_name == "E2E Tests" ]]; then
        # Run Playwright tests
        cd tests/e2e/pipeline
        npx playwright test
        local exit_code=$?
        cd ../../..
    else
        # Run PHPUnit tests
        ./vendor/bin/phpunit --testsuite "$suite_name" --testdox
        local exit_code=$?
    fi
    
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ ${suite_name} passed${NC}"
    else
        echo -e "${RED}✗ ${suite_name} failed${NC}"
    fi
    
    return $exit_code
}

# Track overall test status
overall_status=0

# Run Unit Tests
run_test_suite "Unit" "tests/unit/modules/Pipeline"
[ $? -ne 0 ] && overall_status=1

# Run Integration Tests
run_test_suite "Integration" "tests/integration/pipeline"
[ $? -ne 0 ] && overall_status=1

# Run API Tests
run_test_suite "API" "tests/api/pipeline"
[ $? -ne 0 ] && overall_status=1

# Run Performance Tests (optional, can be slow)
if [[ "${RUN_PERFORMANCE_TESTS}" == "true" ]]; then
    run_test_suite "Performance" "tests/performance"
    [ $? -ne 0 ] && overall_status=1
fi

# Run E2E Tests (requires running server)
if [[ "${RUN_E2E_TESTS}" == "true" ]]; then
    echo -e "\n${YELLOW}Starting test server...${NC}"
    php -S localhost:8080 -t public > /dev/null 2>&1 &
    SERVER_PID=$!
    sleep 2
    
    run_test_suite "E2E Tests" "tests/e2e/pipeline"
    [ $? -ne 0 ] && overall_status=1
    
    # Stop test server
    kill $SERVER_PID 2>/dev/null
fi

# Generate coverage report
if [[ "${GENERATE_COVERAGE}" == "true" ]]; then
    echo -e "\n${YELLOW}Generating coverage report...${NC}"
    ./vendor/bin/phpunit --coverage-html tests/coverage/html
    echo -e "${GREEN}Coverage report generated at: tests/coverage/html/index.html${NC}"
fi

# Summary
echo -e "\n============================================="
if [ $overall_status -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed${NC}"
fi

echo ""
echo "Test Reports:"
echo "- PHPUnit HTML: tests/coverage/html/index.html"
echo "- E2E Report: tests/e2e-report/index.html"
echo ""

# Quick test command examples
echo "Quick test commands:"
echo "- Run unit tests only: ./vendor/bin/phpunit --testsuite Unit"
echo "- Run specific test: ./vendor/bin/phpunit tests/unit/modules/Pipeline/PipelineStageTest.php"
echo "- Run E2E tests: cd tests/e2e/pipeline && npx playwright test"
echo "- Run with coverage: GENERATE_COVERAGE=true ./tests/run-pipeline-tests.sh"
echo "- Run all tests: RUN_E2E_TESTS=true RUN_PERFORMANCE_TESTS=true ./tests/run-pipeline-tests.sh"

exit $overall_status