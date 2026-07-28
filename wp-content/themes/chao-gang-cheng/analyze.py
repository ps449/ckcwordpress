import re

with open("functions.php", "r", encoding="utf-8") as f:
    content = f.read()

funcs = re.findall(r"function\s+([a-zA-Z0-9_]+)\s*\(", content)
print(f"Total functions: {len(funcs)}")

prefixes = {}
for func in funcs:
    prefix = func.split("_")[0]
    if len(prefix) > 2:
        prefixes[prefix] = prefixes.get(prefix, 0) + 1

print("Top function prefixes:")
for p, count in sorted(prefixes.items(), key=lambda x: x[1], reverse=True)[:10]:
    print(f"  {p}: {count}")
