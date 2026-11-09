
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "doctor") {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT doctor_id FROM doctors WHERE user_id = ?"
);
$stmt->execute([$_SESSION["user_id"]]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found.");
}

$message = "";

if (isset($_POST["update_status"])) {

    $appointmentId = (int)$_POST["appointment_id"];
    $newStatus = $_POST["status"];

    $allowed = [
        "Confirmed",
        "Rejected",
        "Completed",
        "Cancelled"
    ];

    if (in_array($newStatus, $allowed, true)) {

        $stmt = $conn->prepare(
            "SELECT status FROM appointments
             WHERE appointment_id = ? AND doctor_id = ?"
        );

        $stmt->execute([
            $appointmentId,
            $doctor["doctor_id"]
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        $current = $appointment["status"] ?? "";

        $validTransition =
            ($current == "Pending" &&
             in_array($newStatus, ["Confirmed", "Rejected"], true))
            ||
            ($current == "Confirmed" &&
             in_array($newStatus, ["Completed", "Cancelled"], true));

        if ($validTransition) {

            $stmt = $conn->prepare(
                "UPDATE appointments
                 SET status = ?
                 WHERE appointment_id = ? AND doctor_id = ?"
            );

            $stmt->execute([
                $newStatus,
                $appointmentId,
                $doctor["doctor_id"]
            ]);

            $message = "Appointment status updated.";

        } else {
            $message = "This status change is not allowed.";
        }
    }
}

$stmt = $conn->prepare(
    "SELECT a.*, u.name AS patient_name, p.phone
     FROM appointments a
     JOIN patients p ON a.patient_id = p.patient_id
     JOIN users u ON p.user_id = u.user_id
     WHERE a.doctor_id = ?
     ORDER BY a.appointment_date DESC, a.start_time DESC"
);

$stmt->execute([$doctor["doctor_id"]]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Appointments - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Doctor Appointment Management</p>
</header>

<div class="dashboard">

    <h2>My Appointments</h2>

    <p><?= htmlspecialchars($message) ?></p>

    <table>
        <tr>
            <th>Patient</th>
            <th>Phone</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php foreach ($appointments as $a): ?>

        <tr>
            <td><?= htmlspecialchars($a["patient_name"]) ?></td>
            <td><?= htmlspecialchars($a["phone"] ?? "") ?></td>
            <td><?= htmlspecialchars($a["appointment_date"]) ?></td>
            <td><?= htmlspecialchars($a["start_time"]) ?></td>
            <td><?= htmlspecialchars($a["status"]) ?></td>

            <td>
                <?php if ($a["status"] == "Pending"): ?>

                    <?php $options = ["Confirmed", "Rejected"]; ?>

                <?php elseif ($a["status"] == "Confirmed"): ?>

                    <?php $options = ["Completed", "Cancelled"]; ?>

                <?php else: ?>

                    <?php $options = []; ?>

                <?php endif; ?>

                <?php if (count($options) > 0): ?>
                <form method="POST">

                    <input type="hidden" name="appointment_id"
                           value="<?= $a["appointment_id"] ?>">

                    <select name="status">
                        <?php foreach ($options as $option): ?>
                            <option value="<?= $option ?>">
                                <?= $option ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="btn" name="update_status">
                        Update
                    </button>

                </form>
                <?php else: ?>
                    No action
                <?php endif; ?>
            </td>
        </tr>

        <?php endforeach; ?>

    </table>

    <a href="dashboard.php">Back to Dashboard</a>

</div>
</body>
</html>
