
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

if (isset($_POST["add_schedule"])) {

    $day = $_POST["day"];
    $start = $_POST["start_time"];
    $end = $_POST["end_time"];

    $days = [
        "Monday", "Tuesday", "Wednesday",
        "Thursday", "Friday", "Saturday", "Sunday"
    ];

    if (!in_array($day, $days, true) ||
        !preg_match('/^\d{2}:\d{2}$/', $start) ||
        !preg_match('/^\d{2}:\d{2}$/', $end) ||
        $start >= $end) {

        $message = "Please enter a valid schedule.";

    } else {

        try {
            $stmt = $conn->prepare(
                "INSERT INTO doctor_schedules
                 (doctor_id, available_day, start_time, end_time)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $doctor["doctor_id"],
                $day,
                $start,
                $end
            ]);

            $message = "Schedule added successfully.";

        } catch (PDOException $e) {
            $message = "Schedule already exists or could not be added.";
        }
    }
}

// Delete schedule belonging to this doctor
if (isset($_POST["delete_schedule"])) {

    $scheduleId = (int)$_POST["schedule_id"];

    $stmt = $conn->prepare(
        "DELETE FROM doctor_schedules
         WHERE schedule_id = ? AND doctor_id = ?"
    );

    $stmt->execute([$scheduleId, $doctor["doctor_id"]]);

    $message = "Schedule deleted.";
}

$stmt = $conn->prepare(
    "SELECT * FROM doctor_schedules
     WHERE doctor_id = ?
     ORDER BY FIELD(available_day,
     'Monday','Tuesday','Wednesday','Thursday',
     'Friday','Saturday','Sunday'), start_time"
);

$stmt->execute([$doctor["doctor_id"]]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Schedule - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Manage Your Availability</p>
</header>

<div class="dashboard">

    <h2>Doctor Schedule Management</h2>

    <p><?= htmlspecialchars($message) ?></p>

    <h3>Add Working Schedule</h3>

    <form method="POST">

        <select name="day" required>
            <option value="">Select Day</option>
            <option>Monday</option>
            <option>Tuesday</option>
            <option>Wednesday</option>
            <option>Thursday</option>
            <option>Friday</option>
            <option>Saturday</option>
            <option>Sunday</option>
        </select>

        <label>Start Time</label>
        <input type="time" name="start_time" required>

        <label>End Time</label>
        <input type="time" name="end_time" required>

        <button class="btn" name="add_schedule">
            Add Schedule
        </button>

    </form>

    <h3>My Schedules</h3>

    <table>
        <tr>
            <th>Day</th>
            <th>Start</th>
            <th>End</th>
            <th>Action</th>
        </tr>

        <?php foreach ($schedules as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s["available_day"]) ?></td>
            <td><?= htmlspecialchars($s["start_time"]) ?></td>
            <td><?= htmlspecialchars($s["end_time"]) ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="schedule_id"
                           value="<?= $s["schedule_id"] ?>">
                    <button class="btn" name="delete_schedule">
                        Delete
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>

    </table>

    <a href="d_home.php">Back</a>

</div>
</body>
</html>
