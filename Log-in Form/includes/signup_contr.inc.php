<?php

declare(strict_types=1);

function is_input_empty(string $username, string $pwd, string $email) {
    if (empty($username) || empty($pwd) || empty($email)) {
        return true;
    } else {
        return false;
    }
}
function is_email_registered(object $pdo, string $email) {
    if (get_email($pdo, $email)) {
        return true;
    } else {
        return false;
    }
}

function is_username_taken(object $pdo, string $username) {
    if (get_username($pdo, $username)) {
        return true;
    } else {
        return false;
    }
}

function create_user(object $pdo, string $pwd, string $username, string $email, string $verification_code) {
    set_user($pdo, $pwd, $username, $email, $verification_code);
}