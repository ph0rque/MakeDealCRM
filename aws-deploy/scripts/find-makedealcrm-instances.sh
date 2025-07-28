#!/bin/bash

# Find all MakeDealCRM instances across all AWS regions

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

# Check AWS CLI
if ! command -v aws &> /dev/null; then
    print_error "AWS CLI is not installed. Please install it first."
    exit 1
fi

# Check AWS credentials
if ! aws sts get-caller-identity &> /dev/null; then
    print_error "AWS credentials are not configured. Please run 'aws configure' first."
    exit 1
fi

print_status "Searching for MakeDealCRM instances across all regions..."
print_status "This may take a moment..."
echo ""

FOUND_INSTANCES=0

# Get all AWS regions
REGIONS=$(aws ec2 describe-regions --query 'Regions[*].RegionName' --output text)

for REGION in $REGIONS; do
    # Search for instances with MakeDealCRM tags or in MakeDealCRM stacks (excluding terminated)
    INSTANCES=$(aws ec2 describe-instances \
        --region $REGION \
        --filters "Name=instance-state-name,Values=running,stopped,stopping,pending" \
        --query 'Reservations[*].Instances[?Tags[?Key==`Name` && contains(Value, `makedealcrm`)] || Tags[?Key==`aws:cloudformation:stack-name` && contains(Value, `makedealcrm`)]].[InstanceId,PublicIpAddress,PrivateIpAddress,State.Name,Tags[?Key==`Name`].Value|[0],Tags[?Key==`aws:cloudformation:stack-name`].Value|[0]]' \
        --output text 2>/dev/null || echo "")
    
    if [ ! -z "$INSTANCES" ] && [ "$INSTANCES" != "None" ]; then
        print_status "Found instances in region: $REGION"
        echo "---------------------------------------------"
        printf "%-20s %-16s %-16s %-10s %-30s %-30s\n" "Instance ID" "Public IP" "Private IP" "State" "Name Tag" "Stack Name"
        echo "$INSTANCES" | while IFS=$'\t' read -r INSTANCE_ID PUBLIC_IP PRIVATE_IP STATE NAME_TAG STACK_NAME; do
            # Replace None with -
            PUBLIC_IP=${PUBLIC_IP/None/-}
            PRIVATE_IP=${PRIVATE_IP/None/-}
            NAME_TAG=${NAME_TAG/None/-}
            STACK_NAME=${STACK_NAME/None/-}
            
            printf "%-20s %-16s %-16s %-10s %-30s %-30s\n" "$INSTANCE_ID" "$PUBLIC_IP" "$PRIVATE_IP" "$STATE" "$NAME_TAG" "$STACK_NAME"
            FOUND_INSTANCES=$((FOUND_INSTANCES + 1))
        done
        echo ""
    fi
done

# Also search for CloudFormation stacks
print_status "Searching for MakeDealCRM CloudFormation stacks..."
echo ""

FOUND_STACKS=0

for REGION in $REGIONS; do
    STACKS=$(aws cloudformation describe-stacks \
        --region $REGION \
        --query 'Stacks[?contains(StackName, `makedealcrm`)].[StackName,StackStatus,CreationTime]' \
        --output text 2>/dev/null || echo "")
    
    if [ ! -z "$STACKS" ] && [ "$STACKS" != "None" ]; then
        print_status "Found stacks in region: $REGION"
        echo "---------------------------------------------"
        printf "%-40s %-20s %-30s\n" "Stack Name" "Status" "Created"
        echo "$STACKS" | while IFS=$'\t' read -r STACK_NAME STATUS CREATED; do
            printf "%-40s %-20s %-30s\n" "$STACK_NAME" "$STATUS" "$CREATED"
            FOUND_STACKS=$((FOUND_STACKS + 1))
            
            # Get stack outputs
            APP_URL=$(aws cloudformation describe-stacks --stack-name $STACK_NAME --region $REGION --query 'Stacks[0].Outputs[?OutputKey==`ApplicationURL`].OutputValue' --output text 2>/dev/null || echo "")
            if [ ! -z "$APP_URL" ] && [ "$APP_URL" != "None" ]; then
                echo "  Application URL: $APP_URL"
            fi
        done
        echo ""
    fi
done

# Search by the specific IP
print_status "Searching for instance with IP 35.169.23.176..."
for REGION in $REGIONS; do
    INSTANCE=$(aws ec2 describe-instances \
        --region $REGION \
        --filters "Name=ip-address,Values=35.169.23.176" \
        --query 'Reservations[*].Instances[*].[InstanceId,State.Name,Tags[?Key==`Name`].Value|[0]]' \
        --output text 2>/dev/null || echo "")
    
    if [ ! -z "$INSTANCE" ] && [ "$INSTANCE" != "None" ]; then
        print_status "Found instance with IP 35.169.23.176 in region: $REGION"
        echo "$INSTANCE"
        echo ""
        break
    fi
done

if [ $FOUND_INSTANCES -eq 0 ] && [ $FOUND_STACKS -eq 0 ]; then
    print_warning "No MakeDealCRM instances or stacks found in any region."
    print_warning ""
    print_warning "Possible reasons:"
    print_warning "1. The instances were terminated"
    print_warning "2. Different AWS account is being used"
    print_warning "3. Instances don't have 'makedealcrm' in their tags"
    print_warning ""
    print_status "Try searching for all running instances:"
    print_status "aws ec2 describe-instances --query 'Reservations[*].Instances[?State.Name==\`running\`].[InstanceId,PublicIpAddress,Tags[?Key==\`Name\`].Value|[0]]' --output table"
fi

print_status "Search complete!"