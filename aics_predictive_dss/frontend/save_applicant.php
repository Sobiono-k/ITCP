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
    
    // FIXED: Changed 'birth_date' to 'dob' to match your form input name
    $birthDate      = !empty($_POST['dob']) ? $conn->real_escape_string($_POST['dob']) : '0000-00-00'; 
    
    $barangay       = isset($_POST['barangay']) ? $conn->real_escape_string($_POST['barangay']) : 'Not Specified';
    $medicalCause   = $conn->real_escape_string($_POST['medical_cause']);
    $assistanceType = isset($_POST['assistance_type']) ? $conn->real_escape_string($_POST['assistance_type']) : 'Not Specified';

    // 2. Updated SQL Query (Kept your structure)
    $sql = "INSERT INTO aics_sample_data (request_date, medical_cause, assistance_type, status, fname, mname, lname, birth_date, barangay) 
            VALUES ('$date', '$medicalCause', '$assistanceType', 'Pending', '$firstName', '$middleName', '$lastName', '$birthDate', '$barangay')";

    if ($conn->query($sql) === TRUE) {
        $newID = $conn->insert_id;
        
        // Update id_number format (e.g., QC-0001)
        $formattedID = "QC-" . $newID; // Changed from str_pad to match your DB screenshot style
        $conn->query("UPDATE aics_sample_data SET id_number = '$formattedID' WHERE id = $newID");

        header("Location: records.php?msg=success&new_id=" . $newID);
        exit();
    } else {
        die("Error: " . $conn->error);
    }
}
?>