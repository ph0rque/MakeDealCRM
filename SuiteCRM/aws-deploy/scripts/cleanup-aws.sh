#!/bin/bash

# AWS Cleanup Script - Remove all MakeDealCRM resources
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Get AWS credentials from environment or prompt
if [ -z "$AWS_ACCESS_KEY_ID" ] || [ -z "$AWS_SECRET_ACCESS_KEY" ]; then
    print_warning "AWS credentials not found in environment"
    read -p "Enter AWS Access Key ID: " AWS_ACCESS_KEY_ID
    read -s -p "Enter AWS Secret Access Key: " AWS_SECRET_ACCESS_KEY
    echo
    export AWS_ACCESS_KEY_ID
    export AWS_SECRET_ACCESS_KEY
fi

# Get region
read -p "Enter AWS Region (default: us-east-1): " REGION
REGION=${REGION:-us-east-1}

print_status "Checking for MakeDealCRM CloudFormation stacks in region $REGION..."

# List all stacks that match MakeDealCRM pattern
STACKS=$(aws cloudformation list-stacks \
    --region $REGION \
    --stack-status-filter CREATE_COMPLETE UPDATE_COMPLETE \
    --query "StackSummaries[?contains(StackName, 'makedealcrm')].StackName" \
    --output text)

if [ -z "$STACKS" ]; then
    print_warning "No MakeDealCRM stacks found in region $REGION"
    
    # Check for orphaned resources
    print_status "Checking for orphaned resources..."
    
    # Check for EC2 instances
    INSTANCES=$(aws ec2 describe-instances \
        --region $REGION \
        --filters "Name=tag:Name,Values=*makedealcrm*" "Name=instance-state-name,Values=running,stopped" \
        --query "Reservations[].Instances[].InstanceId" \
        --output text)
    
    if [ ! -z "$INSTANCES" ]; then
        print_warning "Found orphaned EC2 instances: $INSTANCES"
        read -p "Delete these instances? (y/N): " CONFIRM
        if [[ $CONFIRM =~ ^[Yy]$ ]]; then
            for INSTANCE in $INSTANCES; do
                print_status "Terminating instance $INSTANCE..."
                aws ec2 terminate-instances --instance-ids $INSTANCE --region $REGION
            done
        fi
    fi
    
    # Check for RDS instances
    RDS_INSTANCES=$(aws rds describe-db-instances \
        --region $REGION \
        --query "DBInstances[?contains(DBInstanceIdentifier, 'makedealcrm')].DBInstanceIdentifier" \
        --output text)
    
    if [ ! -z "$RDS_INSTANCES" ]; then
        print_warning "Found orphaned RDS instances: $RDS_INSTANCES"
        read -p "Delete these RDS instances? (y/N): " CONFIRM
        if [[ $CONFIRM =~ ^[Yy]$ ]]; then
            for RDS in $RDS_INSTANCES; do
                print_status "Deleting RDS instance $RDS..."
                aws rds delete-db-instance \
                    --db-instance-identifier $RDS \
                    --skip-final-snapshot \
                    --delete-automated-backups \
                    --region $REGION
            done
        fi
    fi
    
    exit 0
fi

print_status "Found the following MakeDealCRM stacks:"
echo "$STACKS"
echo

# Confirm deletion
print_warning "This will delete ALL resources associated with these stacks, including:"
echo "  - EC2 instances"
echo "  - RDS databases (data will be lost!)"
echo "  - VPCs and security groups"
echo "  - S3 buckets"
echo "  - All other associated resources"
echo
read -p "Are you sure you want to proceed? Type 'DELETE' to confirm: " CONFIRM

if [ "$CONFIRM" != "DELETE" ]; then
    print_error "Deletion cancelled"
    exit 1
fi

# Delete each stack
for STACK in $STACKS; do
    print_status "Deleting stack: $STACK"
    
    # First, empty and delete any S3 buckets associated with the stack
    BUCKETS=$(aws cloudformation describe-stack-resources \
        --stack-name $STACK \
        --region $REGION \
        --query "StackResources[?ResourceType=='AWS::S3::Bucket'].PhysicalResourceId" \
        --output text)
    
    for BUCKET in $BUCKETS; do
        if [ ! -z "$BUCKET" ] && [ "$BUCKET" != "None" ]; then
            print_status "Emptying S3 bucket: $BUCKET"
            aws s3 rm s3://$BUCKET --recursive --region $REGION 2>/dev/null || true
            
            # Delete all versions if versioning is enabled
            aws s3api delete-objects \
                --bucket $BUCKET \
                --delete "$(aws s3api list-object-versions \
                    --bucket $BUCKET \
                    --query '{Objects: Versions[].{Key:Key,VersionId:VersionId}}' \
                    --region $REGION)" \
                --region $REGION 2>/dev/null || true
        fi
    done
    
    # Delete the stack
    aws cloudformation delete-stack --stack-name $STACK --region $REGION
    
    print_status "Waiting for stack deletion to complete..."
    aws cloudformation wait stack-delete-complete --stack-name $STACK --region $REGION || {
        print_error "Stack deletion failed or timed out for $STACK"
        print_status "Checking deletion status..."
        aws cloudformation describe-stacks --stack-name $STACK --region $REGION 2>/dev/null || true
    }
    
    print_status "Stack $STACK deleted successfully"
done

# Clean up any remaining resources
print_status "Checking for any remaining resources..."

# Release Elastic IPs
EIPS=$(aws ec2 describe-addresses \
    --region $REGION \
    --query "Addresses[?contains(Tags[?Key=='Name'].Value | [0], 'makedealcrm')].AllocationId" \
    --output text)

for EIP in $EIPS; do
    if [ ! -z "$EIP" ] && [ "$EIP" != "None" ]; then
        print_status "Releasing Elastic IP: $EIP"
        aws ec2 release-address --allocation-id $EIP --region $REGION
    fi
done

# Delete Key Pairs
KEY_PAIRS=$(aws ec2 describe-key-pairs \
    --region $REGION \
    --query "KeyPairs[?contains(KeyName, 'makedealcrm')].KeyName" \
    --output text)

for KEY in $KEY_PAIRS; do
    if [ ! -z "$KEY" ] && [ "$KEY" != "None" ]; then
        print_status "Deleting key pair: $KEY"
        aws ec2 delete-key-pair --key-name $KEY --region $REGION
    fi
done

print_status "Cleanup completed!"
print_warning "Note: Some resources like CloudWatch Logs may persist and continue to incur minimal charges."