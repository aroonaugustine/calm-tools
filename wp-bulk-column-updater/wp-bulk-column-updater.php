<?php
/**
 * Worker: WP Bulk Column Updater — v15.09.0002.0001
 * - Correctly updates core fields incl. user_email
 * - Adds alias-aware header resolution for PK/SK
 * - Keeps NA normalization for NIC/Passport
 */
declare(strict_types=1);

if (!function_exists('wpbcu144_run')) {
function wpbcu144_run(array $cfg, string $run_dir): array {

  /* --------------------------------------------------------
   *  LOAD CONFIG
   * ------------------------------------------------------*/
  $csv_path  = $cfg['csv_path'];
  $primary   = $cfg['primary'];           // email_address | emp_no | passport_number | nic_number | username
  $secondary = $cfg['secondary'] ?? '';
  $limit     = max(0, (int)($cfg['limit'] ?? 0));
  $live      = !empty($cfg['live']);
  $mappings  = $cfg['mappings'] ?? [];

  $logPath   = $run_dir . '/log.ndjson';
  $sumPath   = $run_dir . '/summary.json';
  $logFh     = fopen($logPath, 'a');

  $writeLog = function(array $row) use($logFh){
    fwrite($logFh, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
  };

  $na_list = ['na','n.a.','n/a','not applicable','-','—','--','nil','none',''];
  $norm_ident = function($val) use($na_list){
    $v = trim((string)$val);
    $canon = mb_strtolower($v, 'UTF-8');
    return in_array($canon, $na_list, true) ? null : $v;
  };

  /* --------------------------------------------------------
   *  OPEN CSV
   * ------------------------------------------------------*/
  if (!is_file($csv_path)) {
    $writeLog(['level'=>'error','msg'=>'CSV not found','path'=>$csv_path]);
    fclose($logFh);
    file_put_contents($sumPath,json_encode(['ok'=>false,'error'=>'CSV not found']));
    return [$sumPath,$logPath];
  }
  if (($fh = fopen($csv_path, 'r')) === false) {
    $writeLog(['level'=>'error','msg'=>'Cannot open CSV']);
    fclose($logFh);
    file_put_contents($sumPath,json_encode(['ok'=>false,'error'=>'Cannot open CSV']));
    return [$sumPath,$logPath];
  }

  $headers = fgetcsv($fh) ?: [];
  $headers = array_map(fn($h)=>trim((string)$h), $headers);
  $H = array_flip($headers);

  /* --------------------------------------------------------
   *  NORMALIZE HEADERS
   * ------------------------------------------------------*/
  $norm = function(string $s): string {
    $s = preg_replace("/\r?\n+/", ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = trim($s, " \t\n\r\0\x0B\"");
    $s = strtolower($s);
    $s = str_replace(["\u{00A0}"], ' ', $s);
    return $s;
  };

  $norm_map = [];
  foreach ($headers as $h) { $norm_map[$norm($h)] = $h; }

  $aliases = [
    'email_address' => ['email_address','email','e-mail','e mail','user_email','mail'],
    'emp_no'        => ['emp no','employee no','employee number','employee#','emp#'],
    'passport_number' => ['passport number','passport','passport no','passport#'],
    'nic_number'      => ['nic number','nic','nic no','national id'],
    'username'        => ['username','user_login','login','userid','user id']
  ];

  $resolve_header_for_key = function(string $logical_key)
      use ($aliases, $norm_map, $norm): ?string {

    if ($logical_key === '') return null;
    $cands = $aliases[$logical_key] ?? [$logical_key];

    foreach ($cands as $label) {
      $w = $norm($label);
      if (isset($norm_map[$w])) return $norm_map[$w];
    }

    foreach ($cands as $label) {
      $w = $norm($label);
      foreach ($norm_map as $nh => $orig) {
        if (str_starts_with($nh, $w) || str_contains($nh, $w)) return $orig;
      }
    }
    return null;
  };

  $pk_header = $resolve_header_for_key($primary);
  $sk_header = $resolve_header_for_key($secondary);

  if (!$pk_header) {
    $writeLog([
      'level'=>'error',
      'msg'=>'Primary key column not found',
      'primary'=>$primary,
      'headers'=>$headers
    ]);
  }

  /* --------------------------------------------------------
   *  HELPERS
   * ------------------------------------------------------*/
  $val = function(array $row, ?string $col) use($H){
    if (!$col || !isset($H[$col])) return '';
    return $row[$H[$col]] ?? '';
  };

  $resolve_user = function(array $row)
      use($primary,$secondary,$pk_header,$sk_header,$val,$norm_ident){

    $pk_value = trim((string)$val($row, $pk_header));
    if ($pk_value === '') return [null, 'missing_pk'];

    if ($primary === 'passport_number' || $primary === 'nic_number') {
      $nv = $norm_ident($pk_value);
      if ($nv === null) return [null, 'normalized_pk_null'];
      $pk_value = $nv;
    }

    $get_by = function($key, $value){
      switch ($key) {
        case 'email_address': return get_user_by('email', $value) ?: null;
        case 'username':      return get_user_by('login', $value) ?: null;
        case 'emp_no':        $meta = 'employee_number'; break;
        case 'passport_number': $meta = 'passport_no'; break;
        case 'nic_number':      $meta = 'nic_no'; break;
        case 'full_name':       $meta = 'display_name'; break;
        default: return null;
      }

      $args = [
        'meta_key'   => $meta,
        'meta_value' => $value,
        'number'     => 1,
        'fields'     => 'all'
      ];
      $u = get_users($args);
      return $u ? $u[0] : null;
    };

    $u1 = $get_by($primary, $pk_value);
    if ($u1) return [$u1,'ok'];

    if ($secondary && $sk_header) {
      $sv = trim((string)$val($row, $sk_header));
      if ($secondary === 'passport_number' || $secondary === 'nic_number') {
        $sv = $norm_ident($sv);
      }
      if ($sv !== '' && $sv !== null) {
        $u2 = $get_by($secondary, $sv);
        if ($u2) return [$u2,'ok-secondary'];
      }
    }

    return [null,'not_found'];
  };

  /* --------------------------------------------------------
   *  PROCESS CSV
   * ------------------------------------------------------*/
  $rowN=0; $done=0; $updated=0; $skipped=0; $no_match=0; $no_pk=0;
  $started = time();

  $H_local = array_flip($headers);
  $getCell = function(array $row, string $header) use($H_local){
    return $row[$H_local[$header]] ?? '';
  };

  while (($row = fgetcsv($fh)) !== false) {
    $rowN++;
    if ($limit>0 && $done >= $limit) break;

    [$user, $why] = $resolve_user($row);
    if (!$user) {
      if ($why==='missing_pk') $no_pk++;
      else $no_match++;
      $skipped++;
      $writeLog(['row'=>$rowN+1,'status'=>'SKIP','why'=>$why]);
      continue;
    }

    $uid = (int)$user->ID;

    $core_updates = ['ID'=>$uid];
    $meta_updates = [];
    $password = null;

    foreach ($mappings as $map) {
      $target = $map['target'];
      $col    = $map['column'];
      $raw    = (string)$getCell($row, $col);

      if ($target === 'nic_no' || $target === 'passport_no') {
        $n = $norm_ident($raw);
        $raw = ($n===null ? '' : $n);
      }

      /* CORE user fields incl. FIXED user_email */
      switch ($target) {

        case 'display_name':
        case 'first_name':
        case 'last_name':
        case 'user_email':   // FIXED: user_email now updates wp_users correctly
          if ($raw !== '') $core_updates[$target] = $raw;
          break;

        case 'user_pass':
          if ($raw !== '') $password = $raw;
          break;

        default:
          if ($target !== '') {
            $meta_updates[$target] = $raw;
          }
      }
    }

    $changes = []; $did = false;

    if ($live) {

      if (count($core_updates) > 1) {
        $res = wp_update_user($core_updates);
        if (!is_wp_error($res)) { $changes['core']=$core_updates; $did=true; }
      }

      if ($password !== null) {
        wp_set_password($password,$uid);
        $changes['password']=true;
        $did=true;
      }

      foreach ($meta_updates as $mk=>$mv) {
        update_user_meta($uid, $mk, $mv);
        $did = true;
      }
      if (!empty($meta_updates)) $changes['meta']=$meta_updates;

    } else {
      if (count($core_updates) > 1) $changes['core']=$core_updates;
      if ($password !== null)     $changes['password']=true;
      if (!empty($meta_updates))  $changes['meta']=$meta_updates;
    }

    $done++;
    $updated += (!$live || $did) ? 1 : 0;

    $writeLog([
      'row'=>$rowN+1,
      'status'=>$live?($did?'OK':'NOCHANGE'):'OK-DRYRUN',
      'user_id'=>$uid,
      'changes'=>$changes
    ]);
  }

  fclose($fh);

  $summary = [
    'ok'=>true,
    'version'=>'1.4.4',
    'rows_processed'=>$done,
    'updated_count'=>$updated,
    'skipped'=>$skipped,
    'no_pk'=>$no_pk,
    'no_match'=>$no_match,
    'duration_sec'=>time()-$started,
    'live'=>$live,
    'at'=>date('c'),
    'pk_header_used'=>$pk_header,
    'sk_header_used'=>$sk_header
  ];

  file_put_contents($sumPath, json_encode($summary, JSON_PRETTY_PRINT));
  fclose($logFh);

  return [$sumPath,$logPath];
}}
