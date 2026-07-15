<?php
// 1. Include your established database connection
include 'connect.php';

// 2. Check if the form's "Save Event" button was clicked via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Capture data from your popup modal input field text fields
    $title       = $_POST['event_title'];  // e.g. "Hearing - Civil Suit"
    $date        = $_POST['event_date'];   // e.g. "2026-06-11"
    $time        = $_POST['event_time'];   // e.g. "09:00:00"
    $case_client = $_POST['case_client'];  // Selected dropdown value
    $type        = $_POST['event_type'];   // Dropdown context: Court/SOL/Meeting

    // 3. Prepare the secure SQL Statement layout
    $sql = "INSERT INTO events (event_title, event_date, event_time, case_client, event_type) VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Bind parameters safely ("sssss" stands for 5 string arguments)
        $stmt->bind_param("sssss", $title, $date, $time, $case_client, $type);
        
        // Execute and evaluate process completion state
        if ($stmt->execute()) {
            // Redirect smoothly right back to the calendar workspace page
            header("Location: calender.php?status=success");
            exit();
        } else {
            echo "❌ Failed to save event entry: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}
?>