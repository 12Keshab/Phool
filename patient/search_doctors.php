
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "patient") {
    header("Location: ../login.php");
    exit();
}

$date = $_GET["date"] ?? date("Y-m-d");
$validDate = DateTime::createFromFormat("!Y-m-d", $date);

if (!$validDate || $validDate->format("Y-m-d") !== $date ||
    $date < date("Y-m-d")) {
    $date = date("Y-m-d");
}

$day = date("l", strtotime($date));

$sql = "SELECT d.doctor_id, d.qualification,
               u.name, s.specialization_name
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN specializations s
        ON d.specialization_id = s.specialization_id
        WHERE u.is_active = 1
        ORDER BY u.name";

$doctors = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Search Doctors - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Find and Book a Doctor</p>
</header>

<div class="dashboard">

    <h2>Search Doctors</h2>

    <form method="GET">
        <label>Select Appointment Date:</label>

        <input type="date" name="date"
               min="<?= date("Y-m-d") ?>"
               value="<?= htmlspecialchars($date) ?>" required>

        <button class="btn" type="submit">Search</button>
    </form>

    <hr>

    <?php foreach ($doctors as $doctor): ?>

        <div style="border:1px solid #ddd; padding:15px; margin:15px 0;">

            <h3><?= htmlspecialchars($doctor["name"]) ?></h3>

            <p>
                Specialization:
                <?= htmlspecialchars($doctor["specialization_name"] ?? "Not specified") ?>
            </p>

            <p>
                Qualification:
                <?= htmlspecialchars($doctor["qualification"] ?? "Not specified") ?>
            </p>

            <h4>Available Times for <?= htmlspecialchars($date) ?></h4>

            <?php
            $stmt = $conn->prepare(
                "SELECT start_time, end_time
                 FROM doctor_schedules
                 WHERE doctor_id = ? AND available_day = ?"
            );

            $stmt->execute([$doctor["doctor_id"], $day]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare(
                "SELECT start_time
                 FROM appointments
                 WHERE doctor_id = ?
                 AND appointment_date = ?
                 AND status NOT IN ('Cancelled', 'Rejected')"
            );

            $stmt->execute([$doctor["doctor_id"], $date]);
            $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $booked = array_map(
                fn($t) => substr($t, 0, 8),
                $booked
            );

            $availableCount = 0;
            ?>

            <?php foreach ($schedules as $schedule): ?>

                <?php
                $start = new DateTime($schedule["start_time"]);
                $end = new DateTime($schedule["end_time"]);

                while (true) {
                    $slot = $start->format("H:i:s");

                    $next = clone $start;
                    $next->modify("+30 minutes");

                    if ($next > $end) {
                        break;
                    }

                    if (!in_array($slot, $booked, true) &&
                        ($date > date("Y-m-d") || $slot > date("H:i:s"))) {

                        $availableCount++;
                        ?>

                        <form method="POST" action="book_appointment.php"
                              style="display:inline-block;">

                            <input type="hidden" name="doctor_id"
                                   value="<?= $doctor["doctor_id"] ?>">

                            <input type="hidden" name="date"
                                   value="<?= htmlspecialchars($date) ?>">

                            <input type="hidden" name="time"
                                   value="<?= $slot ?>">

                            <button class="btn" type="submit">
                                <?= $start->format("h:i A") ?>
                            </button>
                        </form>

                        <?php
                    }

                    $start->modify("+30 minutes");
                }
                ?>

            <?php endforeach; ?>

            <?php if ($availableCount == 0): ?>
                <p>No available appointments for this date.</p>
            <?php endif; ?>

        </div>

    <?php endforeach; ?>

    <a href="p_home.php">Back</a>

</div>
</body>
</html>
