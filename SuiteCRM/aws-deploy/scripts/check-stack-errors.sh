#!/bin/bash

# Script to check CloudFormation stack errors
STACK_NAME="${1:-makedealcrm-production}"
REGION="${AWS_REGION:-us-east-1}"

echo "Checking CloudFormation stack errors for: $STACK_NAME"
echo "================================================"

# Get stack events to see what failed
echo -e "\nStack Events (showing errors):"
aws cloudformation describe-stack-events \
    --stack-name $STACK_NAME \
    --region $REGION \
    --query "StackEvents[?ResourceStatus=='CREATE_FAILED' || ResourceStatus=='UPDATE_FAILED' || ResourceStatus=='DELETE_FAILED'].[Timestamp,ResourceType,LogicalResourceId,ResourceStatusReason]" \
    --output table

echo -e "\nFull Stack Status:"
aws cloudformation describe-stacks \
    --stack-name $STACK_NAME \
    --region $REGION \
    --query "Stacks[0].[StackStatus,StackStatusReason]" \
    --output table

echo -e "\nMost Recent Events (last 10):"
aws cloudformation describe-stack-events \
    --stack-name $STACK_NAME \
    --region $REGION \
    --query "StackEvents[0:10].[Timestamp,ResourceStatus,ResourceType,LogicalResourceId,ResourceStatusReason]" \
    --output table