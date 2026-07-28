import os
import re

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")

with open(functions_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "AJAX 呼叫 Gemini API 進行聊天對話" in l:
        print(f"Agent starts at {i}")
    if "add_action( 'wp_ajax_ckc_gemini_chat', 'ckc_ajax_gemini_chat' );" in l:
        print(f"Agent action at {i}")
        
    if "全站彈窗廣告系統" in l:
        print(f"Popup starts at {i}")
    if "add_action( 'wp_footer', 'ckc_popup_render' );" in l:
        print(f"Popup action at {i}")
        
    if "13. WooCommerce Shop Layout" in l:
        print(f"Shop Layout starts at {i}")
    if "add_action( 'wp_head', 'chao_gang_cheng_shop_custom_styles' );" in l:
        print(f"Shop action at {i}")
