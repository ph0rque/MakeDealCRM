#!/bin/bash

# Check CloudFormation stack state and handle ROLLBACK_COMPLETE
# Usage: ./check-stack-state.sh <stack-name>

STACK_NAME=$1

if [ -z "$STACK_NAME" ]; then
    echo "Error: Stack name is required"
    echo "Usage: $0 <stack-name>"
    exit 1
fi

# Get stack status
STACK_STATUS=$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --query 'Stacks[0].StackStatus' --output text 2>/dev/null)

if [ $? -ne 0 ]; then
    echo "Stack does not exist or error checking status"
    exit 0
fi

echo "Stack Status: $STACK_STATUS"

# Check if stack is in a failed state that requires deletion
case "$STACK_STATUS" in
    "ROLLBACK_COMPLETE"|"UPDATE_ROLLBACK_COMPLETE"|"DELETE_FAILED")
        echo "Stack is in $STACK_STATUS state and must be deleted before redeployment"
        echo "Run: aws cloudformation delete-stack --stack-name $STACK_NAME"
        exit 1
        ;;
    "CREATE_IN_PROGRESS"|"UPDATE_IN_PROGRESS"|"DELETE_IN_PROGRESS"|"ROLLBACK_IN_PROGRESS")
        echo "Stack operation in progress. Please wait for completion."
        exit 2
        ;;
    "CREATE_COMPLETE"|"UPDATE_COMPLETE")
        echo "Stack is in a healthy state"
        exit 0
        ;;
    *)
        echo "Stack is in state: $STACK_STATUS"
        exit 3
        ;;
esac