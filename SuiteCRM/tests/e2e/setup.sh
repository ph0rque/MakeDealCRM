#!/bin/bash

# E2E Test Setup Script
# Installs dependencies and configures the E2E test environment

set -e

echo "🚀 Setting up E2E Test Environment"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js first."
    exit 1
fi

print_status "Node.js found: $(node --version)"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install npm first."
    exit 1
fi

print_status "npm found: $(npm --version)"

# Install dependencies
print_info "Installing dependencies..."
npm install

if [ $? -eq 0 ]; then
    print_status "Dependencies installed successfully"
else
    print_error "Failed to install dependencies"
    exit 1
fi

# Install Playwright browsers
print_info "Installing Playwright browsers..."
npx playwright install

if [ $? -eq 0 ]; then
    print_status "Playwright browsers installed successfully"
else
    print_warning "Playwright browsers installation failed. You may need to run 'npx playwright install' manually."
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        print_status "Created .env file from .env.example"
        print_warning "Please update .env file with your configuration"
    else
        print_warning ".env.example not found. You'll need to create .env manually."
    fi
else
    print_status ".env file already exists"
fi

# Create required directories
mkdir -p test-data
mkdir -p test-results/screenshots
mkdir -p test-results/html-report
mkdir -p test-results/videos
mkdir -p test-results/traces

print_status "Created test directories"

# Run environment check
print_info "Running environment check..."
node scripts/check-env.js

if [ $? -eq 0 ]; then
    print_status "Environment check passed"
else
    print_warning "Environment check failed. Please fix the issues before running tests."
fi

echo ""
echo "=================================="
echo -e "${GREEN}🎉 E2E Test Environment Setup Complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Update .env file with your configuration"
echo "2. Make sure Docker is running (docker-compose up)"
echo "3. Run smoke tests: npm run test:smoke"
echo "4. Run all tests: npm test"
echo ""
echo "Available commands:"
echo "  npm test                  - Run all tests"
echo "  npm run test:ui          - Run tests in UI mode"
echo "  npm run test:headed      - Run tests with visible browser"
echo "  npm run test:debug       - Debug tests"
echo "  npm run test:smoke       - Run smoke tests"
echo "  npm run test:deals       - Run deal module tests"
echo "  npm run report           - Show test report"
echo "  npm run check:env        - Check environment"
echo ""