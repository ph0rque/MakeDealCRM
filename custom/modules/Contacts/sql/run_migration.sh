#!/bin/bash

# =====================================================
# Stakeholder Tracking Migration Runner
# Version: 1.0.0
# =====================================================

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASS=""
RUN_MODE="all" # all, individual, check
MIGRATION_DIR="$(dirname "$0")"

# Function to display usage
usage() {
    echo "Usage: $0 -d DATABASE -u USERNAME [-h HOST] [-p PORT] [-m MODE]"
    echo ""
    echo "Options:"
    echo "  -d DATABASE   Database name (required)"
    echo "  -u USERNAME   Database username (required)"
    echo "  -h HOST       Database host (default: localhost)"
    echo "  -p PORT       Database port (default: 3306)"
    echo "  -m MODE       Migration mode: all, individual, check, rollback (default: all)"
    echo "  -?            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 -d suitecrm -u root -m all"
    echo "  $0 -d suitecrm -u root -m check"
    echo "  $0 -d suitecrm -u root -m rollback"
    exit 1
}

# Parse command line arguments
while getopts "d:u:h:p:m:?" opt; do
    case $opt in
        d) DB_NAME="$OPTARG" ;;
        u) DB_USER="$OPTARG" ;;
        h) DB_HOST="$OPTARG" ;;
        p) DB_PORT="$OPTARG" ;;
        m) RUN_MODE="$OPTARG" ;;
        ?) usage ;;
    esac
done

# Validate required parameters
if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo -e "${RED}Error: Database name and username are required${NC}"
    usage
fi

# Prompt for password
echo -n "Enter password for $DB_USER@$DB_HOST: "
read -s DB_PASS
echo ""

# MySQL connection command
MYSQL_CMD="mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME"

# Function to run a SQL file
run_sql_file() {
    local file=$1
    local desc=$2
    
    echo -e "${YELLOW}Running: $desc${NC}"
    
    if $MYSQL_CMD < "$file" 2>/tmp/mysql_error.log; then
        echo -e "${GREEN}✓ Success: $desc${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed: $desc${NC}"
        echo -e "${RED}Error details:${NC}"
        cat /tmp/mysql_error.log
        return 1
    fi
}

# Function to check migration status
check_status() {
    echo -e "${YELLOW}Checking migration status...${NC}"
    
    $MYSQL_CMD -e "
    SELECT 
        migration_file as 'Migration File',
        executed_at as 'Executed At',
        execution_time_ms as 'Time (ms)',
        status as 'Status'
    FROM stakeholder_migrations
    ORDER BY executed_at;" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Migration table found${NC}"
    else
        echo -e "${YELLOW}No migrations have been run yet${NC}"
    fi
    
    # Check for key tables
    echo -e "\n${YELLOW}Checking key tables...${NC}"
    
    tables=("contact_communication_history" "communication_templates" "deal_stakeholder_teams")
    for table in "${tables[@]}"; do
        result=$($MYSQL_CMD -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME' AND table_name = '$table';" -s 2>/dev/null)
        if [ "$result" == "1" ]; then
            echo -e "${GREEN}✓ Table exists: $table${NC}"
        else
            echo -e "${RED}✗ Table missing: $table${NC}"
        fi
    done
    
    # Check for views
    echo -e "\n${YELLOW}Checking views...${NC}"
    
    views=("v_stakeholder_dashboard" "v_deal_stakeholder_matrix")
    for view in "${views[@]}"; do
        result=$($MYSQL_CMD -e "SELECT COUNT(*) FROM information_schema.views WHERE table_schema = '$DB_NAME' AND table_name = '$view';" -s 2>/dev/null)
        if [ "$result" == "1" ]; then
            echo -e "${GREEN}✓ View exists: $view${NC}"
        else
            echo -e "${RED}✗ View missing: $view${NC}"
        fi
    done
}

# Function to run all migrations
run_all_migrations() {
    echo -e "${YELLOW}Running all stakeholder tracking migrations...${NC}"
    
    # First, source each individual file within the master script
    # In production, you might modify the master script to use SOURCE commands
    
    # For now, we'll run them individually in order
    migrations=(
        "001_add_stakeholder_tracking_fields.sql:Adding stakeholder tracking fields"
        "002_create_communication_history_tables.sql:Creating communication history tables"
        "003_enhance_deals_contacts_relationship.sql:Enhancing deals-contacts relationship"
        "004_create_stakeholder_integration_views.sql:Creating integration views and functions"
    )
    
    for migration in "${migrations[@]}"; do
        IFS=':' read -r file desc <<< "$migration"
        if ! run_sql_file "$MIGRATION_DIR/$file" "$desc"; then
            echo -e "${RED}Migration failed at: $file${NC}"
            echo -e "${YELLOW}Run with -m check to see migration status${NC}"
            exit 1
        fi
    done
    
    echo -e "${GREEN}✓ All migrations completed successfully!${NC}"
}

# Function to run individual migrations
run_individual_migrations() {
    echo -e "${YELLOW}Available migrations:${NC}"
    echo "1. Add stakeholder tracking fields"
    echo "2. Create communication history tables"
    echo "3. Enhance deals-contacts relationship"
    echo "4. Create integration views and functions"
    echo ""
    
    read -p "Enter migration number to run (1-4): " choice
    
    case $choice in
        1) run_sql_file "$MIGRATION_DIR/001_add_stakeholder_tracking_fields.sql" "Adding stakeholder tracking fields" ;;
        2) run_sql_file "$MIGRATION_DIR/002_create_communication_history_tables.sql" "Creating communication history tables" ;;
        3) run_sql_file "$MIGRATION_DIR/003_enhance_deals_contacts_relationship.sql" "Enhancing deals-contacts relationship" ;;
        4) run_sql_file "$MIGRATION_DIR/004_create_stakeholder_integration_views.sql" "Creating integration views and functions" ;;
        *) echo -e "${RED}Invalid choice${NC}" ;;
    esac
}

# Function to run rollback
run_rollback() {
    echo -e "${RED}WARNING: This will rollback all stakeholder tracking changes!${NC}"
    echo -e "${RED}This action cannot be undone and will DELETE all stakeholder tracking data!${NC}"
    echo ""
    read -p "Are you sure you want to continue? Type 'YES' to confirm: " confirm
    
    if [ "$confirm" == "YES" ]; then
        # Extract rollback script from master migration
        sed -n '/-- To rollback all changes/,/SELECT.*Rollback completed/p' "$MIGRATION_DIR/000_master_stakeholder_migration.sql" | \
        sed '1d' | sed 's/^\/\*//' | sed 's/\*\/$//' > /tmp/rollback.sql
        
        run_sql_file "/tmp/rollback.sql" "Rolling back all migrations"
        rm /tmp/rollback.sql
    else
        echo -e "${YELLOW}Rollback cancelled${NC}"
    fi
}

# Main execution
echo -e "${GREEN}Stakeholder Tracking Migration Runner${NC}"
echo "Database: $DB_NAME@$DB_HOST:$DB_PORT"
echo ""

# Test database connection
echo -e "${YELLOW}Testing database connection...${NC}"
if $MYSQL_CMD -e "SELECT 1;" >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Failed to connect to database${NC}"
    echo -e "${RED}Please check your credentials and try again${NC}"
    exit 1
fi

# Enable event scheduler
echo -e "${YELLOW}Enabling event scheduler...${NC}"
$MYSQL_CMD -e "SET GLOBAL event_scheduler = ON;" 2>/dev/null
echo -e "${GREEN}✓ Event scheduler enabled${NC}"

# Run based on mode
case $RUN_MODE in
    all)
        run_all_migrations
        echo ""
        check_status
        ;;
    individual)
        run_individual_migrations
        ;;
    check)
        check_status
        ;;
    rollback)
        run_rollback
        ;;
    *)
        echo -e "${RED}Invalid mode: $RUN_MODE${NC}"
        usage
        ;;
esac

echo ""
echo -e "${GREEN}Done!${NC}"

# Cleanup
rm -f /tmp/mysql_error.log 2>/dev/null