#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "     Deals Module Test Suite Runner      "
echo "========================================="

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker is not running${NC}"
    exit 1
fi

# Run PHPUnit tests
echo -e "\n${YELLOW}Running PHPUnit Tests...${NC}"
docker exec suitecrm bash -c "cd /var/www/html && ./vendor/bin/phpunit tests/unit/phpunit/modules/Deals/DealTest.php"
PHPUNIT_DEAL=$?

docker exec suitecrm bash -c "cd /var/www/html && ./vendor/bin/phpunit tests/unit/phpunit/modules/Deals/DealsLogicHooksTest.php"
PHPUNIT_HOOKS=$?

# Check PHPUnit results
if [ $PHPUNIT_DEAL -eq 0 ] && [ $PHPUNIT_HOOKS -eq 0 ]; then
    echo -e "${GREEN}✓ PHPUnit tests passed${NC}"
else
    echo -e "${RED}✗ PHPUnit tests failed${NC}"
fi

# Install Playwright if needed
echo -e "\n${YELLOW}Setting up Playwright...${NC}"
cd e2e
npm install @playwright/test
npx playwright install chromium

# Run Playwright tests
echo -e "\n${YELLOW}Running Playwright E2E Tests...${NC}"
npx playwright test deals/deals.spec.js
PLAYWRIGHT_RESULT=$?

if [ $PLAYWRIGHT_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ Playwright tests passed${NC}"
else
    echo -e "${RED}✗ Playwright tests failed${NC}"
fi

# Generate test report
echo -e "\n${YELLOW}Generating Test Report...${NC}"
npx playwright show-report

# Summary
echo -e "\n========================================="
echo "              Test Summary               "
echo "========================================="

if [ $PHPUNIT_DEAL -eq 0 ]; then
    echo -e "Deal Bean Tests:        ${GREEN}PASSED${NC}"
else
    echo -e "Deal Bean Tests:        ${RED}FAILED${NC}"
fi

if [ $PHPUNIT_HOOKS -eq 0 ]; then
    echo -e "Logic Hooks Tests:      ${GREEN}PASSED${NC}"
else
    echo -e "Logic Hooks Tests:      ${RED}FAILED${NC}"
fi

if [ $PLAYWRIGHT_RESULT -eq 0 ]; then
    echo -e "E2E UI Tests:           ${GREEN}PASSED${NC}"
else
    echo -e "E2E UI Tests:           ${RED}FAILED${NC}"
fi

echo "========================================="

# Exit with appropriate code
if [ $PHPUNIT_DEAL -eq 0 ] && [ $PHPUNIT_HOOKS -eq 0 ] && [ $PLAYWRIGHT_RESULT -eq 0 ]; then
    exit 0
else
    exit 1
fi