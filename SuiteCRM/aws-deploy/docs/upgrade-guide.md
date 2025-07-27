# MakeDealCRM AWS Upgrade Guide

## Overview

This guide provides instructions for upgrading your MakeDealCRM deployment on AWS, including scaling resources, updating the application, and migrating between deployment tiers.

## Table of Contents

1. [Pre-Upgrade Checklist](#pre-upgrade-checklist)
2. [Application Updates](#application-updates)
3. [Scaling Resources](#scaling-resources)
4. [Tier Migration](#tier-migration)
5. [Database Upgrades](#database-upgrades)
6. [Rollback Procedures](#rollback-procedures)
7. [Post-Upgrade Verification](#post-upgrade-verification)

## Pre-Upgrade Checklist

Before performing any upgrade:

- [ ] Create a full backup of your application and database
- [ ] Review the release notes for breaking changes
- [ ] Test the upgrade in a staging environment
- [ ] Schedule a maintenance window
- [ ] Notify users of planned downtime
- [ ] Ensure you have rollback procedures ready

## Application Updates

### Minor Updates (Bug Fixes & Security Patches)

1. **Connect to your EC2 instance:**
   ```bash
   ssh -i your-key.pem ec2-user@your-instance-ip
   ```

2. **Create a backup:**
   ```bash
   cd /opt/makedealcrm
   sudo ./aws-deploy/scripts/backup.sh
   ```

3. **Pull the latest updates:**
   ```bash
   cd /opt/makedealcrm
   sudo git fetch origin
   sudo git checkout tags/v1.x.x  # Replace with target version
   ```

4. **Update Docker images:**
   ```bash
   cd aws-deploy/docker
   sudo docker-compose pull
   sudo docker-compose up -d
   ```

5. **Run database migrations:**
   ```bash
   sudo docker-compose exec app php /var/www/html/aws-deploy/scripts/migrate-database.sh
   ```

### Major Updates

For major version updates that may include breaking changes:

1. **Create a staging environment:**
   ```bash
   aws cloudformation create-stack \
     --stack-name makedealcrm-staging \
     --template-body file://aws-deploy/templates/cloudformation-solo-tier.yaml \
     --parameters ParameterKey=InstanceName,ParameterValue=makedealcrm-staging
   ```

2. **Test the upgrade in staging first**

3. **If successful, apply to production using blue-green deployment**

## Scaling Resources

### Vertical Scaling (Increasing Instance Size)

1. **Stop the EC2 instance:**
   ```bash
   aws ec2 stop-instances --instance-ids i-1234567890abcdef0
   ```

2. **Modify instance type:**
   ```bash
   aws ec2 modify-instance-attribute \
     --instance-id i-1234567890abcdef0 \
     --instance-type "{\"Value\": \"t3.large\"}"
   ```

3. **Start the instance:**
   ```bash
   aws ec2 start-instances --instance-ids i-1234567890abcdef0
   ```

### Horizontal Scaling (Adding Load Balancing)

To scale beyond a single instance:

1. **Create an AMI from your current instance:**
   ```bash
   aws ec2 create-image \
     --instance-id i-1234567890abcdef0 \
     --name "makedealcrm-v1.0-$(date +%Y%m%d)"
   ```

2. **Deploy the multi-tier CloudFormation template:**
   ```bash
   aws cloudformation create-stack \
     --stack-name makedealcrm-multi-tier \
     --template-body file://aws-deploy/templates/cloudformation-multi-tier.yaml
   ```

## Tier Migration

### Solo Tier to Team Tier

When your organization grows beyond 5 users:

1. **Export current configuration:**
   ```bash
   ./aws-deploy/scripts/export-config.sh > current-config.json
   ```

2. **Deploy Team Tier infrastructure:**
   ```bash
   ./aws-deploy/scripts/deploy.sh \
     --tier team \
     --import-config current-config.json
   ```

3. **Migrate data:**
   ```bash
   ./aws-deploy/scripts/migrate-tier.sh \
     --from solo \
     --to team \
     --source-stack makedealcrm-production \
     --target-stack makedealcrm-team
   ```

### Team Tier to Enterprise Tier

For organizations requiring high availability and advanced features:

1. **Enable Multi-AZ RDS:**
   ```bash
   aws rds modify-db-instance \
     --db-instance-identifier makedealcrm-db \
     --multi-az \
     --apply-immediately
   ```

2. **Deploy enterprise infrastructure:**
   ```bash
   ./aws-deploy/scripts/deploy.sh \
     --tier enterprise \
     --enable-ha \
     --enable-read-replicas
   ```

## Database Upgrades

### MySQL Version Upgrade

1. **Create a snapshot:**
   ```bash
   aws rds create-db-snapshot \
     --db-instance-identifier makedealcrm-db \
     --db-snapshot-identifier makedealcrm-db-pre-upgrade-$(date +%Y%m%d)
   ```

2. **Perform the upgrade:**
   ```bash
   aws rds modify-db-instance \
     --db-instance-identifier makedealcrm-db \
     --engine-version 5.7.44 \
     --apply-immediately
   ```

3. **Monitor the upgrade:**
   ```bash
   aws rds describe-db-instances \
     --db-instance-identifier makedealcrm-db \
     --query 'DBInstances[0].DBInstanceStatus'
   ```

### Schema Migrations

Always run migrations in this order:

1. **Backup current database**
2. **Run pre-migration checks:**
   ```bash
   ./aws-deploy/scripts/pre-migration-check.sh
   ```

3. **Execute migrations:**
   ```bash
   ./aws-deploy/scripts/migrate-database.sh --version v1.x.x
   ```

4. **Verify migration success:**
   ```bash
   ./aws-deploy/scripts/post-migration-verify.sh
   ```

## Rollback Procedures

### Application Rollback

1. **Identify the previous version:**
   ```bash
   cd /opt/makedealcrm
   git tag -l | sort -V | tail -2 | head -1
   ```

2. **Checkout previous version:**
   ```bash
   git checkout tags/v1.x.x  # Previous version
   ```

3. **Rebuild containers:**
   ```bash
   cd aws-deploy/docker
   sudo docker-compose build
   sudo docker-compose up -d
   ```

### Database Rollback

1. **Restore from snapshot:**
   ```bash
   aws rds restore-db-instance-from-db-snapshot \
     --db-instance-identifier makedealcrm-db-restored \
     --db-snapshot-identifier makedealcrm-db-pre-upgrade-20240125
   ```

2. **Switch application to restored database:**
   - Update the database endpoint in your application configuration
   - Restart the application

### Full Stack Rollback

For complete infrastructure rollback:

```bash
# Restore CloudFormation stack to previous state
aws cloudformation update-stack \
  --stack-name makedealcrm-production \
  --use-previous-template \
  --parameters file://previous-parameters.json
```

## Post-Upgrade Verification

### Automated Health Checks

Run the comprehensive health check:

```bash
./aws-deploy/scripts/health-check.sh
```

### Manual Verification Checklist

- [ ] Application loads without errors
- [ ] Users can log in successfully
- [ ] Core features are functional:
  - [ ] Deal pipeline drag-and-drop
  - [ ] Checklist creation and management
  - [ ] Financial calculations
  - [ ] Email integration
  - [ ] File uploads
- [ ] Performance metrics are normal
- [ ] No critical errors in logs
- [ ] Backup systems are operational

### Performance Validation

Compare metrics before and after upgrade:

```bash
# Generate performance report
./aws-deploy/scripts/performance-report.sh \
  --compare-with pre-upgrade-baseline.json
```

## Upgrade Automation Scripts

### create-upgrade-plan.sh

```bash
#!/bin/bash
# Generate an upgrade plan based on current and target versions

CURRENT_VERSION=$(git describe --tags)
TARGET_VERSION=$1

echo "Generating upgrade plan from $CURRENT_VERSION to $TARGET_VERSION..."

# Check compatibility
./aws-deploy/scripts/check-compatibility.sh \
  --from $CURRENT_VERSION \
  --to $TARGET_VERSION

# Generate step-by-step plan
./aws-deploy/scripts/generate-upgrade-steps.sh \
  --from $CURRENT_VERSION \
  --to $TARGET_VERSION \
  > upgrade-plan-$(date +%Y%m%d).md
```

### automated-upgrade.sh

```bash
#!/bin/bash
# Automated upgrade with rollback capability

set -e

UPGRADE_PLAN=$1
ROLLBACK_POINT=$(date +%s)

# Create rollback point
./aws-deploy/scripts/create-rollback-point.sh --id $ROLLBACK_POINT

# Execute upgrade plan
while IFS= read -r step; do
    echo "Executing: $step"
    eval $step || {
        echo "Step failed! Initiating rollback..."
        ./aws-deploy/scripts/rollback.sh --to $ROLLBACK_POINT
        exit 1
    }
done < "$UPGRADE_PLAN"

echo "Upgrade completed successfully!"
```

## Best Practices

1. **Always test in staging first**
2. **Maintain detailed logs of all changes**
3. **Use version control for configuration files**
4. **Automate repetitive tasks**
5. **Monitor system metrics during and after upgrades**
6. **Have a communication plan for users**
7. **Document any custom modifications**

## Support

For upgrade assistance:
- Check the [MakeDealCRM Documentation](https://docs.makedealcrm.com)
- Contact support at support@makedealcrm.com
- Join our community forum at community.makedealcrm.com