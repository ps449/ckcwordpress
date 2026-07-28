def find_matching_brace(filename, start_line_0_indexed):
    with open(filename, 'r', encoding='utf-8') as f:
        lines = f.readlines()
        
    brace_count = 0
    started = False
    
    for i in range(start_line_0_indexed, len(lines)):
        line = lines[i]
        for char in line:
            if char == '{':
                brace_count += 1
                started = True
            elif char == '}':
                brace_count -= 1
                
        if started and brace_count == 0:
            return i + 1  # 1-indexed line number
            
    return -1

print("Agent Function ends at:", find_matching_brace("functions.php", 7181))
