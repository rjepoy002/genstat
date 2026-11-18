'use strict';
const {validate}=require('../tools/validate-report-sql.cjs'),assert=require('assert');
const config={database:'genstat_db',allowedTables:['area_master','area_mapping','overallsalessummary','billadjustment','kwh_diff_allocation','billhistorycancelled','billhistorycancelled_2','latebills']};
const valid=`WITH params AS (SELECT :ReportMonth AS ReportMonth)
SELECT a.AreaID, a.AreaName, SUM(s.KWH) AS Sales_KWH, TRUNCATE(SUM(s.KWH),2) AS KWH_Diff
FROM area_master a JOIN overallsalessummary s ON a.AreaID=1
WHERE s.BillMonth=(SELECT ReportMonth FROM params) GROUP BY a.AreaID,a.AreaName`;
assert.equal(validate({...config,sql:valid}).params.length,1);
for(const sql of ['DELETE FROM area_master','SELECT :ReportMonth; SELECT 2','SELECT :ReportMonth INTO OUTFILE \'x\'','SELECT :ReportMonth, SLEEP(10)','SELECT :ReportMonth, LOAD_FILE(\'x\')','SELECT :ReportMonth, @@version','SELECT :ReportMonth FROM mysql.user','SELECT :ReportMonth FROM area_master FOR UPDATE','SELECT :ReportMonth /* hidden */','WITH x AS (SELECT * FROM mysql.user) SELECT :ReportMonth FROM x','SELECT :ReportMonth, unknown_function(1)','SELECT :ReportMonth FROM billhistorycancelled_3'])assert.throws(()=>validate({...config,sql}));
assert.equal(validate({...config,sql:"SELECT :ReportMonth AS Month, 'UPDATE; DROP' AS Info"}).params.length,1);
console.log('PASS: CTE/aggregates, bound month, and string literals; modifying, multistatement, files, variables, locks, unknown functions and unapproved tables rejected');

for(const table of config.allowedTables){
 for(const name of [table,'genstat_db.'+table])assert(validate({...config,sql:`WITH params AS (SELECT :ReportMonth AS ReportMonth) SELECT (SELECT ReportMonth FROM params) AS ReportMonth FROM ${name}`}).tables.includes(table));
}
const cancelled=`WITH params AS (SELECT :ReportMonth AS ReportMonth),
 cancellations AS (SELECT SACode, SUM(KWH) AS Cancelled_KWH FROM billhistorycancelled WHERE BillMonth=(SELECT ReportMonth FROM params) GROUP BY SACode),
 cancellations2 AS (SELECT SACode, SUM(KWH) AS Cancelled_KWH_2 FROM genstat_db.billhistorycancelled_2 WHERE BillMonth=(SELECT ReportMonth FROM params) GROUP BY SACode)
 SELECT a.SACode, COALESCE(a.Cancelled_KWH,0) AS Cancelled_KWH, COALESCE(b.Cancelled_KWH_2,0) AS Cancelled_KWH_2,
 ROW_NUMBER() OVER (PARTITION BY a.SACode ORDER BY a.Cancelled_KWH) AS SequenceNo FROM cancellations a LEFT JOIN cancellations2 b ON b.SACode=a.SACode`;
assert.deepEqual(validate({...config,sql:cancelled}).tables.sort(),['billhistorycancelled','billhistorycancelled_2']);
for(const sql of ['SELECT :ReportMonth FROM genstat_db.billhistorycancelled /* comment */','SELECT :ReportMonth FROM billhistorycancelled -- comment','SELECT :ReportMonth FROM billhistorycancelled # comment','SELECT /*+ hint */ :ReportMonth FROM billhistorycancelled','SELECT :ReportMonth FROM billhistorycancelled; DELETE FROM billhistorycancelled','SELECT :ReportMonth, SLEEP(1) FROM billhistorycancelled','SELECT :ReportMonth FROM other_db.billhistorycancelled','SELECT :ReportMonth FROM overallsalessummary_x','SELECT :ReportMonth FROM billadjustment_1'])assert.throws(()=>validate({...config,sql}));
assert.throws(()=>validate({...config,sql:'SELECT :ReportMonth FROM other_db.latebills'}));
console.log('PASS: eight reporting tables accepted, including qualified and unqualified latebills; comments/hints, unsafe functions, multiple statements, alternate tables and cross-database access remain blocked');
