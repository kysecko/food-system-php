<?php
session_start();
session_destroy();
session_start();

require_once 'includes/google-config.php';
require_once 'includes/dbh.inc.php';

// Helper: perform HTTP POST (prefer cURL, fallback to file_get_contents)
function http_post_json(string $url, array $postFields): array {
    $payload = http_build_query($postFields);

    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['error' => 'curl_error', 'message' => $err];
        }
        $data = json_decode($response, true);
        return $data ?? ['error' => 'invalid_json', 'raw' => $response];
    }

    // fallback
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'ignore_errors' => true,
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['error' => 'file_get_contents_failed'];
    }
    $data = json_decode($response, true);
    return $data ?? ['error' => 'invalid_json', 'raw' => $response];
}

// Helper: HTTP GET with Authorization header
function http_get_bearer(string $url, string $accessToken): array {
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['error' => 'curl_error', 'message' => $err];
        }
        $data = json_decode($response, true);
        return $data ?? ['error' => 'invalid_json', 'raw' => $response];
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer $accessToken\r\n",
            'ignore_errors' => true,
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['error' => 'file_get_contents_failed'];
    }
    $data = json_decode($response, true);
    return $data ?? ['error' => 'invalid_json', 'raw' => $response];
}

if (!isset($_GET['code'])) {
    header('Location: login.php?error=google_no_code');
    exit;
}

$code = $_GET['code'];

// Exchange code for tokens
$tokenEndpoint = 'https://oauth2.googleapis.com/token';
$post = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$tokenResponse = http_post_json($tokenEndpoint, $post);
if (isset($tokenResponse['error'])) {
    // Normalize error information
    $msg = is_array($tokenResponse['error']) ? json_encode($tokenResponse['error']) : $tokenResponse['error'];
    header('Location: login.php?error=token_exchange_failed&msg=' . urlencode($msg));
    exit;
}

if (empty($tokenResponse['access_token'])) {
    header('Location: login.php?error=no_access_token');
    exit;
}

$accessToken = $tokenResponse['access_token'];

// Fetch user info
$userinfoEndpoint = 'https://www.googleapis.com/oauth2/v2/userinfo';
$userInfo = http_get_bearer($userinfoEndpoint, $accessToken);
if (isset($userInfo['error'])) {
    $msg = is_array($userInfo['error']) ? json_encode($userInfo['error']) : $userInfo['error'];
    header('Location: login.php?error=userinfo_failed&msg=' . urlencode($msg));
    exit;
}

$email = $userInfo['email'] ?? null;
$name = $userInfo['name'] ?? ($userInfo['given_name'] ?? 'GoogleUser');

if (empty($email)) {
    header('Location: login.php?error=no_email');
    exit;
}

// Check if user exists
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    header('Location: login.php?error=db_error&msg=' . urlencode($e->getMessage()));
    exit;
}

if (!$user) {
    // Create new user with verification
    $randomPwd = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    try {
        // Start with a clean session
        session_destroy();
        session_start();
        
        $ins = $pdo->prepare('INSERT INTO users (username, email, pwd, is_verified) VALUES (?, ?, ?, 1)');
        $ins->execute([$name, $email, $randomPwd]);
        $userId = $pdo->lastInsertId();
    } catch (Throwable $e) {
        header('Location: login.php?error=db_insert_failed&msg=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    $userId = $user['id'];
}

// Set session and redirect
$newSessionId = session_create_id();
$sessionId = $newSessionId . "_" . $userId;
session_id($sessionId);

$_SESSION["user_id"] = $userId;
$_SESSION["user_username"] = $name;
$_SESSION['email'] = $email;
$_SESSION["last_regeneration"] = time();

// Redirect to the user dashboard landing page
header('Location: ../user dashboard/userDashboardLanding.php');
exit;
?>