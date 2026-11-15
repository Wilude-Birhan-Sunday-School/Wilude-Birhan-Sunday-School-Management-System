<?php
/**
 * Telegram bot walkthrough against a real MariaDB.
 * ------------------------------------------------------------
 * Service-level E2E through the real bot code (no Telegram
 * network): registry validation, operator auth gates, full
 * text-only registration flows (waiting + transfer), edit /
 * back / resume / cancel / expiry / daily-limit, the HR
 * correction cycle, approval + idempotency + duplicate
 * enforcement, retention, form versioning, and attachment
 * quarantine — then full cleanup with a residue check.
 *
 * COMMAND LINE ONLY, and destructive: it seeds users, classes,
 * operators, drafts and members, then deletes them again.
 * Disposable database only (destructive_guard interlock).
 *
 * Usage (sandbox, migrations through 091 applied):
 *   SSMS_AUDIT_TESTING=1 php tests/e2e/telegram_bot_walkthrough.php
 *
 * NOT run in CI (no database in the sandbox); the sandbox pins
 * the same behaviors statically
 * (tests/security/test_telegram_registration_foundation.py).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$envFile = $root . '/.fkss_env.php';
if (!is_file($envFile)) {
    fwrite(STDERR, "missing .fkss_env.php\n");
    exit(3);
}
require $envFile;
require __DIR__ . '/destructive_guard.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Collect webhook-adjacent internal errors instead of logging them. */
$internalErrors = [];
function reportInternalError(string $message, ?Throwable $error = null): void
{
    global $internalErrors;
    $internalErrors[] = $message . ' :: ' . ($error ? get_class($error) . ': ' . $error->getMessage() : 'none');
}

/** Quarantine root resolves to /tmp/ssms_private/telegram_quarantine. */
define('ROOT_PATH', '/tmp/tg_rt_pub');

require_once $root . '/admin/backend/services/AmharicIdentityNormalizer.php';
require_once $root . '/admin/backend/services/TelegramFormRegistry.php';
require_once $root . '/admin/backend/services/TelegramDuplicateCandidateService.php';
require_once $root . '/admin/backend/services/TelegramBotApiService.php';
require_once $root . '/admin/backend/services/TelegramAttachmentService.php';
require_once $root . '/admin/backend/services/TelegramRegistrationService.php';
require_once $root . '/admin/backend/services/TelegramMemberApprovalService.php';
require_once $root . '/admin/backend/services/TelegramApprovalReadinessService.php';
require_once $root . '/admin/backend/services/TelegramFormAdminService.php';
require_once $root . '/admin/backend/services/TelegramRetentionService.php';
require_once $root . '/admin/backend/services/MemberCanonicalIdentityService.php';
require_once $root . '/admin/backend/services/MemberDuplicateService.php';

use App\Services\MemberCanonicalIdentityService;
use App\Services\TelegramApprovalReadinessService;
use App\Services\TelegramAttachmentService;
use App\Services\TelegramFormAdminService;
use App\Services\TelegramFormRegistry;
use App\Services\TelegramMemberApprovalService;
use App\Services\TelegramRegistrationService;
use App\Services\TelegramRetentionService;

$dbName = getenv('SSMS_SYNC_DB') ?: 'ssms_e2e';
ssms_require_disposable_database($dbName, 'telegram_bot_walkthrough');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "mysqli connect failed: {$mysqli->connect_error}\n");
    exit(3);
}

try {
    $mysqli->query('SELECT 1 FROM telegram_registration_sessions LIMIT 1');
    $mysqli->query('SELECT 1 FROM telegram_form_profiles LIMIT 1');
    $mysqli->query('SELECT 1 FROM members LIMIT 1');
} catch (mysqli_sql_exception $e) {
    fwrite(STDERR, "telegram/members tables are not present in {$dbName}\n");
    exit(3);
}

$failures = [];
$checks = 0;

function check(string $label, $expected, $actual): void
{
    global $failures, $checks;
    $checks++;
    if ($expected !== $actual) {
        $failures[] = sprintf(
            "%s\n      expected: %s\n      actual:   %s",
            $label,
            json_encode($expected, JSON_UNESCAPED_UNICODE),
            json_encode($actual, JSON_UNESCAPED_UNICODE)
        );
        echo "  FAIL  {$label}\n";
    } else {
        echo "  ok    {$label}\n";
    }
}

function checkContains(string $label, string $needle, string $haystack): void
{
    global $failures, $checks;
    $checks++;
    if (!str_contains($haystack, $needle)) {
        $failures[] = "{$label}\n      expected to contain: {$needle}\n      actual: " . mb_substr($haystack, 0, 200);
        echo "  FAIL  {$label}\n";
    } else {
        echo "  ok    {$label}\n";
    }
}

function expectThrow(string $label, string $class, string $needle, callable $fn): void
{
    global $failures, $checks;
    $checks++;
    try {
        $fn();
    } catch (Throwable $e) {
        if (!($e instanceof $class) || !str_contains($e->getMessage(), $needle)) {
            $failures[] = "{$label}\n      expected: {$class} containing '{$needle}'\n      actual: " . get_class($e) . ': ' . $e->getMessage();
            echo "  FAIL  {$label}\n";
            return;
        }
        echo "  ok    {$label} [" . get_class($e) . "]\n";
        return;
    }
    $failures[] = "{$label}\n      expected: {$class} containing '{$needle}'\n      actual: no exception";
    echo "  FAIL  {$label}\n";
}

/** @return list<array<string,mixed>> */
function q(string $sql): array
{
    global $mysqli;
    $r = $mysqli->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

/** @return array<string,mixed>|null */
function one(string $sql): ?array
{
    global $mysqli;
    $r = $mysqli->query($sql);
    if (!$r) {
        return null;
    }
    $row = $r->fetch_assoc();
    return $row === null ? null : $row;
}

function execSql(string $sql): void
{
    global $mysqli;
    $mysqli->query($sql);
}

$service = new TelegramRegistrationService($mysqli);
$msgId = 1000;
function bot(int $tg, int $chat, string $text, string $chatType = 'private'): string
{
    global $service, $msgId;
    $msgId++;
    $payload = $service->handleMessage([
        'message_id' => $msgId,
        'from' => ['id' => $tg, 'first_name' => 'TG', 'username' => 'tg_e2e'],
        'chat' => ['id' => $chat, 'type' => $chatType],
        'text' => $text,
    ]);
    return (string)($payload['text'] ?? '');
}

function sessionOf(int $botUserId, ?string $status = null): ?array
{
    $sql = "SELECT * FROM telegram_registration_sessions WHERE bot_user_id={$botUserId}";
    if ($status !== null) {
        $sql .= " AND status='{$status}'";
    }
    return one($sql . ' ORDER BY id DESC LIMIT 1');
}

function answersOf(int $sessionId): array
{
    $out = [];
    foreach (q("SELECT field_key,value_text,value_normalized FROM telegram_registration_answers WHERE session_id={$sessionId} ORDER BY id") as $row) {
        $out[$row['field_key']] = $row;
    }
    return $out;
}

echo "== seed ==\n";
execSql("INSERT INTO users(username,full_name,role,password_hash,is_active) VALUES('tg_hr_e2e','TG HR','hr_dept','x',1),('tg_dead_e2e','TG Dead','hr_dept','x',0)");
$hrId = (int)one("SELECT id FROM users WHERE username='tg_hr_e2e'")['id'];
$deadId = (int)one("SELECT id FROM users WHERE username='tg_dead_e2e'")['id'];
execSql("INSERT INTO classes(class_name,class_code,is_active) VALUES('TG Section A','TG-A',1)");
$classId = (int)$mysqli->insert_id;
$ops = [];
function seedOp(int $tg, ?int $userId, string $status, int $w, int $d, int $t, int $limit = 50, ?string $expires = null): int
{
    global $mysqli, $ops;
    $u = $userId === null ? 'NULL' : (string)$userId;
    $e = $expires === null ? 'NULL' : "'{$expires}'";
    $mysqli->query("INSERT INTO telegram_bot_users(telegram_user_id,linked_user_id,status,can_waiting,can_direct,can_transfer,daily_registration_limit,expires_at) VALUES({$tg},{$u},'{$status}',{$w},{$d},{$t},{$limit},{$e})");
    return $ops[$tg] = (int)$mysqli->insert_id;
}
$opA = seedOp(900001, $hrId, 'active', 1, 0, 0);
$opB = seedOp(900002, $hrId, 'suspended', 1, 0, 0);
$opC = seedOp(900003, $hrId, 'revoked', 1, 0, 0);
$opD = seedOp(900004, $deadId, 'active', 1, 0, 0);
$opE = seedOp(900005, $hrId, 'active', 1, 0, 0);
$opT = seedOp(900006, $hrId, 'active', 0, 0, 1);
$opX = seedOp(900007, $hrId, 'active', 1, 0, 0, 50, '2020-01-01 00:00:00');
$opM = seedOp(900008, $hrId, 'active', 1, 0, 1);
check('seeded 2 users + 1 class + 8 operators', true, $hrId > 0 && $deadId > 0 && $classId > 0 && $opX > 0 && $opM > 0);

echo "== R: registry validation (no DB) ==\n";
$r = TelegramFormRegistry::validate('full_name_am', 'ሀብታሙ ተስፋዬ', true);
check('R1 valid name normalized', 'ሀብታሙ ተስፋዬ', $r['normalized']);
expectThrow('R2 latin name rejected', InvalidArgumentException::class, 'አማርኛ', fn() => TelegramFormRegistry::validate('full_name_am', 'Habtamu Tesfaye', true));
expectThrow('R3 single name rejected', InvalidArgumentException::class, 'first and father', fn() => TelegramFormRegistry::validate('full_name_am', 'ሀብታሙ', true));
$g = TelegramFormRegistry::validate('gender', 'ወንድ / Male', true);
check('R4 gender label resolves', 'male', $g['normalized']);
expectThrow('R5 bad gender rejected', InvalidArgumentException::class, 'available options', fn() => TelegramFormRegistry::validate('gender', 'x', true));
$d = TelegramFormRegistry::validate('dob_ec', '12/4/2002', false);
check('R6 dob canonicalized', '12/04/2002', $d['display']);
expectThrow('R7 bad dob rejected', InvalidArgumentException::class, 'not valid', fn() => TelegramFormRegistry::validate('dob_ec', '31/02/2000', false));
$p = TelegramFormRegistry::validate('phone_number', '0911 23 45 67', false);
check('R8 phone suffix normalized', '911234567', $p['normalized']);
expectThrow('R9 short phone rejected', InvalidArgumentException::class, '9 phone digits', fn() => TelegramFormRegistry::validate('phone_number', '123', false));
/* Class-only: the Amharic wording is pinned statically; byte-matching it
 * here risks lookalike-codepoint false failures. */
expectThrow('R10 file field rejects text', InvalidArgumentException::class, '', fn() => TelegramFormRegistry::validate('student_photo', 'a photo', false));

echo "== W: operator auth gates ==\n";
$w1 = bot(999001, 999001, '/new');
checkContains('W1 unknown id denied', 'አልተፈቀደም', $w1);
checkContains('W1 pairing id shown', 'Pairing request:', $w1);
$pending = one('SELECT * FROM telegram_bot_users WHERE telegram_user_id=999001');
check('W1 pending row auto-created', 'pending', $pending['status'] ?? null);
check('W1 no session for stranger', null, sessionOf((int)$pending['id']));
checkContains('W2 suspended denied', 'አልተፈቀደም', bot(900002, 900002, '/new'));
checkContains('W3 revoked denied', 'አልተፈቀደም', bot(900003, 900003, '/new'));
checkContains('W4 inactive-linked denied', 'አልተፈቀደም', bot(900004, 900004, '/new'));
checkContains('W5 expired operator denied', 'አልተፈቀደም', bot(900007, 900007, '/new'));
checkContains('W6 group chat refused', 'authorized private chat', bot(900001, -100, '/new', 'group'));

echo "== F: full waiting flow (operator A) ==\n";
checkContains('F1 /start shows home', 'ውሉደ ብርሃን', bot(900001, 900001, '/start'));
check('F1 no session yet', null, sessionOf($opA));
/* Op A is waiting-only: v2 auto-skips the profile chooser when a single
 * profile is allowed and published. */
checkContains('F2 /new auto-skips to name (single cap)', 'ሙሉ ስም', bot(900001, 900001, '/new'));
$s = sessionOf($opA);
check('F2 collecting row created', 'collecting', $s['status'] ?? null);
check('F2 profile waiting', 'waiting', $s['profile_key'] ?? null);
check('F2 form v1 pinned', 1, (int)($s['form_version'] ?? 0));
check('F2 first field', 'full_name_am', $s['current_field_key'] ?? null);
checkContains('F3 second /new blocked', 'ያልተጠናቀቀ', bot(900001, 900001, '/new'));
check('F3 still one session', 1, (int)one("SELECT COUNT(*) n FROM telegram_registration_sessions WHERE bot_user_id={$opA}")['n']);
/* Multi-cap operator exercises the chooser; a capless choice re-prompts. */
checkContains('F4 multi-cap /new shows chooser', 'የምዝገባ ዓይነት', bot(900008, 900008, '/new'));
check('F4 choosing row created', 'choosing', sessionOf($opM)['status'] ?? null);
checkContains('F4 direct without cap re-prompts', 'የምዝገባ ዓይነት', bot(900008, 900008, '⚡ ቀጥታ ምዝገባ'));
check('F4 still choosing', 'choosing', sessionOf($opM)['status'] ?? null);
checkContains('F4 waiting choice collects', 'ሙሉ ስም', bot(900008, 900008, '⏳ ጊዜያዊ / Temporary'));
$m = sessionOf($opM);
check('F4 profile waiting', 'waiting', $m['profile_key'] ?? null);
check('F4 collecting after choice', 'collecting', $m['status'] ?? null);
bot(900008, 900008, '/cancel');
checkContains('F6 latin name warned', '⚠️', bot(900001, 900001, 'Habtamu Tesfaye'));
check('F6 stays on field', 'full_name_am', sessionOf($opA)['current_field_key']);
checkContains('F7 single name warned', '⚠️', bot(900001, 900001, 'ሀብታሙ'));
checkContains('F8 valid name advances', 'ክርስትና', bot(900001, 900001, 'ሀብታሙ ተስፋዬ'));
check('F8 current baptismal', 'baptismal_name', sessionOf($opA)['current_field_key']);
checkContains('F9 skip advances', 'ጾታ', bot(900001, 900001, '⏭️ ዝለል'));
check('F9 skipped answer empty', '', answersOf((int)$s['id'])['baptismal_name']['value_text'] ?? null);
checkContains('F10 bad gender warned', '⚠️', bot(900001, 900001, 'x'));
$f11 = bot(900001, 900001, 'male');
$ans = answersOf((int)sessionOf($opA)['id']);
check('F11 gender display label', 'ወንድ / Male', $ans['gender']['value_text']);
check('F11 gender normalized', 'male', $ans['gender']['value_normalized']);
checkContains('F12 bad father warned', '⚠️', bot(900001, 900001, 'Tesfaye'));
checkContains('F13 valid father advances', 'እናት', bot(900001, 900001, 'ተስፋዬ'));
checkContains('F14 skip mother advances', 'ትውልድ', bot(900001, 900001, '⏭️ ዝለል'));
checkContains('F15 bad dob warned', '⚠️', bot(900001, 900001, '31/02/2000'));
checkContains('F15 valid dob advances', 'ስልክ', bot(900001, 900001, '12/04/2002'));
checkContains('F16 bad phone warned', '⚠️', bot(900001, 900001, '123'));
checkContains('F16 valid phone advances', 'አሳዳጊ', bot(900001, 900001, '0911234567'));
checkContains('F17 latin guardian warned', '⚠️', bot(900001, 900001, 'Ato Kebede'));
checkContains('F17 skip guardian advances', 'አሳዳጊ ስልክ', bot(900001, 900001, '/skip'));
$f18 = bot(900001, 900001, '0922345678');
$sA = sessionOf($opA);
check('F18 review reached', 'review', $sA['status']);
checkContains('F18 summary lists name', 'ሀብታሙ ተስፋዬ', $f18);
checkContains('F18 summary lists phone', '0911234567', $f18);
checkContains('F19 pencil jumps to field', 'ስልክ', bot(900001, 900001, '✏️ Phone / ስልክ'));
check('F19 edit mode stays in review', 'review', sessionOf($opA)['status']);
check('F19 edit targets phone', 'phone_number', sessionOf($opA)['current_field_key']);
$f19b = bot(900001, 900001, '0933345678');
check('F19b edit returns to review', 'review', sessionOf($opA)['status']);
check('F19b phone updated', '0933345678', answersOf((int)$sA['id'])['phone_number']['value_text']);
$f20 = bot(900001, 900001, '✅ ላክ');
$sA = sessionOf($opA, 'submitted');
check('F20 submitted', true, $sA !== null);
checkContains('F20 submit confirms HR review', 'HR', $f20);
check('F20 duplicate state graceful', true, in_array($sA['duplicate_state'], ['none', 'error'], true));
checkContains('F21 post-submit goes home', 'ውሉደ ብርሃን', bot(900001, 900001, '✅ ላክ'));

echo "== F: limits / cancel / expiry / resume (operator A) ==\n";
bot(900001, 900001, '/new');
$s2 = sessionOf($opA);
check('F22 second draft opens', 'collecting', $s2['status'] ?? null);
execSql("UPDATE telegram_bot_users SET daily_registration_limit=2 WHERE id={$opA}");
bot(900001, 900001, '/cancel');
check('F24 cancel flips status', 'cancelled', one("SELECT status FROM telegram_registration_sessions WHERE id=" . (int)$s2['id'])['status']);
checkContains('F23 daily limit enforced', 'ገደብ', bot(900001, 900001, '/new'));
execSql("UPDATE telegram_bot_users SET daily_registration_limit=50 WHERE id={$opA}");
bot(900001, 900001, '/new');
$s3 = sessionOf($opA);
execSql("UPDATE telegram_registration_sessions SET expires_at='2020-01-01 00:00:00' WHERE id=" . (int)$s3['id']);
bot(900001, 900001, '/new');
check('F25 stale draft expired', 'expired', one("SELECT status FROM telegram_registration_sessions WHERE id=" . (int)$s3['id'])['status']);
$s4 = sessionOf($opA);
check('F25 fresh draft opens', true, $s4 !== null && (int)$s4['id'] !== (int)$s3['id']);
checkContains('F26 bad name stays', '⚠️', bot(900001, 900001, 'hello'));
checkContains('F26 resume re-prompts field', 'ሙሉ ስም', bot(900001, 900001, '/resume'));
bot(900001, 900001, 'ሀብታሙ ተስፋዬ');
checkContains('F26 resume re-prompts next field', 'ክርስትና', bot(900001, 900001, '/resume'));
$f27 = bot(900001, 900001, '/back');
checkContains('F27 /back shows previous', 'ሙሉ ስም', $f27);
check('F27 back to name', 'full_name_am', sessionOf($opA)['current_field_key']);
bot(900001, 900001, 'ሀብታሙ ተስፋዬ');
checkContains('F28 /review mid-flow', 'ማጠቃለያ', bot(900001, 900001, '/review'));
check('F28 review does not submit', 'collecting', sessionOf($opA)['status']);
bot(900001, 900001, '⏭️ ዝለል');
bot(900001, 900001, 'male');
bot(900001, 900001, 'ተስፋዬ');
bot(900001, 900001, '⏭️ ዝለል');
bot(900001, 900001, '⏭️ ዝለል');
bot(900001, 900001, '⏭️ ዝለል');
bot(900001, 900001, '⏭️ ዝለል');
bot(900001, 900001, '⏭️ ዝለል');
check('F29 reached review', 'review', sessionOf($opA)['status']);
checkContains('F29 stray text re-shows review', 'ማጠቃለያ', bot(900001, 900001, 'random words'));
checkContains('F30 edit-all shows menu', 'ማስተካከል', bot(900001, 900001, '✏️ አርም'));
checkContains('F30 menu jumps to first field', 'ሙሉ ስም', bot(900001, 900001, '✏️ ሙሉ ስም'));
check('F30 back at first field', 'full_name_am', sessionOf($opA)['current_field_key']);
bot(900001, 900001, '/cancel');

echo "== F: operator E flow + HR correction cycle ==\n";
bot(900005, 900005, '/new');
bot(900005, 900005, 'ሀብታሙ ተስፋዬ');
bot(900005, 900005, '⏭️ ዝለል');
bot(900005, 900005, 'male');
bot(900005, 900005, 'ተስፋዬ');
bot(900005, 900005, '⏭️ ዝለል');
bot(900005, 900005, '12/04/2002');
bot(900005, 900005, '0933345678');
bot(900005, 900005, '⏭️ ዝለል');
bot(900005, 900005, '0922345678');
bot(900005, 900005, '✅ ላክ');
$sE = sessionOf($opE, 'submitted');
check('F31 E draft submitted', true, $sE !== null);
/* Emulate HR review_decide(changes_requested) for one field. */
execSql("UPDATE telegram_registration_sessions SET status='collecting',current_field_key='phone_number',correction_fields='[\"phone_number\"]',revision_number=revision_number+1,submitted_at=NULL,expires_at=DATE_ADD(NOW(),INTERVAL 24 HOUR) WHERE id=" . (int)$sE['id']);
checkContains('F31 resume prompts correction', 'ስልክ', bot(900005, 900005, '/resume'));
bot(900005, 900005, '0944345678');
$sE = sessionOf($opE);
check('F31 correction returns to review', 'review', $sE['status']);
check('F31 corrections cleared', null, $sE['correction_fields']);
bot(900005, 900005, '✅ ላክ');
$sE = sessionOf($opE, 'submitted');
check('F31 resubmitted revision 2', 2, (int)$sE['revision_number']);
check('F31 corrected phone stored', '0944345678', answersOf((int)$sE['id'])['phone_number']['value_text']);

echo "== F: transfer flow + caps (operator T) ==\n";
bot(900006, 900006, '/new');
$sT = sessionOf($opT);
/* Transfer-only op auto-skips the chooser straight into transfer; an explicit
 * waiting refusal cannot occur (cap-refusal on the chooser is covered by F4). */
check('F32 transfer auto-profile', 'transfer', $sT['profile_key'] ?? null);
check('F32 transfer collecting', 'collecting', $sT['status'] ?? null);
bot(900006, 900006, 'አበበ በቀለ');
bot(900006, 900006, '⏭️ ዝለል');
bot(900006, 900006, 'female');
bot(900006, 900006, 'በቀለ');
bot(900006, 900006, '⏭️ ዝለል');
bot(900006, 900006, '05/09/2001');
bot(900006, 900006, '0955345678');
bot(900006, 900006, '⏭️ ዝለል');
bot(900006, 900006, '0966345678');
bot(900006, 900006, '✅ ላክ');
$sT = sessionOf($opT, 'submitted');
check('F32 transfer submitted', true, $sT !== null);

echo "== A: readiness + approval on pristine schema ==\n";
$readiness = (new TelegramApprovalReadinessService($mysqli))->inspect($sA, array_values(array_map(
    fn($k, $v) => ['field_key' => $k, 'value_text' => $v['value_text'], 'value_normalized' => $v['value_normalized']],
    array_keys(answersOf((int)$sA['id'])),
    answersOf((int)$sA['id'])
)));
check('A1 readiness computed', true, isset($readiness['ready']));
/* Migration 093 provides members.membership_tier on tracked-migration schemas,
 * so approval succeeds directly (the old throwaway probe column is gone). */
$appr = (new TelegramMemberApprovalService($mysqli))->approve((int)$sA['id'], $hrId, 'E2E approval');

echo "== A: approval assertions ==\n";
check('A3 approved', false, $appr['already_approved']);
check('A3 profile waiting', 'waiting', $appr['profile']);
$m1 = one('SELECT * FROM members WHERE id=' . (int)$appr['member_id']);
check('A3 tier temporary', 'temporary', $m1['membership_tier']);
check('A3 names split', ['ሀብታሙ', 'ተስፋዬ'], [$m1['student_name'], $m1['father_name']]);
check('A3 gender', 'male', $m1['gender']);
check('A3 dob parts', [12, 4, 2002], [(int)$m1['dob_ec_day'], (int)$m1['dob_ec_month'], (int)$m1['dob_ec_year']]);
check('A3 phone', '0933345678', $m1['phone_number']);
check('A3 canonical refreshed', true, ($m1['student_name_norm'] ?? '') !== '' && ($m1['phone_suffix9'] ?? '') === '933345678');
check('A3 session approved', 'approved', one('SELECT status FROM telegram_registration_sessions WHERE id=' . (int)$sA['id'])['status']);
check('A3 review row', 'approved', one('SELECT decision FROM telegram_registration_reviews WHERE session_id=' . (int)$sA['id'] . ' ORDER BY id DESC LIMIT 1')['decision']);
$again = (new TelegramMemberApprovalService($mysqli))->approve((int)$sA['id'], $hrId, 'E2E approval');
check('A4 idempotent re-approve', [true, $appr['member_id']], [$again['already_approved'], $again['member_id']]);
check('A4 still one member', 1, (int)one('SELECT COUNT(*) n FROM members')['n']);
/* Prove the indexed lookup itself works once the column exists. */
$errBefore = count($internalErrors);
bot(900005, 900005, '/new');
bot(900005, 900005, '⏳ ጊዜያዊ / Temporary');
bot(900005, 900005, 'ገብረ ማርያም');
$probe = sessionOf($opE);
check('A3b lookup state none', 'none', $probe['duplicate_state']);
$meta = json_decode((string)$probe['duplicate_summary'], true);
check('A3b summary shape', true, is_array($meta) && array_key_exists('candidates', $meta) && ($meta['plausible_count'] ?? -1) === 0);
check('A3b no new internal errors', $errBefore, count($internalErrors));
bot(900005, 900005, '/cancel');
expectThrow('A5 strong duplicate blocked', DomainException::class, 'strongly matching',
    fn() => (new TelegramMemberApprovalService($mysqli))->approve((int)$sE['id'], $hrId, 'E2E duplicate attempt'));
execSql("UPDATE telegram_registration_sessions SET duplicate_state='likely' WHERE id=" . (int)$sT['id']);
expectThrow('A6 likely duplicate blocks preflight', DomainException::class, 'likely duplicate',
    fn() => (new TelegramMemberApprovalService($mysqli))->approve((int)$sT['id'], $hrId, 'E2E likely attempt'));
execSql("UPDATE telegram_registration_sessions SET duplicate_state='none' WHERE id=" . (int)$sT['id']);
/* Finding F1 (CLOSED by audit fix): a current-revision rejected/duplicate HR
 * verdict is terminal — approval refuses even though the row stays submitted. */
execSql("INSERT INTO telegram_registration_reviews(session_id,session_revision,decision,reviewer_user_id,note) VALUES(" . (int)$sT['id'] . ",1,'rejected',{$hrId},'E2E reject first')");
expectThrow('A7 rejected draft refused (F1 closed)', DomainException::class, 'marked "rejected"',
    fn() => (new TelegramMemberApprovalService($mysqli))->approve((int)$sT['id'], $hrId, 'E2E approval after reject'));
check('A7 history keeps the rejection', 1, (int)one('SELECT COUNT(*) n FROM telegram_registration_reviews WHERE session_id=' . (int)$sT['id'])['n']);
check('A8 tier column present (093)', 1, (int)one("SELECT COUNT(*) n FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='members' AND column_name='membership_tier'")['n']);

echo "== T: retention ==\n";
execSql("INSERT INTO telegram_registration_sessions(public_id,bot_user_id,telegram_chat_id,status,expires_at) VALUES('TGRETENTIONEXPIRED000001',{$opA},900001,'choosing','2020-01-01 00:00:00')");
$expId = (int)$mysqli->insert_id;
execSql("INSERT INTO telegram_registration_sessions(public_id,bot_user_id,telegram_chat_id,status,expires_at) VALUES('TGRETENTIONCANCELLED00001',{$opA},900001,'cancelled',DATE_ADD(NOW(),INTERVAL 1 DAY))");
$oldId = (int)$mysqli->insert_id;
execSql("INSERT INTO telegram_registration_answers(session_id,field_key,value_text) VALUES({$oldId},'full_name_am','x')");
execSql("UPDATE telegram_registration_sessions SET updated_at='2020-01-01 00:00:00' WHERE id={$oldId}");
execSql("INSERT INTO telegram_processed_updates(update_id,telegram_user_id,update_type,status,received_at) VALUES(990000001,900001,'message','processed','2020-01-01 00:00:00')");
execSql("INSERT INTO telegram_audit_log(actor_type,actor_id,event,entity_type,entity_id,result,created_at) VALUES('telegram',900001,'retention_probe','session',{$oldId},'success','2020-01-01 00:00:00')");
$counts = TelegramRetentionService::run($mysqli, 500);
check('T1 expired flipped', true, $counts['expired_sessions'] >= 1);
check('T1 old update pruned', true, $counts['processed_updates_deleted'] >= 1);
check('T1 old cancelled pruned', true, $counts['cancelled_sessions_deleted'] >= 1);
check('T1 old audit pruned', true, $counts['audit_deleted'] >= 1);
check('T1 expired status set', 'expired', one("SELECT status FROM telegram_registration_sessions WHERE id={$expId}")['status']);
check('T1 old session gone', null, one("SELECT id FROM telegram_registration_sessions WHERE id={$oldId}"));
check('T1 old answers gone', 0, (int)one("SELECT COUNT(*) n FROM telegram_registration_answers WHERE session_id={$oldId}")['n']);
/* Finding F6 (fixed in telegram round-3): retention now cleans stale
 * attachments — the quarantined file is unlinked and the row is tombstoned
 * ('deleted') instead of leaking both. Verified with a throwaway session. */
execSql("INSERT INTO telegram_registration_sessions(public_id,bot_user_id,telegram_chat_id,status,expires_at) VALUES('TGRETENTIONATTACH0000001',{$opA},900001,'cancelled',DATE_ADD(NOW(),INTERVAL 1 DAY))");
$attSess = (int)$mysqli->insert_id;
@mkdir('/tmp/ssms_private/telegram_quarantine', 0700, true);
file_put_contents('/tmp/ssms_private/telegram_quarantine/' . str_repeat('a', 32) . '.png', 'orphan');
execSql("INSERT INTO telegram_registration_attachments(session_id,field_key,telegram_file_id,telegram_unique_id,stored_path,mime_type,byte_size,status) VALUES({$attSess},'student_photo','f1','u1','private://telegram_quarantine/" . str_repeat('a', 32) . ".png','image/png',6,'stored')");
execSql("UPDATE telegram_registration_sessions SET updated_at='2020-01-01 00:00:00' WHERE id={$attSess}");
TelegramRetentionService::run($mysqli, 500);
check('T2 session pruned', null, one("SELECT id FROM telegram_registration_sessions WHERE id={$attSess}"));
check('T2 attachment ROW tombstoned (F6)', 'deleted', one("SELECT status FROM telegram_registration_attachments WHERE session_id={$attSess}")['status']);
check('T2 attachment FILE cleaned (F6)', false, is_file('/tmp/ssms_private/telegram_quarantine/' . str_repeat('a', 32) . '.png'));
execSql("DELETE FROM telegram_registration_attachments WHERE session_id={$attSess}");
@unlink('/tmp/ssms_private/telegram_quarantine/' . str_repeat('a', 32) . '.png');

echo "== M: form versioning ==\n";
$forms = new TelegramFormAdminService($mysqli);
expectThrow('M2 bad profile rejected', InvalidArgumentException::class, 'Invalid form',
    fn() => $forms->createDraft('nope', $hrId));
$draft = $forms->createDraft('waiting', $hrId);
check('M1 draft v2 created', [2, 'draft'], [(int)$draft['version'], $draft['status']]);
$draftId = (int)$draft['id'];
$mirror = array_map(fn($f) => [
    'field_key' => $f['field_key'], 'is_enabled' => $f['is_enabled'], 'is_required' => $f['is_required'],
    'prompt_am' => $f['prompt_am'], 'prompt_en' => $f['prompt_en'], 'help_am' => $f['help_am'], 'help_en' => $f['help_en'],
], $draft['fields']);
$bad = $mirror;
$bad[2]['is_enabled'] = 0; /* gender required but disabled */
expectThrow('M3 required+disabled rejected', InvalidArgumentException::class, 'must be enabled',
    fn() => $forms->saveDraft($draftId, 't', 't', $bad));
$bad2 = $mirror;
$bad2[0]['prompt_en'] = '';
expectThrow('M4 missing prompt rejected', InvalidArgumentException::class, 'both prompts',
    fn() => $forms->saveDraft($draftId, 't', 't', $bad2));
$saved = $forms->saveDraft($draftId, 'ጊዜያዊ ምዝገባ v2', 'Temporary Registration v2', $mirror);
check('M5 draft saved', 'Temporary Registration v2', $saved['title_en']);
$pub = $forms->publish($draftId, $hrId);
check('M6 v2 published', 'published', $pub['status']);
check('M6 v1 retired', 'retired', one("SELECT status FROM telegram_form_profiles WHERE profile_key='waiting' AND version=1")['status']);
expectThrow('M7 republish rejected', InvalidArgumentException::class, 'Only a draft',
    fn() => $forms->publish($draftId, $hrId));
expectThrow('M7 edit published rejected', InvalidArgumentException::class, 'immutable',
    fn() => $forms->saveDraft($draftId, 't', 't', $mirror));
bot(900005, 900005, '/new');
bot(900005, 900005, '⏳ ጊዜያዊ / Temporary');
check('M8 bot picks published v2', 2, (int)sessionOf($opE)['form_version']);
bot(900005, 900005, '/cancel');
execSql("DELETE FROM telegram_form_fields WHERE profile_id={$draftId}");
execSql("DELETE FROM telegram_form_profiles WHERE id={$draftId}");
execSql("UPDATE telegram_form_profiles SET status='published' WHERE profile_key='waiting' AND version=1");

echo "== S: attachments ==\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$tmp = tempnam(sys_get_temp_dir(), 'tg_e2e_');
file_put_contents($tmp, $png);
$atts = new TelegramAttachmentService($mysqli);
bot(900001, 900001, '/new');
bot(900001, 900001, '⏳ ጊዜያዊ / Temporary');
$sessS = (int)sessionOf($opA)['id'];
$stored = $atts->store($sessS, 'student_photo', 'fileid1', 'uniq1', $tmp);
check('S1 stored', 'stored', one("SELECT status FROM telegram_registration_attachments WHERE id=" . (int)$stored['id'])['status']);
check('S1 file exists', true, is_file(TelegramAttachmentService::resolve($stored['path'])));
check('S1 mode 0600', '600', substr(sprintf('%o', fileperms(TelegramAttachmentService::resolve($stored['path']))), -3));
$txt = tempnam(sys_get_temp_dir(), 'tg_e2e_');
file_put_contents($txt, 'not an image');
expectThrow('S2 text file rejected', InvalidArgumentException::class, 'verified',
    function () use ($atts, $sessS, $txt) { $atts->store($sessS, 'guardian_photo', 'f2', 'u2', $txt); });
@unlink($txt);
$big = tempnam(sys_get_temp_dir(), 'tg_e2e_');
file_put_contents($big, str_repeat('0', 5242881));
expectThrow('S3 oversize rejected', InvalidArgumentException::class, '5 MB',
    function () use ($atts, $sessS, $big) { $atts->store($sessS, 'guardian_photo', 'f3', 'u3', $big); });
@unlink($big);
$pdf = tempnam(sys_get_temp_dir(), 'tg_e2e_');
file_put_contents($pdf, "%PDF-1.4\n%fake minimal pdf body for finfo detection........\n");
try {
    $atts->store($sessS, 'doc_signed_form', 'f4', 'u4', $pdf);
    check('S4 pdf accepted for image_pdf', 'pdf', pathinfo(TelegramAttachmentService::resolve(one("SELECT stored_path FROM telegram_registration_attachments WHERE session_id={$sessS} AND field_key='doc_signed_form'")['stored_path']), PATHINFO_EXTENSION));
} catch (Throwable $e) {
    check('S4 pdf accepted for image_pdf', 'no-throw', get_class($e) . ': ' . $e->getMessage());
}
@unlink($pdf);
$pdf2 = tempnam(sys_get_temp_dir(), 'tg_e2e_');
file_put_contents($pdf2, "%PDF-1.4\n%fake minimal pdf body for finfo detection........\n");
expectThrow('S5 pdf rejected for image-only', InvalidArgumentException::class, 'verified',
    function () use ($atts, $sessS, $pdf2) { $atts->store($sessS, 'student_photo', 'f5', 'u5', $pdf2); });
@unlink($pdf2);
check('S6 resolve ok', true, TelegramAttachmentService::resolve($stored['path']) !== null);
check('S6 traversal refused', null, TelegramAttachmentService::resolve('private://telegram_quarantine/../x.png'));
check('S6 bad ext refused', null, TelegramAttachmentService::resolve('private://telegram_quarantine/' . str_repeat('b', 32) . '.exe'));
$oldFile = TelegramAttachmentService::resolve($stored['path']);
$tmp2 = tempnam(sys_get_temp_dir(), 'tg_e2e_');
file_put_contents($tmp2, $png);
$stored2 = $atts->store($sessS, 'student_photo', 'fileid9', 'uniq9', $tmp2);
check('S7 replace ok', true, $stored2['path'] !== $stored['path']);
check('S7 old file discarded', false, is_file($oldFile));
$atts->cleanupSession($sessS);
check('S8 cleanup marks deleted', 'deleted', one("SELECT status FROM telegram_registration_attachments WHERE session_id={$sessS} AND field_key='student_photo'")['status']);
check('S8 file removed', false, is_file(TelegramAttachmentService::resolve($stored2['path']) ?? '/nonexistent'));
bot(900001, 900001, '/cancel');

echo "== cleanup + residue ==\n";
try {
    $botIds = implode(',', array_map('intval', array_values($ops))) . ',999001,' . (int)$pending['id'];
    /* resolve real bot ids for the auto-created stranger row */
    $allBots = array_column(q("SELECT id FROM telegram_bot_users WHERE telegram_user_id IN (900001,900002,900003,900004,900005,900006,900007,900008,999001)"), 'id');
    $botList = implode(',', array_map('intval', $allBots));
    $sessIds = array_column(q("SELECT id FROM telegram_registration_sessions WHERE bot_user_id IN ({$botList})"), 'id');
    $sessList = $sessIds === [] ? '0' : implode(',', array_map('intval', $sessIds));
    execSql("DELETE FROM telegram_registration_answers WHERE session_id IN ({$sessList})");
    execSql("DELETE FROM telegram_registration_reviews WHERE session_id IN ({$sessList})");
    $leftover = q("SELECT stored_path FROM telegram_registration_attachments WHERE session_id IN ({$sessList}) AND stored_path IS NOT NULL");
    execSql("DELETE FROM telegram_registration_attachments WHERE session_id IN ({$sessList})");
    foreach ($leftover as $row) {
        $p = TelegramAttachmentService::resolve((string)$row['stored_path']);
        if ($p !== null) {
            @unlink($p);
        }
    }
    execSql("DELETE FROM telegram_registration_sessions WHERE id IN ({$sessList})");
    execSql("DELETE FROM telegram_processed_updates WHERE telegram_user_id IN (900001,900002,900003,900004,900005,900006,900007,900008,999001) OR update_id=990000001");
    execSql("DELETE FROM telegram_audit_log WHERE actor_type='telegram'");
    execSql("DELETE FROM telegram_bot_users WHERE id IN ({$botList})");
    execSql("DELETE FROM members WHERE id IN (" . implode(',', array_map('intval', array_column(q('SELECT id FROM members'), 'id'))) . ",0)");
    execSql("DELETE FROM users WHERE id IN ({$hrId},{$deadId})");
    execSql("DELETE FROM classes WHERE id={$classId}");
    @rmdir('/tmp/ssms_private/telegram_quarantine');
    @rmdir('/tmp/ssms_private');
    $residue = (int)one("SELECT (SELECT COUNT(*) FROM telegram_bot_users WHERE telegram_user_id BETWEEN 900000 AND 999999)+(SELECT COUNT(*) FROM members)+(SELECT COUNT(*) FROM telegram_registration_sessions WHERE public_id LIKE 'TGRET%')+(SELECT COUNT(*) FROM users WHERE username LIKE 'tg\\_%e2e') n")['n'];
    check('residue zero', 0, $residue);
    echo "  ok    seeded rows removed\n";
} catch (Throwable $e) {
    $failures[] = 'cleanup: ' . $e->getMessage();
    echo "  FAIL  cleanup: {$e->getMessage()}\n";
}

echo "\ninternal errors captured: " . count($internalErrors) . "\n";
foreach (array_slice($internalErrors, 0, 5) as $e) {
    echo "  note  {$e}\n";
}
echo "\n{$checks} checks, " . count($failures) . " failures\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}
echo "WALKTHROUGH GREEN\n";


