<?php
// fetch_forum.php - Final Working Version
$host = 'localhost';
$db   = 'lawyers';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $department_id = null;

    if ($user_id) {
        $deptStmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $department_id = $deptStmt->fetchColumn();
    }

    // ====================== NOTIFICATION EMAIL FUNCTION ======================
    function sendUserNotifications($pdo, $user_id) {
        if (!$user_id) return false;

        // Get user email
        $stmt = $pdo->prepare("SELECT email, CONCAT(COALESCE(firstname,''), ' ', COALESCE(lastname,'')) as full_name 
                               FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['email'])) return false;

        // Get latest notifications
        $notifications = [];

        // Discussion Replies
        $stmt = $pdo->prepare("
            SELECT dr.id, d.title as discussion_title, dr.reply, dr.created_at
            FROM discussion_replies dr
            JOIN discussions d ON dr.discussion_id = d.id
            WHERE (dr.user_id = ? OR d.user_id = ?)
            ORDER BY dr.created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id, $user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($notifications)) return true;

        // Send Email
        require_once '../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'chegeofficial02@gmail.com';
            $mail->Password   = 'cyjykxbwdaqofpmx';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('chegeofficial02@gmail.com', 'LegalPro');
            $mail->addAddress($user['email'], $user['full_name']);

            $mail->isHTML(true);
            $mail->Subject = '🔔 New Notifications - LegalPro';

            $body = "<h3>Hello " . htmlspecialchars($user['full_name'] ?? 'User') . ",</h3>";
            $body .= "<p>You have new notifications:</p><ul>";

            foreach ($notifications as $notif) {
                $body .= "<li><strong>" . htmlspecialchars($notif['discussion_title']) . "</strong><br>" 
                          . htmlspecialchars(substr($notif['reply'], 0, 120)) . "...</li>";
            }
            $body .= "</ul><p><a href='http://localhost/wakili/dashboard.php'>View in Dashboard</a></p>";

            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</li>'], "\n", $body));

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
            return false;
        }
    }
    // =====================================================================

    // AJAX Handlers
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        if (isset($_POST['action']) && $_POST['action'] === 'add_reply') {
            $discussion_id = intval($_POST['discussion_id'] ?? 0);
            $message = trim($_POST['reply_message'] ?? '');
            $user_id = $_SESSION['user_id'] ?? 1;

            if ($discussion_id > 0 && !empty($message)) {
                $stmt = $pdo->prepare("INSERT INTO discussion_replies (discussion_id, user_id, reply, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$discussion_id, $user_id, $message]);
                
                // 🔥 SEND EMAIL NOTIFICATION AFTER EVERY NEW REPLY
                sendUserNotifications($pdo, $user_id);

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid input']);
            }
            exit;
        }
    }

    // Fetch Topics - MODIFIED: Filter by user's department
    $topics = [];
    if ($department_id) {
        $topics = $pdo->prepare("
            SELECT id, title, message AS body_text, 'System Counsel' as author, 'https://via.placeholder.com/45' as profile_pic
            FROM discussions 
            WHERE department_id = ?
            ORDER BY id DESC
        ");
        $topics->execute([$department_id]);
        $topics = $topics->fetchAll(PDO::FETCH_ASSOC);
    }

    echo '<div class="row g-4 p-2">';
    if (empty($topics)) {
        echo '<div class="col-12 text-center py-5 text-muted">No discussions yet in your department.</div>';
    } else {
        foreach ($topics as $topic) {
            $topic_id = $topic['id'];
            
            // Safe replies with user names
            $replies = $pdo->prepare("
                SELECT r.*, 
                       COALESCE(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')), 'User') as responder
                FROM discussion_replies r 
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.discussion_id = ? 
                ORDER BY r.created_at ASC
            ");
            $replies->execute([$topic_id]);
            $reply_list = $replies->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="col-12">
                <div class="glass p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($topic['profile_pic']) ?>" class="rounded-circle" style="width:45px;height:45px;object-fit:cover;">
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($topic['title']) ?></h5>
                                <small class="text-muted">by <?= htmlspecialchars($topic['author']) ?></small>
                            </div>
                        </div>
                        <span class="badge bg-primary"><?= count($reply_list) ?> Replies</span>
                    </div>

                    <div class="p-3 bg-dark rounded mb-4 text-light">
                        <?= nl2br(htmlspecialchars($topic['body_text'] ?? '')) ?>
                    </div>

                    <h6 class="text-secondary mb-3">Thread Conversation</h6>
                    <div class="mb-3" style="max-height:280px; overflow-y:auto;">
                        <?php if (empty($reply_list)): ?>
                            <p class="text-muted fst-italic">No replies yet. Be the first!</p>
                        <?php else: foreach ($reply_list as $r): ?>
                            <div class="d-flex gap-3 mb-3">
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($r['responder']) ?></strong>
                                    <small class="text-muted ms-2"><?= date('M d, H:i', strtotime($r['created_at'])) ?></small>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($r['reply'] ?? '')) ?></p>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <form class="forum-reply-form" data-id="<?= $topic_id ?>">
                        <div class="input-group">
                            <input type="text" class="form-control bg-dark text-light reply-input-field" placeholder="Type your reply..." required>
                            <button class="btn btn-primary" type="submit">Send</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }
    }
    echo '</div>';

} catch (PDOException $e) {
    echo '<div class="alert alert-danger m-4">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>