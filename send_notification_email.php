<?php
// send_notification_email.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Since this file sits in the root folder, look directly in the current directory
require_once __DIR__ . '/connect.php'; 

// Manual PHPMailer inclusion matching your root folder path
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendUserNotifications($user_id) {
    global $conn;

    if (empty($user_id) || !$conn) {
        throw new Exception("Database connection missing or invalid User ID.");
    }

    // 1. Get user email and name safely
    $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['email'])) {
        throw new Exception("User data or recipient email address not found.");
    }

    $notifications = [];

    // 2. Fetch Discussion Replies using Prepared Statements
    $stmt1 = $conn->prepare("
        SELECT dr.id, d.title as discussion_title, dr.reply, dr.created_at
        FROM discussion_replies dr
        JOIN discussions d ON dr.discussion_id = d.id
        WHERE dr.user_id = ? OR d.user_id = ?
        ORDER BY dr.created_at DESC LIMIT 5
    ");
    $stmt1->bind_param("ii", $user_id, $user_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while ($row = $result1->fetch_assoc()) {
        $row['title'] = "💬 Reply on: " . $row['discussion_title'];
        $row['message'] = substr($row['reply'] ?? '', 0, 100) . "...";
        $notifications[] = $row;
    }
    $stmt1->close();

    // 3. Fetch Court Dates using Prepared Statements
    $stmt2 = $conn->prepare("
        SELECT event_title, event_date, event_time, location 
        FROM court_dates 
        WHERE user_id = ? AND event_date >= CURDATE()
        ORDER BY event_date ASC LIMIT 3
    ");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $row['title'] = "📅 Court: " . $row['event_title'];
        $row['message'] = $row['event_date'] . " at " . $row['event_time'] . " - " . $row['location'];
        $notifications[] = $row;
    }
    $stmt2->close();

    // 4. Fetch New Reminders using Prepared Statements
    $stmt3 = $conn->prepare("
        SELECT reminder_text, reminder_date, priority 
        FROM reminders 
        WHERE user_id = ? AND reminder_date >= CURDATE()
        ORDER BY reminder_date ASC LIMIT 3
    ");
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    while ($row = $result3->fetch_assoc()) {
        $row['title'] = "⏰ Reminder (" . ucfirst($row['priority'] ?? 'normal') . ")";
        $row['message'] = $row['reminder_text'] . " - Due: " . $row['reminder_date'];
        $notifications[] = $row;
    }
    $stmt3->close();

    // If there's nothing to report, return true gracefully
    if (empty($notifications)) {
        return true;
    }

    // 5. Send the compiled notification list via PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'chegeofficial02@gmail.com';
        $mail->Password   = 'cyjykxbwdaqofpmx'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Bypass Local SSL Verification (Prevents Localhost Connection Failures)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom('chegeofficial02@gmail.com', 'LegalPro');
        $mail->addAddress($user['email'], $user['full_name'] ?? 'User');

        $mail->isHTML(true);
        $mail->Subject = '🔔 New Notifications - LegalPro';

        $body = "<h3>Hello " . htmlspecialchars($user['full_name'] ?? 'User') . ",</h3>";
        $body .= "<p>You have new notification updates on your dashboard:</p><ul>";

        foreach ($notifications as $notif) {
            $body .= "<li><strong>" . htmlspecialchars($notif['title']) . "</strong><br>" 
                  . htmlspecialchars($notif['message']) . "</li><br>";
        }
        $body .= "</ul><p><a href='http://localhost/wakili/dashboard.php'>View all in Dashboard</a></p>";

        $mail->Body = $body;
        $mail->send();
        return true;

    } catch (Exception $e) {
        throw new Exception("PHPMailer Failed: " . $mail->ErrorInfo);
    }
}
?>