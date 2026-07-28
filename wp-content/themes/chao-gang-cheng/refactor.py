import os
import re

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")
includes_dir = os.path.join(base_dir, "includes")

for subdir in ["core", "woocommerce", "api", "shortcodes", "admin"]:
    os.makedirs(os.path.join(includes_dir, subdir), exist_ok=True)

with open(functions_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

def extract_block(lines, start_str, end_str, output_rel_path, replacement_require):
    start_idx = -1
    end_idx = -1
    for i, line in enumerate(lines):
        if start_str in line and start_idx == -1:
            start_idx = i
        if end_str in line and start_idx != -1 and end_idx == -1:
            end_idx = i
            break
            
    if start_idx != -1 and end_idx != -1:
        extracted = lines[start_idx:end_idx]
        with open(os.path.join(base_dir, output_rel_path), "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.writelines(extracted)
        
        # Replace the block in memory with the require statement
        new_lines = lines[:start_idx] + [replacement_require + "\n"] + lines[end_idx:]
        print(f"Extracted {len(extracted)} lines into {output_rel_path}")
        return new_lines
    else:
        print(f"Could not find block for {output_rel_path}")
        return lines

# 1. Product Loop Cards (4951 - 5648)
lines = extract_block(
    lines, 
    "/* --- Product Loop Cards Hover Overlay & Option Buttons --- */", 
    "// =============================================================================", 
    "includes/woocommerce/product-loop.php", 
    "require_once get_template_directory() . '/includes/woocommerce/product-loop.php';"
)

# 2. Popup Ads (5925 - 7219)
lines = extract_block(
    lines,
    "// 全站彈窗廣告系統",
    "// --- 1a. 解析並執行訂單狀態寫入操作 (Agent Action: Update Order Status) ---",
    "includes/core/popup.php",
    "require_once get_template_directory() . '/includes/core/popup.php';"
)

# 3. Agent Actions & Checkout bindings (7219 - 10306)
lines = extract_block(
    lines,
    "// --- 1a. 解析並執行訂單狀態寫入操作 (Agent Action: Update Order Status) ---",
    "// Load custom LINE Login module",
    "includes/api/agent-actions.php",
    "require_once get_template_directory() . '/includes/api/agent-actions.php';"
)

with open(functions_path, "w", encoding="utf-8") as f:
    f.writelines(lines)
    
print("Refactoring complete.")
