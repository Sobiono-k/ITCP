<?php
// save_applicant.php
$conn = new mysqli("localhost", "root", "", "aics_dss");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Collect and Sanitize (Matching your NEW applicant form names)
    $date           = $conn->real_escape_string($_POST['date']);
    $firstName      = $conn->real_escape_string($_POST['fname']); // Matches name="fname"
    $lastName       = $conn->real_escape_string($_POST['lname']); // Matches name="lname"
    $medicalCause   = $conn->real_escape_string($_POST['medical_cause']);
    
    // This catches the "Type of Assistance Requested" radio buttons
    // Make sure you changed name="cause" to name="assistance_type" in your HTML!
    $assistanceType = isset($_POST['assistance_type']) ? $conn->real_escape_string($_POST['assistance_type']) : 'Not Specified';

    // 2. The SQL Query
    // Note: We don't insert 'id' because the database generates it automatically
    $sql = "INSERT INTO aics_sample_data (request_date, medical_cause, assistance_type, status) 
            VALUES ('$date', '$medicalCause', '$assistanceType', 'Pending')";

    if ($conn->query($sql) === TRUE) {
        // 3. Get the ID Number that was just created
        $newID = $conn->insert_id;
        
        // 4. Redirect with the new ID number in the URL
        header("Location: records.php?msg=success&new_id=" . $newID);
        exit();
    } else {
        die("Error: " . $conn->error);
    }
}
?>