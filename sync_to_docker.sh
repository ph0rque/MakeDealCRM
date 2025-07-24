#!/bin/bash
# Sync MakeDeal CRM files to Docker container

echo "Syncing MakeDeal CRM files to Docker container..."

# Create directories
docker exec suitecrm mkdir -p /var/www/html/custom/modules/mdeal_Deals/views
docker exec suitecrm mkdir -p /var/www/html/custom/modules/mdeal_Deals/language/en_us
docker exec suitecrm mkdir -p /var/www/html/custom/modules/Pipelines/views
docker exec suitecrm mkdir -p /var/www/html/modules/mdeal_Deals

# Copy mdeal_Deals module files
docker cp custom/modules/mdeal_Deals/controller.php suitecrm:/var/www/html/custom/modules/mdeal_Deals/
docker cp custom/modules/mdeal_Deals/action_view_map.php suitecrm:/var/www/html/custom/modules/mdeal_Deals/
docker cp custom/modules/mdeal_Deals/views/view.pipeline.php suitecrm:/var/www/html/custom/modules/mdeal_Deals/views/
docker cp custom/modules/mdeal_Deals/Menu.php suitecrm:/var/www/html/custom/modules/mdeal_Deals/
docker cp modules/mdeal_Deals/pipeline.php suitecrm:/var/www/html/modules/mdeal_Deals/

# Copy Pipeline module files
docker cp custom/modules/Pipelines/views/pipeline-kanban.css suitecrm:/var/www/html/custom/modules/Pipelines/views/
docker cp custom/modules/Pipelines/views/PipelineKanbanView.js suitecrm:/var/www/html/custom/modules/Pipelines/views/

# Copy test file
docker cp pipeline_test.php suitecrm:/var/www/html/

# Set permissions
docker exec suitecrm chown -R www-data:www-data /var/www/html/custom/modules/mdeal_Deals
docker exec suitecrm chown -R www-data:www-data /var/www/html/custom/modules/Pipelines
docker exec suitecrm chown -R www-data:www-data /var/www/html/modules/mdeal_Deals
docker exec suitecrm chmod -R 755 /var/www/html/custom/modules/mdeal_Deals
docker exec suitecrm chmod -R 755 /var/www/html/custom/modules/Pipelines

echo "Sync complete! Now run Quick Repair and Rebuild in SuiteCRM Admin."