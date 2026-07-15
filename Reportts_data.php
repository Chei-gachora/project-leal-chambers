<?php
include 'connect.php';
header('Content-Type: application/json');

function safeCount($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? (int)$res->fetch_row()[0] : 0;
}

// Metrics
$metrics = [
    'total_docs' => safeCount($conn, "SELECT COUNT(*) FROM documents"),
    'total_cases' => safeCount($conn, "SELECT COUNT(*) FROM cases"),
    'upcoming_court' => safeCount($conn, "SELECT COUNT(*) FROM court_dates WHERE court_date >= CURDATE()"),
    'sol_risk' => safeCount($conn, "SELECT COUNT(*) FROM cases WHERE sol_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")
];

// Trend
$trend_res = $conn->query("SELECT DATE_FORMAT(uploaded_at, '%b %Y') as month, COUNT(*) as count FROM documents GROUP BY DATE_FORMAT(uploaded_at, '%Y-%m') ORDER BY uploaded_at DESC LIMIT 6");
$months = $counts = [];
while ($r = $trend_res->fetch_assoc()) { $months[] = $r['month']; $counts[] = $r['count']; }

// Status
$status_labels = $status_values = [];
$status_res = $conn->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status");
while ($r = $status_res->fetch_assoc()) { $status_labels[] = $r['status']; $status_values[] = $r['cnt']; }

// Document Types
$type_labels = $type_counts = [];
$type_res = $conn->query("SELECT file_type, COUNT(*) as cnt FROM documents GROUP BY file_type ORDER BY cnt DESC LIMIT 6");
while ($r = $type_res->fetch_assoc()) { $type_labels[] = $r['file_type'] ?: 'Other'; $type_counts[] = $r['cnt']; }

// Users
$user_names = $user_counts = [];
$user_res = $conn->query("SELECT name, COUNT(d.id) as docs FROM users u LEFT JOIN documents d ON u.id = d.uploaded_by 
                          WHERE d.uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY u.id ORDER BY docs DESC LIMIT 8");
while ($r = $user_res->fetch_assoc()) { $user_names[] = $r['name']; $user_counts[] = $r['docs']; }

// Top Cases
$top_cases = [];
$cases_res = $conn->query("SELECT case_number, client_name, 
                           COALESCE(DATE_FORMAT(next_hearing_date, '%d %b %Y'), 'N/A') as next_date,
                           COALESCE(DATE_FORMAT(sol_date, '%d %b %Y'), 'N/A') as sol_date 
                           FROM cases WHERE status = 'Active' ORDER BY next_hearing_date ASC LIMIT 10");
while ($r = $cases_res->fetch_assoc()) $top_cases[] = $r;

echo json_encode([
    'metrics' => $metrics,
    'trend' => ['months' => array_reverse($months), 'counts' => array_reverse($counts)],
    'status' => ['labels' => $status_labels, 'values' => $status_values],
    'types' => ['labels' => $type_labels, 'counts' => $type_counts],
    'users' => ['names' => $user_names, 'counts' => $user_counts],
    'top_cases' => $top_cases
]);
?>