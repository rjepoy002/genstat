<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../lib/monthly-worksheets.php';
$period=$argv[1]??'2026-09';
if(!preg_match('/^2026-(0[1-9]|1[0-2])$/D',$period)){fwrite(STDERR,"Expected a period such as 2026-09.\n");exit(1);}
echo json_encode(monthly_source_availability(__DIR__.'/..',2026,(int)substr($period,-2)),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
