<?php
// google_auth.php

session_start();

// Load Composer's autoloader
require_once 'vendor/autoload.php';

// Load configuration
require_once 'config.php';

// Create a new Google Client object
$client = new Google_Client();

// Set the credentials
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

// Add the scopes (what we want to access)
$client->addScope("email");
$client->addScope("profile");

// Generate the authentication URL
$auth_url = $client->createAuthUrl();

// Redirect the user to the Google authentication page
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit();

?>