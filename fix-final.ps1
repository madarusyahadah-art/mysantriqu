$base = 'C:\xampp\htdocs\MySantriQu'
$files = @('absensi-input.html','absensi.html','admin-users.html','asatidz.html','input.html','kegiatan.html','laporan.html','pelanggaran.html','profil-santri.html','santri.html')
foreach ($f in $files) {
    $path = Join-Path $base $f
    $content = Get-Content $path -Raw
    # Remove any leftover '>' on its own line after a broken link
    $content = $content -replace "`n    >", ""
    # Fix broken double-link pattern by finding anything between <link rel="stylesheet" and />
    $content = $content -replace '<link rel="stylesheet" <link rel="stylesheet" href="([^"]+)"/>', '<link rel="stylesheet" href="$1"/>'
    # Also try with single quotes escaped differently
    $content = $content -replace "&lt;link rel=&quot;stylesheet&quot; &lt;link rel=&quot;stylesheet&quot; href=&quot;([^&]+)&quot;&gt;", "&lt;link rel=&quot;stylesheet&quot; href=&quot;$1&quot;&gt;"
    Set-Content -Path $path -Value $content -Encoding UTF8
    Write-Host "Processed: $f"
}
Write-Host 'Done!'