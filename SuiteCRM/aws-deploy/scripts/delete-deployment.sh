#!/bin/bash

# MakeDealCRM AWS Deployment Cleanup Script
# This script deletes all AWS resources created by the deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if stack name is provided
if [ -z "$1" ]; then
    print_warning "No stack name provided. Looking for MakeDealCRM stacks..."
    
    # Find all MakeDealCRM stacks
    STACKS=$(aws cloudformation describe-stacks --region us-east-1 --query 'Stacks[?contains(StackName, `makedealcrm`)].StackName' --output text 2>/dev/null || echo "")
    
    if [ -z "$STACKS" ]; then
        print_error "No MakeDealCRM stacks found in us-east-1"
        print_status "Checking other regions..."
        
        # Check other common regions
        for REGION in us-east-2 us-west-2 eu-west-1; do
            REGIONAL_STACKS=$(aws cloudformation describe-stacks --region $REGION --query 'Stacks[?contains(StackName, `makedealcrm`)].StackName' --output text 2>/dev/null || echo "")
            if [ ! -z "$REGIONAL_STACKS" ]; then
                print_status "Found stacks in $REGION: $REGIONAL_STACKS"
                STACKS="$STACKS $REGIONAL_STACKS"
            fi
        done
    fi
    
    if [ -z "$STACKS" ]; then
        print_error "No MakeDealCRM stacks found"
        exit 1
    fi
    
    print_status "Found the following MakeDealCRM stacks:"
    for STACK in $STACKS; do
        echo "  - $STACK"
    done
    
    echo ""
    read -p "Delete all these stacks? (yes/no): " CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        print_warning "Deletion cancelled"
        exit 0
    fi
else
    STACKS=$1
fi

# Function to delete a stack
delete_stack() {
    local STACK_NAME=$1
    local REGION=${2:-us-east-1}
    
    print_status "Deleting stack: $STACK_NAME in region: $REGION"
    
    # First, we need to empty and delete any S3 buckets created by the stack
    print_status "Checking for S3 buckets..."
    BUCKET=$(aws cloudformation describe-stacks --stack-name $STACK_NAME --region $REGION --query 'Stacks[0].Outputs[?OutputKey==`BackupBucket`].OutputValue' --output text 2>/dev/null || echo "")
    
    if [ ! -z "$BUCKET" ] && [ "$BUCKET" != "None" ]; then
        print_status "Found S3 bucket: $BUCKET"
        print_status "Emptying bucket..."
        aws s3 rm s3://$BUCKET --recursive --region $REGION 2>/dev/null || true
        print_status "Deleting bucket..."
        aws s3 rb s3://$BUCKET --force --region $REGION 2>/dev/null || true
    fi
    
    # Delete the stack
    print_status "Initiating stack deletion..."
    aws cloudformation delete-stack --stack-name $STACK_NAME --region $REGION
    
    print_status "Waiting for stack deletion to complete (this may take several minutes)..."
    aws cloudformation wait stack-delete-complete --stack-name $STACK_NAME --region $REGION
    
    print_status "Stack $STACK_NAME deleted successfully!"
}

# Main deletion process
print_status "Starting MakeDealCRM AWS resource cleanup..."

# For the specific deployment mentioned
if [[ "$STACKS" == *"makedealcrm-production"* ]]; then
    print_status "Deleting makedealcrm-production stack and all its resources..."
    delete_stack "makedealcrm-production" "us-east-1"
else
    # Delete all found stacks
    for STACK in $STACKS; do
        # Determine region (default to us-east-1)
        REGION="us-east-1"
        
        # Check if stack exists in us-east-1
        if ! aws cloudformation describe-stacks --stack-name $STACK --region us-east-1 &>/dev/null; then
            # Check other regions
            for CHECK_REGION in us-east-2 us-west-2 eu-west-1; do
                if aws cloudformation describe-stacks --stack-name $STACK --region $CHECK_REGION &>/dev/null; then
                    REGION=$CHECK_REGION
                    break
                fi
            done
        fi
        
        delete_stack $STACK $REGION
    done
fi

# Clean up any orphaned resources
print_status "Checking for orphaned resources..."

# Check for key pairs
print_status "Checking for MakeDealCRM key pairs..."
KEY_PAIRS=$(aws ec2 describe-key-pairs --region us-east-1 --query 'KeyPairs[?contains(KeyName, `makedealcrm`)].KeyName' --output text 2>/dev/null || echo "")

if [ ! -z "$KEY_PAIRS" ]; then
    print_status "Found key pairs:"
    for KEY in $KEY_PAIRS; do
        echo "  - $KEY"
        read -p "Delete key pair $KEY? (yes/no): " CONFIRM
        if [ "$CONFIRM" == "yes" ]; then
            aws ec2 delete-key-pair --key-name $KEY --region us-east-1
            print_status "Deleted key pair: $KEY"
        fi
    done
fi

# Check for any remaining EC2 instances with makedealcrm tags
print_status "Checking for any remaining EC2 instances..."
INSTANCES=$(aws ec2 describe-instances --region us-east-1 --filters "Name=tag:Name,Values=*makedealcrm*" "Name=instance-state-name,Values=running,stopped" --query 'Reservations[*].Instances[*].InstanceId' --output text 2>/dev/null || echo "")

if [ ! -z "$INSTANCES" ]; then
    print_warning "Found orphaned instances: $INSTANCES"
    print_warning "These should have been deleted with the stack. Manual cleanup may be required."
fi

print_status ""
print_status "Cleanup complete!"
print_status ""
print_status "Summary:"
print_status "- Deleted CloudFormation stack(s)"
print_status "- Deleted associated S3 buckets"
print_status "- Cleaned up key pairs (if confirmed)"
print_status ""
print_warning "Note: Some resources like CloudWatch logs may remain and will incur minimal charges."
print_warning "You can delete them manually from the AWS Console if needed."