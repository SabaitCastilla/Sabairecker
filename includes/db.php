<?php
// Database configuration
// Replace these with your actual database credentials
define('DB_HOST', 'localhost');         // Database host (e.g., 'localhost' or IP address)
define('DB_USER', 'your_db_username');  // Database username
define('DB_PASS', 'your_db_password');  // Database password
define('DB_NAME', 'php_pos_db');        // Database name

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn === false) {
    // If connection fails, terminate the script and display an error message
    // mysqli_connect_errno() returns the error code from the last connection attempt
    // mysqli_connect_error() returns the error description from the last connection attempt
    die("ERROR: Could not connect to the database. " . mysqli_connect_errno() . ": " . mysqli_connect_error());
}

// Optional: Set character set to utf8 (recommended for internationalization)
if (!mysqli_set_charset($conn, "utf8")) {
    // This is unlikely to fail with a standard MySQL setup but good to check
    error_log("Error loading character set utf8: " . mysqli_error($conn));
}

// The connection object $conn is now established and can be used by other PHP scripts
// that include this file. For example:
// include 'includes/db.php';
// $result = mysqli_query($conn, "SELECT * FROM some_table");

// It's generally not recommended to output success messages directly in a db connection script
// as it can interfere with other output, especially if this file is included in multiple places
// or before HTTP headers are sent.
// For development, you might temporarily uncomment the line below to verify connection:
// echo "Database connected successfully. Host info: " . mysqli_get_host_info($conn);
?>
