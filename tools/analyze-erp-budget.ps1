$ErrorActionPreference = 'Stop'

# ใช้ connection เดิมแบบอ่านค่าใน memory เท่านั้น และไม่แสดง credential ออกมา
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

function Invoke-ResultSets([string]$sql, [string[]]$names) {
  $command = $connection.CreateCommand()
  $command.CommandTimeout = 30
  $command.CommandText = $sql
  $reader = $command.ExecuteReader()
  $result = [ordered]@{}
  foreach ($name in $names) {
    $rows = [System.Collections.Generic.List[object]]::new()
    while ($reader.Read()) {
      $row = [ordered]@{}
      for ($index = 0; $index -lt $reader.FieldCount; $index++) {
        $row[$reader.GetName($index)] = if ($reader.IsDBNull($index)) { $null } else { $reader.GetValue($index) }
      }
      $rows.Add([pscustomobject]$row)
    }
    $result[$name] = @($rows.ToArray())
    [void]$reader.NextResult()
  }
  $reader.Close()
  return $result
}

try {
  $connection.Open()
  $sql = @"
SELECT TOP (1) TABLEID,NAME,SQLNAME
FROM dbo.SQLDICTIONARY
WHERE FIELDID=0 AND ARRAY=0 AND (UPPER(NAME)='LEDGERBUDGET' OR UPPER(SQLNAME)='LEDGERBUDGET');

DECLARE @LedgerBudgetTableId int=(SELECT TOP (1) TABLEID FROM dbo.SQLDICTIONARY WHERE FIELDID=0 AND ARRAY=0 AND (UPPER(NAME)='LEDGERBUDGET' OR UPPER(SQLNAME)='LEDGERBUDGET'));
SELECT CONTEXTCOMPANYID,CONTEXTTABLEID,TRACKINGSTATUS,CONFIGURATIONNAME,COUNT(*) AS row_count,
  MIN(CREATEDDATETIME) AS first_created,MAX(MODIFIEDDATETIME) AS last_modified
FROM dbo.WORKFLOWTRACKINGSTATUSTABLE
WHERE CONTEXTTABLEID=@LedgerBudgetTableId
GROUP BY CONTEXTCOMPANYID,CONTEXTTABLEID,TRACKINGSTATUS,CONFIGURATIONNAME
ORDER BY CONTEXTCOMPANYID,TRACKINGSTATUS,CONFIGURATIONNAME;

SELECT COMPANYID,REFTABLEID,STATUS,TYPE,MENUITEMNAME,DATASOURCENAME,COUNT(*) AS row_count,
  MIN(CREATEDDATETIME) AS first_created,MAX(MODIFIEDDATETIME) AS last_modified
FROM dbo.WORKFLOWWORKITEMTABLE
WHERE REFTABLEID=@LedgerBudgetTableId
GROUP BY COMPANYID,REFTABLEID,STATUS,TYPE,MENUITEMNAME,DATASOURCENAME
ORDER BY COMPANYID,STATUS,TYPE;

SELECT 'LEDGERBUDGET' AS table_name,DATAAREAID,COUNT(*) AS row_count FROM dbo.LEDGERBUDGET GROUP BY DATAAREAID
UNION ALL SELECT 'BUDGETMODEL',DATAAREAID,COUNT(*) FROM dbo.BUDGETMODEL GROUP BY DATAAREAID
UNION ALL SELECT 'BPC_BUDGETTABLE',DATAAREAID,COUNT(*) FROM dbo.BPC_BUDGETTABLE GROUP BY DATAAREAID
UNION ALL SELECT 'BPC_LEDGERBUDGETTRANS',DATAAREAID,COUNT(*) FROM dbo.BPC_LEDGERBUDGETTRANS GROUP BY DATAAREAID
ORDER BY table_name,DATAAREAID;

SELECT MODELID,TXT,BLOCKED,COV,TYPE,SUBMODELID,TRACKREVISIONS,DATAAREAID,RECID
FROM dbo.BUDGETMODEL WHERE DATAAREAID='si5' ORDER BY MODELID;

SELECT YEAR(STARTDATE) AS start_year,COUNT(*) AS row_count,
  SUM(BPC_BUDGET) AS bpc_budget,SUM(BPC_ACTUALAMOUNT) AS actual_amount,
  SUM(BPC_AVAILABLEAMOUNT) AS available_amount,SUM(BPC_RESERVEAMOUNT) AS reserve_amount,
  SUM(BPC_TRANSFER) AS transfer_amount
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5'
GROUP BY YEAR(STARTDATE) ORDER BY start_year;

SELECT ACTIVE,STOP,COVSTATUS,REPORT,COUNT(*) AS row_count
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5'
GROUP BY ACTIVE,STOP,COVSTATUS,REPORT ORDER BY ACTIVE,STOP,COVSTATUS,REPORT;

SELECT STATUS,CANCEL,POSTEDJN,COUNT(*) AS row_count,
  SUM(CASE WHEN NULLIF(PURCHREQID,'') IS NOT NULL THEN 1 ELSE 0 END) AS with_pr,
  SUM(CASE WHEN NULLIF(PURCHID,'') IS NOT NULL THEN 1 ELSE 0 END) AS with_po,
  SUM(CASE WHEN NULLIF(INVOICEID,'') IS NOT NULL THEN 1 ELSE 0 END) AS with_invoice,
  MIN(TRANSDATE) AS first_transdate,MAX(TRANSDATE) AS last_transdate
FROM dbo.BPC_LEDGERBUDGETTRANS WHERE DATAAREAID='si5'
GROUP BY STATUS,CANCEL,POSTEDJN ORDER BY STATUS,CANCEL,POSTEDJN;

SELECT TOP (30) BUDGETNO,NAME,DATAAREAID,RECID
FROM dbo.BPC_BUDGETTABLE WHERE DATAAREAID='si5' ORDER BY BUDGETNO;

SELECT 'BUDGETMODEL' AS source_table,MODELID AS code,TXT AS name,DATAAREAID,RECID
FROM dbo.BUDGETMODEL WHERE DATAAREAID='si5' AND MODELID IN ('AC-26-001','BD-17-001','BG26','BG27')
UNION ALL
SELECT 'BPC_BUDGETTABLE',BUDGETNO,NAME,DATAAREAID,RECID
FROM dbo.BPC_BUDGETTABLE WHERE DATAAREAID='si5' AND BUDGETNO IN ('AC-26-001','BD-17-001','PDP-511503','PDB-511601');

SELECT DIMENSIONCODE,NUM,DESCRIPTION,CLOSED,DATAAREAID,RECID
FROM dbo.DIMENSIONS WHERE DATAAREAID='si5'
  AND NUM IN ('SPV-06','SPV-07','NON','AC-26-001','BD-17-001','BG26Q4','BG27Q1')
ORDER BY DIMENSIONCODE,NUM;

SELECT ACCOUNTNUM,ACCOUNTNAME,CLOSED,BLOCKEDINJOURNAL,BPC_CHECKBUDGET,DATAAREAID,RECID
FROM dbo.LEDGERTABLE WHERE DATAAREAID='si5' AND ACCOUNTNUM IN ('141016','114014','511503','511601')
ORDER BY ACCOUNTNUM;

SELECT COUNT(DISTINCT MODELNUM) AS model_count,COUNT(DISTINCT BPC_BUDGETNO) AS budget_no_count,
  COUNT(DISTINCT ACCOUNTNUM) AS account_count,COUNT(DISTINCT DIMENSION) AS department_count,
  COUNT(DISTINCT DIMENSION2_) AS cost_dimension_count,COUNT(DISTINCT DIMENSION3_) AS purpose_count
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5' AND YEAR(STARTDATE)=2026;

SELECT DIMENSION AS dept_code,COUNT(*) AS row_count
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5' AND YEAR(STARTDATE)=2026
GROUP BY DIMENSION ORDER BY DIMENSION;

SELECT MODELNUM,COUNT(*) AS ledger_row_count,COUNT(DISTINCT BPC_BUDGETNO) AS budget_no_count,
  COUNT(DISTINCT ACCOUNTNUM) AS account_count,MIN(STARTDATE) AS first_date,MAX(STARTDATE) AS last_date
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5' AND MODELNUM IN ('AC-26-001','BD-17-001','BG26','BG27')
GROUP BY MODELNUM ORDER BY MODELNUM;

SELECT RECID,MODELNUM,BPC_BUDGETNO,ACCOUNTNUM,STARTDATE,ENDDATE,FREQCODE,ACTIVE,AMOUNT,COMMENT_,
  DIMENSION,DIMENSION2_,DIMENSION3_,AUTOTRANS,CURRENCY,QTY,PRICE,STOP,KEY_,REPORT,COV,COVSTATUS,
  EXPANDID,CREDITING,FREQ,TAXGROUP,INVENTRECID,INVENTTABLEID,ALLOCATEMETHOD,FORECASTMODELID,
  ASSETID,ASSETTRANSTYPE,ASSETBOOKID,PROJTRANSID,AMOUNTMST,REVISIONDATE,
  BPC_RESERVEAMOUNT,BPC_ACTUALAMOUNT,
  BPC_AVAILABLEAMOUNT,BPC_SPAMOUNT,BPC_TRANSFER,BPC_BUDGET,CREATEDDATETIME,CREATEDBY,
  MODIFIEDDATETIME,DEL_MODIFIEDTIME,MODIFIEDBY,DATAAREAID,RECVERSION,
  BPC_USERADJSPAMT,BPC_DATETIMEADJSPAMT,BPC_DATETIMEADJSPAMTTZID
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5' AND
 ((MODELNUM='AC-26-001' AND COMMENT_=N'เครื่องพิมพ์รุ่น LQ-310') OR
  (MODELNUM='BD-17-001' AND COMMENT_='Packing' AND DIMENSION='SPV-07'))
ORDER BY MODELNUM,STARTDATE,RECID;

SELECT TOP (30) RECID,MODELNUM,BPC_BUDGETNO,ACCOUNTNUM,COMMENT_,DIMENSION,DIMENSION2_,DIMENSION3_,
  STARTDATE,ENDDATE,ACTIVE,STOP,COVSTATUS,REPORT,BPC_BUDGET,BPC_ACTUALAMOUNT,
  BPC_AVAILABLEAMOUNT,BPC_RESERVEAMOUNT,BPC_SPAMOUNT,BPC_TRANSFER,CREATEDDATETIME,CREATEDBY,
  MODIFIEDDATETIME,MODIFIEDBY,DATAAREAID
FROM dbo.LEDGERBUDGET WHERE DATAAREAID='si5' AND YEAR(STARTDATE)>=2025
ORDER BY STARTDATE DESC,RECID DESC;

SELECT o.name AS object_name,m.definition
FROM sys.sql_modules m JOIN sys.objects o ON o.object_id=m.object_id
WHERE o.name IN ('BPC_LEDGERBUDGETVIEW','BPC_LEDGERBUDGETTRANSVIEW') ORDER BY o.name;

SELECT tr.name AS trigger_name,OBJECT_NAME(tr.parent_id) AS parent_table,tr.is_disabled
FROM sys.triggers tr WHERE OBJECT_NAME(tr.parent_id) IN ('LEDGERBUDGET','BPC_BUDGETTABLE','BPC_LEDGERBUDGETTRANS');

SELECT OBJECT_NAME(fkc.parent_object_id) AS child_table,COL_NAME(fkc.parent_object_id,fkc.parent_column_id) AS child_column,
  OBJECT_NAME(fkc.referenced_object_id) AS parent_table,COL_NAME(fkc.referenced_object_id,fkc.referenced_column_id) AS parent_column
FROM sys.foreign_key_columns fkc
WHERE OBJECT_NAME(fkc.parent_object_id) IN ('LEDGERBUDGET','BPC_BUDGETTABLE','BPC_LEDGERBUDGETTRANS')
   OR OBJECT_NAME(fkc.referenced_object_id) IN ('LEDGERBUDGET','BPC_BUDGETTABLE','BPC_LEDGERBUDGETTRANS');
"@
  $names = @('ledger_budget_table_id','workflow_tracking','workflow_work_items','company_counts','models_si5',
    'year_summary_si5','ledger_status_distribution','transaction_status_distribution','budget_numbers_si5',
    'target_masters','target_dimensions','target_accounts','year_2026_distinct_counts','erp_departments_2026',
    'target_model_cardinality','target_full_rows','recent_budget_rows','view_definitions','triggers','foreign_keys')
  $result = Invoke-ResultSets $sql $names
  $output = [ordered]@{
    checked_at = (Get-Date).ToString('o')
    source = 'SI-DBSV / 192.168.7.10 / SIERP2017'
    scope = 'Budget-related SELECT analysis only; no writes.'
    results = $result
  }
  $path = Join-Path (Split-Path $PSScriptRoot -Parent) 'ERP_BUDGET_ANALYSIS_RAW.json'
  $output | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $path -Encoding UTF8
  [pscustomobject]@{
    path = $path
    ledger_budget_table_id = $result.ledger_budget_table_id
    workflow_tracking_groups = $result.workflow_tracking.Count
    workflow_work_item_groups = $result.workflow_work_items.Count
    company_count_groups = $result.company_counts.Count
    models_si5 = $result.models_si5.Count
    recent_budget_rows = $result.recent_budget_rows.Count
    triggers = $result.triggers.Count
    foreign_keys = $result.foreign_keys.Count
  } | ConvertTo-Json -Depth 5
} finally {
  $connection.Dispose()
  $values.Clear()
}
