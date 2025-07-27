#!/bin/bash

# MakeDealCRM AWS Deployment Troubleshooting Script
# This script helps diagnose issues with AWS deployments

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

# Get instance information
check_instance() {
    print_status "Checking EC2 instance status..."
    
    if [ -z "$1" ]; then
        print_error "Please provide instance ID or IP address as argument"
        echo "Usage: $0 <instance-id-or-ip>"
        exit 1
    fi
    
    INSTANCE_IDENTIFIER=$1
    
    # Check if it's an instance ID or IP
    if [[ $INSTANCE_IDENTIFIER == i-* ]]; then
        INSTANCE_ID=$INSTANCE_IDENTIFIER
        # Get instance IP
        INSTANCE_IP=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --query 'Reservations[0].Instances[0].PublicIpAddress' --output text 2>/dev/null || echo "")
    else
        INSTANCE_IP=$INSTANCE_IDENTIFIER
        # Try to find instance ID from IP
        INSTANCE_ID=$(aws ec2 describe-instances --filters "Name=ip-address,Values=$INSTANCE_IP" --query 'Reservations[0].Instances[0].InstanceId' --output text 2>/dev/null || echo "")
    fi
    
    if [ -z "$INSTANCE_ID" ] || [ "$INSTANCE_ID" == "None" ]; then
        print_error "Could not find instance with identifier: $INSTANCE_IDENTIFIER"
        exit 1
    fi
    
    print_status "Instance ID: $INSTANCE_ID"
    print_status "Instance IP: $INSTANCE_IP"
    
    # Get instance state
    INSTANCE_STATE=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --query 'Reservations[0].Instances[0].State.Name' --output text)
    print_status "Instance State: $INSTANCE_STATE"
    
    if [ "$INSTANCE_STATE" != "running" ]; then
        print_error "Instance is not running. Current state: $INSTANCE_STATE"
        exit 1
    fi
}

# Check security groups
check_security_groups() {
    print_status "Checking security group rules..."
    
    # Get security group IDs
    SG_IDS=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --query 'Reservations[0].Instances[0].SecurityGroups[*].GroupId' --output text)
    
    for SG_ID in $SG_IDS; do
        print_status "Security Group: $SG_ID"
        
        # Check for HTTP (80) rule
        HTTP_RULE=$(aws ec2 describe-security-groups --group-ids $SG_ID --query 'SecurityGroups[0].IpPermissions[?FromPort==`80`]' --output json)
        if [ "$HTTP_RULE" != "[]" ]; then
            print_status "  ✓ HTTP (80) is open"
        else
            print_warning "  ✗ HTTP (80) is not open"
        fi
        
        # Check for HTTPS (443) rule
        HTTPS_RULE=$(aws ec2 describe-security-groups --group-ids $SG_ID --query 'SecurityGroups[0].IpPermissions[?FromPort==`443`]' --output json)
        if [ "$HTTPS_RULE" != "[]" ]; then
            print_status "  ✓ HTTPS (443) is open"
        else
            print_warning "  ✗ HTTPS (443) is not open"
        fi
        
        # Check for SSH (22) rule
        SSH_RULE=$(aws ec2 describe-security-groups --group-ids $SG_ID --query 'SecurityGroups[0].IpPermissions[?FromPort==`22`]' --output json)
        if [ "$SSH_RULE" != "[]" ]; then
            print_status "  ✓ SSH (22) is open"
        else
            print_warning "  ✗ SSH (22) is not open"
        fi
    done
}

# Check application health
check_application() {
    print_status "Checking application health..."
    
    # Try HTTP first
    print_status "Testing HTTP connection to http://$INSTANCE_IP ..."
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -m 10 http://$INSTANCE_IP || echo "000")
    
    if [ "$HTTP_STATUS" == "200" ] || [ "$HTTP_STATUS" == "301" ] || [ "$HTTP_STATUS" == "302" ]; then
        print_status "✓ HTTP connection successful (Status: $HTTP_STATUS)"
    else
        print_error "✗ HTTP connection failed (Status: $HTTP_STATUS)"
    fi
    
    # Try HTTPS
    print_status "Testing HTTPS connection to https://$INSTANCE_IP ..."
    HTTPS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -m 10 -k https://$INSTANCE_IP || echo "000")
    
    if [ "$HTTPS_STATUS" == "200" ] || [ "$HTTPS_STATUS" == "301" ] || [ "$HTTPS_STATUS" == "302" ]; then
        print_status "✓ HTTPS connection successful (Status: $HTTPS_STATUS)"
    else
        print_warning "✗ HTTPS connection failed (Status: $HTTPS_STATUS)"
    fi
}

# Get CloudFormation stack info
check_cloudformation() {
    print_status "Checking CloudFormation stack..."
    
    # Find stack by instance ID
    STACK_NAME=$(aws cloudformation describe-stacks --query "Stacks[?contains(Outputs[?OutputKey=='EC2InstanceId'].OutputValue | [0], '$INSTANCE_ID')].StackName | [0]" --output text 2>/dev/null || echo "")
    
    if [ -z "$STACK_NAME" ] || [ "$STACK_NAME" == "None" ]; then
        print_warning "Could not find associated CloudFormation stack"
    else
        print_status "Stack Name: $STACK_NAME"
        
        # Get stack outputs
        APP_URL=$(aws cloudformation describe-stacks --stack-name $STACK_NAME --query 'Stacks[0].Outputs[?OutputKey==`ApplicationURL`].OutputValue' --output text)
        print_status "Application URL from stack: $APP_URL"
    fi
}

# Provide SSH command
provide_ssh_command() {
    print_status ""
    print_status "To SSH into the instance and check logs:"
    print_status "ssh -i <your-key.pem> ec2-user@$INSTANCE_IP"
    print_status ""
    print_status "Once connected, check:"
    print_status "  - Docker status: sudo systemctl status docker"
    print_status "  - Running containers: sudo docker ps"
    print_status "  - Docker logs: sudo docker logs makedealcrm-app"
    print_status "  - Deployment log: sudo cat /var/log/makedealcrm-deploy.log"
}

# Main execution
main() {
    print_status "MakeDealCRM AWS Deployment Troubleshooting"
    print_status "=========================================="
    
    check_instance "$1"
    check_security_groups
    check_application
    check_cloudformation
    provide_ssh_command
    
    print_status ""
    print_status "Troubleshooting complete!"
}

# Run main function
main "$@"