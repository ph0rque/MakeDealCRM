# AWS MakeDealCRM Troubleshooting Guide

## Current Status
- **Instance**: Running (i-057fa583fef755c06)
- **IP**: 35.169.23.176
- **Region**: us-east-1
- **Security Groups**: Properly configured (ports 80, 443, 22 open)
- **Application**: Not responding (connection timeout)

## The Problem
The EC2 instance is running but the application isn't accessible. This means either:
1. Docker didn't start
2. The application failed to deploy
3. The docker-compose setup failed

## Next Steps

### 1. SSH into the instance to check what's happening:
```bash
ssh -i makedealcrm-production.pem ec2-user@35.169.23.176
```

If you don't have the key file, check your deployment directory or AWS console.

### 2. Once connected, run these diagnostic commands:

```bash
# Check if Docker is running
sudo systemctl status docker

# Check running containers
sudo docker ps

# Check all containers (including stopped ones)
sudo docker ps -a

# Check if docker-compose is installed
docker-compose --version

# Check deployment directory
ls -la /opt/makedealcrm/

# Check for deployment logs
sudo cat /var/log/makedealcrm-deploy.log
sudo cat /var/log/cloud-init-output.log | tail -100

# Check docker logs if containers exist
sudo docker logs makedealcrm-app
sudo docker logs makedealcrm-traefik
```

### 3. Common Fixes:

#### If Docker isn't running:
```bash
sudo systemctl start docker
sudo systemctl enable docker
```

#### If no containers are running:
```bash
cd /opt/makedealcrm/aws-deploy/docker
sudo docker-compose up -d
```

#### If docker-compose isn't installed:
```bash
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 4. Manual Application Start (if needed):
```bash
# Create the directory structure
sudo mkdir -p /opt/makedealcrm
cd /opt/makedealcrm

# Clone the repository
sudo git clone https://github.com/ph0rque/MakeDealCRM.git .

# Create environment file
sudo tee .env << EOF
DB_HOST=localhost
DB_PORT=3306
DB_NAME=makedealcrm
DB_USER=makedealcrm
DB_PASSWORD=changeme
SITE_URL=http://35.169.23.176
ADMIN_EMAIL=admin@example.com
EOF

# Start with docker-compose
cd aws-deploy/docker
sudo docker-compose up -d
```

### 5. Quick Test After Fix:
```bash
# Test locally on the server
curl http://localhost

# If that works, test from outside
curl http://35.169.23.176
```

## Understanding the Deployment Issue

The CloudFormation template created the infrastructure correctly, but the UserData script (which runs on first boot) likely failed. Common reasons:

1. **GitHub repository issue**: The git clone command in UserData references a placeholder repo
2. **Docker Compose path**: The path to docker-compose.yml might be incorrect
3. **Environment variables**: Database credentials might not have been set correctly
4. **Timing**: Services might have started before dependencies were ready

## For Future Deployments

1. Update the CloudFormation template with your actual GitHub repository
2. Use the fixed template that uses HTTP initially
3. Consider using AWS Systems Manager for better deployment tracking

## Emergency Access

If you can't SSH:
1. Check the key pair name in EC2 console
2. Verify your security group allows SSH from your IP
3. Use EC2 Instance Connect from AWS Console as backup