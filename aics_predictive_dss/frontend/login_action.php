<?php
session_start();

// 1. Establish Database Connection
// Replace these with your actual database credentials
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "aics_dss"; 

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // 2. Use Prepared Statements for security
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // 3. SET THE SESSION DATA
        // This is the critical part that the Sidebar reads
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = $row['role']; // MUST be 'Admin' or 'Staff' in your database

        // 4. Redirect based on role
        if ($_SESSION['role'] === 'Admin') {
            header("Location: dashboard.php");
        } else {
            header("Location: new_applicant.php");
        }
        exit();
    } else {
        // Redirect back to login with error if credentials fail
        header("Location: login.php?error=invalid");
        exit();
    }
}
?>