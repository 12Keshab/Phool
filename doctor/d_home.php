
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "doctor") {
    header("Location: ../login.php");
    exit();
}

$sql = "SELECT doctor_id FROM doctors WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$_SESSION["user_id"]]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found.");
}

$sql = "SELECT a.*, u.name AS patient_name,
               p.phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u ON p.user_id = u.user_id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.start_time DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$doctor["doctor_id"]]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Doctor Home page - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Doctor Home page</p>
</header>

<div class="dashboard">

    <h2>
        Welcome, Dr. <?= htmlspecialchars($_SESSION["name"]) ?>!
    </h2>

    <p>Manage your appointments here.</p>

    <nav>
        <a href="../logout.php">Logout</a>
    </nav>

    <h3>My Appointments</h3>

    <table>
        <tr>
            <th>Patient</th>
            <th>Phone</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
        </tr>

        <?php if (count($appointments) > 0): ?>

            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td><?= htmlspecialchars($appointment["patient_name"]) ?></td>

                    <td><?= htmlspecialchars($appointment["phone"] ?? "") ?></td>

                    <td><?= htmlspecialchars($appointment["appointment_date"]) ?></td>

                    <td><?= htmlspecialchars($appointment["start_time"]) ?></td>

                    <td><?= htmlspecialchars($appointment["status"]) ?></td>
                </tr>
            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="5">No appointments found.</td>
            </tr>

        <?php endif; ?>

    </table>

</div>

</body>
</html>
