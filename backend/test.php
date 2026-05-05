<?php
require_once 'config/database.php';

$conn = getConnection();

echo "<h2>Testing Database Connection...</h2>";
echo "✅ Connected to: <strong>" . DB_NAME . "</strong><br><br>";

$tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_ASSOC);
echo "<strong>Tables found:</strong><br>";
foreach ($tables as $t) {
    echo "✅ " . array_values($t)[0] . "<br>";
}

// Test admin table
$admins = $conn->query("SELECT admin_id, username, status FROM admin")->fetch_all(MYSQLI_ASSOC);
echo "<br><strong>Admin users:</strong><br>";
foreach ($admins as $a) {
    echo "👤 " . $a['username'] . " — " . $a['status'] . "<br>";
}

$conn->close();
echo "<br>✅ All good!";
?>