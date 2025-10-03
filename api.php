<?php
// api.php

function token_cache_path()
{
    return __DIR__ . '/cache/token.json';
}

function getAccessToken()
{
    $cacheFile = token_cache_path();

    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }

    // Check cache
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached 
            && isset($cached['access_token'], $cached['expires_at'], $cached['token_type']) 
            && time() < $cached['expires_at'] - 30) {
            return $cached; // return array
        }
    }

    // Request a new token
    $loginUrl = "https://api.baubuddy.de/index.php/login";
    $payload = json_encode(["username" => "365", "password" => "1"]);

    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic QVBJX0V4cGxvcmVyOjEyMzQ1NmlzQUxhbWVQYXNz",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Login cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $json = json_decode($resp, true);

    if (!$json || !isset($json['oauth']['access_token'])) {
        error_log("Login failed or unexpected response: " . $resp);
        return null;
    }

    $token     = $json['oauth']['access_token'];
    $tokenType = $json['oauth']['token_type'] ?? 'Bearer';
    $expiresIn = intval($json['oauth']['expires_in'] ?? 3300);

    $cacheData = [
        'access_token' => $token,
        'token_type'   => $tokenType,
        'expires_at'   => time() + $expiresIn
    ];

    file_put_contents($cacheFile, json_encode($cacheData));

    return $cacheData; // return array
}

function fetchTasksFromRemote()
{
    $data = getAccessToken();
    if (empty($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not get access token']);
        exit;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.baubuddy.de/dev/index.php/v1/tasks/select",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: " . $data['token_type'] . " " . $data['access_token'],
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return "cURL Error #:" . $err;
    } else {
        return $response; 
    }
}

// If called directly, show token info
// if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
//     $token = getAccessToken();
//     echo json_encode($token, JSON_PRETTY_PRINT);
// }
