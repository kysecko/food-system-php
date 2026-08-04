<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/facebook-config.php';
require_once 'includes/dbh.inc.php';

function getFacebookAccessToken($code) {
    $token_url = "https://graph.facebook.com/v12.0/oauth/access_token";
    $params = [
        'client_id' => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'redirect_uri' => FACEBOOK_REDIRECT_URI,
        'code' => $code
    ];

    $response = file_get_contents($token_url . '?' . http_build_query($params));
    return json_decode($response, true);
}

function getFacebookUserData($access_token) {
    $graph_url = "https://graph.facebook.com/v12.0/me?fields=id,name,email&access_token=" . $access_token;
    $response = file_get_contents($graph_url);
    return json_decode($response, true);
}

if (isset($_GET['code'])) {
    try {
        $token_data = getFacebookAccessToken($_GET['code']);
        
        if (isset($token_data['access_token'])) {
            $user_data = getFacebookUserData($token_data['access_token']);
            
            if (isset($user_data['email'])) {
                // Start with a clean session
                session_destroy();
                session_start();
                
                // Check if user exists
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
                $stmt->execute([$user_data['email']]);
                $existing_user = $stmt->fetch();

                if ($existing_user) {
                    // User exists - log them in
                    $newSessionId = session_create_id();
                    $sessionId = $newSessionId . "_" . $existing_user["id"];
                    session_id($sessionId);
                    
                    $_SESSION["user_id"] = $existing_user["id"];
                    $_SESSION["user_username"] = $existing_user["username"];
                    $_SESSION['email'] = $existing_user['email'];
                    $_SESSION["last_regeneration"] = time();
                    
                    if ($existing_user['role'] === 'admin') {
                        header("Location: ../admin dashboard/adminDashboard.php");
                    } else {
                        header("Location: ../user dashboard/userDashboard.php");
                    }
                } else {
                    // Create new user
                    $username = explode('@', $user_data['email'])[0];
                    $random_password = bin2hex(random_bytes(16));
                    $hashed_pwd = password_hash($random_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, pwd, is_verified) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$username, $user_data['email'], $hashed_pwd]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Set up session
                    $newSessionId = session_create_id();
                    $sessionId = $newSessionId . "_" . $userId;
                    session_id($sessionId);
                    
                    $_SESSION["user_id"] = $userId;
                    $_SESSION["user_username"] = $username;
                    $_SESSION['email'] = $user_data['email'];
                    $_SESSION["last_regeneration"] = time();
                    
                    header("Location: ../user dashboard/userDashboard.php");
                }
                exit();
            }
        }
    } catch (Exception $e) {
        header('Location: login.php?error=facebook_error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

header('Location: login.php?error=facebook_failed');
exit();
?>