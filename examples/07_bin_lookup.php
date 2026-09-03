<?php

require_once __DIR__ . '/../src/PayTRException.php';
require_once __DIR__ . '/../src/BasketItem.php';
require_once __DIR__ . '/../src/PaymentData.php';
require_once __DIR__ . '/../src/PayTRClient.php';

use PayTR\PayTRClient;
use PayTR\PayTRException;

$client = new PayTRClient([
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true,
]);

try {
    // Sorgulanacak Kartın İlk 6 veya 8 Hanesi
    $sampleBin = '552879'; // Örn: Bonus / Garanti BBVA kartı

    echo "=== PayTR BIN Detay Sorgulama ===\n";
    echo "Sorgulanan BIN: {$sampleBin}\n\n";

    $binDetail = $client->getBinDetail($sampleBin);

    echo "Banka Adı: " . ($binDetail['bank'] ?? 'N/A') . "\n";
    echo "Kart Ailesi: " . ($binDetail['cardFamily'] ?? $binDetail['brand'] ?? 'N/A') . "\n";
    echo "Kart Türü: " . ($binDetail['cardType'] ?? 'N/A') . " (Kredi/Banka/Ön Ödemeli)\n";
    echo "Ödeme Şeması: " . ($binDetail['schema'] ?? 'N/A') . " (MasterCard/Visa/Troy)\n";

    print_r($binDetail);

} catch (PayTRException $e) {
    echo "BIN Sorgulama Hatası: " . $e->getMessage() . "\n";
}
