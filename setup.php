
<?php
require_once "config/database.php";

try {
    $conn->beginTransaction();

    // Create Admin account
    $adminPassword = password_hash(
        "Admin@12345",
        PASSWORD_DEFAULT
    );

    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, password, role)
         VALUES (?, ?, ?, 'admin')"
    );

    $stmt->execute([
        "Phool Administrator",
        "admin@phool.com",
        $adminPassword
    ]);

    // Create Doctor account
    $doctorPassword = password_hash(
        "Doctor@12345",
        PASSWORD_DEFAULT
    );

    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, password, role)
         VALUES (?, ?, ?, 'doctor')"
    );

    $stmt->execute([
        "Dr. John",
        "doctor@phool.com",
        $doctorPassword
    ]);

    $doctorUserId = $conn->lastInsertId();

    // Create Doctor profile
    $stmt = $conn->prepare(
        "INSERT INTO doctors
         (user_id, specialization_id, qualification)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        $doctorUserId,
        1,
        "MBBS"
    ]);

    $conn->commit();

    echo "Admin and Doctor accounts created successfully.";

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo "Setup failed. The accounts may already exist.";
}
?>
