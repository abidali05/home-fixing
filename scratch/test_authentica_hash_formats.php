<?php

$apiKey = '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG';
$baseUrl = 'https://api.authentica.sa/api/v2';

// Test 1: Passing message field with App Hash
$ch1 = curl_init("{$baseUrl}/send-otp");
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '+966500000000',
    'method' => 'sms',
    'otp' => '654321',
    'message' => "Your Azhl verification code is 654321\nIi43T702uXm",
    'app_hash' => 'Ii43T702uXm'
]));
curl_setopt($ch1, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

echo "Test 1 (message field): HTTP {$httpCode1} -> {$response1}\n";

// Test 2: Passing template_id = 31 or custom template params
$ch2 = curl_init("{$baseUrl}/send-otp");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '+966500000000',
    'method' => 'sms',
    'otp' => '654321',
    'template_id' => 31,
    'app_hash' => 'Ii43T702uXm'
]));
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Test 2 (template_id 31): HTTP {$httpCode2} -> {$response2}\n";
