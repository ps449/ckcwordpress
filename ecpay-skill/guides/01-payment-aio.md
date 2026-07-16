> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 全方位金流 AIO 完整指南

## 概述

AIO（All-In-One）是 ECPay 最常用的金流整合方案，將消費者導向綠界標準付款頁面，支援 10+ 種付款方式。適合絕大多數電商場景。

## 前置需求

- MerchantID / HashKey / HashIV（測試：3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs）
- PHP SDK：`composer require ecpay/sdk`
- 加密方式：CheckMacValue SHA256

> ⚠️ 安全提醒：本指南範例中的 HashKey / HashIV 為公開測試值。正式環境禁止在程式碼中硬編碼 — 務必使用環境變數或密鑰管理服務。

## 🚀 首次串接：最快成功路徑

### 前置確認清單

> ⚠️ ItemName 長度限制（常見掉單原因）：ItemName 超過 400 字元會被 ECPay 截斷，截斷處的 UTF-8 多位元組字元產生亂碼，導致 CheckMacValue 不一致 → 掉單。官方建議不超過 200 字元。

- [ ] 測試帳號就緒：MerchantID `3002607` / HashKey `pwFHCqoQZGmho4w6` / HashIV `EkRm7iFT261dpevs`
- [ ] ReturnURL 可公開訪問（localhost 無效，用 ngrok 或部署到可訪問的主機）
- [ ] CheckMacValue 演算法已實作並通過測試向量（見 guides/13）
- [ ] PHP SDK 已安裝（`composer require ecpay/sdk`）或已自行實作 CMV-SHA256 請求格式
- [ ] 付款方式先用 `ChoosePayment=Credit`

### 步驟 1：後端建立訂單

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'        => '3002607',
    'MerchantTradeNo'   => 'AIO' . time(),
    'MerchantTradeDate' => date('Y/m/d H:i:s'),
    'PaymentType'       => 'aio',
    'TotalAmount'       => 100,
    'TradeDesc'         => 'Test',
    'ItemName'          => '測試商品',
    'ReturnURL'         => 'https://你的網站/ecpay/notify',
    'ChoosePayment'     => 'Credit',
    'EncryptType'       => 1,
];
echo $autoSubmitFormService->generate($input, 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5');
```

### 步驟 2：消費者在綠界付款頁完成付款

測試信用卡號：`4311-9522-2222-2222`，有效期限任意未來日期，CVV `222`，3DS 驗證碼：`1234`。

### 步驟 3：接收 ReturnURL 付款通知

綠界以 Server-to-Server Form POST 呼叫你的 ReturnURL。必須回應 `1|OK`，否則綠界會每 5-15 分鐘重試，最多每日 4 次。

```python
# Python / Flask — ReturnURL 接收（AIO 金流，Form POST + CheckMacValue SHA256）
import hmac, hashlib, urllib.parse
from flask import Flask, request

app = Flask(__name__)
HASH_KEY = 'pwFHCqoQZGmho4w6'
HASH_IV  = 'EkRm7iFT261dpevs'

def verify_check_mac_value(params: dict) -> bool:
    received = params.get('CheckMacValue', '')
    sorted_params = sorted(((k, v) for k, v in params.items() if k != 'CheckMacValue'), key=lambda x: x[0].lower())
    raw = f'HashKey={HASH_KEY}&' + '&'.join(f'{k}={v}' for k, v in sorted_params) + f'&HashIV={HASH_IV}'
    encoded = urllib.parse.quote_plus(raw).replace('~', '%7e').lower()
    for orig, repl in [('%2d','-'),('%5f','_'),('%2e','.'),('%21','!'),('%2a','*'),('%28','('),('%29',')')]:
        encoded = encoded.replace(orig, repl)
    computed = hashlib.sha256(encoded.encode()).hexdigest().upper()
    return hmac.compare_digest(computed, received.upper())

@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    params = request.form.to_dict()
    if not verify_check_mac_value(params):
        import logging; logging.error('ECPay callback CheckMacValue 驗證失敗')
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    if params.get('RtnCode') == '1':
        trade_no = params['MerchantTradeNo']
        print(f'[ReturnURL] 付款成功 訂單={trade_no}')
    return '1|OK', 200, {'Content-Type': 'text/plain'}
```

```javascript
// Node.js / Express — ReturnURL 接收
const express = require('express');
const crypto  = require('crypto');
const app = express();
app.use(express.urlencoded({ extended: true }));
const HASH_KEY = 'pwFHCqoQZGmho4w6';
const HASH_IV  = 'EkRm7iFT261dpevs';

function verifyCheckMacValue(params) {
  const { CheckMacValue: received, ...rest } = params;
  const sorted = Object.entries(rest).sort(([a], [b]) => a.toLowerCase().localeCompare(b.toLowerCase()));
  let raw = `HashKey=${HASH_KEY}&` + sorted.map(([k,v]) => `${k}=${v}`).join('&') + `&HashIV=${HASH_IV}`;
  let encoded = encodeURIComponent(raw).replace(/%20/g,'+').replace(/~/g,'%7e').replace(/'/g,'%27').toLowerCase()
    .replace(/%2d/g,'-').replace(/%5f/g,'_').replace(/%2e/g,'.')
    .replace(/%21/g,'!').replace(/%2a/g,'*').replace(/%28/g,'(').replace(/%29/g,')');
  const computed = crypto.createHash('sha256').update(encoded).digest('hex').toUpperCase();
  const bufA = Buffer.from(computed), bufB = Buffer.from((received || '').toUpperCase());
  if (bufA.length !== bufB.length) return false;
  return crypto.timingSafeEqual(bufA, bufB);
}

app.post('/ecpay/notify', (req, res) => {
  if (!verifyCheckMacValue(req.body)) {
    console.error('[ECPay] callback CheckMacValue 驗證失敗');
    return res.type('text').send('1|OK');
  }
  if (req.body.RtnCode === '1') {
    console.log('[ReturnURL] 付款成功 訂單=', req.body.MerchantTradeNo);
  }
  res.type('text').send('1|OK');
});
```

## HTTP 協議速查

| 項目 | 規格 |
|------|------|
| HTTP 方法 | POST |
| Content-Type | application/x-www-form-urlencoded |
| 認證 | CheckMacValue（SHA256） |
| 正式環境 | https://payment.ecpay.com.tw |
| 測試環境 | https://payment-stage.ecpay.com.tw |
| 建單回應 | HTML 頁面（瀏覽器重導至綠界付款頁） |
| Callback | Form POST 至 ReturnURL，必須回應 `1\|OK` |

### 端點 URL 一覽

| 功能 | 端點路徑 | 回應格式 |
|------|---------|---------|
| 建立訂單 | /Cashier/AioCheckOut/V5 | HTML（重導） |
| 查詢訂單 | /Cashier/QueryTradeInfo/V5 | URL-encoded |
| 信用卡請退款 | /CreditDetail/DoAction（⚠️ Stage 環境不可用） | URL-encoded |
| 對帳檔下載 | /PaymentMedia/TradeNoAio（domain 為 vendor.ecpay.com.tw） | text |

## AIO 共用必填參數

| 參數 | 類型 | 長度 | 說明 |
|------|------|------|------|
| MerchantID | String | 10 | 特店編號 |
| MerchantTradeNo | String | 20 | 特店交易編號（永久唯一，不可重複） |
| MerchantTradeDate | String | 20 | 交易時間 yyyy/MM/dd HH:mm:ss |
| PaymentType | String | 20 | 固定值 aio |
| TotalAmount | Int | — | 交易金額（新台幣整數） |
| TradeDesc | String | 200 | 交易描述 |
| ItemName | String | 400 | 商品名稱（多項用 # 分隔） |
| ReturnURL | String | 200 | 付款結果通知 URL（Server 端） |
| ChoosePayment | String | 20 | ALL / Credit / ATM / CVS / BARCODE / WebATM / ApplePay / TWQR / BNPL / WeiXin |
| EncryptType | Int | — | 固定值 1（SHA256） |

### 選用參數

| 參數 | 說明 |
|------|------|
| ClientBackURL | 消費者付款完成後導回的網址（前端） |
| OrderResultURL | 付款完成後導向並帶回結果的網址 |
| IgnorePayment | 排除的付款方式 |
| CustomField1~4 | 自訂欄位（Callback 會原樣回傳） |

> ⚠️ 三個 URL 用途不同，不可設為同一 URL：ReturnURL（Server-to-Server，必須回 1|OK）、OrderResultURL（瀏覽器導向，不需回 1|OK）、ClientBackURL（消費者按取消/返回時導回）。

## 各付款方式專用參數

| 付款方式 | ChoosePayment | 專用參數 |
|---------|--------------|---------|
| 信用卡 | Credit | Redeem, UnionPay, BindingCard, MerchantMemberID |
| 分期 | Credit | CreditInstallment=3,6,12,18,24,30 |
| 定期定額 | Credit | PeriodAmount,PeriodType,Frequency,ExecTimes,PeriodReturnURL |
| ATM | ATM | ExpireDate=7（天，範圍 1-60） |
| 超商代碼 | CVS | StoreExpireDate=4320（分鐘），Desc_1~4,PaymentInfoURL |
| 條碼 | BARCODE | StoreExpireDate=5（天），Desc_1~4,PaymentInfoURL |
| BNPL | BNPL | 金額 ≥3000 |

## 付款結果通知（ReturnURL）欄位

| 欄位 | 說明 |
|------|------|
| RtnCode | 交易狀態碼（1=成功，其餘皆為異常，型別為字串） |
| TradeNo | 綠界交易編號 |
| PaymentDate | 付款時間 |
| PaymentType | 付款方式（如 Credit_CreditCard） |
| SimulatePaid | 是否為模擬付款（0=否, 1=是） |
| CheckMacValue | 檢查碼（必須驗證） |

> ⚠️ ATM/CVS/BARCODE 取號通知走 PaymentInfoURL，不是 ReturnURL。RtnCode=2（ATM 取號成功）或 10100073（CVS/BARCODE 取號成功）不是錯誤，代表消費者尚未繳費。

## 信用卡請款 / 退款 / 取消

```php
$postService = $factory->create('PostWithCmvEncodedStrResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂單編號',
    'TradeNo'         => '綠界交易編號',
    'Action'          => 'C',          // C=請款, R=退款, E=取消關帳, N=放棄
    'TotalAmount'     => 100,
];
$response = $postService->post($input, 'https://payment.ecpay.com.tw/CreditDetail/DoAction');
```

> ⚠️ DoAction 僅正式環境可用（Stage 環境不支援），且僅適用於信用卡。ATM/超商代碼/條碼付款不支援線上退款 API，需人工處理。

## 查詢訂單（QueryTradeInfo）

> ⚠️ TimeStamp 有效期僅 3 分鐘，每次呼叫前必須重新產生。

```php
$postService = $factory->create('PostWithCmvVerifiedEncodedStrResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂單編號',
    'TimeStamp'       => time(),
];
$response = $postService->post($input, 'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5');
```

## 常見錯誤碼速查

| RtnCode | 含義 |
|---------|------|
| 1 | 付款成功 |
| 2 | ATM 取號成功 |
| 10100073 | CVS/BARCODE 取號成功 |
| 10200073 | CheckMacValue 驗證失敗 |
| 10200047 | MerchantTradeNo 重複 |
| 10200105 | BNPL 金額未達最低（需 ≥3000） |
| 10400011 | WAF 關鍵字攔截（ItemName/TradeDesc 含系統指令關鍵字） |

> 完整錯誤碼清單見 guides/20-error-codes-reference.md

## ⚡ 完整可執行範例（Python Flask）

```python
# pip install flask requests
import hashlib, time, urllib.parse, hmac
from flask import Flask, request

app = Flask(__name__)
MERCHANT_ID  = '3002607'
HASH_KEY     = 'pwFHCqoQZGmho4w6'
HASH_IV      = 'EkRm7iFT261dpevs'
PAYMENT_URL  = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5'
RETURN_URL   = 'https://你的網域/ecpay/notify'

def ecpay_url_encode(s: str) -> str:
    encoded = urllib.parse.quote_plus(s)
    encoded = encoded.lower()
    encoded = encoded.replace('~', '%7e')
    encoded = encoded.replace('%2d', '-').replace('%5f', '_').replace('%2e', '.') \
                     .replace('%21', '!').replace('%2a', '*') \
                     .replace('%28', '(').replace('%29', ')')
    return encoded

def generate_cmv(params: dict) -> str:
    filtered = {k: v for k, v in params.items() if v is not None and k != 'CheckMacValue'}
    sorted_items = sorted(filtered.items(), key=lambda x: x[0].lower())
    raw = '&'.join(f'{k}={v}' for k, v in sorted_items)
    raw = f'HashKey={HASH_KEY}&{raw}&HashIV={HASH_IV}'
    encoded = ecpay_url_encode(raw)
    return hashlib.sha256(encoded.encode('utf-8')).hexdigest().upper()

def verify_cmv(params: dict) -> bool:
    expected = generate_cmv(params)
    received = params.get('CheckMacValue', '')
    return hmac.compare_digest(expected.encode(), received.encode())

def build_auto_submit_form(params: dict, action: str) -> str:
    params['CheckMacValue'] = generate_cmv(params)
    fields = '\n'.join(f'<input type="hidden" name="{k}" value="{v}">' for k, v in params.items())
    return f'''<!DOCTYPE html><html><body>
<form id="f" method="post" action="{action}">{fields}</form>
<script>document.getElementById("f").submit();</script>
</body></html>'''

@app.route('/checkout')
def checkout():
    trade_no = 'AIO' + str(int(time.time()))
    params = {
        'MerchantID': MERCHANT_ID, 'MerchantTradeNo': trade_no,
        'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
        'PaymentType': 'aio', 'TotalAmount': 100,
        'TradeDesc': '測試商品', 'ItemName': '測試商品x1',
        'ReturnURL': RETURN_URL, 'ChoosePayment': 'Credit', 'EncryptType': 1,
    }
    return build_auto_submit_form(params, PAYMENT_URL)

@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    data = request.form.to_dict()
    if not verify_cmv(data):
        return '1|OK', 200
    rtn_code = data.get('RtnCode', '')
    trade_no = data.get('MerchantTradeNo', '')
    if rtn_code == '1':
        print(f'付款成功 訂單={trade_no}')
    return '1|OK', 200, {'Content-Type': 'text/plain'}

if __name__ == '__main__':
    app.run(port=5000, debug=True)
```

## 相關文件

- 官方 API 規格：references/Payment/全方位金流API技術文件.md（45 個 URL）
- CheckMacValue 解說：guides/13-checkmacvalue.md
- 除錯指南：guides/15-troubleshooting.md
- 上線檢查：guides/16-go-live-checklist.md

> 完整原文（含各付款方式完整範例、定期定額、對帳檔格式、PaymentType 回覆值全表等）請見官方 repo：
> https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/01-payment-aio.md
