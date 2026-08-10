<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 全站 SEO／GEO（生成式引擎優化）補強
 *
 * 這個檔案只放「新增」的東西，跟 functions.php 裡原本就有的
 * chao_gang_cheng_seo_geo_meta_tags()／chao_gang_cheng_structured_data_local_business()
 * （2026-08 已經在原地修正過重複／canonical 錯誤等問題）分開，避免同一個
 * 巨大檔案越改越難維護：
 *
 * 1. WebSite 結構化資料（首頁，讓 Google 有機會顯示 Sitelinks 搜尋框）。
 * 2. FAQPage 結構化資料：自動掃描頁面內容裡「Q：...A：...」格式的問答，
 *    符合就自動產生 FAQPage JSON-LD，不需要另外設定（目前 /faq/ 頁面本來
 *    就是這個格式，之後其他頁面用同樣格式寫也會自動抓到）。這是特別針對
 *    GEO 加的──AI 答案引擎（ChatGPT／Perplexity／Google AI Overview 等）
 *    特別容易直接引用結構清楚的問答內容。
 * 3. llms.txt：給 AI 答案引擎讀的網站摘要（概念類似 robots.txt，但是給 AI
 *    用的「這個網站是誰、賣什麼、去哪裡找資訊」快速索引），用網址改寫規則
 *    動態輸出純文字，不需要在網站根目錄放實體檔案（部署流程目前只會同步
 *    佈景主題資料夾，實體根目錄檔案不確定會不會被同步上去）。
 * 4. robots.txt 補充：明確允許主要 AI 爬蟲（GPTBot／ClaudeBot／
 *    PerplexityBot…）並附上 llms.txt 位置。網站原本的 robots.txt（由
 *    Jetpack 產生）預設就沒有擋這些爬蟲，這裡是用明確的 Allow 規則把這件
 *    事寫清楚、並且做未來的擴充留一個好改的地方，而不是實際改變允許範圍。
 */

/**
 * 1. WebSite 結構化資料（首頁）。跟 functions.php 裡既有的 Restaurant
 * 結構化資料（@id = home_url('/') . '#restaurant'）用同一個 @id 互相參照。
 */
add_action( 'wp_head', 'chao_gang_cheng_website_schema', 21 );
function chao_gang_cheng_website_schema() {
    if ( ! ( is_front_page() || is_home() ) ) {
        return;
    }

    $sd = array(
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        '@id'             => home_url( '/' ) . '#website',
        'name'            => get_bloginfo( 'name' ),
        'url'             => home_url( '/' ),
        'publisher'       => array( '@id' => home_url( '/' ) . '#restaurant' ),
        'potentialAction' => array(
            '@type'       => 'SearchAction',
            'target'      => array(
                '@type'       => 'EntryPoint',
                'urlTemplate' => home_url( '/?s={search_term_string}&post_type=product' ),
            ),
            'query-input' => 'required name=search_term_string',
        ),
    );

    echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $sd, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}

/**
 * 2. FAQPage 結構化資料。只認 <p><strong>Q：問題</strong><br>A：答案</p>
 * 這個固定格式，沒有符合格式的頁面完全不會輸出任何東西，不影響其他頁面。
 */
add_action( 'wp_head', 'chao_gang_cheng_faq_schema', 25 );
function chao_gang_cheng_faq_schema() {
    if ( ! is_singular() ) {
        return;
    }

    global $post;
    if ( ! $post instanceof WP_Post || empty( $post->post_content ) ) {
        return;
    }

    // 內容可能是 Gutenberg 區塊或純 HTML，先跑過 WP 的內容渲染管線
    // （執行區塊／shortcode 渲染），確保抓到的是「最終看起來」的 HTML，
    // 跟前台使用者實際看到的問答文字一致。
    $rendered = apply_filters( 'the_content', $post->post_content );

    if ( false === stripos( $rendered, 'Q' ) ) {
        return; // 完全沒有 Q 的頁面先快速跳過，省一次 regex
    }

    $pattern = '/<strong>\s*Q[：:]\s*(.+?)\s*<\/strong>\s*<br\s*\/?>\s*A[：:]\s*(.+?)\s*<\/p>/isu';
    if ( ! preg_match_all( $pattern, $rendered, $matches, PREG_SET_ORDER ) || empty( $matches ) ) {
        return;
    }

    $faq_items = array();
    foreach ( $matches as $m ) {
        $question = wp_strip_all_tags( $m[1] );
        $answer   = wp_strip_all_tags( $m[2] );
        if ( '' === $question || '' === $answer ) {
            continue;
        }
        $faq_items[] = array(
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $answer,
            ),
        );
    }

    if ( empty( $faq_items ) ) {
        return;
    }

    $sd = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_items,
    );

    echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $sd, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}

/**
 * 3. llms.txt：用網址改寫規則動態輸出，不是實體檔案。
 */
add_action( 'init', 'chao_gang_cheng_llms_txt_rewrite' );
function chao_gang_cheng_llms_txt_rewrite() {
    add_rewrite_rule( '^llms\.txt$', 'index.php?ckc_llms_txt=1', 'top' );
}

add_filter( 'query_vars', 'chao_gang_cheng_llms_txt_query_var' );
function chao_gang_cheng_llms_txt_query_var( $vars ) {
    $vars[] = 'ckc_llms_txt';
    return $vars;
}

/**
 * 3a. 新增網址改寫規則後，WordPress 需要「刷新」一次規則才會真的生效
 * （平常是存永久連結設定時觸發，這裡用一個一次性的 option 旗標，部署後
 * 第一次有人連進網站時自動刷新一次，之後就不會再刷，不影響效能）。
 */
add_action( 'init', 'chao_gang_cheng_llms_txt_maybe_flush', 20 );
function chao_gang_cheng_llms_txt_maybe_flush() {
    if ( ! get_option( 'ckc_llms_txt_rewrite_flushed_v1' ) ) {
        flush_rewrite_rules();
        update_option( 'ckc_llms_txt_rewrite_flushed_v1', 1 );
    }
}

add_action( 'template_redirect', 'chao_gang_cheng_llms_txt_render' );
function chao_gang_cheng_llms_txt_render() {
    if ( ! get_query_var( 'ckc_llms_txt' ) ) {
        return;
    }

    header( 'Content-Type: text/plain; charset=utf-8' );

    $lines   = array();
    $lines[] = '# ' . get_bloginfo( 'name' );
    $lines[] = '> ' . wp_strip_all_tags( get_bloginfo( 'description' ) );
    $lines[] = '';
    $lines[] = '潮港城是台中在地經營超過30年的辦桌世家「潮港城餐飲集團」旗下電商購物網站，販售常溫禮盒、冷凍美食、年菜、伴手禮，主打總鋪師手路菜宅配到府，配送範圍為台灣本島（不含離島）。';
    $lines[] = '';
    $lines[] = '## 商品分類';

    $product_cats = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        )
    );
    if ( ! is_wp_error( $product_cats ) ) {
        foreach ( $product_cats as $cat ) {
            $lines[] = '- [' . $cat->name . '](' . get_term_link( $cat ) . ')';
        }
    }

    $lines[] = '';
    $lines[] = '## 重要頁面';

    $pages = array(
        'about-us'                      => '關於我們',
        'faq'                            => '常見問題 FAQ',
        'shopping-guide'                 => '購物說明與流程',
        'shipping-policy'                => '配送與運費政策',
        'refund-policy'                  => '退換貨及退款服務',
        'privacy-policy'                 => '隱私權保護政策',
        'product-insurance-registration' => '產品責任險與食登字號',
    );
    foreach ( $pages as $slug => $label ) {
        $page = get_page_by_path( $slug );
        if ( $page ) {
            $lines[] = '- [' . $label . '](' . get_permalink( $page ) . ')';
        }
    }

    $lines[] = '';
    $lines[] = '## 聯絡方式';
    $lines[] = '- 客服專線：04-2386-3322（客服時間：平日 10:00–18:00）';
    $lines[] = '- 客服信箱：service@ckcgroup.com.tw';
    $lines[] = '- 地址：台中市南屯區環中路四段2號';
    $lines[] = '- 統一編號：53301080';
    $lines[] = '';
    $lines[] = 'Sitemap: ' . home_url( '/sitemap.xml' );

    echo implode( "\n", $lines );
    exit;
}

/**
 * 4. robots.txt 補充：明確允許主要 AI 答案引擎的爬蟲，並附上 llms.txt
 * 位置。附加在 Jetpack 產生的內容後面（優先權設較大的數字，確保排在
 * Jetpack 加的 Sitemap 宣告之後），不影響原本任何 Disallow 規則。
 */
add_filter( 'robots_txt', 'chao_gang_cheng_robots_txt_ai_notes', 20 );
function chao_gang_cheng_robots_txt_ai_notes( $output ) {
    $output .= "\n# GEO：歡迎主要 AI 答案引擎爬取本站商品與內容\n";
    $output .= "User-agent: GPTBot\nAllow: /\n\n";
    $output .= "User-agent: ClaudeBot\nAllow: /\n\n";
    $output .= "User-agent: PerplexityBot\nAllow: /\n\n";
    $output .= "User-agent: OAI-SearchBot\nAllow: /\n\n";
    $output .= "User-agent: Google-Extended\nAllow: /\n\n";
    $output .= 'llms.txt: ' . home_url( '/llms.txt' ) . "\n";

    return $output;
}
