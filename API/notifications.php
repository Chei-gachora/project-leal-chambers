<?php
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

include_once '../connect.php';

try {
    if (!$conn) throw new Exception("Database connection failed");

    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 1);
    $notifications = [];

    // 1. Reminders
    $result = $conn->query("SELECT id, reminder_text as title, reminder_date, priority, 'reminder' as type 
                            FROM reminders WHERE status = 'Pending' AND user_id = $user_id ORDER BY id DESC LIMIT 10");
    while ($row = $result->fetch_assoc()) {
        $row['message'] = "Reminder: " . htmlspecialchars($row['title']);
        $row['link'] = "#";
        $notifications[] = $row;
    }

    // 2. SOL
    $result = $conn->query("SELECT id, case_no, sol_expiry_date, 
                            DATEDIFF(sol_expiry_date, CURDATE()) as days_left, 'sol' as type 
                            FROM cases WHERE sol_expiry_date IS NOT NULL AND user_id = $user_id 
                            ORDER BY sol_expiry_date ASC LIMIT 10");
    while ($row = $result->fetch_assoc()) {
        $row['title'] = "SOL Expiry - " . $row['case_no'];
        $row['message'] = "⚠️ Expires in " . $row['days_left'] . " days";
        $row['link'] = "#";
        $notifications[] = $row;
    }

    // 3. Discussion Replies
    $result = $conn->query("
        SELECT dr.id, d.title as discussion_title, dr.reply, dr.created_at, 'discussion' as type
        FROM discussion_replies dr
        JOIN discussions d ON dr.discussion_id = d.id
        WHERE dr.user_id = $user_id OR d.user_id = $user_id
        ORDER BY dr.created_at DESC LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        $row['title'] = "💬 Reply on: " . htmlspecialchars($row['discussion_title']);
        $row['message'] = htmlspecialchars(substr($row['reply'] ?? '', 0, 85)) . "...";
        $row['link'] = "team.php";
        $notifications[] = $row;
    }

    // 4. Recent Court Dates (NEW - Added as requested)
    $result = $conn->query("
        SELECT id, event_title, event_date, event_time, location, 'court' as type
        FROM court_dates 
        WHERE user_id = $user_id 
        ORDER BY event_date ASC, event_time ASC LIMIT 3
    ");
    while ($row = $result->fetch_assoc()) {
        $row['title'] = "📅 Court Date: " . htmlspecialchars($row['event_title']);
        $row['message'] = "On " . $row['event_date'] . " at " . $row['event_time'] . " - " . htmlspecialchars($row['location']);
        $row['link'] = "#";
        $notifications[] = $row;
    }

    // --- NEW: BATCH EMAIL LOGIC (triggers after every 8 new notifications) ---
    $current_count = count($notifications);
    $email_status = "Skipped (Threshold of 8 new items not reached)";
    
    // Initialize session baseline if this is the first execution
    if (!isset($_SESSION['last_emailed_count_checkpoint'])) {
        $_SESSION['last_emailed_count_checkpoint'] = $current_count;
    }

    $new_items_accumulated = $current_count - $_SESSION['last_emailed_count_checkpoint'];

    // Trigger SMTP dispatch ONLY when 8 or more new items arrive
    if ($new_items_accumulated >= 8) {
        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                require_once __DIR__ . '/PHPMailer/src/Exception.php';
                require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
                require_once __DIR__ . '/PHPMailer/src/SMTP.php';
            }

            // Fetch recipient details securely matching table schemas
            $user_stmt = $conn->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_res = $user_stmt->get_result();
            $user = $user_res->fetch_assoc();

            if ($user && !empty($user['email'])) {
                $display_name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
                if (empty($display_name)) {
                    $display_name = "User";
                }

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->SMTPDebug = 0; 
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'chegeofficial02@gmail.com';
                $mail->Password   = 'cyjykxbwdaqofpmx'; 
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                $mail->setFrom('chegeofficial02@gmail.com', 'LegalPro');
                $mail->addAddress($user['email'], $display_name);

                $mail->isHTML(true);
                $mail->Subject = '🔔 Summary Update Alert (' . $new_items_accumulated . ' New Updates) - LegalPro';

                $body = "<h3>Hello " . htmlspecialchars($display_name) . ",</h3>";
                $body .= "<p>Your dashboard has accumulated <strong>" . $new_items_accumulated . "</strong> new notifications since your last email alert.</p>";
                $body .= "<p>Here is your summary list:</p><hr><ul>";
                foreach ($notifications as $notif) {
                    $body .= "<li><strong>" . $notif['title'] . "</strong>: " . $notif['message'] . "</li><br>";
                }
                $body .= "</ul><p><a href='http://localhost/wakili/dashboard.php'>Go to Dashboard</a></p>";

                $mail->Body = $body;
                $mail->send();

                // Advanced step-up calculation: update checkpoint baseline to stop loops
                $_SESSION['last_emailed_count_checkpoint'] = $current_count;
                $email_status = "Dispatched (Threshold met with " . $new_items_accumulated . " new items)";
                
                file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Success (Batch): Notification email sent to " . $user['email'] . " (" . $new_items_accumulated . " new items)\n", FILE_APPEND);
            }
        } catch (Throwable $mailError) {
            $email_status = "Failed: " . $mailError->getMessage();
            file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Error (Batch): " . $mailError->getMessage() . "\n", FILE_APPEND);
        }
    }

    echo json_encode([
        'success' => true,
        'count' => $current_count,
        'notifications' => $notifications,
        'email_status' => $email_status
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'count' => 0,
        'notifications' => []
    ]);
}
?>