import os
import re

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")
includes_dir = os.path.join(base_dir, "includes")

with open(functions_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

def extract_block_by_pattern(start_pattern, end_pattern, output_path, require_stmt, is_regex=False):
    global lines
    start_idx = -1
    end_idx = -1
    
    for i, line in enumerate(lines):
        if is_regex:
            if re.search(start_pattern, line):
                start_idx = i
                break
        else:
            if start_pattern in line:
                start_idx = i
                break
                
    if start_idx == -1:
        print(f"Start pattern not found for {output_path}")
        return False
        
    for i in range(start_idx, len(lines)):
        if is_regex:
            if re.search(end_pattern, lines[i]):
                end_idx = i
                break
        else:
            if end_pattern in lines[i]:
                end_idx = i
                break
                
    if end_idx == -1:
        print(f"End pattern not found for {output_path}")
        return False
        
    # include the end line, and if the end line is just a function declaration, find its closing brace
    # Actually, for simplicity, if end_pattern is a function end, we just use end_idx + 1
    
    extracted = lines[start_idx:end_idx+1]
    
    with open(os.path.join(base_dir, output_path), "w", encoding="utf-8") as f:
        f.write("<?php\n")
        f.writelines(extracted)
        
    lines = lines[:start_idx] + [require_stmt + "\n"] + lines[end_idx+1:]
    print(f"Extracted {len(extracted)} lines into {output_path}")
    return True

# 1. Agent Actions
extract_block_by_pattern(
    "AJAX 呼叫 Gemini API 進行聊天對話",
    "add_action( 'wp_ajax_ckc_gemini_chat', 'ckc_ajax_gemini_chat' );",
    "includes/api/agent-actions.php",
    "require_once get_template_directory() . '/includes/api/agent-actions.php';"
)

# 2. Popup
# The popup ends with the function ckc_popup_render and its closing brace.
# Let's find the closing brace by line matching.
def extract_popup():
    global lines
    start = -1
    for i, l in enumerate(lines):
        if "全站彈窗廣告系統" in l:
            start = i - 1 # to include the /**
            break
    if start == -1: return
    
    end = -1
    for i in range(start, len(lines)):
        if "add_action( 'wp_footer', 'ckc_popup_render' );" in lines[i]:
            # The closing brace should be a few lines below
            for j in range(i, i+50):
                if lines[j].strip() == "}":
                    end = j
                    break
            break
    
    if end != -1:
        extracted = lines[start:end+1]
        with open(os.path.join(base_dir, "includes/core/popup.php"), "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.writelines(extracted)
        lines = lines[:start] + ["require_once get_template_directory() . '/includes/core/popup.php';\n"] + lines[end+1:]
        print(f"Extracted popup ({len(extracted)} lines)")

extract_popup()

# 3. Shop Styles
def extract_shop_styles():
    global lines
    start = -1
    for i, l in enumerate(lines):
        if "21. Inject shop layout styles inline" in l:
            start = i - 1
            break
    if start == -1: return
    
    end = -1
    for i in range(start, len(lines)):
        if "add_action( 'wp_head', 'chao_gang_cheng_inline_shop_styles', 100 );" in lines[i]:
            for j in range(i, i+50):
                if lines[j].strip() == "}":
                    end = j
                    break
            break
            
    if end != -1:
        extracted = lines[start:end+1]
        with open(os.path.join(base_dir, "includes/woocommerce/shop-styles.php"), "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.writelines(extracted)
        lines = lines[:start] + ["require_once get_template_directory() . '/includes/woocommerce/shop-styles.php';\n"] + lines[end+1:]
        print(f"Extracted shop-styles ({len(extracted)} lines)")

extract_shop_styles()

# 4. Checkout JS
def extract_checkout_js():
    global lines
    start = -1
    for i, l in enumerate(lines):
        if "add_action( 'wp_footer', 'chao_checkout_custom_js_css' );" in l:
            start = i
            break
    if start == -1: return
    
    end = -1
    for i in range(start, len(lines)):
        if "</script>" in lines[i] and "chao_checkout_custom_js_css" in "".join(lines[start:i]):
            for j in range(i, i+5):
                if lines[j].strip() == "}":
                    end = j
                    break
            break
            
    if end != -1:
        extracted = lines[start:end+1]
        with open(os.path.join(base_dir, "includes/woocommerce/checkout.php"), "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.writelines(extracted)
        lines = lines[:start] + ["require_once get_template_directory() . '/includes/woocommerce/checkout.php';\n"] + lines[end+1:]
        print(f"Extracted checkout js ({len(extracted)} lines)")

extract_checkout_js()

# 5. Checkout UX Backend Hooks
def extract_checkout_ux():
    global lines
    start = -1
    for i, l in enumerate(lines):
        if "CHECKOUT UX OPTIMIZATION BACKEND HOOKS" in l:
            start = i - 1
            break
    if start == -1: return
    
    end = -1
    for i in range(start, len(lines)):
        if "add_action( 'wp_footer', 'chao_cart_ux_footer_assets' );" in lines[i]:
            for j in range(i, i+200): # large search space since it's a big JS block
                if "</script>" in lines[j]:
                    for k in range(j, j+10):
                        if lines[k].strip() == "}":
                            end = k
                            break
                    if end != -1: break
            break
            
    if end != -1:
        extracted = lines[start:end+1]
        with open(os.path.join(base_dir, "includes/woocommerce/checkout-ux.php"), "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.writelines(extracted)
        lines = lines[:start] + ["require_once get_template_directory() . '/includes/woocommerce/checkout-ux.php';\n"] + lines[end+1:]
        print(f"Extracted checkout ux ({len(extracted)} lines)")

extract_checkout_ux()

with open(functions_path, "w", encoding="utf-8") as f:
    f.writelines(lines)
    
print("All extraction complete.")
