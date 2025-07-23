#!/bin/bash

# Run Duplicate Detection E2E Tests
# This script runs the comprehensive E2E tests for duplicate detection in the Deals module

echo "=========================================="
echo "Running Duplicate Detection E2E Tests"
echo "=========================================="

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if SuiteCRM is running
echo -e "${YELLOW}Checking if SuiteCRM is running...${NC}"
if curl -s http://localhost:8080 > /dev/null; then
    echo -e "${GREEN}✓ SuiteCRM is running${NC}"
else
    echo -e "${RED}✗ SuiteCRM is not running. Please start it with: docker-compose up -d${NC}"
    exit 1
fi

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    npm install
fi

# Install Playwright browsers if needed
echo -e "${YELLOW}Ensuring Playwright browsers are installed...${NC}"
npx playwright install

# Set environment variables
export DB_HOST=${DB_HOST:-localhost}
export DB_USER=${DB_USER:-root}
export DB_PASSWORD=${DB_PASSWORD:-root}
export DB_NAME=${DB_NAME:-suitecrm}
export DB_PORT=${DB_PORT:-3306}

# Parse command line arguments
RUN_MODE="headless"
UPDATE_BASELINES=false
SPECIFIC_TEST=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --headed)
            RUN_MODE="headed"
            shift
            ;;
        --debug)
            RUN_MODE="debug"
            shift
            ;;
        --ui)
            RUN_MODE="ui"
            shift
            ;;
        --update-baselines)
            UPDATE_BASELINES=true
            shift
            ;;
        --test)
            SPECIFIC_TEST="--grep \"$2\""
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--headed|--debug|--ui] [--update-baselines] [--test \"test name\"]"
            exit 1
            ;;
    esac
done

# Create directories for test results
mkdir -p visual-baselines
mkdir -p visual-diffs
mkdir -p test-results

# Run the tests
echo -e "${YELLOW}Running duplicate detection E2E tests...${NC}"
echo "Mode: $RUN_MODE"

if [ "$UPDATE_BASELINES" = true ]; then
    export UPDATE_BASELINES=true
    echo -e "${YELLOW}Updating visual regression baselines${NC}"
fi

case $RUN_MODE in
    headed)
        npx playwright test deals/duplicate-detection.spec.js --headed $SPECIFIC_TEST
        ;;
    debug)
        npx playwright test deals/duplicate-detection.spec.js --debug $SPECIFIC_TEST
        ;;
    ui)
        npx playwright test deals/duplicate-detection.spec.js --ui $SPECIFIC_TEST
        ;;
    *)
        npx playwright test deals/duplicate-detection.spec.js $SPECIFIC_TEST
        ;;
esac

TEST_RESULT=$?

# Show test results
if [ $TEST_RESULT -eq 0 ]; then
    echo -e "${GREEN}=========================================="
    echo -e "✓ All duplicate detection tests passed!"
    echo -e "==========================================${NC}"
else
    echo -e "${RED}=========================================="
    echo -e "✗ Some tests failed. Check the report."
    echo -e "==========================================${NC}"
    
    # Open the HTML report
    echo -e "${YELLOW}Opening test report...${NC}"
    npx playwright show-report
fi

# Clean up test data (optional, uncomment if needed)
# echo -e "${YELLOW}Cleaning up test data...${NC}"
# node -e "
# const { DealsTestDataHelper } = require('./deals/test-data-helper');
# (async () => {
#   const helper = new DealsTestDataHelper();
#   await helper.connect();
#   await helper.cleanup();
#   await helper.disconnect();
# })();
# "

exit $TEST_RESULT