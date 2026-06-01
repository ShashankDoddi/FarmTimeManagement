<?php

$employee = [
    "name" => "John",
    "role" => "Field Worker",
    "id" => "FT-102"
];

// hourly rate (set salary rule here)
$hourlyRate = 25;

// weekly attendance (example)
$payslips = [

    [
        "week" => "Week 1 - March 2026",
        "attendance" => [
            ["date" => "01 Mar", "hours" => 8],
            ["date" => "02 Mar", "hours" => 7.5],
            ["date" => "03 Mar", "hours" => 8],
            ["date" => "04 Mar", "hours" => 6],
            ["date" => "05 Mar", "hours" => 8],
        ]
    ],

    [
        "week" => "Week 2 - March 2026",
        "attendance" => [
            ["date" => "08 Mar", "hours" => 8],
            ["date" => "09 Mar", "hours" => 7],
            ["date" => "10 Mar", "hours" => 8],
            ["date" => "11 Mar", "hours" => 8],
            ["date" => "12 Mar", "hours" => 0],
        ]
    ]

];

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Farm Time Employee Dashboard</title>

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

  <link rel="stylesheet" href="adminDashboard.css">
</head>

<body>

<div class="container-fluid">
  <div class="row">

    <!-- Sidebar -->
    <aside class="col-lg-2 col-md-3 sidebar p-3">

      <div class="brand">Farm Time</div>

      <nav class="nav flex-column mt-4">

        <a class="nav-link active" href="dashboard.php">
          <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>

        <a class="nav-link" href="roster.php">
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
<main class="col-md-9 col-lg-10 p-4">

    <h3>💰 Payslips</h3>

    <div class="card card-box p-3 mb-4">

        <div>
            <strong><?php echo $employee['name']; ?></strong><br>
            ID: <?php echo $employee['id']; ?> | Role: <?php echo $employee['role']; ?>
        </div>

    </div>

    <div class="row g-4">

<?php foreach($payslips as $p): ?>

<?php
    // calculate total hours
    $totalHours = 0;

    foreach ($p["attendance"] as $day) {
        $totalHours += $day["hours"];
    }

    // salary calculation
    $gross = $totalHours * $hourlyRate;

    // overtime rule (optional)
    $standardHours = 40;
    $overtimePay = 0;

    if ($totalHours > $standardHours) {
        $overtimeHours = $totalHours - $standardHours;
        $overtimePay = $overtimeHours * ($hourlyRate * 1.5);
        $gross = ($standardHours * $hourlyRate) + $overtimePay;
    }

    // tax deduction
    $tax = $gross * 0.1;
    $net = $gross - $tax;
?>

<div class="col-md-6 col-xl-4">

    <div class="card card-box p-3">

        <h5><?php echo $p["week"]; ?></h5>

        <hr>

        <p><strong>Total Hours:</strong> <?php echo $totalHours; ?> hrs</p>
        <p><strong>Hourly Rate:</strong> $<?php echo $hourlyRate; ?></p>

        <p><strong>Gross Pay:</strong> $<?php echo number_format($gross, 2); ?></p>
        <p><strong>Tax (10%):</strong> $<?php echo number_format($tax, 2); ?></p>

        <hr>

        <div style="font-size:22px;font-weight:bold;color:#16a34a;">
            Net Pay: $<?php echo number_format($net, 2); ?>
        </div>

        <button class="btn btn-outline-primary btn-sm mt-3 w-100">
            Download Payslip
        </button>

    </div>

</div>

<?php endforeach; ?>

</div>

</main>

</div>
</div>

</body>
</html>