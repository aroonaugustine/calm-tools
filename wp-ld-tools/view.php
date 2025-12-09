<?php
/**
 * CALM Web View — v2.6.1-i18n-courses
 * Public “View in browser” renderer for mailer links.
 *
 * - Renders live progress from LearnDash
 * - Language switch: ?lang=en|zh|id|vi|si
 * - Translates known course titles (EN→ZH/ID/VI/SI)
 * - Optional whitelist: set $COURSE_WHITELIST = [] to show all enrollments
 *
 * Examples:
 *   /wp-mailer/view.php?login=aa&lang=en
 *   /wp-mailer/view.php?login=aa&lang=zh
 *   /wp-mailer/view.php?login=aa&lang=id
 *   /wp-mailer/view.php?login=aa&lang=vi
 *   /wp-mailer/view.php?login=aa&lang=si
 *
 * Identity:
 *   - login=username
 *   - OR _se=base64url(user_email)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) define('WP_DISABLE_FATAL_ERROR_HANDLER', true);

require('/var/www/html/wp-load.php'); // adjust if needed

/* -----------------------------
   CONFIG
------------------------------ */

// To restrict to specific courses, list IDs here.
// To show ALL enrollments, set [].
$COURSE_WHITELIST = [2871,4216,2869,3027]; // or []

/* -----------------------------
   I18N: Course title translations
   (keyed by normalized English title)
------------------------------ */

$COURSE_TITLE_I18N = [
  // "Pre-Arrival and Essential Information"
  'pre-arrival and essential information' => [
    'id' => 'Informasi Pra-Kedatangan dan Informasi Penting',
    'zh' => '抵达前和基本信息',
    'si' => 'පැමිණීමට පෙර සහ අත්‍යවශ්‍ය තොරතුරු',
    'vi' => 'Thông tin trước khi đến và thông tin cần thiết',
  ],
  // "Port City BPO Induction"
  'port city bpo induction' => [
    'id' => 'Induksi Port City BPO',
    'zh' => 'Port City BPO 入职培训',
    'si' => 'Port City BPO ප්‍රේරණය',
    'vi' => 'Cảm ứng Port City BPO',
  ],
  // "Anti-Bribery and Anti-Corruption"
  'anti-bribery and anti-corruption' => [
    'id' => 'Anti-Suap dan Anti-Korupsi',
    'zh' => '反贿赂和反腐败',
    'si' => 'අල්ලස් විරෝධී සහ දූෂණ විරෝධී',
    'vi' => 'Chống hối lộ và chống tham nhũng',
  ],
  // "Prevention of Sexual Harassment"
  'prevention of sexual harassment' => [
    'id' => 'Pencegahan Pelecehan Seksual',
    'zh' => '防止性骚扰',
    'si' => 'ලිංගික හිරිහැර වැළැක්වීම',
    'vi' => 'Phòng ngừa quấy rối tình dục',
  ],
];

/* -----------------------------
   I18N: Static strings per language
------------------------------ */

$L = [
  'en' => [
    'title'         => 'CALM – Your Course Progress',
    'dear'          => 'Dear',
    'completeLead'  => 'Fantastic news — you’ve completed all your assigned compliance courses on the CALM Platform!',
    'incompleteLead'=> 'We’d like to share your current progress on the CALM Platform (Compliance And Learning Management). Completing these courses is mandatory to stay aligned with company standards.',
    'loginEmail'    => 'Login email',
    'resetNote'     => 'If you need to reset your password, please use this link:',
    'courseHeader'  => 'Your Course Progress',
    'footerHint'    => 'Please log in and complete any outstanding modules at your earliest convenience. The platform includes training modules, policy documents, and progress tracking to support your development.',
    'links'         => 'Helpful Links',
    'qs'            => 'Quick Start Guide',
    'qsGo'          => 'Get Started',
    'changePwd'     => 'Change Password',
    'forgotPwd'     => 'Password Reset',
    'needHelp'      => 'If you require any assistance:',
    'support'       => 'Contact Support',
    'helpCenter'    => 'Help Center',
    'team'          => 'Warm regards,',
    'teamName'      => 'The CALM Team',
  ],
  'zh' => [
    'title'         => 'CALM – 您的课程进度',
    'dear'          => '亲爱的',
    'completeLead'  => '好消息——您已在CALM平台完成所有指定合规课程！',
    'incompleteLead'=> '现向您通报在CALM平台（合规与学习管理平台）的当前进度。为确保符合公司标准，您必须完成这些课程。',
    'loginEmail'    => '登录邮箱',
    'resetNote'     => '如需重置密码，请使用此链接：',
    'courseHeader'  => '课程进度',
    'footerHint'    => '请尽快登录平台完成未完成的模块。平台包含培训模块、政策文档及进度跟踪功能，助您顺利完成学习。',
    'links'         => '实用链接',
    'qs'            => '快速入门指南',
    'qsGo'          => '立即开始',
    'changePwd'     => '修改密码',
    'forgotPwd'     => '密码重置',
    'needHelp'      => '如需协助：',
    'support'       => '联系支持',
    'helpCenter'    => '帮助中心',
    'team'          => '此致',
    'teamName'      => 'CALM团队',
  ],
  'id' => [
    'title'         => 'CALM – Kemajuan Kursus Anda',
    'dear'          => 'Yth.',
    'completeLead'  => 'Berita bagus — Anda telah menyelesaikan semua kursus kepatuhan yang ditugaskan di Platform CALM!',
    'incompleteLead'=> 'Kami ingin berbagi kemajuan Anda saat ini di Platform CALM (Compliance And Learning Management). Menyelesaikan kursus-kursus ini wajib untuk tetap sesuai standar perusahaan.',
    'loginEmail'    => 'Alamat email login',
    'resetNote'     => 'Jika Anda perlu mereset kata sandi, silakan gunakan tautan ini:',
    'courseHeader'  => 'Progres Kursus Anda',
    'footerHint'    => 'Silakan login dan selesaikan modul yang belum selesai sesegera mungkin. Platform ini mencakup modul pelatihan, dokumen kebijakan, dan pelacakan progres.',
    'links'         => 'Tautan Berguna',
    'qs'            => 'Panduan Cepat',
    'qsGo'          => 'Mulai Sekarang',
    'changePwd'     => 'Ubah Kata Sandi',
    'forgotPwd'     => 'Reset Kata Sandi',
    'needHelp'      => 'Jika Anda memerlukan bantuan:',
    'support'       => 'Hubungi Dukungan',
    'helpCenter'    => 'Pusat Bantuan',
    'team'          => 'Salam hangat,',
    'teamName'      => 'Tim CALM',
  ],
  'vi' => [
    'title'         => 'CALM – Tiến độ học tập của bạn',
    'dear'          => 'Kính gửi',
    'completeLead'  => 'Tin vui – Bạn đã hoàn thành tất cả các khóa học tuân thủ được giao trên nền tảng CALM!',
    'incompleteLead'=> 'Chúng tôi muốn chia sẻ tiến độ học tập hiện tại của bạn trên nền tảng CALM (Tuân thủ và Quản lý Học tập). Việc hoàn thành các khóa học này là bắt buộc để đảm bảo tuân thủ tiêu chuẩn.',
    'loginEmail'    => 'Địa chỉ email đăng nhập',
    'resetNote'     => 'Nếu bạn cần đặt lại mật khẩu, vui lòng sử dụng liên kết này:',
    'courseHeader'  => 'Tiến độ học tập của bạn',
    'footerHint'    => 'Vui lòng đăng nhập và hoàn thành các mô-đun còn thiếu sớm nhất có thể. Nền tảng có mô-đun đào tạo, tài liệu chính sách và theo dõi tiến độ.',
    'links'         => 'Liên kết hữu ích',
    'qs'            => 'Hướng dẫn bắt đầu nhanh',
    'qsGo'          => 'Bắt đầu',
    'changePwd'     => 'Thay đổi mật khẩu',
    'forgotPwd'     => 'Đặt lại mật khẩu',
    'needHelp'      => 'Nếu bạn cần hỗ trợ:',
    'support'       => 'Liên hệ hỗ trợ',
    'helpCenter'    => 'Trung tâm hỗ trợ',
    'team'          => 'Thân ái,',
    'teamName'      => 'Đội ngũ CALM',
  ],
  'si' => [
    'title'         => 'CALM – ඔබේ පාඨමාලා ප්‍රගතිය',
    'dear'          => 'හිතවත්',
    'completeLead'  => 'අපූරු පුවතක් — ඔබ CALM වේදිකාවේ ඔබට පවරා ඇති සියලුම අනුකූලතා පාඨමාලා සම්පූර්ණ කර ඇත!',
    'incompleteLead'=> 'CALM වේදිකාවේ (අනුකූලතාවය සහ ඉගෙනුම් කළමනාකරණය) ඔබගේ වත්මන් ප්‍රගතිය බෙදා ගැනීමට අපි කැමැත්තෙමු. සමාගම් ප්‍රමිතීන්ට අනුකූලව සිටීමට මෙම පාඨමාලා සම්පූර්ණ කිරීම අනිවාර්ය වේ.',
    'loginEmail'    => 'පිවිසුම් විද්‍යුත් තැපෑල',
    'resetNote'     => 'ඔබේ මුරපදය නැවත සැකසීමට අවශ්‍ය නම්, කරුණාකර මෙම සබැඳිය භාවිතා කරන්න:',
    'courseHeader'  => 'ඔබේ පාඨමාලා ප්‍රගතිය',
    'footerHint'    => 'කරුණාකර ඉක්මනින් ලොග් වී අපූරු මොඩියුල සම්පූර්ණ කරන්න. වේදිකාවේ පුහුණු මොඩියුල, ප්‍රතිපත්ති ලේඛන සහ ප්‍රගති ලුහුබැඳීම ඇතුළත් වේ.',
    'links'         => 'ප්‍රයෝජනවත් සබැඳි',
    'qs'            => 'ඉක්මන් ආරම්භක මාර්ගෝපදේශය',
    'qsGo'          => 'ආරම්භ කරන්න',
    'changePwd'     => 'මුරපදය වෙනස් කරන්න',
    'forgotPwd'     => 'මුරපදය යළි පිහිටුවීම',
    'needHelp'      => 'ඔබට සහාය අවශ්‍යනම්:',
    'support'       => 'සහාය අමතන්න',
    'helpCenter'    => 'උපකාරක මධ්‍යස්ථානය',
    'team'          => 'සුභ පැතුම්,',
    'teamName'      => 'CALM කණ්ඩායම',
  ],
];

/* -----------------------------
   Helpers
------------------------------ */

function lang(): string {
  $lang = strtolower(trim((string)($_GET['lang'] ?? 'en')));
  return in_array($lang, ['en','zh','id','vi','si'], true) ? $lang : 'en';
}
function norm_title(string $s): string {
  return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
}
function translate_course_title(string $title, string $lang, array $map): string {
  if ($lang === 'en') return $title;
  $key = norm_title($title);
  if (isset($map[$key][$lang]) && $map[$key][$lang] !== '') {
    return $map[$key][$lang];
  }
  return $title;
}
function ld_user_course_ids_full($user_id): array {
  $ids = [];
  if (function_exists('learndash_user_get_enrolled_courses')) $ids = (array) learndash_user_get_enrolled_courses($user_id);
  elseif (function_exists('ld_get_mycourses'))                $ids = (array) ld_get_mycourses($user_id);
  if (function_exists('learndash_get_users_group_ids') && function_exists('learndash_group_enrolled_courses')) {
    $group_ids = (array) learndash_get_users_group_ids($user_id);
    foreach ($group_ids as $gid) {
      $ids = array_merge($ids, (array) learndash_group_enrolled_courses($gid));
    }
  }
  $ids = array_values(array_unique(array_map('intval',$ids)));
  sort($ids, SORT_NUMERIC);
  return $ids;
}
function ld_course_is_completed_v($user_id,$course_id): bool {
  if (function_exists('learndash_course_completed') && learndash_course_completed($user_id,$course_id)) return true;
  $meta = get_user_meta($user_id, 'course_completed_'.$course_id, true);
  if (!empty($meta)) return true;
  if (function_exists('learndash_course_status')) {
    $status = learndash_course_status($course_id,$user_id);
    if (is_string($status) && stripos($status,'Completed')!==false) return true;
  }
  return false;
}
function ld_course_percent_v($user_id,$course_id): int {
  if (ld_course_is_completed_v($user_id,$course_id)) return 100;
  if (function_exists('learndash_course_progress')) {
    $p = learndash_course_progress(['user_id'=>$user_id,'course_id'=>$course_id,'array'=>true]);
    if (is_array($p) && isset($p['percentage'])) {
      $perc = is_numeric($p['percentage']) ? (float)$p['percentage'] : (float) str_replace('%','',(string)$p['percentage']);
      return (int) max(0,min(100,round($perc)));
    }
    if (is_array($p) && isset($p['completed'],$p['total']) && (int)$p['total']>0) {
      $perc = ((int)$p['completed']/(int)$p['total']) * 100.0;
      return (int) max(0,min(100,round($perc)));
    }
  }
  return 0;
}

/* -----------------------------
   Identify the user
------------------------------ */

$user = null;
if (!empty($_GET['login'])) {
  $user = get_user_by('login', (string)$_GET['login']);
} elseif (!empty($_GET['_se'])) {
  $raw = (string)$_GET['_se']; // base64url email
  $b64 = strtr($raw, '-_', '+/');
  $pad = strlen($b64) % 4;
  if ($pad) $b64 .= str_repeat('=', 4 - $pad);
  $email = base64_decode($b64);
  if ($email) $user = get_user_by('email', $email);
}

if (!$user) {
  http_response_code(404);
  echo "User not found.";
  exit;
}

/* -----------------------------
   Build course universe
------------------------------ */

$user_ids = ld_user_course_ids_full($user->ID);
$consider_ids = !empty($COURSE_WHITELIST)
  ? array_values(array_intersect($user_ids, $COURSE_WHITELIST))
  : $user_ids;

$all_course_ids = !empty($COURSE_WHITELIST) ? $COURSE_WHITELIST : $consider_ids;
$all_course_ids = array_values(array_unique(array_map('intval',$all_course_ids)));
sort($all_course_ids, SORT_NUMERIC);

$course_titles = [];
foreach ($all_course_ids as $cid) {
  $t = get_the_title($cid);
  $course_titles[$cid] = preg_replace('/\s+/', ' ', trim($t ?: ("Course {$cid}")));
}

/* -----------------------------
   Compute progress & build list (with translated titles)
------------------------------ */

$lang = lang();
$any_incomplete = false;
$list_items = '';

foreach ($consider_ids as $cid) {
  $title_en   = $course_titles[$cid] ?? ("Course {$cid}");
  $title_i18n = translate_course_title($title_en, $lang, $COURSE_TITLE_I18N);
  $pct        = ld_course_percent_v($user->ID, $cid);
  if ($pct < 100) $any_incomplete = true;
  $list_items .= '<li>'.esc_html($title_i18n).' – '.intval($pct)."%</li>\n";
}
$course_list_html = '<ul style="padding-left:20px; margin-top:8px;">'.$list_items.'</ul>';

/* -----------------------------
   Page content
------------------------------ */

$first = (string) get_user_meta($user->ID, 'first_name', true);
if ($first === '') $first = $user->display_name ?: $user->user_login;
$email = (string)$user->user_email;

$Lx = $L[$lang] ?? $L['en'];
$RESET_LINK = 'https://portcalm.portcitybpo.lk/en/password-reset/';

?><!doctype html>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<title><?=htmlspecialchars($Lx['title'])?></title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:28px;max-width:880px}
  .card{border:1px solid #ddd;border-radius:12px;padding:26px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  h1{font-size:22px;margin:0 0 10px;color:#004b6b}
  h2{font-size:18px;margin:18px 0 8px}
  ul{margin:8px 0 0 18px}
  .brand{text-align:center;margin-bottom:18px}
  .brand img{max-width:100px;border-radius:12px}
  .muted{color:#666}
  .hint{background:#f0f8ff;border-left:4px solid #0077b6;padding:12px;margin:14px 0}
  .footer{margin-top:16px}
</style>
<div class="card">
  <div class="brand">
    <img src="https://portcalm.portcitybpo.lk/wp-content/uploads/2025/03/Port-City-BPO-logo-hd-1.webp" alt="Port City BPO">
  </div>
  <h1><?=htmlspecialchars($Lx['title'])?></h1>
  <p><?=htmlspecialchars($Lx['dear'])?> <strong><?=htmlspecialchars($first)?></strong>,</p>

  <?php if ($any_incomplete): ?>
    <p><?=htmlspecialchars($Lx['incompleteLead'])?></p>
    <div class="hint">
      <div><strong><?=htmlspecialchars($Lx['loginEmail'])?>:</strong> <?=htmlspecialchars($email)?></div>
      <div><?=htmlspecialchars($Lx['resetNote'])?> <a href="<?=$RESET_LINK?>" target="_blank" rel="noopener"><?=$RESET_LINK?></a></div>
    </div>
  <?php else: ?>
    <p><?=htmlspecialchars($Lx['completeLead'])?></p>
  <?php endif; ?>

  <h2><?=htmlspecialchars($Lx['courseHeader'])?></h2>
  <?=$course_list_html?>

  <?php if ($any_incomplete): ?>
    <p class="footer"><?=htmlspecialchars($Lx['footerHint'])?></p>
  <?php endif; ?>

  <h2><?=htmlspecialchars($Lx['links'])?></h2>
  <ul>
    <li><?=htmlspecialchars($Lx['qs'])?>: <a href="https://portcalm.portcitybpo.lk/get-started/" target="_blank" rel="noopener"><?=htmlspecialchars($Lx['qsGo'])?></a></li>
    <li><?=htmlspecialchars($Lx['changePwd'])?>: <a href="https://portcalm.portcitybpo.lk/account/password/" target="_blank" rel="noopener"><?=htmlspecialchars($Lx['changePwd'])?></a></li>
    <li><?=htmlspecialchars($Lx['forgotPwd'])?>: <a href="<?=$RESET_LINK?>" target="_blank" rel="noopener"><?=$RESET_LINK?></a></li>
  </ul>

  <p class="muted"><?=htmlspecialchars($Lx['needHelp'])?></p>
  <ul>
    <li><strong><?=htmlspecialchars($Lx['support'])?>:</strong> <a href="mailto:calm@portcitybpo.lk">calm@portcitybpo.lk</a></li>
    <li><strong><?=htmlspecialchars($Lx['helpCenter'])?>:</strong> <a href="https://portcalm.portcitybpo.lk/faqs/" target="_blank" rel="noopener">CALM FAQs</a></li>
  </ul>

  <p><?=htmlspecialchars($Lx['team'])?><br><strong><?=htmlspecialchars($Lx['teamName'])?></strong></p>
</div>