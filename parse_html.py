# -*- coding: utf-8 -*-
"""Parse synology.html and extract NAS model rows into a JSON seed file."""
import re, json, sys, io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

SRC = r"C:\scode\synology.html"
OUT = r"C:\scode\synology_app\data\seed.json"

with open(SRC, 'r', encoding='utf-8') as f:
    html = f.read()

# Locate tbody block
tbody_start = html.find('<tbody>')
tbody_end = html.find('</tbody>')
tbody = html[tbody_start:tbody_end]

# Split into <tr ...> ... </tr> chunks
row_re = re.compile(r"<tr\s+class='text-c'[^>]*>(.*?)</tr>", re.DOTALL)
td_re = re.compile(r"<td[^>]*>(.*?)</td>", re.DOTALL)
ver_re = re.compile(r"version=([0-9.]+)#packages\">([0-9.]+)套件")

rows = []
for m in row_re.finditer(tbody):
    chunk = m.group(1)
    tds = td_re.findall(chunk)
    if len(tds) < 3:
        continue
    arch = re.sub(r'\s+', '', tds[0])
    sub_arch = re.sub(r'\s+', '', tds[1])
    model = re.sub(r'\s+', '', tds[2])
    # versions appear inside the 4th td
    versions = sorted(set(ver_re.findall(chunk)))
    # versions is list of (ver, label); keep unique version strings
    ver_list = sorted({v[0] for v in versions}, key=lambda x: [int(p) for p in x.split('.')])
    if not model:
        continue
    rows.append({
        "arch": arch,
        "sub_arch": sub_arch,
        "model": model,
        "versions": ver_list,
    })

with open(OUT, 'w', encoding='utf-8') as f:
    json.dump(rows, f, ensure_ascii=False, indent=2)

print(f"Extracted {len(rows)} models -> {OUT}")
# Sample
for r in rows[:3]:
    print(r)
print("...")
for r in rows[-3:]:
    print(r)
