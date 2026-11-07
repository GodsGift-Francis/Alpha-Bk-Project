<?php
require_once __DIR__ . '/money.php';
function validateSyntheticHistory(string $json): array {
    if (strlen($json) > 2000000) throw new InvalidArgumentException('Dataset exceeds 2 MB.');
    try { $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new InvalidArgumentException('Upload a valid JSON dataset.'); }
    if (!is_array($data) || ($data['dataset_type'] ?? '') !== 'synthetic_banking_test_data' || ($data['currency'] ?? '') !== 'USD') throw new InvalidArgumentException('A synthetic USD dataset is required.');
    $rows = $data['transactions'] ?? null;
    if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 1000) throw new InvalidArgumentException('Use between 1 and 1,000 transactions.');
    foreach (['opening_balance_cents', 'closing_balance_cents'] as $key) {
        if (!is_int($data[$key] ?? null) || abs($data[$key]) > 999999999999999) throw new InvalidArgumentException('Invalid balance.');
    }
    $balance = $data['opening_balance_cents']; $previous = '2001-01-01'; $ids = []; $references = [];
    foreach ($rows as &$row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid transaction.');
        foreach (['transaction_id', 'reference', 'counterparty', 'description', 'date'] as $key) {
            if (!is_string($row[$key] ?? null) || trim($row[$key]) === '' || strlen($row[$key]) > 500) throw new InvalidArgumentException('Missing or oversized transaction details.');
        }
        $row['counterparty'] = trim((string)preg_replace('/^fictional(?:\s+|$)/iu', '', trim($row['counterparty'])));
        if ($row['counterparty'] === '') throw new InvalidArgumentException('Counterparty details cannot be empty.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $row['date']);
        if (!$date || $date->format('Y-m-d') !== $row['date'] || $row['date'] < $previous || $row['date'] > gmdate('Y-m-d')) throw new InvalidArgumentException('Dates must be chronological, between 2001 and today.');
        $previous = $row['date'];
        if (isset($ids[$row['transaction_id']]) || isset($references[$row['reference']])) throw new InvalidArgumentException('Transaction IDs and references must be unique.');
        $ids[$row['transaction_id']] = true; $references[$row['reference']] = true;
        if (!in_array($row['type'] ?? '', ['deposit','withdrawal','transfer'], true) || !in_array($row['direction'] ?? '', ['credit','debit'], true) || ($row['currency'] ?? '') !== 'USD') throw new InvalidArgumentException('Invalid transaction type, direction or currency.');
        if (($row['type'] === 'deposit' && $row['direction'] !== 'credit') || ($row['type'] === 'withdrawal' && $row['direction'] !== 'debit')) throw new InvalidArgumentException('Transaction direction does not match its type.');
        if (!is_int($row['amount_cents'] ?? null) || $row['amount_cents'] < 1 || $row['amount_cents'] > 999999999999999) throw new InvalidArgumentException('Amounts must be positive integer cents.');
        $balance += $row['direction'] === 'credit' ? $row['amount_cents'] : -$row['amount_cents'];
        if (abs($balance) > 999999999999999 || ($row['running_balance_cents'] ?? null) !== $balance) throw new InvalidArgumentException('A running balance does not reconcile.');
        $row['is_synthetic'] = true;
    }
    unset($row);
    if ($balance !== $data['closing_balance_cents'] || ($data['transaction_count'] ?? null) !== count($rows)) throw new InvalidArgumentException('Closing balance or transaction count does not reconcile.');
    $data['transactions'] = $rows; $data['is_synthetic'] = true;
    return $data;
}
function syntheticMoney(int $cents): string {
    return ($cents < 0 ? '-' : '') . number_format(intdiv(abs($cents), 100), 0, '.', ',') . '.' . str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}
function generateSyntheticHistory(int $closing, int $count, string $start, string $end): array {
    $from=DateTimeImmutable::createFromFormat('!Y-m-d',$start); $to=DateTimeImmutable::createFromFormat('!Y-m-d',$end);
    if (!$from || !$to || $from->format('Y-m-d')!==$start || $to->format('Y-m-d')!==$end || $start<'2001-01-01' || $end>gmdate('Y-m-d') || $start>$end || $count<1 || $count>1000) throw new InvalidArgumentException('Choose 1–1,000 transactions and valid dates between 2001 and today.');
    $batch='SYN-'.strtoupper(bin2hex(random_bytes(6))); $rows=[]; $net=0;
    $names=['Cedar Services','Harbor Supply','Maple Studio','Oak Retail'];
    for ($i=0;$i<$count;$i++) {
        $type=['deposit','withdrawal','transfer'][random_int(0,2)];
        $direction=$type==='deposit'?'credit':($type==='withdrawal'?'debit':(random_int(0,1)?'credit':'debit'));
        $amount=random_int(100,100000); $net+=$direction==='credit'?$amount:-$amount;
        $date=gmdate('Y-m-d',random_int($from->getTimestamp(),$to->getTimestamp()));
        $rows[]=['transaction_id'=>$batch.'-'.$i,'reference'=>$batch.'-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'date'=>$date,'type'=>$type,'direction'=>$direction,'amount_cents'=>$amount,'currency'=>'USD','counterparty'=>$names[random_int(0,count($names)-1)],'description'=>['deposit'=>'Account funding','withdrawal'=>'Account withdrawal','transfer'=>'Account transfer'][$type],'batch_id'=>$batch];
    }
    usort($rows,fn($a,$b)=>strcmp($a['date'],$b['date'])); $opening=$closing-$net; $running=$opening;
    foreach ($rows as &$row) { $running+=$row['direction']==='credit'?$row['amount_cents']:-$row['amount_cents']; $row['running_balance_cents']=$running; }
    unset($row);
    return validateSyntheticHistory(json_encode(['dataset_type'=>'synthetic_banking_test_data','currency'=>'USD','batch_id'=>$batch,'opening_balance_cents'=>$opening,'closing_balance_cents'=>$closing,'transaction_count'=>$count,'transactions'=>$rows],JSON_THROW_ON_ERROR));
}

