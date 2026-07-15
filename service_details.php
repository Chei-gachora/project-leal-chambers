<?php
include 'connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid Service ID");
}

$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    die("Service not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['name']) ?> - LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; }
        .hero-image {
            height: 400px;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                        url('https://source.unsplash.com/random/1200x600/?<?= strtolower($service['name']) ?>') center/cover;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">LegalPro</a>
            <a href="services.php" class="btn btn-outline-light">Back to Services</a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="hero-image mb-5"></div>
                
                <h1 class="display-5 fw-bold mb-4"><?= htmlspecialchars($service['name']) ?></h1>
                
                <div class="lead mb-5">
                    <?= nl2br(htmlspecialchars($service['description'])) ?>
                </div>

                <h4 class="mb-3">How Corporate Law Works</h4>
                <div class="bg-dark p-4 rounded-3 mb-5">
                    <p><strong>Corporate Law</strong> involves advising businesses on formation, governance, compliance, contracts, mergers, and regulatory matters.</p>
                    <ul>
                        <li>Company registration and structuring</li>
                        <li>Drafting and reviewing contracts</li>
                        <li>Corporate governance and compliance</li>
                        <li>Mergers, acquisitions, and due diligence</li>
                        <li>Shareholder disputes and resolutions</li>
                    </ul>
                </div>

                <a href="contact.php" class="btn btn-primary btn-lg">Request Consultation for this Service</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>