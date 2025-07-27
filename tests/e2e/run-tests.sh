#!/bin/bash

# MakeDealCRM E2E Test Runner
# This script provides a convenient way to run different test suites

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
TEST_TYPE="all"
BROWSER="chromium"
HEADED=false
DEBUG=false
WORKERS=1
RETRIES=0
BASE_URL="http://localhost:8080"

# Function to display usage
usage() {
    echo -e "${BLUE}MakeDealCRM E2E Test Runner${NC}"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -t, --type TYPE        Test type: all, smoke, regression, auth, deals, pipeline, checklists, stakeholders, financial, email"
    echo "  -b, --browser BROWSER  Browser: chromium, firefox, webkit, all"
    echo "  -h, --headed          Run in headed mode (visible browser)"
    echo "  -d, --debug           Run in debug mode"
    echo "  -w, --workers NUM     Number of parallel workers (default: 1)"
    echo "  -r, --retries NUM     Number of retries on failure (default: 0)"
    echo "  -u, --url URL         Base URL (default: http://localhost:8080)"
    echo "  --ui                  Run with Playwright UI"
    echo "  --report              Show HTML report"
    echo "  --install             Install dependencies and browsers"
    echo "  --clean               Clean test artifacts"
    echo "  --help                Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 --type smoke --browser chromium"
    echo "  $0 --type deals --headed --debug"
    echo "  $0 --type all --workers 4 --retries 2"
    echo "  $0 --install"
    echo ""
}

# Function to check if SuiteCRM is accessible
check_suitecrm() {
    echo -e "${BLUE}Checking SuiteCRM availability at ${BASE_URL}...${NC}"
    
    if curl -s --head "$BASE_URL" | head -n 1 | grep -q "200\|302"; then
        echo -e "${GREEN}✓ SuiteCRM is accessible${NC}"
    else
        echo -e "${RED}✗ SuiteCRM is not accessible at ${BASE_URL}${NC}"
        echo -e "${YELLOW}Please ensure SuiteCRM is running in Docker:${NC}"
        echo "  docker-compose up -d"
        exit 1
    fi
}

# Function to install dependencies
install_deps() {
    echo -e "${BLUE}Installing dependencies...${NC}"
    npm ci
    
    echo -e "${BLUE}Installing Playwright browsers...${NC}"
    npx playwright install --with-deps
    
    echo -e "${GREEN}✓ Installation complete${NC}"
}

# Function to clean artifacts
clean_artifacts() {
    echo -e "${BLUE}Cleaning test artifacts...${NC}"
    rm -rf test-results reports/screenshots/* reports/videos/* reports/traces/*
    echo -e "${GREEN}✓ Cleanup complete${NC}"
}

# Function to run tests
run_tests() {
    local cmd="npx playwright test"
    
    # Add browser option
    if [ "$BROWSER" != "all" ]; then
        cmd="$cmd --project=$BROWSER"
    fi
    
    # Add test type
    case $TEST_TYPE in
        "smoke")
            cmd="$cmd --grep @smoke"
            ;;
        "regression")
            cmd="$cmd --grep @regression"
            ;;
        "auth")
            cmd="$cmd auth/"
            ;;
        "deals")
            cmd="$cmd deals/"
            ;;
        "pipeline")
            cmd="$cmd pipeline/"
            ;;
        "checklists")
            cmd="$cmd checklists/"
            ;;
        "stakeholders")
            cmd="$cmd stakeholders/"
            ;;
        "financial")
            cmd="$cmd financial/"
            ;;
        "email")
            cmd="$cmd email-integration/"
            ;;
        "all")
            # Run all tests
            ;;
        *)
            echo -e "${RED}Unknown test type: $TEST_TYPE${NC}"
            exit 1
            ;;
    esac
    
    # Add other options
    if [ "$HEADED" = true ]; then
        cmd="$cmd --headed"
    fi
    
    if [ "$DEBUG" = true ]; then
        cmd="$cmd --debug"
    fi
    
    cmd="$cmd --workers=$WORKERS"
    cmd="$cmd --retries=$RETRIES"
    
    # Set environment variables
    export BASE_URL="$BASE_URL"
    
    echo -e "${BLUE}Running tests...${NC}"
    echo -e "${YELLOW}Command: $cmd${NC}"
    echo ""
    
    # Run the tests
    eval $cmd
    
    local exit_code=$?
    
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ All tests passed!${NC}"
    else
        echo -e "${RED}✗ Some tests failed (exit code: $exit_code)${NC}"
    fi
    
    return $exit_code
}

# Function to show report
show_report() {
    echo -e "${BLUE}Opening HTML report...${NC}"
    npx playwright show-report reports/html
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--type)
            TEST_TYPE="$2"
            shift 2
            ;;
        -b|--browser)
            BROWSER="$2"
            shift 2
            ;;
        -h|--headed)
            HEADED=true
            shift
            ;;
        -d|--debug)
            DEBUG=true
            shift
            ;;
        -w|--workers)
            WORKERS="$2"
            shift 2
            ;;
        -r|--retries)
            RETRIES="$2"
            shift 2
            ;;
        -u|--url)
            BASE_URL="$2"
            shift 2
            ;;
        --ui)
            echo -e "${BLUE}Starting Playwright UI...${NC}"
            npx playwright test --ui
            exit 0
            ;;
        --report)
            show_report
            exit 0
            ;;
        --install)
            install_deps
            exit 0
            ;;
        --clean)
            clean_artifacts
            exit 0
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            usage
            exit 1
            ;;
    esac
done

# Main execution
echo -e "${BLUE}MakeDealCRM E2E Test Runner${NC}"
echo -e "${BLUE}=============================${NC}"
echo ""
echo -e "Test Type: ${YELLOW}$TEST_TYPE${NC}"
echo -e "Browser: ${YELLOW}$BROWSER${NC}"
echo -e "Base URL: ${YELLOW}$BASE_URL${NC}"
echo -e "Workers: ${YELLOW}$WORKERS${NC}"
echo -e "Retries: ${YELLOW}$RETRIES${NC}"
echo ""

# Check if package.json exists
if [ ! -f "package.json" ]; then
    echo -e "${RED}Error: package.json not found. Please run from the tests/e2e directory.${NC}"
    exit 1
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Dependencies not installed. Installing...${NC}"
    install_deps
fi

# Check SuiteCRM availability
check_suitecrm

# Run tests
run_tests
exit_code=$?

# Show report if tests completed
if [ $exit_code -eq 0 ] || [ $exit_code -eq 1 ]; then
    echo ""
    echo -e "${BLUE}Test report available at: reports/html/index.html${NC}"
    echo -e "${BLUE}Run './run-tests.sh --report' to view in browser${NC}"
fi

exit $exit_code