#!/bin/bash

# MakeDealCRM Stack Failure Diagnostic Script
# This script helps diagnose why a CloudFormation stack failed

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

# Get stack name from argument or use default
STACK_NAME=${1:-makedealcrm-production}
REGION=${2:-us-east-1}

print_status "Diagnosing stack failure for: $STACK_NAME in region: $REGION"

# Check if stack exists
if ! aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$REGION" &>/dev/null; then
    print_error "Stack $STACK_NAME not found in region $REGION"
    exit 1
fi

# Get stack status
STACK_STATUS=$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$REGION" --query 'Stacks[0].StackStatus' --output text)
print_info "Current stack status: $STACK_STATUS"

echo ""
print_status "Fetching stack events to identify failure cause..."
echo ""

# Get stack events, focusing on failed resources
print_warning "Failed Events:"
aws cloudformation describe-stack-events \
    --stack-name "$STACK_NAME" \
    --region "$REGION" \
    --query 'StackEvents[?ResourceStatus==`CREATE_FAILED` || ResourceStatus==`UPDATE_FAILED` || ResourceStatus==`DELETE_FAILED`].[Timestamp,ResourceType,LogicalResourceId,ResourceStatusReason]' \
    --output table

echo ""
print_info "Recent Events (last 10):"
aws cloudformation describe-stack-events \
    --stack-name "$STACK_NAME" \
    --region "$REGION" \
    --query 'StackEvents[0:10].[Timestamp,ResourceStatus,ResourceType,LogicalResourceId,ResourceStatusReason]' \
    --output table

# Check for common issues
echo ""
print_status "Checking for common issues..."

# Check if AMI exists in the region
print_info "Checking AMI availability..."
TEMPLATE_BODY=$(aws cloudformation get-template --stack-name "$STACK_NAME" --region "$REGION" --query 'TemplateBody' --output json 2>/dev/null || echo "{}")

# Check instance type availability
print_info "Checking instance type availability in region..."
INSTANCE_TYPES=$(aws ec2 describe-instance-types --region "$REGION" --query 'InstanceTypes[].InstanceType' --output text 2>/dev/null | grep -E "t3\.(small|medium|large)" | head -5)
echo "Available T3 instance types: $INSTANCE_TYPES"

# Check for VPC quota
print_info "Checking VPC quota..."
VPC_COUNT=$(aws ec2 describe-vpcs --region "$REGION" --query 'length(Vpcs)' --output text)
echo "Current VPC count: $VPC_COUNT (default limit is usually 5)"

# Check for Elastic IP quota
print_info "Checking Elastic IP quota..."
EIP_COUNT=$(aws ec2 describe-addresses --region "$REGION" --query 'length(Addresses)' --output text)
echo "Current Elastic IP count: $EIP_COUNT (default limit is usually 5)"

# Get stack parameters
echo ""
print_info "Stack Parameters:"
aws cloudformation describe-stacks \
    --stack-name "$STACK_NAME" \
    --region "$REGION" \
    --query 'Stacks[0].Parameters' \
    --output table

# Provide recommendations
echo ""
print_status "Recommendations based on common issues:"
echo ""

if [[ "$STACK_STATUS" == *"ROLLBACK"* ]]; then
    print_warning "1. Delete the failed stack before retrying:"
    echo "   aws cloudformation delete-stack --stack-name $STACK_NAME --region $REGION"
    echo ""
fi

print_info "2. Common causes of stack creation failure:"
echo "   - Invalid AMI ID for the region"
echo "   - Insufficient IAM permissions"
echo "   - Service quota limits (VPC, Elastic IP, EC2 instances)"
echo "   - Invalid parameter values"
echo "   - Network configuration issues"
echo ""

print_info "3. To view the full CloudFormation template:"
echo "   aws cloudformation get-template --stack-name $STACK_NAME --region $REGION"
echo ""

print_info "4. To check service quotas:"
echo "   aws service-quotas list-service-quotas --service-code ec2 --region $REGION"

# Save diagnostic report
REPORT_FILE="stack-diagnostic-$(date +%Y%m%d-%H%M%S).txt"
{
    echo "Stack Diagnostic Report"
    echo "======================"
    echo "Stack Name: $STACK_NAME"
    echo "Region: $REGION"
    echo "Status: $STACK_STATUS"
    echo "Generated: $(date)"
    echo ""
    echo "Failed Events:"
    aws cloudformation describe-stack-events \
        --stack-name "$STACK_NAME" \
        --region "$REGION" \
        --query 'StackEvents[?ResourceStatus==`CREATE_FAILED`]' \
        --output json
} > "$REPORT_FILE"

echo ""
print_status "Diagnostic report saved to: $REPORT_FILE"