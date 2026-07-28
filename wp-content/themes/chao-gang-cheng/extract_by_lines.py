import os

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")

with open(functions_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

def extract_exact(start, end, path, require_stmt):
    global lines
    extracted = lines[start-1:end]
    with open(os.path.join(base_dir, path), "w", encoding="utf-8") as f:
        f.write("<?php\n")
        f.writelines(extracted)
    lines = lines[:start-1] + [require_stmt + "\n"] + lines[end:]
    print(f"Extracted {len(extracted)} lines into {path}")

# Extract bottom up!
# 5. checkout-ux
extract_exact(10282, 11088, "includes/woocommerce/checkout-ux.php", "require_once get_template_directory() . '/includes/woocommerce/checkout-ux.php';")

# 4. checkout-js
extract_exact(9458, 10269, "includes/woocommerce/checkout.php", "require_once get_template_directory() . '/includes/woocommerce/checkout.php';")

# 3. agent-actions
extract_exact(7178, 7878, "includes/api/agent-actions.php", "require_once get_template_directory() . '/includes/api/agent-actions.php';")

# 1. popup (5904 is higher than 4629)
extract_exact(5904, 6354, "includes/core/popup.php", "require_once get_template_directory() . '/includes/core/popup.php';")

# 2. shop-styles
extract_exact(4629, 5159, "includes/woocommerce/shop-styles.php", "require_once get_template_directory() . '/includes/woocommerce/shop-styles.php';")

with open(functions_path, "w", encoding="utf-8") as f:
    f.writelines(lines)

print("All extractions completed via exact line numbers.")
