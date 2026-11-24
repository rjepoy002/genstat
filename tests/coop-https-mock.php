<?php

// Appended to the copied client only by the isolated browser fixture.
function coop_https_request(array $connection, ?array $body = null): array
{
    coop_https_url($connection['apiUrl']);
    if ($body === null) {
        usleep(200000);
        return match ($connection['apiKey']) {
            'bad-key' => coop_https_response(401, '{}'),
            'database-off' => coop_https_response(503, '{}'),
            'bad-json' => coop_https_response(200, '{'),
            'bad-version' => coop_https_response(200, '{"success":true,"api_version":"1.0","database_connected":true}'),
            'timeout' => throw new DomainException('API request timed out.'),
            'redirect' => coop_https_response(302, '{}'),
            'missing-endpoint' => coop_https_response(404, '{}'),
            'fixture-secret' => coop_https_response(200, '{"success":true,"api_version":"1.1","database_connected":true}'),
            default => throw new DomainException('Invalid API key.'),
        };
    }
    if ($connection['apiKey'] !== 'fixture-secret') {
        throw new DomainException('Invalid API key.');
    }
    $lookup = ($body['action'] ?? null) === 'account_lookup';
    $month = $body['ReportMonth'] ?? null;
    if (!$lookup && (($body['action'] ?? null) !== 'monthly_consumption' || !in_array($month, ['202607','202608'], true))) {
        throw new RuntimeException('Unexpected fixture request.');
    }
    $codes = $lookup ? [$body['AcctCode']] : $body['accounts'];
    $items = [];
    foreach ($codes as $code) {
        [$number, $name, $status, $kwh] = match ($code) {
            '03-0152-0325' => ['AX2019030000136','Fixture Aborlan','ok',$month === '202607' ? '1407.00' : '70.00'],
            '70-0550-0750' => ['RX2018780000305','Fixture Cagayancillo','ok',$month === '202607' ? '40.00' : '8.00'],
            'HTTP-MISSING' => [null,null,'account_not_found',null],
            'HTTP-ZERO' => ['00009','Fixture Zero','ok','0.000'],
            'HTTP-EXTRA' => ['00011','Fixture Extra','ok','2.00'],
            'HTTP-NO-BILL' => ['00010','Fixture No Bill','no_billing_record',null],
            default => [null,null,'no_active_account',null],
        };
        $item = ['AcctCode'=>$code,'AcctNo'=>$number,'AccountName'=>$name,'status'=>$lookup && $number !== null ? 'found' : $status];
        if (!$lookup) {
            $item['KWH'] = $kwh;
        }
        $items[] = $item;
    }
    $payload = $lookup ? ['success'=>true,'data'=>$items[0]] : ['success'=>true,'ReportMonth'=>$month,'data'=>$items];
    return coop_https_response(200, json_encode($payload, JSON_THROW_ON_ERROR));
}
