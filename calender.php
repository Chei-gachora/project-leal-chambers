<?php
// ====================== SECURE SESSION CHECK ======================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Update activity timestamp
$_SESSION['last_activity'] = time();

// ====================== DATABASE CONNECTION ======================
include 'connect.php';   

$message = '';

// Handle Add, Edit, Delete
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $_POST['id'], $user_id);
        $message = $stmt->execute() ? "success" : "error";
        $stmt->close();
    } elseif (in_array($action, ['add', 'edit'])) {
        $id     = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $title  = trim($_POST['event_title']);
        $date   = $_POST['event_date'];
        $time   = $_POST['event_time'] ?? null;
        $client = trim($_POST['case_client'] ?? '');
        $type   = $_POST['event_type'];

        if (!empty($title) && !empty($date)) {
            if ($action === 'edit' && $id) {
                $sql = "UPDATE events SET event_title=?, event_date=?, event_time=?, case_client=?, event_type=? 
                        WHERE id=? AND user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii", $title, $date, $time, $client, $type, $id, $user_id);
            } else {
                $sql = "INSERT INTO events (user_id, event_title, event_date, event_time, case_client, event_type) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssss", $user_id, $title, $date, $time, $client, $type);
            }
            $message = $stmt->execute() ? "success" : "error";
            $stmt->close();
        }
    }
}

// Fetch events FOR CURRENT USER from BOTH tables
$events = [];
$user_id = $_SESSION['user_id'];

// 1. From events table
$result = $conn->query("SELECT * FROM events WHERE user_id = $user_id ORDER BY event_date, event_time");
if ($result) {
    while ($row = $result->fetch_assoc()) $events[] = $row;
}

// 2. From court_dates table
$result2 = $conn->query("SELECT id, event_title, event_date, event_time, 'Court' as event_type, location as case_client 
                         FROM court_dates WHERE user_id = $user_id ORDER BY event_date");
if ($result2) {
    while ($row = $result2->fetch_assoc()) $events[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | LegalPro</title>
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
        
        .calendar-grid th { 
            background-color: rgba(30, 41, 55, 0.8); 
            color: #94a3b8; 
        }
        .calendar-grid td {
            height: 110px;
            vertical-align: top;
            border-color: rgba(51, 65, 85, 0.6);
            position: relative;
        }
        
        .event-hearing { background-color: #fbbf24; color: #1e2937; font-weight: bold; }
        .event-sol { background-color: #ef4444; color: white; }
        .event-court { background-color: #3b82f6; color: white; }
        .calendar-grid td {
    height: 110px;
    vertical-align: top;
    position: relative;
    padding: 8px;
}

.has-event {
    background-color: rgba(59, 130, 246, 0.15) !important;
    font-weight: 500;
}

.event-court { background: #3b82f6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; }
.event-sol { background: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; }
.event-hearing { background: #fbbf24; color: #1e2937; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; }
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
                <li class="nav-item"><a href="calender.php" class="nav-link active"><i class="fas fa-calendar-alt me-2"></i> Calendar</a></li>
                <li class="nav-item"><a href="casess.php" class="nav-link text-light"><i class="fas fa-folder-open me-2"></i> Cases</a></li>
                <li class="nav-item"><a href="soltracker.php" class="nav-link text-light"><i class="fas fa-clock me-2"></i> SOL Tracker</a></li>
                <li class="nav-item"><a href="team.php" class="nav-link text-light"><i class="fas fa-users me-2"></i> Team</a></li>
                <li class="nav-item"><a href="documents.php" class="nav-link text-light"><i class="fas fa-file-alt me-2"></i> Documents</a></li>
                <li class="nav-item"><a href="Reportts.php" class="nav-link text-light"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-0">
            <!-- Top Bar -->
            <div class="top-bar p-3 d-flex justify-content-between align-items-center border-bottom">
                <h5>Court Calendar &amp; Deadlines</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="resetModal()">
                    <i class="fas fa-plus me-2"></i> Add Event
                </button>
            </div>

            <?php if ($message === "success"): ?>
                <div class="alert alert-success mx-4 mt-3 glass">✅ Event saved successfully!</div>
            <?php endif; ?>

            <div class="p-4">
                <div class="row">
                  <!-- Dynamic Calendar -->
<div class="col-lg-8 mb-4">
    <div class="glass">
  
        <?php
        // Get current month/year (default to June 2026 for your demo, or use current date)
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 6;
        $year  = isset($_GET['year']) ? (int)$_GET['year'] : 2026;

        $firstDay = strtotime("$year-$month-01");
        $daysInMonth = date('t', $firstDay);
        $startDay = date('w', $firstDay); // 0 = Sunday

        // Fetch events for this month
        $startDate = "$year-$month-01";
        $endDate   = "$year-$month-$daysInMonth";
        $eventQuery = $conn->prepare("SELECT event_date, event_title, event_type FROM events 
                                      WHERE event_date BETWEEN ? AND ? 
                                      ORDER BY event_date");
        $eventQuery->bind_param("ss", $startDate, $endDate);
        $eventQuery->execute();
        $eventResult = $eventQuery->get_result();

        $eventsByDate = [];
        while ($row = $eventResult->fetch_assoc()) {
            $date = $row['event_date'];
            if (!isset($eventsByDate[$date])) $eventsByDate[$date] = [];
            $eventsByDate[$date][] = $row;
        }
        ?>

        <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
            <h6>
                <i class="fas fa-calendar"></i> 
                <?= date('F Y', $firstDay) ?>
            </h6>
            <div class="btn-group">
                <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-secondary">← Prev</a>
                <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-secondary">Next →</a>
            </div>
        </div>

        <div class="p-0">
            <table class="table table-bordered text-center calendar-grid mb-0">
                <thead>
                    <tr>
                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day = 1;
                    $totalCells = ceil(($daysInMonth + $startDay) / 7) * 7;

                    for ($i = 0; $i < $totalCells; $i++) {
                        if ($i % 7 === 0) echo "<tr>";
                        
                        if ($i < $startDay || $day > $daysInMonth) {
                            echo "<td class='text-muted'></td>";
                        } else {
                            $currentDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                            $hasEvent = isset($eventsByDate[$currentDate]);
                            
                            echo "<td class='" . ($hasEvent ? "has-event" : "") . "'>";
                            echo "<strong>$day</strong>";
                            
                            if ($hasEvent) {
                                foreach ($eventsByDate[$currentDate] as $ev) {
                                    $typeClass = ($ev['event_type'] === 'Court') ? 'event-court' : 
                                                ($ev['event_type'] === 'SOL' || $ev['event_type'] === 'Deadline' ? 'event-sol' : 'event-hearing');
                                    echo "<div class='small $typeClass mt-1'>".htmlspecialchars($ev['event_title'])."</div>";
                                }
                            }
                            echo "</td>";
                            $day++;
                        }
                        
                        if ($i % 7 === 6) echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
                    <!-- Upcoming This Week -->
                    <div class="col-lg-4 mb-4">
                        <div class="glass h-100">
                            <div class="p-4 border-bottom">
                                <h6><i class="fas fa-clock"></i> Upcoming This Week</h6>
                            </div>
                            <div class="p-4">
                                <?php if (empty($events)): ?>
                                    <p class="text-muted">No upcoming events scheduled.</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($events, 0, 4) as $e): ?>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <strong><?= date('d M Y', strtotime($e['event_date'])) ?> 
                                            <?= $e['event_time'] ? date('g:i A', strtotime($e['event_time'])) : '' ?></strong><br>
                                            <small><?= htmlspecialchars($e['event_title']) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- All Scheduled Events -->
                <div class="glass">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6>All Scheduled Events</h6>
                        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search events...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="eventsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Event</th>
                                    <th>Case / Client</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['event_date']) ?></td>
                                    <td><?= htmlspecialchars($event['event_time'] ?? 'All Day') ?></td>
                                    <td><?= htmlspecialchars($event['event_title']) ?></td>
                                    <td><?= htmlspecialchars($event['case_client'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($event['event_type']) ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" onclick="editEvent(<?= $event['id'] ?>, 
                                            '<?= addslashes($event['event_title']) ?>',
                                            '<?= $event['event_date'] ?>',
                                            '<?= $event['event_time'] ?>',
                                            '<?= addslashes($event['case_client'] ?? '') ?>',
                                            '<?= addslashes($event['event_type']) ?>')">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(<?= $event['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="eventId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Event Title</label>
                        <input type="text" name="event_title" id="event_title" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Date</label>
                            <input type="date" name="event_date" id="event_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Time</label>
                            <input type="time" name="event_time" id="event_time" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Case / Client</label>
                        <select name="case_client" id="case_client" class="form-select">
                            <option value="">-- Select --</option>
                            <option value="Civil Suit 245/2025 - Kenya Power Ltd">Civil Suit 245/2025 - Kenya Power Ltd</option>
                            <option value="Land Dispute L89/2024 - John Mwangi">Land Dispute L89/2024 - John Mwangi</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Event Type</label>
                        <select name="event_type" id="event_type" class="form-select" required>
                            <option value="Court">Court</option>
                            <option value="Filing">Filing</option>
                            <option value="Client Meeting">Client Meeting</option>
                            <option value="Deadline">Deadline</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function resetModal() {
        document.getElementById('modalTitle').textContent = 'Add New Event';
        document.getElementById('formAction').value = 'add';
        document.getElementById('eventId').value = '';
        document.getElementById('event_title').value = '';
        document.getElementById('event_date').value = '';
        document.getElementById('event_time').value = '';
        document.getElementById('case_client').value = '';
        document.getElementById('event_type').value = 'Court';
    }

    function editEvent(id, title, date, time, client, type) {
        document.getElementById('modalTitle').textContent = 'Edit Event';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('eventId').value = id;
        document.getElementById('event_title').value = title;
        document.getElementById('event_date').value = date;
        document.getElementById('event_time').value = time || '';
        document.getElementById('case_client').value = client;
        document.getElementById('event_type').value = type;
        new bootstrap.Modal(document.getElementById('eventModal')).show();
    }

    function deleteEvent(id) {
        if (confirm('Are you sure you want to delete this event?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Live Search
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('#eventsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
</script>
</body>
</html>