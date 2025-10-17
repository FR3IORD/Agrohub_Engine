<?php
// Show PHP configuration information
phpinfo();

// Show session information
echo "<h2>Session Information</h2>";
echo "<pre>";
echo "session_id(): " . session_id() . "\n";
echo "session_status(): " . session_status() . "\n";
echo "Session constants: \n";
if (defined('PHP_SESSION_DISABLED')) echo "PHP_SESSION_DISABLED = " . PHP_SESSION_DISABLED . "\n";
if (defined('PHP_SESSION_NONE')) echo "PHP_SESSION_NONE = " . PHP_SESSION_NONE . "\n";
if (defined('PHP_SESSION_ACTIVE')) echo "PHP_SESSION_ACTIVE = " . PHP_SESSION_ACTIVE . "\n";
echo "</pre>";

// Show available PDO drivers
echo "<h2>PDO Drivers</h2>";
echo "<pre>";
print_r(PDO::getAvailableDrivers());
echo "</pre>";
?>
