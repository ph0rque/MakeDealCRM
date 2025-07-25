#!/bin/bash

# MakeDealCRM AWS Deployment Script
# This script automates the deployment of MakeDealCRM to AWS

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
STACK_NAME="makedealcrm-${ENVIRONMENT:-production}"
TEMPLATE_FILE="../templates/cloudformation-solo-tier.yaml"
REGION=${AWS_REGION:-us-east-1}

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

# Check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."
    
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
    
    # Check jq
    if ! command -v jq &> /dev/null; then
        print_warning "jq is not installed. Some features may not work properly."
    fi
    
    print_status "Prerequisites check passed!"
}

# Validate parameters
validate_parameters() {
    print_status "Validating deployment parameters..."
    
    if [ -z "$INSTANCE_TYPE" ]; then
        INSTANCE_TYPE="t3.small"
        print_warning "INSTANCE_TYPE not set, using default: $INSTANCE_TYPE"
    fi
    
    if [ -z "$KEY_PAIR_NAME" ]; then
        print_error "KEY_PAIR_NAME is required. Please set it as an environment variable."
        exit 1
    fi
    
    if [ -z "$ADMIN_EMAIL" ]; then
        print_error "ADMIN_EMAIL is required. Please set it as an environment variable."
        exit 1
    fi
    
    print_status "Parameters validated!"
}

# Create or update CloudFormation stack
deploy_stack() {
    print_status "Deploying CloudFormation stack: $STACK_NAME"
    
    # Check if stack exists
    if aws cloudformation describe-stacks --stack-name $STACK_NAME --region $REGION &> /dev/null; then
        ACTION="update-stack"
        print_status "Stack exists, updating..."
    else
        ACTION="create-stack"
        print_status "Creating new stack..."
    fi
    
    # Deploy stack
    aws cloudformation $ACTION \
        --stack-name $STACK_NAME \
        --template-body file://$TEMPLATE_FILE \
        --parameters \
            ParameterKey=InstanceType,ParameterValue=$INSTANCE_TYPE \
            ParameterKey=KeyPairName,ParameterValue=$KEY_PAIR_NAME \
            ParameterKey=DomainName,ParameterValue="${DOMAIN_NAME:-}" \
            ParameterKey=AdminEmail,ParameterValue=$ADMIN_EMAIL \
            ParameterKey=BackupRetentionDays,ParameterValue=${BACKUP_RETENTION:-7} \
            ParameterKey=EnableHighAvailability,ParameterValue=${ENABLE_HA:-false} \
        --capabilities CAPABILITY_IAM \
        --region $REGION
    
    print_status "Waiting for stack operation to complete..."
    
    # Wait for stack to complete
    if [ "$ACTION" == "create-stack" ]; then
        aws cloudformation wait stack-create-complete --stack-name $STACK_NAME --region $REGION
    else
        aws cloudformation wait stack-update-complete --stack-name $STACK_NAME --region $REGION || true
    fi
    
    # Check stack status
    STACK_STATUS=$(aws cloudformation describe-stacks --stack-name $STACK_NAME --region $REGION --query 'Stacks[0].StackStatus' --output text)
    
    if [[ "$STACK_STATUS" == *"COMPLETE" ]]; then
        print_status "Stack deployment completed successfully!"
    else
        print_error "Stack deployment failed with status: $STACK_STATUS"
        exit 1
    fi
}

# Get stack outputs
get_outputs() {
    print_status "Retrieving deployment information..."
    
    # Get outputs
    OUTPUTS=$(aws cloudformation describe-stacks --stack-name $STACK_NAME --region $REGION --query 'Stacks[0].Outputs')
    
    # Parse outputs
    APP_URL=$(echo $OUTPUTS | jq -r '.[] | select(.OutputKey=="ApplicationURL") | .OutputValue')
    INSTANCE_ID=$(echo $OUTPUTS | jq -r '.[] | select(.OutputKey=="EC2InstanceId") | .OutputValue')
    DB_ENDPOINT=$(echo $OUTPUTS | jq -r '.[] | select(.OutputKey=="DatabaseEndpoint") | .OutputValue')
    BACKUP_BUCKET=$(echo $OUTPUTS | jq -r '.[] | select(.OutputKey=="BackupBucket") | .OutputValue')
    SSH_COMMAND=$(echo $OUTPUTS | jq -r '.[] | select(.OutputKey=="SSHCommand") | .OutputValue')
    
    # Save outputs to file
    cat > deployment-info.json <<EOF
{
    "stackName": "$STACK_NAME",
    "region": "$REGION",
    "applicationUrl": "$APP_URL",
    "instanceId": "$INSTANCE_ID",
    "databaseEndpoint": "$DB_ENDPOINT",
    "backupBucket": "$BACKUP_BUCKET",
    "sshCommand": "$SSH_COMMAND",
    "deployedAt": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
}
EOF
    
    print_status "Deployment information saved to deployment-info.json"
}

# Wait for application to be ready
wait_for_app() {
    print_status "Waiting for application to be ready..."
    
    # Get instance public IP
    INSTANCE_IP=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --region $REGION --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)
    
    # Wait for application to respond
    MAX_RETRIES=30
    RETRY_COUNT=0
    
    while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
        if curl -s -o /dev/null -w "%{http_code}" http://$INSTANCE_IP | grep -q "200\|301\|302"; then
            print_status "Application is ready!"
            break
        fi
        
        RETRY_COUNT=$((RETRY_COUNT + 1))
        print_status "Waiting for application... ($RETRY_COUNT/$MAX_RETRIES)"
        sleep 30
    done
    
    if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
        print_warning "Application may not be fully ready. Please check manually."
    fi
}

# Generate admin password
generate_admin_password() {
    print_status "Generating admin credentials..."
    
    # Generate secure password
    ADMIN_PASSWORD=$(openssl rand -base64 12)
    
    # Save credentials
    cat > admin-credentials.txt <<EOF
MakeDealCRM Admin Credentials
=============================
URL: $APP_URL
Username: admin
Password: $ADMIN_PASSWORD

Please change this password after first login!
EOF
    
    chmod 600 admin-credentials.txt
    print_status "Admin credentials saved to admin-credentials.txt (keep this secure!)"
}

# Print summary
print_summary() {
    echo ""
    echo "======================================"
    echo "MakeDealCRM Deployment Complete!"
    echo "======================================"
    echo ""
    echo "Application URL: $APP_URL"
    echo "Instance ID: $INSTANCE_ID"
    echo "Database Endpoint: $DB_ENDPOINT"
    echo "Backup Bucket: $BACKUP_BUCKET"
    echo ""
    echo "SSH Access: $SSH_COMMAND"
    echo ""
    echo "Admin credentials are in: admin-credentials.txt"
    echo ""
    echo "Next steps:"
    echo "1. Access your MakeDealCRM instance at $APP_URL"
    echo "2. Log in with the admin credentials"
    echo "3. Configure email settings"
    echo "4. Create additional users"
    echo "5. Import your data"
    echo ""
    print_warning "Remember to configure your domain DNS if using a custom domain!"
}

# Main execution
main() {
    print_status "Starting MakeDealCRM AWS deployment..."
    
    check_prerequisites
    validate_parameters
    deploy_stack
    get_outputs
    wait_for_app
    generate_admin_password
    print_summary
    
    print_status "Deployment completed successfully!"
}

# Run main function
main "$@"