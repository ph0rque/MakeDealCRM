# MakeDealCRM - SuiteCRM Docker Setup

This repository contains a Dockerized setup for SuiteCRM, an open-source Customer Relationship Management system.

## Prerequisites

- Docker
- Docker Compose

## Quick Start

1. Clone this repository
2. Copy the environment file:
   ```bash
   cp .env.example .env
   ```
3. Build and start the containers:
   
   **For Production:**
   ```bash
   docker-compose up -d
   ```
   
   **For Development (includes phpMyAdmin):**
   ```bash
   docker-compose --profile dev up -d
   ```
   
4. Access the applications:
   - SuiteCRM: http://localhost:8080
   - phpMyAdmin: http://localhost:8081 (development only)

## Default Credentials

### SuiteCRM Admin
- Username: admin
- Password: admin123

### Database
- Host: db
- Database: suitecrm
- Username: suitecrm
- Password: suitecrm_password
- Root Password: root_password

## Services

- **suitecrm**: Main SuiteCRM application (PHP 7.4 with Apache)
- **db**: MySQL 8.0 database
- **phpmyadmin**: Database management interface (development profile only)

## Volumes

- `db_data`: MySQL data persistence
- `suitecrm_uploads`: File uploads
- `suitecrm_cache`: Application cache

## Useful Commands

```bash
# Start services (production)
docker-compose up -d

# Start services (development with phpMyAdmin)
docker-compose --profile dev up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Access SuiteCRM container
docker-compose exec suitecrm bash

# Access MySQL
docker-compose exec db mysql -u root -p
```

## Configuration

Edit `.env` file to customize:
- Database credentials
- Admin credentials
- PHP settings
- Site URL

## Security Notice

⚠️ The default credentials are for development only. Always change them in production environments.