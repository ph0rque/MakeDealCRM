#!/bin/bash

# Script to find and fix "Opportunities" labels to "Deals"

echo "Finding all 'Opportunities' references in language files..."

# Find all occurrences in language files
find /Users/andrewgauntlet/Desktop/MakeDealCRM -name "*.lang.php" -type f | while read file; do
    if grep -q "Opportunities" "$file" 2>/dev/null; then
        echo "Found in: $file"
        # Create backup
        cp "$file" "$file.bak"
        # Replace Opportunities with Deals (case-sensitive)
        sed -i '' 's/Opportunities/Deals/g' "$file"
        sed -i '' 's/Opportunity/Deal/g' "$file"
        sed -i '' 's/opportunities/deals/g' "$file"
        sed -i '' 's/opportunity/deal/g' "$file"
    fi
done

# Also check for dashboard widgets
echo -e "\nChecking dashboard widget definitions..."
find /Users/andrewgauntlet/Desktop/MakeDealCRM -name "*.php" -type f -path "*/Dashlets/*" | while read file; do
    if grep -q "Opportunities" "$file" 2>/dev/null; then
        echo "Found in dashlet: $file"
        cp "$file" "$file.bak"
        sed -i '' 's/Opportunities/Deals/g' "$file"
        sed -i '' 's/Opportunity/Deal/g' "$file"
    fi
done

echo -e "\nDone! All 'Opportunities' references have been updated to 'Deals'"