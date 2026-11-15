<?php
/**
 * ACM family-linking gaps walkthrough against a real MariaDB.
 * ------------------------------------------------------------
 * G3: ticket link-suggestions query the real 069 pair columns.
 * (G2 verification + G1 parent-children delivery extend this file.)
 *
 * COMMAND LINE ONLY, and destructive: it seeds users, members,
 * guardians, persons, tickets and suggestions, then deletes them
 * again. Disposable database only (destructive_guard interlock).
 *
 * Usage (sandbox, migrations through 091 applied):
 *   SSMS_AUDIT_TESTING=1 php tests/e2e/acm_family_walkthrough.php
 *
 * NOT run in CI (no database in the sandbox); the sandbox pins
 * the same behaviors statically
 * (tests/security/test_acm_family_gaps.py).
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
require_once $root . '/acm/autoload.php';
require_once $root . '/admin/backend/services/GuardianMatchService.php';
require_once $root . '/admin/backend/services/GuardianService.php';
require_once $root . '/api/v1/core/acl.php';

use App\Services\GuardianService;
use SSMS\Acm\Accounts\AcmTicketService;
use SSMS\Acm\Identity\AcmPersonService;

$dbName = getenv('SSMS_SYNC_DB') ?: 'ssms_e2e';
ssms_require_disposable_database($dbName, 'acm_family_walkthrough');

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . $dbName . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

try {
    $pdo->query('SELECT 1 FROM acm_activation_tickets LIMIT 1');
    $pdo->query('SELECT 1 FROM guardian_link_suggestions LIMIT 1');
} catch (PDOException $e) {
    fwrite(STDERR, "acm/suggestions tables are not present in {$dbName}\n");
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

echo "== seed ==\n";
$pdo->exec("INSERT INTO users(username,full_name,role,password_hash,is_active) VALUES('fam_e2e','Fam E2E','accounts_office','x',1)");
$uid = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO members(full_name_am,student_name,father_name,gender) VALUES('ሰላም ተስፋ','ሰላም','ተስፋ','female')");
$mid = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO guardians(display_name,phone_primary,status) VALUES('አቶ ተስፋ','0911000001','active'),('ወሮ ሰላም','0911000002','active')");
$gidA = (int)$pdo->query("SELECT id FROM guardians WHERE phone_primary='0911000001'")->fetchColumn();
$gidB = (int)$pdo->query("SELECT id FROM guardians WHERE phone_primary='0911000002'")->fetchColumn();
$persons = new AcmPersonService($pdo);
$tickets = new AcmTicketService($pdo, $persons);
$person = $persons->create(['full_name_am' => 'አቶ ተስፋ', 'guardian_id' => $gidA], $uid);
$ticket = $tickets->create(['person_id' => $person['id'], 'account_type' => 'parent'], $uid);
check('seeded user+member+guardians+person+ticket', true, $uid > 0 && $mid > 0 && $gidB > 0 && $ticket['id'] > 0);

echo "== G3: link suggestions ==\n";
$pdo->exec("INSERT INTO guardian_link_suggestions(guardian_a,guardian_b,member_id,score,band,status) VALUES({$gidA},{$gidB},{$mid},0.82,'review','pending'),({$gidB},{$gidA},NULL,0.95,'auto','pending')");
$got = $tickets->get((int)$ticket['id']);
check('G3 two suggestions returned', 2, count($got['suggestions']));
$cols = array_keys($got['suggestions'][0]);
check('G3 pair columns present', true, in_array('guardian_a', $cols, true) && in_array('guardian_b', $cols, true));
check('G3 no phantom column', false, in_array('guardian_id', $cols, true));
$personM = $persons->create(['full_name_am' => 'ሰላም ተስፋ', 'member_id' => $mid], $uid);
$ticketM = $tickets->create(['person_id' => $personM['id'], 'account_type' => 'member'], $uid);
$gotM = $tickets->get((int)$ticketM['id']);
check('G3 guardian-less ticket: empty', [], $gotM['suggestions']);

echo "== G2: link verification ==\n";
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "mysqli connect failed: {$mysqli->connect_error}\n");
    exit(3);
}
$mysqli->set_charset('utf8mb4');
$pdo->exec("INSERT INTO users(username,full_name,role,password_hash,is_active) VALUES('fam_g2_e2e','Fam G2','hr_dept','x',1)");
$uid2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO members(full_name_am,student_name,father_name,gender) VALUES('ሰላም ገብረ','ሰላም','ገብረ','male')");
$mid2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO guardians(display_name,phone_primary,status) VALUES('አቶ ገብረ','0922000001','active'),('ወሮ ገብረ','0922000002','active')");
$gid2a = (int)$pdo->query("SELECT id FROM guardians WHERE phone_primary='0922000001'")->fetchColumn();
$gid2b = (int)$pdo->query("SELECT id FROM guardians WHERE phone_primary='0922000002'")->fetchColumn();
$_SESSION['admin_id'] = $uid2;
$_SESSION['admin_username'] = 'fam_g2_e2e';
GuardianService::linkMember($mysqli, $gid2a, $mid2, 'father', 1, true, true, true);
$link = $pdo->query("SELECT verified_by, verified_at FROM member_guardians WHERE member_id={$mid2} AND guardian_id={$gid2a}")->fetch();
check('G2 office link stamped', $uid2, (int)$link['verified_by']);
check('G2 stamp has timestamp', true, $link['verified_at'] !== null);
$_SESSION['admin_id'] = 0;
$pdo->exec("INSERT INTO members(full_name_am,student_name,father_name,gender) VALUES('ጽጌ ገብረ','ጽጌ','ገብረ','female')");
$mid3 = (int)$pdo->lastInsertId();
GuardianService::linkMember($mysqli, $gid2a, $mid3, 'other', 1, false);
$auto = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 derived link stays unverified', null, $auto);
GuardianService::linkMember($mysqli, $gid2a, $mid2, 'father', 1, true);
$kept = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid2} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 relink never clears stamp', $uid2, (int)$kept);
$_SESSION['admin_id'] = $uid2;
GuardianService::linkMember($mysqli, $gid2a, $mid3, 'other', 1, false, true, true);
$late = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 office relink stamps', $uid2, (int)$late);
GuardianService::clearVerification($mysqli, $gid2a, $mid3, $uid2);
$cleared = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 clear removes stamp, link stays', null, $cleared);
check('G2 link row survives clear', 1, (int)$pdo->query("SELECT COUNT(*) FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2a}")->fetchColumn());
GuardianService::verifyLink($mysqli, $gid2a, $mid3, $uid2);
GuardianService::verifyLink($mysqli, $gid2a, $mid3, $uid2);
$restamped = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 verify re-stamp safe', $uid2, (int)$restamped);
try {
    GuardianService::verifyLink($mysqli, $gid2b, $mid3, $uid2);
    check('G2 verify missing link throws', 'throw', 'no-throw');
} catch (InvalidArgumentException $e) {
    check('G2 verify missing link throws', true, true);
}
$kids = GuardianService::childrenOf($mysqli, $gid2a, 50);
$flags = [];
foreach ($kids as $k) {
    $flags[(int)$k['id']] = (int)$k['is_verified'];
}
check('G2 childrenOf count', 2, count($flags));
check('G2 childrenOf flag mid2', 1, $flags[$mid2] ?? -1);
check('G2 childrenOf flag mid3', 1, $flags[$mid3] ?? -1);
GuardianService::clearVerification($mysqli, $gid2a, $mid3, $uid2);
$picked = GuardianService::attachFromLegacy($mysqli, $mid3, $gid2a, 'hr');
check('G2 picked mode', 'picked', $picked['mode']);
$rePick = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 picked path stamps', $uid2, (int)$rePick);
/* Grandfather: legacy office row vs legacy system row. */
$pdo->exec("INSERT INTO member_guardians(member_id,guardian_id,relation,contact_priority,is_primary,created_by,created_at) VALUES({$mid2},{$gid2b},'mother',2,0,{$uid2},'2026-01-05 10:00:00'),({$mid3},{$gid2b},'other',2,0,NULL,'2026-01-05 10:00:00')");
$sql092 = implode("\n", array_filter(
    explode("\n", file_get_contents($root . '/sql/092_acm_guardian_link_verification.sql')),
    fn($line) => !str_starts_with(ltrim($line), '--')
));
$pdo->exec($sql092);
$grand = $pdo->query("SELECT verified_by, verified_at FROM member_guardians WHERE member_id={$mid2} AND guardian_id={$gid2b}")->fetch();
check('G2 grandfather stamps office row', [$uid2, '2026-01-05 10:00:00'], [(int)$grand['verified_by'], $grand['verified_at']]);
$sysRow = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid3} AND guardian_id={$gid2b}")->fetchColumn();
check('G2 system row stays unverified', null, $sysRow);
$pdo->exec($sql092);
check('G2 migration re-run safe', 1, (int)$pdo->query("SELECT COUNT(*) FROM member_guardians WHERE member_id={$mid2} AND guardian_id={$gid2b}")->fetchColumn());
$stats = GuardianService::stats($mysqli);
check('G2 stats counts unverified', true, $stats['links_unverified'] >= 1);
/* Merge preserves verification on moved links (loser-only member). */
$pdo->exec("INSERT INTO members(full_name_am,student_name,father_name,gender) VALUES('ሄኖክ ገብረ','ሄኖክ','ገብረ','male')");
$mid4 = (int)$pdo->lastInsertId();
GuardianService::linkMember($mysqli, $gid2b, $mid4, 'other', 1, true, true, true);
GuardianService::merge($mysqli, $gid2b, $gid2a, $uid2);
$moved = $pdo->query("SELECT verified_by FROM member_guardians WHERE member_id={$mid4} AND guardian_id={$gid2a}")->fetchColumn();
check('G2 merge keeps verified stamp', $uid2, (int)$moved);

echo "== G1: parent children read path ==\n";
$pdo->exec("INSERT INTO users(username,full_name,role,password_hash,is_active) VALUES('fam_g1_parent','Fam G1','parent','x',1),('fam_g1_staff','Fam G1s','info_dept','x',1)");
$uidP = (int)$pdo->query("SELECT id FROM users WHERE username='fam_g1_parent'")->fetchColumn();
$uidS = (int)$pdo->query("SELECT id FROM users WHERE username='fam_g1_staff'")->fetchColumn();
$pdo->exec("INSERT INTO guardians(display_name,phone_primary,status) VALUES('አባ G1','0933000001','active'),('አባ G1 other','0933000002','active')");
$gidP = (int)$pdo->query("SELECT id FROM guardians WHERE phone_primary='0933000001'")->fetchColumn();
$gidO = (int)$pdo->query("SELECT id FROM guardians WHERE phone_primary='0933000002'")->fetchColumn();
$pdo->exec("INSERT INTO members(full_name_am,student_name,father_name,gender,status) VALUES('ዳዊት ወልደ','ዳዊት','ወልደ','male','active'),('ማርታ ወልደ','ማርታ','ወልደ','female','active'),('ዮሴፍ ወልደ','ዮሴፍ','ወልደ','male','archived'),('ሰራዊት ወልደ','ሰራዊት','ወልደ','female','active')");
$midV = (int)$pdo->query("SELECT id FROM members WHERE student_name='ዳዊት' AND father_name='ወልደ'")->fetchColumn();
$midU = (int)$pdo->query("SELECT id FROM members WHERE student_name='ማርታ'")->fetchColumn();
$midA = (int)$pdo->query("SELECT id FROM members WHERE student_name='ዮሴፍ'")->fetchColumn();
$midO = (int)$pdo->query("SELECT id FROM members WHERE student_name='ሰራዊት'")->fetchColumn();
$_SESSION['admin_id'] = $uid2;
GuardianService::linkMember($mysqli, $gidP, $midV, 'father', 1, true, true, true);
GuardianService::linkMember($mysqli, $gidP, $midU, 'father', 1, false);
GuardianService::linkMember($mysqli, $gidP, $midA, 'father', 1, false, true, true);
GuardianService::linkMember($mysqli, $gidO, $midO, 'father', 1, true, true, true);
$pdo->exec("INSERT INTO acm_persons(uuid,full_name_am,guardian_id) VALUES(UUID(),'አባ G1',{$gidP})");
$pidP = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO acm_person_accounts(person_id,user_id,account_type,status,linked_by,linked_at) VALUES({$pidP},{$uidP},'parent','active',{$uid2},NOW())");
$authP = ['uid' => $uidP, 'rol' => 'parent'];
$authS = ['uid' => $uidS, 'rol' => 'info_dept'];
check('G1 resolver maps parent to guardian', $gidP, apiParentGuardianId($mysqli, $authP));
check('G1 staff resolves to none', 0, apiParentGuardianId($mysqli, $authS));
check('G1 unknown user resolves to none', 0, apiParentGuardianId($mysqli, ['uid' => 999999, 'rol' => 'parent']));
$pdo->exec("UPDATE acm_person_accounts SET status='suspended' WHERE user_id={$uidP}");
check('G1 suspended link resolves to none', 0, apiParentGuardianId($mysqli, $authP));
$pdo->exec("UPDATE acm_person_accounts SET status='active' WHERE user_id={$uidP}");
check('G1 verified child visible', true, apiParentCanSeeMember($mysqli, $authP, $midV));
check('G1 unverified child hidden', false, apiParentCanSeeMember($mysqli, $authP, $midU));
check('G1 other guardian child hidden', false, apiParentCanSeeMember($mysqli, $authP, $midO));
check('G1 staff never scoped', false, apiParentCanSeeMember($mysqli, $authS, $midV));
$kids = apiParentChildren($mysqli, $authP);
check('G1 list has verified non-archived only', [$midV], array_map(fn($k) => $k['id'], $kids));
check('G1 list empty for staff', [], apiParentChildren($mysqli, $authS));
$leaked = array_intersect(['phone_number', 'phone_primary', 'alt_phone_number', 'guardian_name', 'guardian_phone1', 'guardian_phone2', 'address', 'city', 'sub_city', 'woreda', 'date_of_birth', 'student_photo_path'], array_keys($kids[0]));
check('G1 child row leaks no PII', [], array_values($leaked));
check('G1 child carries relation', 'father', $kids[0]['relation']);
check('G1 child carries primary flag', 1, $kids[0]['is_primary']);
check('G1 child carries verified_at', true, $kids[0]['verified_at'] !== null);

echo "== cleanup + residue ==\n";
try {
    $pdo->exec("DELETE FROM acm_ticket_events WHERE ticket_id IN (" . (int)$ticket['id'] . ',' . (int)$ticketM['id'] . ')');
    $pdo->exec("DELETE FROM acm_activation_tickets WHERE id IN (" . (int)$ticket['id'] . ',' . (int)$ticketM['id'] . ')');
    $pdo->exec("DELETE FROM acm_person_accounts WHERE person_id IN (" . (int)$person['id'] . ',' . (int)$personM['id'] . ')');
    $pdo->exec("DELETE FROM acm_persons WHERE id IN (" . (int)$person['id'] . ',' . (int)$personM['id'] . ')');
    $pdo->exec("DELETE FROM guardian_link_suggestions WHERE guardian_a IN ({$gidA},{$gidB}) OR guardian_b IN ({$gidA},{$gidB})");
    $pdo->exec("DELETE FROM member_guardians WHERE guardian_id IN ({$gidA},{$gidB}) OR member_id={$mid}");
    $pdo->exec("DELETE FROM guardian_aliases WHERE guardian_id IN ({$gidA},{$gidB})");
    $pdo->exec("DELETE FROM guardians WHERE id IN ({$gidA},{$gidB})");
    $pdo->exec("DELETE FROM members WHERE id={$mid}");
    $pdo->exec("DELETE FROM users WHERE id={$uid}");
    $pdo->exec("DELETE FROM member_guardians WHERE guardian_id IN ({$gid2a},{$gid2b}) OR member_id IN ({$mid2},{$mid3},{$mid4})");
    $pdo->exec("DELETE FROM guardian_aliases WHERE guardian_id IN ({$gid2a},{$gid2b})");
    $pdo->exec("DELETE FROM guardians WHERE id IN ({$gid2a},{$gid2b})");
    $pdo->exec("DELETE FROM members WHERE id IN ({$mid2},{$mid3},{$mid4})");
    $pdo->exec("DELETE FROM users WHERE id={$uid2}");
    $pdo->exec("DELETE FROM member_guardians WHERE guardian_id IN ({$gidP},{$gidO}) OR member_id IN ({$midV},{$midU},{$midA},{$midO})");
    $pdo->exec("DELETE FROM guardian_aliases WHERE guardian_id IN ({$gidP},{$gidO})");
    $pdo->exec("DELETE FROM acm_person_accounts WHERE user_id IN ({$uidP},{$uidS})");
    $pdo->exec("DELETE FROM acm_persons WHERE id={$pidP}");
    $pdo->exec("DELETE FROM guardians WHERE id IN ({$gidP},{$gidO})");
    $pdo->exec("DELETE FROM members WHERE id IN ({$midV},{$midU},{$midA},{$midO})");
    $pdo->exec("DELETE FROM users WHERE id IN ({$uidP},{$uidS})");
    $pdo->exec("DELETE FROM activity_logs WHERE action LIKE 'Guardian%' AND (details LIKE 'Member {$mid2}%' OR details LIKE 'Member {$mid3}%' OR details LIKE 'Member {$mid4}%' OR details LIKE 'Member {$midV}%' OR details LIKE 'Member {$midU}%' OR details LIKE 'Member {$midA}%' OR details LIKE 'Member {$midO}%' OR details LIKE 'Guardian {$gid2a}%' OR details LIKE 'Guardian {$gid2b}%' OR details LIKE 'Guardian {$gidP}%' OR details LIKE 'Guardian {$gidO}%')");
    $residue = (int)$pdo->query(
        "SELECT (SELECT COUNT(*) FROM users WHERE username IN ('fam_e2e','fam_g2_e2e','fam_g1_parent','fam_g1_staff'))"
        . "+(SELECT COUNT(*) FROM members WHERE student_name IN ('ሰላም','ሰላም','ጽጌ','ሄኖክ','ዳዊት','ማርታ','ዮሴፍ','ሰራዊት'))"
        . "+(SELECT COUNT(*) FROM guardians WHERE phone_primary IN ('0911000001','0911000002','0922000001','0922000002','0933000001','0933000002'))"
        . "+(SELECT COUNT(*) FROM acm_person_accounts WHERE user_id IN ({$uidP},{$uidS}))"
    )->fetchColumn();
    check('residue zero', 0, $residue);
    echo "  ok    seeded rows removed\n";
} catch (Throwable $e) {
    $failures[] = 'cleanup: ' . $e->getMessage();
    echo "  FAIL  cleanup: {$e->getMessage()}\n";
}

echo "\n{$checks} checks, " . count($failures) . " failures\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}
echo "WALKTHROUGH GREEN\n";
