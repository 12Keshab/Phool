
<?php
session_start();
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];

    if ($name == "" || $email == "" || $password == "") {
        $message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        try {
            $conn->beginTransaction();

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $sql = "INSERT INTO users
                    (name, email, password, role)
                    VALUES (?, ?, ?, 'patient')";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $email, $hashedPassword]);

            $userId = $conn->lastInsertId();

            $sql = "INSERT INTO patients (user_id, phone)
                    VALUES (?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId, $phone]);

            $conn->commit();

            header("Location: login.php?registered=1");
            exit();

        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $message = "Registration failed. This email may already exist.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Registration - Phool</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Patient Registration</p>
</header>

<div class="container">

    <h2>Create Patient Account</h2>

    <p style="color:red;">
        <?= htmlspecialchars($message) ?>
    </p>

    <form method="POST">

        <input type="text" name="name"
               placeholder="Full Name" required>

        <input type="email" name="email"
               placeholder="Email Address" required>

        <input type="text" name="phone"
               placeholder="Phone Number">

        <input type="password" name="password"
               placeholder="Password (minimum 8 characters)"
               required minlength="8">

        <button type="submit" class="btn">
            Register
        </button>

    </form>

    <p>Already have an account?
        <a href="login.php">Login</a>
    </p>

</div>

</body>
</html>
