#!/bin/bash

# Test deployment script for debugging
echo "[$(date)] Starting test deployment..."
echo "[$(date)] Environment variables:"
env | grep -E "(AWS_|INSTANCE_|STACK_|DOMAIN_)" | sed 's/AWS_SECRET_ACCESS_KEY=.*/AWS_SECRET_ACCESS_KEY=***/'

echo "[$(date)] Checking AWS credentials..."
if aws sts get-caller-identity; then
    echo "[$(date)] AWS credentials are valid"
else
    echo "[$(date)] ERROR: AWS credentials are invalid"
    exit 1
fi

echo "[$(date)] Test deployment completed successfully"

# Create a simple deployment info file
# Save to the deployment directory if DEPLOYMENT_DIR is set
OUTPUT_DIR="${DEPLOYMENT_DIR:-.}"

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

cat > "$OUTPUT_DIR/deployment-info.json" <<EOF
{
    "deployedAt": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "region": "${AWS_REGION:-us-east-1}",
    "instanceId": "i-test123456",
    "applicationUrl": "http://test.example.com",
    "status": "success"
}
EOF

# Create admin credentials file
echo "Admin Username: admin" > "$OUTPUT_DIR/admin-credentials.txt"
echo "Password: TestPassword123!" >> "$OUTPUT_DIR/admin-credentials.txt"

echo "[$(date)] Deployment info and credentials saved"