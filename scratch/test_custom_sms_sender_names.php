<?php

$apiKey = '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG';
$baseUrl = 'https://api.authentica.sa/api/v2';

$otp = '654321';
$hash = 'Ii43T702uXm';
$messageText = "Your Azhl verification code is {$otp}\n{$hash}";

$senders = ['AUTHENTICA', 'Authentica', 'authentica', 'Default', 'DEFAULT', 'AZHL', 'Azhl', 'SMS', 'OTP'];

foreach ($senders as $sender) {
    $ch = curl_init("{$baseUrl}/send-sms");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'phone' => '+966500000000',
        'sender_name' => $sender,
        'message' => $messageText,
        'text' => $messageText,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Authorization: {$apiKey}",
        "Accept: application/json",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Sender '{$sender}' (HTTP {$httpCode}): {$response}\n";
}
