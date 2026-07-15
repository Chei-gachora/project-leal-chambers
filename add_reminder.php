<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if ($user_id === null) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to add reminders.']);
    exit;
}

$reminder_text = trim($_POST['reminder_text'] ?? '');
$reminder_date = trim($_POST['reminder_date'] ?? '');
$priority      = trim($_POST['priority'] ?? 'normal');

if (empty($reminder_text) || empty($reminder_date)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$formatted_date = date('Y-m-d', strtotime($reminder_date));

try {
    // 1. Insert the reminder record into the database
    $sql = "INSERT INTO reminders (user_id, reminder_text, reminder_date, priority) 
            VALUES (:user_id, :reminder_text, :reminder_date, :priority)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id'       => $user_id,
        ':reminder_text' => $reminder_text,
        ':reminder_date' => $formatted_date,
        ':priority'      => $priority
    ]);

    $mail_status = "Email sent successfully!";

    // 2. Send notification email explicitly bypassing external include hooks
    try {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once __DIR__ . '/PHPMailer/src/Exception.php';
            require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        }

        // Fetch user data with correct structural matching columns
        $user_stmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();

        if ($user && !empty($user['email'])) {
            
            // Assemble full profile display name variables securely
            $display_name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
            if (empty($display_name)) {
                $display_name = "User";
            }

            $notifications = [];
            $notifications[] = "⏰ <strong>New Reminder Set:</strong> " . htmlspecialchars($reminder_text);
            $notifications[] = "📅 <strong>Due Date:</strong> " . htmlspecialchars($formatted_date);
            $notifications[] = "⚡ <strong>Priority Level:</strong> " . htmlspecialchars(ucfirst($priority));

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
            $mail->Subject = '🔔 New Dashboard Reminder - LegalPro';

            $body = "<h3>Hello " . htmlspecialchars($display_name) . ",</h3>";
            $body .= "<p>An item was added to your task agenda updates:</p><ul>";
            foreach ($notifications as $line) {
                $body .= "<li>" . $line . "</li>";
            }
            $body .= "</ul><p><a href='http://localhost/wakili/dashboard.php'>Go to Dashboard</a></p>";

            $mail->Body = $body;
            $mail->send();
            
            // Log successful attempts to your shared tracking file
            file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Success (Reminder): Sent to " . $user['email'] . "\n", FILE_APPEND);

        } else {
            $mail_status = "Skipped: Logged-in user has no valid email address in the database.";
            file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] " . $mail_status . "\n", FILE_APPEND);
        }
    } catch (Throwable $mailError) {
        $mail_status = "Failed: " . $mailError->getMessage();
        file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Error (Reminder): " . $mailError->getMessage() . "\n", FILE_APPEND);
    }

    // Return everything safely encapsulated within structured JSON properties
    echo json_encode([
        'success' => true, 
        'message' => 'Reminder scheduled successfully! Email Status: ' . $mail_status
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
exit;
?>