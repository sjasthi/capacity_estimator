<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function db() {
    $conn = new mysqli("localhost", "root", "", "capacity_estimator");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>