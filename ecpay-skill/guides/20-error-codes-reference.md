> 對應 ECPay API 版本 | 最後更新：2026-03

# 全服務錯誤碼集中參考

> ⚠️ RtnCode / TransCode 型別依協定不同：
> - CMV-SHA256（AIO 金流）/ CMV-MD5（國內物流）：Callback 回傳 Form POST，RtnCode 為字串 "1"
> - AES-JSON（ECPG 線上金流、發票、全方位物流 v2、跨境物流、ECTicket）：JSON 解密後 TransCode 與 RtnCode 為整數 1

## 常見錯誤碼快速查找

| 錯誤碼 | 服務 | 一句話原因 |
|--------|------|-----------|
| TransCode=1 | AES-JSON 全服務 | 外層成功，需再檢查 RtnCode |
| TransCode!=1 | AES-JSON 全服務 | 外層失敗（加密/格式/傳輸層問題） |
| 1 | 全服務 | 交易成功 |
| 2 | AIO 金流 | ATM 取號成功（等待轉帳） |
| 10100073 | AIO 金流 | CVS/BARCODE 取號成功（等待繳費） |
| 10200009 | AIO 金流 | 訂單已過期 |
| 10200043 | 站內付 2.0/AIO | 3D 驗證失敗 |
| 10200047 | AIO 金流 | MerchantTradeNo 重複 |
| 10200050 | AIO 金流 | TotalAmount 超出範圍 |
| 10200058 | 站內付 2.0/AIO | 信用卡授權失敗 |
| 10200073 | CMV 驗證服務 | CheckMacValue 驗證失敗 |
| 10200095 | AIO 金流 | 重複付款 |
| 10200105 | AIO 金流 | 金額低於 BNPL 門檻（最低 3,000） |
| 10200115 | 站內付 2.0/AIO | 信用卡授權逾時 |
| 10300006 | 國內物流/AIO 金流 | 物流訂單已過期／超商繳費期限已過（同代碼跨服務含意不同） |
| 10100058 | AIO 金流/電子發票 | ATM 繳費期限已過／發票作業逾時（同代碼跨服務含意不同） |
| 10400011 | AIO 金流 | WAF 關鍵字攔截（ItemName/TradeDesc 含系統指令關鍵字） |

## 錯誤碼閱讀方式

### CMV-SHA256（AIO 金流）/ CMV-MD5（國內物流）— 單層 RtnCode

- RtnCode=1：交易成功
- RtnCode=2：ATM 取號成功（非錯誤，等待轉帳）
- RtnCode=10100073：CVS/BARCODE 取號成功（非錯誤，等待繳費）
- 其他值：錯誤

### AES-JSON（站內付 2.0、幕後授權、電子發票、全方位物流 v2、跨境物流）— 雙層 TransCode → RtnCode

- TransCode=1 + RtnCode=1：成功
- TransCode≠1：外層錯誤（通常是加密/格式問題）
- TransCode=1 + RtnCode≠1：業務邏輯錯誤

```json
{
  "MerchantID": "3002607",
  "RpHeader": { "Timestamp": 1709618401 },
  "TransCode": 1,
  "TransMsg": "",
  "Data": "Base64EncodedAESEncryptedString..."
}
```

## AIO 金流錯誤碼（CMV-SHA256）

### 成功狀態碼

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 付款成功 | 正常處理訂單 |
| 2 | ATM 取號成功 | 等待消費者轉帳，勿視為錯誤 |
| 10100073 | CVS/BARCODE 取號成功 | 等待消費者繳費，勿視為錯誤 |

### 錯誤碼

| RtnCode | 含義 | 可重試 | 處理方式 |
|---------|------|:------:|---------|
| 10100001 | 超商代碼已失效 | 否 | 重新取號 |
| 10100058 | ATM 繳費期限已過 | 否 | 重新建立訂單取號 |
| 10200009 | 訂單已過期 | 否 | 檢查 ExpireDate 設定 |
| 10200043 | 3D 驗證失敗 | 是 | 請消費者重新進行 3D 驗證 |
| 10200047 | MerchantTradeNo 重複 | 否 | 使用不同的訂單編號 |
| 10200050 | TotalAmount 超出範圍 | 否 | 檢查 TotalAmount 是否正確 |
| 10200058 | 信用卡授權失敗 | 是 | 請消費者確認卡片資訊或更換信用卡 |
| 10200073 | CheckMacValue 驗證失敗 | 否 | 檢查 HashKey/HashIV 和加密邏輯 |
| 10200095 | 交易已付款 | 否 | 重複付款，檢查訂單是否已處理 |
| 10200105 | BNPL 金額未達最低 | 否 | TotalAmount 需 >= 3,000 元 |
| 10200115 | 信用卡授權逾時 | 是 | 請消費者重新付款 |
| 10300006 | 超商繳費期限已過 | 否 | 重新建立訂單 |
| 10400011 | WAF 關鍵字攔截 | 否 | ItemName/TradeDesc 含系統指令關鍵字（echo、python、cmd、wget、curl、bash 等約 40 個），移除關鍵字即可 |

## 站內付 2.0 錯誤碼（AES-JSON）

### 外層 TransCode

| TransCode | 含義 | 處理方式 |
|-----------|------|---------|
| 1 | API 呼叫成功 | 解密 Data 欄位，繼續檢查 RtnCode |
| 其他 | API 層級錯誤 | 檢查 TransMsg，通常是 AES 加密錯誤或 JSON 格式問題 |

### 內層 RtnCode（Data 解密後）

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 操作成功 | 正常流程 |
| 其他 | 業務錯誤 | 檢查 RtnMsg，常見原因：參數錯誤、訂單不存在、重複操作 |

### 站內付 2.0 雙 Domain 注意事項

| 功能 | 測試 Domain | 正式 Domain |
|------|------------|------------|
| Token 相關（GetTokenbyTrade/CreatePayment） | ecpg-stage.ecpay.com.tw | ecpg.ecpay.com.tw |
| 查詢/請退款（QueryTrade/DoAction） | ecpayment-stage.ecpay.com.tw | ecpayment.ecpay.com.tw |

## 物流錯誤碼

### 國內物流（CMV-MD5）

| RtnCode | 含義 |
|---------|------|
| 1 | 操作成功（建立物流訂單） |
| 0 | 操作失敗，格式：0\|ErrorMessage |

### 全方位物流 v2（AES-JSON）

使用 AES JSON，與國內物流的 Form + CheckMacValue MD5 完全不同。

### 常用物流狀態碼速查

| 狀態碼 | 含義 | 適用 |
|--------|------|------|
| 300 | 訂單處理中 | 全超商 |
| 2030 | 已出貨/已到店 | 7-ELEVEN |
| 2063 | 已取件 | 7-ELEVEN |
| 3022 | 已到店 | 全家 |
| 3024 | 已取件 | 全家 |
| 5005 | 已配達 | 宅配 |
| 5011 | 配達失敗 | 宅配 |

## 安全驗證相關錯誤

### CheckMacValue 驗證失敗

症狀：10200073 CheckMacValue verify fail（AIO 金流）。完整排查見 guides/13 + guides/15。

### AES 加密/解密失敗

症狀：TransCode≠1，排查步驟：
1. Key/IV 長度 — 必須取前 16 bytes（AES-128-CBC）
2. 加解密順序 — 加密前先 URL encode，解密後才 URL decode
3. Padding — 使用 PKCS7 padding
4. Base64 — 加密後的密文必須 Base64 encode，確認無多餘換行或空格
5. JSON 格式 — 確認 JSON 字串無多餘空格或 BOM

## HTTP 層級錯誤

### 403 Forbidden（Rate Limit）

ECPay 會在 API 呼叫過頻時回傳 403。觸發後需等待約 30 分鐘。

### ReturnURL 收不到通知排查步驟

1. URL 格式 — 必須是完整的 https:// URL
2. 防火牆 — 確認伺服器允許綠界 IP 存取
3. 埠號 — 僅支援 80/443
4. SSL — 必須 TLS 1.2
5. CDN — 不可放在 CDN 後面
6. 回應格式 — 必須回應純字串 1|OK（不可有 HTML 標籤、BOM）
7. 特殊字元 — URL 中不可含分號 `;`、管道 `|`、反引號

**重送機制（AIO 金流）**：如果沒收到正確回應，綠界會每 5-15 分鐘重送，每天最多 4 次。

## 環境混用檢查

| 環境 | 特徵 | 測試帳號 |
|------|------|---------|
| 測試環境 | URL 含 -stage | MerchantID=3002607（金流）/ 2000132（發票） |
| 正式環境 | URL 不含 -stage | 向綠界申請的正式帳號 |

## 相關文件

- guides/15-troubleshooting.md — 快速排查決策樹
- guides/21-webhook-events-reference.md — Callback 欄位定義
- references/Payment/全方位金流API技術文件.md — AIO 金流錯誤碼

> 完整內容（含幕後授權/發票/ECTicket 錯誤碼、PHP SDK 內部錯誤碼等）請見官方 repo：
> https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/20-error-codes-reference.md
