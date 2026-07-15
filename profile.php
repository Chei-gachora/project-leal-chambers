<?php

session_start();
require_once 'connect.php';

$user_id = $_SESSION['user_id'] ?? 1;

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Initialize variables to avoid warnings
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... your POST handling code ...
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname    = trim($_POST['firstname'] ?? '');
    $lastname     = trim($_POST['lastname'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $jurisdiction = trim($_POST['jurisdiction'] ?? '');

    $error = '';

    // Profile Picture Upload
// === PROFILE PICTURE UPLOAD ===
$profile_pic = $user['profile_pic'] ?? 'assets/img/profile-default.png';

if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
    $target_dir = "uploads/profiles/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_ext, $allowed)) {
        $new_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_name;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
            $profile_pic = $target_file;
        }
    }
}
    // Update Database
    if (empty($error)) {
    $stmt = $conn->prepare("UPDATE users SET 
    firstname=?, lastname=?, email=?, phone=?, 
    jurisdiction=?, profile_pic=?, updated_at=NOW() 
    WHERE id=?");
        $stmt->bind_param("ssssssi", 
    $firstname, $lastname, $email, $phone, 
    $jurisdiction, $profile_pic, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Profile updated successfully!";
            $_SESSION['profile_pic'] = $profile_pic;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Failed to update profile.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .profile-img { width: 150px; height: 150px; object-fit: cover; border: 4px solid #0061f2; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4><i class="fas fa-user-circle"></i> My Profile</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'assets/img/default.png'); ?>" 
                                 alt="Profile Picture" class="profile-img rounded-circle mb-3">
                            <br>
                            <input type="file" name="profile_pic" class="form-control d-inline-block w-auto" accept="image/*">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="firstname" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['firstname'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="lastname" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['lastname'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jurisdiction</label>
                            <input type="text" name="jurisdiction" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['jurisdiction'] ?? ''); ?>">
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>