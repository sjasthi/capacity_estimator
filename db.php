<?php
function db() {
    $conn = new mysqli("localhost", "root", "", "capacity_estimator");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>