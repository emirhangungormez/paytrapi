<?php

require_once __DIR__ . '/../src/PayTRException.php';
require_once __DIR__ . '/../src/BasketItem.php';
require_once __DIR__ . '/../src/PaymentData.php';
require_once __DIR__ . '/../src/PayTRClient.php';

use PayTR\PayTRClient;
use PayTR\PayTRException;

/**
 * Bu dosya, PayTR Bildirim URL'niz (Webhook Callback Endpoint) için
 * üretim ortamı standartlarında referans uygulamadır.
 * 
 * KRİTİK GÜVENLİK VE ÇALIŞMA KURALLARI:
 * 1. PayTR bu URL'ye sunucudan sunucuya (Server-to-Server) HTTP POST isteği atar.
 * 2. İstek CSRF korumasından muaf tutulmalıdır (Laravel'de bootstrap/app.php veya VerifyCsrfToken middleware).
 * 3. Gelen hash, PayTRClient::validateCallback ile doğrulanmalıdır.
 * 4. Tutar kontrolü: total_amount (kuruş) ile veritabanındaki sipariş tutarı kuruşu kuruşuna karşılaştırılmalıdır.
 * 5. İdempotency (Tekrarlayan İstek Koruması): PayTR 'OK' yanıtı alana kadar bildirimi yineler.
 *    Eğer sipariş zaten 'paid' durumundaysa tekrar stok düşmeyin, tekrar fatura kesmeyin; sadece 'OK' yanıtı verin.
 * 6. Yanıt: Çıktıda boşluk, satır atlama veya HTML olmamalı, SADECE 'OK' yazılmalıdır.
 */

$client = new PayTRClient([
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
]);

// 1. Gelen POST Verisini Alma
$post = $_POST;

if (empty($post)) {
    http_response_code(400);
    exit('PAYTR notification failed: empty payload');
}

// 2. Hash İmzası Doğrulama
if (!$client->validateCallback($post)) {
    http_response_code(400);
    exit('PAYTR notification failed: bad hash');
}

$merchantOid = (string) ($post['merchant_oid'] ?? '');
$status = (string) ($post['status'] ?? '');
$totalAmountKurus = (int) ($post['total_amount'] ?? 0);

// 3. Veritabanından Siparişi Çekme Simülasyonu
// $order = Order::findWhere('order_number', $merchantOid);
$mockOrder = [
    'order_number' => $merchantOid,
    'grand_total_tl' => 450.75, // Beklenen sipariş tutarı
    'is_paid' => false,         // Veritabanındaki ödeme durumu
];

// 4. İdempotency Kontrolü: Sipariş Zaten Ödenmiş mi?
if ($mockOrder['is_paid']) {
    // Sipariş zaten işlenmiş, mükerrer işlem yapma ve hemen OK yanıtı ver!
    exit('OK');
}

// 5. Tutar Eşleşme Kontrolü (Kuruş Hassasiyeti)
$expectedKurus = (int) round($mockOrder['grand_total_tl'] * 100);

if ($status === 'success') {
    if ($totalAmountKurus !== $expectedKurus) {
        // Tutar uyuşmuyor! Potansiyel manipülasyon veya kur farkı riski.
        // Siparişi şüpheli olarak işaretleyin.
        // $order->markAsFraud('Tutar uyuşmuyor: Beklenen: ' . $expectedKurus . ', Gelen: ' . $totalAmountKurus);
        exit('OK'); // PayTR'ın tekrar tekrar denemesini önlemek için OK yanıtı verilebilir veya incelemeye alınır.
    }

    // ÖDEME BAŞARILI!
    // $order->markAsPaid();
    // $inventory->deduct($order);
    // $notification->sendOrderSuccessEmail($order);

} else {
    // ÖDEME BAŞARISIZ!
    $failedReasonCode = $post['failed_reason_code'] ?? null;
    $failedReasonMsg = $post['failed_reason_msg'] ?? 'Bilinmeyen hata';
    
    // $order->markAsFailed($failedReasonMsg);
}

// 6. PayTR'a Başarılı Alındı Yanıtı Verme (Zorunlu)
echo "OK";
exit;
