# MakeDealCRM AWS One-Click Deployment System

## Overview

The MakeDealCRM AWS Deployment System provides a streamlined, automated way to deploy MakeDealCRM on Amazon Web Services. Designed for simplicity and reliability, it offers a complete infrastructure-as-code solution optimized for small to medium M&A firms.

## Features

### 🚀 One-Click Deployment
- Interactive web-based deployment wizard
- Automated AWS resource provisioning
- Pre-configured for optimal performance
- SSL certificates included

### 💰 Cost-Optimized Solo Tier
- Designed for 1-5 users
- Estimated monthly cost: $75-150
- Auto-scaling capabilities
- Pay-as-you-grow model

### 🔒 Enterprise-Grade Security
- Automated security hardening
- SSL/TLS encryption
- Regular security updates
- Compliance-ready configuration

### 📊 Built-in Monitoring
- CloudWatch integration
- Real-time health checks
- Performance metrics dashboard
- Automated alerting

### 🔄 Automated Backups
- Daily automated backups
- Point-in-time recovery
- Cross-region backup options
- One-click restore

## Quick Start

### Prerequisites
- AWS Account with administrative access
- Domain name (optional)
- 15-20 minutes for deployment

### Deployment Steps

1. **Launch the Deployment Wizard**
   ```bash
   cd aws-deploy/wizard
   open index.html
   ```

2. **Connect Your AWS Account**
   - Enter AWS Access Key ID
   - Enter AWS Secret Access Key
   - Select your preferred region

3. **Configure Your Deployment**
   - Choose instance size (Small/Medium/Large)
   - Enter domain name (or use AWS default)
   - Select optional features

4. **Review and Deploy**
   - Review cost estimates
   - Confirm configuration
   - Click "Deploy Now"

5. **Access Your CRM**
   - Wait for deployment to complete (~15 minutes)
   - Access via provided URL
   - Log in with generated credentials

## Deployment Options

### Using CloudFormation (CLI)

```bash
# Set environment variables
export KEY_PAIR_NAME=your-keypair
export ADMIN_EMAIL=admin@yourcompany.com

# Run deployment script
./aws-deploy/scripts/deploy.sh
```

### Using Docker Compose (Manual)

```bash
# Clone repository
git clone https://github.com/yourusername/MakeDealCRM.git
cd MakeDealCRM

# Configure environment
cp .env.example .env
# Edit .env with your settings

# Deploy with Docker
cd aws-deploy/docker
docker-compose up -d
```

## Architecture

### Solo Tier Architecture
```
┌─────────────────┐     ┌─────────────────┐
│   CloudFront    │────▶│   Application   │
│      (CDN)      │     │   Load Balancer │
└─────────────────┘     └────────┬────────┘
                                 │
                        ┌────────▼────────┐
                        │   EC2 Instance  │
                        │  (Docker Host)  │
                        └────────┬────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
           ┌────────▼────────┐      ┌────────▼────────┐
           │   RDS MySQL     │      │   ElastiCache   │
           │   (Primary)     │      │     (Redis)     │
           └─────────────────┘      └─────────────────┘
                    │
           ┌────────▼────────┐
           │   S3 Bucket     │
           │   (Backups)     │
           └─────────────────┘
```

## Cost Breakdown

### Solo Tier (1-5 users)
| Service | Configuration | Monthly Cost |
|---------|--------------|--------------|
| EC2 | t3.small | $15-20 |
| RDS | db.t3.micro | $15-20 |
| EBS Storage | 100GB | $10 |
| Data Transfer | 50GB | $5 |
| Backups | Daily, 7-day retention | $10 |
| **Total** | | **$75-150** |

*Prices based on US East (N. Virginia) region*

## Security Features

### Infrastructure Security
- VPC with private subnets
- Security groups with minimal access
- Network ACLs for additional protection
- AWS Systems Manager for patch management

### Application Security
- SSL/TLS encryption enforced
- Regular security updates
- Fail2ban for intrusion prevention
- ModSecurity WAF rules

### Data Security
- Encrypted EBS volumes
- Encrypted RDS storage
- Encrypted S3 backups
- IAM roles with least privilege

## Monitoring & Alerts

### CloudWatch Dashboards
- CPU, Memory, Disk utilization
- Application response times
- Database performance metrics
- Custom business metrics

### Automated Alerts
- High resource utilization
- Application errors
- Security events
- Backup failures

## Backup & Recovery

### Automated Backups
- Daily full backups
- Continuous transaction logs
- Point-in-time recovery
- Cross-region replication (optional)

### Recovery Options
```bash
# List available backups
./aws-deploy/scripts/rollback.sh list

# Restore from backup
./aws-deploy/scripts/rollback.sh rollback <backup-id>
```

## Scaling Options

### Vertical Scaling
Upgrade instance size without downtime:
```bash
aws ec2 modify-instance-attribute \
  --instance-id i-xxxxx \
  --instance-type t3.large
```

### Horizontal Scaling
Migrate to Team Tier for multiple instances:
```bash
./aws-deploy/scripts/deploy.sh --tier team
```

## Maintenance

### Regular Updates
```bash
# Check for updates
./aws-deploy/scripts/check-updates.sh

# Apply updates
./aws-deploy/scripts/update.sh
```

### Health Checks
```bash
# Run health check
./aws-deploy/scripts/health-check.sh

# View status dashboard
./aws-deploy/scripts/health-check.sh --json
```

## Troubleshooting

### Common Issues

1. **Deployment Fails**
   - Check AWS credentials
   - Verify region availability
   - Review CloudFormation events

2. **Cannot Access Application**
   - Check security group rules
   - Verify DNS propagation
   - Review application logs

3. **Performance Issues**
   - Check CloudWatch metrics
   - Review slow query logs
   - Consider scaling up

### Debug Commands
```bash
# View application logs
docker logs makedealcrm-app

# Check database connectivity
./aws-deploy/scripts/health-check.sh

# View CloudFormation stack events
aws cloudformation describe-stack-events \
  --stack-name makedealcrm-production
```

## File Structure

```
aws-deploy/
├── wizard/                 # Web-based deployment wizard
│   ├── index.html
│   ├── deployment-wizard.js
│   └── styles.css
├── templates/             # CloudFormation templates
│   ├── cloudformation-solo-tier.yaml
│   └── cloudwatch-dashboards.json
├── docker/                # Docker configuration
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── apache-config.conf
├── scripts/               # Automation scripts
│   ├── deploy.sh          # Main deployment script
│   ├── cost-estimator.py  # Cost calculation tool
│   ├── migrate-database.sh
│   ├── security-hardening.sh
│   ├── health-check.sh
│   └── rollback.sh
└── docs/                  # Documentation
    └── upgrade-guide.md
```

## Support

### Documentation
- [Deployment Guide](docs/deployment-guide.md)
- [Upgrade Guide](docs/upgrade-guide.md)
- [Security Best Practices](docs/security.md)

### Community
- GitHub Issues: [github.com/yourusername/MakeDealCRM/issues](https://github.com/yourusername/MakeDealCRM/issues)
- Community Forum: [community.makedealcrm.com](https://community.makedealcrm.com)

### Professional Support
- Email: support@makedealcrm.com
- Phone: 1-800-XXX-XXXX
- Enterprise Support Plans Available

## License

MakeDealCRM is licensed under the GNU General Public License v3.0. See [LICENSE](../LICENSE) for details.

---

**Built with ❤️ for the M&A Community**