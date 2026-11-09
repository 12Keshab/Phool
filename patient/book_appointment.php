
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "patient") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: search_doctors.php");
    exit();
}

$doctorId = (int)($_POST["doctor_id"] ?? 0);
$date = $_POST["date"] ?? "";
$time = $_POST["time"] ?? "";

$validDate = DateTime::createFromFormat("!Y-m-d", $date);
$validTime = DateTime::createFromFormat("!H:i:s", $time);

if (!$validDate || $validDate->format("Y-m-d") !== $date ||
    !$validTime || $validTime->format("H:i:s") !== $time ||
    $date < date("Y-m-d")) {
    die("Invalid appointment date or time.");
}

if ($date == date("Y-m-d") && $time <= date("H:i:s")) {
    die("Please choose a future appointment time.");
}

try {
    $conn->beginTransaction();

    // Lock doctor row to serialize bookings for this doctor
    $stmt = $conn->prepare(
        "SELECT d.doctor_id
         FROM doctors d
         JOIN users u ON d.user_id = u.user_id
         WHERE d.doctor_id = ? AND u.is_active = 1
         FOR UPDATE"
    );

    $stmt->execute([$doctorId]);

    if (!$stmt->fetch()) {
        throw new Exception("Doctor is unavailable.");
    }

    // Find patient profile
    $stmt = $conn->prepare(
        "SELECT patient_id FROM patients WHERE user_id = ?"
    );

    $stmt->execute([$_SESSION["user_id"]]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception("Patient profile not found.");
    }

    // Check whether doctor works at the selected time
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

    $scheduleValid = false;

    foreach ($schedules as $schedule) {
        $scheduleStart = new DateTime($schedule["start_time"]);
        $scheduleEnd = new DateTime($schedule["end_time"]);

        $diff = ($slotStart->getTimestamp() -
                 $scheduleStart->getTimestamp()) / 60;

        if ($slotStart >= $scheduleStart &&
            $slotEnd <= $scheduleEnd &&
            $diff >= 0 && $diff % 30 == 0) {
            $scheduleValid = true;
            break;
        }
    }

    if (!$scheduleValid) {
        throw new Exception("This time is outside the doctor's schedule.");
    }

    // Check for an existing active booking
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
        throw new Exception("Sorry, this appointment slot is already booked.");
    }

    // Save appointment
    $stmt = $conn->prepare(
        "INSERT INTO appointments
         (patient_id, doctor_id, appointment_date, start_time, status)
         VALUES (?, ?, ?, ?, 'Pending')"
    );

    $stmt->execute([
        $patient["patient_id"],
        $doctorId,
        $date,
        $time
    ]);

    $conn->commit();

    echo "Appointment booked successfully! Your status is Pending.";
    echo '<p><a href="dashboard.php">Go to Dashboard</a></p>';

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo htmlspecialchars($e->getMessage());
    echo '<p><a href="search_doctors.php">Try Again</a></p>';
}
?>
