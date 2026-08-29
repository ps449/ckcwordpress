<?php
/**
 * 29. AJAX 呼叫 Gemini API 進行聊天對話 (具備實時讀取與寫入資料庫之 Agent 功能)
 */
add_action( 'wp_ajax_ckc_gemini_chat', 'ckc_ajax_gemini_chat' );
function ckc_ajax_gemini_chat() {
    check_ajax_referer( 'ckc_gemini_nonce', 'security' );
    if ( ! current_user_can( 'edit_pages' ) ) {
        wp_send_json_error( array( 'message' => '權限不足' ) );
    }

    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
    if ( empty( $message ) ) {
        wp_send_json_error( array( 'message' => '訊息不能為空' ) );
    }

    $api_key = get_option( 'ckc_gemini_api_key' );
    
    // System Instruction for E-commerce Assistant / Agent
    $system_context = "你是一位整合在「潮港城國際美食館」電商後台的「出貨AI助理」。你已具備自主 Agent 功能，能直接讀取與寫入商店資料庫。請以專業、親切的語氣協助管理者，根據系統提供的 [即時數據庫內容] 或 [系統動作執行紀錄] 進行回答與回報。回答請簡明扼要。";
    
    // --- 1a. 解析並執行訂單狀態寫入操作 (Agent Action: Update Order Status) ---
    $action_result = '';
    $has_action = false;
    if ( preg_match( '/(?:標記|修改|變更|更新)訂單\s*#?(\d+)\s*(?:為|的狀態為)?\s*(已出貨|已完成|處理中|取消|完成|completed|processing|cancelled)/u', $message, $matches ) ) {
        $order_id = intval( $matches[1] );
        $raw_status = $matches[2];
        $has_action = true;
        
        $status_map = array(
            '已出貨' => 'completed',
            '已完成' => 'completed',
            '完成'   => 'completed',
            'completed' => 'completed',
            '處理中' => 'processing',
            'processing' => 'processing',
            '取消'   => 'cancelled',
            'cancelled' => 'cancelled',
        );
        
        $target_status = isset( $status_map[$raw_status] ) ? $status_map[$raw_status] : '';
        if ( ! empty( $api_key ) ) {
            if ( $target_status && function_exists( 'wc_get_order' ) ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $order->update_status( $target_status, '由 出貨AI助理 自動更新。' );
                    $action_result = "\n[系統動作執行紀錄]：成功將訂單編號 #{$order_id} 的狀態變更為「{$raw_status}」！";
                } else {
                    $action_result = "\n[系統動作執行紀錄]：執行失敗，找不到訂單編號 #{$order_id}。";
                }
            }
        } else {
            // Sandbox Mode Mock Action
            $action_result = "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已成功將模擬訂單 #{$order_id} 狀態變更為「{$raw_status}」！(如配置真實 API Key 將直接修改資料庫)";
        }
    }

    // --- 1b. 解析並執行商品庫存寫入操作 (Agent Action: Update Stock Level) ---
    if ( preg_match( '/(?:更新|修改|調整)商品\s*「?([^」\n]+)」?\s*(?:的)?庫存(?:數量)?為\s*(\d+)\s*(?:件|包|個)?/u', $message, $matches ) ) {
        $product_name_query = trim( $matches[1] );
        $target_qty = intval( $matches[2] );
        $has_action = true;
        
        if ( ! empty( $api_key ) && function_exists( 'wc_get_products' ) ) {
            $prods = wc_get_products( array( 'title' => $product_name_query, 'limit' => 1 ) );
            if ( ! empty( $prods ) ) {
                $product = reset( $prods );
                $product->set_manage_stock( true );
                $product->set_stock_quantity( $target_qty );
                $product->save();
                $action_result = "\n[系統動作執行紀錄]：成功將商品「{$product->get_name()}」的庫存數量更新為 {$target_qty} 件！";
            } else {
                $action_result = "\n[系統動作執行紀錄]：執行失敗，找不到品名為「{$product_name_query}」的商品。";
            }
        } else {
            // Sandbox Mode Mock Action
            $action_result = "\n[系統動作執行紀錄]：【模擬沙盒動作成功】成功將商品「{$product_name_query}」的模擬庫存數量更新為 {$target_qty} 件！(如配置真實 API Key 將直接修改資料庫)";
        }
    }

    // --- 1c. 解析並執行批次出貨寫入操作 (Agent Action: Batch Update Order Status to Shipped) ---
    if ( preg_match( '/(?:批次自動化出貨|批次將.*(?:變更|更新|標記)為已出貨|批次更新.*已出貨|批次一鍵)/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) ) {
            if ( function_exists( 'wc_get_orders' ) ) {
                $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
                $updated_ids = array();
                foreach ( $orders as $order ) {
                    $order->update_status( 'completed', '由 出貨AI助理 批次自動化出貨。' );
                    $updated_ids[] = '#' . $order->get_id();
                }
                $count = count( $updated_ids );
                if ( $count > 0 ) {
                    $action_result = "\n[系統動作執行紀錄]：成功完成批次自動化出貨！共更新了 {$count} 筆處理中訂單（訂單編號：" . implode( ', ', $updated_ids ) . "）為「已出貨」狀態。";
                } else {
                    $action_result = "\n[系統動作執行紀錄]：目前沒有狀態為「處理中」的待出貨訂單。";
                }
            }
        } else {
            // Sandbox Mode Mock Action
            $action_result = "\n[系統動作執行紀錄]：【模擬沙盒動作成功】成功將模擬訂單 #10245, #10246, #10247 一鍵批次更新為「已出貨」狀態！(如配置真實 API Key 將直接批次修改真實資料庫)";
        }
    }

    // --- 1d. 填入黑貓託運單號（單筆）(Agent Action: Fill T-cat Tracking Number) ---
    $tracking_filled = false;
    $single_pairs = array();
    if ( preg_match( '/訂單\s*#?(\d+)\s*(?:的)?\s*(?:黑貓)?\s*託運單號\s*(?:為|是|填入|[:：])?\s*([0-9\-]{8,20})/u', $message, $m ) ) {
        $single_pairs[] = array( intval( $m[1] ), $m[2] );
    } elseif ( preg_match( '/(?:黑貓)?託運單號\s*[:：]?\s*([0-9\-]{8,20})\s*(?:填入|寫入|登記到|填到)\s*訂單\s*#?(\d+)/u', $message, $m ) ) {
        $single_pairs[] = array( intval( $m[2] ), $m[1] );
    }
    if ( ! empty( $single_pairs ) ) {
        $has_action = true;
        $tracking_filled = true;
        list( $t_order_id, $t_no ) = $single_pairs[0];
        if ( ! empty( $api_key ) && function_exists( 'wc_get_order' ) ) {
            if ( ckc_tcat_fill_tracking( $t_order_id, $t_no ) ) {
                $action_result .= "\n[系統動作執行紀錄]：成功將黑貓託運單號 {$t_no} 填入訂單 #{$t_order_id}，並已寄送出貨通知（含託運單號與查詢連結）給客戶！";
            } else {
                $action_result .= "\n[系統動作執行紀錄]：執行失敗，找不到訂單編號 #{$t_order_id}。";
            }
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已將黑貓託運單號 {$t_no} 填入模擬訂單 #{$t_order_id}！(如配置真實 API Key 將直接寫入資料庫並通知客戶)";
        }
    }

    // --- 1e. 批次填入黑貓託運單號（貼上黑貓契客系統匯出的「訂單號 託運單號」清單）---
    if ( ! $tracking_filled && preg_match( '/(?:批次|匯入|貼上).{0,20}託運單號|託運單號.{0,20}(?:批次|匯入|清單)/u', $message ) ) {
        if ( preg_match_all( '/#?(\d{2,8})\s*[:：,，\t、 ]+\s*(\d{10,13})\b/u', $message, $pair_matches, PREG_SET_ORDER ) && count( $pair_matches ) > 0 ) {
            $has_action = true;
            $tracking_filled = true;
            if ( ! empty( $api_key ) && function_exists( 'wc_get_order' ) ) {
                $ok = array();
                $fail = array();
                foreach ( array_slice( $pair_matches, 0, 50 ) as $pm ) {
                    if ( ckc_tcat_fill_tracking( intval( $pm[1] ), $pm[2] ) ) {
                        $ok[] = '#' . $pm[1] . '→' . $pm[2];
                    } else {
                        $fail[] = '#' . $pm[1];
                    }
                }
                $action_result .= "\n[系統動作執行紀錄]：批次填入黑貓託運單號完成！成功 " . count( $ok ) . " 筆（" . implode( '、', $ok ) . "）";
                if ( $fail ) {
                    $action_result .= "；失敗 " . count( $fail ) . " 筆（找不到訂單：" . implode( '、', $fail ) . "）";
                }
                $action_result .= "。每筆成功訂單皆已寄送含託運單號的出貨通知給客戶。";
            } else {
                $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已批次填入 " . count( $pair_matches ) . " 筆模擬黑貓託運單號！(如配置真實 API Key 將直接寫入資料庫並通知客戶)";
            }
        }
    }

    // --- 1f. 自動抓取黑貓貨態並回填後台 (Agent Action: Auto-fetch T-cat Delivery Status) ---
    if ( ! $tracking_filled && preg_match( '/(?:抓取|同步|查詢|更新).{0,15}黑貓|黑貓.{0,15}(?:貨態|貨運狀態|配送狀態|狀態)/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array(
                'limit'      => 10,
                'orderby'    => 'date',
                'order'      => 'DESC',
                'meta_query' => array(
                    array(
                        'key'     => '_tcat_tracking_number',
                        'compare' => 'EXISTS',
                    ),
                ),
            ) );
            if ( empty( $orders ) ) {
                $action_result .= "\n[系統動作執行紀錄]：目前沒有任何已填入黑貓託運單號的訂單，請先填入託運單號後再執行貨態抓取。";
            } else {
                $lines = array();
                foreach ( $orders as $order ) {
                    $t_no = $order->get_meta( '_tcat_tracking_number' );
                    $status = ckc_tcat_fetch_status( $t_no );
                    if ( '' !== $status ) {
                        $prev = $order->get_meta( '_tcat_last_status' );
                        $order->update_meta_data( '_tcat_last_status', $status );
                        $order->update_meta_data( '_tcat_last_status_time', current_time( 'mysql' ) );
                        $order->save();
                        if ( $prev !== $status ) {
                            $order->add_order_note( sprintf( '黑貓貨態更新：%s（託運單號 %s，由出貨AI助理自動抓取）', $status, $t_no ) );
                        }
                        $lines[] = "- 訂單 #{$order->get_id()}（託運單號 {$t_no}）：{$status}" . ( false !== mb_strpos( $status, '順利送達' ) ? ' ✅' : '' );
                    } else {
                        $lines[] = "- 訂單 #{$order->get_id()}（託運單號 {$t_no}）：暫時查無貨態（可能尚未集貨或官網查詢暫時無回應），可稍後重試或至黑貓官網人工查詢。";
                    }
                }
                $action_result .= "\n[系統動作執行紀錄]：黑貓貨態自動抓取完成（最近 " . count( $orders ) . " 筆有託運單號的訂單），結果已回填至各訂單紀錄：\n" . implode( "\n", $lines );
            }
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已自動抓取 3 筆模擬訂單的黑貓貨態並回填後台：#10245 配送中、#10246 已集貨、#10247 順利送達 ✅ (如配置真實 API Key 將實際查詢黑貓官網並寫入資料庫)";
        }
    }

    // --- 1h. 夥伴分潤對帳單 (Agent Query: Partner Payout Statement) ---
    if ( preg_match( '/(?:夥伴|出金).{0,10}(?:對帳|對帳單|結算)|對帳單/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'ckc_refp_statement_text' ) ) {
            $action_result .= "\n[系統動作執行紀錄]：\n" . ckc_refp_statement_text();
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒數據】夥伴分潤對帳單：王小明（KOL，8%）可出金 NT$3,200（扣繳 NT$0、二代健保 NT$0、實付 NT$3,200）。(如配置真實 API Key 將讀取真實帳本)";
        }
    }

    // --- 1i. 標記夥伴出金 (Agent Action: Mark Partner Payout as Paid) ---
    if ( preg_match( '/(?:標記|完成).{0,15}會員\s*ID?\s*(\d+).{0,20}(?:已出金|出金|已付款|已匯款)/u', $message, $m ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'ckc_refp_mark_paid' ) ) {
            $action_result .= "\n[系統動作執行紀錄]：" . ckc_refp_mark_paid( intval( $m[1] ) );
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已將會員 ID {$m[1]} 的可出金分潤標記為已出金！(如配置真實 API Key 將實際更新帳本)";
        }
    }

    // --- 1j. 核准推廣夥伴 (Agent Action: Approve Partner Application) ---
    if ( preg_match( '/核准.{0,10}會員\s*ID?\s*(\d+).{0,15}(?:成為)?(?:推廣)?夥伴(?:.{0,15}?(\d+(?:\.\d+)?)\s*%)?/u', $message, $m ) ) {
        $has_action = true;
        $approve_id = intval( $m[1] );
        $approve_rate = isset( $m[2] ) && '' !== $m[2] ? $m[2] : '';
        if ( ! empty( $api_key ) && function_exists( 'ckc_refp_partner_type' ) ) {
            if ( get_user_by( 'id', $approve_id ) ) {
                $p_type = ( false !== mb_strpos( $message, '團購' ) ) ? 'groupbuyer' : 'kol';
                update_user_meta( $approve_id, '_ckc_ref_partner', $p_type );
                delete_user_meta( $approve_id, '_ckc_ref_partner_apply' );
                if ( '' !== $approve_rate ) {
                    update_user_meta( $approve_id, '_ckc_ref_partner_rate', $approve_rate );
                }
                $action_result .= "\n[系統動作執行紀錄]：已核准會員 ID {$approve_id} 成為推廣夥伴（" . ( 'kol' === $p_type ? 'KOL' : '團購主' ) . ( '' !== $approve_rate ? "，費率 {$approve_rate}%" : '，費率預設 8%' ) . "），其推薦訂單將改走現金分潤軌。";
            } else {
                $action_result .= "\n[系統動作執行紀錄]：執行失敗，找不到會員 ID {$approve_id}。";
            }
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已核准會員 ID {$approve_id} 成為推廣夥伴！(如配置真實 API Key 將實際更新會員資料)";
        }
    }

    // --- 1g. 推薦分潤報表 (Agent Query: Referral Commission Report) ---
    if ( preg_match( '/(?:推薦|分潤).{0,10}(?:報表|統計|概況|成效)|(?:報表|統計).{0,10}(?:推薦|分潤)/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'ckc_ref_admin_report_text' ) ) {
            $action_result .= "\n[系統動作執行紀錄]：推薦分潤報表查詢結果：\n" . ckc_ref_admin_report_text();
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒數據】推薦訂單共 12 筆，推薦營收 NT$18,600，已發放分潤 930 點。Top 推薦人：1. 王小明：5 筆訂單，累計 420 點。(如配置真實 API Key 將讀取真實資料庫)";
        }
    }

    // --- 2. 解析並讀取即時資料庫數據 (Agent Query) ---
    $db_context = '';
    
    // a. 待出貨訂單
    if ( ( stripos( $message, '出貨' ) !== false || stripos( $message, '處理中' ) !== false ) && stripos( $message, '健康檢查' ) === false && stripos( $message, '營運檢查' ) === false ) {
        if ( stripos( $message, '統計' ) === false && stripos( $message, '概況' ) === false && stripos( $message, '撿貨' ) === false && stripos( $message, '配貨' ) === false && stripos( $message, '宅配' ) === false && stripos( $message, '名冊' ) === false && stripos( $message, '物流' ) === false ) {
            if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
                $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 10 ) );
                $formatted_orders = array();
                foreach ( $orders as $order ) {
                    $items = array();
                    foreach ( $order->get_items() as $item ) {
                        $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                    }
                    $user_id = $order->get_customer_id();
                    $tags = get_user_meta( $user_id, 'ckc_customer_tags', true );
                    $tags_str = ! empty( $tags ) && is_array( $tags ) ? implode( ', ', $tags ) : '無';
                    $formatted_orders[] = sprintf(
                        "- 訂單編號: #%d, 收件人: %s, 聯絡電話: %s, 商品: %s, 金額: $%s, 狀態: %s, 會員標籤: %s",
                        $order->get_id(),
                        $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        $order->get_billing_phone(),
                        implode( ', ', $items ),
                        $order->get_total(),
                        $order->get_status(),
                        $tags_str
                    );
                }
                $db_context .= "\n[即時待出貨訂單列表]：\n" . ( empty( $formatted_orders ) ? "目前沒有待出貨的訂單。" : implode( "\n", $formatted_orders ) );
            } else {
                // Sandbox Mode Mock Data
                $db_context .= "\n[即時待出貨訂單列表]：\n" .
                               "- 訂單編號: #10245, 收件人: 王小明, 聯絡電話: 0912-345678, 商品: 潮港城一斤肉牛肉爐 x 2, 金額: $1980, 狀態: 處理中\n" .
                               "- 訂單編號: #10246, 收件人: 李美華, 聯絡電話: 0928-111222, 商品: 太陽百匯平日午餐券 x 4, 金額: $3520, 狀態: 處理中\n" .
                               "- 訂單編號: #10247, 收件人: 陳大同, 聯絡電話: 0933-444555, 商品: 黃金鮑魚土雞煲 x 1, 金額: $1280, 狀態: 處理中";
            }
        }
    }
    
    // b. 最新訂單
    if ( stripos( $message, '最新' ) !== false && stripos( $message, '撿貨' ) === false && stripos( $message, '配貨' ) === false && stripos( $message, '宅配' ) === false && stripos( $message, '名冊' ) === false && stripos( $message, '物流' ) === false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) );
            $formatted_orders = array();
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }
                $formatted_orders[] = sprintf(
                    "- 訂單編號: #%d, 收件人: %s, 商品: %s, 金額: $%s, 狀態: %s",
                    $order->get_id(),
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    implode( ', ', $items ),
                    $order->get_total(),
                    $order->get_status()
                );
            }
            $db_context .= "\n[即時最新 5 筆訂單列表]：\n" . ( empty( $formatted_orders ) ? "目前沒有任何訂單。" : implode( "\n", $formatted_orders ) );
        } else {
            // Sandbox Mode Mock Data
            $db_context .= "\n[即時最新 5 筆訂單列表]：\n" .
                           "- 訂單編號: #10248, 收件人: 林曉婷, 商品: 港式臘味蘿蔔糕 x 2, 金額: $560, 狀態: 等待付款\n" .
                           "- 訂單編號: #10247, 收件人: 陳大同, 商品: 黃金鮑魚土雞煲 x 1, 金額: $1280, 狀態: 處理中\n" .
                           "- 訂單編號: #10246, 收件人: 李美華, 商品: 太陽百匯平日午餐券 x 4, 金額: $3520, 狀態: 處理中\n" .
                           "- 訂單編號: #10245, 收件人: 王小明, 商品: 潮港城一斤肉牛肉爐 x 2, 金額: $1980, 狀態: 處理中\n" .
                           "- 訂單編號: #10244, 收件人: 趙自強, 商品: 經典海鮮煲 x 1, 金額: $1580, 狀態: 已完成";
        }
    }

    // c. 庫存查詢 / 低庫存商品
    if ( ( stripos( $message, '庫存' ) !== false || stripos( $message, '警報' ) !== false ) && !$has_action && stripos( $message, '健康檢查' ) === false && stripos( $message, '營運檢查' ) === false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_products' ) ) {
            $products = wc_get_products( array( 'limit' => 40 ) );
            $formatted_products = array();
            foreach ( $products as $product ) {
                $stock = $product->get_stock_quantity();
                $stock_status = $product->get_stock_status();
                
                if ( stripos( $message, '低' ) !== false || stripos( $message, '警報' ) !== false ) {
                    // 低庫存篩選
                    if ( $product->managing_stock() && $stock <= 15 ) {
                        $formatted_products[] = sprintf( "- 商品: %s (SKU: %s), 剩餘庫存: %d 件, 狀態: %s", $product->get_name(), $product->get_sku(), $stock, $stock_status );
                    }
                } else {
                    $formatted_products[] = sprintf( "- 商品: %s (SKU: %s), 剩餘庫存: %s 件, 狀態: %s", $product->get_name(), $product->get_sku(), $product->managing_stock() ? $stock : '未啟用庫存管理', $stock_status );
                }
            }
            $db_context .= "\n[即時低庫存商品列表]：\n" . ( empty( $formatted_products ) ? "目前沒有符合條件的商品。" : implode( "\n", $formatted_products ) );
        } else {
            // Sandbox Mode Mock Data
            $db_context .= "\n[即時低庫存商品列表]：\n" .
                           "- 商品: 潮港城一斤肉牛肉爐 (SKU: CKC-BEEF-01), 剩餘庫存: 5 件, 狀態: instock\n" .
                           "- 商品: 黃金鮑魚土雞煲 (SKU: CKC-CHICKEN-02), 剩餘庫存: 2 件, 狀態: instock\n" .
                           "- 商品: 太陽百匯平日午餐券 (SKU: CKC-TICKET-WD), 剩餘庫存: 12 件, 狀態: instock";
        }
    }

    // d. 查詢特定訂單詳細狀況
    if ( preg_match( '/訂單\s*#?(\d+)/u', $message, $matches ) && ! $has_action ) {
        $order_id = intval( $matches[1] );
        if ( ! empty( $api_key ) && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }
                $user_id = $order->get_customer_id();
                $tags = get_user_meta( $user_id, 'ckc_customer_tags', true );
                $tags_str = ! empty($tags) && is_array($tags) ? implode(', ', $tags) : '無';
                $db_context .= sprintf(
                    "\n[即時訂單 #%d 詳細資訊]：\n- 狀態: %s\n- 收件人: %s\n- 聯絡電話: %s\n- 配送地址: %s\n- 購買商品: %s\n- 總金額: $%s\n- 付款方式: %s\n- 建立日期: %s\n- 會員標籤: %s",
                    $order_id,
                    $order->get_status(),
                    $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                    $order->get_billing_phone(),
                    $order->get_shipping_address_1() . ' ' . $order->get_shipping_city(),
                    implode( ', ', $items ),
                    $order->get_total(),
                    $order->get_payment_method_title(),
                    $order->get_date_created()->date('Y-m-d H:i:s'),
                    $tags_str
                );
            } else {
                $db_context .= "\n[即時資料庫搜尋結果]：找不到訂單編號 #{$order_id}。";
            }
        } else {
            // Sandbox Mode Mock Detail
            $db_context .= "\n[即時訂單 #{$order_id} 詳細資訊]：\n- 狀態: 處理中 (processing)\n- 收件人: 李美華\n- 聯絡電話: 0928-111222\n- 配送地址: 台中市南屯區公益路二段99號\n- 購買商品: 太陽百匯平日午餐券 x 4\n- 總金額: $3520\n- 付款方式: 信用卡線上支付\n- 建立日期: " . date('Y-m-d') . " 10:14:32";
        }
    }

    // e. 搜尋特定收件人的訂單
    if ( preg_match( '/(?:搜尋|查詢)收件人\s*「?([^」\n]+)」?/u', $message, $matches ) ) {
        $name = trim( $matches[1] );
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'billing_first_name' => $name, 'limit' => 5 ) );
            if ( empty( $orders ) ) {
                $orders = wc_get_orders( array( 'shipping_first_name' => $name, 'limit' => 5 ) );
            }
            $formatted_orders = array();
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }
                $formatted_orders[] = sprintf(
                    "- 訂單 #%d | 收件人: %s | 商品: %s | 金額: $%s | 狀態: %s",
                    $order->get_id(),
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    implode( ', ', $items ),
                    $order->get_total(),
                    $order->get_status()
                );
            }
            $db_context .= "\n[收件人「{$name}」的訂單記錄]：\n" . ( empty( $formatted_orders ) ? "查無此收件人的訂單。" : implode( "\n", $formatted_orders ) );
        } else {
            // Sandbox Mode Mock Search
            if ( $name === '王小明' ) {
                $db_context .= "\n[收件人「王小明」的訂單記錄]：\n- 訂單 #10245 | 收件人: 王小明 | 商品: 潮港城一斤肉牛肉爐 x 2 | 金額: $1980 | 狀態: 處理中";
            } else {
                $db_context .= "\n[收件人「{$name}」的訂單記錄]：\n- 訂單 #10250 | 收件人: {$name} | 商品: 經典海鮮煲 x 1 | 金額: $1580 | 狀態: 已完成";
            }
        }
    }

    // f. 統計今日/本月出貨與銷售概況
    if ( stripos( $message, '統計' ) !== false || stripos( $message, '概況' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $all_orders = wc_get_orders( array( 'limit' => 100, 'status' => array( 'completed', 'processing' ) ) );
            $total_sales = 0;
            $order_count = count( $all_orders );
            foreach ( $all_orders as $order ) {
                $total_sales += floatval( $order->get_total() );
            }
            $db_context .= sprintf(
                "\n[即時商店銷售統計]：\n- 統計期間：最近 100 筆已付款訂單\n- 總訂單筆數：%d 筆\n- 累計總銷售金額：$%s\n- 待出貨訂單數：%d 筆",
                $order_count,
                number_format( $total_sales, 2 ),
                count( wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) ) )
            );
        } else {
            // Sandbox Mode Mock Statistics
            $db_context .= "\n[即時商店銷售統計]：\n- 統計期間：本月份截至今日\n- 總訂單筆數：158 筆\n- 累計總銷售金額：$284,500\n- 已完成出貨：142 筆\n- 待出貨處理中：16 筆\n- 本月最暢銷商品：\n  1. 潮港城一斤肉牛肉爐 (銷量: 235 包)\n  2. 太陽百匯平日午餐券 (銷量: 180 張)";
        }
    }

    // g. 產生配貨與撿貨清單
    if ( stripos( $message, '撿貨' ) !== false || stripos( $message, '配貨' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
            $picking_list = array();
            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    $prod_name = $item->get_name();
                    $qty = $item->get_quantity();
                    $product = $item->get_product();
                    $sku = $product ? $product->get_sku() : '無 SKU';
                    
                    if ( isset( $picking_list[$prod_name] ) ) {
                        $picking_list[$prod_name]['qty'] += $qty;
                        $picking_list[$prod_name]['orders'][] = '#' . $order->get_id();
                    } else {
                        $picking_list[$prod_name] = array(
                            'sku' => $sku,
                            'qty' => $qty,
                            'orders' => array( '#' . $order->get_id() )
                        );
                    }
                }
            }
            $formatted_picking = array();
            foreach ( $picking_list as $name => $info ) {
                $formatted_picking[] = sprintf(
                    "- **%s** (SKU: %s) ➔ 總需求: **%d** 件 (來自訂單: %s)",
                    $name,
                    $info['sku'],
                    $info['qty'],
                    implode( ', ', $info['orders'] )
                );
            }
            $db_context .= "\n[即時配貨與撿貨總量清單]：\n" . ( empty( $formatted_picking ) ? "目前沒有待處理訂單，無須備貨。" : implode( "\n", $formatted_picking ) );
        } else {
            // Sandbox Mock Picking List
            $db_context .= "\n[即時配貨與撿貨總量清單]：\n" .
                           "- **潮港城一斤肉牛肉爐** (SKU: CKC-BEEF-01) ➔ 總需求: **2** 包 (來自訂單: #10245)\n" .
                           "- **太陽百匯平日午餐券** (SKU: CKC-TICKET-WD) ➔ 總需求: **4** 張 (來自訂單: #10246)\n" .
                           "- **黃金鮑魚土雞煲** (SKU: CKC-CHICKEN-02) ➔ 總需求: **1** 包 (來自訂單: #10247)";
        }
    }

    // h. 物流宅配名冊
    if ( stripos( $message, '宅配' ) !== false || stripos( $message, '名冊' ) !== false || stripos( $message, '物流' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
            $manifest = array();
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x' . $item->get_quantity();
                }
                $manifest[] = sprintf(
                    "訂單: #%d | 收件人: %s | 電話: %s | 地址: %s | 內容: %s",
                    $order->get_id(),
                    $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                    $order->get_billing_phone(),
                    $order->get_shipping_address_1() . ' ' . $order->get_shipping_city(),
                    implode( ', ', $items )
                );
            }
            $db_context .= "
[即時物流宅配名冊]：
" . ( empty( $manifest ) ? "目前沒有待出貨訂單的宅配資訊。" : implode( "
", $manifest ) );
        } else {
            // Sandbox Mock Manifest
            $db_context .= "
[即時物流宅配名冊]：
" .
                           "1. 訂單 #10245 | 收件人: 王小明 | 電話: 0912-345678 | 地址: 台中市南屯區公益路二段99號 | 內容: 潮港城一斤肉牛肉爐 x2
" .
                           "2. 訂單 #10246 | 收件人: 李美華 | 電話: 0928-111222 | 地址: 台北市大安區信義路四段100號 | 內容: 太陽百匯平日午餐券 x4
" .
                           "3. 訂單 #10247 | 收件人: 陳大同 | 電話: 0933-444555 | 地址: 台南市中西區民權路三段50號 | 內容: 黃金鮑魚土雞煲 x1";
        }
    }

    // i. 自動化營運與庫存健康檢查
    if ( stripos( $message, '健康檢查' ) !== false || stripos( $message, '營運檢查' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            // Check processing orders created > 24 hours ago
            $processing_orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
            $delayed_orders = array();
            $now = current_time( 'timestamp' );
            foreach ( $processing_orders as $order ) {
                $created = $order->get_date_created()->getTimestamp();
                if ( ($now - $created) > 86400 ) {
                    $hours = round( ($now - $created) / 3600 );
                    $delayed_orders[] = sprintf( "- 訂單 #%d (%s) - 已等待 %d 小時未出貨", $order->get_id(), $order->get_billing_first_name(), $hours );
                }
            }
            
            // Check out-of-stock or negative stock items
            $products = wc_get_products( array( 'limit' => 100 ) );
            $out_of_stock = array();
            $out_of_stock = array();
            foreach ( $products as $product ) {
                if ( $product->managing_stock() ) {
                    $stock = $product->get_stock_quantity();
                    if ( $stock <= 0 ) {
                        $out_of_stock[] = sprintf( "- %s (SKU: %s, 目前庫存: %d 件)", $product->get_name(), $product->get_sku(), $stock );
                    }
                }
            }
            
            $db_context .= "\n[自動化每日營運檢查報告]：\n";
            $db_context .= "【訂單出貨時效稽核】：\n" . ( empty( $delayed_orders ) ? "✅ 太棒了！目前沒有付款超過 24 小時卻未出貨 the delayed orders。\n" : implode( "\n", $delayed_orders ) . "\n" );
            $db_context .= "【缺貨/斷貨商品監控】：\n" . ( empty( $out_of_stock ) ? "✅ 良好！目前沒有庫存為零或负數的商品。\n" : implode( "\n", $out_of_stock ) . "\n" );
        } else {
            // Sandbox Mock Health Check Report
            $db_context .= "\n[自動化每日營運檢查報告]：\n" .
                           "【訂單出貨時效稽核】：\n" .
                           "- ⚠️ 訂單 #10240 (林阿美) - 已等待 48 小時未出貨 (待處理)\n" .
                           "- ⚠️ 訂單 #10242 (張大千) - 已等待 36 小時未出貨 (待處理)\n" .
                           "【缺貨/斷貨商品監控】：\n" .
                           "- ❌ 潮港城招牌蘿蔔糕 (SKU: CKC-CAKE-01, 目前庫存: 0 件)\n" .
                           "- ❌ 冷凍熟凍小龍蝦 (SKU: CKC-CRAY-05, 目前庫存: -2 件)\n" .
                           "【營運優化建議】：\n" .
                           "1. 延遲訂單請儘速包裝寄出，避免引起客訴。\n" .
                           "2. 招牌蘿蔔糕為缺貨商品，需通知廚房備料上架。小龍蝦出現負庫存，請確認實體倉儲數量與系統設定。";
        }
    }

    // --- 3. 處理模擬測試模式與真實 API 發送 ---
    if ( empty( $api_key ) ) {
        // 沙盒模擬模式下的 AI 回應組合
        $reply = "【沙盒模擬模式測試】\n我是您的出貨AI助理。目前系統處於模擬對話沙盒中：\n\n";
        
        if ( $has_action ) {
            $reply .= $action_result . "\n\n💡 配置您的真實 Gemini API 金鑰後，此指令將在資料庫中自動對 WooCommerce 進行修改！";
        } elseif ( stripos( $message, '撿貨' ) !== false || stripos( $message, '配貨' ) !== false ) {
            $reply .= "📋 **今日待出貨「配貨與撿貨總量清單」** (合併總數量)：\n\n" .
                      "| 商品名稱 | SKU | 待撿總數量 | 來源訂單 |\n" .
                      "| :--- | :--- | :---: | :--- |\n" .
                      "| **潮港城一斤肉牛肉爐** | CKC-BEEF-01 | **2** 包 | #10245 |\n" .
                      "| **太陽百匯平日午餐券** | CKC-TICKET-WD | **4** 張 | #10246 |\n" .
                      "| **黃金鮑魚土雞煲** | CKC-CHICKEN-02 | **1** 包 | #10247 |\n\n" .
                      "💡 **出貨提示**：牛肉爐與土雞煲為冷凍商品，請提前自冷凍庫備出；餐券為有價實體票券，請確認編號無誤後封信封裝寄。";
        } elseif ( stripos( $message, '宅配' ) !== false || stripos( $message, '名冊' ) !== false || stripos( $message, '物流' ) !== false ) {
            $reply .= "🚚 **今日待出貨「物流宅配名冊」**：\n\n" .
                      "| 訂單號 | 收件人 | 聯絡電話 | 配送地址 | 購買品項 |\n" .
                      "| :--- | :--- | :--- | :--- | :--- |\n" .
                      "| #10245 | 王小明 | 0912-345678 | 台中市南屯區公益路二段99號 | 潮港城一斤肉牛肉爐 x2 |\n" .
                      "| #10246 | 李美華 | 0928-111222 | 台北市大安區信義路四段100號 | 太陽百匯平日午餐券 x4 |\n" .
                      "| #10247 | 陳大同 | 0933-444555 | 台南市中西區民權路三段50號 | 黃金鮑魚土雞煲 x1 |\n\n" .
                      "💡 **提示**：可直接複製此表單匯入至物流系統後台進行大宗列印寄件單。";
        } elseif ( stripos( $message, '健康檢查' ) !== false || stripos( $message, '營運檢查' ) !== false ) {
            $reply .= "⚠️ **自動化每日營運檢查報告**：\n\n" .
                      "### 1. 訂單出貨時效稽核 (超過 24 小時未出貨)\n" .
                      "- 🔴 訂單 **#10240** (林阿美) - 已付款待出貨 **48** 小時\n" .
                      "- 🔴 訂單 **#10242** (張大千) - 已付款待出貨 **36** 小時\n\n" .
                      "### 2. 缺貨/斷貨商品監控 (庫存 <= 0)\n" .
                      "- ❌ **潮港城招牌蘿蔔糕** (SKU: CKC-CAKE-01) ➔ **目前庫存：0 件**\n" .
                      "- ❌ **冷凍熟凍小龍蝦** (SKU: CKC-CRAY-05) ➔ **目前庫存：-2 件** (負數異常)\n\n" .
                      "### 3. 營運優化建議\n" .
                      "1. 延遲的 2 筆訂單請優先安排揀貨出貨，避免引起延誤客訴。\n" .
                      "2. 招招牌蘿蔔糕已完全無庫存，請儘速通知廚房製作，或在系統後台調整狀態。\n" .
                      "3. 小龍蝦出現負庫存，請實體倉管人員進行複盤。";
        } elseif ( stripos( $message, '出貨' ) !== false || stripos( $message, '處理中' ) !== false ) {
            $reply .= "📋 **今日待出貨訂單明細**：\n" .
                      "1. 訂單 #10245 (王小明) - 潮港城一斤肉牛肉爐 x 2 ($1980) ➔ 待配貨\n" .
                      "2. 訂單 #10246 (李美華) - 太陽百匯平日午餐券 x 4 ($3520) ➔ 待備券\n" .
                      "3. 訂單 #10247 (陳大同) - 黃金鮑魚土雞煲 x 1 ($1280) ➔ 待配貨\n\n" .
                      "出貨人員提示：請優先打包牛肉爐與土雞煲，並確保冷凍保存箱工作正常。自取訂單請提前印出出貨單備查。";
        } elseif ( stripos( $message, '庫存' ) !== false || stripos( $message, '警報' ) !== false ) {
            $reply .= "⚠️ **低庫存警告商品列表 (低於 15 件)**：\n" .
                      "- **潮港城一斤肉牛肉爐** (SKU: CKC-BEEF-01) ➔ **僅剩 5 包**\n" .
                      "- **黃金鮑魚土雞煲** (SKU: CKC-CHICKEN-02) ➔ **僅剩 2 包**\n" .
                      "- **太陽百匯平日午餐券** (SKU: CKC-TICKET-WD) ➔ **僅剩 12 張**\n\n" .
                      "作業提示：牛肉爐與土雞煲庫存量均低於安全水位 (10包)，請盡快向廚房或採購單位提出補貨排程！";
        } elseif ( stripos( $message, '統計' ) !== false || stripos( $message, '概況' ) !== false ) {
            $reply .= "📈 **今日與本月電商銷售與出貨統計概況**：\n" .
                      "- **本月總銷售金額**：$284,500 元\n" .
                      "- **已付款總訂單數**：158 筆\n" .
                      "- **已出貨完成**：142 筆\n" .
                      "- **待出貨 (處理中)**：16 筆\n\n" .
                      "🔥 **本月熱銷排行榜**：\n" .
                      "1. 潮港城一斤肉牛肉爐（累計銷售 235 包）➔ 庫存緊張\n" .
                      "2. 太陽百匯平日午餐券（累計銷售 180 張）\n" .
                      "3. 黃金鮑魚土雞煲（累計銷售 98 包）";
        } elseif ( stripos( $message, '搜尋' ) !== false || stripos( $message, '收件人' ) !== false ) {
            $reply .= "👤 **搜尋收件人「王小明」的訂單記錄**：\n" .
                      "- 訂單 #10245 | 王小明 | 潮港城一斤肉牛肉爐 x 2 | 金額: $1980 | 狀態: 處理中 (已付款，待出貨)\n\n" .
                      "出貨人員提示：該客戶訂購冷凍商品，出貨時請黏貼冷凍標籤，並確認宅配單號已正確輸入。";
        } elseif ( preg_match( '/訂單\s*#?(\d+)/u', $message, $matches ) ) {
            $order_id = intval( $matches[1] );
            $reply .= "🔍 **訂單 #{$order_id} 詳細資訊**：\n" .
                      "- **狀態**：處理中 (Processing)\n" .
                      "- **收件人**：李美華 (電話: 0928-111222)\n" .
                      "- **收件地址**：台中市南屯區公益路二段99號\n" .
                      "- **購買商品**：太陽百匯平日午餐券 x 4 | 金額: $3520\n" .
                      "- **付款方式**：信用卡線上支付\n" .
                      "- **建立時間**：2026-07-11 10:14:32\n\n" .
                      "作業提示：該筆訂單為實體餐券，出貨時請雙重確認餐券編號並以掛號寄出。";
        } else {
            $reply = "哈囉！我是您的出貨AI助理 🤖 目前您尚未儲存 Gemini API 金鑰，系統正處於沙盒模擬測試狀態。\n\n您可以點擊左側「出貨與庫存作業」列表，模擬讀取待出貨訂單、低庫存警報，或是模擬更新訂單狀態！儲存您的 API 金鑰後，將可完全開通與真實 WooCommerce 資料庫的實時智慧串接！";
        }
        
        wp_send_json_success( array( 'reply' => $reply, 'is_mock' => true ) );
    }

    // --- 3b. 使用真實 Gemini API 連線發送 ---
    $full_prompt = $system_context;
    if ( ! empty( $db_context ) ) {
        $full_prompt .= "\n\n[系統即時讀取的資料庫內容]：\n" . $db_context;
    }
    if ( ! empty( $action_result ) ) {
        $full_prompt .= "\n\n[系統寫入動作執行紀錄]：\n" . $action_result;
    }
    $full_prompt .= "\n\n使用者提問：" . $message;

    $payload = array(
        'contents' => array(
            array(
                'parts' => array(
                    array( 'text' => $full_prompt )
                )
            )
        )
    );

    // 嘗試呼叫的端點與模型順序 (優先採用新世代穩定 v1 的 gemini-2.5-flash / gemini-2.0-flash / gemini-3.5-flash，並以 flash-latest / pro-latest / 1.5-flash 保底)
    $endpoints = array(
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-3.5-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-latest:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . urlencode( $api_key ),
    );

    $last_error = '';
    $success = false;
    $reply = '';

    foreach ( $endpoints as $api_url ) {
        $response = wp_remote_post( $api_url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            $last_error = '連線失敗：' . $response->get_error_message();
            continue;
        }

        $body_text = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body_text, true );

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $reply = $data['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break;
        } else {
            $last_error = isset( $data['error']['message'] ) ? $data['error']['message'] : '無法解析 API 回傳內容';
        }
    }

    if ( $success ) {
        wp_send_json_success( array( 'reply' => $reply, 'is_mock' => false ) );
    } else {
        wp_send_json_error( array( 'message' => 'API 錯誤：' . $last_error ) );
    }
}
