#!/bin/bash

# MakeDealCRM AWS Resource Cleanup Script
# This script deletes all AWS resources created by the deployment

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print functions
print_status() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
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

# Get region from environment or use default
REGION=${AWS_REGION:-us-east-1}

# Resources to clean up based on deployment logs
STACK_NAME="makedealcrm-production"
KEY_PAIR_NAME="makedealcrm-makedealcrm-test"

print_status "Starting AWS resource cleanup for MakeDealCRM..."
print_info "Region: $REGION"

# Function to wait for stack deletion
wait_for_stack_deletion() {
    local stack_name=$1
    local max_attempts=60
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        status=$(aws cloudformation describe-stacks --stack-name "$stack_name" --region "$REGION" --query 'Stacks[0].StackStatus' --output text 2>/dev/null)
        
        if [ $? -ne 0 ]; then
            print_status "Stack $stack_name has been deleted successfully!"
            return 0
        fi
        
        if [ "$status" == "DELETE_COMPLETE" ]; then
            print_status "Stack deletion completed!"
            return 0
        elif [ "$status" == "DELETE_FAILED" ]; then
            print_error "Stack deletion failed! Manual intervention may be required."
            return 1
        else
            echo -ne "\rDeleting stack... Status: $status (attempt $((attempt+1))/$max_attempts)"
            sleep 10
            ((attempt++))
        fi
    done
    
    print_error "Timeout waiting for stack deletion"
    return 1
}

# 1. Delete CloudFormation Stack
print_status "Checking for CloudFormation stack: $STACK_NAME"
if aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$REGION" &> /dev/null; then
    print_status "Found stack $STACK_NAME. Initiating deletion..."
    
    # Disable termination protection if enabled
    aws cloudformation update-termination-protection \
        --stack-name "$STACK_NAME" \
        --region "$REGION" \
        --no-enable-termination-protection &> /dev/null
    
    # Delete the stack
    if aws cloudformation delete-stack --stack-name "$STACK_NAME" --region "$REGION"; then
        print_status "Stack deletion initiated. Waiting for completion..."
        wait_for_stack_deletion "$STACK_NAME"
    else
        print_error "Failed to initiate stack deletion"
    fi
else
    print_info "Stack $STACK_NAME not found or already deleted"
fi

# 2. Delete EC2 Key Pair
print_status "Checking for EC2 key pair: $KEY_PAIR_NAME"
if aws ec2 describe-key-pairs --key-names "$KEY_PAIR_NAME" --region "$REGION" &> /dev/null; then
    print_status "Found key pair $KEY_PAIR_NAME. Deleting..."
    if aws ec2 delete-key-pair --key-name "$KEY_PAIR_NAME" --region "$REGION"; then
        print_status "Key pair deleted successfully!"
    else
        print_error "Failed to delete key pair"
    fi
else
    print_info "Key pair $KEY_PAIR_NAME not found or already deleted"
fi

# 3. Check for orphaned S3 buckets with makedealcrm prefix
print_status "Checking for orphaned S3 buckets..."
buckets=$(aws s3api list-buckets --query "Buckets[?contains(Name, 'makedealcrm')].Name" --output text)

if [ -n "$buckets" ]; then
    print_warning "Found S3 buckets with 'makedealcrm' prefix:"
    for bucket in $buckets; do
        echo "  - $bucket"
        
        # Check if bucket is empty
        object_count=$(aws s3api list-objects-v2 --bucket "$bucket" --max-items 1 --query 'KeyCount' --output text 2>/dev/null)
        
        if [ "$object_count" == "0" ] || [ "$object_count" == "None" ]; then
            read -p "Delete empty bucket $bucket? (y/N) " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                # Remove bucket policy first
                aws s3api delete-bucket-policy --bucket "$bucket" 2>/dev/null
                
                # Delete the bucket
                if aws s3api delete-bucket --bucket "$bucket" --region "$REGION"; then
                    print_status "Bucket $bucket deleted"
                else
                    print_error "Failed to delete bucket $bucket"
                fi
            fi
        else
            print_warning "Bucket $bucket is not empty. Skipping..."
        fi
    done
else
    print_info "No orphaned S3 buckets found"
fi

# 4. Check for orphaned EBS snapshots
print_status "Checking for orphaned EBS snapshots..."
snapshots=$(aws ec2 describe-snapshots --owner-ids self --region "$REGION" \
    --query "Snapshots[?contains(Description, 'makedealcrm') || contains(Tags[?Key=='Name'].Value | [0], 'makedealcrm')].SnapshotId" \
    --output text)

if [ -n "$snapshots" ]; then
    print_warning "Found EBS snapshots related to makedealcrm:"
    for snapshot in $snapshots; do
        echo "  - $snapshot"
    done
    print_info "These snapshots may be backups. Review and delete manually if needed."
else
    print_info "No orphaned EBS snapshots found"
fi

# 5. Check for orphaned security groups
print_status "Checking for orphaned security groups..."
sgs=$(aws ec2 describe-security-groups --region "$REGION" \
    --query "SecurityGroups[?contains(GroupName, 'makedealcrm') && GroupName != 'default'].GroupId" \
    --output text)

if [ -n "$sgs" ]; then
    print_warning "Found security groups with 'makedealcrm' in name:"
    for sg in $sgs; do
        sg_name=$(aws ec2 describe-security-groups --group-ids "$sg" --region "$REGION" --query "SecurityGroups[0].GroupName" --output text)
        echo "  - $sg ($sg_name)"
    done
    print_info "These will be deleted when the CloudFormation stack is fully removed"
else
    print_info "No orphaned security groups found"
fi

# 6. List any remaining resources
print_status "Checking for any remaining resources with 'makedealcrm' tag..."
aws resourcegroupstaggingapi get-resources \
    --region "$REGION" \
    --tag-filters "Key=Project,Values=MakeDealCRM" \
    --query "ResourceTagMappingList[*].[ResourceARN, Tags[?Key=='Name'].Value | [0]]" \
    --output table 2>/dev/null

print_status "Cleanup process completed!"
print_info "Note: Some resources may take a few minutes to fully delete."
print_info "Check the AWS Console to verify all resources have been removed."

# Clean up local deployment directory
if [ -d "/Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/aws-deploy/deployments/deploy_68859f5cd9e15" ]; then
    print_status "Cleaning up local deployment directory..."
    rm -rf "/Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/aws-deploy/deployments/deploy_68859f5cd9e15"
fi