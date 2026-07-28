import os

base_dir = "."
functions_path = os.path.join(base_dir, "functions.php")

with open(functions_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

start_idx = -1
for i, line in enumerate(lines):
    if "AJAX 呼叫 Gemini API 進行聊天對話" in line:
        start_idx = i - 1  # Include the /** line
        break

if start_idx != -1:
    brace_count = 0
    started = False
    end_idx = -1
    
    for i in range(start_idx, len(lines)):
        for char in lines[i]:
            if char == '{':
                brace_count += 1
                started = True
            elif char == '}':
                brace_count -= 1
                
        if started and brace_count == 0:
            end_idx = i
            break
            
    if end_idx != -1:
        extracted = lines[start_idx:end_idx+1]
        
        os.makedirs(os.path.join(base_dir, "includes/api"), exist_ok=True)
        with open(os.path.join(base_dir, "includes/api/agent-actions.php"), "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.writelines(extracted)
            
        new_lines = lines[:start_idx] + ["require_once get_template_directory() . '/includes/api/agent-actions.php';\n"] + lines[end_idx+1:]
        with open(functions_path, "w", encoding="utf-8") as f:
            f.writelines(new_lines)
            
        print(f"Extracted {len(extracted)} lines into includes/api/agent-actions.php")
    else:
        print("Could not find matching brace.")
else:
    print("Could not find start pattern.")
