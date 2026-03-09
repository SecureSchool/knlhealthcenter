<?php
// Database connection settings
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'macy');              // yung MySQL username mo
define('DB_PASSWORD', 'delaconcepcion');    // yung MySQL password mo
define('DB_NAME', 'cs310-cs3b-2025');       // tama na DB name

// Attempt to connect to the database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set default timezone
date_default_timezone_set('Asia/Manila');
?>
