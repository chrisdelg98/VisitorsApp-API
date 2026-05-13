$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8000/api/v1'

function Show-Step($title) {
    Write-Host "`n=== $title ===" -ForegroundColor Cyan
}

function Invoke-Json {
    param([string]$Method, [string]$Url, $Body=$null, $Headers=@{})
    $Headers['Accept'] = 'application/json'
    if ($Body) {
        $Headers['Content-Type'] = 'application/json'
        $b = ($Body | ConvertTo-Json -Depth 8 -Compress)
        return Invoke-RestMethod -Method $Method -Uri $Url -Headers $Headers -Body $b
    } else {
        return Invoke-RestMethod -Method $Method -Uri $Url -Headers $Headers
    }
}

Show-Step '1. POST /auth/validate-station'
$r = Invoke-Json POST "$base/auth/validate-station" @{ code = 'EFL-001' }
$r | ConvertTo-Json -Depth 5
$apiKey = $r.data.api_key
$hdr = @{ 'X-API-Key' = $apiKey }

Show-Step '2. POST /auth/validate-station BAD code'
try { Invoke-Json POST "$base/auth/validate-station" @{ code = 'NOPE' } } catch { Write-Host ('  -> HTTP ' + $_.Exception.Response.StatusCode.value__) -ForegroundColor Yellow }

Show-Step '3. GET /station/me'
(Invoke-Json GET "$base/station/me" $null $hdr) | ConvertTo-Json -Depth 5

Show-Step '4. POST /visitors'
$v = Invoke-Json POST "$base/visitors" @{
    first_name='Juan'; last_name='Perez'; document_number='12345678-9';
    document_type='DUI'; email='juan@example.com'; phone='7777-0000'; company='ACME'
} $hdr
$v | ConvertTo-Json -Depth 5
$visitorId = $v.data.id

Show-Step '5. GET /visitors/search?q=Pere'
(Invoke-Json GET "$base/visitors/search?q=Pere" $null $hdr) | ConvertTo-Json -Depth 5

Show-Step '6. PUT /visitors/{id}'
(Invoke-Json PUT "$base/visitors/$visitorId" @{ company='ACME Updated' } $hdr) | ConvertTo-Json -Depth 5

Show-Step '7. POST /visits (check-in)'
$visit = Invoke-Json POST "$base/visits" @{
    visitor_id=$visitorId; visitor_type='Visitor'; visit_reason='Interview';
    visiting_person='Maria Lopez'; notes='Pruebas E2E'
} $hdr
$visit | ConvertTo-Json -Depth 5
$visitId = $visit.data.id

Show-Step '8. GET /visits/active'
(Invoke-Json GET "$base/visits/active" $null $hdr) | ConvertTo-Json -Depth 5

Show-Step '9. GET /visits/{id}'
(Invoke-Json GET "$base/visits/$visitId" $null $hdr) | ConvertTo-Json -Depth 5

Show-Step '10. GET /visitors/{id}/latest-visit'
(Invoke-Json GET "$base/visitors/$visitorId/latest-visit" $null $hdr) | ConvertTo-Json -Depth 5

Show-Step '11. POST /visits/{id}/images (real PNG upload)'
$png = [byte[]](
    0x89,0x50,0x4E,0x47,0x0D,0x0A,0x1A,0x0A,
    0x00,0x00,0x00,0x0D,0x49,0x48,0x44,0x52,
    0x00,0x00,0x00,0x01,0x00,0x00,0x00,0x01,
    0x08,0x06,0x00,0x00,0x00,0x1F,0x15,0xC4,
    0x89,0x00,0x00,0x00,0x0D,0x49,0x44,0x41,
    0x54,0x78,0x9C,0x63,0x00,0x01,0x00,0x00,
    0x05,0x00,0x01,0x0D,0x0A,0x2D,0xB4,0x00,
    0x00,0x00,0x00,0x49,0x45,0x4E,0x44,0xAE,
    0x42,0x60,0x82
)
$tmp = Join-Path $env:TEMP 'pixel.png'
[System.IO.File]::WriteAllBytes($tmp, $png)
$form = @{ type='personal_photo'; image = Get-Item $tmp }
(Invoke-RestMethod -Method POST -Uri "$base/visits/$visitId/images" -Headers $hdr -Form $form) | ConvertTo-Json -Depth 5

Show-Step '12. GET /visits/{id}/images/personal_photo'
$resp = Invoke-WebRequest -Uri "$base/visits/$visitId/images/personal_photo" -Headers $hdr -UseBasicParsing
Write-Host ('  status=' + $resp.StatusCode + ' content-type=' + $resp.Headers['Content-Type'] + ' bytes=' + $resp.Content.Length)

Show-Step '13. PATCH /visits/{id}/checkout'
(Invoke-Json PATCH "$base/visits/$visitId/checkout" $null $hdr) | ConvertTo-Json -Depth 5

Show-Step '14. PATCH /visits/{id}/checkout AGAIN (should 409)'
try { Invoke-Json PATCH "$base/visits/$visitId/checkout" $null $hdr } catch { Write-Host ('  -> HTTP ' + $_.Exception.Response.StatusCode.value__) -ForegroundColor Yellow }

Show-Step '15. Validation error: POST /visitors missing fields (should 422)'
try { Invoke-Json POST "$base/visitors" @{ first_name='X' } $hdr } catch {
    $s = $_.ErrorDetails.Message
    Write-Host ('  -> HTTP ' + $_.Exception.Response.StatusCode.value__)
    Write-Host $s
}

Write-Host "`n=== ALL TESTS DONE ===" -ForegroundColor Green
