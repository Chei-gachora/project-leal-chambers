<?php
// Database configuration matching your local setup
$host = 'localhost';
$db   = 'lawyers'; 
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $dept_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // 1. Fetch Department Details safely from the database
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$dept_id]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dept) {
        echo json_encode(['success' => false, 'message' => 'Department workspace profile not found.']);
        exit;
    }

    // 2. Dynamic Fetch: Select users whose department_id matches this section
    $stmtMembers = $pdo->prepare("SELECT id, firstname, lastname, role, email, profile_pic FROM users WHERE department_id = ? AND status = 'active' ORDER BY firstname ASC");
    $stmtMembers->execute([$dept_id]);
    $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

    // Buffer the HTML return structure 
    ob_start();
    ?>
    
    <!-- Workspace Context Header Component -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <span class="text-primary text-uppercase font-monospace small" style="letter-spacing: 1px;">Practice Management Workspace</span>
            <h2 class="text-white mb-0 mt-1"><?php echo htmlspecialchars($dept['name']); ?></h2>
        </div>
        <button class="btn btn-sm btn-outline-light back-to-depts px-3">
            <i class="fas fa-arrow-left me-2"></i>Back to Departments
        </button>
    </div>

    <div class="row g-4">
        <!-- Practice Description Sidebar -->
        <div class="col-md-4">
            <div class="glass p-4 h-100" style="background: rgba(30, 41, 55, 0.45); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px;">
                <h5 class="text-white mb-3"><i class="fas fa-align-left text-primary me-2"></i> Practice Description</h5>
                
                <!-- Fixed style readability text color contrast -->
                <p class="text-light opacity-75 small mb-4" style="line-height: 1.7; font-size: 0.95rem;">
                    <?php echo !empty($dept['description']) ? htmlspecialchars($dept['description']) : 'No description statement available for this practice panel group.'; ?>
                </p>
                
                <!-- Total Active Counsel Counter Widget Component -->
                <div class="p-3 border border-secondary rounded" style="background: rgba(15, 23, 42, 0.6);">
                    <div class="text-secondary small fw-medium text-uppercase mb-1">Total Active Counsel</div>
                    <div class="fs-2 fw-bold text-info d-flex align-items-center gap-2">
                        <i class="fas fa-user-shield fs-4 opacity-50"></i>
                        <span><?php echo count($members); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Team Roster Panel -->
        <div class="col-md-8">
            <div class="glass p-4 h-100" style="background: rgba(30, 41, 55, 0.45); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px;">
                <h5 class="text-white mb-3"><i class="fas fa-users text-primary me-2"></i> Assigned Team Roster</h5>
                
                <?php if (empty($members)): ?>
                    <div class="text-center py-5 border border-secondary border-dashed rounded" style="border-style: dashed !important; background: rgba(15, 23, 42, 0.3);">
                        <i class="fas fa-users-slash text-muted fs-1 mb-3 opacity-50"></i>
                        <p class="text-muted mb-0 px-3">No lawyers are currently assigned to this practice group.<br>Update user profiles to assign them to this section.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-dark align-middle mb-0" style="--bs-table-bg: transparent;">
                            <thead>
                                <tr class="text-secondary small border-secondary" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <th>Counsel Name</th>
                                    <th>Role / Status</th>
                                    <th>Email Address</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): 
                                    $fullname = htmlspecialchars($member['firstname'] . ' ' . $member['lastname']);
                                    $pic = !empty($member['profile_pic']) ? htmlspecialchars($member['profile_pic']) : 'https://via.placeholder.com/40';
                                ?>
                                <tr class="border-secondary">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?php echo $pic; ?>" class="rounded-circle border border-secondary" style="width: 36px; height: 36px; object-fit: cover;">
                                            <span class="text-white fw-medium" style="font-size: 0.95rem;"><?php echo $fullname; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                            <?php echo htmlspecialchars($member['role'] ?? 'Associate'); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary view-profile-btn py-1 px-3" data-id="<?php echo $member['id']; ?>">
                                            <i class="fas fa-eye me-1"></i> View Profile
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php
    $html = ob_get_clean();

    // Send payload safely back to AJAX frame orchestrator
    echo json_encode(['success' => true, 'html' => $html]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database exception: ' . $e->getMessage()]);
}
?>