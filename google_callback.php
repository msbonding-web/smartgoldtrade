<?php
// google_callback.php

session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'db_connect.php';

// Create Google Client
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

// Handle the code received from Google
if (isset($_GET['code'])) {
    try {
        // Exchange authorization code for an access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            throw new Exception('Failed to retrieve access token: ' . $token['error_description']);
        }
        
        $client->setAccessToken($token['access_token']);

        // Get user profile information from Google
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        // You can also get other info like: $google_id = $google_account_info->id;

        // Check if user already exists in your database
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // User exists, log them in
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

        } else {
            // User does not exist, create a new account
            // Generate a random password as it's required but won't be used
            $random_password = bin2hex(random_bytes(16));
            $password_hash = password_hash($random_password, PASSWORD_BCRYPT);
            
            // Create a simple username from email
            $username = explode('@', $email)[0] . rand(100, 999);
            
            $uuid = uniqid('', true); // Generate a unique ID

            // Insert new user into 'users' table
            $insert_stmt = $conn->prepare("INSERT INTO users (uuid, email, username, password_hash, status, email_verified_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $insert_stmt->bind_param("ssss", $uuid, $email, $username, $password_hash);
            $insert_stmt->execute();
            
            $new_user_id = $conn->insert_id;

            // Assign the default 'client' role (role_id = 3) to the new user
            $role_id = 3; 
            $role_stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $role_stmt->bind_param("ii", $new_user_id, $role_id);
            $role_stmt->execute();
            
            // Log the new user in
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $username;
        }
        $stmt->close();
        
        // Fetch roles for the session (for both existing and new users)
        $user_roles = [];
        $roles_stmt = $conn->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
        $roles_stmt->bind_param("i", $_SESSION['user_id']);
        $roles_stmt->execute();
        $roles_result = $roles_stmt->get_result();
        while ($role_row = $roles_result->fetch_assoc()) {
            $user_roles[] = $role_row['slug'];
        }
        $_SESSION['user_roles'] = $user_roles;
        $roles_stmt->close();

        // Redirect to the user dashboard
        header("Location: user/dashboard.php");
        exit();

    } catch (Exception $e) {
        die('An error occurred: ' . $e->getMessage());
    }
} else {
    // If no code is present, redirect to login page
    header("Location: login.php");
    exit();
}
?>