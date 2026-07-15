<?php
require_once 'connect.php';
    
    // Updated query to match your actual table structure
    $query = "SELECT d.id, d.name, d.description, COUNT(u.id) as total_lawyers
              FROM departments d
              LEFT JOIN users u ON d.id = u.department_id
              GROUP BY d.id
              ORDER BY d.name ASC";
              
    $stmt = $pdo->query($query);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="row g-4 p-2">';
   
    if (empty($departments)) {
        echo "<div class='col-12'><p class='text-muted text-center py-4'>No departments registered in the system yet.</p></div>";
    } else {
        foreach ($departments as $dept) {
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="glass p-4 h-100 d-flex flex-column justify-content-between" style="background: rgba(30, 41, 55, 0.65); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px;">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-white mb-0"><i class="fas fa-briefcase text-primary me-2"></i> <?php echo htmlspecialchars($dept['name']); ?></h5>
                            <span class="badge bg-dark border border-secondary text-info"><?php echo $dept['total_lawyers']; ?> Members</span>
                        </div>
                        <p class="text-muted small" style="line-height: 1.6;"><?php echo htmlspecialchars($dept['description'] ?? 'No description available'); ?></p>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-light w-100 manage-practice-btn" data-id="<?php echo $dept['id']; ?>">
                            Manage Practice
                        </button>
                    </div>
                </div>
            </div>
            <?php
        }
    }
   
    echo '</div>';
} catch (PDOException $e) {
    echo "<div class='alert alert-danger m-2'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>