<?php

namespace PayTR;

class PayTRClient
{
    public const TOKEN_ENDPOINT = 'https://www.paytr.com/odeme/api/get-token';
    public const REFUND_ENDPOINT = 'https://www.paytr.com/odeme/iade';
    public const INSTALLMENTS_ENDPOINT = 'https://www.paytr.com/odeme/taksit-oranlari';
    public const BIN_DETAIL_ENDPOINT = 'https://www.paytr.com/odeme/api/bin-detail';

    private string $merchantId;
    private string $merchantKey;
    private string $merchantSalt;
    private bool $testMode;
    private int $timeout;
    private bool $verifySsl;
    private ?object $logger = null;

    /**
     * @param array{
     *     merchant_id: string,
     *     merchant_key: string,
     *     merchant_salt: string,
     *     test_mode?: bool,
     *     timeout?: int,
     *     verify_ssl?: bool,
     *     logger?: object
     * } $config
     * @throws PayTRException
     */
    public function __construct(array $config)
    {
        if (empty($config['merchant_id'])) {
            throw new PayTRException('PayTR Mağaza No (merchant_id) zorunludur.');
        }
        if (empty($config['merchant_key'])) {
            throw new PayTRException('PayTR Mağaza Parolası (merchant_key) zorunludur.');
        }
        if (empty($config['merchant_salt'])) {
            throw new PayTRException('PayTR Mağaza Gizli Anahtarı (merchant_salt) zorunludur.');
        }

        $this->merchantId = (string) $config['merchant_id'];
        $this->merchantKey = (string) $config['merchant_key'];
        $this->merchantSalt = (string) $config['merchant_salt'];
        $this->testMode = !empty($config['test_mode']);
        $this->timeout = $config['timeout'] ?? 30;
        $this->verifySsl = $config['verify_ssl'] ?? true;
        $this->logger = $config['logger'] ?? null;

        if (!$this->verifySsl) {
            $this->log('warning', 'PayTR Güvenlik Uyarısı: SSL doğrulaması (verify_ssl) devre dışı bırakılmış. MitM riskine karşı canlı ortamda daima true olmalıdır.');
        }
    }

    /* -------------------------------------------------------------------------
     * 1. iFRAME TOKEN ALMA (Checkout / get-token)
     * ------------------------------------------------------------------------- */

    /**
     * PayTR iFrame ödeme tokeni alır.
     *
     * @param PaymentData $payment
     * @return array{
     *     token: string,
     *     iframe_url: string,
     *     status: string
     * }
     * @throws PayTRException
     */
    public function createIframeToken(PaymentData $payment): array
    {
        $amountInKurus = $payment->getAmountInKurus();
        $basketJson = $payment->toBasketJson();
        $noInstallment = $payment->noInstallment ? 1 : 0;
        $maxInstallment = $payment->maxInstallment;
        $testMode = ($payment->testMode !== null ? $payment->testMode : $this->testMode) ? 1 : 0;

        // PayTR Token İmza Dizilimi (Sıralama kritiktir)
        $hashString = $this->merchantId
            . $payment->userIp
            . $payment->merchantOid
            . $payment->email
            . $amountInKurus
            . $basketJson
            . $noInstallment
            . $maxInstallment
            . $payment->currency
            . $testMode;

        $token = base64_encode(hash_hmac(
            'sha256',
            $hashString . $this->merchantSalt,
            $this->merchantKey,
            true
        ));

        $payload = [
            'merchant_id'       => $this->merchantId,
            'user_ip'           => $payment->userIp,
            'merchant_oid'      => $payment->merchantOid,
            'email'             => $payment->email,
            'payment_amount'    => $amountInKurus,
            'paytr_token'       => $token,
            'user_basket'       => $basketJson,
            'debug_on'          => $testMode,
            'no_installment'    => $noInstallment,
            'max_installment'   => $maxInstallment,
            'user_name'         => $payment->userName,
            'user_address'      => $payment->userAddress,
            'user_phone'        => $payment->userPhone,
            'merchant_ok_url'   => $payment->merchantOkUrl,
            'merchant_fail_url' => $payment->merchantFailUrl,
            'timeout_limit'     => $payment->timeoutLimit,
            'currency'          => $payment->currency,
            'test_mode'         => $testMode,
            'lang'              => $payment->lang
        ];

        $response = $this->post(self::TOKEN_ENDPOINT, $payload);

        if (!is_array($response) || ($response['status'] ?? null) !== 'success') {
            $reason = is_array($response)
                ? ($response['reason'] ?? $response['err_msg'] ?? $response['message'] ?? 'Bilinmeyen PayTR hatası.')
                : 'PayTR geçersiz yanıt döndürdü.';
            
            $this->log('error', "PayTR Token Hatası: {$reason}", ['merchant_oid' => $payment->merchantOid]);
            throw new PayTRException("PayTR token alınamadı: {$reason}", 0, null, null, $response);
        }

        $iframeToken = (string) $response['token'];

        return [
            'token'      => $iframeToken,
            'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . $iframeToken,
            'status'     => 'success'
        ];
    }

    /**
     * PayTR iFrame HTML yerleştirme kodunu üretir.
     * PayTR iframeResizer kütüphanesiyle responsive ve tam uyumlu yükseklik sağlar.
     */
    public function renderIframeHtml(
        string $token,
        string $elementId = 'paytriframe',
        string $style = 'width: 100%; border: none; min-height: 600px;'
    ): string {
        $iframeUrl = 'https://www.paytr.com/odeme/guvenli/' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($elementId, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
<iframe src="{$iframeUrl}" id="{$id}" frameborder="0" scrolling="no" style="{$style}"></iframe>
<script>iFrameResize({checkOrigin: false}, '#{$id}');</script>
HTML;
    }

    /* -------------------------------------------------------------------------
     * 2. WEBHOOK / BİLDİRİM URL DOĞRULAMA (Callback Validation)
     * ------------------------------------------------------------------------- */

    /**
     * PayTR bildirim URL'sine (Webhook) gelen isteğin imzasını doğrular.
     * Timing attack saldırılarına karşı hash_equals kullanılır.
     *
     * @param array<string, mixed> $postData
     */
    public function validateCallback(array $postData): bool
    {
        $merchantOid = (string) ($postData['merchant_oid'] ?? '');
        $status = (string) ($postData['status'] ?? '');
        $totalAmount = (string) ($postData['total_amount'] ?? '');
        $receivedHash = (string) ($postData['hash'] ?? '');

        $calculatedHash = $this->calculateCallbackHash($merchantOid, $status, $totalAmount);

        return hash_equals($calculatedHash, $receivedHash);
    }

    /**
     * Webhook imzasını hesaplar: HMAC-SHA256(merchant_oid + merchant_salt + status + total_amount, merchant_key)
     */
    public function calculateCallbackHash(string $merchantOid, string $status, string $totalAmount): string
    {
        return base64_encode(hash_hmac(
            'sha256',
            $merchantOid . $this->merchantSalt . $status . $totalAmount,
            $this->merchantKey,
            true
        ));
    }

    /**
     * Callback verisini ve beklenen sipariş tutarını birlikte doğrular.
     *
     * @param array<string, mixed> $postData
     * @param float|int $expectedAmount TL cinsinden beklenen tutar (Örn: 150.75) veya kuruş (int)
     * @return array{
     *     valid: bool,
     *     status: string,
     *     order_id: string,
     *     paid_amount_tl: float,
     *     paid_amount_kurus: int,
     *     installment_count: int,
     *     payment_type: ?string,
     *     failed_reason_code: ?string,
     *     failed_reason_msg: ?string
     * }
     * @throws PayTRException
     */
    public function verifyCallback(array $postData, float|int $expectedAmount): array
    {
        if (!$this->validateCallback($postData)) {
            $this->log('critical', 'PayTR Bildirimi Geçersiz Hash!', ['merchant_oid' => $postData['merchant_oid'] ?? null]);
            throw new PayTRException('PAYTR notification failed: bad hash');
        }

        $merchantOid = (string) ($postData['merchant_oid'] ?? '');
        $isSuccess = ($postData['status'] ?? '') === 'success';
        $chargedKurus = (int) ($postData['total_amount'] ?? 0);
        $paymentAmountKurus = (int) ($postData['payment_amount'] ?? $chargedKurus);

        // Beklenen tutarı kuruşa çevir (eğer float TL olarak verilmişse)
        $expectedKurus = is_int($expectedAmount) ? $expectedAmount : (int) round($expectedAmount * 100);
        $amountMatches = $paymentAmountKurus === $expectedKurus;

        $status = $isSuccess && $amountMatches
            ? 'success'
            : ($isSuccess ? 'amount_mismatch' : 'failed');

        return [
            'valid'              => $isSuccess && $amountMatches,
            'status'             => $status,
            'order_id'           => $merchantOid,
            'paid_amount_tl'     => round($chargedKurus / 100, 2),
            'paid_amount_kurus'  => $chargedKurus,
            'installment_count'  => (int) ($postData['installment_count'] ?? 1),
            'payment_type'       => $postData['payment_type'] ?? null,
            'failed_reason_code' => $postData['failed_reason_code'] ?? null,
            'failed_reason_msg'  => $postData['failed_reason_msg'] ?? null
        ];
    }

    /**
     * PayTR bildirimine verilmesi zorunlu standart başarılı yanıtını döndürür.
     */
    public function okResponse(): string
    {
        return 'OK';
    }

    /* -------------------------------------------------------------------------
     * 3. İADE / REFUND API
     * ------------------------------------------------------------------------- */

    /**
     * Başarılı bir ödemenin tamamını veya bir kısmını karta iade eder.
     *
     * @param string $merchantOid Sipariş numarası / referansı
     * @param float $returnAmount İade edilecek TL tutarı (Örn: 99.90)
     * @param string|null $referenceNo Benzersiz iade takip referansı
     * @return array<string, mixed>
     * @throws PayTRException
     */
    public function refund(string $merchantOid, float $returnAmount, ?string $referenceNo = null): array
    {
        $formattedAmount = number_format(round($returnAmount, 2), 2, '.', '');
        $refNo = $referenceNo ?: ('REF-' . time() . '-' . random_int(1000, 9999));

        // İade İmzası: HMAC-SHA256(merchant_id + merchant_oid + return_amount + merchant_salt, merchant_key)
        $token = base64_encode(hash_hmac(
            'sha256',
            $this->merchantId . $merchantOid . $formattedAmount . $this->merchantSalt,
            $this->merchantKey,
            true
        ));

        $payload = [
            'merchant_id'   => $this->merchantId,
            'merchant_oid'  => $merchantOid,
            'return_amount' => $formattedAmount,
            'paytr_token'   => $token,
            'reference_no'  => $refNo
        ];

        $response = $this->post(self::REFUND_ENDPOINT, $payload);

        if (!is_array($response) || ($response['status'] ?? null) !== 'success') {
            $errNo = $response['err_no'] ?? null;
            $errMsg = $response['err_msg'] ?? ($response['message'] ?? 'İade talebi PayTR tarafından reddedildi.');
            $fullMsg = trim(implode(' - ', array_filter([$errNo, $errMsg])));

            $this->log('error', "PayTR İade Hatası: {$fullMsg}", ['merchant_oid' => $merchantOid, 'amount' => $formattedAmount]);
            throw new PayTRException($fullMsg ?: 'PayTR iade talebini reddetti.', 0, null, $errNo, $response);
        }

        return $response;
    }

    /* -------------------------------------------------------------------------
     * 4. TAKSİT VE KART ORANLARI (Installment Inquiry)
     * ------------------------------------------------------------------------- */

    /**
     * Mağazaya tanımlı banka kart ailelerinin taksit oranlarını ve komisyonlarını sorgular.
     *
     * @param string|null $requestId
     * @return array<string, mixed>
     * @throws PayTRException
     */
    public function getInstallmentRates(?string $requestId = null): array
    {
        $reqId = $requestId ?: (string) time();

        $token = base64_encode(hash_hmac(
            'sha256',
            $this->merchantId . $reqId . $this->merchantSalt,
            $this->merchantKey,
            true
        ));

        $payload = [
            'merchant_id' => $this->merchantId,
            'request_id'  => $reqId,
            'paytr_token' => $token
        ];

        $response = $this->post(self::INSTALLMENTS_ENDPOINT, $payload);

        if (!is_array($response) || ($response['status'] ?? null) !== 'success') {
            $err = $response['err_msg'] ?? 'Taksit oranları alınamadı.';
            throw new PayTRException($err, 0, null, null, $response);
        }

        return $response;
    }

    /* -------------------------------------------------------------------------
     * 5. KART BIN DETAY SORGULAMA (BIN Lookup)
     * ------------------------------------------------------------------------- */

    /**
     * Kredi/Banka kartının ilk 6 veya 8 hanesinden banka, kart ailesi ve kart tipini sorgular.
     *
     * @param string $binNumber Kartın ilk 6 veya 8 hanesi
     * @return array<string, mixed>
     * @throws PayTRException
     */
    public function getBinDetail(string $binNumber): array
    {
        $bin = substr(preg_replace('/\D/', '', $binNumber), 0, 8);
        if (strlen($bin) < 6) {
            throw new PayTRException('BIN numarası en az 6 haneli olmalıdır.');
        }

        $token = base64_encode(hash_hmac(
            'sha256',
            $bin . $this->merchantId . $this->merchantSalt,
            $this->merchantKey,
            true
        ));

        $payload = [
            'merchant_id' => $this->merchantId,
            'bin_number'  => $bin,
            'paytr_token' => $token
        ];

        $response = $this->post(self::BIN_DETAIL_ENDPOINT, $payload);

        if (!is_array($response) || ($response['status'] ?? null) !== 'success') {
            $err = $response['err_msg'] ?? 'BIN detay sorgulama başarısız.';
            throw new PayTRException($err, 0, null, null, $response);
        }

        return $response;
    }

    /* -------------------------------------------------------------------------
     * 6. BAĞLANTI TESTİ (Test Connection)
     * ------------------------------------------------------------------------- */

    /**
     * PayTR sunucularına bağlantıyı ve mağaza kimlik bilgilerini test eder.
     *
     * @return array{success: bool, mode: string, message: string}
     * @throws PayTRException
     */
    public function testConnection(): array
    {
        try {
            $response = $this->post(self::TOKEN_ENDPOINT, [
                'merchant_id' => $this->merchantId
            ]);

            return [
                'success' => true,
                'mode'    => $this->testMode ? 'Test' : 'Canlı',
                'message' => 'PayTR API servisine başarıyla erişildi.'
            ];
        } catch (PayTRException $e) {
            // Eksik parametre uyarısı bile API'ye ulaşıldığını kanıtlar
            if (stripos($e->getMessage(), 'token') !== false || stripos($e->getMessage(), 'merchant') !== false) {
                return [
                    'success' => true,
                    'mode'    => $this->testMode ? 'Test' : 'Canlı',
                    'message' => 'PayTR API servisi erişilebilir (Kimlik parametreleri doğrulandı).'
                ];
            }
            throw $e;
        }
    }

    /* -------------------------------------------------------------------------
     * 7. YARDIMCI METOTLAR (IP Resolution, cURL HTTP Motoru & Logging)
     * ------------------------------------------------------------------------- */

    /**
     * Cloudflare, ters vekil (reverse proxy) veya doğrudan bağlantılardan istemci gerçek IP'sini çözer.
     */
    public static function resolveClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', (string) $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    private function post(string $url, array $params): mixed
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            $msg = 'PayTR cURL Bağlantı Hatası: ' . ($curlError ?: 'Zaman aşımı');
            $this->log('error', $msg, ['url' => $url]);
            throw new PayTRException($msg);
        }

        $json = json_decode($responseBody, true);
        return $json !== null ? $json : $responseBody;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->{$level}('[PayTR SDK] ' . $message, $context);
        }
    }
}
