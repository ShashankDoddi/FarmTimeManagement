<?php
$date = date("d M Y");
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

  <style>

    body{
      background:#f4f6f9;
      font-family: Arial, sans-serif;
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

    .section-title{
      color:#6b7280;
      font-size:14px;
    }

    .icon-box{
      width:50px;
      height:50px;
      background:#e0f2fe;
      display:flex;
      align-items:center;
      justify-content:center;
      border-radius:12px;
      font-size:22px;
      color:#0284c7;
    }

    /* Clock Button */
    #clockBtn{
      width:250px;
      border-radius:12px;
      font-size:20px;
      font-weight:bold;
    }

    /* Mobile */
    @media(max-width:768px){

      .sidebar{
        min-height:auto;
      }

      #clockBtn{
        width:100%;
      }

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

        <a class="nav-link active" href="#">
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
    <main class="col-lg-10 col-md-9 px-0">

      <!-- Topbar -->
      <div class="topbar d-flex justify-content-between align-items-center px-4 py-3">

        <div>
          <h4 class="mb-0">👋 Welcome John</h4>
          <small class="text-muted">Employee Dashboard</small>
        </div>

        <div class="d-flex align-items-center gap-3">
          <span class="text-muted"><?php echo $date; ?></span>
          <div class="fw-semibold">Field Worker</div>
        </div>

      </div>

      <!-- Content -->
      <div class="p-4">

        <!-- Status -->
        <div class="mb-4">

          <div class="card card-box p-3">

            <div class="d-flex justify-content-between align-items-center">

              <div>
                <div class="section-title">Work Status</div>
                <h5 class="mb-0 text-danger" id="statusText">
                  Not Clocked In
                </h5>
              </div>

              <div class="icon-box">
                <i class="bi bi-person-workspace"></i>
              </div>

            </div>

          </div>

        </div>

        <!-- Clock In -->
        <div class="mb-4 text-center">

          <button id="clockBtn" class="btn btn-primary btn-lg px-5 py-3">
            CLOCK IN
          </button>

          <div class="mt-3 text-muted">
            Worked Today:
            <span id="workedTime">0h 0m</span>
          </div>

        </div>

        <!-- Cards -->
        <div class="row g-4 mb-4">

          <!-- Break -->
          <div class="col-md-6 col-xl-3">

            <div class="card card-box p-3 text-center">

              <h6>Break</h6>

              <button id="breakBtn"
                class="btn btn-outline-secondary mt-2">
                Start Break
              </button>

            </div>

          </div>

          <!-- Payslips -->
          <div class="col-md-6 col-xl-3">

            <div class="card card-box p-3 text-center">

              <h6>Payslips</h6>

            <a href="payslips.php" class="btn btn-outline-success mt-2">
				View Payslips
			</a>

            </div>

          </div>

          <!-- Timesheet -->
          <div class="col-md-6 col-xl-3">

            <div class="card card-box p-3 text-center">

              <h6>Timesheet</h6>

              <a href="attendance.php" class="btn btn-outline-primary mt-2">
                View Timesheet
              </a>

            </div>

          </div>

          <!-- Leave -->
          <div class="col-md-6 col-xl-3">

            <div class="card card-box p-3 text-center">

              <h6>Leave</h6>

              <button class="btn btn-outline-danger mt-2">
                Request Leave
              </button>

            </div>

          </div>

        </div>

        <!-- Next Shift -->
        <div class="card card-box p-4 text-center">

          <h5 class="mb-2">Next Shift</h5>

          <p class="mb-0">Friday</p>

          <p class="fw-semibold">
            9:00 AM – 5:00 PM
          </p>

        </div>

      </div>

    </main>

  </div>
</div>

<!-- JavaScript -->
<script>

let isClockedIn = false;
let isOnBreak = false;

const clockBtn = document.getElementById("clockBtn");
const breakBtn = document.getElementById("breakBtn");
const statusText = document.getElementById("statusText");

clockBtn.addEventListener("click", () => {

  isClockedIn = !isClockedIn;

  if (isClockedIn) {

    clockBtn.innerText = "CLOCK OUT";

    clockBtn.classList.remove("btn-primary");
    clockBtn.classList.add("btn-danger");

    statusText.innerText = "Working";

    statusText.classList.remove("text-danger");
    statusText.classList.add("text-success");

  } else {

    clockBtn.innerText = "CLOCK IN";

    clockBtn.classList.remove("btn-danger");
    clockBtn.classList.add("btn-primary");

    statusText.innerText = "Not Clocked In";

    statusText.classList.remove("text-success");
    statusText.classList.add("text-danger");

  }

});

breakBtn.addEventListener("click", () => {

  if (!isClockedIn) {

    alert("You must clock in first!");
    return;

  }

  isOnBreak = !isOnBreak;

  if (isOnBreak) {

    breakBtn.innerText = "End Break";

    breakBtn.classList.remove("btn-outline-secondary");
    breakBtn.classList.add("btn-warning");

  } else {

    breakBtn.innerText = "Start Break";

    breakBtn.classList.remove("btn-warning");
    breakBtn.classList.add("btn-outline-secondary");

  }

});

</script>

</body>
</html>