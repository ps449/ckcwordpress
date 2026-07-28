import re

with open("functions.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

def find_blocks(lines):
    blocks = []
    current_block = []
    in_comment = False
    for i, line in enumerate(lines):
        if line.strip().startswith("// ====") or line.strip().startswith("/* ---"):
            if current_block:
                blocks.append((start_idx, current_block))
            current_block = [line]
            start_idx = i
        else:
            current_block.append(line)
            if i == 0:
                start_idx = 0
    if current_block:
        blocks.append((start_idx, current_block))
    return blocks

blocks = find_blocks(lines)
print(f"Found {len(blocks)} blocks.")
for start, lines in blocks[:20]:
    header = lines[0].strip()[:50]
    func_count = len([l for l in lines if re.match(r"\s*function\s+[a-zA-Z0-9_]+", l)])
    print(f"Block at line {start+1}: {header} ({len(lines)} lines, {func_count} functions)")
