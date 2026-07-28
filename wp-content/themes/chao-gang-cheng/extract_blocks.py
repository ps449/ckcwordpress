import os

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")
includes_dir = os.path.join(base_dir, "includes")

os.makedirs(os.path.join(includes_dir, "core"), exist_ok=True)
os.makedirs(os.path.join(includes_dir, "woocommerce"), exist_ok=True)

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
            
        new_lines = lines[:start_idx] + [replacement_require + "\n"] + lines[end_idx:]
        print(f"Extracted {len(extracted)} lines into {output_rel_path}")
        return new_lines
    else:
        print(f"Could not find boundaries for {output_rel_path}: {start_idx}, {end_idx}")
        return lines

# 1. Product Loop
lines = extract_block(
    lines,
    "/* --- Product Loop Cards Hover Overlay & Option Buttons --- */",
    "// 後台插件選單重新整理",
    "includes/woocommerce/product-loop.php",
    "require_once get_template_directory() . '/includes/woocommerce/product-loop.php';"
)

# 2. Popup
lines = extract_block(
    lines,
    "// 全站彈窗廣告系統",
    "require_once get_template_directory() . '/includes/api/agent-actions.php';",
    "includes/core/popup.php",
    "require_once get_template_directory() . '/includes/core/popup.php';"
)

# 3. Checkout UI Helper
lines = extract_block(
    lines,
    "// --- CHECKOUT UI HELPER FUNCTIONS ---",
    "// Load custom LINE Login module",
    "includes/woocommerce/checkout.php",
    "require_once get_template_directory() . '/includes/woocommerce/checkout.php';"
)

with open(functions_path, "w", encoding="utf-8") as f:
    f.writelines(lines)
    
print("Extraction complete.")
