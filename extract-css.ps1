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
        if ($content -match '(?s)<style>(.*?)</style>') {
            $cssContent = $Matches[1]
            $cssFileName = $cssMap[$name]
            Set-Content -Path (Join-Path $base $cssFileName) -Value $cssContent -Encoding UTF8
            $content = $content -replace '(?s)<style>.*?</style>', ''
            if ($content -match 'href="style.css') {
                $content = $content -replace '(href="style.css[^"]*")', "<link rel=""stylesheet"" href=""$cssFileName""/>`n    $1"
            } else {
                $content = $content -replace '(</head>)', "<link rel=""stylesheet"" href=""$cssFileName""/>`n$1"
            }
            Set-Content -Path $htmlFile.FullName -Value $content -Encoding UTF8
            Write-Host "Updated: $name -> $cssFileName"
        } else {
            Write-Host "No inline CSS: $name"
        }
    }
}
Write-Host 'Done!'