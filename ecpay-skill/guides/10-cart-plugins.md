> 對應 ECPay API 版本 | 最後更新：2026-03

# 購物車模組指南

> 本指南為快速索引，不含程式碼實作。各電商平台（WooCommerce、OpenCart、Magento、Shopify）使用官方提供的模組安裝即可。

## 何時選用購物車外掛？

| 情境 | 建議 |
|------|------|
| 已使用 WooCommerce / Magento / OpenCart / Shopify | 使用官方外掛，10 分鐘完成整合 |
| 自訂開發後端 | 直接串接 API，見 guides/01 |
| 需要高度客製化結帳流程 | 自訂開發，外掛限制較多 |

## 支援平台

> ⚠️ SNAPSHOT 2026-03 | 來源：references/Cart/購物車設定說明.md

| 平台 | 官方驗證版本 | 說明 |
|------|------------|------|
| WooCommerce | WordPress 6.5.3 / WooCommerce 8.8.0 / PHP 8.2 | 模組名稱：ECPay Ecommerce for WooCommerce |
| OpenCart | OpenCart 4.0.2.3 / PHP 8.2 | 模組名稱：ECPay Ecommerce for OpenCart |
| Magento | 2.4.3 / 2.4.5 | Adobe 電商平台 |
| Shopify | — | 透過 Shopify 專用 API |

## WooCommerce 安裝步驟

**系統需求**：WordPress 6.5.3+、WooCommerce 8.8.0+、PHP 8.2+、SSL 憑證

1. 從 ECPay 官網（https://www.ecpay.com.tw） → 廠商專區 → 模組下載，下載 WooCommerce 模組
2. 解壓縮套件檔，取得 `ecpay-ecommerce-for-woocommerce.zip`
3. WordPress 後台 → 外掛(Plugins) → 安裝外掛(Add New) → 上傳外掛(Upload Plugin) → 選擇 zip 檔 → 立即安裝(Install Now)
4. 安裝完成後按「啟用外掛(Activate Plugin)」
5. 前往 WooCommerce → 設定(Settings) → 點選「綠界科技」分頁
6. 分別設定：
   - **金流設定**：填入 MerchantID、HashKey、HashIV，選擇啟用的付款方式
   - **物流設定**：填入物流帳號資訊、寄件人資料，並至「運送方式 → 運送區域」新增綠界物流種類
   - **電子發票設定**：填入發票帳號資訊，設定開立模式與延期天數

> ⚠️ 金流、物流、電子發票使用不同的 MerchantID / HashKey / HashIV，請分別填入。
> 錯誤配置症狀：
> - 把金流帳號用於物流 → 呼叫物流 API 時回傳「MerchantID 不符」或 CheckMacValue 驗證失敗
> - 把物流/發票帳號用於金流 → 前台結帳出現 10100248 帳號錯誤或 CheckMacValue Error
> - 三組帳號在 ECPay 後台廠商專區（https://vendor.ecpay.com.tw）分別管理，測試階段可用三組測試帳號（金流 3002607 / 發票 2000132 / 物流另行申請）

### WooCommerce 常見問題

| 問題 | 解決方式 |
|------|---------|
| SSL 未啟用 | 金流無法正常運作（ECPay 要求 HTTPS），安裝 SSL 憑證並強制 HTTPS |
| 外掛衝突 | 若已安裝舊版模組（ECPay Payment / ECPay Logistics / ECPay Invoice for WooCommerce），請先移除，這些舊模組已下架且不再更新，會與新模組產生衝突 |
| 回呼失敗 | 確認 WordPress 站台的 wp-json 或 wc-api 端點可被外部存取 |
| 永久連結設定 | 選擇超商物流後頁面顯示「找不到符合條件的頁面」，請將 WooCommerce 後台的永久連結改為「預設」 |
| ATM/CVS 訂單被提前取消 | WooCommerce 內建「保留庫存」機制（WordPress 後台 → WooCommerce → 設定 → 商品 → 庫存 → 保留庫存(分)）會自動取消超時未付款訂單，請依繳費期限調整分鐘數（例如 ATM 3 天 = 4320 分鐘） |
| 超商取貨付款 | 需額外至 WooCommerce → 設定 → 付款 → 貨到付款 → 啟用運送方式，加入超商取貨付款的物流種類 |

## OpenCart 安裝步驟

**系統需求**：OpenCart 4.0.2.3+、PHP 8.2+（需安裝 PHP curl 模組）

1. 購物車後台 → 擴充模組安裝(Extension Installer) → 上傳 ecpay.ocmod.zip → 等待安裝完成
2. 金流：擴充模組(Extensions) → 付款模組(Payments) → 綠界金流模組 → 安裝 → 編輯 → 填入 MerchantID、HashKey、HashIV
3. 物流：擴充模組(Extensions) → 運送模組(Shipping) → 綠界物流模組 → 安裝 → 編輯 → 填入物流帳號資訊與運費設定
4. 發票：擴充模組(Extensions) → 功能模組(Modules) → 綠界電子發票模組 → 安裝 → 編輯 → 填入發票帳號資訊

> ⚠️ 注意事項：Hash Key 與 Hash IV 不可包含空白；須搭配綠界金流模組才能使用物流和電子發票模組；超商物流金額限制 1~20,000 元，黑貓宅配取貨付款限制 1~20,000 元；TWQR 金額限制 6~49,999 元，微信支付金額限制 6~500,000 元。

## Magento 安裝步驟

**系統需求**：Magento 2.4.3 或 2.4.5、PHP 8.1+

> ⚠️ Magento 2.4.3 限制：金流功能有支援，但物流與電子發票功能不支援。建議需要物流或發票功能時使用 Magento 2.4.5（完整支援金流 + 物流 + 發票）。

```bash
# 套件名稱可能隨版本變動，請至 Packagist 或 ECPay 官網廠商專區確認最新名稱
composer require ecpay/magento2-payment
php bin/magento module:enable ECPay_Payment
php bin/magento setup:upgrade
php bin/magento cache:flush
```

後台 → Stores → Configuration → Sales → Payment Methods → 找到 ECPay 區塊，啟用並填入帳號資訊。

## Shopify

Shopify 透過 ECPay 提供的 Shopify 專用付款 App 串接，商家不需自行撰寫付款 API 程式碼。付款整合在 Shopify 後台 → Settings → Payments → 新增 ECPay 付款供應商（App 安裝方式）。API 規格（對帳/訂單管理用）詳見 references/Payment/Shopify專用金流API技術文件.md。

## 模組功能支援矩陣（金流）

> ⚠️ SNAPSHOT 2026-03，功能支援可能隨模組更新而變動

| 購物車＼功能 | 信用卡一次付清 | 分期付款 | 定期定額 | 銀聯卡 | Apple Pay | TWQR | BNPL | ATM | 超商代碼 | 超商條碼 | 網路ATM | 微信支付 |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| WooCommerce 8.X | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| OpenCart 4.x | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| Magento 2.4.5 | ● | ● | ✗ | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| Shopify | ● | ● | ✗ | ● | ● | ● | ○ | ● | ✗ | ✗ | ● | ● |

物流/發票支援：WooCommerce、OpenCart（3.x/4.x）、Magento 2.4.5 均完整支援 7-ELEVEN/全家/萊爾富/OK超商/黑貓宅配/郵局宅配/電子發票；Magento 2.4.3 全部不支援。

## 常見設定問題

| 問題 | 可能原因 | 解決方式 |
|------|---------|---------|
| 付款頁面空白 | SSL 未啟用 | 安裝 SSL 憑證並強制 HTTPS |
| 回呼未收到 | 防火牆阻擋 | 確認伺服器允許 ECPay IP 的 POST 請求 |
| 金額不符 | 幣別設定錯誤 | 確認購物車幣別為 TWD |
| TradeAmount Error | 金額含小數點 | ECPay 不支援小數點金額，將購物車小數位數設為 0 |
| 模組無法安裝 | PHP 版本過低 | WooCommerce / OpenCart 需 PHP 8.2+，Magento 需 PHP 8.1+ |
| 發票未開立 | 發票模組未啟用 | 另外安裝並啟用 ECPay 發票模組 |
| Hash Key/IV 錯誤 | 複製時含空白 | 確認 Hash Key 與 Hash IV 內容不包含空白字元，建議使用複製貼上 |

## 相關文件

- 購物車設定：references/Cart/購物車設定說明.md
- Shopify API：references/Payment/Shopify專用金流API技術文件.md
- 如需自訂整合：guides/01-payment-aio.md
- 上線檢查：guides/16-go-live-checklist.md
- 除錯指南：guides/15-troubleshooting.md

> 原文：https://github.com/ECPay/ECPay-API-Skill/blob/master/guides/10-cart-plugins.md
