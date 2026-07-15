<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'connect.php';

// Get the logged-in user's ID from the session
$user_id = $_SESSION['user_id'];

// Modify the SQL to filter by user_id using a prepared statement for security
$sql = "SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC";
$stmt = $conn->prepare($sql);

$documents = [];
if ($stmt) {
    // "i" assumes user_id is an integer. Change to "s" if it's a string/UUID.
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents | LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #0f172a; 
            color: #e2e8f0;
            background-image: linear-gradient(rgba(15,23,42,0.85), rgba(15,23,42,0.85));
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
        
        .nav-link.active { 
            background-color: #3b82f6 !important; 
            color: white !important; 
        }
        
        .doc-card {
            transition: all 0.3s ease;
        }
        .doc-card:hover {
            transform: translateY(-5px);
        }

        /* Custom Preview Modal Theme Rules */
        .custom-modal {
            display: none; 
            position: fixed; 
            z-index: 2000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            background-color: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
        }

        .custom-modal-content {
            background: rgba(30, 41, 55, 0.95);
            color: #e2e8f0;
            margin: 3% auto; 
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 80%; 
            height: 85vh;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.5);
        }

        .custom-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .custom-close-btn {
            color: #94a3b8;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .custom-close-btn:hover {
            color: #f1f5f9;
        }

        .custom-modal-body {
            flex-grow: 1;
            width: 100%;
            height: 100%;
        }

        #previewFrame {
            width: 100%;
            height: 100%;
            border-radius: 8px;
            background-color: #ffffff;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-4">
            <h4 class="mb-4"><i class="fas fa-gavel"></i> LegalPro</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link text-light"><i class="fas fa-home me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="calender.php" class="nav-link text-light"><i class="fas fa-calendar-alt me-2"></i> Calendar</a></li>
                <li class="nav-item"><a href="cases.php" class="nav-link text-light"><i class="fas fa-folder-open me-2"></i> Cases</a></li>
                <li class="nav-item"><a href="soltracker.php" class="nav-link text-light"><i class="fas fa-clock me-2"></i> SOL Tracker</a></li>
                <li class="nav-item"><a href="team.php" class="nav-link text-light"><i class="fas fa-users me-2"></i> Team</a></li>
                <li class="nav-item"><a href="documents.php" class="nav-link active"><i class="fas fa-file-alt me-2"></i> Documents</a></li>
                <li class="nav-item"><a href="Reportts.php" class="nav-link text-light"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
            </ul>
        </div>

        <div class="col-md-10 p-0">
            <div class="top-bar p-3 d-flex justify-content-between align-items-center border-bottom">
                <h5>Document Management</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload me-2"></i> Upload New Document
                </button>
            </div>

            <div class="p-4">
                <div class="glass p-4 mb-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by document title, case number or filename...">
                </div>

                <div class="glass">
                    <div class="p-4 border-bottom">
                        <h6>All Documents (<?= count($documents) ?>)</h6>
                    </div>
                    <div class="p-4">
                        <div class="row g-4" id="documentsContainer">
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                <div class="col-md-6 col-lg-4 doc-item">
                                    <div class="glass doc-card p-4">
                                        <div class="d-flex">
                                            <i class="fas fa-file-<?= strpos(strtolower($doc['file_type'] ?? ''), 'pdf') !== false ? 'pdf' : 'alt' ?> fa-3x text-primary me-4"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($doc['document_title']) ?></h6>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($doc['case_number'] ?? 'No Case') ?> • 
                                                    <?= date('d M Y', strtotime($doc['uploaded_at'])) ?>
                                                </small>
                                                <div class="mt-3 d-flex gap-2">
                                                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-sm btn-outline-primary" download>
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <button class="btn btn-sm btn-primary" data-file-url="<?= htmlspecialchars($doc['file_path']) ?>" onclick="openPreview(this)">
                                                        <i class="fas fa-eye"></i> Preview
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                    <p class="text-muted">No documents found. Upload your first document.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="previewModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h5 class="m-0"><i class="fas fa-file-contract text-primary me-2"></i> Document Preview</h5>
            <span class="custom-close-btn" onclick="closePreview()">&times;</span>
        </div>
        <div class="custom-modal-body">
            <iframe id="previewFrame" src="" frameborder="0"></iframe>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header">
                <h5 class="modal-title">Upload New Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="upload_document.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Document Title</label>
                        <input type="text" name="document_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Case Number (Optional)</label>
                        <input type="text" name="case_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Select File</label>
                        <input type="file" name="document_file" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPreview(button) {
    const fileUrl = button.getAttribute('data-file-url');
    const previewFrame = document.getElementById('previewFrame');
    const modal = document.getElementById('previewModal');
    
    if(fileUrl) {
        previewFrame.src = fileUrl;
        modal.style.display = "block";
    } else {
        alert("File location missing structure path reference.");
    }
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    const previewFrame = document.getElementById('previewFrame');
    
    modal.style.display = "none";
    previewFrame.src = ""; // Stop file process completely
}

window.onclick = function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target == modal) {
        closePreview();
    }
}

// Live Search logic
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('.doc-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>