<?php
$date = date("d M Y");
session_start();

/* =========================
   DEFAULT SETTINGS
========================= */

if (!isset($_SESSION['settings'])) {
    $_SESSION['settings'] = [
        "name" => "John",
        "role" => "Field Worker",
        "id" => "FT-102",
        "phone" => "0400 000 000",
        "email" => "john@farmtime.com",
        "hourly_rate" => 25,
        "overtime_rate" => 1.5,
        "tax" => 10
    ];
}

/* =========================
   SAVE SETTINGS
========================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $_SESSION['settings']['name'] = $_POST['name'];
    $_SESSION['settings']['role'] = $_POST['role'];
    $_SESSION['settings']['phone'] = $_POST['phone'];
    $_SESSION['settings']['email'] = $_POST['email'];
    $_SESSION['settings']['hourly_rate'] = $_POST['hourly_rate'];
    $_SESSION['settings']['overtime_rate'] = $_POST['overtime_rate'];
    $_SESSION['settings']['tax'] = $_POST['tax'];

    $message = "Settings updated successfully!";
}

$s = $_SESSION['settings'];
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Settings - Farm Time</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

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

    .card-box{
      border:none;
      border-radius:15px;
      box-shadow:0 2px 10px rgba(0,0,0,0.08);
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

        <a class="nav-link" href="attendance.php">
          <i class="bi bi-clock-history me-2"></i>Attendance
        </a>

        <a class="nav-link" href="payslips.php">
          <i class="bi bi-receipt me-2"></i>Payslips
        </a>

        <a class="nav-link active" href="setting.php">
          <i class="bi bi-gear me-2"></i>Settings
        </a>

      </nav>

    </aside>

    <!-- Main -->
    <main class="col-lg-10 col-md-9 px-0">

      <!-- Topbar -->
      <div class="topbar d-flex justify-content-between align-items-center px-4 py-3">

        <div>
          <h4 class="mb-0">⚙ Settings</h4>
          <small class="text-muted">Manage your profile & payroll</small>
        </div>

        <div class="d-flex align-items-center gap-3">
          <span class="text-muted"><?php echo $date; ?></span>
          <div class="fw-semibold"><?php echo $s['role']; ?></div>
        </div>

      </div>

      <!-- Content -->
      <div class="p-4">

        <?php if(isset($message)): ?>
        <div class="alert alert-success">
          <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="card card-box p-4">

          <form method="POST">

            <!-- Employee Info -->
            <h5 class="mb-3">👤 Employee Info</h5>

            <div class="row">

              <div class="col-md-4 mb-3">
                <label>Employee ID</label>
                <input type="text" class="form-control bg-light"
                  value="<?php echo $s['id']; ?>" readonly>
                <small class="text-muted">Cannot be changed</small>
              </div>

              <div class="col-md-4 mb-3">
                <label>Name</label>
                <input type="text" name="name" class="form-control"
                  value="<?php echo $s['name']; ?>" required>
              </div>

              <div class="col-md-4 mb-3">
                <label>Role</label>
                <input type="text" name="role" class="form-control"
                  value="<?php echo $s['role']; ?>" required>
              </div>

            </div>

            <!-- Contact Info -->
            <h5 class="mb-3 mt-3">📞 Contact Info</h5>

            <div class="row">

              <div class="col-md-6 mb-3">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control"
                  value="<?php echo $s['phone']; ?>" required>
              </div>

              <div class="col-md-6 mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control"
                  value="<?php echo $s['email']; ?>" required>
              </div>

            </div>

            

            <button class="btn btn-primary mt-3">
              Save Settings
            </button>

          </form>

        </div>

      </div>

    </main>

  </div>
</div>

</body>
</html>