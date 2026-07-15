<?php
require_once 'connect.php';

try {
    // Query to get total cases
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_cases FROM cases");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalCases = $result['total_cases'] ?? 0;
    
    // Optional: Get cases added this month
    $stmtMonth = $pdo->prepare("
        SELECT COUNT(*) as this_month 
        FROM cases 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmtMonth->execute();
    $monthResult = $stmtMonth->fetch(PDO::FETCH_ASSOC);
    $thisMonth = $monthResult['this_month'] ?? 0;
    
} catch (PDOException $e) {
    $totalCases = 0;
    $thisMonth = 0;
    error_log("Database error: " . $e->getMessage());
}
?>

<!-- HTML for Total Cases Card (to be placed in your dashboard) -->
<div class="card">
    <div class="card-content">
        <h3>Total Cases</h3>
        <div class="number"><?php echo $totalCases; ?></div>
        <?php if ($thisMonth > 0): ?>
            <div class="trend up">↑ <?php echo $thisMonth; ?> this month</div>
        <?php endif; ?>
    </div>
    <div class="icon">💼</div>
</div>