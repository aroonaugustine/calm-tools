<?php
/**
 * Batch Runner — v15.09.0002.0001
 * Splits a CSV into chunks and runs the worker sequentially to avoid timeouts/load.
 *
 * Args:
 *   --csv=/path/input.csv
 *   --matched=/path/merged_matched.csv
 *   --unmatched=/path/merged_unmatched.csv
 *   --batch-size=500
 *   --batch-delay-ms=250
 *   --worker=/srv/admin-tools/wp-resigned-processor/resigned_worker.php
 *   --pass=BASE64_ENCODED_WORKER_FLAG   (repeatable; e.g. --pass=base64('--match=strict'))
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$argv_map=[];
foreach ($argv ?? [] as $a) {
  if (preg_match('/^--([^=]+)=(.*)$/',$a,$m)) $argv_map[$m[1]]=$m[2];
}
$csv     = (string)($argv_map['csv'] ?? '');
$outM    = (string)($argv_map['matched'] ?? '');
$outU    = (string)($argv_map['unmatched'] ?? '');
$bs      = max(1, (int)($argv_map['batch-size'] ?? 500));
$delayMs = max(0, (int)($argv_map['batch-delay-ms'] ?? 0));
$worker  = (string)($argv_map['worker'] ?? '');

$passes=[];
foreach ($argv ?? [] as $a) {
  if (preg_match('/^--pass=(.*)$/',$a,$m)) $passes[] = base64_decode($m[1], true) ?: '';
}
$passes = array_values(array_filter($passes, fn($x)=>$x!==''));

if ($csv==='' || !is_file($csv)) { fwrite(STDERR,"❌ CSV missing: $csv\n"); exit(2); }
if ($worker==='' || !is_file($worker)) { fwrite(STDERR,"❌ Worker missing: $worker\n"); exit(2); }

$dir = dirname($outM) ?: getcwd();
@mkdir($dir,0755,true);
$tmp = $dir . '/chunks_' . basename($outM,'.csv') . '_' . bin2hex(random_bytes(2));
@mkdir($tmp,0755,true);

echo "🧩 Batching CSV: {$csv}\n";
echo "   Batch size: {$bs}, delay: {$delayMs}ms, chunks dir: {$tmp}\n";

// Split CSV
$fh = fopen($csv,'r'); if(!$fh){ fwrite(STDERR,"❌ Cannot open CSV\n"); exit(2); }
$header = fgetcsv($fh); if($header===false){ fwrite(STDERR,"❌ Empty CSV\n"); exit(2); }

$rows=0; $chunk=0; $files=[];
$w = null;

while (($row = fgetcsv($fh)) !== false) {
  if ($rows % $bs === 0) {
    if ($w) fclose($w);
    $chunk++;
    $path = sprintf("%s/chunk_%03d.csv",$tmp,$chunk);
    $w = fopen($path,'w');
    fputcsv($w,$header);
    $files[]=$path;
  }
  fputcsv($w,$row);
  $rows++;
}
if ($w) fclose($w);
fclose($fh);

if (!$files){
  echo "ℹ️ Nothing to process.\n";
  file_put_contents($outM, "user_login,user_email,actions\n");
  file_put_contents($outU, "reason,user_email,NIC,Passport\n");
  @touch(dirname($outM).'/DONE');
  exit(0);
}

echo "📦 Created ".count($files)." chunk(s), total rows={$rows}\n";

// Prepare merged outputs with headers
$fm = fopen($outM,'w'); $fu = fopen($outU,'w');
fputcsv($fm, ['user_login','user_email','actions',
  'employee_number','employment_status','passport_no','passport_exp','nationality','expat_local',
  'visa_issue_date','visa_exp','nic_no','date_of_birth','mobile_no','gender','company','division',
  'designation','home_address','perm_address','office_address','emergency_contact_email',
  'emergency_contact_phone','emergency_contact_who','join_date','resign_date','profile_picture',
  'removed_groups','unenrolled_courses','progress_reset'
]);
fputcsv($fu, ['reason','user_email','NIC','Passport']);

// Run each chunk synchronously
$php = PHP_BINARY ?: '/usr/bin/php';
$done=0; $unm=0; $mat=0;

foreach ($files as $i=>$chunkPath) {
  $mTemp = $chunkPath.'.matched.csv';
  $uTemp = $chunkPath.'.unmatched.csv';

  $args = [];
  $args[] = '--csv='.$chunkPath;
  $args[] = '--matched='.$mTemp;
  $args[] = '--unmatched='.$uTemp;
  foreach ($passes as $p) $args[] = $p;

  $cmd = sprintf('%s %s %s 2>&1',
    escapeshellarg($php),
    escapeshellarg($worker),
    implode(' ', array_map('escapeshellarg',$args))
  );
  echo "▶️  Chunk ".($i+1)."/".count($files)." — $cmd\n";
  passthru($cmd, $rc);

  if (is_file($mTemp)) {
    $fh = fopen($mTemp,'r'); if($fh){
      $hdr = fgetcsv($fh);
      while(($r=fgetcsv($fh))!==false){ fputcsv($fm,$r); $mat++; }
      fclose($fh);
    }
    @unlink($mTemp);
  }
  if (is_file($uTemp)) {
    $fh = fopen($uTemp,'r'); if($fh){
      $hdr = fgetcsv($fh);
      while(($r=fgetcsv($fh))!==false){ fputcsv($fu,$r); $unm++; }
      fclose($fh);
    }
    @unlink($uTemp);
  }

  $done++;
  echo "✅ Finished chunk {$done}/".count($files)." (matched+unmatched so far: ".($mat+$unm).")\n";
  if ($delayMs>0 && $i < count($files)-1) usleep($delayMs*1000);
}

fclose($fm); fclose($fu);
foreach ($files as $p) @unlink($p);
@rmdir($tmp);
@touch(dirname($outM).'/DONE');
echo "🏁 Batching complete. Chunks processed={$done}. Merged outputs created.\n";
