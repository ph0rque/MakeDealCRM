<?php
/**
 * Test fixtures for duplicate detection testing
 * Provides mock data and helper methods for comprehensive test coverage
 */

namespace SuiteCRM\Tests\Unit\modules\Deals\fixtures;

class DuplicateDetectionFixtures
{
    /**
     * Get common company name variations for testing
     */
    public static function getCompanyNameVariations()
    {
        return [
            'Acme Corporation' => [
                'Acme Corp',
                'Acme Corp.',
                'Acme Corporation Inc',
                'Acme Corporation Inc.',
                'Acme Corporation, Inc.',
                'ACME CORPORATION',
                'acme corporation',
                'Acme Co',
                'Acme Company',
                'The Acme Corporation',
                'Acme Corporation LLC',
                'Acme Corporation Ltd',
                'Acme Corporation Limited'
            ],
            'Johnson & Johnson' => [
                'Johnson and Johnson',
                'J&J',
                'J & J',
                'Johnson&Johnson',
                'JOHNSON & JOHNSON',
                'Johnson + Johnson',
                'Johnson/Johnson'
            ],
            'AT&T' => [
                'AT and T',
                'ATT',
                'A.T.&T.',
                'AT & T',
                'American Telephone & Telegraph',
                'American Telephone and Telegraph'
            ],
            '3M Company' => [
                '3M',
                'Three M',
                'MMM',
                '3M Co',
                '3M Corporation',
                'Minnesota Mining and Manufacturing'
            ]
        ];
    }
    
    /**
     * Get edge case company names
     */
    public static function getEdgeCaseCompanyNames()
    {
        return [
            // Special characters
            "O'Reilly Auto Parts",
            "Barnes & Noble",
            "Tiffany & Co.",
            "L'Oréal",
            "Moët & Chandon",
            "Procter & Gamble",
            
            // Numbers and special formats
            "7-Eleven",
            "20th Century Fox",
            "3Com",
            "1-800-Flowers",
            "99 Cents Only Stores",
            
            // International characters
            "Zürich Insurance",
            "Société Générale",
            "Banco Santander",
            "Российские железные дороги", // Russian Railways
            "中国石油", // PetroChina
            
            // Very long names
            "The International Business Machines Corporation of America and Associated Territories Worldwide",
            
            // Very short names
            "X",
            "Z",
            "AI",
            
            // SQL injection attempts
            "Company'; DROP TABLE users; --",
            "Company\" OR 1=1 --",
            "Company'; INSERT INTO users VALUES ('hacker', 'password'); --",
            "Company</script><script>alert('XSS')</script>",
            
            // Empty/whitespace
            "",
            " ",
            "   ",
            "\t\n",
            
            // Unicode edge cases
            "Company\u200B", // Zero-width space
            "Company\u00A0", // Non-breaking space
            "🏢 Company", // Emoji
            "Company™️", // Trademark symbol
            "Company®", // Registered symbol
            
            // Domain-like names
            "company.com",
            "www.company.com",
            "https://company.com",
            "company@email.com"
        ];
    }
    
    /**
     * Get test email addresses
     */
    public static function getTestEmails()
    {
        return [
            // Standard emails
            'john.doe@company.com',
            'jane_smith@company.co.uk',
            'contact@company.org',
            'info@company.net',
            
            // Edge cases
            'user+tag@company.com',
            'user.name+tag@example.com',
            'test@sub.domain.company.com',
            'user@[192.168.1.1]', // IP address
            'user@localhost',
            
            // International
            'user@company.中国',
            'user@εταιρεία.gr',
            
            // Invalid but might be entered
            'not-an-email',
            '@company.com',
            'user@',
            'user@@company.com',
            'user @company.com',
            'user@company .com'
        ];
    }
    
    /**
     * Get test amounts with edge cases
     */
    public static function getTestAmounts()
    {
        return [
            // Normal amounts
            1000,
            5000.50,
            100000,
            1500000,
            
            // Edge cases
            0,
            -1000, // Negative
            0.01, // Very small
            999999999999, // Very large
            null, // Null value
            '', // Empty string
            'not-a-number', // Invalid
            '1,000.00', // Formatted
            '$5,000', // With currency
            '5000.999', // Extra decimals
            
            // Scientific notation
            1e5,
            1.5e6,
            
            // Special values
            PHP_INT_MAX,
            PHP_FLOAT_MAX
        ];
    }
    
    /**
     * Get test deal names with variations
     */
    public static function getDealNameVariations()
    {
        return [
            'Enterprise Software License Deal' => [
                'Enterprise Software License',
                'Software License Deal',
                'Enterprise License Deal',
                'Ent. Software Lic. Deal',
                'ENTERPRISE SOFTWARE LICENSE DEAL',
                'enterprise software license deal',
                'Enterprise  Software  License  Deal', // Extra spaces
                'Enterprise Software Licence Deal', // British spelling
                'Enterprise SW License Deal',
                'Enterprise Software Deal'
            ],
            'Annual Maintenance Contract' => [
                'Annual Maint Contract',
                'Annual Maintenance',
                'Maintenance Contract',
                'Annual Maint. Contract',
                'Yearly Maintenance Contract',
                'Annual Service Contract',
                'Annual Support Contract',
                '12-Month Maintenance Contract',
                'Annual Maintenance Agreement'
            ]
        ];
    }
    
    /**
     * Generate bulk test data for performance testing
     */
    public static function generateBulkTestData($count = 1000)
    {
        $deals = [];
        $companies = ['Acme', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta'];
        $products = ['Software', 'Hardware', 'Service', 'Consulting', 'Support', 'Training'];
        $types = ['License', 'Contract', 'Agreement', 'Deal', 'Purchase', 'Order'];
        
        for ($i = 0; $i < $count; $i++) {
            $company = $companies[array_rand($companies)];
            $product = $products[array_rand($products)];
            $type = $types[array_rand($types)];
            
            $deals[] = [
                'id' => 'bulk-test-' . $i,
                'name' => "$company $product $type",
                'account_name' => "$company Corporation",
                'amount' => rand(10000, 500000),
                'sales_stage' => 'Proposal/Price Quote',
                'email' => strtolower($company) . '@example.com'
            ];
        }
        
        return $deals;
    }
    
    /**
     * Get SQL injection test strings
     */
    public static function getSQLInjectionStrings()
    {
        return [
            "'; DROP TABLE deals; --",
            "' OR '1'='1",
            "' OR 1=1 --",
            "'; DELETE FROM users WHERE '1'='1",
            "admin'--",
            "admin' #",
            "admin'/*",
            "' or 1=1#",
            "' or 1=1--",
            "' or 1=1/*",
            "') or '1'='1--",
            "') or ('1'='1--",
            "' UNION SELECT * FROM users --",
            "' UNION ALL SELECT NULL--",
            "; INSERT INTO users (username, password) VALUES ('hacker', 'password')--",
            "'; UPDATE users SET password='hacked' WHERE username='admin'--",
            chr(0), // Null byte
            chr(27) . "[2J", // ANSI escape sequence
            "../../../etc/passwd", // Path traversal
            "..\\..\\..\\windows\\system32\\config\\sam", // Windows path traversal
            "<script>alert('XSS')</script>",
            "javascript:alert('XSS')",
            "<?php system('ls'); ?>",
            "{{7*7}}", // Template injection
            "${7*7}", // Expression injection
            "%(7*7)s" // Format string
        ];
    }
    
    /**
     * Get performance test scenarios
     */
    public static function getPerformanceScenarios()
    {
        return [
            [
                'name' => 'High match density',
                'description' => 'Many similar names/amounts',
                'setup' => function() {
                    $deals = [];
                    for ($i = 0; $i < 100; $i++) {
                        $deals[] = [
                            'name' => 'Acme Deal ' . ($i % 10), // Only 10 unique names
                            'amount' => 50000 + ($i % 5) * 1000, // Only 5 unique amounts
                            'account_name' => 'Acme Corporation'
                        ];
                    }
                    return $deals;
                }
            ],
            [
                'name' => 'Complex queries',
                'description' => 'All criteria provided',
                'setup' => function() {
                    return [
                        'checkData' => [
                            'name' => 'Complex Deal Name With Many Words',
                            'account_name' => 'Complex Corporation International LLC',
                            'amount' => 123456.78,
                            'email1' => 'complex@email.com'
                        ]
                    ];
                }
            ],
            [
                'name' => 'Large result set',
                'description' => 'Query returns many matches',
                'setup' => function() {
                    $deals = [];
                    for ($i = 0; $i < 500; $i++) {
                        $deals[] = [
                            'name' => 'Generic Deal',
                            'amount' => 50000,
                            'account_name' => 'Generic Company'
                        ];
                    }
                    return $deals;
                }
            ]
        ];
    }
}