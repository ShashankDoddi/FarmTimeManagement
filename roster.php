<?php

/* =========================
   CURRENT WEEK ROSTER
========================= */

$currentWeek = [
    [
        "day" => "Monday",
        "shift" => "7:00 AM – 3:00 PM",
        "location" => "North Field",
        "status" => "Scheduled",
        "badge" => "success"
    ],
    [
        "day" => "Tuesday",
        "shift" => "8:00 AM – 4:00 PM",
        "location" => "Greenhouse",
        "status" => "Scheduled",
        "badge" => "success"
    ],
    [
        "day" => "Wednesday",
        "shift" => "OFF DAY",
        "location" => "",
        "status" => "Off",
        "badge" => "secondary"
    ],
    [
        "day" => "Thursday",
        "shift" => "6:00 AM – 2:00 PM",
        "location" => "Packing Shed",
        "status" => "Scheduled",
        "badge" => "success"
    ],
    [
        "day" => "Friday",
        "shift" => "9:00 AM – 5:00 PM",
        "location" => "Orchard",
        "status" => "Scheduled",
        "badge" => "success"
    ]
];


/* =========================
   NEXT WEEK ROSTER
========================= */

$nextWeek = [
    [
        "day" => "Monday",
        "shift" => "8:00 AM – 4:00 PM",
        "location" => "Warehouse",
        "status" => "Scheduled",
        "badge" => "primary"
    ],
    [
        "day" => "Tuesday",
        "shift" => "7:00 AM – 3:00 PM",
        "location" => "Packing Shed",
        "status" => "Scheduled",
        "badge" => "primary"
    ],
    [
        "day" => "Wednesday",
        "shift" => "OFF DAY",
        "location" => "",
        "status" => "Off",
        "badge" => "secondary"
    ],
    [
        "day" => "Thursday",
        "shift" => "9:00 AM – 5:00 PM",
        "location" => "North Field",
        "status" => "Scheduled",
        "badge" => "primary"
    ],
    [
        "day" => "Friday",
        "shift" => "6:00 AM – 2:00 PM",
        "location" => "Greenhouse",
        "status" => "Scheduled",
        "badge" => "primary"
    ]
];

?>

<!doctype html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Weekly Roster</title>

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

/* Shift Cards */
.shift-card{
    border-left:5px solid #16a34a;
    transition:0.3s;
}

.shift-card:hover{
    transform:translateY(-3px);
}

.day-title{
    font-size:18px;
    font-weight:bold;
}

.shift-time{
    color:#16a34a;
    font-weight:bold;
    margin-top:10px;
}

/* Tabs */
.nav-pills .nav-link.active{
    background:#16a34a;
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

        <a class="nav-link active" href="roster.php">
            <i class="bi bi-calendar-week me-2"></i>Rosters
        </a>

        <a class="nav-link" href="attendance.php">
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
            <h4 class="mb-0">📅 Weekly Roster</h4>
            <small class="text-muted">
                View current and upcoming shifts
            </small>
        </div>

    </div>

    <!-- Content -->
    <div class="p-4">

        <!-- Tabs -->
        <ul class="nav nav-pills mb-4" id="rosterTabs">

            <li class="nav-item">
                <button
                  class="nav-link active"
                  data-bs-toggle="pill"
                  data-bs-target="#currentWeek"
                >
                  Present Week
                </button>
            </li>

            <li class="nav-item">
                <button
                  class="nav-link"
                  data-bs-toggle="pill"
                  data-bs-target="#nextWeek"
                >
                  Next Week
                </button>
            </li>

        </ul>

        <!-- Tab Content -->
        <div class="tab-content">

            <!-- PRESENT WEEK -->
            <div class="tab-pane fade show active" id="currentWeek">

                <div class="row g-4">

                    <?php foreach($currentWeek as $shift): ?>

                    <div class="col-md-6 col-xl-4">

                        <div class="card card-box shift-card p-4">

                            <div class="day-title">
                                <?php echo $shift['day']; ?>
                            </div>

                            <div class="shift-time">
                                <?php echo $shift['shift']; ?>
                            </div>

                            <?php if($shift['location'] != ""): ?>

                            <div class="text-muted mt-2">
                                Farm Area:
                                <?php echo $shift['location']; ?>
                            </div>

                            <?php endif; ?>

                            <span class="badge bg-<?php echo $shift['badge']; ?> mt-3">
                                <?php echo $shift['status']; ?>
                            </span>

                        </div>

                    </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- NEXT WEEK -->
            <div class="tab-pane fade" id="nextWeek">

                <div class="row g-4">

                    <?php foreach($nextWeek as $shift): ?>

                    <div class="col-md-6 col-xl-4">

                        <div class="card card-box shift-card p-4">

                            <div class="day-title">
                                <?php echo $shift['day']; ?>
                            </div>

                            <div class="shift-time">
                                <?php echo $shift['shift']; ?>
                            </div>

                            <?php if($shift['location'] != ""): ?>

                            <div class="text-muted mt-2">
                                Farm Area:
                                <?php echo $shift['location']; ?>
                            </div>

                            <?php endif; ?>

                            <span class="badge bg-<?php echo $shift['badge']; ?> mt-3">
                                <?php echo $shift['status']; ?>
                            </span>

                        </div>

                    </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    </div>

</main>

</div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>