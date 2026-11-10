
<?php
session_start();
require_once "../config/database.php";

// Check patient login
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "patient") {
    header("Location: ../login.php");
    exit();
}

$message = "";
$success = false;

// Get selected appointment information
$doctorId = (int)($_POST["doctor_id"] ?? $_GET["doctor_id"] ?? 0);
$date = $_POST["date"] ?? $_GET["date"] ?? "";
$time = $_POST["time"] ?? $_GET["time"] ?? "";

// Validate date and time
$validDate = DateTime::createFromFormat("!Y-m-d", $date);
$validTime = DateTime::createFromFormat("!H:i:s", $time);

if (
    !$validDate ||
    $validDate->format("Y-m-d") !== $date ||
    !$validTime ||
    $validTime->format("H:i:s") !== $time ||
    $date < date("Y-m-d") ||
    $doctorId <= 0
) {
    die("Invalid appointment information.");
}

// Get patient information
$stmt = $conn->prepare(
    "SELECT patient_id FROM patients WHERE user_id = ?"
);
$stmt->execute([$_SESSION["user_id"]]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("Patient profile not found.");
}

// Get doctor information
$stmt = $conn->prepare(
    "SELECT d.doctor_id, d.qualification,
            u.name, s.specialization_name
     FROM doctors d
     JOIN users u ON d.user_id = u.user_id
     LEFT JOIN specializations s
     ON d.specialization_id = s.specialization_id
     WHERE d.doctor_id = ? AND u.is_active = 1"
);

$stmt->execute([$doctorId]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor not found or account is inactive.");
}

// Process appointment confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["confirm_booking"])) {

    try {
        $conn->beginTransaction();

        // Lock doctor row to prevent simultaneous bookings
        $stmt = $conn->prepare(
            "SELECT doctor_id FROM doctors
             WHERE doctor_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$doctorId]);

        if (!$stmt->fetch()) {
            throw new Exception("Doctor is unavailable.");
        }

        // Check doctor availability
        $day = date("l", strtotime($date));

        $stmt = $conn->prepare(
            "SELECT start_time, end_time
             FROM doctor_schedules
             WHERE doctor_id = ? AND available_day = ?"
        );

        $stmt->execute([$doctorId, $day]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $slotStart = new DateTime($time);
        $slotEnd = clone $slotStart;
        $slotEnd->modify("+30 minutes");

        $available = false;

        foreach ($schedules as $schedule) {
            $start = new DateTime($schedule["start_time"]);
            $end = new DateTime($schedule["end_time"]);

            $difference =
                ($slotStart->getTimestamp() -
                 $start->getTimestamp()) / 60;

            if (
                $slotStart >= $start &&
                $slotEnd <= $end &&
                $difference >= 0 &&
                $difference % 30 == 0
            ) {
                $available = true;
                break;
            }
        }

        if (!$available) {
            throw new Exception("Doctor is not available at this time.");
        }

        // Check if slot is already booked
        $stmt = $conn->prepare(
            "SELECT appointment_id
             FROM appointments
             WHERE doctor_id = ?
             AND appointment_date = ?
             AND start_time = ?
             AND status NOT IN ('Cancelled', 'Rejected')
             FOR UPDATE"
        );

        $stmt->execute([$doctorId, $date, $time]);

        if ($stmt->fetch()) {
            throw new Exception(
                "Sorry, this appointment slot has already been booked."
            );
        }

        // Check for past appointment time
        if (
            $date < date("Y-m-d") ||
            ($date == date("Y-m-d") && $time <= date("H:i:s"))
        ) {
            throw new Exception(
                "Please select a future appointment time."
            );
        }

        // Save appointment
        $stmt = $conn->prepare(
            "INSERT INTO appointments
             (patient_id, doctor_id, appointment_date,
              start_time, status)
             VALUES (?, ?, ?, ?, 'Pending')"
        );

        $stmt->execute([
            $patient["patient_id"],
            $doctorId,
            $date,
            $time
        ]);

        $conn->commit();

        $success = true;
        $message = "Your appointment has been booked successfully!";

    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Book Appointment - Phool</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .booking-box {
            max-width: 550px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            text-align: left;
        }

        .booking-box h2 {
            text-align: center;
            color: #247ba0;
        }

        .booking-details {
            background: #f2f7fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .booking-details p {
            margin: 12px 0;
        }

        .booking-box .btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .message {
            text-align: center;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>

<header>
    <h1>Phool</h1>
    <p>Hospital Appointment Management System</p>
</header>

<div class="booking-box">

    <h2>Confirm Your Appointment</h2>

    <?php if ($message != ""): ?>

        <div class="message <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <?php if (!$success): ?>

        <div class="booking-details">

            <p>
                <strong>Patient:</strong>
                <?= htmlspecialchars($_SESSION["name"]) ?>
            </p>

            <p>
                <strong>Doctor:</strong>
                <?= htmlspecialchars($doctor["name"]) ?>
            </p>

            <p>
                <strong>Specialization:</strong>
                <?= htmlspecialchars(
                    $doctor["specialization_name"] ?? "Not specified"
                ) ?>
            </p>

            <p>
                <strong>Qualification:</strong>
                <?= htmlspecialchars(
                    $doctor["qualification"] ?? "Not specified"
                ) ?>
            </p>

            <p>
                <strong>Appointment Date:</strong>
                <?= htmlspecialchars($date) ?>
            </p>

            <p>
                <strong>Appointment Time:</strong>
                <?= date("h:i A", strtotime($time)) ?>
            </p>

            <p>
                <strong>Appointment Status:</strong>
                Pending
            </p>

        </div>

        <form method="POST">

            <input type="hidden" name="doctor_id"
                   value="<?= $doctorId ?>">

            <input type="hidden" name="date"
                   value="<?= htmlspecialchars($date) ?>">

            <input type="hidden" name="time"
                   value="<?= htmlspecialchars($time) ?>">

            <button type="submit"
                    name="confirm_booking"
                    class="btn">
                Confirm Booking
            </button>

        </form>

    <?php else: ?>

        <p style="text-align:center;">
            Your appointment request has been saved.
            Please wait for the doctor's confirmation.
        </p>

        <a href="p_home.php" class="btn">
            Go to Patient Dashboard
        </a>

    <?php endif; ?>

    <p style="text-align:center; margin-top:20px;">
        <a href="search_doctors.php">Back to Doctor Search</a>
    </p>

</div>

</body>
</html>
