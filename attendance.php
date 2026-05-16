<?php

/* =========================
   ATTENDANCE DATA
========================= */

$attendance = [

    [
        "date" => "01 Apr 2026",
        "clockIn" => "07:56 AM",
        "clockOut" => "04:02 PM",
        "break" => "30 mins",
        "hours" => "8h 06m",
        "status" => "Present",
        "badge" => "success"
    ],

    [
        "date" => "31 Mar 2026",
        "clockIn" => "09:11 AM",
        "clockOut" => "05:00 PM",
        "break" => "20 mins",
        "hours" => "7h 29m",
        "status" => "Late",
        "badge" => "warning"
    ],

    [
        "date" => "30 Mar 2026",
        "clockIn" => "-",
        "clockOut" => "-",
        "break" => "-",
        "hours" => "0h",
        "status" => "Absent",
        "badge" => "danger"
    ],

    [
        "date" => "29 Mar 2026",
        "clockIn" => "08:00 AM",
        "clockOut" => "04:05 PM",
        "break" => "25 mins",
        "hours" => "7h 40m",
        "status" => "Present",
        "badge" => "success"
    ],

    [
        "date" => "28 Mar 2026",
        "clockIn" => "07:48 AM",
        "clockOut" => "03:55 PM",
        "break" => "30 mins",
        "hours" => "7h 37m",
        "status" => "Present",
        "badge" => "success"
    ]

];

?>

<!doctype html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Attendance</title>

<!-- Bootstrap -->
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
  rel="stylesheet"
/>

<!-- Bootstrap Icons -->
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
  rel="stylesheet"
/>

<style>

body{
    background:#f4f6f9;
    font-family:Arial, sans-serif;
}

/* Sidebar */
.sidebar{
    min-height:100vh;
    background:#1f2937;
    color:white;
}

.brand{
    font-size:24px;
    font-weight:bold;
    text-align:center;
    margin-bottom:20px;
}

.nav-link{
    color:#d1d5db;
    margin-bottom:8px;
    border-radius:8px;
    padding:10px 15px;
}

.nav-link:hover,
.nav-link.active{
    background:#374151;
    color:white;
}

/* Topbar */
.topbar{
    background:white;
    border-bottom:1px solid #ddd;
}

/* Cards */
.card-box{
    border:none;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

/* Stats */
.stat-card{
    text-align:center;
    padding:20px;
}

.stat-number{
    font-size:28px;
    font-weight:bold;
}

.table thead{
    background:#f1f5f9;
}

</style>

</head>

<body>

<div class="container-fluid">

<div class="row">

<!-- Sidebar -->
<aside class="col-lg-2 col-md-3 sidebar p-3">

    <div class="brand">Farm Time</div>

    <nav class="nav flex-column mt-4">

        <a class="nav-link" href="dashboard.php">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>

        <a class="nav-link" href="roster.php">
            <i class="bi bi-calendar-week me-2"></i>Rosters
        </a>

        <a class="nav-link active" href="attendance.php">
            <i class="bi bi-clock-history me-2"></i>Attendance
        </a>

        <a class="nav-link" href="payslips.php">
            <i class="bi bi-receipt me-2"></i>Payslips
        </a>

        <a class="nav-link" href="setting.php">
            <i class="bi bi-gear me-2"></i>Settings
        </a>

    </nav>

</aside>

<!-- Main -->
<main class="col-lg-10 col-md-9 px-0">

    <!-- Topbar -->
    <div class="topbar d-flex justify-content-between align-items-center px-4 py-3">

        <div>
            <h4 class="mb-0">🕒 Attendance</h4>
            <small class="text-muted">
                Track your attendance history
            </small>
        </div>

    </div>

    <!-- Content -->
    <div class="p-4">

        <!-- Stats -->
        <div class="row g-4 mb-4">

            <div class="col-md-4">

                <div class="card card-box stat-card">

                    <div class="text-muted">
                        Days Present
                    </div>

                    <div class="stat-number text-success">
                        18
                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="card card-box stat-card">

                    <div class="text-muted">
                        Late Arrivals
                    </div>

                    <div class="stat-number text-warning">
                        2
                    </div>

                </div>

            </div>


        </div>

        <!-- Attendance Table -->
        <div class="card card-box p-4">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Attendance Records
                </h5>

                <button class="btn btn-outline-success btn-sm">
                    Download Report
                </button>

            </div>

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>

                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Break</th>
                            <th>Total Hours</th>
                            <th>Status</th>

                        </tr>

                    </thead>

                    <?php
// Filter attendance (remove Absent / 0h / not worked days)
$filteredAttendance = array_filter($attendance, function($record) {
    return $record['status'] !== "Absent" && $record['hours'] !== "0h";
});
?>

<tbody>

<?php foreach($filteredAttendance as $record): ?>

    <tr>

        <td><?php echo $record['date']; ?></td>

        <td><?php echo $record['clockIn']; ?></td>

        <td><?php echo $record['clockOut']; ?></td>

        <td><?php echo $record['break']; ?></td>

        <td><?php echo $record['hours']; ?></td>

        <td>
            <span class="badge bg-<?php echo $record['badge']; ?>">
                <?php echo $record['status']; ?>
            </span>
        </td>

    </tr>

<?php endforeach; ?>

</tbody>

                </table>

            </div>

        </div>

    </div>

</main>

</div>

</div>

</body>
</html>