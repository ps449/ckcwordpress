> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 測試→上線切換完整檢查清單

## 🔴 紅燈檢查（5 項必過）

> 上線前必須全部通過——任何一項未完成都可能導致交易失敗或資安風險。

- [ ] **1. 測試→正式 URL 已全數切換**：所有 API 端點已從 -stage 切換到正式域名。站內付 2.0 注意雙 Domain 都要切（ecpg-stage → ecpg，ecpayment-stage → ecpayment）
- [ ] **2. HashKey/HashIV 已替換為正式環境值**：MerchantID、HashKey、HashIV 全部改為正式帳號（從綠界商店後台 vendor.ecpay.com.tw 取得）
- [ ] **3. 密鑰未硬編碼於前端或版本控制**：HashKey/HashIV 使用環境變數或 Secret Manager 管理
- [ ] **4. Callback URL 已更新為正式環境且可接收**：ReturnURL、OrderResultURL 指向正式伺服器的 HTTPS URL（port 443）
- [ ] **5. RtnCode 型別判斷正確**：CMV 協定（AIO / 國內物流）的 RtnCode 為字串 === '1'；AES-JSON 協定（ECPG / 發票 / 物流 v2）的 RtnCode 為整數 === 1

## URL 對照

| 服務 | 測試 | 正式 |
|------|------|------|
| 金流 AIO | payment-stage.ecpay.com.tw | payment.ecpay.com.tw |
| 站內付 2.0（Token/建立交易） | ecpg-stage.ecpay.com.tw | ecpg.ecpay.com.tw |
| ECPG（查詢/授權/請退款） | ecpayment-stage.ecpay.com.tw | ecpayment.ecpay.com.tw |
| 物流 | logistics-stage.ecpay.com.tw | logistics.ecpay.com.tw |
| 電子發票 | einvoice-stage.ecpay.com.tw | einvoice.ecpay.com.tw |
| 特店後台 | vendor-stage.ecpay.com.tw | vendor.ecpay.com.tw |

## 安全性

- [ ] 已更換程式碼中的 MerchantID、HashKey、HashIV 為正式帳號
- [ ] HashKey / HashIV 未出現在前端程式碼、未出現在版本控制（git）中
- [ ] 使用環境變數或加密設定檔管理機敏資料
- [ ] TLS 1.2 已啟用

### PCI DSS 範圍影響

| 整合方式 | PCI 等級 | 說明 |
|---------|---------|------|
| AIO（跳轉） | SAQ-A | 最低範圍 — 你的伺服器不接觸卡號資料 |
| 站內付 2.0 | SAQ-A-EP | 中等範圍 — 卡號直接送至綠界，不經過你的後端 |
| 幕後授權 | SAQ-D 或更高 | 最高範圍 — 你的後端直接處理卡號資料 |

> 建議：除非有明確需求，優先選擇 AIO 或 ECPG 以降低 PCI 合規負擔。

## 回呼 URL 檢查

- [ ] ReturnURL、OrderResultURL、ClientBackURL 設為不同的 URL
- [ ] ReturnURL 可被外網存取，使用 HTTPS，僅使用 80 或 443 埠
- [ ] ReturnURL 回應純字串 `1|OK`（無 HTML、無 BOM），HTTP Status Code 為 200
- [ ] ReturnURL 在 10 秒內回應（不可有阻塞 I/O 或外部 API 呼叫）
- [ ] ReturnURL 未放在 CDN 後面
- [ ] ATM 付款 PaymentInfoURL 需處理 RtnCode=2（取號成功，非最終付款）
- [ ] CVS/BARCODE 付款 PaymentInfoURL 需處理 RtnCode=10100073（取號成功）

## 應用層安全

- [ ] Callback 端點已驗證來源 IP（向綠界客服索取 IP 白名單）
- [ ] MerchantTradeNo 冪等性檢查（拒絕重複訂單編號的重複處理）
- [ ] 所有使用者輸入已做參數化查詢（防 SQL 注入）
- [ ] 前端顯示的交易資訊已做 HTML 跳脫（防 XSS）
- [ ] MerchantTradeNo 限制為英數字（≤20 字元）
- [ ] TotalAmount 必須為正整數（不可為 0、負數或小數）
- [ ] ItemName / TradeDesc 已過濾 HTML 標籤與控制字元，避免 WAF 攔截或 CheckMacValue 不符

## 驗證邏輯

- [ ] CheckMacValue 驗證必須使用 timing-safe 比較函式（不可用 == 或 ===）
- [ ] 確認 RtnCode 比對型別正確：AIO/物流 Callback 為字串 "1"，AES-JSON 服務解密後為整數 1
- [ ] SimulatePaid 檢查已實作（測試交易不出貨）
- [ ] 防重複處理已實作（同一筆通知可能重送多次）
- [ ] 確認 ItemName 不超過 400 字元（含中文多位元組字元），截斷會導致 CheckMacValue 不符
- [ ] MerchantTradeDate 時區：確認伺服器產生的 MerchantTradeDate 為 UTC+8（Asia/Taipei）

## 功能測試

- [ ] 已用正式帳號完成至少一筆小額信用卡交易
- [ ] 已驗證主要付款方式都能正常運作
- [ ] 發票功能已測試（如有使用）
- [ ] 物流功能已測試（如有使用）
- [ ] 退款 / 折讓 / 退貨流程已測試
- [ ] 確認退款/請款/取消（DoAction）僅用於信用卡交易，ATM/CVS/條碼付款無退款 API
- [ ] BNPL 先買後付最低金額 ≥ 3,000 元

## 錯誤處理

- [ ] 錯誤處理和日誌記錄已到位
- [ ] 付款失敗的使用者體驗已處理（顯示錯誤訊息、提供重試）
- [ ] 回呼處理的例外已捕獲（不可因程式錯誤導致未回應 1|OK）
- [ ] API 超時處理已實作

## 3D Secure

- [ ] 已確認 3D Secure 2.0 相容（2025/8/1 起強制）

## 基礎設施

- [ ] SSL 憑證有效期 > 90 天，設定自動續約提醒
- [ ] Callback endpoint 可從外部 IP 訪問（非僅限內網）

## 監控

- [ ] 交易成功率監控已建立
- [ ] 回呼失敗警示已建立
- [ ] 異常交易金額警示已建立

### 上線後第一天觀察重點

- 建立訂單 → callback 接收的比例是否接近 1:1
- callback 處理時間是否在 10 秒內（超時會觸發重送）
- 有無 CheckMacValue 驗證失敗（可能代表 HashKey 設定錯誤）
- ATM/CVS 訂單的 RtnCode=2/10100073 是否被正確處理（非錯誤）

## 🚨 金鑰洩漏緊急處置 SOP

若發現 HashKey/HashIV 或 MerchantID 洩漏（例如提交至公開 Git、日誌洩漏）：

1. 立即通知綠界客服（techsupport@ecpay.com.tw / (02) 2655-1775）要求重發金鑰
2. 停用洩漏金鑰：暫停相關服務收款（Feature Flag 或維護模式）
3. 檢查異常交易：透過特店後台（vendor.ecpay.com.tw）查閱洩漏期間的交易紀錄
4. 更新金鑰：取得新金鑰後更新環境變數並重啟服務
5. 回溯清理：從 Git 歷史清除敏感值（git filter-branch 或 BFG Repo-Cleaner）
6. 覆盤記錄：記錄洩漏原因、影響範圍、處理時間，更新團隊安全規範

## 環境切換最佳實踐

| 環境變數 | 測試值 | 正式值 |
|---------|--------|--------|
| ECPAY_MERCHANT_ID | 3002607（AIO）/ 2000132（發票） | 正式特店編號 |
| ECPAY_HASH_KEY | 測試 HashKey | 正式 HashKey |
| ECPAY_HASH_IV | 測試 HashIV | 正式 HashIV |
| ECPAY_ENV | staging | production |

## 漸進式上線策略

1. 先小額交易測試 — 用真實帳號做 10 元測試交易
2. 先只開信用卡 — 確認穩定後再逐步開啟 ATM、CVS 等
3. 先不串發票 — 確認金流穩定後再加電子發票

## 安全防護

- [ ] 付款頁面絕不使用 iframe 嵌入 ECPay 付款頁面（瀏覽器安全限制會封鎖）
- [ ] OrderResultURL 屬 ECPay 前端 Form POST 回傳，不可強制要求 CSRF Token；請改驗證 ResultData 內容
- [ ] ReturnURL（server-to-server）不需 CSRF，但需驗 CheckMacValue
- [ ] 從 ECPay 回傳的參數值（TradeDesc, ItemName 等）顯示在頁面時需 HTML escape

## ✅ 整合驗收清單（上線前端到端驗收）

### 金流 AIO（CMV-SHA256）驗收

- [ ] 下單成功：呼叫 AioCheckOut 後，消費者瀏覽器跳轉到綠界付款頁，頁面顯示正確金額與商品名稱
- [ ] 信用卡付款成功：使用測試卡號 4311-9522-2222-2222 完成付款，後端 ReturnURL 收到 RtnCode='1'（字串）
- [ ] ATM 取號成功：後端 ReturnURL 收到 RtnCode='2'（字串），PaymentInfoURL 收到虛擬帳號
- [ ] Callback 驗證通過：CheckMacValue 與系統重新計算值一致（使用 timing-safe 比較）
- [ ] Callback 回應正確：已回應純文字 1|OK（HTTP 200，不含引號/換行/HTML）
- [ ] 冪等性：Callback 重送時，訂單狀態僅更新一次（不重複入帳）

### 通用驗收

- [ ] 測試→正式環境切換：HashKey/HashIV 改為正式值，所有 URL 已去除 -stage 後綴
- [ ] 回應超時：ReturnURL 在 3 秒內回應 1|OK（壓力測試模擬 10 並發 callback）
- [ ] RtnCode 型別：CMV 類 === '1'（字串）；AES-JSON 類 === 1（整數）均已正確判斷
- [ ] 錯誤情境：模擬 RtnCode !== 1 時，系統不更新訂單狀態（不誤判為成功）

## 相關文件

- 除錯指南：guides/15-troubleshooting.md
- 金流 AIO：guides/01-payment-aio.md
- 錯誤碼排查：guides/20-error-codes-reference.md
- Callback 處理：guides/21-webhook-events-reference.md

> 完整原文（含站內付 2.0 專屬清單、爭議款項處理、HashKey 輪換指引、緊急復原計畫等）請見官方 repo：
> https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/16-go-live-checklist.md
