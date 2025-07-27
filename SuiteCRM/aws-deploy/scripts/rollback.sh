#!/bin/bash

# MakeDealCRM Rollback Script
# Provides automated rollback capabilities for failed deployments

set -e

# Configuration
BACKUP_DIR="/opt/makedealcrm/backups"
ROLLBACK_LOG="/var/log/makedealcrm-rollback.log"
MAX_ROLLBACK_POINTS=10

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Functions
print_status() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$ROLLBACK_LOG"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$ROLLBACK_LOG"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$ROLLBACK_LOG"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$ROLLBACK_LOG"
}

# Create rollback point
create_rollback_point() {
    local rollback_id="${1:-$(date +%s)}"
    local rollback_dir="$BACKUP_DIR/rollback-$rollback_id"
    
    print_status "Creating rollback point: $rollback_id"
    
    # Create rollback directory
    mkdir -p "$rollback_dir"
    
    # Save current application state
    print_status "Backing up application files..."
    tar -czf "$rollback_dir/app-backup.tar.gz" \
        -C /opt/makedealcrm \
        --exclude='backups' \
        --exclude='logs' \
        --exclude='.git' \
        .
    
    # Save database snapshot
    print_status "Creating database snapshot..."
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        "$DB_NAME" | gzip > "$rollback_dir/database-backup.sql.gz"
    
    # Save configuration
    print_status "Saving configuration..."
    cp /opt/makedealcrm/.env "$rollback_dir/env-backup"
    
    # Save Docker images
    print_status "Saving Docker images..."
    docker images --format "{{.Repository}}:{{.Tag}}" | \
        grep makedealcrm | \
        xargs -I {} docker save {} | \
        gzip > "$rollback_dir/docker-images.tar.gz"
    
    # Save CloudFormation stack state
    if [ -n "$STACK_NAME" ]; then
        print_status "Saving CloudFormation stack state..."
        aws cloudformation describe-stacks \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION" \
            > "$rollback_dir/stack-state.json"
    fi
    
    # Create metadata
    cat > "$rollback_dir/metadata.json" <<EOF
{
    "rollback_id": "$rollback_id",
    "created_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "app_version": "$(cd /opt/makedealcrm && git describe --tags 2>/dev/null || echo 'unknown')",
    "created_by": "$USER",
    "reason": "${ROLLBACK_REASON:-Manual rollback point}"
}
EOF
    
    # Clean up old rollback points
    cleanup_old_rollback_points
    
    print_status "Rollback point created successfully: $rollback_id"
    echo "$rollback_id"
}

# List available rollback points
list_rollback_points() {
    print_status "Available rollback points:"
    
    if [ ! -d "$BACKUP_DIR" ]; then
        print_warning "No rollback points found"
        return 1
    fi
    
    echo ""
    printf "%-15s %-25s %-15s %s\n" "ID" "Created" "Version" "Reason"
    printf "%-15s %-25s %-15s %s\n" "---" "-------" "-------" "------"
    
    for rollback_dir in $(ls -d "$BACKUP_DIR"/rollback-* 2>/dev/null | sort -r); do
        if [ -f "$rollback_dir/metadata.json" ]; then
            local id=$(basename "$rollback_dir" | sed 's/rollback-//')
            local created=$(jq -r '.created_at' "$rollback_dir/metadata.json")
            local version=$(jq -r '.app_version' "$rollback_dir/metadata.json")
            local reason=$(jq -r '.reason' "$rollback_dir/metadata.json")
            
            printf "%-15s %-25s %-15s %s\n" "$id" "$created" "$version" "$reason"
        fi
    done
    echo ""
}

# Perform rollback
perform_rollback() {
    local rollback_id="$1"
    local rollback_dir="$BACKUP_DIR/rollback-$rollback_id"
    
    if [ ! -d "$rollback_dir" ]; then
        print_error "Rollback point not found: $rollback_id"
        return 1
    fi
    
    print_status "Starting rollback to point: $rollback_id"
    
    # Verify rollback files exist
    for file in app-backup.tar.gz database-backup.sql.gz env-backup docker-images.tar.gz; do
        if [ ! -f "$rollback_dir/$file" ]; then
            print_error "Missing rollback file: $file"
            return 1
        fi
    done
    
    # Create pre-rollback backup
    print_status "Creating pre-rollback backup..."
    PRE_ROLLBACK_ID="pre-rollback-$(date +%s)"
    create_rollback_point "$PRE_ROLLBACK_ID" > /dev/null
    
    # Stop services
    print_status "Stopping services..."
    cd /opt/makedealcrm/aws-deploy/docker
    docker-compose down
    
    # Restore application files
    print_status "Restoring application files..."
    cd /opt/makedealcrm
    tar -xzf "$rollback_dir/app-backup.tar.gz"
    
    # Restore configuration
    print_status "Restoring configuration..."
    cp "$rollback_dir/env-backup" /opt/makedealcrm/.env
    
    # Restore Docker images
    print_status "Restoring Docker images..."
    gunzip -c "$rollback_dir/docker-images.tar.gz" | docker load
    
    # Restore database
    print_status "Restoring database..."
    gunzip -c "$rollback_dir/database-backup.sql.gz" | \
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"
    
    # Start services
    print_status "Starting services..."
    cd /opt/makedealcrm/aws-deploy/docker
    docker-compose up -d
    
    # Wait for services to be ready
    print_status "Waiting for services to be ready..."
    sleep 30
    
    # Verify rollback
    if verify_rollback; then
        print_status "Rollback completed successfully!"
        
        # Log rollback event
        log_rollback_event "$rollback_id" "success"
        
        return 0
    else
        print_error "Rollback verification failed!"
        print_warning "You may need to manually restore from: $PRE_ROLLBACK_ID"
        
        # Log rollback event
        log_rollback_event "$rollback_id" "failed"
        
        return 1
    fi
}

# Verify rollback success
verify_rollback() {
    print_status "Verifying rollback..."
    
    # Check if services are running
    if ! docker-compose ps | grep -q "Up"; then
        print_error "Docker services are not running"
        return 1
    fi
    
    # Check database connectivity
    if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
        print_error "Database connection failed"
        return 1
    fi
    
    # Check application health
    if ! curl -s -o /dev/null -w "%{http_code}" http://localhost/health.php | grep -q "200"; then
        print_error "Application health check failed"
        return 1
    fi
    
    print_status "Rollback verification passed"
    return 0
}

# Clean up old rollback points
cleanup_old_rollback_points() {
    local count=$(ls -d "$BACKUP_DIR"/rollback-* 2>/dev/null | wc -l)
    
    if [ "$count" -gt "$MAX_ROLLBACK_POINTS" ]; then
        print_status "Cleaning up old rollback points..."
        
        # Keep only the most recent rollback points
        ls -dt "$BACKUP_DIR"/rollback-* | \
            tail -n +$((MAX_ROLLBACK_POINTS + 1)) | \
            xargs rm -rf
    fi
}

# Log rollback event
log_rollback_event() {
    local rollback_id="$1"
    local status="$2"
    
    cat >> "$ROLLBACK_LOG" <<EOF
{
    "event": "rollback",
    "rollback_id": "$rollback_id",
    "status": "$status",
    "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "user": "$USER"
}
EOF
}

# Delete rollback point
delete_rollback_point() {
    local rollback_id="$1"
    local rollback_dir="$BACKUP_DIR/rollback-$rollback_id"
    
    if [ ! -d "$rollback_dir" ]; then
        print_error "Rollback point not found: $rollback_id"
        return 1
    fi
    
    print_warning "Are you sure you want to delete rollback point $rollback_id? (yes/no)"
    read -r response
    
    if [ "$response" = "yes" ]; then
        rm -rf "$rollback_dir"
        print_status "Rollback point deleted: $rollback_id"
    else
        print_status "Deletion cancelled"
    fi
}

# Show rollback details
show_rollback_details() {
    local rollback_id="$1"
    local rollback_dir="$BACKUP_DIR/rollback-$rollback_id"
    
    if [ ! -d "$rollback_dir" ]; then
        print_error "Rollback point not found: $rollback_id"
        return 1
    fi
    
    print_status "Rollback point details: $rollback_id"
    echo ""
    
    # Show metadata
    if [ -f "$rollback_dir/metadata.json" ]; then
        echo "Metadata:"
        jq . "$rollback_dir/metadata.json"
        echo ""
    fi
    
    # Show file sizes
    echo "Backup files:"
    ls -lh "$rollback_dir" | grep -E '\.(tar\.gz|sql\.gz|json)$'
    echo ""
    
    # Calculate total size
    local total_size=$(du -sh "$rollback_dir" | cut -f1)
    echo "Total size: $total_size"
}

# Main execution
main() {
    # Load environment variables
    if [ -f /opt/makedealcrm/.env ]; then
        source /opt/makedealcrm/.env
    fi
    
    case "${1:-}" in
        create)
            shift
            create_rollback_point "$@"
            ;;
        list)
            list_rollback_points
            ;;
        rollback)
            shift
            if [ -z "$1" ]; then
                print_error "Rollback ID required"
                echo "Usage: $0 rollback <rollback-id>"
                exit 1
            fi
            perform_rollback "$1"
            ;;
        delete)
            shift
            if [ -z "$1" ]; then
                print_error "Rollback ID required"
                echo "Usage: $0 delete <rollback-id>"
                exit 1
            fi
            delete_rollback_point "$1"
            ;;
        details)
            shift
            if [ -z "$1" ]; then
                print_error "Rollback ID required"
                echo "Usage: $0 details <rollback-id>"
                exit 1
            fi
            show_rollback_details "$1"
            ;;
        cleanup)
            cleanup_old_rollback_points
            ;;
        *)
            echo "MakeDealCRM Rollback Manager"
            echo ""
            echo "Usage: $0 {create|list|rollback|delete|details|cleanup} [options]"
            echo ""
            echo "Commands:"
            echo "  create [id]          Create a new rollback point"
            echo "  list                 List available rollback points"
            echo "  rollback <id>        Perform rollback to specified point"
            echo "  delete <id>          Delete a rollback point"
            echo "  details <id>         Show rollback point details"
            echo "  cleanup              Remove old rollback points"
            echo ""
            echo "Examples:"
            echo "  $0 create                    # Create rollback point with auto ID"
            echo "  $0 create pre-upgrade-v2     # Create with custom ID"
            echo "  $0 list                      # Show all rollback points"
            echo "  $0 rollback 1706189432       # Rollback to specific point"
            echo ""
            exit 1
            ;;
    esac
}

# Run main function
main "$@"