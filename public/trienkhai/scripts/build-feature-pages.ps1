$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Web

$base = "c:\Users\Administrator\Documents\GitHub\eralms\public\trienkhai"
$imagesDir = Join-Path $base "assets\images\flows"
New-Item -ItemType Directory -Force -Path $imagesDir | Out-Null

function Convert-ToSlug {
    param([string]$value)

    $normalized = $value.Normalize([Text.NormalizationForm]::FormD)
    $builder = New-Object -TypeName System.Text.StringBuilder

    foreach ($ch in $normalized.ToCharArray()) {
        $cat = [System.Globalization.CharUnicodeInfo]::GetUnicodeCategory($ch)
        if ($cat -ne [System.Globalization.UnicodeCategory]::NonSpacingMark) {
            $null = $builder.Append($ch)
        }
    }

    $ascii = $builder.ToString().Normalize([Text.NormalizationForm]::FormC).ToLower()
    $ascii = $ascii -replace '[^\w\s-]', ''
    $ascii = $ascii -replace '\s+', '-'
    $ascii = $ascii.Replace('đ', 'd').Replace('Đ', 'D')
    return $ascii
}

function Escape-Html {
    param([string]$text)
    if ([string]::IsNullOrWhiteSpace($text)) { return "" }
    $text.Replace('&', '&amp;').Replace('<', '&lt;').Replace('>', '&gt;').Replace('"', '&quot;')
}

function Strip-Html {
    param([string]$text)
    if ([string]::IsNullOrWhiteSpace($text)) { return "" }
    ($text -replace '<[^>]+>', '').Trim()
}

function Render-Nav {
    param([string]$active)

    $items = @(
        @{ href = "/trienkhai"; label = "Home"; key = "home" },
        @{ href = "/trienkhai/dao-tao.html"; label = "Đào tạo"; key = "dao-tao" },
        @{ href = "/trienkhai/danh-gia.html"; label = "Đánh giá"; key = "danh-gia" },
        @{ href = "/trienkhai/nguoi-hoc.html"; label = "Người học"; key = "nguoi-hoc" },
        @{ href = "/trienkhai/chuan-obe.html"; label = "Chuẩn & OBE"; key = "chuan-obe" },
        @{ href = "/trienkhai/tich-hop.html"; label = "Tích hợp"; key = "tich-hop" },
        @{ href = "/trienkhai/ai-analytics.html"; label = "AI & Analytics"; key = "ai-analytics" },
        @{ href = "/trienkhai/tat-ca-chuc-nang.html"; label = "Tất cả chức năng"; key = "tat-ca" },
        @{ href = "/trienkhai/lo-trinh.html"; label = "Lộ trình"; key = "lo-trinh" }
    )

    $links = foreach ($item in $items) {
        $class = if ($item.key -eq $active) { " class=`"active`"" } else { "" }
        "      <a$($class) href=`"$($item.href)`">$($item.label)</a>"
    }

    return @"
  <header class="topbar">
    <nav class="nav">
      <a class="brand" href="/trienkhai"><span class="brand-mark">E</span><span>EraLMS triển khai</span></a>
      <div class="nav-links">
$($links -join "`n")
      </div>
    </nav>
  </header>
"@
}

function New-FlowSvg {
    param(
        [string]$title,
        [string[]]$steps,
        [string]$filePath
    )

    $boxW = 205
    $boxH = 84
    $gap = 32
    $padX = 44
    $padY = 70
    $count = $steps.Count
    $width = 120 + ($count * $boxW) + (($count - 1) * $gap)
    $height = 210

    $lines = @()
    for ($i = 0; $i -lt $count; $i++) {
        $x = $padX + ($i * ($boxW + $gap))
        $line1 = $steps[$i]
        if ($line1.Length -gt 35) {
            $line1 = $line1.Substring(0, 33) + "…"
        }
        $y = $padY

        $lines += "  <g>"
        $lines += "    <rect x=`"$x`" y=`"$y`" width=`"$boxW`" height=`"$boxH`" rx=`"12`" fill=`"#fff`" stroke=`"#d9e0ea`"/>"
        $lines += "    <rect x=`"$x`" y=`"$y`" width=`"$boxW`" height=`"24`" rx=`"12`" fill=`"#e7f5f7`" stroke=`"#d9e0ea`"/>"
        $lines += "    <text x=`"$(($x + $boxW / 2))`" y=`"$($y + 16)`" text-anchor=`"middle`" fill=`"#075766`" font-size=`"11`">$($i + 1)</text>"
        $lines += "    <text x=`"$(($x + $boxW / 2))`" y=`"$($y + 52)`" text-anchor=`"middle`" fill=`"#172033`" font-size=`"12`">$([System.Web.HttpUtility]::HtmlEncode($line1))</text>"
        $lines += "  </g>"

        if ($i -lt $count - 1) {
            $x1 = $x + $boxW
            $x2 = $x + $boxW + $gap
            $mid = $y + 42
            $lines += "  <line x1=`"$x1`" y1=`"$mid`" x2=`"$x2`" y2=`"$mid`" stroke=`"#007f8f`" stroke-width=`"2.3`" marker-end=`"url(#arrow)`" />"
        }
    }

    $content = @"
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $width $height" preserveAspectRatio="xMidYMid meet">
  <defs>
    <marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto" markerUnits="strokeWidth">
      <path d="M0,0 L8,4 L0,8" fill="#007f8f" />
    </marker>
  </defs>
  <rect width="$width" height="$height" fill="#f9fbfd" />
  <rect x="16" y="16" width="$($width - 32)" height="$($height - 32)" fill="#fff" stroke="#e0e6f0" rx="12"/>
  <text x="$([int]($width / 2))" y="44" text-anchor="middle" fill="#172033" font-size="16" font-weight="700">$([System.Web.HttpUtility]::HtmlEncode($title))</text>
  <g font-family="Arial,Helvetica,sans-serif">
$($lines -join "`n")
  </g>
</svg>
"@

    Set-Content -Path $filePath -Value $content -Encoding UTF8
}

function Build-FeatureHtml {
    param(
        [string]$groupSlug,
        [string]$groupLabel,
        [string[]]$featureNames,
        [string[]]$featureDescs,
        [string[]]$featureFlows,
        [string]$navKey,
        [string]$groupIntro,
        [string]$screens
    )

    $featureCards = @()
    for ($i = 0; $i -lt $featureNames.Count; $i++) {
        $slug = Convert-ToSlug $featureNames[$i]
        $desc = $featureDescs[$i]
        $steps = ($featureFlows[$i] -split '\s*;\s*') | ForEach-Object { (Strip-Html $_).Trim() }
        if ($steps.Count -lt 4) {
            $steps = @(
                "Nhập dữ liệu nghiệp vụ",
                "Thực hiện luồng xử lý chính",
                "Kiểm tra trạng thái theo quy định",
                "Xuất kết quả nghiệm thu"
            )
        }

        $flowPath = Join-Path $imagesDir "$groupSlug-$slug.svg"
        New-FlowSvg -title $featureNames[$i] -steps $steps -filePath $flowPath

        $featureCards += "<a class=`"card feature-link`" href=`"/trienkhai/$groupSlug/$slug.html`"><h3>$([System.Web.HttpUtility]::HtmlEncode($featureNames[$i]))</h3><p>$([System.Web.HttpUtility]::HtmlEncode($desc))</p></a>"

        $pageHtml = @"
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>$([System.Web.HttpUtility]::HtmlEncode($featureNames[$i])) - $([System.Web.HttpUtility]::HtmlEncode($groupLabel))</title>
  <link rel="stylesheet" href="/trienkhai/assets/site.css">
</head>
<body>
$(Render-Nav -active $navKey)
  <main>
    <section class="page-title">
      <div class="section">
        <p class="eyebrow">$([System.Web.HttpUtility]::HtmlEncode($groupLabel))</p>
        <h1>$([System.Web.HttpUtility]::HtmlEncode($featureNames[$i]))</h1>
        <p>$([System.Web.HttpUtility]::HtmlEncode($desc))</p>
      </div>
    </section>

    <section class="section">
      <h2>Mục tiêu nghiệp vụ</h2>
      <p class="lead">Áp dụng chức năng <strong>$([System.Web.HttpUtility]::HtmlEncode($featureNames[$i]))</strong> cho luồng nghiệp vụ của phân hệ <strong>$([System.Web.HttpUtility]::HtmlEncode($groupLabel))</strong>.</p>
      <div class="grid two">
        <article class="card">
          <h3>Đầu vào và đầu ra</h3>
          <p><strong>Input:</strong> Dữ liệu nghiệp vụ theo phân hệ, quyền thao tác của vai trò tạo luồng.</p>
          <p><strong>Output:</strong> Nhật ký hoạt động, kết quả nghiệp vụ và trạng thái nghiệm thu.</p>
        </article>
        <article class="card">
          <h3>Màn hình liên quan</h3>
          <p>$([System.Web.HttpUtility]::HtmlEncode($screens))</p>
        </article>
      </div>
    </section>

    <section class="section">
      <p class="eyebrow">Luồng quy trình</p>
      <h2>Minh hoạ flow triển khai</h2>
      <figure class="flow-figure">
        <img src="/trienkhai/assets/images/flows/$groupSlug-$slug.svg" alt="Flow $([System.Web.HttpUtility]::HtmlEncode($featureNames[$i]))">
        <figcaption>Luồng tham chiếu cho chức năng $([System.Web.HttpUtility]::HtmlEncode($featureNames[$i]))</figcaption>
      </figure>
      <ol class="step-list">
        <li><strong>Bắt đầu:</strong> Kích hoạt đúng vai trò.</li>
        $( ($steps | ForEach-Object { "        <li>$_</li>" }) -join "`n" )
      </ol>
    </section>

    <section class="band">
      <div class="section">
        <p class="eyebrow">Kiểm thử</p>
        <h2>Tiêu chí nghiệm thu gợi ý</h2>
        <ol class="step-list">
          <li>Luồng nghiệp vụ chạy được từ bước 1 tới bước cuối.</li>
          <li>Lỗi nhập liệu trả về mã rõ ràng và có trace để điều tra.</li>
          <li>Dữ liệu đầu ra đúng phạm vi quyền đã phân công.</li>
          <li>Không có bước nào bị treo khi timeout hoặc mất dữ liệu giữa chừng.</li>
        </ol>
      </div>
    </section>

    <footer class="footer"><div class="section"><a href="/trienkhai/$groupSlug.html">Quay về phân hệ $([System.Web.HttpUtility]::HtmlEncode($groupLabel))</a> · <a href="/trienkhai/tat-ca-chuc-nang.html">Xem ma trận đầy đủ</a></div></footer>
  </main>
</body>
</html>
"@

        $groupFolder = Join-Path $base $groupSlug
        New-Item -ItemType Directory -Force -Path $groupFolder | Out-Null
        Set-Content -Path (Join-Path $groupFolder "$slug.html") -Value $pageHtml -Encoding UTF8
    }

    return $featureCards
}

$groups = @(
    @{
        key = "dao-tao"
        label = "Đào tạo & học liệu"
        source = Join-Path $base "dao-tao.html"
        screens = "/courses, /courses/studio, /repository, /learning-path, /videos"
        navKey = "dao-tao"
        intro = "Quản trị khóa học, soạn học liệu và thiết kế lộ trình học tập toàn diện từ khi tạo nội dung đến theo dõi tiến độ."
    },
    @{
        key = "danh-gia"
        label = "Khảo thí và đánh giá"
        source = Join-Path $base "danh-gia.html"
        screens = "/question-banks, /exams, /assignments, /gradebook"
        navKey = "danh-gia"
        intro = "Bao phủ từ quản lý ngân hàng câu hỏi tới chấm, duyệt điểm và công bố kết quả đánh giá."
    },
    @{
        key = "nguoi-hoc"
        label = "Người học & cộng đồng"
        source = Join-Path $base "nguoi-hoc.html"
        screens = "/enrollment, /attendance, /community, /surveys, /career, /credentials"
        navKey = "nguoi-hoc"
        intro = "Dù học trực tiếp hay trực tuyến, phân hệ này đảm bảo hành trình học viên và hồ sơ năng lực đầy đủ."
    },
    @{
        key = "chuan-obe"
        label = "Chuẩn & OBE"
        source = Join-Path $base "chuan-obe.html"
        screens = "/obe, /standards, /moodle-parity, /analytics"
        navKey = "chuan-obe"
        intro = "Liên kết chuẩn đầu ra với học liệu, đánh giá và báo cáo kiểm định một cách có kiểm soát."
    },
    @{
        key = "tich-hop"
        label = "Tích hợp & vận hành"
        source = Join-Path $base "tich-hop.html"
        screens = "/admin, /sis, /security, /plugins, /backup, /admin/lms/system-check"
        navKey = "tich-hop"
        intro = "Thiết lập tenant, role, SIS, API, webhook, backup và kiểm soát vận hành để triển khai an toàn."
    },
    @{
        key = "ai-analytics"
        label = "AI & Analytics"
        source = Join-Path $base "ai-analytics.html"
        screens = "/ai, /analytics, /analytics/learning, /interventions"
        navKey = "ai-analytics"
        intro = "Tích hợp AI và phân tích dữ liệu để trợ giảng, sinh tài nguyên, cảnh báo rủi ro và tăng hiệu quả điều hành."
    }
)

$allFeatureLinks = @()

foreach ($group in $groups) {
    $html = Get-Content -Raw -Path $group.source
    $cardMatches = [regex]::Matches($html, '(?s)<article class="card">(.*?)</article>')

    $names = @()
    $descs = @()
    $flows = @()

    foreach ($match in $cardMatches) {
        $block = $match.Groups[1].Value
        $name = Strip-Html ([regex]::Match($block, '<h3>(.*?)</h3>').Groups[1].Value)
        $desc = Strip-Html ([regex]::Match($block, '<p>(.*?)</p>').Groups[1].Value)
        $lis = [regex]::Matches($block, '<li>(.*?)</li>') | ForEach-Object { $_.Groups[1].Value }

        $names += $name
        $descs += $desc
        if ($lis.Count -gt 0) {
            $flows += (($lis | ForEach-Object { [string](Strip-Html $_) }) -join "; ")
        } else {
            $flows += "Nhập liệu, xử lý quy trình, kiểm thử nghiệp vụ, hoàn tất"
        }
    }

    $cards = Build-FeatureHtml -groupSlug $group.key -groupLabel $group.label -featureNames $names -featureDescs $descs -featureFlows $flows -navKey $group.navKey -groupIntro $group.intro -screens $group.screens

    $groupPage = @"
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>$([System.Web.HttpUtility]::HtmlEncode($group.label)) - EraLMS</title>
  <link rel="stylesheet" href="/trienkhai/assets/site.css">
</head>
<body>
$(Render-Nav -active $group.navKey)

  <main>
    <section class="page-title">
      <div class="section">
        <p class="eyebrow">Phân hệ $([System.Web.HttpUtility]::HtmlEncode($group.label))</p>
        <h1>Chi tiết triển khai theo chức năng</h1>
        <p>$([System.Web.HttpUtility]::HtmlEncode($group.intro))</p>
      </div>
    </section>

    <section class="section">
      <h2>Danh sách chức năng</h2>
      <div class="grid">
$($cards -join "`n")
      </div>
    </section>

    <section class="band">
      <div class="section">
        <p class="eyebrow">Gợi ý kiểm thử</p>
        <h2>Chia nhỏ theo chức năng để nghiệm thu có kiểm soát</h2>
        <p class="lead">Truy cập từng trang để xem luồng nghiệp vụ riêng, ảnh minh họa flow và tiêu chí nghiệm thu.</p>
      </div>
    </section>
  </main>

  <footer class="footer"><div class="section"><a href="/trienkhai">Quay về trang giới thiệu</a></div></footer>
</body>
</html>
"@

    $mainFile = Join-Path $base "$($group.key).html"
    $indexFile = Join-Path $base "$($group.key)\index.html"
    Set-Content -Path $mainFile -Value $groupPage -Encoding UTF8
    Set-Content -Path $indexFile -Value $groupPage -Encoding UTF8

    for ($i = 0; $i -lt $names.Count; $i++) {
        $allFeatureLinks += [pscustomobject]@{
            Group = $group.label
            Name = $names[$i]
            Slug = Convert-ToSlug $names[$i]
            GroupKey = $group.key
            Short = $descs[$i]
            Intro = $group.intro
        }
    }
}

$matrixRows = @{}
foreach ($item in $allFeatureLinks) {
    if (-not $matrixRows.ContainsKey($item.Group)) { $matrixRows[$item.Group] = @() }
    $matrixRows[$item.Group] += @"
        <tr>
          <td><a href="/trienkhai/$($item.GroupKey)/$($item.Slug).html">$([System.Web.HttpUtility]::HtmlEncode($item.Name))</a></td>
          <td>$([System.Web.HttpUtility]::HtmlEncode($item.Short))</td>
          <td><a href="/trienkhai/$($item.GroupKey).html">$([System.Web.HttpUtility]::HtmlEncode($item.Group))</a></td>
          <td>$([System.Web.HttpUtility]::HtmlEncode($item.Intro))</td>
        </tr>
"@
}

$matrixSections = @()
foreach ($group in $groups) {
    $rows = if ($matrixRows.ContainsKey($group.label)) { $matrixRows[$group.label] } else { @() }
    $matrixSections += @"
    <section class="section">
      <p class="eyebrow">$([System.Web.HttpUtility]::HtmlEncode($group.label))</p>
      <h2>Chi tiết chức năng</h2>
      <table class="module-table">
        <thead>
          <tr><th>Chức năng</th><th>Mô tả</th><th>Phân hệ</th><th>Mục tiêu</th></tr>
        </thead>
        <tbody>
$($rows -join "`n")
        </tbody>
      </table>
    </section>
"@
}

$allFunctionPage = @"
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tất cả chức năng - EraLMS</title>
  <link rel="stylesheet" href="/trienkhai/assets/site.css">
</head>
<body>
$(Render-Nav -active "tat-ca")
  <main>
    <section class="page-title">
      <div class="section">
        <p class="eyebrow">Ma trận chức năng</p>
        <h1>Danh sách chi tiết theo từng chức năng</h1>
        <p>Mỗi chức năng có trang riêng gồm mô tả, luồng nghiệp vụ và ảnh minh họa flow triển khai.</p>
      </div>
    </section>
$($matrixSections -join "`n")
  </main>
  <footer class="footer"><div class="section">EraLMS triển khai sản phẩm - <a href="/trienkhai/lo-trinh.html">xem lộ trình go-live</a></div></footer>
</body>
</html>
"@

Set-Content -Path (Join-Path $base "tat-ca-chuc-nang.html") -Value $allFunctionPage -Encoding UTF8
Write-Output "Generated $($groups.Count) nhóm, tổng $($allFeatureLinks.Count) trang chức năng."


