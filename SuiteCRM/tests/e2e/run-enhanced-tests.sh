#!/bin/bash

# Enhanced E2E Test Execution Script
# Runs comprehensive test suite with all assertion types

set -e

echo "🚀 Starting Enhanced E2E Test Suite"
echo "===================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
print_status "Checking prerequisites..."

# Check if Docker is running
if ! docker ps > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker first."
    exit 1
fi

# Check if SuiteCRM is accessible
if ! curl -s http://localhost:8080 > /dev/null; then
    print_warning "SuiteCRM not accessible at http://localhost:8080"
    print_status "Starting SuiteCRM containers..."
    docker-compose -f ../../../docker-compose.yml up -d
    
    # Wait for SuiteCRM to be ready
    echo "Waiting for SuiteCRM to start..."
    for i in {1..30}; do
        if curl -s http://localhost:8080 > /dev/null; then
            break
        fi
        sleep 2
        echo -n "."
    done
    echo ""
fi

# Verify database connection
print_status "Verifying database connection..."
if ! npm run check:env > /dev/null 2>&1; then
    print_error "Database connection check failed. Please verify DB environment variables."
    exit 1
fi

print_success "Prerequisites check completed"

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    print_status "Installing dependencies..."
    npm install
fi

# Install browsers if needed
print_status "Ensuring browsers are installed..."
npx playwright install

# Clean previous test results
print_status "Cleaning previous test results..."
rm -rf test-results/* || true
rm -rf playwright-report/* || true

# Create results directories
mkdir -p test-results/visual-baselines
mkdir -p test-results/visual-diffs
mkdir -p test-results/performance

# Set test environment variables
export NODE_ENV=test
export CI=${CI:-false}
export PWDEBUG=${PWDEBUG:-0}

# Function to run test suite
run_test_suite() {
    local suite_name="$1"
    local test_command="$2"
    local description="$3"
    
    echo ""
    print_status "Running $suite_name"
    echo "Description: $description"
    echo "Command: $test_command"
    echo "----------------------------------------"
    
    if eval "$test_command"; then
        print_success "$suite_name completed successfully"
        return 0
    else
        print_error "$suite_name failed"
        return 1
    fi
}

# Test execution plan
FAILED_SUITES=()

# 1. Enhanced Integration Tests
if run_test_suite "Enhanced Integration Tests" \
   "npm run test:integration" \
   "Comprehensive integration tests with UI, data, audit, and visual verification"; then
    :
else
    FAILED_SUITES+=("Enhanced Integration")
fi

# 2. Feature Tests with Enhanced Assertions
if run_test_suite "Feature Tests (Enhanced)" \
   "npm run test:deals" \
   "Updated feature tests using enhanced assertion helpers"; then
    :
else
    FAILED_SUITES+=("Feature Tests")
fi

# 3. Visual Regression Tests
if run_test_suite "Visual Regression Tests" \
   "npm run test:visual" \
   "Cross-browser and responsive design visual consistency tests"; then
    :
else
    FAILED_SUITES+=("Visual Regression")
fi

# 4. Performance Tests
if run_test_suite "Performance Tests" \
   "npm run test:performance" \
   "Page load time, API response, and memory usage monitoring"; then
    :
else
    FAILED_SUITES+=("Performance Tests")
fi

# 5. Audit Trail Tests
if run_test_suite "Audit Trail Tests" \
   "npm run test:audit" \
   "Database audit log and activity timeline verification"; then
    :
else
    FAILED_SUITES+=("Audit Trail")
fi

# Generate comprehensive test report
print_status "Generating comprehensive test report..."

# Create HTML report with enhanced metrics
cat > test-results/enhanced-test-report.html << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Enhanced E2E Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .metric-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .metric-value { font-size: 2em; font-weight: bold; color: #4CAF50; }
        .failed { color: #f44336; }
        .test-section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-results { margin-top: 20px; }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .timestamp { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 Enhanced E2E Test Report</h1>
        <p class="timestamp">Generated on: $(date)</p>
        <p>Comprehensive testing with UI, data persistence, audit logs, and visual regression</p>
    </div>
    
    <div class="summary">
        <div class="metric-card">
            <h3>Test Coverage</h3>
            <div class="metric-value">5</div>
            <p>Test Suites Executed</p>
        </div>
        <div class="metric-card">
            <h3>Success Rate</h3>
            <div class="metric-value $([ ${#FAILED_SUITES[@]} -eq 0 ] && echo '' || echo 'failed')">$((100 - (${#FAILED_SUITES[@]} * 20)))%</div>
            <p>Overall Pass Rate</p>
        </div>
        <div class="metric-card">
            <h3>Failed Suites</h3>
            <div class="metric-value $([ ${#FAILED_SUITES[@]} -eq 0 ] && echo '' || echo 'failed')">${#FAILED_SUITES[@]}</div>
            <p>Requiring Attention</p>
        </div>
        <div class="metric-card">
            <h3>Enhancement Level</h3>
            <div class="metric-value">100%</div>
            <p>Enhanced Assertions</p>
        </div>
    </div>
    
    <div class="test-section">
        <h2>📊 Test Suite Results</h2>
        <ul>
            <li class="$(echo "${FAILED_SUITES[@]}" | grep -q "Enhanced Integration" && echo "error" || echo "success")">
                Enhanced Integration Tests - UI, Data, Audit & Visual verification
            </li>
            <li class="$(echo "${FAILED_SUITES[@]}" | grep -q "Feature Tests" && echo "error" || echo "success")">
                Feature Tests (Enhanced) - Updated with comprehensive assertions
            </li>
            <li class="$(echo "${FAILED_SUITES[@]}" | grep -q "Visual Regression" && echo "error" || echo "success")">
                Visual Regression Tests - Cross-browser consistency
            </li>
            <li class="$(echo "${FAILED_SUITES[@]}" | grep -q "Performance Tests" && echo "error" || echo "success")">
                Performance Tests - Load time and memory monitoring
            </li>
            <li class="$(echo "${FAILED_SUITES[@]}" | grep -q "Audit Trail" && echo "error" || echo "success")">
                Audit Trail Tests - Database audit log verification
            </li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>🔧 Enhanced Features</h2>
        <ul>
            <li>✅ UI State Change Assertions</li>
            <li>✅ Database Persistence Verification</li>
            <li>✅ Audit Log & Activity Timeline Tracking</li>
            <li>✅ Performance Monitoring (Load times, Memory usage)</li>
            <li>✅ Visual Regression Testing</li>
            <li>✅ Cross-Browser Consistency</li>
            <li>✅ Custom Playwright Matchers</li>
            <li>✅ Responsive Design Testing</li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>📈 Metrics Covered</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <h4>UI Testing</h4>
                <ul>
                    <li>Element visibility</li>
                    <li>Text updates</li>
                    <li>Form states</li>
                    <li>Loading indicators</li>
                </ul>
            </div>
            <div>
                <h4>Data Integrity</h4>
                <ul>
                    <li>Database persistence</li>
                    <li>Relationship integrity</li>
                    <li>Field validation</li>
                    <li>Transaction consistency</li>
                </ul>
            </div>
            <div>
                <h4>Audit & Compliance</h4>
                <ul>
                    <li>Audit log entries</li>
                    <li>Activity tracking</li>
                    <li>Change history</li>
                    <li>User actions</li>
                </ul>
            </div>
            <div>
                <h4>Performance</h4>
                <ul>
                    <li>Page load times</li>
                    <li>API responses</li>
                    <li>Memory usage</li>
                    <li>DOM operations</li>
                </ul>
            </div>
        </div>
    </div>
    
    $([ ${#FAILED_SUITES[@]} -gt 0 ] && cat << 'FAILURES'
    <div class="test-section" style="border-left: 4px solid #f44336;">
        <h2>❌ Failed Test Suites</h2>
        <p>The following test suites require attention:</p>
        <ul>
FAILURES
)
    
    $(for suite in "${FAILED_SUITES[@]}"; do
        echo "<li class=\"error\">$suite</li>"
    done)
    
    $([ ${#FAILED_SUITES[@]} -gt 0 ] && echo "</ul></div>")
    
</body>
</html>
EOF

# Summary
echo ""
echo "======================================"
print_status "Enhanced E2E Test Suite Complete"
echo "======================================"

if [ ${#FAILED_SUITES[@]} -eq 0 ]; then
    print_success "All test suites passed! 🎉"
    echo ""
    echo "✅ Enhanced Integration Tests"
    echo "✅ Feature Tests with Enhanced Assertions"
    echo "✅ Visual Regression Tests"
    echo "✅ Performance Monitoring"
    echo "✅ Audit Trail Verification"
else
    print_error "Some test suites failed:"
    for suite in "${FAILED_SUITES[@]}"; do
        echo "❌ $suite"
    done
    echo ""
    print_status "Check detailed reports for debugging information"
fi

echo ""
print_status "Reports generated:"
echo "📊 HTML Report: test-results/enhanced-test-report.html"
echo "📊 Playwright Report: playwright-report/index.html"
echo "📊 Visual Diffs: test-results/visual-diffs/"
echo "📊 Performance Metrics: test-results/performance/"

echo ""
print_status "Quick commands for debugging:"
echo "🔍 View HTML report: open test-results/enhanced-test-report.html"
echo "🔍 View Playwright report: npm run report"
echo "🔍 Debug failed tests: npm run test:enhanced:debug"
echo "🔍 Update visual baselines: npm run test:visual:update"

# Exit with error code if any suites failed
if [ ${#FAILED_SUITES[@]} -gt 0 ]; then
    exit 1
else
    exit 0
fi