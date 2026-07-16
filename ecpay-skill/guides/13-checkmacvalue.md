> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# CheckMacValue 完整解說

## 概述

CheckMacValue 是 ECPay 用於驗證請求/回應完整性的檢查碼。用於 AIO 金流和國內物流。非 PHP 開發者需要自行實作此機制。

## 使用場景

| 服務 | Hash 方法 |
|------|----------|
| AIO 金流 | SHA256 |
| 國內物流 | MD5 |
| ECTicket | SHA256（公式與 AIO 不同，見 guides/09） |
| B2C 發票 AllowanceByCollegiate（線上折讓）Callback | MD5（發票中唯一帶 CMV 的 Callback） |
| ECPG / 發票 / 全方位物流（其他 API） | 不使用，改用 AES 加密 |

## 計算流程

```
1. filter()                    — 移除參數中既有的 CheckMacValue
2. sort()                      — Key 不區分大小寫字典序排序
3. toEncodeSourceString()      — "HashKey={key}&{k1=v1&k2=v2&...}&HashIV={iv}"
4. ecpayUrlEncode()            — urlencode → 轉小寫 → .NET 特殊字元替換
5. generateHash()               — SHA256 或 MD5
6. strtoupper()                 — 轉大寫
```

> ⚠️ 空值參數處理：空字串參數（param=）仍須納入排序與組合；完全未傳送的參數則不納入。

## ECPay 專用 URL Encode

```
1. urlencode()     — 標準 URL 編碼（空格 → +）
2. strtolower()    — 全部轉小寫
3. .NET 特殊字元還原：
   %2d → -   %5f → _   %2e → .   %21 → !
   %2a → *   %28 → (   %29 → )
```

> ⚠️ 特殊字元最終輸出（跨語言最常見 Bug 來源）：
> - 空格（space） → 最終為 `+`
> - `~`（tilde） → 最終為 `%7e`（不被還原）
> - `'`（apostrophe） → 最終為 `%27`（不被還原）

## 各語言 URL Encode 行為差異

| 語言 | URL Encode 函式 | 空格編碼 | ~ / ' 編碼 | 需要額外處理 |
|------|----------------|---------|---------|-------------|
| PHP | urlencode() | + | %7E / %27 | 否（原生行為） |
| Python | urllib.parse.quote_plus() | + | ~ 不編碼 / %27 | 需替換 ~→%7e |
| Node.js | encodeURIComponent() | %20 | ~ ' 不編碼 | 需替換 %20→+、~→%7e、'→%27 |
| Java | URLEncoder.encode() | + | 視 JVM 而定 | 需替換 ~→%7e |
| C# | WebUtility.UrlEncode() | + | ~ 不編碼 | 需替換 ~→%7e |
| Go | url.QueryEscape() | + | ~ ' 不編碼 | 需替換 ~→%7e、'→%27 |

> ⚠️ 重要：ecpayUrlEncode 僅用於 CheckMacValue（CMV-SHA256 / CMV-MD5）。AES-JSON 協定（ECPG、發票、物流）使用完全不同的 aesUrlEncode（僅 urlencode，無小寫、無 .NET 替換），定義於 guides/14。絕不混用兩者。

> ⚠️ 安全警告：Timing-Safe 比較。驗證 CheckMacValue 時必須使用 timing-safe 比較函式，避免 timing attack。

| 語言 | Timing-Safe 函式 |
|------|----------------|
| PHP | hash_equals() |
| Python | hmac.compare_digest() |
| Node.js | crypto.timingSafeEqual() |
| Go | subtle.ConstantTimeCompare() |
| Java | MessageDigest.isEqual() |
| C# | CryptographicOperations.FixedTimeEquals() |

## PHP（使用 SDK 可跳過手動實作）

```php
$calculated = $checkMacValue->generate($params);
$isValid = hash_equals($calculated, $receivedCheckMacValue);
```

## Python

```python
import hashlib
import hmac
import urllib.parse

def ecpay_url_encode(source: str) -> str:
    encoded = urllib.parse.quote_plus(source)  # 空格→+
    encoded = encoded.replace('~', '%7E')
    encoded = encoded.lower()
    replacements = {
        '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!',
        '%2a': '*', '%28': '(', '%29': ')',
    }
    for old, new in replacements.items():
        encoded = encoded.replace(old, new)
    return encoded

def generate_check_mac_value(params: dict, hash_key: str, hash_iv: str, method: str = 'sha256') -> str:
    filtered = {k: v for k, v in params.items() if k != 'CheckMacValue'}
    sorted_params = sorted(filtered.items(), key=lambda x: x[0].lower())
    param_str = '&'.join(f'{k}={v}' for k, v in sorted_params)
    raw = f'HashKey={hash_key}&{param_str}&HashIV={hash_iv}'
    encoded = ecpay_url_encode(raw)
    if method == 'md5':
        hashed = hashlib.md5(encoded.encode('utf-8')).hexdigest()
    else:
        hashed = hashlib.sha256(encoded.encode('utf-8')).hexdigest()
    return hashed.upper()

def verify_check_mac_value(params: dict, hash_key: str, hash_iv: str, method: str = 'sha256') -> bool:
    received = params.get('CheckMacValue', '')
    calculated = generate_check_mac_value(params, hash_key, hash_iv, method)
    return hmac.compare_digest(received, calculated)
```

## Node.js

```javascript
const crypto = require('crypto');

function ecpayUrlEncode(source) {
  let encoded = encodeURIComponent(source).replace(/%20/g, '+').replace(/~/g, '%7e').replace(/'/g, '%27');
  encoded = encoded.toLowerCase();
  const replacements = {
    '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!',
    '%2a': '*', '%28': '(', '%29': ')',
  };
  for (const [old, char] of Object.entries(replacements)) {
    encoded = encoded.split(old).join(char);
  }
  return encoded;
}

function generateCheckMacValue(params, hashKey, hashIv, method = 'sha256') {
  const filtered = Object.fromEntries(Object.entries(params).filter(([k]) => k !== 'CheckMacValue'));
  const sorted = Object.keys(filtered).sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()));
  const paramStr = sorted.map(k => `${k}=${filtered[k]}`).join('&');
  const raw = `HashKey=${hashKey}&${paramStr}&HashIV=${hashIv}`;
  const encoded = ecpayUrlEncode(raw);
  const hash = crypto.createHash(method).update(encoded, 'utf8').digest('hex');
  return hash.toUpperCase();
}

function verifyCheckMacValue(params, hashKey, hashIv, method = 'sha256') {
  const received = params.CheckMacValue || '';
  const calculated = generateCheckMacValue(params, hashKey, hashIv, method);
  const a = Buffer.from(received);
  const b = Buffer.from(calculated);
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

module.exports = { ecpayUrlEncode, generateCheckMacValue, verifyCheckMacValue };
```

## 測試向量

### SHA256 測試向量（金流 AIO）

參數：
```
MerchantID=3002607
MerchantTradeNo=Test1234567890
MerchantTradeDate=2025/01/01 12:00:00
PaymentType=aio
TotalAmount=100
TradeDesc=測試
ItemName=測試商品
ReturnURL=https://example.com/notify
ChoosePayment=ALL
EncryptType=1
HashKey=pwFHCqoQZGmho4w6
HashIV=EkRm7iFT261dpevs
Method=SHA256
```

預期結果（SHA256）：`291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2`

### MD5 測試向量（國內物流）

參數：
```
MerchantID=2000132
LogisticsType=CVS
LogisticsSubType=UNIMART
MerchantTradeDate=2025/01/01 12:00:00
HashKey=5294y06JbISpM5x9
HashIV=v77hoKGq4kWxNNIS
Method=MD5
```

預期結果（MD5）：`545E6146FD45BDA683C88454DB34CE8D`

### 特殊字元 ' 測試向量（驗證 Node.js/TypeScript 修正）

參數：
```
MerchantID=3002607
ItemName=Tom's Shop
TotalAmount=100
HashKey=pwFHCqoQZGmho4w6
HashIV=EkRm7iFT261dpevs
Method=SHA256
```

預期結果（SHA256）：`CF0A3D4901D99459D8641516EC57210700E8A5C9AB26B1D021301E9CB93EF78D`

> encodeURIComponent("'") 不編碼 '，但 PHP urlencode("'") = %27。若未加 .replace(/'/g, '%27')，CMV 計算結果將與 ECPay 不一致。

## 常見錯誤

1. 排序不正確 — 必須不區分大小寫排序
2. URL encode 行為不同 — Node.js 的 encodeURIComponent 空格是 %20 不是 +
3. 沒有轉小寫 — URL encode 後必須全部轉小寫
4. 遺漏 .NET 替換 — 7 個特殊字元必須還原
5. Hash 沒轉大寫 — 最後結果必須全部大寫
6. 字串編碼 — 必須使用 UTF-8
7. 國內物流用了 SHA256 — 國內物流是 MD5，不是 SHA256

## 相關文件

- AES 加解密：guides/14-aes-encryption.md
- 機器可讀測試向量：test-vectors/checkmacvalue.json

> 完整 12 種語言（含 Java/C#/Go/C/C++/Rust/Swift/Kotlin/Ruby）完整實作請見官方 repo：
> https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/13-checkmacvalue.md
