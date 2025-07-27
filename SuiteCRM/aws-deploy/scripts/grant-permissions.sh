#!/bin/bash

# Script to grant necessary permissions to IAM user
USER_NAME="andrew_gauntletai"
POLICY_NAME="MakeDealCRMDeploymentPolicy"

echo "Creating IAM policy for MakeDealCRM deployment..."

# Create the policy
aws iam create-policy \
  --policy-name $POLICY_NAME \
  --policy-document file://../docs/required-iam-policy.json \
  --description "Permissions required for MakeDealCRM AWS deployment"

# Get the policy ARN
POLICY_ARN=$(aws iam list-policies --query "Policies[?PolicyName=='$POLICY_NAME'].Arn" --output text)

if [ -z "$POLICY_ARN" ]; then
  echo "Error: Failed to create or find policy"
  exit 1
fi

echo "Attaching policy to user $USER_NAME..."

# Attach the policy to the user
aws iam attach-user-policy \
  --user-name $USER_NAME \
  --policy-arn $POLICY_ARN

echo "Permissions granted successfully!"
echo "Policy ARN: $POLICY_ARN"