param([ValidateSet('tables','columns','sample','packing','catalog')][string]$Mode = 'tables')
$ErrorActionPreference = 'Stop'
# Read existing credentials in memory only; never execute the legacy PHP file.
$source = Get-Content -LiteralPath '\\192.168.7.12\htdocs\ERP\conn_erp.php' -Raw
$values = @{}
foreach ($key in @('servername5','databasename5','user5','pass5')) {
  $match = [regex]::Match($source, ('(?m)^\s*\$' + $key + '\s*=\s*[''"]([^''"]*)[''"]\s*;'))
  if (!$match.Success) { throw ('Cannot parse configuration key: ' + $key) }
  $values[$key] = $match.Groups[1].Value
}
$builder = New-Object System.Data.SqlClient.SqlConnectionStringBuilder
$builder['Data Source'] = $values.servername5
$builder['Initial Catalog'] = $values.databasename5
$builder['User ID'] = $values.user5
$builder['Password'] = $values.pass5
$builder['Connect Timeout'] = 8
$builder['Encrypt'] = $true
$builder['TrustServerCertificate'] = $true
$connection = New-Object System.Data.SqlClient.SqlConnection $builder.ConnectionString
try {
  $connection.Open()
  $command = $connection.CreateCommand()
  $command.CommandTimeout = 15
  if ($Mode -eq 'catalog') {
    # Export schema metadata only, never table contents, defaults, or credentials.
    $command.CommandText = @"
SELECT DB_NAME() AS database_name,
  CONVERT(nvarchar(128),SERVERPROPERTY('ServerName')) AS server_name,
  CONVERT(nvarchar(128),SERVERPROPERTY('ProductVersion')) AS sql_version,
  CONVERT(nvarchar(128),DATABASEPROPERTYEX(DB_NAME(),'Updateability')) AS updateability,
  HAS_PERMS_BY_NAME(DB_NAME(),'DATABASE','VIEW DEFINITION') AS can_view_definition;
SELECT name AS database_name,state_desc,is_read_only,HAS_DBACCESS(name) AS can_access
FROM sys.databases ORDER BY name;
SELECT s.name AS schema_name,t.name AS table_name,COUNT(c.column_id) AS column_count
FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id
JOIN sys.columns c ON c.object_id=t.object_id
WHERE t.is_ms_shipped=0 GROUP BY s.name,t.name ORDER BY s.name,t.name;
SELECT s.name AS schema_name,o.name AS object_name,o.type_desc AS object_type,
  c.column_id AS ordinal,c.name AS column_name,ty.name AS data_type,
  c.max_length AS max_length_bytes,c.precision,c.scale,c.is_nullable,c.is_identity,c.is_computed,
  CASE WHEN EXISTS (SELECT 1 FROM sys.indexes i JOIN sys.index_columns ic
    ON ic.object_id=i.object_id AND ic.index_id=i.index_id
    WHERE i.object_id=c.object_id AND i.is_primary_key=1 AND ic.column_id=c.column_id)
    THEN 1 ELSE 0 END AS is_primary_key_column
FROM sys.objects o JOIN sys.schemas s ON s.schema_id=o.schema_id
JOIN sys.columns c ON c.object_id=o.object_id JOIN sys.types ty ON ty.user_type_id=c.user_type_id
WHERE o.type IN ('U','V') AND o.is_ms_shipped=0 ORDER BY s.name,o.name,c.column_id;
SELECT s.name AS schema_name,v.name AS view_name,COUNT(c.column_id) AS column_count
FROM sys.views v JOIN sys.schemas s ON s.schema_id=v.schema_id
JOIN sys.columns c ON c.object_id=v.object_id
WHERE v.is_ms_shipped=0 GROUP BY s.name,v.name ORDER BY s.name,v.name;
"@
    $catalogReader = $command.ExecuteReader()
    $catalogNames = @('source','visible_databases','tables','columns','views')
    $catalog = [ordered]@{ checked_at = (Get-Date).ToString('o'); scope = 'Visible schema metadata only; no business rows or SQL definitions exported.' }
    foreach ($catalogName in $catalogNames) {
      $catalogRows = [System.Collections.Generic.List[object]]::new()
      while ($catalogReader.Read()) {
        $catalogRow = [ordered]@{}
        for ($index=0; $index -lt $catalogReader.FieldCount; $index++) {
          $catalogRow[$catalogReader.GetName($index)] = if ($catalogReader.IsDBNull($index)) { $null } else { $catalogReader.GetValue($index) }
        }
        $catalogRows.Add([pscustomobject]$catalogRow)
      }
      $catalog[$catalogName] = @($catalogRows.ToArray())
      [void]$catalogReader.NextResult()
    }
    $catalogReader.Close()
    $catalogPath = Join-Path (Split-Path $PSScriptRoot -Parent) 'ERP_SCHEMA_CATALOG.json'
    $catalog | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $catalogPath -Encoding UTF8
    [pscustomobject]@{
      path=$catalogPath; source=$catalog.source; visible_databases=$catalog.visible_databases;
      table_count=$catalog.tables.Count; view_count=$catalog.views.Count;
      table_column_count=@($catalog.columns | Where-Object object_type -eq 'USER_TABLE').Count;
      view_column_count=@($catalog.columns | Where-Object object_type -eq 'VIEW').Count
    } | ConvertTo-Json -Depth 5
    return
  }
  if ($Mode -eq 'tables') {
    $command.CommandText = "SELECT DB_NAME() AS database_name,s.name AS schema_name,t.name AS table_name FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id WHERE t.name LIKE @pattern OR t.name IN ('LEDGERTABLE','DIMENSIONS','DATAAREA') ORDER BY t.name"
  } elseif ($Mode -eq 'columns') {
    $command.CommandText = "SELECT t.name AS table_name,c.name AS column_name,ty.name AS data_type FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id JOIN sys.types ty ON ty.user_type_id=c.user_type_id WHERE t.name LIKE @pattern ORDER BY t.name,c.column_id"
  } elseif ($Mode -eq 'packing') {
    $command.CommandText = "SELECT TOP (20) RECID,DATAAREAID,MODELNUM,BPC_BUDGETNO,ACCOUNTNUM,COMMENT_,DIMENSION,DIMENSION2_,DIMENSION3_,CONVERT(varchar(10),STARTDATE,120) AS start_date,AMOUNT,AMOUNTMST,BPC_BUDGET,BPC_ACTUALAMOUNT,BPC_AVAILABLEAMOUNT,BPC_RESERVEAMOUNT,BPC_SPAMOUNT,BPC_TRANSFER FROM dbo.LEDGERBUDGET WHERE DATAAREAID=@company AND MODELNUM=@model AND COMMENT_=@description AND DIMENSION=@department AND DIMENSION2_=@cost ORDER BY STARTDATE,RECID"
    [void]$command.Parameters.AddWithValue('@company','si5')
    [void]$command.Parameters.AddWithValue('@model','BD-17-001')
    [void]$command.Parameters.AddWithValue('@description','Packing')
    [void]$command.Parameters.AddWithValue('@department','SPV-07')
    [void]$command.Parameters.AddWithValue('@cost','NON')
  } else {
    $command.CommandText = "SELECT TOP (5) RECID,DATAAREAID,MODELNUM,BPC_BUDGETNO,ACCOUNTNUM,COMMENT_,DIMENSION,DIMENSION2_,DIMENSION3_,CONVERT(varchar(10),STARTDATE,120) AS start_date,AMOUNT,AMOUNTMST,BPC_BUDGET,BPC_ACTUALAMOUNT,BPC_AVAILABLEAMOUNT,BPC_RESERVEAMOUNT,BPC_SPAMOUNT,BPC_TRANSFER FROM dbo.LEDGERBUDGET WHERE DATAAREAID=@company AND MODELNUM=@model ORDER BY RECID"
    [void]$command.Parameters.AddWithValue('@company','si5')
    [void]$command.Parameters.AddWithValue('@model','AC-26-001')
  }
  [void]$command.Parameters.AddWithValue('@pattern','%BUDGET%')
  $reader = $command.ExecuteReader()
  $table = New-Object System.Data.DataTable
  $table.Load($reader)
  foreach ($row in $table.Rows) {
    $result = [ordered]@{}
    foreach ($column in $table.Columns) { $result[$column.ColumnName] = $row[$column.ColumnName] }
    [pscustomobject]$result | ConvertTo-Json -Compress
  }
} catch [System.Data.SqlClient.SqlException] {
  Write-Output ('SQL error number: ' + $_.Exception.Number)
} finally {
  $connection.Dispose()
  $values.Clear()
}
