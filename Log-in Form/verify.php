<?php
require_once 'includes/dbh.inc.php';
require_once 'includes/config_session.inc.php';

$message = '';
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}
$email = $_SESSION['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verification_code'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['is_verified'] == 0 && $user['verification_code'] === $_POST['verification_code']) {
            $stmt = $pdo->prepare('UPDATE users SET is_verified = 1 WHERE email = :email');
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            // Set session variables so user is logged in after verification
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['last_regeneration'] = time();
            $role = $user['role'];
            if ($role === 'admin') {
                header('Location: /Food_System/admin20%dashboard/adminDashboard.php');
            } else {
                header('Location: /Food_System/user%20dashboard/userDashboard.php');
            }
            exit;
        } else {
            $message = 'Invalid code or account already verified.';
        }
    }
    if (isset($_POST['resend_code'])) {
        // Generate new code
        $new_code = str_pad(strval(random_int(100000, 999999)), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('UPDATE users SET verification_code = :code WHERE email = :email');
        $stmt->bindParam(':code', $new_code);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        // Send email
        require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth = true;
        $mail->Username = 'luietan04@gmail.com';
        $mail->Password = 'cqwv qwwg zacn odnz';
        $mail->setFrom('luietan04@gmail.com', 'Arko Flavours');
        $mail->addAddress($email);
        $mail->Subject = 'Your  Verification Code';
        $mail->isHTML(true);
        $mail->Body = '<p>Your new verification code is: <b>' . $new_code . '</b></p>';
        @$mail->send();
        $message = 'Verification code resent! Check your email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./design/verify.css">
</head>
<body>

    <!-- PHP loader include -->
    <!-- include $_SERVER['DOCUMENT_ROOT'].'/Log-in%20Form/includes/loading.inc.php' -->

    <form method="post">
        <h2>Verify Your Account</h2>
        <?php if (!empty($message)) {
            echo '<p>' . $message . '</p>';
        } ?>
        <label for="verification_code">Enter the Verification Code we've sent to you.</label>
        <input type="text" name="verification_code" id="verification_code" required maxlength="10">
        <button type="submit">Verify Account</button>
    </form>

    <form method="post">
        <button type="submit" name="resend_code">Resend Verification Code</button>
    </form>

</body>
</html>
