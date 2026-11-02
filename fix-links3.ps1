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
        # Fix broken pattern with simple string replace
        $broken = '<link rel="stylesheet" <link rel="stylesheet" href="' + $cssFileName + '"/>'
        $fixed = '<link rel="stylesheet" href="' + $cssFileName + '"/>'
        if ($content -contains $broken) {
            $content = $content.Replace($broken, $fixed)
            Write-Host "Fixed broken link: $name"
        }
        # Remove standalone style.css link if page css is present
        $styleLink = '<link rel="stylesheet" href="style.css'
        if ($content -contains $styleLink) {
            $content = $content -replace [regex]::Escape($styleLink) + '[^"]*"', ''
            Write-Host "Removed style.css: $name"
        }
        Set-Content -Path $htmlFile.FullName -Value $content -Encoding UTF8
    }
}
Write-Host 'Done!'