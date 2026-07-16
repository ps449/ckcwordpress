with open('wp-content/themes/chao-gang-cheng/style.css', 'r', encoding='utf-8') as f:
    lines = f.readlines()

stack = []

for idx, line in enumerate(lines):
    line_num = idx + 1
    
    # Strip comments to avoid matching brackets inside them
    clean_line = line
    # Simple regex-less comment stripping for inline comments
    if '/*' in clean_line and '*/' in clean_line:
        # remove everything between /* and */
        parts = clean_line.split('/*')
        clean_parts = [parts[0]]
        for p in parts[1:]:
            if '*/' in p:
                clean_parts.append(p.split('*/')[1])
        clean_line = "".join(clean_parts)
        
    for char in clean_line:
        if char == '{':
            stack.append((line_num, line.strip()))
        elif char == '}':
            if stack:
                stack.pop()
            else:
                print(f"Excess closing bracket at line {line_num}!")

print("\n--- Remaining Unclosed Brackets in Stack ---")
for num, content in stack:
    print(f"Line {num}: {content}")
