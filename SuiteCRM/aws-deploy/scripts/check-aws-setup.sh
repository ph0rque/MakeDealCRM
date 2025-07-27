#!/bin/bash

# Quick AWS setup check

echo "AWS Setup Check"
echo "==============="

# Check AWS CLI
if command -v aws &> /dev/null; then
    echo "✓ AWS CLI is installed: $(aws --version)"
else
    echo "✗ AWS CLI is not installed"
    exit 1
fi

# Check credentials
if aws sts get-caller-identity &> /dev/null; then
    echo "✓ AWS credentials are configured"
    IDENTITY=$(aws sts get-caller-identity)
    echo "  Account: $(echo $IDENTITY | jq -r '.Account')"
    echo "  User: $(echo $IDENTITY | jq -r '.Arn' | cut -d'/' -f2)"
else
    echo "✗ AWS credentials are not configured"
    echo "  Run: aws configure"
    exit 1
fi

# Check default region
DEFAULT_REGION=$(aws configure get region)
if [ ! -z "$DEFAULT_REGION" ]; then
    echo "✓ Default region: $DEFAULT_REGION"
else
    echo "✗ No default region set"
fi

# Check for instances in default region
echo ""
echo "Checking for EC2 instances in $DEFAULT_REGION..."
INSTANCES=$(aws ec2 describe-instances --query 'Reservations[*].Instances[?State.Name==`running`].[InstanceId,PublicIpAddress,Tags[?Key==`Name`].Value|[0]]' --output table)
echo "$INSTANCES"

# Check specific IP
echo ""
echo "Searching for IP 35.169.23.176 in all regions..."
echo "(This may take a moment...)"

# Common regions to check first
COMMON_REGIONS="us-east-1 us-west-2 eu-west-1 ap-southeast-1"

for REGION in $COMMON_REGIONS; do
    RESULT=$(aws ec2 describe-instances --region $REGION --filters "Name=ip-address,Values=35.169.23.176" --query 'Reservations[0].Instances[0].InstanceId' --output text 2>/dev/null || echo "")
    if [ ! -z "$RESULT" ] && [ "$RESULT" != "None" ] && [ "$RESULT" != "null" ]; then
        echo "✓ Found instance $RESULT with IP 35.169.23.176 in region: $REGION"
        echo ""
        echo "To troubleshoot, run:"
        echo "export AWS_DEFAULT_REGION=$REGION"
        echo "./troubleshoot-deployment.sh $RESULT"
        exit 0
    fi
done

echo "✗ Could not find instance with IP 35.169.23.176 in common regions"
echo ""
echo "Run ./find-makedealcrm-instances.sh to search all regions"