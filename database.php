<?php
mysqli_report(MYSQLI_REPORT_OFF);
$host = "localhost";
$username = "root";
$password = "";
$database = "library_management";
$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    exit("Database connection failed. Start MySQL in Laragon and check database.php settings.");
}
mysqli_set_charset($conn, "utf8mb4");