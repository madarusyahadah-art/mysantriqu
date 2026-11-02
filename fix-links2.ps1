$base = 'C:\xampp\htdocs\MySantriQu'
$cssMap = @{
    'absensi.html'='absensi.css';
    'absensi-input.html'='absensi-input.css';
    'admin-users.html'='admin-users.css';
    'asatidz.html'='asatidz.css';
    'dashboard.html'='dashboard.css';
    'input.html'='input.css';
    'kalender-akademik.html'='kalender-akademik.css';
    'kegiatan.html'='kegiatan.css';
    'laporan.html'='laporan.css';
    'laporan-kegiatan.html'='laporan-kegiatan.css';
    'pelanggaran.html'='pelanggaran.css';
    'pemantauan.html'='pemantauan.css';
    'pilih-role.html'='pilih-role.css';
    'profil-santri.html'='profil-santri.css';
    'santri.html'='santri.css'
}
foreach ($htmlFile in Get-ChildItem $base -Filter *.html) {
    $name = $htmlFile.Name
    if ($cssMap.ContainsKey($name)) {
        $content = Get-Content $htmlFile.FullName -Raw
        $cssFileName = $cssMap[$name]
        # Fix broken pattern: <link rel="stylesheet" <link rel="stylesheet" href="x.css"/>
        $content = $content -replace '<link rel=""stylesheet"" <link rel=""stylesheet"" href="([^"]+)"/>', '<link rel="stylesheet" href="$1"/>'
        # Remove any orphaned style.css link if page css exists (keep page css)
        if ($content -match 'href="style.css') {
            $content = $content -replace '<link rel=""stylesheet"" href="style.css[^"]*"', ''
        }
        Set-Content -Path $htmlFile.FullName -Value $content -Encoding UTF8
        Write-Host "Fixed: $name"
    }
}
Write-Host 'Done!'