<?php

// MySQL server details
$host = "localhost";
$username = "agronest_app";
$password = "BSE-2510233!";
$database = "agronest";

// Create a connection to MySQL
$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

// Check if the connection failed
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Set the character encoding
$conn->set_charset("utf8mb4");

?>