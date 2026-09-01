<?php

$apiKey = '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG';
$baseUrl = 'https://api.authentica.sa/api/v2';

$ch = curl_init("{$baseUrl}/balance");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Authorization: {$apiKey}",
    "Accept: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";
