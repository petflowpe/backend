# Aplica el trait BelongsToCompany a todos los modelos multi-tenant.
# Idempotente: si el modelo ya tiene el trait, no hace nada.
#
# Uso (desde la carpeta backend/):
#   pwsh -File .\scripts\apply_company_scope.ps1
#
# Lista hardcoded de modelos multi-tenant detectados (con columna company_id).
# Excluimos: User (auth especial), Company (es la raiz), Branch (multi-tenant
# pero conviene revisar manualmente), ChatConversation (revisar manualmente).

$modelsDir = Join-Path $PSScriptRoot '..\app\Models'
$models = @(
    'AccountingEntry','Appointment','Area','AuditLog','BillingDocument','Boleta',
    'Brand','CashMovement','CashSession','Category','ClientReview','Client',
    'CompanyConfiguration','CompanyTaxProfile','CreditNote','DailySummary',
    'DebitNote','DispatchGuide','Invoice','MedicalRecord','Notification',
    'OptimizationRecord','Payment','Pet','PetConfiguration','Product','ProductSale',
    'PurchaseOrder','Retention','Route','Service','StockMovement','Supplier',
    'Unit','VaccineRecord','Vehicle','VehicleConfiguration','VehicleCoverageRule',
    'VehicleExpense','VehicleInspection','VehicleInspectionTemplate',
    'VehicleMaintenance','VehicleService','VoidedDocument','Zone','ChatConversation'
)

$applied = @()
$skipped = @()
$missing = @()

foreach ($name in $models) {
    $path = Join-Path $modelsDir "$name.php"
    if (-not (Test-Path $path)) {
        $missing += $name
        continue
    }
    $content = Get-Content $path -Raw
    if ($content -match 'BelongsToCompany') {
        $skipped += $name
        continue
    }

    # 1) Insertar el use statement despues del ultimo use ya presente.
    $newUse = 'use App\Models\Concerns\BelongsToCompany;'
    # Encuentra todos los matches de "use ...;" antes de "class ...".
    $classIdx = $content.IndexOf("`nclass ")
    if ($classIdx -lt 0) { $classIdx = $content.IndexOf('class ') }
    $beforeClass = $content.Substring(0, $classIdx)
    $afterClass  = $content.Substring($classIdx)
    $useMatches = [regex]::Matches($beforeClass, '(?m)^use [^;]+;\s*$')
    if ($useMatches.Count -gt 0) {
        $lastMatch = $useMatches[$useMatches.Count - 1]
        $insertAt = $lastMatch.Index + $lastMatch.Length
        $beforeClass = $beforeClass.Substring(0, $insertAt) + "`r`n" + $newUse + $beforeClass.Substring($insertAt)
    } else {
        # Si no hay usings, lo metemos despues del namespace.
        $nsMatch = [regex]::Match($beforeClass, '(?m)^namespace [^;]+;\s*$')
        if ($nsMatch.Success) {
            $insertAt = $nsMatch.Index + $nsMatch.Length
            $beforeClass = $beforeClass.Substring(0, $insertAt) + "`r`n`r`n" + $newUse + $beforeClass.Substring($insertAt)
        }
    }
    $content = $beforeClass + $afterClass

    # 2) Agregar el trait en la primera declaracion `use ...;` DENTRO de la clase.
    # Buscamos la primera linea que sea exactamente "    use XXX;" o "    use XXX, YYY;" tras la apertura de la clase.
    $classOpenIdx = $content.IndexOf('{', $content.IndexOf('class '))
    $bodyStart = $classOpenIdx + 1
    $bodyTail = $content.Substring($bodyStart)
    $traitRegex = [regex]'(?m)^(\s*)use\s+([^;]+);'
    $traitMatch = $traitRegex.Match($bodyTail)
    if ($traitMatch.Success -and $traitMatch.Index -lt 500) {
        $indent  = $traitMatch.Groups[1].Value
        $traits  = $traitMatch.Groups[2].Value.Trim()
        if ($traits -notmatch 'BelongsToCompany') {
            $newDecl = "$indent" + 'use ' + $traits + ', BelongsToCompany;'
            $bodyTail = $bodyTail.Remove($traitMatch.Index, $traitMatch.Length).Insert($traitMatch.Index, $newDecl)
        }
    } else {
        # No habia ningun "use XXX;" interno: insertamos uno propio justo despues de la apertura de la clase.
        $bodyTail = "`r`n    use BelongsToCompany;`r`n" + $bodyTail
    }
    $content = $content.Substring(0, $bodyStart) + $bodyTail

    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
    $applied += $name
}

Write-Host "Aplicados ($($applied.Count)):" -ForegroundColor Green
$applied | ForEach-Object { Write-Host "  + $_" }
Write-Host "Saltados (ya tenian el trait) ($($skipped.Count)):" -ForegroundColor Yellow
$skipped | ForEach-Object { Write-Host "  = $_" }
if ($missing.Count -gt 0) {
    Write-Host "No encontrados ($($missing.Count)):" -ForegroundColor Red
    $missing | ForEach-Object { Write-Host "  ! $_" }
}
