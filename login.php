
<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);#keeshaab12@gmail.com
    $password = $_POST["password"];#keshab1234

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        if ($user["role"] == "patient") {
            header("Location: patient/p_home.php");
        } elseif ($user["role"] == "doctor") {
            header("Location: doctor/d_home.php");
        } elseif ($user["role"] == "admin") {
            header("Location: admin/dashboard.php");
        }

        exit();

    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Phool</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>Phool</h1>
    <p>Login to Your Account</p>
</header>

<div class="container">

    <h2>Login</h2>

    <?php if (isset($_GET["registered"])): ?>
        <p style="color:green;">
            Registration successful! Please login.
        </p>
    <?php endif; ?>

    <p style="color:red;">
        <?= htmlspecialchars($error) ?>
    </p>

    <form method="POST">

        <input type="email" name="email"
               placeholder="Email Address" required>

        <input type="password" name="password"
               placeholder="Password" required>

        <button type="submit" class="btn">
            Login
        </button>

    </form>

    <p>Don't have an account?
        <a href="register.php">Register</a>
    </p>

</div>

</body>
</html>
