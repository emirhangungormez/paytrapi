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
    $orderNumber = 'SIPARIS-10025';
    $refundAmount = 150.50; // TL Cinsinden İade Tutarı (Tam veya Kısmi İade Yapılabilir)
    $customReference = 'IADE-' . time();

    echo "=== PayTR İade (Refund) Talebi Gönderme ===\n";
    echo "Sipariş No: {$orderNumber}\n";
    echo "İade Tutarı: {$refundAmount} TL\n";
    echo "Referans Kodu: {$customReference}\n\n";

    $response = $client->refund(
        merchantOid: $orderNumber,
        returnAmount: $refundAmount,
        referenceNo: $customReference
    );

    echo "İade Başarıyla Gerçekleşti!\n";
    echo "Durum: " . ($response['status'] ?? 'success') . "\n";
    echo "Geri Dönen Tutar: " . ($response['return_amount'] ?? $refundAmount) . " TL\n";

} catch (PayTRException $e) {
    echo "İade Başarısız: " . $e->getMessage() . "\n";
    if ($e->getErrorNumber()) {
        echo "PayTR Hata No: " . $e->getErrorNumber() . "\n";
    }
}
