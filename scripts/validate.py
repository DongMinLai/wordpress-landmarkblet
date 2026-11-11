"""检查本仓库的代码发布范围。"""
import hashlib
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAYLOAD = ROOT / "wp-content"
ALLOWED_ROOTS = (
    PAYLOAD / "themes" / "landmarkus-pilot-child",
    PAYLOAD / "plugins" / "landmarkus-core",
)
FORBIDDEN_NAMES = {
    "wp-config.php", ".env", "local-development.php",
    "local-test-gateway.php", "preview-router.php", "id_rsa", "id_ed25519",
}
LOCAL_URL = re.compile(r"https?://(?:local-landmark[.]local|localhost|127[.]0[.]0[.]1)(?=[:/]|$)", re.I)
PRIVATE_KEY = re.compile(r"-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----")
DB_SECRET = re.compile(r"define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,", re.I)

def validate():
    actual = {}
    failures = []
    for directory in ALLOWED_ROOTS:
        if not directory.is_dir():
            failures.append(f"发布目录缺失 {directory.relative_to(ROOT)}")
    for file in sorted(PAYLOAD.rglob("*")):
        if file.is_symlink():
            failures.append(f"符号链接 {file.relative_to(ROOT)}")
            continue
        if not file.is_file():
            continue
        name = file.relative_to(ROOT).as_posix()
        if not any(file.is_relative_to(directory) for directory in ALLOWED_ROOTS):
            failures.append(f"发布范围外文件 {name}")
        if file.name in FORBIDDEN_NAMES or file.suffix.lower() in {".sql", ".pem", ".key", ".log", ".bak"}:
            failures.append(f"禁止发布文件 {name}")
        data = file.read_bytes()
        actual[name] = hashlib.sha256(data).hexdigest()
        if file.suffix.lower() in {".php", ".js", ".css", ".html", ".json", ".md", ".txt"}:
            content = data.decode("utf-8-sig")
            if LOCAL_URL.search(content):
                failures.append(f"本机地址 {name}")
            if PRIVATE_KEY.search(content) or DB_SECRET.search(content):
                failures.append(f"敏感配置 {name}")
    theme = (ALLOWED_ROOTS[0] / "style.css").read_text(encoding="utf-8-sig")
    if not re.search(r"^Template:\s*blocksy\s*$", theme, re.M):
        failures.append("子主题缺少 Blocksy 父主题声明")
    plugin_files = {"landmarkus-core.php", "wholesale-order.php", "smart-search.php", "contact-inquiry.php"}
    if {file.name for file in ALLOWED_ROOTS[1].iterdir() if file.is_file()} != plugin_files:
        failures.append("Core 插件文件集合不符合发布清单")
    if failures:
        raise SystemExit("\n".join(failures))
    print(f"PASS 发布范围，共 {len(actual)} 个文件")
    return actual

if __name__ == "__main__":
    validate()
