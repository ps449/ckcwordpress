<?php
/**
 * 翻譯 Facebook for WooCommerce 或 Google Listings 的預設 Google 商品分類（頂層與子分類）
 */
add_action( 'admin_enqueue_scripts', 'ckc_enqueue_google_category_translation_script' );
function ckc_enqueue_google_category_translation_script() {
    if ( ! is_admin() ) return;
    
    // 載入完整 5500+ 項目的翻譯字典
    wp_enqueue_script(
        'ckc-google-category-map',
        get_template_directory_uri() . '/includes/ckc-google-category-map.js',
        array(),
        '1.0',
        true
    );
}

add_action( 'admin_footer', 'ckc_translate_google_product_categories_js' );
function ckc_translate_google_product_categories_js() {
    if ( ! is_admin() ) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        // 使用從外部 JS 載入的字典，若無則回退空物件
        var categoryMap = (typeof ckcGoogleCategoryMap !== 'undefined') ? ckcGoogleCategoryMap : {};

        function translateText(text) {
            if (!text) return text;
            var segments = text.split(' > ');
            for (var i = 0; i < segments.length; i++) {
                var segment = segments[i].trim();
                if (categoryMap[segment]) {
                    segments[i] = categoryMap[segment];
                }
            }
            return segments.join(' > ');
        }

        function translateCategories() {
            // 處理 Select2 或標準 select 的選項
            $('.select2-results__option, option').each(function() {
                var el = $(this);
                if (el.children().length > 0 && el.prop('tagName').toLowerCase() !== 'option') return;
                
                var text = el.text().trim();
                if (!text) return;

                var translated = translateText(text);
                if (text !== translated) {
                    el.text(translated);
                }
            });

            // 處理 Select2 已經選中的項目顯示
            $('.select2-selection__rendered').each(function() {
                var el = $(this);
                var childNodes = el[0].childNodes;
                for (var i = 0; i < childNodes.length; i++) {
                    var node = childNodes[i];
                    if (node.nodeType === 3) { // 文字節點
                        var text = node.nodeValue.trim();
                        if (!text) continue;

                        var translated = translateText(text);
                        if (text !== translated) {
                            node.nodeValue = ' ' + translated;
                        }
                    }
                }
            });
        }

        // 由於選項可能是非同步載入或動態渲染，使用 MutationObserver 監控
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                var needsTranslation = false;
                for (var i = 0; i < mutations.length; i++) {
                    var mutation = mutations[i];
                    if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                        for (var j = 0; j < mutation.addedNodes.length; j++) {
                            var node = mutation.addedNodes[j];
                            if (node.classList && (node.classList.contains('select2-results__options') || node.classList.contains('select2-results__option') || node.classList.contains('select2-selection__rendered'))) {
                                needsTranslation = true;
                                break;
                            } else if (node.nodeType === 1 && node.querySelector && node.querySelector('.select2-results__option')) {
                                needsTranslation = true;
                                break;
                            }
                        }
                    }
                    if (needsTranslation) break;
                }
                
                if (needsTranslation) {
                    translateCategories();
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });
        }
        
        // 初次執行與延遲執行確保抓到
        translateCategories();
        setTimeout(translateCategories, 500);
        setTimeout(translateCategories, 2000);
    });
    </script>
    <?php
}
