<?php
// database.php
function get_connection() {
    $servername = "localhost";
    $username = "root";   // your MySQL username
    $password = "";       // your MySQL password
    $dbname = "aics_dss"; // database name

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}
?>