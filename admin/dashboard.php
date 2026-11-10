
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$totalPatients = $conn->query(
    "SELECT COUNT(*) FROM patients"
)->fetchColumn();

$totalDoctors = $conn->query(
    "SELECT COUNT(*) FROM doctors"
)->fetchColumn();

$totalAppointments = $conn->query(
    "SELECT COUNT(*) FROM appointments"
)->fetchColumn();

$sql = "SELECT a.*, 
               pu.name AS patient_name,
               du.name AS doctor_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users pu ON p.user_id = pu.user_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        JOIN users du ON d.user_id = du.user_id
        ORDER BY a.appointment_date DESC, a.start_time DESC";

$appointments = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Admin Dashboard</p>
</header>

<div class="dashboard">

    <h2>
        Welcome, <?= htmlspecialchars($_SESSION["name"]) ?>!
    </h2>

    <p>Hospital Appointment Management Overview</p>

    <nav>
        <a href="../logout.php">Logout</a>
        <a href="manage_doctors.php">Manage Doctors</a>
    </nav>

    <div class="cards">

        <div class="card">
            <h3>Total Patients</h3>
            <h2><?= $totalPatients ?></h2>
        </div>

        <div class="card">
            <h3>Total Doctors</h3>
            <h2><?= $totalDoctors ?></h2>
        </div>

        <div class="card">
            <h3>Total Appointments</h3>
            <h2><?= $totalAppointments ?></h2>
        </div>

    </div>

    <h3>All Appointments</h3>

    <table>
        <tr>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
        </tr>

        <?php if (count($appointments) > 0): ?>

            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td><?= htmlspecialchars($appointment["patient_name"]) ?></td>

                    <td><?= htmlspecialchars($appointment["doctor_name"]) ?></td>

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
