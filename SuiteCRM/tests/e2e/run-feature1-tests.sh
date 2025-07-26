#!/bin/bash

# Feature 1: Deal as Central Object - Test Runner
# This script runs the E2E tests for Feature 1 of MakeDealCRM

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Feature 1: Deal as Central Object${NC}"
echo -e "${GREEN}E2E Test Suite${NC}"
echo -e "${GREEN}========================================${NC}"

# Check if we're in the right directory
if [ ! -f "playwright.config.js" ]; then
    echo -e "${RED}Error: Please run this script from the e2e directory${NC}"
    echo "cd /Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/tests/e2e"
    exit 1
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    npm install
fi

# Parse command line arguments
BROWSER=""
MODE=""
GREP=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --chrome|--chromium)
            BROWSER="--project=chromium"
            shift
            ;;
        --firefox)
            BROWSER="--project=firefox"
            shift
            ;;
        --webkit|--safari)
            BROWSER="--project=webkit"
            shift
            ;;
        --ui)
            MODE="--ui"
            shift
            ;;
        --debug)
            MODE="--debug"
            shift
            ;;
        --headed)
            MODE="--headed"
            shift
            ;;
        --test)
            GREP="-g \"$2\""
            shift 2
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Usage: $0 [--chrome|--firefox|--webkit] [--ui|--debug|--headed] [--test \"test name\"]"
            exit 1
            ;;
    esac
done

# Set default browser if not specified
if [ -z "$BROWSER" ]; then
    BROWSER="--project=chromium"
    echo -e "${YELLOW}Using default browser: Chromium${NC}"
fi

# Build the command
CMD="npx playwright test deals/feature1-deal-central-object.spec.js $BROWSER $MODE $GREP"

# Show what we're running
echo -e "${YELLOW}Running command: $CMD${NC}"
echo ""

# Check if SuiteCRM is accessible
echo -e "${YELLOW}Checking SuiteCRM availability...${NC}"
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|301\|302"; then
    echo -e "${GREEN}✓ SuiteCRM is accessible${NC}"
else
    echo -e "${RED}✗ SuiteCRM is not accessible at http://localhost:8080${NC}"
    echo -e "${YELLOW}Please ensure SuiteCRM is running before running tests${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}Starting tests...${NC}"
echo ""

# Run the tests
eval $CMD

# Check exit code
if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}✓ All Feature 1 tests passed!${NC}"
    echo -e "${GREEN}========================================${NC}"
    
    # Show report location
    if [ -z "$MODE" ] || [ "$MODE" != "--ui" ]; then
        echo ""
        echo -e "${YELLOW}View detailed report:${NC}"
        echo "npx playwright show-report"
    fi
else
    echo ""
    echo -e "${RED}========================================${NC}"
    echo -e "${RED}✗ Some tests failed${NC}"
    echo -e "${RED}========================================${NC}"
    
    # Show helpful commands
    echo ""
    echo -e "${YELLOW}Debug failed tests:${NC}"
    echo "1. Run with UI mode: $0 --ui"
    echo "2. Run specific test: $0 --test \"Test Case 1.1\""
    echo "3. View report: npx playwright show-report"
    echo "4. Check screenshots: ls -la test-results/"
fi

echo ""