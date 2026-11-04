
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "patient") {
    header("Location: ../login.php");
    exit();
}

$sql = "SELECT patient_id FROM patients WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$_SESSION["user_id"]]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("Patient profile not found.");
}

$sql = "SELECT a.*, u.name AS doctor_name,
               s.specialization_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN specializations s
        ON d.specialization_id = s.specialization_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.start_time DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$patient["patient_id"]]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Home page - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Patient Home page</p>
</header>

<div class="dashboard">

    <h2>
        Welcome, <?= htmlspecialchars($_SESSION["name"]) ?>!
    </h2>

    <p>Manage your hospital appointments here.</p>

    <nav>
        <a href="../index.php">Home</a>
        <a href="../logout.php">Logout</a>
    </nav>

    <h3>My Appointments</h3>

    <table>
        <tr>
            <th>Doctor</th>
            <th>Specialization</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
        </tr>

        <?php if (count($appointments) > 0): ?>

            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td><?= htmlspecialchars($appointment["doctor_name"]) ?></td>

                    <td><?= htmlspecialchars(
                        $appointment["specialization_name"] ?? "Not specified"
                    ) ?></td>

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
