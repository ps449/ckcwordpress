import os

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")
includes_dir = os.path.join(base_dir, "includes")

os.makedirs(os.path.join(includes_dir, "core"), exist_ok=True)
os.makedirs(os.path.join(includes_dir, "woocommerce"), exist_ok=True)

with open(functions_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

def extract_block(lines, start_line, end_line, output_rel_path, replacement_require):
    # Convert to 0-indexed
    start_idx = start_line - 1
    end_idx = end_line
    
    extracted = lines[start_idx:end_idx]
    with open(os.path.join(base_dir, output_rel_path), "w", encoding="utf-8") as f:
        f.write("<?php\n")
        f.writelines(extracted)
        
    new_lines = lines[:start_idx] + [replacement_require + "\n"] + lines[end_idx:]
    print(f"Extracted {len(extracted)} lines into {output_rel_path}")
    return new_lines

# IMPORTANT: Start from the bottom so indices don't shift!
# 1. Popup (5904 - 6354)
lines = extract_block(
    lines, 5904, 6354, 
    "includes/core/popup.php", 
    "require_once get_template_directory() . '/includes/core/popup.php';"
)

# 2. Shop Styles (4629 - 5159)
lines = extract_block(
    lines, 4629, 5159, 
    "includes/woocommerce/shop-styles.php", 
    "require_once get_template_directory() . '/includes/woocommerce/shop-styles.php';"
)

with open(functions_path, "w", encoding="utf-8") as f:
    f.writelines(lines)
    
print("Extraction complete.")
