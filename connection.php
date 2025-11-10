<?php
$servername = "localhost";   // if using XAMPP or WAMP
$username   = "root";        // default MySQL username
$password   = "";            // default MySQL password (empty on XAMPP)
$dbname     = "finance_tracker"; // name of your DB

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
