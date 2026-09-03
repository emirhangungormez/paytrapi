<?php

require_once __DIR__ . '/../src/PayTRException.php';
require_once __DIR__ . '/../src/BasketItem.php';
require_once __DIR__ . '/../src/PaymentData.php';
require_once __DIR__ . '/../src/PayTRClient.php';

use PayTR\PayTRClient;
use PayTR\PaymentData;
use PayTR\BasketItem;

$client = new PayTRClient([
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true,
]);

// Token oluşturma simülasyonu
$payment = new PaymentData(
    merchantOid: 'ORDER-99124',
    paymentAmount: 250.00,
    userIp: '127.0.0.1',
    email: 'test@example.com',
    userName: 'Ayşe Demir',
    userAddress: 'Adres...',
    userPhone: '05551112233',
    merchantOkUrl: 'https://example.com/ok',
    merchantFailUrl: 'https://example.com/fail'
);
$payment->addItem(new BasketItem('Kitap Seti', 250.00, 1));

$token = 'sample_paytr_token_string';
try {
    $res = $client->createIframeToken($payment);
    $token = $res['token'];
} catch (\Throwable $e) {
    // Test amaçlı fallback
}

// iFrame HTML çıktısı üretme
$iframeHtml = $client->renderIframeHtml($token);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Güvenli Ödeme</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; }
        .payment-container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 24px; }
        h1 { font-size: 20px; margin-bottom: 16px; color: #1f2937; text-align: center; }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>PayTR 256-Bit SSL Güvenli Ödeme Ekranı</h1>
        <!-- PayTR iFrame Embed -->
        <?= $iframeHtml ?>
    </div>
</body>
</html>
