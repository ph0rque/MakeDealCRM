#!/usr/bin/env python3
"""
MakeDealCRM AWS Cost Estimator
Calculates estimated monthly costs based on configuration
"""

import json
import argparse
from datetime import datetime

# AWS Pricing (US East 1 - prices may vary by region)
PRICING = {
    'ec2': {
        't3.small': 0.0208,   # per hour
        't3.medium': 0.0416,  # per hour
        't3.large': 0.0832,   # per hour
    },
    'rds': {
        'db.t3.micro': 0.017,   # per hour
        'db.t3.small': 0.034,   # per hour
    },
    'ebs': {
        'gp3': 0.08,  # per GB per month
    },
    'data_transfer': {
        'out': 0.09,  # per GB after 1GB free tier
    },
    's3': {
        'storage': 0.023,  # per GB per month
        'requests': 0.0004,  # per 1000 requests
    },
    'cloudwatch': {
        'logs': 0.50,  # per GB ingested
        'metrics': 0.30,  # per metric per month
        'alarms': 0.10,  # per alarm per month
    },
    'backup': {
        'storage': 0.05,  # per GB per month
    }
}

def calculate_ec2_cost(instance_type, high_availability=False):
    """Calculate EC2 instance costs"""
    hourly_rate = PRICING['ec2'].get(instance_type, 0.0208)
    monthly_hours = 730  # Average hours in a month
    
    instances = 2 if high_availability else 1
    monthly_cost = hourly_rate * monthly_hours * instances
    
    return {
        'service': 'EC2 Instances',
        'description': f'{instances}x {instance_type}',
        'monthly_cost': round(monthly_cost, 2)
    }

def calculate_rds_cost(high_availability=False):
    """Calculate RDS database costs"""
    instance_class = 'db.t3.small' if high_availability else 'db.t3.micro'
    hourly_rate = PRICING['rds'].get(instance_class, 0.017)
    monthly_hours = 730
    
    # Multi-AZ doubles the cost
    multiplier = 2 if high_availability else 1
    monthly_cost = hourly_rate * monthly_hours * multiplier
    
    return {
        'service': 'RDS Database',
        'description': f'{instance_class} {"Multi-AZ" if high_availability else "Single-AZ"}',
        'monthly_cost': round(monthly_cost, 2)
    }

def calculate_storage_cost(storage_gb=100):
    """Calculate EBS storage costs"""
    monthly_cost = PRICING['ebs']['gp3'] * storage_gb
    
    return {
        'service': 'EBS Storage',
        'description': f'{storage_gb}GB GP3',
        'monthly_cost': round(monthly_cost, 2)
    }

def calculate_data_transfer_cost(gb_per_month=50):
    """Calculate data transfer costs"""
    # First 1GB is free
    billable_gb = max(0, gb_per_month - 1)
    monthly_cost = PRICING['data_transfer']['out'] * billable_gb
    
    return {
        'service': 'Data Transfer',
        'description': f'{gb_per_month}GB outbound',
        'monthly_cost': round(monthly_cost, 2)
    }

def calculate_backup_cost(backup_gb=50, retention_days=7):
    """Calculate backup storage costs"""
    # Average backup size over retention period
    avg_storage = backup_gb * (retention_days / 2)
    monthly_cost = PRICING['backup']['storage'] * avg_storage
    
    return {
        'service': 'Backup Storage',
        'description': f'{backup_gb}GB daily, {retention_days} day retention',
        'monthly_cost': round(monthly_cost, 2)
    }

def calculate_monitoring_cost(enable_monitoring=True):
    """Calculate CloudWatch monitoring costs"""
    if not enable_monitoring:
        return {
            'service': 'CloudWatch Monitoring',
            'description': 'Disabled',
            'monthly_cost': 0
        }
    
    # Estimate: 5GB logs, 10 custom metrics, 5 alarms
    logs_cost = PRICING['cloudwatch']['logs'] * 5
    metrics_cost = PRICING['cloudwatch']['metrics'] * 10
    alarms_cost = PRICING['cloudwatch']['alarms'] * 5
    
    monthly_cost = logs_cost + metrics_cost + alarms_cost
    
    return {
        'service': 'CloudWatch Monitoring',
        'description': 'Logs, Metrics, Alarms',
        'monthly_cost': round(monthly_cost, 2)
    }

def calculate_total_cost(config):
    """Calculate total monthly cost based on configuration"""
    costs = []
    
    # EC2 costs
    costs.append(calculate_ec2_cost(
        config.get('instance_type', 't3.small'),
        config.get('high_availability', False)
    ))
    
    # RDS costs
    costs.append(calculate_rds_cost(
        config.get('high_availability', False)
    ))
    
    # Storage costs
    costs.append(calculate_storage_cost(
        config.get('storage_gb', 100)
    ))
    
    # Data transfer costs
    costs.append(calculate_data_transfer_cost(
        config.get('data_transfer_gb', 50)
    ))
    
    # Backup costs
    if config.get('enable_backups', True):
        costs.append(calculate_backup_cost(
            config.get('backup_gb', 50),
            config.get('retention_days', 7)
        ))
    
    # Monitoring costs
    costs.append(calculate_monitoring_cost(
        config.get('enable_monitoring', True)
    ))
    
    # Calculate total
    total = sum(item['monthly_cost'] for item in costs)
    
    return {
        'breakdown': costs,
        'total_monthly': round(total, 2),
        'total_annual': round(total * 12, 2),
        'currency': 'USD',
        'region': config.get('region', 'us-east-1'),
        'generated_at': datetime.utcnow().isoformat()
    }

def generate_cost_report(config):
    """Generate a detailed cost report"""
    estimate = calculate_total_cost(config)
    
    print("\n" + "="*60)
    print("MakeDealCRM AWS Cost Estimate")
    print("="*60)
    print(f"\nConfiguration:")
    print(f"  Instance Type: {config.get('instance_type', 't3.small')}")
    print(f"  High Availability: {'Yes' if config.get('high_availability') else 'No'}")
    print(f"  Storage: {config.get('storage_gb', 100)}GB")
    print(f"  Backups: {'Enabled' if config.get('enable_backups', True) else 'Disabled'}")
    print(f"  Monitoring: {'Enabled' if config.get('enable_monitoring', True) else 'Disabled'}")
    print(f"  Region: {config.get('region', 'us-east-1')}")
    
    print(f"\nCost Breakdown:")
    print("-"*60)
    for item in estimate['breakdown']:
        print(f"{item['service']:<30} {item['description']:<20} ${item['monthly_cost']:>8.2f}")
    
    print("-"*60)
    print(f"{'Total Monthly Cost':<52} ${estimate['total_monthly']:>8.2f}")
    print(f"{'Total Annual Cost':<52} ${estimate['total_annual']:>8.2f}")
    
    print("\nNotes:")
    print("- Prices are estimates based on US East (N. Virginia) region")
    print("- Actual costs may vary based on usage patterns")
    print("- AWS Free Tier credits may reduce first-year costs")
    print("- Data transfer costs are estimated based on typical usage")
    
    return estimate

def save_estimate(estimate, filename='cost-estimate.json'):
    """Save cost estimate to JSON file"""
    with open(filename, 'w') as f:
        json.dump(estimate, f, indent=2)
    print(f"\nEstimate saved to: {filename}")

def main():
    parser = argparse.ArgumentParser(description='MakeDealCRM AWS Cost Estimator')
    parser.add_argument('--instance-type', default='t3.small', 
                       choices=['t3.small', 't3.medium', 't3.large'],
                       help='EC2 instance type')
    parser.add_argument('--high-availability', action='store_true',
                       help='Enable Multi-AZ deployment')
    parser.add_argument('--storage-gb', type=int, default=100,
                       help='Storage size in GB')
    parser.add_argument('--disable-backups', action='store_true',
                       help='Disable automated backups')
    parser.add_argument('--disable-monitoring', action='store_true',
                       help='Disable CloudWatch monitoring')
    parser.add_argument('--data-transfer-gb', type=int, default=50,
                       help='Estimated monthly data transfer in GB')
    parser.add_argument('--retention-days', type=int, default=7,
                       help='Backup retention days')
    parser.add_argument('--region', default='us-east-1',
                       help='AWS region')
    parser.add_argument('--save', action='store_true',
                       help='Save estimate to JSON file')
    
    args = parser.parse_args()
    
    # Build configuration
    config = {
        'instance_type': args.instance_type,
        'high_availability': args.high_availability,
        'storage_gb': args.storage_gb,
        'enable_backups': not args.disable_backups,
        'enable_monitoring': not args.disable_monitoring,
        'data_transfer_gb': args.data_transfer_gb,
        'retention_days': args.retention_days,
        'region': args.region
    }
    
    # Generate report
    estimate = generate_cost_report(config)
    
    # Save if requested
    if args.save:
        save_estimate(estimate)

if __name__ == '__main__':
    main()