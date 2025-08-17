<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'smarwmcw_smartgoldtrade_user');
define('DB_PASSWORD', 'DnY*O!$w#Z1V');
define('DB_NAME', 'smarwmcw_smartgoldtrade');

// Create a new database connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the character set to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $conn->error);
}

?>