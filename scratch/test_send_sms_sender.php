<?php

$apiKey = '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG';
$baseUrl = 'https://api.authentica.sa/api/v2';

$otp = '654321';
$hash = 'Ii43T702uXm';
$messageText = "Your Azhl verification code is {$otp}\n{$hash}";

// Test with sender_name = AUTHENTICA
$ch = curl_init("{$baseUrl}/send-sms");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '+966500000000',
    'sender_name' => 'AUTHENTICA',
    'message' => $messageText,
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST /send-sms sender_name=AUTHENTICA (HTTP {$httpCode}): {$response}\n";
