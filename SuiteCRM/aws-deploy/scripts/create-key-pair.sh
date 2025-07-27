#!/bin/bash

# Script to create EC2 key pair for MakeDealCRM deployment
KEY_NAME="${1:-makedealcrm-keypair}"
REGION="${AWS_REGION:-us-east-1}"
OUTPUT_DIR="${2:-.}"

echo "Creating EC2 key pair: $KEY_NAME"

# Check if key pair already exists
if aws ec2 describe-key-pairs --key-names $KEY_NAME --region $REGION &>/dev/null; then
    echo "Key pair $KEY_NAME already exists!"
    echo "To use a different name, run: $0 <new-key-name>"
    exit 1
fi

# Create the key pair and save the private key
aws ec2 create-key-pair \
    --key-name $KEY_NAME \
    --region $REGION \
    --query 'KeyMaterial' \
    --output text > "$OUTPUT_DIR/$KEY_NAME.pem"

if [ $? -eq 0 ]; then
    chmod 600 "$OUTPUT_DIR/$KEY_NAME.pem"
    echo "Key pair created successfully!"
    echo "Private key saved to: $OUTPUT_DIR/$KEY_NAME.pem"
    echo ""
    echo "IMPORTANT: Keep this private key file safe! You'll need it to SSH into your EC2 instance."
    echo ""
    echo "To use this key pair in deployment, set:"
    echo "export KEY_PAIR_NAME=$KEY_NAME"
else
    echo "Failed to create key pair"
    exit 1
fi