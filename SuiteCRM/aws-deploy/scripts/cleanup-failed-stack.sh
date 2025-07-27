#!/bin/bash

# MakeDealCRM Failed Stack Cleanup Script
# This script helps users clean up CloudFormation stacks in ROLLBACK_COMPLETE state

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Check if AWS CLI is installed
if ! command -v aws &> /dev/null; then
    print_error "AWS CLI is not installed. Please install it first."
    exit 1
fi

# Check AWS credentials
if ! aws sts get-caller-identity &> /dev/null; then
    print_error "AWS credentials are not configured. Please run 'aws configure' first."
    exit 1
fi

# Get region from argument or use default
REGION=${1:-us-east-1}
print_status "Checking for failed MakeDealCRM stacks in region: $REGION"

# Find all MakeDealCRM stacks in ROLLBACK_COMPLETE or similar states
FAILED_STACKS=$(aws cloudformation list-stacks \
    --region $REGION \
    --stack-status-filter ROLLBACK_COMPLETE UPDATE_ROLLBACK_COMPLETE DELETE_FAILED \
    --query 'StackSummaries[?contains(StackName, `makedealcrm`)].{Name:StackName,Status:StackStatus,Reason:StackStatusReason}' \
    --output json)

if [ "$FAILED_STACKS" == "[]" ]; then
    print_info "No failed MakeDealCRM stacks found in region $REGION"
    
    # Check other regions
    print_status "Checking other common regions..."
    for OTHER_REGION in us-east-2 us-west-2 eu-west-1 eu-central-1; do
        if [ "$OTHER_REGION" != "$REGION" ]; then
            OTHER_FAILED=$(aws cloudformation list-stacks \
                --region $OTHER_REGION \
                --stack-status-filter ROLLBACK_COMPLETE UPDATE_ROLLBACK_COMPLETE DELETE_FAILED \
                --query 'StackSummaries[?contains(StackName, `makedealcrm`)].StackName' \
                --output text 2>/dev/null || echo "")
            
            if [ ! -z "$OTHER_FAILED" ]; then
                print_warning "Found failed stacks in $OTHER_REGION: $OTHER_FAILED"
                print_info "Run this script with region parameter: $0 $OTHER_REGION"
            fi
        fi
    done
    exit 0
fi

# Display failed stacks
echo ""
print_warning "Found the following failed stacks:"
echo "$FAILED_STACKS" | jq -r '.[] | "  - \(.Name) [\(.Status)]"'
echo ""

# Ask for confirmation
read -p "Do you want to delete ALL these failed stacks? (yes/no): " -r
echo

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    print_status "Cleanup cancelled"
    exit 0
fi

# Delete each failed stack
echo "$FAILED_STACKS" | jq -r '.[] | .Name' | while read -r STACK_NAME; do
    print_status "Deleting stack: $STACK_NAME"
    
    # Delete the stack
    if aws cloudformation delete-stack --stack-name "$STACK_NAME" --region "$REGION"; then
        print_status "Delete command sent for $STACK_NAME"
    else
        print_error "Failed to delete $STACK_NAME"
    fi
done

print_status "Waiting for stack deletions to complete..."

# Wait for all deletions to complete
WAIT_COUNT=0
MAX_WAIT=60  # 30 minutes (30 seconds * 60)

while [ $WAIT_COUNT -lt $MAX_WAIT ]; do
    # Check if any stacks are still being deleted
    DELETING=$(aws cloudformation list-stacks \
        --region $REGION \
        --stack-status-filter DELETE_IN_PROGRESS \
        --query 'StackSummaries[?contains(StackName, `makedealcrm`)].StackName' \
        --output text 2>/dev/null || echo "")
    
    if [ -z "$DELETING" ]; then
        print_status "All stack deletions completed!"
        break
    fi
    
    print_info "Stacks still being deleted: $DELETING (waited ${WAIT_COUNT}0s)..."
    sleep 30
    WAIT_COUNT=$((WAIT_COUNT + 1))
done

if [ $WAIT_COUNT -ge $MAX_WAIT ]; then
    print_warning "Timeout reached. Some stacks may still be deleting."
    print_info "Check AWS Console for final status."
fi

# Final check for any remaining failed stacks
REMAINING=$(aws cloudformation list-stacks \
    --region $REGION \
    --stack-status-filter DELETE_FAILED \
    --query 'StackSummaries[?contains(StackName, `makedealcrm`)].StackName' \
    --output text 2>/dev/null || echo "")

if [ ! -z "$REMAINING" ]; then
    print_error "Some stacks failed to delete: $REMAINING"
    print_info "Manual intervention may be required. Check AWS Console for details."
else
    print_status "Cleanup completed successfully!"
    print_info "You can now retry your deployment."
fi