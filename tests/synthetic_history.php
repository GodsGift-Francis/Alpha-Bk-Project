<?php
require __DIR__.'/../includes/synthetic-history.php';
$data=['dataset_type'=>'synthetic_banking_test_data','currency'=>'USD','opening_balance_cents'=>10000,'closing_balance_cents'=>10500,'transaction_count'=>1,'transactions'=>[['transaction_id'=>'S1','reference'=>'R1','date'=>'2001-01-01','type'=>'deposit','direction'=>'credit','amount_cents'=>500,'currency'=>'USD','counterparty'=>'Fictional company','description'=>'Example','running_balance_cents'=>10500]]];
$valid=validateSyntheticHistory(json_encode($data));
if (!$valid['transactions'][0]['is_synthetic']) throw new Exception('Missing marker');
$cases=[];
$bad=$data; $bad['transactions'][0]['running_balance_cents']=10501; $cases[]=$bad;
$bad=$data; $bad['closing_balance_cents']=10501; $cases[]=$bad;
$bad=$data; $bad['transactions'][0]['date']='2000-01-01'; $cases[]=$bad;
$bad=$data; $bad['transactions'][0]['date']='2026-02-30'; $cases[]=$bad;
$bad=$data; $bad['transactions'][0]['amount_cents']=5.5; $cases[]=$bad;
$bad=$data; $bad['transactions'][0]['direction']='debit'; $cases[]=$bad;
$bad=$data; $bad['transactions'][]=$bad['transactions'][0]; $cases[]=$bad;
foreach ($cases as $case) { try { validateSyntheticHistory(json_encode($case)); } catch (InvalidArgumentException $e) { continue; } throw new Exception('Invalid input accepted'); }
echo 'PASS: valid data and seven invalid dataset cases'.PHP_EOL;
foreach ([0,10000,700000000] as $closing) {
    $generated=generateSyntheticHistory($closing,500,'2001-01-01',gmdate('Y-m-d'));
    if ($generated['closing_balance_cents']!==$closing || count($generated['transactions'])!==500) throw new Exception('Generator did not reconcile');
}
echo 'PASS: generated datasets reconcile to zero, small and large balances'.PHP_EOL;
if (isset($argv[1])) { $supplied=validateSyntheticHistory(file_get_contents($argv[1])); echo 'PASS: supplied dataset, '.count($supplied['transactions']).' transactions, closing USD '.syntheticMoney($supplied['closing_balance_cents']).PHP_EOL; }

