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
    'test_mode'     => true, // Test ortamı için true, canlı için false
    'verify_ssl'    => true
]);

try {
    echo "=== PayTR Bağlantı ve Kimlik Doğrulama Testi ===\n";
    $result = $client->testConnection();

    echo "Durum: " . ($result['success'] ? 'BAŞARILI' : 'BAŞARISIZ') . "\n";
    echo "Çalışma Modu: " . $result['mode'] . "\n";
    echo "Mesaj: " . $result['message'] . "\n";

} catch (PayTRException $e) {
    echo "Bağlantı Hatası: " . $e->getMessage() . "\n";
}
