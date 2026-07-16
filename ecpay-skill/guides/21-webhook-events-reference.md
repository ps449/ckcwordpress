> 對應 ECPay API 版本 | 最後更新：2026-03

# 統一 Callback/Webhook 參考

> ⚠️ 認證方式依服務而異：金流 AIO → SHA256，國內物流 → MD5，B2C 發票線上折讓 → MD5，ECPG/幕後授權/幕後取號/發票（其他 API）/物流 v2 → AES 解密（無 CheckMacValue），ECTicket → AES 解密 + CheckMacValue (SHA256)。

## ⚠️ RtnCode 型別依協議而異（靜默失敗常見根因）

| 服務類別 | 協議 | RtnCode 型別 | 正確比較 | 錯誤寫法 |
|---------|------|-------------|---------|---------|
| AIO 金流、國內物流 | CMV（Form POST） | 字串 "1" | === '1' | === 1（永遠 false） |
| ECPG 線上金流、發票、全方位物流 v2、ECTicket | AES-JSON | 整數 1 | === 1 | === '1'（永遠 false） |

防禦性寫法：`Number(rtnCode) === 1`（JavaScript）/ `int(rtn_code) == 1`（Python）。

## ⚡ Callback 回應格式速查

| 服務 | Callback URL | 必須回應的格式 | 錯誤後果 |
|------|-----------------|--------------|---------|
| AIO 金流（ReturnURL） | ReturnURL | `1\|OK`（純文字） | 每 5-15 分鐘重送，每日最多 4 次 |
| 站內付 2.0 | ReturnURL | `1\|OK`（純文字） | 約每 2 小時重試 |
| 信用卡幕後授權 | ReturnURL | `1\|OK`（純文字） | 約每 2 小時重試 |
| 國內物流 | ServerReplyURL | `1\|OK`（純文字） | 約每 2 小時重試 |
| 全方位 / 跨境物流 | ServerReplyURL | AES 加密 JSON 三層結構 | 約每 2 小時重試 |
| B2C 發票（線上折讓） | ReturnURL | `1\|OK`（純文字，CheckMacValue MD5） | 未公開 |
| ECTicket | UseStatusNotifyURL | AES 加密 JSON + CheckMacValue | 每 5-15 分鐘重送，每日最多 4 次 |

> ⚠️ `1|OK` 常見錯誤格式（每種都會觸發 ECPay 重試）：
> - `"1|OK"`（含引號）
> - `1|ok`（小寫，OK 必須大寫）
> - `1OK`（缺少管道符）
> - `1|OK\n` 或 `1|OK `（結尾含換行或空白）
>
> 正確回應必須是精確的 ASCII 字串 `1|OK`，無引號、無換行、無尾隨空白。

## 實作 Callback 的安全處理檢查清單

- [ ] ① 驗簽：CheckMacValue 或 AES 解密必須通過
- [ ] ② RtnCode 型別：CMV 協議為字串 === '1'；AES-JSON 為整數 === 1
- [ ] ③ 業務狀態：RtnCode 是否在預期值範圍
- [ ] ④ 冪等檢查：此 MerchantTradeNo 是否已處理過（防止重複入帳），用 upsert 不可用 insert
- [ ] ⑤ 立即回應：依服務回應精確格式，10 秒內必須回應
- [ ] ⑥ 非同步後處理：回應後再處理業務邏輯（發信、開發票、更新庫存）

## Callback 驗證程式碼範例

### Python — AIO Callback（CMV-SHA256）

```python
params = dict(request.form)
received_cmv = params.pop('CheckMacValue', '')
expected_cmv = generate_check_mac_value(params, hash_key, hash_iv, 'sha256')
if not hmac.compare_digest(expected_cmv, received_cmv):
    return '0|CheckMacValue Error'
if params['RtnCode'] == '1':
    # 付款成功，處理訂單（冪等 upsert）
    pass
return '1|OK'
```

### Node.js — ECPG Callback（AES-JSON）

```javascript
const body = req.body;
if (body.TransCode !== 1) {
    return res.send('0|Fail');
}
const data = JSON.parse(aesDecrypt(body.Data, hashKey, hashIv));
if (data.RtnCode === 1) {  // 整數比較
    // 付款成功
}
res.send('1|OK');
```

## Callback 總覽表

| 服務 | URL 欄位名 | 觸發時機 | 認證方式 | 必須回應 | 重試機制 |
|------|-----------|---------|---------|---------|---------|
| AIO 金流 | ReturnURL | 付款完成 | CheckMacValue (SHA256) | `1\|OK` | 每 5-15 分鐘重送，每日最多 4 次 |
| AIO 金流 | PaymentInfoURL | ATM/CVS/BARCODE 取號完成 | CheckMacValue (SHA256) | `1\|OK` | 同上 |
| AIO 金流 | PeriodReturnURL | 定期定額每期扣款 | CheckMacValue (SHA256) | `1\|OK` | 同上 |
| AIO 金流 | OrderResultURL | 前端跳轉 | CheckMacValue (SHA256) | HTML 頁面 | 不重試 |
| 站內付 2.0 | ReturnURL | 付款完成 | AES 解密 Data | `1\|OK` | 約每 2 小時重試 |
| 站內付 2.0 | OrderResultURL | 前端跳轉 | JSON 解析 ResultData → AES 解密 | HTML 頁面 | 不重試 |
| 信用卡幕後授權 | ReturnURL | 授權結果 | AES 解密 Data | `1\|OK` | 約每 2 小時重試 |
| 國內物流 | ServerReplyURL | 物流狀態變更 | CheckMacValue (MD5) | `1\|OK` | 約每 2 小時重試 |
| 全方位物流 | ServerReplyURL | 物流狀態變更 | AES 解密 | AES 加密 JSON | 約每 2 小時重試 |
| ECTicket | UseStatusNotifyURL | 退款/核退通知 | AES 解密 + CheckMacValue (SHA256) | AES 加密 JSON + CMV | 每 5-15 分鐘重送，每日最多 4 次 |
| B2C 發票（線上折讓） | ReturnURL | 消費者同意折讓 | CheckMacValue (MD5) | `1\|OK` | 未公開 |

> 到達順序不保證：你的處理邏輯必須依賴冪等鍵（MerchantTradeNo / AllPayLogisticsID）而非到達順序來判斷狀態。

## AIO ReturnURL — 付款成功通知欄位

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 交易狀態碼（1=成功） |
| TradeNo | 綠界交易編號 |
| TradeAmt | 交易金額 |
| PaymentDate | 付款時間 |
| PaymentType | 付款方式 |
| SimulatePaid | 是否為模擬付款（0=否, 1=是） |
| CheckMacValue | 檢查碼 |

處理流程：
1. 解析 POST 參數
2. 驗證 CheckMacValue
3. 確認 RtnCode=1（付款成功）
4. 確認 SimulatePaid=0（非模擬付款）
5. 更新訂單狀態（使用 upsert 確保冪等性）
6. 回應純字串 1|OK

## AIO PaymentInfoURL — 取號通知（ATM/CVS/BARCODE）

取號成功的 RtnCode 不是 1：

| 付款方式 | 取號成功 RtnCode |
|---------|-----------------|
| ATM | 2 |
| CVS | 10100073 |
| BARCODE | 10100073 |

各付款方式額外欄位：ATM（BankCode、vAccount、ExpireDate）、CVS（PaymentNo、ExpireDate）、BARCODE（Barcode1~3、ExpireDate）。

## 冪等性實作建議

使用 MerchantTradeNo（金流）或 AllPayLogisticsID（物流）作為冪等鍵。

### SQL Upsert 範例（PostgreSQL）

```sql
INSERT INTO payment_notifications (merchant_trade_no, rtn_code, trade_amt, payment_date, raw_data)
VALUES ($1, $2, $3, $4, $5)
ON CONFLICT (merchant_trade_no) DO UPDATE SET
  rtn_code = EXCLUDED.rtn_code,
  updated_at = NOW()
WHERE payment_notifications.rtn_code != '1';
```

### MySQL 等價寫法

```sql
INSERT INTO payment_notifications (merchant_trade_no, status, received_at)
VALUES ('MN20240301001', 'paid', NOW())
ON DUPLICATE KEY UPDATE status = VALUES(status);
```

### 設計原則

1. 先存後處理：收到 Callback 立即存入 DB，再做業務邏輯
2. Upsert 而非 Insert：用 ON CONFLICT 防止重複插入
3. 已成功不覆蓋：已標記為成功的交易不應被後續 Callback 覆蓋
4. 永遠回應：無論是否已處理過，都回應 1|OK，否則 ECPay 會持續重送

## 重試機制說明

| 服務 | 重試頻率 | 每日次數 |
|------|---------|---------|
| AIO 金流 | 每 5-15 分鐘 | 最多 4 次 |
| 站內付 2.0（ReturnURL） | 約每 2 小時 | 次數未公開 |
| 國內物流 | 約每 2 小時 | 次數未公開 |
| ECTicket | 每 5-15 分鐘 | 最多 4 次 |

重試觸發條件：你的 server 未回應正確格式、HTTP 回應碼非 200、連線逾時（超過 10 秒）。

> 建議：同時實作主動查詢（QueryTradeInfo）作為補充機制，不要完全依賴 callback。

## 失敗恢復策略

1. **主動查詢**：使用對應的 QueryTrade API 主動查詢訂單狀態（AIO 金流：/Cashier/QueryTradeInfo/V5）
2. **對帳檔**：每日下載對帳檔比對（/PaymentMedia/TradeNoAio）
3. **監控警示**：Callback 接收頻率驟降、RtnCode 非成功比例異常、驗證失敗率上升

## 相關文件

- guides/01-payment-aio.md — AIO 金流完整指南
- guides/13-checkmacvalue.md — CheckMacValue 驗證
- guides/14-aes-encryption.md — AES 加解密
- guides/20-error-codes-reference.md — 錯誤碼參考

> 完整內容（含站內付 2.0 雙 Callback 完整範例、物流逆物流通知、消費爭議處理等）請見官方 repo：
> https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/21-webhook-events-reference.md
