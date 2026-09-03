<?php

require_once __DIR__ . '/../src/PayTRException.php';
require_once __DIR__ . '/../src/BasketItem.php';
require_once __DIR__ . '/../src/PaymentData.php';
require_once __DIR__ . '/../src/PayTRClient.php';

use PayTR\PayTRClient;
use PayTR\PaymentData;
use PayTR\BasketItem;

/**
 * PayTR Doğrudan API (Direct API / Non-iFrame 3D Secure)
 * 
 * Bu yöntem, ödeme ekranını kendi sitenizde tasarlamak istediğinizde
 * kart sahibinin kart bilgileriyle (Kart No, Son Kullanma, CVV) doğrudan
 * bankanın 3D Secure doğrulama ekranına yönlendirilmesini sağlar.
 */

$client = new PayTRClient([
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true,
]);

$payment = new PaymentData(
    merchantOid: 'DIRECT-ORDER-' . time(),
    paymentAmount: 500.00,
    userIp: PayTRClient::resolveClientIp(),
    email: 'musteri@example.com',
    userName: 'Zeynep Kaya',
    userAddress: 'Kadıköy İstanbul',
    userPhone: '05559876543',
    merchantOkUrl: 'https://www.example.com/odeme/basarili',
    merchantFailUrl: 'https://www.example.com/odeme/basarisiz'
);

$payment->addItem(new BasketItem('Premium Yıllık Üyelik', 500.00, 1));

echo "Direct API entegrasyonunda ödeme tokeni alındıktan sonra kart parametreleri ile\n";
echo "PayTR 3D Secure geçidine POST formu gönderilir.\n";
