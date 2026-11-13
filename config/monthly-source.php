<?php
// Read-only availability checks against the locally inspected schema. These
// are source-table descriptors, NOT approved calculations or cell mappings.
return [
 'connection'=>[
  'host'=>getenv('GENSTAT_DB_HOST')?:'127.0.0.1',
  'port'=>(int)(getenv('GENSTAT_DB_PORT')?:3306),
  'database'=>getenv('GENSTAT_DB_NAME')?:'genstat_db',
  'user'=>getenv('GENSTAT_DB_USER')!==false?getenv('GENSTAT_DB_USER'):'root',
  'password'=>getenv('GENSTAT_DB_PASSWORD')!==false?getenv('GENSTAT_DB_PASSWORD'):'',
 ],
 'sources'=>[
  'overallsalessummary'=>['period'=>'BillMonth','columns'=>['SACode','AreaDefinition','Area','Book','RateCode','LocationPoint','TotCon','KWH','Demand','PowerBill','TotalBill']],
  'billadjustment'=>['period'=>'BillMonth','referencePeriod'=>'BillMonthRef','columns'=>['SACode','Area','Book','AdjustmentType','ASBKWH','KWH','ASBPowerBill','PowerBill','PreSales']],
 ],
 // No reviewed area/rate/period/adjustment-to-cell specification or complete
 // source coverage exists in this project. A row count cannot authorize a sheet.
 'worksheetMappings'=>[],
];
