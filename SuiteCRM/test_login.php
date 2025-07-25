<?php
// Test login script
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/authentication/AuthenticationController.php');

echo "Testing SuiteCRM login...\n";

$authController = new AuthenticationController();

// Test with admin/admin
$result = $authController->login('admin', 'admin', false);
echo "Login with admin/admin: " . ($result ? "SUCCESS" : "FAILED") . "\n";

if (!$result) {
    // Try to retrieve admin user
    $user = BeanFactory::getBean('Users');
    $user->retrieve_by_string_fields(['user_name' => 'admin']);
    
    if ($user->id) {
        echo "Admin user exists with ID: " . $user->id . "\n";
        echo "User status: " . $user->status . "\n";
        echo "User deleted: " . $user->deleted . "\n";
        
        // Try to reset password
        echo "\nAttempting to reset password to 'admin'...\n";
        $user->setNewPassword('admin', '1');
        $user->save();
        echo "Password reset complete.\n";
    } else {
        echo "Admin user not found!\n";
    }
}