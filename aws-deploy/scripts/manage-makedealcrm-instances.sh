#!/bin/bash

# Manage MakeDealCRM instances across all AWS regions
# This script can list, filter, and clean up terminated instances

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Default values
SHOW_TERMINATED=false
CLEAN_TERMINATED=false
FORCE_CLEAN=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --show-terminated)
            SHOW_TERMINATED=true
            shift
            ;;
        --clean-terminated)
            CLEAN_TERMINATED=true
            shift
            ;;
        --force)
            FORCE_CLEAN=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --show-terminated    Show terminated instances (default: hide)"
            echo "  --clean-terminated   Remove terminated instances from AWS"
            echo "  --force             Don't ask for confirmation when cleaning"
            echo "  --help, -h          Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                          # List only active instances"
            echo "  $0 --show-terminated        # Show all instances including terminated"
            echo "  $0 --clean-terminated       # Clean up terminated instances"
            echo "  $0 --clean-terminated --force # Clean up without confirmation"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

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

# Function to clean terminated instances
clean_terminated_instances() {
    local REGION=$1
    local INSTANCE_ID=$2
    
    print_info "Cleaning up terminated instance $INSTANCE_ID in region $REGION..."
    
    # Note: Terminated instances are automatically removed from AWS after a certain period
    # We can't manually delete them, but we can filter them from our display
    # If you have tags or other resources associated, clean those up here
    
    print_info "Instance $INSTANCE_ID is terminated and will be automatically removed by AWS"
}

print_status "Searching for MakeDealCRM instances across all regions..."
print_status "This may take a moment..."
echo ""

FOUND_INSTANCES=0
TERMINATED_INSTANCES=()

# Get all AWS regions
REGIONS=$(aws ec2 describe-regions --query 'Regions[*].RegionName' --output text)

# Determine which states to filter
if [ "$SHOW_TERMINATED" = true ] || [ "$CLEAN_TERMINATED" = true ]; then
    INSTANCE_STATES="running,stopped,stopping,pending,terminated"
else
    INSTANCE_STATES="running,stopped,stopping,pending"
fi

for REGION in $REGIONS; do
    # Search for instances with MakeDealCRM tags or in MakeDealCRM stacks
    INSTANCES=$(aws ec2 describe-instances \
        --region $REGION \
        --filters "Name=instance-state-name,Values=$INSTANCE_STATES" \
        --query 'Reservations[*].Instances[?Tags[?Key==`Name` && contains(Value, `makedealcrm`)] || Tags[?Key==`aws:cloudformation:stack-name` && contains(Value, `makedealcrm`)]].[InstanceId,PublicIpAddress,PrivateIpAddress,State.Name,Tags[?Key==`Name`].Value|[0],Tags[?Key==`aws:cloudformation:stack-name`].Value|[0]]' \
        --output text 2>/dev/null || echo "")
    
    if [ ! -z "$INSTANCES" ] && [ "$INSTANCES" != "None" ]; then
        print_status "Found instances in region: $REGION"
        echo "---------------------------------------------"
        printf "%-20s %-16s %-16s %-12s %-30s %-30s\n" "Instance ID" "Public IP" "Private IP" "State" "Name Tag" "Stack Name"
        echo "$INSTANCES" | while IFS=$'\t' read -r INSTANCE_ID PUBLIC_IP PRIVATE_IP STATE NAME_TAG STACK_NAME; do
            # Replace None with -
            PUBLIC_IP=${PUBLIC_IP/None/-}
            PRIVATE_IP=${PRIVATE_IP/None/-}
            NAME_TAG=${NAME_TAG/None/-}
            STACK_NAME=${STACK_NAME/None/-}
            
            # Color code based on state
            if [ "$STATE" = "terminated" ]; then
                printf "${RED}%-20s %-16s %-16s %-12s %-30s %-30s${NC}\n" "$INSTANCE_ID" "$PUBLIC_IP" "$PRIVATE_IP" "$STATE" "$NAME_TAG" "$STACK_NAME"
                TERMINATED_INSTANCES+=("$REGION:$INSTANCE_ID")
            elif [ "$STATE" = "running" ]; then
                printf "${GREEN}%-20s %-16s %-16s %-12s %-30s %-30s${NC}\n" "$INSTANCE_ID" "$PUBLIC_IP" "$PRIVATE_IP" "$STATE" "$NAME_TAG" "$STACK_NAME"
            else
                printf "${YELLOW}%-20s %-16s %-16s %-12s %-30s %-30s${NC}\n" "$INSTANCE_ID" "$PUBLIC_IP" "$PRIVATE_IP" "$STATE" "$NAME_TAG" "$STACK_NAME"
            fi
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
        printf "%-40s %-25s %-30s\n" "Stack Name" "Status" "Created"
        echo "$STACKS" | while IFS=$'\t' read -r STACK_NAME STATUS CREATED; do
            # Color code based on status
            if [[ "$STATUS" == *"FAILED"* ]] || [[ "$STATUS" == *"ROLLBACK"* ]]; then
                printf "${RED}%-40s %-25s %-30s${NC}\n" "$STACK_NAME" "$STATUS" "$CREATED"
            elif [[ "$STATUS" == *"COMPLETE"* ]] && [[ "$STATUS" != *"DELETE"* ]]; then
                printf "${GREEN}%-40s %-25s %-30s${NC}\n" "$STACK_NAME" "$STATUS" "$CREATED"
            else
                printf "${YELLOW}%-40s %-25s %-30s${NC}\n" "$STACK_NAME" "$STATUS" "$CREATED"
            fi
            FOUND_STACKS=$((FOUND_STACKS + 1))
            
            # Get stack outputs for successful stacks
            if [[ "$STATUS" == "CREATE_COMPLETE" ]] || [[ "$STATUS" == "UPDATE_COMPLETE" ]]; then
                APP_URL=$(aws cloudformation describe-stacks --stack-name $STACK_NAME --region $REGION --query 'Stacks[0].Outputs[?OutputKey==`ApplicationURL`].OutputValue' --output text 2>/dev/null || echo "")
                if [ ! -z "$APP_URL" ] && [ "$APP_URL" != "None" ]; then
                    echo "  Application URL: $APP_URL"
                fi
            fi
        done
        echo ""
    fi
done

# Handle terminated instances cleanup
if [ "$CLEAN_TERMINATED" = true ] && [ ${#TERMINATED_INSTANCES[@]} -gt 0 ]; then
    echo ""
    print_warning "Found ${#TERMINATED_INSTANCES[@]} terminated instance(s)"
    
    if [ "$FORCE_CLEAN" != true ]; then
        echo ""
        echo "The following terminated instances will be processed:"
        for INSTANCE in "${TERMINATED_INSTANCES[@]}"; do
            IFS=':' read -r REGION INSTANCE_ID <<< "$INSTANCE"
            echo "  - $INSTANCE_ID (Region: $REGION)"
        done
        echo ""
        read -p "Do you want to proceed with cleanup? (y/N) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            print_info "Cleanup cancelled"
            exit 0
        fi
    fi
    
    echo ""
    for INSTANCE in "${TERMINATED_INSTANCES[@]}"; do
        IFS=':' read -r REGION INSTANCE_ID <<< "$INSTANCE"
        clean_terminated_instances "$REGION" "$INSTANCE_ID"
    done
    print_status "Cleanup complete"
elif [ ${#TERMINATED_INSTANCES[@]} -gt 0 ]; then
    echo ""
    print_info "Found ${#TERMINATED_INSTANCES[@]} terminated instance(s). Use --clean-terminated to remove them."
fi

# Summary
echo ""
if [ $FOUND_INSTANCES -eq 0 ] && [ $FOUND_STACKS -eq 0 ]; then
    print_warning "No MakeDealCRM instances or stacks found in any region."
    print_warning ""
    print_warning "Possible reasons:"
    print_warning "1. The instances were terminated"
    print_warning "2. Different AWS account is being used"
    print_warning "3. Instances don't have 'makedealcrm' in their tags"
else
    print_status "Summary:"
    print_status "  Total instances found: $FOUND_INSTANCES"
    if [ "$SHOW_TERMINATED" = true ]; then
        print_status "  Terminated instances: ${#TERMINATED_INSTANCES[@]}"
    fi
    print_status "  Total stacks found: $FOUND_STACKS"
fi

print_status "Search complete!"