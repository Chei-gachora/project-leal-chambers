<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}



// Include database configuration connection
$host = 'localhost';
$dbname = 'lawyers';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('<div class="alert alert-danger">Database Connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team | LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #0f172a; 
            color: #e2e8f0;
        }
        
        .sidebar { 
            background-color: rgba(30, 41, 55, 0.95); 
            backdrop-filter: blur(12px);
            min-height: 100vh; 
        }
        
        .glass {
            background: rgba(30, 41, 55, 0.65) !important;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .top-bar { 
            background: rgba(30, 41, 55, 0.85); 
            backdrop-filter: blur(12px); 
        }

        .tabs-nav {
            border-bottom: 1px solid #334155;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .tab-link {
            color: #94a3b8;
            text-decoration: none;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .tab-link:hover {
            color: #f8fafc;
        }

        .tab-link.active {
            color: #3b82f6 !important; 
            border-bottom-color: #3b82f6;
            font-weight: 600;
        }
        
        .member-card {
            transition: all 0.3s;
        }
        .member-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4) !important;
        }

        /* Modal styling matching clean dark components */
        .custom-modal {
            display: none; 
            position: fixed; 
            z-index: 10000; 
            left: 0; top: 0; width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.75);
            align-items: center;
            justify-content: center;
        }
        .custom-modal-content {
            background-color: #1e293b;
            padding: 30px; 
            border: 1px solid #475569;
            width: 90%;
            max-width: 500px; 
            border-radius: 12px;
            color: #f8fafc;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-4">
            <h4 class="mb-4"><i class="fas fa-gavel"></i> LegalPro</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link text-light"><i class="fas fa-home me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="calender.php" class="nav-link text-light"><i class="fas fa-calendar-alt me-2"></i> Calendar</a></li>
                <li class="nav-item"><a href="casess.php" class="nav-link text-light"><i class="fas fa-folder-open me-2"></i> Cases</a></li>
                <li class="nav-item"><a href="soltracker.php" class="nav-link text-light"><i class="fas fa-clock me-2"></i> SOL Tracker</a></li>
                <li class="nav-item"><a href="team.php" class="nav-link active"><i class="fas fa-users me-2"></i> Team</a></li>
                <li class="nav-item"><a href="documents.php" class="nav-link text-light"><i class="fas fa-file-alt me-2"></i> Documents</a></li>
                <li class="nav-item"><a href="Reportts.php" class="nav-link text-light"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
            </ul>
        </div>

        <!-- Main Content Workspace Area -->
        <div class="col-md-10 p-0">
            <!-- Top Dashboard Bar Component -->
            <div class="top-bar p-3 d-flex justify-content-between align-items-center border-bottom border-secondary">
                <h5>Team Collaboration</h5>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-primary" id="newDiscussionBtn">
                        <i class="fas fa-plus me-2"></i> New Discussion
                    </button>
                  
                </div>
            </div>

            <!-- Context Workspace Tabs Frame -->
            <div class="p-4">
                <div class="tabs-nav">
                    <a href="#" class="tab-link active" data-tab="members">Team Members</a>
                    <a href="#" class="tab-link" data-tab="discussions">Discussions</a>
                    <a href="#" class="tab-link" data-tab="departments">Departments</a>
                    <a href="#" class="tab-link" data-tab="forum">Open Forum</a>
                </div>
<div class="dynamic-panel mt-3" id="mainWorkspacePanel">
    <div class="row g-4">
        <?php
        // Start session to access logged-in user data if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            // Get the logged-in user ID from session
            $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

            if (!$user_id) {
                echo '<div class="col-12 text-center py-5"><p class="text-muted">Please log in to view team members.</p></div>';
            } else {
                // Filter users to only include those in the same department as the logged-in user
                $stmt = $pdo->prepare("
                    SELECT id, firstname, lastname, role, profile_pic 
                    FROM users 
                    WHERE status = 'active' 
                      AND department_id = (SELECT department_id FROM users WHERE id = :user_id LIMIT 1)
                    ORDER BY firstname ASC
                ");
                $stmt->execute(['user_id' => $user_id]);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($members) > 0):
                    foreach ($members as $member):
                        $full_name = htmlspecialchars($member['firstname'] . ' ' . $member['lastname']);
                        $role = htmlspecialchars($member['role'] ?? 'Lawyer');
                        $profile_pic = !empty($member['profile_pic']) ? htmlspecialchars($member['profile_pic']) : 'https://via.placeholder.com/120';
                ?>
                <div class="col-md-4 col-lg-3">
                    <div class="glass member-card p-4 text-center">
                        <img src="<?= $profile_pic ?>" class="rounded-circle mb-3" alt="<?= $full_name ?>" style="width: 100px; height: 100px; object-fit: cover; border: 2px solid #475569;">
                        <h3><?= $full_name ?></h3>
                        <p class="text-muted small"><?= $role ?></p>
                        <button class="view-profile-btn btn btn-sm btn-outline-primary mt-2" data-id="<?= $member['id'] ?>">View Profile</button>
                    </div>
                </div>
                <?php
                    endforeach;
                else:
                    echo '<div class="col-12 text-center py-5"><p class="text-muted">No team members found in your department.</p></div>';
                endif;
            }
        } catch (Exception $e) {
            echo '<div class="col-12 text-center py-5 text-danger">Error loading workspace contents.</div>';
        }
        ?>
    </div>
</div>

<!-- Modal Component: Add New Discussion -->
<div id="discussionModal" class="custom-modal" style="display: none;">
    <div class="custom-modal-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Start a New Discussion</h3>
            <span class="close-modal fs-3 cursor-pointer" style="cursor: pointer;">&times;</span>
        </div>
        <form id="newDiscussionForm">
            <div class="mb-3">
                <label for="discTitle" class="form-label text-secondary">Discussion Topic / Title</label>
                <input type="text" id="discTitle" name="title" class="form-control bg-dark text-light border-secondary" placeholder="e.g., Updates on Case #4021" required>
            </div>
            <div class="mb-3">
                <label for="discMessage" class="form-label text-secondary">Message</label>
                <textarea id="discMessage" name="message" rows="4" class="form-control bg-dark text-light border-secondary" placeholder="Type your initial message here..." required></textarea>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-secondary close-modal me-2">Cancel</button>
                <button type="submit" class="btn btn-primary">Post Discussion</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==========================================
// 1. Unified Asynchronous Workspace Controller
// ==========================================
document.querySelectorAll('.tab-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();

        document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const targetTab = this.getAttribute('data-tab');
        const panel = document.getElementById('mainWorkspacePanel');
        
        // Show universal loading skeleton spinner
        panel.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-2">Loading content...</p></div>';

        if (targetTab === 'departments') {
            fetch('fetch_departments.php')
                .then(r => { if(!r.ok) throw new Error(); return r.text(); })
                .then(html => panel.innerHTML = html)
                .catch(() => panel.innerHTML = '<p class="text-danger p-4">Error loading departments list.</p>');
        } else if (targetTab === 'discussions') {
            fetch('fetch_discussions.php')
                .then(r => { if(!r.ok) throw new Error(); return r.text(); })
                .then(html => panel.innerHTML = html)
                .catch(() => panel.innerHTML = '<p class="text-danger p-4">Error loading discussion boards.</p>');
        } else if (targetTab === 'forum') {
            // INTEGRATED: Asynchronously fetch live Open Forum data
            fetch('fetch_forum.php')
                .then(r => { if(!r.ok) throw new Error(); return r.text(); })
                .then(html => panel.innerHTML = html)
                .catch(() => panel.innerHTML = '<p class="text-danger p-4">Error loading the open forum workspace.</p>');
        } else if (targetTab === 'members') {
            // Hot reload local window page structure block for default team view
            window.location.reload();
        } else {
            panel.innerHTML = `<p class="text-muted text-center py-5">The ${targetTab} panel integration is ready.</p>`;
        }
    });
});

// ==========================================
// 2. New Discussion Modal UI Interactivity Logic
// ==========================================
const modal = document.getElementById("discussionModal");
const btn = document.getElementById("newDiscussionBtn");
const closeTriggers = document.querySelectorAll(".close-modal");

if (btn) btn.onclick = () => modal.style.display = "flex";
closeTriggers.forEach(trigger => {
    trigger.onclick = () => modal.style.display = "none";
});
window.onclick = (e) => { if (e.target === modal) modal.style.display = "none"; };

// Intercept Add Discussion Form via AJAX Engine
document.getElementById('newDiscussionForm').onsubmit = function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    fetch('save_discussion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modal.style.display = "none";
            this.reset();
            // Refresh Discussions tab
            const discTab = document.querySelector('[data-tab="discussions"]');
            if (discTab) discTab.click();
            else alert('Discussion created successfully!');
        } else {
            alert(data.message || 'Failed to create discussion');
        }
    })
    .catch(() => alert('Failed to connect to server.'));
};

// Delete Discussion Handler
document.getElementById('mainWorkspacePanel').addEventListener('click', function(e) {
    if (e.target.classList.contains('delete-discussion')) {
        if (confirm('Delete this discussion?')) {
            const id = e.target.getAttribute('data-id');
            fetch('fetch_discussions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_discussion&id=${id}`
            })
            .then(() => {
                // Refresh discussions tab
                document.querySelector('[data-tab="discussions"]').click();
            });
        }
    }
});
// ==========================================
// 3. View Profile Interaction Logic Bindings
// ==========================================
document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('view-profile-btn')) {
        const userId = e.target.getAttribute('data-id');
        fetch(`view_profile.php?id=${userId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) { showProfileModal(data.profile); } 
                else { alert(data.message || 'Failed to load profiles'); }
            })
            .catch(() => alert('Error loading profile information.'));
    }
});

function showProfileModal(profile) {
    const existingModal = document.getElementById('profile-modal');
    if (existingModal) existingModal.remove();

    const modalHTML = `
        <div id="profile-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:10000; display:flex; align-items:center; justify-content:center;">
            <div style="background:#1e2937; padding:30px; border-radius:16px; max-width:480px; color:white; box-shadow:0 10px 30px rgba(0,0,0,0.5); width:90%;">
                <div style="text-align:center; margin-bottom:20px;">
                    <img src="${profile.profile_pic || 'https://via.placeholder.com/120'}" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid #3b82f6;">
                    <h2 style="margin:15px 0 5px 0;">${profile.full_name}</h2>
                    <p style="color:#60a5fa; margin:0;">${profile.role || 'Lawyer'}</p>
                </div>
                <div style="line-height:1.8;">
                    <p><strong>Email:</strong> ${profile.email}</p>
                    <p><strong>Phone:</strong> ${profile.phone || 'Not provided'}</p>
                    <p><strong>Status:</strong> <span style="color:#34d399;">${profile.status}</span></p>
                </div>
                <div style="text-align:center; margin-top:30px;">
                    <button id="close-profile-btn" class="btn btn-primary px-5">Close Profile</button>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('close-profile-btn').onclick = () => document.getElementById('profile-modal').remove();
}

// ==========================================
// 4. Dynamic Event Delegation Context Router
// ==========================================
document.getElementById('mainWorkspacePanel').addEventListener('click', function(e) {
    const manageBtn = e.target.closest('.manage-practice-btn');
    const backBtn = e.target.closest('.back-to-depts');

    // Handle "Manage Practice" click routing
    if (manageBtn) {
        const deptId = manageBtn.getAttribute('data-id');
        const panel = document.getElementById('mainWorkspacePanel');
        
        panel.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';

        fetch(`manage_practice.php?id=${deptId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    panel.innerHTML = data.html;
                } else {
                    panel.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(() => {
                panel.innerHTML = '<div class="alert alert-danger">Error initializing the practice management workspace.</div>';
            });
    }

    // Handle back navigational elements inside managed groups
    if (backBtn) {
        const deptTab = document.querySelector('[data-tab="departments"]');
        if (deptTab) deptTab.click();
    }
});

// INTEGRATED: Dynamic Async Interceptor for Live Forum Comment Submissions
document.getElementById('mainWorkspacePanel').addEventListener('submit', function(e) {
    if (e.target && e.target.classList.contains('forum-reply-form')) {
        e.preventDefault();
        
        const form = e.target;
        const discussionId = form.getAttribute('data-id');
        const inputField = form.querySelector('.reply-input-field');
        const replyText = inputField.value.trim();
        
        if (!replyText) return;

        let formData = new FormData();
        formData.append('action', 'add_reply');
        formData.append('discussion_id', discussionId);
        formData.append('reply_message', replyText);

        // === CHANGE THIS LINE ===
        fetch('save_forum_reply.php', {   // ← Use a proper endpoint
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelector('[data-tab="forum"]').click(); // Refresh forum
                inputField.value = '';
            } else {
                alert('Could not submit reply: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => {
            alert('Failed to connect to server. Please check your connection.');
        });
    }
});
</script>
</body>
</html>