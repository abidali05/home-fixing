<?php

$apiKey = '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG';
$baseUrl = 'https://api.authentica.sa/api/v2';

$otp = '654321';
$hash = 'Ii43T702uXm';
$customText = "Your Azhl verification code is {$otp}\n{$hash}";

$ch = curl_init("{$baseUrl}/send-otp");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '+966500000000',
    'method' => 'sms',
    'otp' => $otp,
    'message' => $customText,
    'body' => $customText,
    'text' => $customText,
    'sms_text' => $customText,
    'app_hash' => $hash,
    'hash' => $hash
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Full Custom Text Response (HTTP {$httpCode}): {$response}\n";
