<?php

$apiKey = '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG';
$baseUrl = 'https://api.authentica.sa/api/v2';

$otp = '654321';
$hash = 'Ii43T702uXm';
$messageText = "Your Azhl verification code is {$otp}\n{$hash}";

// Test 1: POST /send-sms
$ch1 = curl_init("{$baseUrl}/send-sms");
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '+966500000000',
    'message' => $messageText,
    'text' => $messageText,
    'body' => $messageText
]));
curl_setopt($ch1, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

echo "POST /send-sms (HTTP {$httpCode1}): {$response1}\n";

// Test 2: POST /send-otp with app_hash variations
$ch2 = curl_init("{$baseUrl}/send-otp");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '+966500000000',
    'method' => 'sms',
    'otp' => $otp,
    'app_hash' => $hash,
    'app-hash' => $hash,
    'android_app_hash' => $hash,
    'hash_key' => $hash,
    'hash' => $hash,
]));
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "POST /send-otp variations (HTTP {$httpCode2}): {$response2}\n";
