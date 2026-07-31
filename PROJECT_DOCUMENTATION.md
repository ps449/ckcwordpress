# 潮港城 (Chao Gang Cheng) 電商網站系統與開發維護文檔

> **版本**: 1.0.0  
> **最後更新日期**: 2026-07-30  
> **適用物件**: 前後端開發者、維護工程師、系統管理員  
> **主要主題**: `wp-content/themes/chao-gang-cheng`

---

## 📖 目錄 (Table of Contents)
1. [專案簡介與系統架構](#1-專案簡介與系統架構)
2. [主題與目錄結構說明](#2-主題與目錄結構說明)
3. [核心功能與業務邏輯細節](#3-核心功能與業務邏輯細節)
   - 3.1 [購物車與結帳頁面 UX (Cart & Checkout UX)](#31-購物車與結帳頁面-ux-cart--checkout-ux)
   - 3.2 [省下金額與動態運費計算 (Order Savings & Dynamic Shipping)](#32-省下金額與動態運費計算-order-savings--dynamic-shipping)
   - 3.3 [CyberBiz 風格折價券與領券中心 (Coupons & Claim Center)](#33-cyberbiz-風格折價券與領券中心-coupons--claim-center)
   - 3.4 [AI 智慧推薦與 AI SEO 文案生成 (AI Integrations)](#34-ai-智慧推薦與-ai-seo-文案生成-ai-integrations)
   - 3.5 [會員紅利點數與分潤夥伴系統 (Points & Referral System)](#35-會員紅利點數與分潤夥伴系統-points--referral-system)
   - 3.6 [第三方整合 (LINE Login / 綠界金物流)](#36-第三方整合-line-login--綠界金物流)
4. [後台管理與商品編輯頁維護規範](#4-後台管理與商品編輯頁維護規範)
5. [常見問題與除錯指南 (Troubleshooting)](#5-常見問題與除錯指南-troubleshooting)
6. [Git 工作流與部署說明 (Deployment Workflow)](#6-git-工作流與部署說明-deployment-workflow)

---

## 1. 專案簡介與系統架構

潮港城電商網站建立於 WordPress + WooCommerce 基礎之上，並透過自訂主題 `chao-gang-cheng` 進行了深度客製化。本系統融合了現代化台灣電商所需的各項行銷與會員機制（如 CyberBiz 風格領券中心、網紅/團媽分潤、LINE 快速登入、綠界 ECPG 金流，以及整合 Google Gemini API 的 AI SEO 與產品推薦）。

### 🛠️ 技術棧 (Tech Stack)
- **CMS / Ecommerce**: WordPress 6.x + WooCommerce 8.x+
- **主題 (Theme)**: `chao-gang-cheng` (純自訂主題，無第三方重型 Builder 依賴)
- **前端 (Frontend)**: HTML5, JavaScript (jQuery + Modern Fetch API), CSS3 (Flexbox/Grid, Responsive RWD)
- **後端 (Backend)**: PHP 8.x (嚴格維護語法相容性與 WooCommerce 標準 API)
- **AI 整合**: Google Gemini REST API (用於產品 SEO 文案生成與商品關聯推薦)
- **金物流 (Gateways)**: 綠界科技 ECPay / ECPG 信用卡金流 + 7-11 C2C/B2C 物流門市選擇
- **身份驗證**: LINE Login API (OAuth 2.0 快速登入/註冊)

---

## 2. 主題與目錄結構說明

所有網站的客製化邏輯均封裝於 `wp-content/themes/chao-gang-cheng/` 內，核心檔案結構如下：

```
wp-content/themes/chao-gang-cheng/
├── style.css                      # 主題基本資訊與前台全域自訂 CSS 樣式
├── functions.php                  # 核心 Hook、AI 模組、後台選單整理、顧客標籤、全域常數
├── header.php                     # 前台頁首 HTML 結構與導覽列
├── footer.php                     # 前台頁尾 HTML 結構與 JavaScript 初始化
├── front-page.php                 # 首頁專用模板 (Banner, 熱銷商品卡片, 品牌特色)
├── woocommerce.php                # WooCommerce 頁面通用模板容器
├── DEVELOPMENT_GUIDE.md           # 本開發維護說明文檔
│
├── assets/                        # 靜態資源
│   ├── css/                       # 獨立 CSS 樣式表
│   ├── images/                    # 網站圖示、預設優惠券背景圖、Logo
│   └── fonts/                     # 自訂字型資源
│
└── includes/                      # 功能模組化目錄 (按業務邏輯拆分)
    ├── ckc-coupons.php            # 領券中心、我的優惠券、單券限制、前台 AJAX 套用邏輯
    ├── ckc-points-admin.php       # 會員紅利點數系統、點數記錄與後台發放/扣除管理
    ├── ckc-referral.php           # 推薦分潤前台邏輯與 Cookie 追蹤
    ├── ckc-referral-partner.php   # 合作夥伴 (KOL/團媽) 費率計算與個別商品分潤設定
    ├── ckc-referral-admin.php     # 分潤後台報表、提領審核與 CSV 匯出
    ├── ckc-referral-tier-admin.php# 階梯式分潤設定
    ├── line-login.php             # LINE 快速登入/註冊整合
    ├── ecpay-ecpg-gateway.php     # 綠界 ECPG 信用卡金流整合
    ├── ecpay-sdk/                 # 綠界官方 SDK
    │
    ├── woocommerce/               # WooCommerce 特定頁面與購物流程優化
    │   ├── checkout.php           # 結帳頁面基礎鉤子與自訂欄位
    │   ├── checkout-ux.php        # 結帳頁 UX 美化、地址欄位中置、免運進度條、單一往返 AJAX
    │   ├── cart.php               # 購物車頁面領券彈窗、一鍵全折、繼續購物導引
    │   ├── order-savings.php      # 「已為您省下」金額動態計算與真實免運費計算核心
    │   └── shop-styles.php        # 商店與商品列表頁面專用 CSS 注入
    │
    ├── core/                      # 系統核心工具
    │   └── popup.php              # 網站彈窗促銷系統 (Cookie 記憶與自動跳轉)
    │
    └── api/                       # REST API 接口
        └── agent-actions.php      # 後端自動化與 Agent 控制接口
```

---

## 3. 核心功能與業務邏輯細節

### 3.1 購物車與結帳頁面 UX (Cart & Checkout UX)
- **地址欄位置中與美化 (`checkout-ux.php`)**:
  - 帳單地址與運送地址欄位採用二欄 Flexbox / Grid 排版，行動版上自動置中。
  - 清理了原生過度冗長的地址提示與贅字。
- **單一往返 AJAX 套券 (Single Roundtrip Coupon UX)**:
  - 在 `form.checkout` 中注入隱藏欄位 `ckc_apply_coupon_now`。
  - 當使用者輸入折扣碼或點擊券卡片時，直接觸發 `update_checkout`，後端於同一次 `update_order_review` 請求內完成「套券 + 計算最終金額」，減少等待時間。
- **套券高亮動畫與提示同步**:
  - 套用成功時，訂單總金額觸發 0.6 秒的 `.ckc-price-highlight` 綠色高亮動畫（原為 1.5 秒，為縮短套用流程的回饋等待時間而調整）。
  - **提示時機**：待 0.6 秒高亮動畫完全播放結束後，才在頁面底部浮出 `ckcToast('折價券使用成功')`，確保動畫流暢不中斷。

### 3.2 省下金額與動態運費計算 (Order Savings & Dynamic Shipping)
- **檔案位置**: `includes/woocommerce/order-savings.php`
- **邏輯機制**:
  - **商品特價差額**: `(商品原價 - 實際售價) × 數量` 之總和。
  - **折價券金額**: `cart->get_coupon_discount_amount()` 之總和。
  - **動態運費免運省下計算**:
    - 當購物車達到免運門檻或套用免運折價券時，系統會查詢 `WC()->shipping()->get_packages()` 取得當前配送區域的真實運費（如常溫 $250、冷藏 $290）。
    - 若 WooCommerce 在免運觸發時隱藏了原本的運費選項，系統設有 **保底機制 ($250)**，確保免運時一定會將節省的運費精確計入「已為您省下」總額中。
    - 省下總額算式：`已為您省下 = 商品特價差額 + 折價券折扣 + 免運節省之真實運費`。

### 3.3 CyberBiz 風格折價券與領券中心 (Coupons & Claim Center)
- **檔案位置**: `includes/ckc-coupons.php`
- **每筆訂單限用一張行銷優惠券**:
  - 由 `ckc_order_keep_single_coupon()` 統一於訂單層級控制，排除點數折抵等系統虛擬券（點數折抵可與優惠券並存）。
- **領券中心與專屬優惠券頁**:
  - 提供 Shortcode `[ckc_coupon_claim_center]`，支援分類 Tab 篩選、剩餘庫存進度條、使用規則彈窗。
  - 會員領券後存入 `user_meta: _ckc_claimed_coupons`。
- **UI 狀態即時連動修正**:
  - 當使用者在結帳頁手動點擊 WooCommerce 原生的 `[移除]` 折價券時，前端 JS 會監聽 `updated_checkout` 事件，並限定在明細區域（`.woocommerce-checkout-review-order-table`）檢測已套用代碼，自動將「我的優惠券」卡片狀態還原為「套用去結帳」。

### 3.4 AI 智慧推薦與 AI SEO 文案生成 (AI Integrations)
- **檔案位置**: `functions.php` (搜尋 `ckc_ai_seo_copywriter` 與 `ckc_ai_recommendations`)
- **AI SEO 文案生成器**:
  - 在後台商品編輯頁新增「AI SEO 文案」頁籤。
  - 輸入關鍵字後點擊生成，後端透過 REST API 呼叫 Google Gemini AI 生成符合 SEO 結構的 HTML 產品文案。
  - 支援一鍵寫入 TinyMCE 主要描述或簡短描述。
- **AI 智慧推薦**:
  - 根據商品名稱與分類，由 Gemini 算力計算最相關的推薦商品 ID 並快取至 `_ckc_ai_recommendations` meta 中，顯示於商品頁底部。

### 3.5 會員紅利點數與分潤夥伴系統 (Points & Referral System)
- **紅利點數 (`ckc-points-admin.php`)**:
  - 後台專屬選單：提供點數發放/扣除、異動紀錄查詢、KOL/會員點數排行 Top 10。
  - 結帳頁支援「一鍵全部折抵」，自動將點數換算為折抵金額。
- **分潤夥伴與提領管理 (`ckc-referral-*.php`)**:
  - 支援專屬推薦連結 (`?ref=USER_ID`) 與專屬折扣碼追蹤。
  - 可於商品編輯頁設定「單品獨立分潤費率」（設 0 表示該商品不分潤）。
  - 後台提供分潤報表、佣金結算與 CSV 匯出功能。

### 3.6 第三方整合 (LINE Login / 綠界金物流)
- **LINE 快速登入 (`includes/line-login.php`)**:
  - 支援 LINE OAuth 2.0 授權，自動建立 WP 帳號並綁定 Email / OpenID。
- **綠界金物流 (`includes/ecpay-ecpg-gateway.php`)**:
  - 整合綠界 ECPG 信用卡刷卡金流，支援門市選擇 (7-11 C2C/B2C) 回傳並自動寫入訂單運送資訊。

---

## 4. 後台管理與商品編輯頁維護規範

### ⚠️ 重要：WooCommerce Admin 欄位開發規範
在擴充商品編輯頁面（Product Data Meta Box）的自訂欄位時，**務必遵守以下規範**：

1. **嚴格使用 WooCommerce 官方標準表單函式**：
   - 官方標準文字輸入框函式為：`woocommerce_wp_text_input( $args )`
   - 官方標準核取方塊函式為：`woocommerce_wp_checkbox( $args )`
   - 官方標準下拉選單函式為：`woocommerce_wp_select( $args )`
   - 官方標準多行文字函式為：`woocommerce_wp_textarea_input( $args )`
   - 🛑 **絕不可呼叫不存在的函式**（例如誤寫成 `woocommerce_wp_text_field`）。在 PHP 中呼叫不存在的函式會觸發 **Fatal Error**，導致 PHP 在渲染「一般」頁籤途中瞬間崩潰中斷，進而使「庫存」、「運送方式」、「連結商品」等後續所有頁籤完全無法輸出 HTML，使後台面板呈現一片空白！

2. **JavaScript 變數防護**：
   - 在商品編輯頁面輸出 inline JS 變數時，必須使用 `intval( $post->ID )` 或 `esc_js()`，避免當 `$post->ID` 為空值時輸出 `product_id: ,` 導致語法錯誤 (SyntaxError) 凍結 `meta-boxes-product.js`。

---

## 5. 常見問題與除錯指南 (Troubleshooting)

### Q1: 後台商品編輯頁面的「庫存」、「運送方式」等頁籤點擊後右側是一片空白？
- **原因**: 99% 是由於 PHP 在渲染 Meta Box 時觸發了致命錯誤 (Fatal Error)，或是 inline JS 存在語法錯誤 (SyntaxError) 導致 WooCommerce 的 `meta-boxes-product.js` 停止執行。
- **排查步驟**:
  1. 檢查 `wp-content/debug.log` 或伺服器 PHP Error Log。
  2. 檢查 `functions.php` 是否包含誤寫的 WooCommerce 函式（如 `woocommerce_wp_text_field`）。
  3. 打開瀏覽器開發者工具 (F12) -> Console，確認有無 JS 報錯。

### Q2: 結帳頁手動移除折價券後，「我的優惠券」卡片沒有恢復為「套用去結帳」？
- **原因**: 頁面上的迷你購物車 (Mini-cart) 或其他隱藏區域可能仍殘留舊的 `.cart-discount` HTML 標籤。
- **解法**: 確保 `includes/ckc-coupons.php` 中的 `ckcRefreshCouponCards()` 檢測範圍限定在 `.woocommerce-checkout-review-order-table` 與 `.woocommerce-cart-form` 主區域。

---

## 6. Git 工作流與部署說明 (Deployment Workflow)

網站程式碼使用 Git 進行版本控制，並與正式環境伺服器綁定。

### 📡 遠端儲存庫資訊
- **Remote URL**: `https://github.com/ps449/ckcwordpress.git`
- **主要分支 (Production Branch)**: `main`

### 🚀 部署標準作業程序 (SOP)
在本地進行修改與測試完成後，執行以下指令將變更推播至正式環境：

```bash
# 1. 檢查檔案變更狀態
git status

# 2. 加入所有修改的檔案
git add .

# 3. 提交 Commit 並附帶明確的修改說明
git commit -m "Fix: 修正結帳頁折價券提示時機與後台商品資料欄位"

# 4. 推播至正式環境 main 分支 (自動觸發伺服器更新)
git push origin main
```

> **提示**：部署完成後，若有修改 CSS 或 JS 邏輯，請通知相關人員清除瀏覽器快取 (Ctrl+F5 / Cmd+Shift+R) 或伺服器快取 (如 Cloudflare / Redis) 以確保取得最新版程式碼。

---

*文檔維護者：潮港城電商開發團隊*
