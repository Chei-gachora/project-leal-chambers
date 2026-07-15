<?php

require_once 'send_notification_email.php';
sendUserNotifications($user_id);   // $user_id = owner of the notification


// sol_alerts.php - Automated SOL Risk Email Alerts
include 'connect.php';

// Configuration
$alert_days = [30, 15, 7, 3]; // Send alerts at these intervals
$from_email = "alerts@legalpro.com";
$from_name = "LegalPro SOL Alert System";

// Get cases with upcoming SOL
$query = "SELECT 
            case_number, 
            client_name, 
            sol_date, 
            DATEDIFF(sol_date, CURDATE()) as days_left,
            assigned_lawyer,
            lawyer_email
          FROM cases 
          WHERE sol_date IS NOT NULL 
            AND sol_date >= CURDATE() 
            AND DATEDIFF(sol_date, CURDATE()) <= 30
          ORDER BY sol_date ASC";

$result = $conn->query($query);

$alerts_sent = 0;

while ($case = $result->fetch_assoc()) {
    $days = (int)$case['days_left'];
    
    // Check if this day threshold should trigger alert
    if (in_array($days, $alert_days)) {
        
        $subject = "⚠️ SOL ALERT: {$case['case_number']} - Expires in {$days} days";
        
        $message = "
        <html>
        <head><title>SOL Alert</title></head>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #ef4444;'>Statute of Limitations Alert</h2>
            <p><strong>Case:</strong> {$case['case_number']}</p>
            <p><strong>Client:</strong> {$case['client_name']}</p>
            <p><strong>SOL Date:</strong> " . date('d M Y', strtotime($case['sol_date'])) . " <strong>({$days} days left)</strong></p>
            <hr>
            <p><strong>Action Required:</strong> Please review this case immediately to prevent loss of rights.</p>
            <br>
            <small>This is an automated alert from LegalPro System.</small>
        </body>
        </html>";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $from_name <$from_email>" . "\r\n";

        // Send to assigned lawyer
        if (!empty($case['lawyer_email'])) {
            mail($case['lawyer_email'], $subject, $message, $headers);
            $alerts_sent++;
        }

        // Optional: Also send to admin
        mail("chegeofficial02@gmail.com", $subject, $message, $headers);
    }
}

// Log the run
$log = date('Y-m-d H:i:s') . " | Alerts Sent: $alerts_sent\n";
file_put_contents('sol_alerts.log', $log, FILE_APPEND);

echo "SOL Alert Check Completed. $alerts_sent alerts sent.\n";

?>