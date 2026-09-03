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
    echo "=== PayTR Banka Taksit Oranları ve Komisyonları Sorgulama ===\n";
    $rates = $client->getInstallmentRates();

    print_r($rates);

} catch (PayTRException $e) {
    echo "Taksit Oranları Alınamadı: " . $e->getMessage() . "\n";
}
