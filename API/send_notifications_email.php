<?php
// send_notification_email.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database link is accessible
require_once __DIR__ . '/connect.php'; 

// Manual PHPMailer inclusion matching your local workspace folder path
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendUserNotifications($user_id) {
    global $pdo; // <--- Target the correct PDO connection instance directly

    if (empty($user_id) || !$pdo) {
        throw new Exception("Active PDO database instance missing.");
    }

    // 1. Fetch User details safely using the correct structural columns (firstname, lastname)
    $stmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) {
        throw new Exception("Recipient profile email address not found.");
    }

    // Assemble the actual name properly from the table fields
    $display_name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    if (empty($display_name)) {
        $display_name = "User";
    }

    $notifications = [];

    // 2. Fetch Court Dates using PDO
    try {
        $stmt1 = $pdo->prepare("
            SELECT event_title, event_date, event_time, location 
            FROM court_dates 
            WHERE user_id = ? AND event_date >= CURDATE()
            ORDER BY event_date ASC LIMIT 3
        ");
        $stmt1->execute([$user_id]);
        while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
            $row['title'] = "📅 Court Event: " . $row['event_title'];
            $row['message'] = $row['event_date'] . " at " . $row['event_time'] . " (" . $row['location'] . ")";
            $notifications[] = $row;
        }
    } catch (PDOException $e) {
        throw new Exception("Court Dates query failed: " . $e->getMessage());
    }

    // 3. Fetch New Reminders using PDO
    try {
        $stmt2 = $pdo->prepare("
            SELECT reminder_text, reminder_date, priority 
            FROM reminders 
            WHERE user_id = ? AND reminder_date >= CURDATE()
            ORDER BY reminder_date ASC LIMIT 3
        ");
        $stmt2->execute([$user_id]);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $row['title'] = "⏰ Reminder (" . ucfirst($row['priority'] ?? 'normal') . ")";
            $row['message'] = $row['reminder_text'] . " - Due: " . $row['reminder_date'];
            $notifications[] = $row;
        }
    } catch (PDOException $e) {
        throw new Exception("Reminders query failed: " . $e->getMessage());
    }

    // Return if there are no new notifications to process
    if (empty($notifications)) {
        return true;
    }

    // 4. Dispatch Email Package via PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'chegeofficial02@gmail.com';
        $mail->Password   = 'cyjykxbwdaqofpmx'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Bypass local SSL validation warning layers safely
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
        $mail->Subject = '🔔 Dashboard Schedule Updates - LegalPro';

        $body = "<h3>Hello " . htmlspecialchars($display_name) . ",</h3>";
        $body .= "<p>Here is your current dashboard agenda summary:</p><ul>";

        foreach ($notifications as $notif) {
            $body .= "<li><strong>" . htmlspecialchars($notif['title']) . "</strong><br>" 
                  . htmlspecialchars($notif['message']) . "</li><br>";
        }
        $body .= "</ul><p><a href='http://localhost/wakili/dashboard.php'>Go to Dashboard</a></p>";

        $mail->Body = $body;
        $mail->send();
        return true;

    } catch (Exception $e) {
        throw new Exception("PHPMailer error: " . $mail->ErrorInfo);
    }
}
?>