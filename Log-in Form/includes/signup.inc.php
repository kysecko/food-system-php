<?php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $pwd = $_POST["pwd"];
    $email = $_POST["email"];

    try {
        require_once 'dbh.inc.php';
        require_once 'signup_model.inc.php';
        require_once 'signup_view.inc.php';
        require_once 'signup_contr.inc.php';


        $errors = [];

        if (is_input_empty($username, $pwd, $email)) {
            $errors["empty_input"] = "Fill in all fields!";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["invalid_email"] = "Invalid email";
        }
        if (is_username_taken($pdo, $username)) {
            $errors["username_taken"] = "Username already taken!";
        }
        if (is_email_registered($pdo, $email)) {
            $errors["email_used"] = "Email already registered!";
        }

        require_once 'config_session.inc.php';

        if ($errors) {
            $_SESSION["errors_signup"] = $errors;

            $signupData = [
                "username" => $username,
                "email" => $email
            ];

            $_SESSION["signup_data"] = $signupData;

            header("Location: ../signup.php");
            die();
        }


    $verification_code = str_pad(strval(random_int(100000, 999999)), 6, '0', STR_PAD_LEFT);
    create_user($pdo, $pwd, $username, $email, $verification_code);

        require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth = true;
        $mail->Username = 'luietan04@gmail.com';
        $mail->Password = 'cqwv qwwg zacn odnz';
        $mail->setFrom('luietan04@gmail.com', 'Food Shop');
        $mail->addAddress($email, $username);
        $mail->Subject = 'Welcome to Food Shop! Please Verify Your Account';
        $mail->isHTML(true);
        $mail->Body = "<div style='max-width:480px;margin:40px auto;padding:32px;background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,0.08);font-family:sans-serif;'>"
            . "<h2 style='color:#2e7d32;text-align:center;margin-bottom:16px;'>Welcome to Food Shop!</h2>"
            . "<p style='text-align:center;'>Hello <b>$username</b>,</p>"
            . "<p style='text-align:center;'>Thank you for signing up! To complete your registration, please enter the verification code below on the website:</p>"
            . "<div style='margin:24px auto 24px auto;max-width:220px;background:#fffbe6;border:2px dashed #ffd600;border-radius:8px;padding:16px;text-align:center;font-size:2rem;font-weight:700;color:#ff9800;letter-spacing:4px;'>$verification_code</div>"
            . "<p style='text-align:center;color:#888;font-size:0.95rem;'>If you did not request this, please ignore this email.</p>"
            . "<p style='text-align:center;margin-top:32px;color:#2e7d32;font-weight:600;'>Best regards,<br>Food Shop Team</p>"
            . "</div>";

        if ($mail->send()) {
            $_SESSION['email'] = $email;
            header("Location: ../verify.php");
            exit;
        } else {
            echo "Mailer Error: " . $mail->ErrorInfo;
            exit;
        }

        $pdo = null;
        $stmt = null;

        die();
    } catch (PDOException $e) {
        die('Query failed: ' . $e->getMessage());
    }
} else {
    header("Location: ../signup.php");
    die();
}
