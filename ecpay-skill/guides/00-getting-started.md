> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 從零開始：第一筆交易到上線

> ### 30 秒速查
>
> | 項目 | 內容 |
> |------|------|
> | **測試商店編號** | 金流 `3002607` / 發票·物流 `2000132` |
> | **最快測試路徑** | 信用卡一次付清 + `SimulatePaid=1` → §AIO 全方位金流首次測試路徑 |
> | **預計完成時間** | PHP 30 分鐘 / Python·Node.js 45 分鐘 / Java·C# 60 分鐘 |
> | **非 PHP 必讀** | guides/13 + guides/14 + guides/19 |
> | **遇到問題** | guides/15（按症狀）或 guides/20（按錯誤碼） |

## 新手必知術語（10 項速查）

| 術語 | 白話解釋 |
|------|---------|
| **MerchantID** | 你的商店編號（綠界發給你的，測試用 `3002607`） |
| **HashKey / HashIV** | 兩把密鑰，像你家大門的鑰匙，加密用的，絕對不能外洩 |
| **AIO** | All-In-One 金流，消費者會跳到綠界頁面付款，最常用的方案（約 60% 商家使用） |
| **ECPG** | EC Payment Gateway 的簡稱，即綠界的線上金流服務，涵蓋站內付 2.0（Web/App）、綁定信用卡、幕後授權、幕後取號等服務。注意：ECPG ≠ 站內付 2.0。POS 刷卡機屬於線下金流，不在 ECPG 範圍內 |
| **CheckMacValue** | 簽名驗證碼——你和綠界雙方各自用密鑰算出一個簽名，對得上才代表資料沒被竄改 |
| **AES 加密** | 進階加密方式——把整段資料用密鑰鎖起來再傳送（ECPG、發票、物流 v2 用這個） |
| **ReturnURL** | 你的伺服器接收通知的網址——綠界付款完成後，會從背景 POST 通知到這個 URL |
| **Callback** | 跟 ReturnURL 同義，就是綠界從後台通知你「付款完成了」的機制 |
| **ClientBackURL** | 消費者付完款後，瀏覽器自動跳回的前端頁面（不是通知你的伺服器，是給消費者看的） |
| **SimulatePaid** | 設為 1 就能模擬付款成功，測試時不用真刷卡 |

> 🎯 **最重要的一件事**：ReturnURL 是伺服器對伺服器的背景通知，ClientBackURL 是瀏覽器跳轉。兩者用途完全不同，不可搞混。

## ECPay 六大服務

| 服務 | 說明 | 適用場景 |
|------|------|---------|
| 金流 | 信用卡、ATM、超商代碼、條碼、WebATM、TWQR、BNPL、微信、Apple Pay、銀聯 | 線上收款（AIO / ECPG）；線下收款（POS 刷卡機） |
| 物流 | 超商取貨（全家/統一/萊爾富/OK（僅 C2C））、宅配（黑貓/郵局）、跨境 | 商品配送 |
| 電子發票 | B2C、B2B（交換/存證模式）、離線 | 合規開票 |
| 電子收據 | 一般（記帳）、公益（社福捐贈）、政治獻金 | 非發票類憑證（guides/25；支援 AES-GCM） |
| ECTicket | 價金保管（使用後核銷/分期核銷）、純發行 | 票券、餐券、遊樂園 |
| 購物車 | WooCommerce、OpenCart、Magento、Shopify 模組 | 現成電商平台 |

## 商務申請流程

1. 立即開始開發 — 使用測試帳號（MerchantID: 3002607），無需等待任何申請
2. 至綠界科技官網申請帳號並提交營業登記相關文件
3. 審核通過後取得正式 MerchantID、HashKey、HashIV
4. 上線前將程式碼中的測試帳號替換為正式帳號（見 guides/16）

> 申請時程參考：專屬測試帳號通常 1-3 個工作天；正式帳號依審核進度約 5-10 個工作天

## AIO 全方位金流首次測試路徑

> AIO 是最常用的收款方案（約 60% 商家採用）。消費者點「結帳」後跳轉到綠界頁面，支援信用卡、ATM、超商代碼等多種付款方式。

串接前必備：
```
□ 測試帳號：MerchantID=3002607 / HashKey=pwFHCqoQZGmho4w6 / HashIV=EkRm7iFT261dpevs
□ ReturnURL 要能接收 POST（localhost 無效，請使用 ngrok 或類似工具建立公開 URL）
□ 非 PHP 語言：先讀 guides/13-checkmacvalue.md 實作 CheckMacValue（SHA256）
```

4 步驟快速路徑：
```
1. POST /Cashier/AioCheckOut/V5 → 建立訂單，回傳表單 HTML
2. 前端 POST 提交表單 → 跳轉到綠界付款頁，填測試卡 4311952222222222
3. 付款完成 → ReturnURL 接收 Server-to-Server Form POST，回應 1|OK
4. QueryTradeInfo 查詢確認訂單狀態
```

## 測試帳號（完整對照表見 SKILL.md）

| 服務 | MerchantID | HashKey | HashIV | 協定 |
|------|-----------|---------|--------|------|
| 金流 AIO | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | SHA256 |
| ECPG 線上金流（站內付 2.0、幕後授權、幕後取號）| 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | AES |
| 電子發票 | 2000132 | ejCk326UnaZWKisg | q9jcZX8Ib9LM8wYk | AES |
| 國內物流 B2C | 2000132 | 5294y06JbISpM5x9 | v77hoKGq4kWxNNIS | MD5 |

### 測試信用卡

- 卡號：`4311-9522-2222-2222`
- 有效期限：任意未過期日期
- CVV：任意三碼數字（如 `222`）
- 3D 驗證 SMS 碼：`1234`

## 新手最常踩的 6 個坑

| # | 坑 | 症狀 | 解法 |
|:-:|---|------|------|
| 1 | 帳號混用 | CheckMacValue 永遠失敗 | 金流、物流、發票各有獨立帳號，不可混用 |
| 2 | ReturnURL 和 ClientBackURL 搞混 | 訂單狀態不更新 | ReturnURL 是伺服器背景通知（必須處理），ClientBackURL 是瀏覽器跳轉（選填） |
| 3 | URL encode 不符 ECPay 規格 | CheckMacValue 產出不一致 | 必須用綠界專屬的 URL encode 規則（見 guides/13） |
| 4 | 沒做雙層錯誤檢查 | 看似成功實際失敗 | AES-JSON 回應要先查 TransCode，再查 RtnCode |
| 5 | 測試環境用 HTTP | 連線被拒絕 | ECPay 所有 API 只接受 HTTPS |
| 6 | ECPG 雙 Domain 混用 | 查詢/退款呼叫回 404 | GetToken/CreatePayment 用 ecpg(-stage)；QueryTrade/DoAction 用 ecpayment(-stage) |

## Node.js Quick Start（AIO 信用卡付款）

```javascript
const express = require('express');
const crypto = require('crypto');
const app = express();
app.use(express.urlencoded({ extended: true }));

const config = {
  merchantId: '3002607',
  hashKey: 'pwFHCqoQZGmho4w6',
  hashIv: 'EkRm7iFT261dpevs',
  baseUrl: 'https://payment-stage.ecpay.com.tw',
};

function ecpayUrlEncode(source) {
  let encoded = encodeURIComponent(source).replace(/%20/g, '+').replace(/~/g, '%7e').replace(/'/g, '%27');
  encoded = encoded.toLowerCase();
  const replacements = { '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!', '%2a': '*', '%28': '(', '%29': ')' };
  for (const [old, char] of Object.entries(replacements)) {
    encoded = encoded.split(old).join(char);
  }
  return encoded;
}

function generateCheckMacValue(params) {
  const filtered = Object.entries(params).filter(([k]) => k !== 'CheckMacValue');
  const sorted = filtered.sort((a, b) => a[0].toLowerCase().localeCompare(b[0].toLowerCase()));
  const paramStr = sorted.map(([k, v]) => `${k}=${v}`).join('&');
  const raw = `HashKey=${config.hashKey}&${paramStr}&HashIV=${config.hashIv}`;
  const encoded = ecpayUrlEncode(raw);
  return crypto.createHash('sha256').update(encoded, 'utf8').digest('hex').toUpperCase();
}

app.get('/checkout', (req, res) => {
  const params = {
    MerchantID: config.merchantId,
    MerchantTradeNo: 'Node' + Date.now(),
    MerchantTradeDate: (() => {
      const now = new Date();
      const pad = n => String(n).padStart(2, '0');
      return `${now.getFullYear()}/${pad(now.getMonth()+1)}/${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    })(),
    PaymentType: 'aio',
    TotalAmount: '100',
    TradeDesc: '測試交易',
    ItemName: '測試商品',
    ReturnURL: 'https://example.com/ecpay/notify',
    ChoosePayment: 'ALL',
    EncryptType: '1',
    SimulatePaid: '1',
  };
  params.CheckMacValue = generateCheckMacValue(params);
  const fields = Object.entries(params).map(([k, v]) => `<input type="hidden" name="${k}" value="${v}">`).join('');
  res.send(`<form id="ecpay" method="POST" action="${config.baseUrl}/Cashier/AioCheckOut/V5">${fields}<script>document.getElementById('ecpay').submit();</script></form>`);
});

app.post('/ecpay/notify', (req, res) => {
  const cmv = generateCheckMacValue(req.body);
  const a = Buffer.from(cmv);
  const b = Buffer.from(req.body.CheckMacValue || '');
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
    return res.send('1|OK');
  }
  if (req.body.RtnCode === '1' && req.body.SimulatePaid === '0') {
    console.log('付款成功:', req.body.MerchantTradeNo);
  }
  res.send('1|OK');
});

app.listen(3000, () => console.log('Server: http://localhost:3000/checkout'));
```

## 本地開發環境（ReturnURL 需要公開 URL）

| | 方案 A：SimulatePaid + QueryTradeInfo | 方案 B：ngrok |
|---|---|---|
| 適用 | 首次快速驗證建單邏輯 | 測試完整的付款→通知→業務邏輯流程 |
| 優點 | 最簡單、無需外部工具 | 真實 Callback、支援 HTTPS |
| 建議時機 | 開發初期驗證 API 串接 | 開發中期測試 Callback |

```bash
ngrok http 3000   # 將本機 3000 port 暴露為公開 HTTPS URL
```

## 下一步

- 想收各種付款 → guides/01-payment-aio.md
- 想嵌入付款到自己的頁面 → guides/02-payment-ecpg.md
- 想開電子發票 → guides/04-invoice-b2c.md
- 想做超商取貨/宅配 → guides/06-logistics-domestic.md
- 想一次搞定收款+發票+出貨 → guides/11-cross-service-scenarios.md
- WooCommerce / Shopify 等購物車外掛 → guides/10-cart-plugins.md
- 錯誤碼排查 → guides/20-error-codes-reference.md
- Callback 處理 → guides/21-webhook-events-reference.md
- 上線檢查 → guides/16-go-live-checklist.md

> 完整原文（含 5 分鐘體驗、Python/Go Quick Start、AES-JSON 端到端範例等）請見官方 repo：
> https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/00-getting-started.md
