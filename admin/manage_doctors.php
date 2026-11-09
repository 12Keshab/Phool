
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$message = "";

// Add doctor
if (isset($_POST["add_doctor"])) {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $specialization = (int)$_POST["specialization"];
    $qualification = trim($_POST["qualification"]);

    try {
        $conn->beginTransaction();

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, role)
             VALUES (?, ?, ?, 'doctor')"
        );
        $stmt->execute([$name, $email, $hash]);

        $userId = $conn->lastInsertId();

        $stmt = $conn->prepare(
            "INSERT INTO doctors
             (user_id, specialization_id, qualification)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$userId, $specialization, $qualification]);

        $conn->commit();
        $message = "Doctor added successfully.";

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = "Could not add doctor. Check whether the email already exists.";
    }
}

// Update doctor
if (isset($_POST["update_doctor"])) {
    $doctorId = (int)$_POST["doctor_id"];
    $name = trim($_POST["name"]);
    $specialization = (int)$_POST["specialization"];
    $qualification = trim($_POST["qualification"]);

    $stmt = $conn->prepare(
        "UPDATE users u
         JOIN doctors d ON u.user_id = d.user_id
         SET u.name = ?, d.specialization_id = ?, d.qualification = ?
         WHERE d.doctor_id = ?"
    );

    $stmt->execute([
        $name, $specialization, $qualification, $doctorId
    ]);

    $message = "Doctor information updated.";
}

// Activate or deactivate doctor
if (isset($_POST["toggle_status"])) {
    $doctorId = (int)$_POST["doctor_id"];
    $newStatus = (int)$_POST["new_status"];

    $stmt = $conn->prepare(
        "UPDATE users u
         JOIN doctors d ON u.user_id = d.user_id
         SET u.is_active = ?
         WHERE d.doctor_id = ?"
    );

    $stmt->execute([$newStatus, $doctorId]);

    $message = "Doctor account status updated.";
}

// Get specializations
$specializations = $conn->query(
    "SELECT * FROM specializations ORDER BY specialization_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Get doctors
$sql = "SELECT d.doctor_id, d.qualification,
               d.specialization_id, s.specialization_name,
               u.name, u.email, u.is_active
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN specializations s
        ON d.specialization_id = s.specialization_id
        ORDER BY d.doctor_id DESC";

$doctors = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Doctors - Phool</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Doctor Management</p>
</header>

<div class="dashboard">

    <h2>Manage Doctors</h2>
    <p><?= htmlspecialchars($message) ?></p>

    <h3>Add New Doctor</h3>

    <form method="POST">
        <input type="text" name="name" placeholder="Doctor Name" required>

        <input type="email" name="email" placeholder="Email" required>

        <input type="password" name="password"
               placeholder="Temporary Password"
               minlength="8" required>

        <input type="text" name="qualification"
               placeholder="Qualification">

        <select name="specialization" required>
            <option value="">Select Specialization</option>
            <?php foreach ($specializations as $s): ?>
                <option value="<?= $s["specialization_id"] ?>">
                    <?= htmlspecialchars($s["specialization_name"]) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn" type="submit" name="add_doctor">
            Add Doctor
        </button>
    </form>

    <hr>

    <h3>Doctor List</h3>

    <?php foreach ($doctors as $d): ?>
        <div style="border:1px solid #ddd; padding:15px; margin:15px 0;">

            <form method="POST">
                <input type="hidden" name="doctor_id"
                       value="<?= $d["doctor_id"] ?>">

                <input type="text" name="name"
                       value="<?= htmlspecialchars($d["name"]) ?>" required>

                <input type="text" value="<?= htmlspecialchars($d["email"]) ?>"
                       disabled>

                <input type="text" name="qualification"
                       value="<?= htmlspecialchars($d["qualification"] ?? "") ?>">

                <select name="specialization">
                    <?php foreach ($specializations as $s): ?>
                        <option value="<?= $s["specialization_id"] ?>"
                            <?= $d["specialization_id"] == $s["specialization_id"] ? "selected" : "" ?>>
                            <?= htmlspecialchars($s["specialization_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn" name="update_doctor">
                    Update
                </button>
            </form>

            <p>
                Account:
                <?= $d["is_active"] ? "Active" : "Inactive" ?>
            </p>

            <form method="POST">
                <input type="hidden" name="doctor_id"
                       value="<?= $d["doctor_id"] ?>">

                <input type="hidden" name="new_status"
                       value="<?= $d["is_active"] ? 0 : 1 ?>">

                <button class="btn" name="toggle_status">
                    <?= $d["is_active"] ? "Deactivate" : "Activate" ?>
                </button>
            </form>

        </div>
    <?php endforeach; ?>

    <a href="dashboard.php">Back to Dashboard</a>

</div>
</body>
</html>
