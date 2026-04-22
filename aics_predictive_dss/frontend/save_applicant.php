<?php
// save_applicant.php

$conn = new mysqli("localhost", "root", "", "aics_dss");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Collect and Sanitize
    $date           = $conn->real_escape_string($_POST['date']);
    $firstName      = $conn->real_escape_string($_POST['fname']);
    $middleName     = isset($_POST['mname']) ? $conn->real_escape_string($_POST['mname']) : '';
    $lastName       = $conn->real_escape_string($_POST['lname']);
    $birthDate      = $conn->real_escape_string($_POST['birth_date']); // Added birth date
    $barangay       = isset($_POST['barangay']) ? $conn->real_escape_string($_POST['barangay']) : 'Not Specified';
    $medicalCause   = $conn->real_escape_string($_POST['medical_cause']);
    $assistanceType = isset($_POST['assistance_type']) ? $conn->real_escape_string($_POST['assistance_type']) : 'Not Specified';

    // 2. Updated SQL Query (Included birth_date, kept structure)
    $sql = "INSERT INTO aics_sample_data (request_date, medical_cause, assistance_type, status, fname, mname, lname, birth_date, barangay) 
            VALUES ('$date', '$medicalCause', '$assistanceType', 'Pending', '$firstName', '$middleName', '$lastName', '$birthDate', '$barangay')";

    if ($conn->query($sql) === TRUE) {
        $newID = $conn->insert_id;
        
        // Update id_number format (e.g., QC-0001)
        $formattedID = "QC-" . str_pad($newID, 4, '0', STR_PAD_LEFT);
        $conn->query("UPDATE aics_sample_data SET id_number = '$formattedID' WHERE id = $newID");

        header("Location: records.php?msg=success&new_id=" . $newID);
        exit();
    } else {
        die("Error: " . $conn->error);
    }
}
?>