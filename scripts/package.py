"""从已验证的代码清单生成部署 ZIP。"""
import hashlib
import json
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo
from validate import ROOT, validate

files = validate()
output = ROOT / "dist"
output.mkdir(exist_ok=True)
(output / "release-manifest.json").write_text(json.dumps({"files": files}, indent=2) + "\n", encoding="utf-8")
archive = output / "landmarkus-wordpress-code.zip"
with ZipFile(archive, "w", compression=ZIP_DEFLATED) as package:
    for name in sorted(files):
        info = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        package.writestr(info, (ROOT / name).read_bytes())
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(output / "SHA256SUMS").write_text(f"{digest}  {archive.name}\n", encoding="utf-8")
print(f"PASS 已生成 {archive.name}")
