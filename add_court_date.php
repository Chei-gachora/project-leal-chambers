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
    echo json_encode(['success' => false, 'message' => 'You must be logged in to add court dates.']);
    exit;
}

$event_title = trim($_POST['event_title'] ?? '');
$location    = trim($_POST['court_location'] ?? '');
$event_date  = trim($_POST['event_date'] ?? '');
$event_time  = trim($_POST['event_time'] ?? '');

if (empty($event_title) || empty($location) || empty($event_date) || empty($event_time)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$formatted_date = date('Y-m-d', strtotime($event_date));
$formatted_time = date('H:i:s', strtotime($event_time));

try {
    $sql = "INSERT INTO court_dates (user_id, event_title, location, event_date, event_time) 
            VALUES (:user_id, :event_title, :location, :event_date, :event_time)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id'     => $user_id,
        ':event_title' => $event_title,
        ':location'    => $location,
        ':event_date'  => $formatted_date,
        ':event_time'  => $formatted_time
    ]);

    $mail_status = "Email sent successfully!";

    // Send notification email explicitly
    try {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once __DIR__ . '/PHPMailer/src/Exception.php';
            require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        }

      // 1. Fetch current user data with the correct database column fields
        $user_stmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();

        if ($user && !empty($user['email'])) {
            
            // Assemble the actual name properly from the table
            $display_name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
            if (empty($display_name)) {
                $display_name = "User";
            }

            $notifications = [];
            $notifications[] = "📅 <strong>New Court Event Added:</strong> " . htmlspecialchars($event_title);
            $notifications[] = "📍 <strong>Location:</strong> " . htmlspecialchars($location);
            $notifications[] = "📅 <strong>Date/Time:</strong> " . htmlspecialchars($formatted_date) . " at " . htmlspecialchars($formatted_time);

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
            $mail->Subject = '🔔 New Court Schedule Update - LegalPro';

            $body = "<h3>Hello " . htmlspecialchars($display_name) . ",</h3>";
            $body .= "<p>A new item has been added to your legal agenda:</p><ul>";
            foreach ($notifications as $line) {
                $body .= "<li>" . $line . "</li>";
            }
            $body .= "</ul><p><a href='http://localhost/wakili/dashboard.php'>Go to Dashboard</a></p>";

            $mail->Body = $body;
            $mail->send();
            
            // Log successful attempts
            file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Success: Sent to " . $user['email'] . "\n", FILE_APPEND);

        } else {
            $mail_status = "Skipped: Logged-in user has no valid email address in the database.";
            file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] " . $mail_status . "\n", FILE_APPEND);
        }
    } catch (Throwable $mailError) {
        $mail_status = "Failed: " . $mailError->getMessage();
        // Write the error out to a local text file
        file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Error: " . $mailError->getMessage() . "\n", FILE_APPEND);
    }

    // Return everything safely encapsulated within structured JSON properties
    echo json_encode([
        'success' => true, 
        'message' => 'Court date scheduled successfully! Email Status: ' . $mail_status
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
exit;
?>