<?php

require_once __DIR__ . '/../src/PayTRException.php';
require_once __DIR__ . '/../src/BasketItem.php';
require_once __DIR__ . '/../src/PaymentData.php';
require_once __DIR__ . '/../src/PayTRClient.php';

use PayTR\PayTRClient;
use PayTR\PaymentData;
use PayTR\BasketItem;
use PayTR\PayTRException;

$client = new PayTRClient([
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true,
]);

try {
    $orderNumber = 'SIPARIS-' . time();
    $totalAmount = 450.75; // TL Cinsinden Toplam Tutar

    // İstemci IP Çözümleme (Cloudflare veya doğrudan bağlantı)
    $userIp = PayTRClient::resolveClientIp();

    // Ödeme Bilgilerini Tanımlama
    $payment = new PaymentData(
        merchantOid: $orderNumber,
        paymentAmount: $totalAmount,
        userIp: $userIp,
        email: 'ahmet.yilmaz@example.com',
        userName: 'Ahmet Yılmaz',
        userAddress: 'Atatürk Mahallesi Güneş Caddesi No:10 Daire:4 Kadıköy İstanbul',
        userPhone: '05551234567',
        merchantOkUrl: 'https://www.example.com/odeme/basarili',
        merchantFailUrl: 'https://www.example.com/odeme/basarisiz'
    );

    // İsteğe Bağlı Ayarlar
    $payment->noInstallment = false;   // Taksit açık
    $payment->maxInstallment = 12;     // Maksimum 12 taksit
    $payment->timeoutLimit = 30;       // 30 dakika geçerli token
    $payment->currency = 'TL';         // TL, USD, EUR, GBP
    $payment->lang = 'tr';

    // Sepet Kalemleri
    $payment->addItem(new BasketItem(name: 'Kablosuz Kulaklık Siyah', price: 350.00, quantity: 1));
    $payment->addItem(new BasketItem(name: 'Hızlı Şarj Adaptörü Type-C', price: 100.75, quantity: 1));

    echo "=== PayTR iFrame Token Alma İsteği ===\n";
    $result = $client->createIframeToken($payment);

    echo "Token Başarıyla Alındı!\n";
    echo "iFrame Token: " . $result['token'] . "\n";
    echo "iFrame Doğrudan URL: " . $result['iframe_url'] . "\n";

} catch (PayTRException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
    if ($e->getResponsePayload()) {
        print_r($e->getResponsePayload());
    }
}
