# Cancelled Bill History Inspection

Inspection date: 2026-11-16 (Asia/Manila). Source: live local `genstat_db`, MariaDB 10.4.32, plus the current repository and available Git history. Full-table findings cover 98 and 397 rows, not samples. This report is the only workspace artifact added by this inspection. No application code, source records, schema, saved SQL settings, mappings, allocations, or workbook formulas were changed.

## A. Executive Summary

The tables have identical 90-column definitions but different, nonoverlapping record sets. `billhistorycancelled` has 98 rows and 216,285 KWH; `_2` has 397 rows and 128,777 KWH. Both cover BillMonth July–September 2026. `_2` has earlier entry dates but also overlaps the first table in time; it is neither demonstrated to be an archive nor a replacement. Its origin remains unknown.

Every row is Posted=1 and Cancelled=0 despite both table names. All 98 rows in the first table have BillMonthRef later than BillMonth; 396 of 397 rows in `_2` have matching months. No shared transaction IDs, non-null rowguids, or SACode/account/bill identities were found. Candidate adjustment links exist, including Post-Sales records, but no cancellation lineage is established. Keep the tables separate and investigate before reporting use.

The application has no runtime reference to either table, and its configured reporting allowlist excludes them. Saved query definitions, mappings, and history are empty. The approved `genstat_kwh` SQL described in the request is unavailable locally; its arithmetic and any upstream cancellation treatment cannot be certified. No cancelled KWH subtraction is justified by this inspection.

## B. Database Structure Comparison

| Property | billhistorycancelled | billhistorycancelled_2 |
| --- | --- | --- |
| Total columns | 90 | 90 |
| Total records | 98 | 397 |
| Primary key | None | None |
| Unique index | ux_sales_area_rowguid (SACode,rowguid) | Same |
| Nonunique index | ix_sales_area_month (SACode,BillMonth) | Same |
| Engine / row format | InnoDB / Dynamic | Same |
| Charset / collation | utf8mb4 / utf8mb4_unicode_ci | Same |
| Auto increment | None | None |
| Table comment / create options | Empty / empty | Same |
| Earliest BillMonth | 202607 | 202607 |
| Latest BillMonth | 202609 | 202609 |
| Earliest BillMonthRef | 202608 | 202607 |
| Latest BillMonthRef | 202610 | 202609 |
| Earliest entry | 2026-08-05 12:07:20.043 | 2026-07-22 08:20:32.187 |
| Latest entry | 2026-10-26 15:47:23.653 | 2026-10-07 16:26:17.603 |
| Earliest BillDate | 2026-07-18 00:00:00.000 | 2026-07-16 00:00:00.000 |
| Latest BillDate | 2026-10-07 00:00:00.000 | 2026-10-06 00:00:00.000 |
| Distinct accounts | 88 | 397 |
| Distinct TransIDs | 98 | 397 |
| Distinct BillNos | 98 | 397 |
| Distinct SACodes | 5 | 5 |
| NULL rowguids | 37 | 382 |
| Local table creation | 2026-10-28 09:32:07 | 2026-10-28 09:32:18 |
| Data length bytes | 114688 | 376832 |
| Index length bytes | 32768 | 32768 |

Column order, types/precision, nullability, defaults, per-column collation/comments, and extra attributes compare identically using SHOW FULL COLUMNS. Normalized SHOW CREATE TABLE differs only in table name. Index definitions match; estimated cardinalities differ with data. SHOW TABLE STATUS estimates were 98/383; exact counts are 98/397. Neither estimated row count nor local creation timestamp establishes source history.

Notable defaults: TransID defaults to an empty string; EntryDate defaults to current_timestamp(3); nullable fields default to NULL. BIT(1) fields include Posted and Cancelled. Nullable rowguid means the composite unique index does not prevent multiple NULL-guid rows. No unique constraint protects TransID or BillNo. Both tables have a BEFORE INSERT trigger that supplies UUID() when TransID is empty. These triggers do not set cancellation status, cancellation time, or sales totals. Full shared DDL appears in Appendix 1.

## C. Monthly Record Comparison

In the following tables A = billhistorycancelled, B = billhistorycancelled_2. Zero means the group is absent. Amounts are sums of stored TotalBill; KWH sums use the stored DOUBLE values. Each table is aggregated independently before alignment, avoiding join multiplication.

### By BillMonth and SACode

| Month | SACode | A Records | B Records | A KWH | B KWH | A TotalBill | B TotalBill | A Accounts | B Accounts | A Transactions | B Transactions | B−A KWH |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 202607 | 01 | 33 | 6 | 75360 | 14819 | 987586.5000 | 189184.7400 | 33 | 6 | 33 | 6 | -60541 |
| 202607 | 03 | 0 | 331 | 0 | 41149 | 0 | 528018.1400 | 0 | 331 | 0 | 331 | 41149 |
| 202607 | 04 | 15 | 0 | 19156 | 0 | 201778.2700 | 0 | 15 | 0 | 15 | 0 | -19156 |
| 202607 | 05 | 2 | 6 | 1717 | 4156 | 21865.7800 | 48515.8700 | 2 | 6 | 2 | 6 | 2439 |
| 202607 | 06 | 1 | 0 | 36 | 0 | 511.6200 | 0 | 1 | 0 | 1 | 0 | -36 |
| 202608 | 01 | 18 | 5 | 55659 | 6338 | 709415.0400 | 83427.3000 | 18 | 5 | 18 | 5 | -49321 |
| 202608 | 02 | 0 | 3 | 0 | 123 | 0 | 1742.4700 | 0 | 3 | 0 | 3 | 123 |
| 202608 | 03 | 1 | 15 | 0 | 38021 | 5.6000 | 477001.5000 | 1 | 15 | 1 | 15 | 38021 |
| 202608 | 04 | 17 | 0 | 28693 | 0 | 302079.8700 | 0 | 17 | 0 | 17 | 0 | -28693 |
| 202608 | 05 | 1 | 4 | 385 | 1776 | 4958.1700 | 22728.0300 | 1 | 4 | 1 | 4 | 1391 |
| 202609 | 01 | 10 | 4 | 35279 | 6927 | 431270.7400 | 82315.2600 | 10 | 4 | 10 | 4 | -28352 |
| 202609 | 02 | 0 | 1 | 0 | 0 | 0 | 5.6000 | 0 | 1 | 0 | 1 | 0 |
| 202609 | 03 | 0 | 11 | 0 | 9890 | 0 | 129557.1700 | 0 | 11 | 0 | 11 | 9890 |
| 202609 | 04 | 0 | 2 | 0 | 2999 | 0 | 35356.2200 | 0 | 2 | 0 | 2 | 2999 |
| 202609 | 05 | 0 | 9 | 0 | 2579 | 0 | 33489.8600 | 0 | 9 | 0 | 9 | 2579 |

### BillMonth monthly totals

| Month | A Records | A KWH | A Accounts | A Transactions | B Records | B KWH | B Accounts | B Transactions |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 202607 | 51 | 96269 | 51 | 51 | 343 | 60124 | 343 | 343 |
| 202608 | 37 | 84737 | 37 | 37 | 27 | 46258 | 27 | 27 |
| 202609 | 10 | 35279 | 10 | 10 | 27 | 22395 | 27 | 27 |

### By BillMonthRef and SACode

| Month | SACode | A Records | B Records | A KWH | B KWH | A TotalBill | B TotalBill | A Accounts | B Accounts | A Transactions | B Transactions | B−A KWH |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 202607 | 01 | 0 | 5 | 0 | 6345 | 0 | 80369.5400 | 0 | 5 | 0 | 5 | 6345 |
| 202607 | 03 | 0 | 331 | 0 | 41149 | 0 | 528018.1400 | 0 | 331 | 0 | 331 | 41149 |
| 202607 | 05 | 0 | 6 | 0 | 4156 | 0 | 48515.8700 | 0 | 6 | 0 | 6 | 4156 |
| 202608 | 01 | 22 | 6 | 58534 | 14812 | 763483.6800 | 192242.5000 | 22 | 6 | 22 | 6 | -43722 |
| 202608 | 02 | 0 | 3 | 0 | 123 | 0 | 1742.4700 | 0 | 3 | 0 | 3 | 123 |
| 202608 | 03 | 0 | 15 | 0 | 38021 | 0 | 477001.5000 | 0 | 15 | 0 | 15 | 38021 |
| 202608 | 05 | 2 | 4 | 1717 | 1776 | 21865.7800 | 22728.0300 | 2 | 4 | 2 | 4 | 59 |
| 202608 | 06 | 1 | 0 | 36 | 0 | 511.6200 | 0 | 1 | 0 | 1 | 0 | -36 |
| 202609 | 01 | 23 | 4 | 67806 | 6927 | 873146.5400 | 82315.2600 | 20 | 4 | 23 | 4 | -60879 |
| 202609 | 02 | 0 | 1 | 0 | 0 | 0 | 5.6000 | 0 | 1 | 0 | 1 | 0 |
| 202609 | 03 | 1 | 11 | 0 | 9890 | 5.6000 | 129557.1700 | 1 | 11 | 1 | 11 | 9890 |
| 202609 | 04 | 24 | 2 | 24935 | 2999 | 265093.5700 | 35356.2200 | 22 | 2 | 24 | 2 | -21936 |
| 202609 | 05 | 1 | 9 | 385 | 2579 | 4958.1700 | 33489.8600 | 1 | 9 | 1 | 9 | 2194 |
| 202610 | 01 | 16 | 0 | 39958 | 0 | 491642.0600 | 0 | 12 | 0 | 16 | 0 | -39958 |
| 202610 | 04 | 8 | 0 | 22914 | 0 | 238764.5700 | 0 | 8 | 0 | 8 | 0 | -22914 |

### BillMonthRef monthly totals

| Month | A Records | A KWH | A Accounts | A Transactions | B Records | B KWH | B Accounts | B Transactions |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 202607 | 0 | 0 | 0 | 0 | 342 | 51650 | 342 | 342 |
| 202608 | 25 | 60287 | 25 | 25 | 28 | 54732 | 28 | 28 |
| 202609 | 49 | 93126 | 44 | 49 | 27 | 22395 | 27 | 27 |
| 202610 | 24 | 62872 | 20 | 24 | 0 | 0 | 0 | 0 |

The month/SACode tables enumerate every shared and one-table-only group. July has a large `_2` concentration in SACode 03 (331 records, 41,149 KWH), absent in A for that BillMonth. A has substantially more July SACode 01 energy (75,360 versus 14,819 KWH), and July SACode 04/06 rows absent in B. In August, A has SACode 04 records absent in B; B has SACode 02 records absent in A. No statistical significance threshold is assumed; exact differences are supplied above.

### Joint billing/reference-month coverage

**billhistorycancelled**

| BillMonth | BillMonthRef | SACode | Records | KWH |
| --- | --- | --- | --- | --- |
| 202607 | 202608 | 01 | 22 | 58534 |
| 202607 | 202608 | 05 | 2 | 1717 |
| 202607 | 202608 | 06 | 1 | 36 |
| 202607 | 202609 | 01 | 9 | 16278 |
| 202607 | 202609 | 04 | 13 | 15830 |
| 202607 | 202610 | 01 | 2 | 548 |
| 202607 | 202610 | 04 | 2 | 3326 |
| 202608 | 202609 | 01 | 14 | 51528 |
| 202608 | 202609 | 03 | 1 | 0 |
| 202608 | 202609 | 04 | 11 | 9105 |
| 202608 | 202609 | 05 | 1 | 385 |
| 202608 | 202610 | 01 | 4 | 4131 |
| 202608 | 202610 | 04 | 6 | 19588 |
| 202609 | 202610 | 01 | 10 | 35279 |

**billhistorycancelled_2**

| BillMonth | BillMonthRef | SACode | Records | KWH |
| --- | --- | --- | --- | --- |
| 202607 | 202607 | 01 | 5 | 6345 |
| 202607 | 202607 | 03 | 331 | 41149 |
| 202607 | 202607 | 05 | 6 | 4156 |
| 202607 | 202608 | 01 | 1 | 8474 |
| 202608 | 202608 | 01 | 5 | 6338 |
| 202608 | 202608 | 02 | 3 | 123 |
| 202608 | 202608 | 03 | 15 | 38021 |
| 202608 | 202608 | 05 | 4 | 1776 |
| 202609 | 202609 | 01 | 4 | 6927 |
| 202609 | 202609 | 02 | 1 | 0 |
| 202609 | 202609 | 03 | 11 | 9890 |
| 202609 | 202609 | 04 | 2 | 2999 |
| 202609 | 202609 | 05 | 9 | 2579 |

## D. Duplicate Investigation

| Identity | Shared distinct | A distinct only | B distinct only |
| --- | --- | --- | --- |
| TransID | 0 | 98 | 397 |
| non-NULL rowguid | 0 | 61 | 15 |
| SACode + non-NULL rowguid | 0 | 61 | 15 |
| SACode + account + bill | 0 | 98 | 397 |
| SACode + account + bill + both months | 0 | 98 | 397 |

The three broadest overlap checks (TransID, non-NULL rowguid, and SACode/account/bill) were also executed as SQL joins under the database collation: all returned zero pairs. Adding BillMonth, BillMonthRef, SACode, or Area cannot create overlaps absent under the broader bill identity.

All 98 A rows and 397 B rows are exclusive under these transaction and bill identities. Within each table, TransID, BillNo, and SACode/account/bill are distinct per row. There are no repeated TransIDs, no exact duplicate full rows, no shared-ID conflicting rows, and no same-bill identity with different TransIDs within or between tables. Exact duplicates were checked across every stored column using complete rows in memory; lack of any shared TransID also excludes cross-table full-row duplicates. Matching-transaction KWH, amount, user and date comparisons have no applicable pairs; zero overlap does not prove either source complete.

Repeated KWH values do occur: A has 9 zero rows and repeated positive values 1165, 735 and 628 (two rows each). B has 35 zero rows and numerous repeated positive values (for example 6 KWH occurs eight times). Equal consumption alone is not a duplicate cancellation. These are distinct records under the tested bill identities; workflow-level event distinctness still needs upstream evidence.

## E. Cancellation Workflow Differences

**billhistorycancelled**

| Posted | Cancelled | Records |
| --- | --- | --- |
| 1 | 0 | 98 |

**billhistorycancelled_2**

| Posted | Cancelled | Records |
| --- | --- | --- |
| 1 | 0 | 397 |

No row has Cancelled=1 or Posted=0. Both tables share the same observed flags, but this cannot prove the same workflow or explain why histories retain Cancelled=0. Filtering on Cancelled=1 would exclude all 495 rows. CancelledBy is populated in every row. UserName equals CancelledBy for 10/98 A rows and 356/397 B rows. B is dominated by the E.Padon/E.Padon pair (301 records); A has more mixed user/cancellation-user pairs.

### User distribution: billhistorycancelled

| UserName | CancelledBy | Records | MinEntry | MaxEntry |
| --- | --- | --- | --- | --- |
| Ailyn | Ailyn | 1 | 2026-08-07 11:30:37.793 | 2026-08-07 11:30:37.793 |
| angel | jayson | 32 | 2026-08-28 14:47:10.700 | 2026-10-26 13:59:54.873 |
| dvlazaro | faye | 1 | 2026-08-28 13:39:13.010 | 2026-08-28 13:39:13.010 |
| dvlazaro | Nieko | 1 | 2026-09-30 14:02:18.937 | 2026-09-30 14:02:18.937 |
| dvlazaro | rcmaranan | 2 | 2026-08-17 14:55:35.077 | 2026-09-15 08:43:45.510 |
| faye | Nieko | 3 | 2026-08-14 09:15:50.300 | 2026-09-14 11:34:57.320 |
| faye | rcmaranan | 27 | 2026-08-11 09:12:32.643 | 2026-10-26 15:47:23.653 |
| fstahum | fstahum | 3 | 2026-08-05 12:07:20.043 | 2026-09-14 09:48:04.937 |
| gfvelasco | rcmaranan | 1 | 2026-09-14 16:15:23.397 | 2026-09-14 16:15:23.397 |
| mjdelosreyes | yqbadenas | 1 | 2026-10-08 09:22:01.633 | 2026-10-08 09:22:01.633 |
| Nieko | Nieko | 4 | 2026-08-12 16:04:11.537 | 2026-10-13 13:09:02.237 |
| Nieko | rcmaranan | 6 | 2026-08-10 09:35:42.737 | 2026-10-15 14:18:39.703 |
| rbbriones | Nieko | 1 | 2026-08-13 13:29:18.753 | 2026-08-13 13:29:18.753 |
| rclaridad | rclaridad | 1 | 2026-09-22 09:43:17.747 | 2026-09-22 09:43:17.747 |
| rcmaranan | rcmaranan | 1 | 2026-08-11 08:37:05.050 | 2026-08-11 08:37:05.050 |
| yqbadenas | Nieko | 4 | 2026-09-09 11:48:07.833 | 2026-09-23 13:37:42.087 |
| yqbadenas | rcmaranan | 9 | 2026-08-10 13:31:41.007 | 2026-10-23 10:25:18.340 |

### User distribution: billhistorycancelled_2

| UserName | CancelledBy | Records | MinEntry | MaxEntry |
| --- | --- | --- | --- | --- |
| angel | angel | 2 | 2026-10-01 09:33:11.907 | 2026-10-04 13:11:09.500 |
| dvlazaro | Nieko | 2 | 2026-10-06 16:58:27.763 | 2026-10-07 16:26:17.603 |
| E.Padon | E.Padon | 301 | 2026-07-23 11:05:19.233 | 2026-07-31 15:34:59.953 |
| E.Padon | rclaridad | 30 | 2026-07-22 08:20:32.187 | 2026-08-25 17:06:44.997 |
| faye | Nieko | 2 | 2026-08-05 11:36:59.543 | 2026-09-08 11:41:25.817 |
| faye | rcmaranan | 4 | 2026-07-31 17:45:27.023 | 2026-09-08 09:29:19.760 |
| fstahum | fstahum | 19 | 2026-07-30 20:16:26.810 | 2026-10-06 18:05:43.233 |
| halili | halili | 4 | 2026-08-25 08:21:56.167 | 2026-09-28 09:07:28.873 |
| mjdelosreyes | rcmaranan | 1 | 2026-08-26 14:34:08.980 | 2026-08-26 14:34:08.980 |
| Nieko | Nieko | 2 | 2026-08-07 10:00:26.720 | 2026-08-07 10:02:10.863 |
| rclaridad | rclaridad | 26 | 2026-07-30 11:18:55.633 | 2026-10-01 11:35:53.640 |
| rcmaranan | rcmaranan | 2 | 2026-08-14 11:15:18.177 | 2026-10-05 13:12:01.843 |
| System | Nieko | 1 | 2026-10-05 16:32:39.040 | 2026-10-05 16:32:39.040 |
| yqbadenas | Nieko | 1 | 2026-08-05 14:49:58.353 | 2026-08-05 14:49:58.353 |

EntryDate is later than BillDate for all rows; no equality or earlier date was found. Entry dates precede local table creation, so this local default cannot by itself explain the preserved timestamps. The default demonstrates insertion-time behavior only if EntryDate is omitted; callers can supply historical values. No dedicated cancellation timestamp, upstream cancellation writer, or audit trail establishing EntryDate semantics was found. Treat EntryDate as an unverified event timestamp, not a certified cancellation date.

## F. KWH Reporting Impact

| Source | KWH | TotalBill | Positive | Negative | Zero | CMKWH | CMNonzero | RPPDKWH | RPPDNonzero | MonthMismatch |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| billhistorycancelled | 216285 | 2659471.5900 | 89 | 0 | 9 | 0 | 0 | 0.0000 | 0 | 98 |
| billhistorycancelled_2 | 128777 | 1631342.1600 | 362 | 0 | 35 | 0 | 0 | 0.0000 | 0 | 1 |

CMKWH and RPPDKWH are zero throughout both tables, so this dataset demonstrates no additional cancelled-energy contribution from them. A reference months are always later, spanning August–October, while BillMonth spans July–September. B has one exceptional July/August record (8,474 KWH, SACode 01); all other months match. Choosing either period without a workflow definition would substantially change reporting attribution. A naive union totals 495 records and 345,062 KWH; this is a source inventory, not a reporting correction.

### Relationship to billadjustment

The live adjustment schema has EntryID, account, SACode, Area, ASBBillNo and BillNo, both months, ASBKWH/KWH, AdjustmentType and PreSales; it has no cancellation TransID foreign key. All 4,264 adjustment rows were read for an in-memory comparison after inspecting schema/counts. Candidate matches require SACode + AcctNo + bill number matching ASBBillNo or BillNo. These are stronger than account/month matches but remain candidate links.

billhistorycancelled: 8 history rows match 10 adjustment pairs. 5 pairs have AdjustmentType=Post-Sales. All candidate pairs share Area; none shares a non-NULL rowguid or TransID/EntryID.

billhistorycancelled_2: 5 history rows match 5 adjustment pairs. 1 pairs have AdjustmentType=Post-Sales. All candidate pairs share Area; none shares a non-NULL rowguid or TransID/EntryID.

| Source | BillMonth | Ref | Adj month | Adj ref | Type | PreSales | History KWH | ASBKWH | Adj KWH |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A | 202609 | 202610 | 202609 | 202610 | Post-Sales | 0 | 22235 | 0 | 22235 |
| A | 202607 | 202608 | 202607 | 202608 | Post-Sales | 0 | 430 | 0 | 430 |
| A | 202607 | 202608 | 202607 | 202608 | Post-Sales | 0 | 6487 | 0 | 6487 |
| A | 202607 | 202608 | 202607 | 202608 | Post-Sales | 0 | 533 | 0 | 533 |
| A | 202608 | 202609 | 202608 | 202608 | Pre-Sales | 0 | 2110 | 0 | 2110 |
| A | 202608 | 202609 | 202608 | 202609 | Post-Sales | 0 | 25164 | 143 | 25164 |
| A | 202608 | 202609 | 202608 | 202608 | Pre-Sales | 0 | 5922 | 6596 | 5922 |
| A | 202608 | 202609 | 202608 | 202608 | Pre-Sales | 0 | 5922 | 4931 | 6596 |
| A | 202608 | 202609 | 202608 | 202608 | Pre-Sales | 0 | 5922 | 0 | 4931 |
| A | 202608 | 202609 | 202608 | 202608 | Pre-Sales | 0 | 2400 | 0 | 2400 |
| B | 202609 | 202609 | 202609 | 202610 | Post-Sales | 0 | 0 | 59 | 0 |
| B | 202608 | 202608 | 202608 | 202608 | Pre-Sales | 0 | 713 | 0 | 713 |
| B | 202608 | 202608 | 202608 | 202608 | Pre-Sales | 0 | 4360 | 0 | 4360 |
| B | 202608 | 202608 | 202608 | 202608 | Pre-Sales | 0 | 942 | 0 | 942 |
| B | 202608 | 202608 | 202608 | 202608 | Pre-Sales | 0 | 2642 | 0 | 2642 |

A has one bill with three candidate adjustment rows, illustrating join fan-out. Candidate adjustment BillNo and ASBBillNo both match the history bill in these pairs. Several Pre-Sales text values coexist with PreSales=0; the bit alone is not a reliable classification for these candidates. Many candidate adjusted KWH values equal the history KWH, but that does not establish whether the history is a before/after cancellation snapshot. Post-Sales candidate overlap makes double counting plausible if cancellation KWH were added or subtracted independently. No relationship is certified without upstream transaction lineage.

### Relationship to overallsalessummary

The 1,623-row sales table contains aggregate grain columns (SACode, Area, Book, RateCode, LocationPoint, BillMonth), with a decimal TransID rather than the cancellation char(36) identifier. It has no AcctNo or BillNo. There is no valid bill-level join proving whether cancellation energy is already reflected. Common month/area coverage is contextual only. No source-generation code or trigger recalculating sales from these histories was found. Upstream inclusion/exclusion remains unknown.

### Area mapping

Every history row maps to exactly one area_mapping row using (SACode,Area), whose unique key prevents multiplication. The referenced AreaID exists in area_master for all rows. The schema also has foreign keys for GenstatAreaID and kwhdiffAreaID. These IDs differ in some areas, so selecting the correct reporting target remains a business-rule decision.

**billhistorycancelled**

| SACode | Area | AreaID | GenstatAreaID | kwhdiffAreaID | AreaName | Records | KWH |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 01 | 01 | 1 | 1 | 1 | Puerto Princesa City | 18 | 75472 |
| 01 | 02 | 1 | 1 | 1 | Puerto Princesa City | 43 | 90826 |
| 03 | 05 | 9 | 9 | 9 | Narra | 1 | 0 |
| 04 | 11 | 11 | 11 | 9 | Rizal | 32 | 47849 |
| 05 | 23 | 13 | 12 | 9 | Espanola | 1 | 268 |
| 05 | 26 | 14 | 12 | 9 | Bataraza | 2 | 1834 |
| 06 | 31 | 2 | 2 | 2 | Roxas | 1 | 36 |

**billhistorycancelled_2**

| SACode | Area | AreaID | GenstatAreaID | kwhdiffAreaID | AreaName | Records | KWH |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 01 | 01 | 1 | 1 | 1 | Puerto Princesa City | 9 | 7035 |
| 01 | 02 | 1 | 1 | 1 | Puerto Princesa City | 6 | 21049 |
| 02 | 03 | 7 | 8 | 8 | Inagawan | 2 | 2 |
| 02 | 04 | 8 | 8 | 8 | Aborlan | 2 | 121 |
| 03 | 05 | 9 | 9 | 9 | Narra | 9 | 5371 |
| 03 | 06 | 9 | 9 | 9 | Narra | 14 | 16449 |
| 03 | 07 | 9 | 9 | 9 | Narra | 6 | 19105 |
| 03 | 08 | 9 | 9 | 9 | Narra | 325 | 35818 |
| 03 | 09 | 9 | 9 | 9 | Narra | 1 | 12018 |
| 03 | 30 | 9 | 9 | 9 | Narra | 2 | 299 |
| 04 | 10 | 10 | 10 | 9 | Quezon | 1 | 1113 |
| 04 | 11 | 11 | 11 | 9 | Rizal | 1 | 1886 |
| 05 | 20 | 12 | 12 | 9 | Brooke's Pt. | 1 | 75 |
| 05 | 21 | 12 | 12 | 9 | Brooke's Pt. | 4 | 1656 |
| 05 | 22 | 12 | 12 | 9 | Brooke's Pt. | 1 | 544 |
| 05 | 23 | 13 | 12 | 9 | Espanola | 3 | 581 |
| 05 | 25 | 12 | 12 | 9 | Brooke's Pt. | 1 | 162 |
| 05 | 26 | 14 | 12 | 9 | Bataraza | 7 | 4073 |
| 05 | 27 | 15 | 15 | 12 | Balabac | 2 | 1420 |

## G. Application Usage

Searched repository text recursively, including hidden/ignored first-party files, PHP, SQL, JS, configuration, docs, storage, tools, tests and migration/import/synchronization names; excluded Git object internals and third-party node_modules. Available Git history was searched with git log --all -S billhistorycancelled. No import dump or cancellation table creation/migration script was found. Binary workbook content and dependencies are not asserted to be an exhaustive external-integration audit.

| Path / line | Behavior |
| --- | --- |
| docs/genstat-full-system-inspection.md:177–178 | Earlier source inventory; identifies both tables as unused. |
| docs/genstat-full-system-inspection.md:704,803 | Earlier observed column/index listings; no source creation history. |
| tests/query-parser.cjs:9 | Negative parser test explicitly rejects SELECT from billhistorycancelled; not a runtime data consumer. |
| config/monthly-source.php:13–14; lib/monthly-worksheets.php:20–39 | Availability checks use sales and adjustment sources only; no cancellation fallback. |
| tools/setup-query-settings.php:13,18–22 | Approved five-table reporting grant list omits both histories. Inspected as text only; setup was not run. |
| lib/query-settings.php:8–16,39–58,71–72 | Loads local allowlist; validates reporting grants and SQL; reads saved definitions/mappings. No direct history read. |
| config/query-settings.local.php (runtime configuration) | Inspected allowlist through the application configuration path; both histories excluded. Credentials not reproduced. |

The listed previous inspection and parser test are all pre-existing literal references to either name found in first-party repository text. No query unions the tables, uses one as fallback, or imports `_2` as an alternate source. The new report itself introduces documentation references. Live genstat_query_definitions, genstat_row_query_mappings and genstat_query_history each contain zero rows. Consequently there is no stored genstat_kwh definition to inspect here. No direct duplicate KWH calculation from these histories is demonstrated in this local application.

Database integration inspection found only the UUID-supplying cancellation triggers described above (and a similar adjustment UUID trigger), zero schema events and zero routines. The Git hit is commit 81c4456, which added the earlier inspection documentation, not evidence of the tables’ original import. No available repository scheduler/import script explains `_2`. External Windows/server jobs, remote ETL and the original billing system were not audited; absence locally does not exclude them.

## H. Findings and Risks

- No current cross-table duplicates were found; nullable GUIDs and unconstrained TransIDs still permit future ambiguity. UUID defaults may generate distinct IDs for separate imports of an upstream event, so bill identities remain necessary safeguards.
- Different coverage and 37/382 NULL rowguids prevent treating either table as a complete authoritative cancellation source. No missing-record count can be established without a source ledger.
- All Cancelled flags are false, and EntryDate semantics are unverified. Names and timestamps cannot substitute for workflow evidence.
- July–September is the entire observed BillMonth range; gaps outside it are unknown completeness, not proof that no cancellations occurred.
- Billing/reference month differences, unequal area target IDs, and adjustment candidate fan-out can distort totals.
- Sales-source cancellation treatment remains unknown. Independently subtracting history KWH could double count a correction already present in sales or adjustments.

## I. Recommendation

Keep the tables separate and investigate further before use. They contain distinct observed records with overlapping BillMonth coverage and materially different reference-month/user distributions; their business roles are unverified. Do not classify `_2` as a backup, archive or successor. Do not combine them into reporting or subtract their KWH from Total KWH Sold on this evidence. Preserve the approved calculation and existing mappings/allocation rules.

A future logical combination requires verified source provenance, workflow/status definitions, period attribution, stable upstream bill/cancellation identity, and reconciliation against sales and adjustment lineage. Current absence of duplicates is a baseline, not a permanent deduplication rule.

## J. Unresolved Questions

1. Why was `_2` created, what upstream extracts populate each table, and are either complete?
2. Why do all records have Cancelled=0, and what do Posted, UserName and CancelledBy mean in the source workflow?
3. Is EntryDate original bill insertion, modification or cancellation time? Where is the actual cancellation audit?
4. Which period is the approved cancellation reporting period, especially for July/August and later references?
5. Are candidate adjustment links the same business event, and why do some Pre-Sales labels have PreSales=0?
6. Does source generation already net cancellations into overallsalessummary?
7. Where is the approved genstat_kwh SQL and its documented source/mapping/allocation semantics?
8. Do external scheduled imports or remote billing integrations explain the tables?

## Appendix 1. Verified shared table definition

The following SHOW CREATE TABLE output applies to both tables after changing only the table name. It is documentation, not an instruction to execute DDL.

```sql
CREATE TABLE `billhistorycancelled` (
  `TransID` char(36) NOT NULL DEFAULT '',
  `BillMonth` char(6) NOT NULL,
  `BillMonthRef` char(6) NOT NULL,
  `AcctNo` varchar(13) NOT NULL,
  `AcctCode` varchar(12) NOT NULL,
  `Area` char(2) NOT NULL,
  `Book` char(4) NOT NULL,
  `Sequence` double NOT NULL,
  `Name` varchar(75) NOT NULL,
  `Address` varchar(50) NOT NULL,
  `RateCode` char(1) NOT NULL,
  `LocationPoint` char(1) NOT NULL,
  `BillNo` decimal(19,0) NOT NULL,
  `ReadingDate` datetime(3) DEFAULT NULL,
  `BillDate` datetime(3) DEFAULT NULL,
  `DueDate` datetime(3) DEFAULT NULL,
  `FlatRate` bit(1) NOT NULL,
  `MeterSerial` varchar(20) DEFAULT NULL,
  `FeedBackCode` char(2) DEFAULT NULL,
  `MultiplierKWH` double NOT NULL,
  `MultiplierDemand` double NOT NULL,
  `PrsRdngKWH` double NOT NULL,
  `PrvRdngKWH` double NOT NULL,
  `PrsRdngDemand` double NOT NULL,
  `PrvRdngDemand` double NOT NULL,
  `KWH` double NOT NULL,
  `Demand` double NOT NULL,
  `CM` char(1) NOT NULL,
  `CMMultiplierKWH` double NOT NULL,
  `CMLastRdngKWH` double NOT NULL,
  `CMOldRdngKWH` double NOT NULL,
  `CMInitialRdngKWH` double NOT NULL,
  `CMLastRdngDemand` double NOT NULL,
  `CMInitialRdngDemand` double NOT NULL,
  `CMKWH` double NOT NULL,
  `CMDemand` double NOT NULL,
  `GenSysCharge` decimal(19,4) NOT NULL,
  `HostCommCharge` decimal(19,4) NOT NULL,
  `ForexCharge` decimal(19,4) NOT NULL,
  `TCDemandCharge` decimal(19,4) NOT NULL,
  `TCSystemCharge` decimal(19,4) NOT NULL,
  `SystemLossCharge` decimal(19,4) NOT NULL,
  `DCDemandCharge` decimal(19,4) NOT NULL,
  `DCSystemCharge` decimal(19,4) NOT NULL,
  `SCRetCustCharge` decimal(19,4) NOT NULL,
  `SCSupplySysCharge` decimal(19,4) NOT NULL,
  `MCRetailCustCharge` decimal(19,4) NOT NULL,
  `MCSystemCharge` decimal(19,4) NOT NULL,
  `ICCrossSubsidyCharge` decimal(19,4) NOT NULL,
  `UCNPCStrandedDebts` decimal(19,4) NOT NULL,
  `UCNPCStrandedContCost` decimal(19,4) NOT NULL,
  `UCDUStrandedContCost` decimal(19,4) NOT NULL,
  `UCME` decimal(19,4) NOT NULL,
  `UCEqTaxesAndRoyalties` decimal(19,4) NOT NULL,
  `UCEC` decimal(19,4) NOT NULL,
  `UCCrossSubRemoval` decimal(19,4) NOT NULL,
  `LifelineDiscSubs` decimal(19,4) NOT NULL,
  `PowerActRateRed` decimal(19,4) NOT NULL,
  `LoanCondo` decimal(19,4) NOT NULL,
  `LoanCondoFix` decimal(19,4) NOT NULL,
  `PowerCostAdj` decimal(19,4) NOT NULL,
  `GenSysEvat` decimal(19,4) NOT NULL,
  `SystemLossEvat` decimal(19,4) NOT NULL,
  `TCDemandEvat` decimal(19,4) NOT NULL,
  `TCSystemEvat` decimal(19,4) NOT NULL,
  `DistributionEvat` decimal(19,4) NOT NULL,
  `OtherEvat` decimal(19,4) NOT NULL,
  `Evat` decimal(19,4) NOT NULL,
  `PowerBill` decimal(19,4) NOT NULL,
  `TotalOC` decimal(19,4) NOT NULL,
  `TotalOCEvat` decimal(19,4) NOT NULL,
  `TotalBill` decimal(19,4) NOT NULL,
  `Surcharge` decimal(19,4) NOT NULL,
  `SurchargeEvat` decimal(19,4) NOT NULL,
  `PPayDiscount` decimal(19,4) NOT NULL,
  `vat_sales` decimal(19,4) NOT NULL,
  `vat_zerorated` decimal(19,4) NOT NULL,
  `vat_exemption` decimal(19,4) NOT NULL,
  `LateBillMonth` char(6) NOT NULL,
  `FB` char(10) NOT NULL,
  `Posted` bit(1) NOT NULL,
  `Cancelled` bit(1) NOT NULL,
  `UserName` varchar(20) NOT NULL,
  `CancelledBy` varchar(20) NOT NULL,
  `EntryDate` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `UCMERed` decimal(19,4) NOT NULL,
  `RPPDKWH` decimal(19,4) NOT NULL,
  `RPPDConsumer` decimal(19,4) NOT NULL,
  `SACode` char(2) NOT NULL,
  `rowguid` char(36) DEFAULT NULL,
  UNIQUE KEY `ux_sales_area_rowguid` (`SACode`,`rowguid`),
  KEY `ix_sales_area_month` (`SACode`,`BillMonth`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Appendix 2. Inspection method and reproducible read-only SQL

SHOW CREATE TABLE, SHOW FULL COLUMNS, SHOW INDEX and SHOW TABLE STATUS were inspected before record comparison. EXPLAIN estimated full scans of 98/383 rows and a buffered nested-loop join for TransID matching; the actual 98×397 comparison is small. Large-source joins were avoided: source projections were compared in memory. Aggregate/full-row reads were run in a read-only consistent-snapshot transaction and rolled back. SQL overlap confirmation ran separately; no writes were issued. Read results reflect the inspected local database, not an upstream production guarantee. No application test suite was run because fixture tests can mutate settings/workbooks and no application behavior changed.

The queries below were executed for each history table; replace the table name with billhistorycancelled_2 to reproduce the second set. Raw customer rows were used only for comparison and are not included in this report.

```sql
SHOW CREATE TABLE billhistorycancelled;
```

```sql
SHOW FULL COLUMNS FROM billhistorycancelled;
```

```sql
SHOW INDEX FROM billhistorycancelled;
```

```sql
SELECT COUNT(*) Records, MIN(BillMonth) MinMonth,MAX(BillMonth) MaxMonth,MIN(BillMonthRef) MinRef,MAX(BillMonthRef) MaxRef,MIN(EntryDate) MinEntry,MAX(EntryDate) MaxEntry,MIN(BillDate) MinBillDate,MAX(BillDate) MaxBillDate,COUNT(DISTINCT AcctNo) Accounts,COUNT(DISTINCT TransID) Transactions,COUNT(DISTINCT BillNo) Bills,COUNT(DISTINCT SACode) SACodes,SUM(KWH) KWH,SUM(TotalBill) TotalBill,SUM(KWH>0) Positive,SUM(KWH<0) Negative,SUM(KWH=0) Zero,SUM(CMKWH) CMKWH,SUM(CMKWH<>0) CMNonzero,SUM(RPPDKWH) RPPDKWH,SUM(RPPDKWH<>0) RPPDNonzero,SUM(BillMonth<>BillMonthRef) MonthMismatch,SUM(rowguid IS NULL) NullGuid,SUM(TransID='') BlankTransID,SUM(EntryDate=BillDate) EntryEqualsBillDate,SUM(EntryDate<BillDate) EntryBeforeBillDate FROM billhistorycancelled;
```

```sql
SELECT BillMonth Month,SACode,COUNT(*) Records,SUM(KWH) KWH,SUM(TotalBill) TotalBill,COUNT(DISTINCT AcctNo) Accounts,COUNT(DISTINCT TransID) Transactions,SUM(CMKWH) CMKWH,SUM(RPPDKWH) RPPDKWH FROM billhistorycancelled GROUP BY BillMonth,SACode ORDER BY BillMonth,SACode;
```

```sql
SELECT BillMonthRef Month,SACode,COUNT(*) Records,SUM(KWH) KWH,SUM(TotalBill) TotalBill,COUNT(DISTINCT AcctNo) Accounts,COUNT(DISTINCT TransID) Transactions,SUM(CMKWH) CMKWH,SUM(RPPDKWH) RPPDKWH FROM billhistorycancelled GROUP BY BillMonthRef,SACode ORDER BY BillMonthRef,SACode;
```

```sql
SELECT BillMonth,BillMonthRef,SACode,COUNT(*) Records,SUM(KWH) KWH FROM billhistorycancelled GROUP BY BillMonth,BillMonthRef,SACode ORDER BY BillMonth,BillMonthRef,SACode;
```

```sql
SELECT CAST(Posted AS UNSIGNED) Posted,CAST(Cancelled AS UNSIGNED) Cancelled,COUNT(*) Records FROM billhistorycancelled GROUP BY Posted,Cancelled;
```

```sql
SELECT UserName,CancelledBy,COUNT(*) Records,MIN(EntryDate) MinEntry,MAX(EntryDate) MaxEntry FROM billhistorycancelled GROUP BY UserName,CancelledBy;
```

```sql
SELECT c.SACode,c.Area,m.AreaID,m.GenstatAreaID,m.kwhdiffAreaID,a.AreaName,COUNT(*) Records,SUM(c.KWH) KWH FROM billhistorycancelled c LEFT JOIN area_mapping m ON m.SACode=c.SACode AND m.Area=c.Area LEFT JOIN area_master a ON a.AreaID=m.AreaID GROUP BY c.SACode,c.Area,m.AreaID,m.GenstatAreaID,m.kwhdiffAreaID,a.AreaName;
```

```sql
SHOW CREATE TABLE billadjustment;
```

```sql
SELECT COUNT(*) Records FROM billadjustment;
```

```sql
SHOW CREATE TABLE overallsalessummary;
```

```sql
SELECT COUNT(*) Records FROM overallsalessummary;
```

```sql
SHOW CREATE TABLE area_mapping;
```

```sql
SELECT COUNT(*) Records FROM area_mapping;
```

```sql
SHOW CREATE TABLE area_master;
```

```sql
SELECT COUNT(*) Records FROM area_master;
```

```sql
SELECT EntryID,ASBBillNo,BillNo,AcctNo,SACode,Area,BillMonth,BillMonthRef,KWH,ASBKWH,CAST(PreSales AS UNSIGNED) PreSales,rowguid,AdjustmentType FROM billadjustment;
```

```sql
SELECT BillMonth,SACode,Area,COUNT(*) Records,SUM(KWH) KWH FROM overallsalessummary GROUP BY BillMonth,SACode,Area;
```

```sql
SELECT query_key,sql_text FROM genstat_query_definitions;
```

```sql
SELECT COUNT(*) Records FROM genstat_query_history;
```

```sql
SHOW COLUMNS FROM genstat_query_history;
```

Additional executed checks:
```sql
SHOW TABLE STATUS WHERE Name IN ('billhistorycancelled','billhistorycancelled_2');
EXPLAIN SELECT * FROM billhistorycancelled;
EXPLAIN SELECT * FROM billhistorycancelled_2;
EXPLAIN SELECT COUNT(*) FROM billhistorycancelled a JOIN billhistorycancelled_2 b ON a.TransID=b.TransID;
SELECT COUNT(*) FROM billhistorycancelled a JOIN billhistorycancelled_2 b ON a.TransID=b.TransID;
SELECT COUNT(*) FROM billhistorycancelled a JOIN billhistorycancelled_2 b ON a.rowguid=b.rowguid;
SELECT COUNT(*) FROM billhistorycancelled a JOIN billhistorycancelled_2 b ON a.SACode=b.SACode AND a.AcctNo=b.AcctNo AND a.BillNo=b.BillNo;
SHOW TRIGGERS;
SHOW EVENTS;
SELECT ROUTINE_NAME,ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='genstat_db';
SELECT COUNT(*) FROM genstat_row_query_mappings;
```
