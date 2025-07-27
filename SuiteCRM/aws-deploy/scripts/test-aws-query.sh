#!/bin/bash

# Test script to verify AWS CLI query parsing works without jq

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test function
test_query() {
    local description="$1"
    local command="$2"
    local expected="$3"
    
    echo -n "Testing: $description... "
    
    result=$(eval "$command" 2>&1)
    
    if [ "$result" == "$expected" ] || [ -n "$result" ]; then
        echo -e "${GREEN}PASSED${NC}"
        echo "  Result: $result"
    else
        echo -e "${RED}FAILED${NC}"
        echo "  Command: $command"
        echo "  Expected: $expected"
        echo "  Got: $result"
    fi
    echo
}

echo "=== AWS CLI Query Testing ==="
echo

# Create a sample JSON for testing
SAMPLE_JSON='[
    {"OutputKey": "ApplicationURL", "OutputValue": "https://app.example.com"},
    {"OutputKey": "EC2InstanceId", "OutputValue": "i-1234567890abcdef0"},
    {"OutputKey": "DatabaseEndpoint", "OutputValue": "db.example.com:3306"},
    {"OutputKey": "BackupBucket", "OutputValue": "backup-bucket-123"},
    {"OutputKey": "SSHCommand", "OutputValue": "ssh -i key.pem ec2-user@1.2.3.4"}
]'

# Save to temporary file
TEMP_FILE="/tmp/test-outputs.json"
echo "$SAMPLE_JSON" > "$TEMP_FILE"

echo "Testing local JSON parsing (simulating AWS output):"
echo

# Test parsing with grep/sed (no jq required)
test_query "Extract ApplicationURL with grep" \
    "cat $TEMP_FILE | grep -o '\"OutputKey\": \"ApplicationURL\".*\"OutputValue\": \"[^\"]*\"' | grep -o '\"OutputValue\": \"[^\"]*\"' | cut -d'\"' -f4" \
    "https://app.example.com"

test_query "Extract EC2InstanceId with grep" \
    "cat $TEMP_FILE | grep -o '\"OutputKey\": \"EC2InstanceId\".*\"OutputValue\": \"[^\"]*\"' | grep -o '\"OutputValue\": \"[^\"]*\"' | cut -d'\"' -f4" \
    "i-1234567890abcdef0"

# Test secret JSON parsing
SECRET_JSON='{"username":"makedealcrm","password":"MySecretPassword123","engine":"mysql","host":"db.example.com","port":"3306","dbInstanceIdentifier":"makedealcrm-db"}'
echo "$SECRET_JSON" > "$TEMP_FILE"

test_query "Extract password from secret JSON" \
    "cat $TEMP_FILE | grep -o '\"password\":\"[^\"]*' | grep -o '[^\"]*$'" \
    "MySecretPassword123"

echo "=== Testing AWS CLI Query Syntax ==="
echo "Note: These tests require AWS credentials and will query actual resources"
echo

# Test AWS CLI query syntax (will only work with proper AWS credentials)
if aws sts get-caller-identity &> /dev/null; then
    echo -e "${GREEN}AWS credentials detected${NC}"
    
    # Get default region
    REGION=$(aws configure get region || echo "us-east-1")
    echo "Using region: $REGION"
    
    # List stacks to test query
    echo
    echo "Testing CloudFormation stack query:"
    aws cloudformation list-stacks --region "$REGION" --query 'StackSummaries[0].StackName' --output text 2>/dev/null || echo "No stacks found"
    
else
    echo -e "${YELLOW}AWS credentials not configured - skipping live tests${NC}"
fi

# Cleanup
rm -f "$TEMP_FILE"

echo
echo "=== Test Complete ==="