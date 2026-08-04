<?php

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? '');
    $pwd = trim($_POST["pwd"] ?? '');

    try {
        require_once 'dbh.inc.php';
        require_once 'login_model.inc.php';
        require_once 'login_contr.inc.php';

        $errors = [];

        if (is_input_empty($username, $pwd)) {
            $errors["empty_input"] = "Please fill in all fields.";
        }

        $result = get_user($pdo, $username);

        if (is_username_wrong($result)) {
            $errors["login_incorrect"] = "Incorrect username or password.";
        }

        if (!is_username_wrong($result) && is_password_wrong($pwd, $result["pwd"])) {
            $errors["login_incorrect"] = "Incorrect username or password.";
        }

        require_once 'config_session.inc.php';

        if ($errors) {
            $_SESSION["errors_login"] = $errors;
            header("Location: ../login.php");
            exit();
        }

        $newSessionId = session_create_id();
        $sessionId = $newSessionId . "_" . $result["id"];
        session_id($sessionId);

        $_SESSION["user_id"] = $result["id"];
        $_SESSION["user_username"] = htmlspecialchars($result["username"]);
        $_SESSION["user_role"] = $result["role"];
        $_SESSION["email"] = $result["email"];
        $_SESSION["last_regeneration"] = time();

        if ($result["role"] === "admin") {
            header("Location: ../../admin%20dashboard/adminDashboard.php");
        } else {
            header("Location: ../../user%20dashboard/userDashboard.php");
        }

        $pdo = null;
        $stmt = null;
        exit();

    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }

} else {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form action="../../admin%20dashboard/adminDashboard.php"></form>
</body>
</html>