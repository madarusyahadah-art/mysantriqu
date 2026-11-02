@echo off
cd /d C:\xampp\htdocs\MySantriQu
for %%f in (absensi-input.html absensi.html admin-users.html asatidz.html input.html kegiatan.html laporan.html pelanggaran.html profil-santri.html santri.html) do (
    echo Processing %%f
    powershell -Command "Get-Content '%%f' -Raw | ForEach-Object { \$_.Replace('^&lt;link rel=&quot;stylesheet&quot; &lt;link rel=&quot;stylesheet&quot; href=&quot;%%f.css&quot;&gt;', '&lt;link rel=&quot;stylesheet&quot; href=&quot;%%f.css&quot;&gt;') } | Set-Content '%%f' -Encoding UTF8"
)
echo Done