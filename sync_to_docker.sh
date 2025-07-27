#!/bin/bash
# Sync MakeDeal CRM files to Docker container

echo "Syncing MakeDeal CRM files to Docker container..."

# Create directories
docker exec suitecrm mkdir -p /var/www/html/custom/modules/Pipelines/views
docker exec suitecrm mkdir -p /var/www/html/custom/modules/Deals/views

# Copy Pipeline module files
docker cp custom/modules/Pipelines/views/pipeline-kanban.css suitecrm:/var/www/html/custom/modules/Pipelines/views/
docker cp custom/modules/Pipelines/views/PipelineKanbanView.js suitecrm:/var/www/html/custom/modules/Pipelines/views/

# Copy test file
docker cp pipeline_test.php suitecrm:/var/www/html/

# Set permissions
docker exec suitecrm chown -R www-data:www-data /var/www/html/custom/modules/Pipelines
docker exec suitecrm chown -R www-data:www-data /var/www/html/custom/modules/Deals
docker exec suitecrm chmod -R 755 /var/www/html/custom/modules/Pipelines
docker exec suitecrm chmod -R 755 /var/www/html/custom/modules/Deals

echo "Sync complete! Now run Quick Repair and Rebuild in SuiteCRM Admin."