# MakeDealCRM - Enhanced SuiteCRM for Real Estate Deal Management

MakeDealCRM is a customized SuiteCRM implementation tailored for commercial real estate professionals, providing advanced deal pipeline management, due diligence tracking, and stakeholder communication tools.

## 🚀 Features

### ✅ Implemented Features

1. **Unified Deal Pipeline System** (Task 1 - Complete)
   - Visual Kanban-style deal pipeline with drag-and-drop functionality
   - Customizable deal stages for different deal types
   - Real-time stage transitions with automated workflows
   - Mobile-responsive pipeline visualization

2. **Due Diligence Checklist System** (Task 2 - Complete)
   - Dynamic checklist templates by deal type and stage
   - Automated task generation based on deal progression
   - Progress tracking with visual indicators
   - Team assignment and permission management
   - Export functionality for reporting

3. **Stakeholder Tracking & Communication** (Task 3 - Complete)
   - Comprehensive stakeholder relationship management
   - Role-based stakeholder categorization
   - Communication history tracking
   - Bulk email functionality with templates
   - Integration with deal workflows

4. **Financial & Valuation Hub** (Task 4 - Complete)
   - Financial dashboard with key metrics visualization
   - Capital stack management
   - Valuation calculations and comparables analysis
   - ROI and IRR calculations
   - Export to Excel functionality

5. **Email Integration System** (Task 5 - Complete)
   - Automated email parsing and deal association
   - Smart email templates with variable substitution
   - File request system with tracking
   - Email communication history per deal
   - Bulk email campaigns for stakeholders

6. **One-Click AWS Deployment** (Task 10 - Complete)
   - CloudFormation templates for infrastructure
   - Automated deployment scripts
   - Multi-tier architecture support
   - Security hardening scripts
   - Health check and monitoring setup
   - Database migration automation

7. **Module Navigation Fixes** (Task 11 - Complete)
   - Fixed Deals module to default to pipeline view
   - Proper AJAX navigation support
   - Consistent module routing

### 🔄 In Progress

- **Module Structure Cleanup** (Tasks 12-15)
  - Auditing and reorganizing module directory structure
  - Moving code to proper SuiteCRM conventions

### 📋 Planned Features

- **Pipeline Asset Recovery** (Tasks 16-19)
  - Locating and fixing missing CSS/JS files
  - Ensuring full pipeline functionality

## 🛠️ Technical Architecture

### Custom Modules
- **Deals**: Enhanced deals module with pipeline visualization
- **Pipelines**: Pipeline automation and stage management
- **Checklists**: Due diligence task management
- **Stakeholder Integration**: Communication and relationship tracking

### Key Technologies
- **Backend**: PHP 7.4, SuiteCRM 7.x framework
- **Frontend**: JavaScript, jQuery, Custom Kanban implementation
- **Database**: MySQL 8.0 with custom schema extensions
- **Deployment**: Docker, AWS CloudFormation, Bash automation

## 📦 Installation

### Prerequisites
- Docker and Docker Compose
- Git
- AWS CLI (for cloud deployment)

### Local Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/MakeDealCRM.git
   cd MakeDealCRM
   ```

2. Copy environment configuration:
   ```bash
   cp .env.example .env
   ```

3. Start the application:
   
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

### AWS Deployment

For one-click AWS deployment:

```bash
cd aws-deploy
./scripts/deploy.sh --stack-name makedealcrm-prod --region us-east-1
```

See [AWS Deployment Guide](aws-deploy/README.md) for detailed instructions.

## 🔐 Default Credentials

### SuiteCRM Admin
- Username: admin
- Password: admin123

### Database
- Host: db
- Database: suitecrm
- Username: suitecrm
- Password: suitecrm_password

⚠️ **Security Notice**: Change all default credentials before deploying to production!

## 📚 Documentation

- [Business Requirements](docs/business/features.md)
- [Technical Implementation](docs/technical/implementation-plan.md)
- [API Documentation](docs/api/README.md)
- [Deployment Guide](aws-deploy/README.md)

## 🧪 Testing

Run the test suite:

```bash
# Unit tests
docker-compose exec suitecrm php vendor/bin/phpunit

# Feature tests
docker-compose exec suitecrm php custom/modules/Deals/test_pipeline.php
docker-compose exec suitecrm php custom/modules/Deals/test_file_request_system.php
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📈 Project Status

- **Overall Progress**: 41% of main tasks completed
- **Subtask Progress**: 51% completed (71/140 subtasks)
- **Current Sprint**: Module structure cleanup and optimization

See [Task Master Status](#) for detailed task tracking.

## 🐛 Known Issues

1. **Pipeline View Assets**: Some CSS/JS files need to be relocated to proper directories
2. **Module Structure**: Top-level `/modules` directory needs cleanup and consolidation

## 📝 License

This project is licensed under the AGPLv3 License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built on [SuiteCRM](https://suitecrm.com/) open-source CRM platform
- Inspired by real estate industry requirements
- Docker configuration based on official SuiteCRM images

## 📞 Support

For issues and questions:
- GitHub Issues: [Create an issue](https://github.com/yourusername/MakeDealCRM/issues)
- Documentation: [Wiki](https://github.com/yourusername/MakeDealCRM/wiki)