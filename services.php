<?php
include 'connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #0f172a; 
            color: #e2e8f0; 
        }
        .service-card {
            background: rgba(30, 41, 55, 0.9);
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
        }
        .icon-circle {
            width: 70px;
            height: 70px;
            background: rgba(59, 130, 246, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #60a5fa;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">LegalPro</a>
            <a href="login.php" class="btn btn-outline-light">Login</a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold">Our Legal Services</h1>
            <p class="lead text-muted">Comprehensive legal solutions tailored for your needs</p>
        </div>

        <div class="row g-4">
            <?php
            $result = $conn->query("SELECT * FROM services ORDER BY sort_order ASC");
            while ($service = $result->fetch_assoc()):
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="service-card p-5 rounded-4 text-center h-100">
                    <div class="icon-circle">
                        <i class="<?= htmlspecialchars($service['icon']) ?>"></i>
                    </div>
                    <h4 class="mb-3"><?= htmlspecialchars($service['name']) ?></h4>
                    <p class="text-muted mb-4"><?= htmlspecialchars(substr($service['description'], 0, 110)) ?>...</p>
                    <a href="service_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary w-100">Learn More</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>