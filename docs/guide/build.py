"""Builds docs/guide/ur/guide.html (the whole Urdu guide in one right-to-left page) and checks that every screenshot the chapters use exists.

Run: python3 docs/guide/build.py [screenshots-dir ...]   (needs the `markdown` package)
Screenshots are looked up in docs/guide/images first; pass extra directories (e.g. a browser crawl) to copy missing ones from.
"""
import glob
import os
import re
import shutil
import sys

import markdown

HERE = os.path.dirname(os.path.abspath(__file__))
CHAPTERS = sorted(glob.glob(os.path.join(HERE, "ur", "[0-9][0-9]-*.md")))
IMAGES = os.path.join(HERE, "images")
SOURCES = sys.argv[1:]

missing = []
for path in CHAPTERS + [os.path.join(HERE, "ur", "README.md")]:
    for name in re.findall(r"\]\(\.\./images/([^)]+)\)", open(path, encoding="utf-8").read()):
        target = os.path.join(IMAGES, name)
        if os.path.exists(target):
            continue
        for src in SOURCES:
            if os.path.exists(os.path.join(src, name)):
                shutil.copy(os.path.join(src, name), target)
                break
        else:
            missing.append((os.path.basename(path), name))

if missing:
    print("missing screenshots:")
    for chapter, name in missing:
        print(f"  {chapter}: {name}")
    sys.exit(1)

CSS = """
:root { color-scheme: light dark; --bg:#fff; --fg:#1b1f23; --muted:#5b6670; --line:#d9dee3; --accent:#0b5cad; --code:#f3f5f7; }
@media (prefers-color-scheme: dark) { :root { --bg:#14181c; --fg:#e6e9ec; --muted:#9aa5b0; --line:#2a3138; --accent:#6fb1ff; --code:#1d2329; } }
* { box-sizing: border-box; }
body { margin:0; background:var(--bg); color:var(--fg); font-family:"Noto Naskh Arabic","Noto Nastaliq Urdu","Jameel Noori Nastaleeq","Segoe UI",Tahoma,Arial,sans-serif; font-size:18px; line-height:2; }
main { max-width:900px; margin:0 auto; padding:16px 16px 64px; }
h1 { font-size:1.9em; line-height:1.5; border-bottom:2px solid var(--accent); padding-bottom:.3em; margin-top:2.2em; }
h2 { font-size:1.45em; line-height:1.6; margin-top:1.8em; } h3 { font-size:1.2em; }
a { color:var(--accent); }
img { max-width:100%; height:auto; border:1px solid var(--line); border-radius:8px; display:block; margin:1em auto; direction:ltr; }
table { border-collapse:collapse; width:100%; display:block; overflow-x:auto; font-size:.92em; line-height:1.7; }
th, td { border:1px solid var(--line); padding:.4em .7em; text-align:right; vertical-align:top; }
th { background:var(--code); }
code, pre { direction:ltr; unicode-bidi:isolate; font-family:Consolas,"SFMono-Regular",Menlo,monospace; font-size:.85em; background:var(--code); border-radius:4px; }
code { padding:.05em .35em; white-space:pre-wrap; word-break:break-word; }
pre { padding:.8em 1em; overflow-x:auto; text-align:left; line-height:1.5; } pre code { padding:0; background:none; white-space:pre; }
blockquote { margin:1em 0; padding:.2em 1em; border-right:4px solid var(--accent); background:var(--code); border-radius:6px; }
.toc a { display:block; padding:.1em 0; } hr { border:0; border-top:1px solid var(--line); margin:3em 0; }
@media print { img { break-inside:avoid; } body { font-size:12pt; } }
"""

md = markdown.Markdown(extensions=["tables", "fenced_code", "sane_lists", "toc"])
parts = []

for path in [os.path.join(HERE, "ur", "README.md")] + CHAPTERS:
    text = open(path, encoding="utf-8").read()
    text = re.sub(r'^\s*<div dir="rtl" align="right">\s*', "", text)
    text = re.sub(r"</div>\s*$", "", text)
    text = re.sub(r"\]\(\.\./images/", "](images/", text)
    text = re.sub(r"\]\((\d\d-[a-z-]+)\.md\)", lambda m: "](#" + m.group(1) + ")", text)
    text = re.sub(r"\]\(README\.md\)", "](#top)", text)
    text = text.replace("](guide.html)", "](#top)")
    text = re.sub(r"\]\(\.\./\.\./help-catalog\.md\)", "](../help-catalog.md)", text)
    text = re.sub(r"\]\(\.\./\.\./(openapi\.yaml|postman_collection\.json|uat\.md)\)", r"](../\1)", text)
    text = re.sub(r"\]\(\.\./\.\./\.\./README\.md\)", "](../../README.md)", text)
    md.reset()
    anchor = os.path.basename(path)[:-3]
    parts.append(f'<section id="{"top" if anchor == "README" else anchor}">\n{md.convert(text)}\n</section>')

html = f"""<!doctype html>
<html lang="ur" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>اکاؤنٹنگ سسٹم — اردو گائیڈ</title>
<style>{CSS}</style></head>
<body><main>
{chr(10).join(parts)}
</main></body></html>
"""
# the page lives in docs/guide/ur/: images are one level up
html = html.replace('src="images/', 'src="../images/').replace('(images/', '(../images/')
open(os.path.join(HERE, "ur", "guide.html"), "w", encoding="utf-8").write(html)
print(f"{len(CHAPTERS)} chapters, {len(os.listdir(IMAGES))} images, guide.html {os.path.getsize(os.path.join(HERE, 'ur', 'guide.html')) // 1024} KB")
