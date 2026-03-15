<?php
// save_applicant.php
$conn = new mysqli("localhost", "root", "", "aics_dss");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect from form - Ensure these match your <input name="...">
    $date = $_POST['date'];
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $medicalCause = $_POST['medical_cause'];
    $assistanceType = $_POST['assistance_type'];

    // This matches your CREATE TABLE save_applicants
    $sql = "INSERT INTO save_applicants (request_date, first_name, last_name, medical_cause, assistance_type) 
            VALUES ('$date', '$firstName', '$lastName', '$medicalCause', '$assistanceType')";

    if ($conn->query($sql) === TRUE) {
        header("Location: dashboard.php?success=1");
        exit();
    } else {
        die("Error: " . $conn->error);
    }
}
?>