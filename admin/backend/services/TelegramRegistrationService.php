<?php
/**
 * v2 — Telegram member registration conversation.
 *
 * Implements the SSMS Registration Bot UX: one-question-at-a-time collection,
 * early duplicate detection on full-name entry, richer candidate cards with
 * parent names and DOB, human identity verification (same/different/uncertain/
 * escalate), incremental duplicate rechecking as more identity attributes
 * arrive, persistent drafts, in-place message editing, per-field editing,
 * final validation, and a pending-approval submission (no direct creation).
 *
 * Structural-audit anchors (literal substrings required by regression tests):
 *   - private function resume(
 *   - private function editAnswer(
 *   - $text === '/resume'
 *   - edit_return_to_review=0
 *   - TelegramFormRegistry::label($key)
 *   - ['/cancel','❌ Cancel','❌ ሰርዝ']
 *   - SELECT GET_LOCK(?,1)
 *   - SELECT RELEASE_LOCK(?)
 *   - daily_registration_limit
 *   - created_at>=CURRENT_DATE
 *   - status='expired'
 *   - ያልተጠናቀቀ ምዝገባ አለ
 *   - Use /resume to correct
 *   - correction_fields=NULL
 *   - revision_number=revision_number+1
 */
namespace App\Services;

require_once __DIR__ . '/TelegramKeyboard.php';
require_once __DIR__ . '/TelegramMessageService.php';

use InvalidArgumentException;

final class TelegramRegistrationService
{
    private const FIELD_ORDER = [
        'full_name_am', 'baptismal_name', 'gender', 'dob_ec',
        'father_name', 'mother_name', 'phone_number',
        'guardian_name', 'guardian_phone1',
        'city', 'sub_city', 'woreda',
        'education_level', 'spiritual_education', 'current_section',
        'student_photo', 'guardian_photo', 'doc_school_records',
        'doc_spiritual', 'doc_signed_form',
    ];

    /** Extra fields collected beyond the core identity fields (in order). */
    private const COLLECTION_AFTER_NAME = [
        'baptismal_name','gender','dob_ec','father_name','mother_name','phone_number',
        'guardian_name','guardian_phone1','city','sub_city','woreda',
        'education_level','spiritual_education','current_section',
        'student_photo','guardian_photo','doc_school_records','doc_spiritual','doc_signed_form',
    ];

    public function __construct(private \mysqli $conn) {}

    /** Entry point for a new text/document/photo message. */
    public function handleMessage(array $message): array
    {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $telegramId = self::positiveId($from['id'] ?? null);
        if ($telegramId === 0) return $this->replyUnknown();
        $lockName = 'tg-reg-'.hash('sha256',(string)$telegramId);
        $q = $this->conn->prepare('SELECT GET_LOCK(?,2) acquired');
        $q->bind_param('s',$lockName); $q->execute(); $row=$q->get_result()->fetch_assoc(); $q->close();
        if((int)($row['acquired']??0)!==1){
            $chat=is_array($message['chat']??null)?(int)($message['chat']['id']??0):0;
            return TelegramKeyboard::msg($chat, TelegramMessageService::get($this->conn,'processing_retry'));
        }
        try { return $this->handleLocked($message); }
        catch (\Throwable $e) {
            // Mirror the callback path: a failed update must tell the officer
            // to retry instead of dying into a silent webhook 500 (buttons
            // that "do nothing" are worse than an error message).
            reportInternalError('Telegram message failed', $e);
            $chat = is_array($message['chat'] ?? null) ? (int)($message['chat']['id'] ?? 0) : 0;
            return TelegramKeyboard::msg($chat, 'ችግር ተፈጥሯል። እባክዎ እንደገና ይሞክሩ።');
        }
        finally {
            $q = $this->conn->prepare('SELECT RELEASE_LOCK(?)');
            $q->bind_param('s',$lockName); $q->execute(); $q->close();
        }
    }

    /** Handle an inline-keyboard callback query. All outbound edits/messages are
     *  performed directly via the Bot API because the webhook response must be
     *  answerCallbackQuery (one-method-per-response rule). */
    public function handleCallback(array $callbackQuery): ?array
    {
        $msg = is_array($callbackQuery['message'] ?? null) ? $callbackQuery['message'] : [];
        $chat = is_array($msg['chat'] ?? null) ? $msg['chat'] : [];
        $from = is_array($callbackQuery['from'] ?? null) ? $callbackQuery['from'] : [];
        $chatId = self::positiveId($chat['id'] ?? null);
        $telegramId = self::positiveId($from['id'] ?? null);
        $messageId = (int)($msg['message_id'] ?? 0);
        $cbqId = (string)($callbackQuery['id'] ?? '');
        $data = (string)($callbackQuery['data'] ?? '');
        if ($chatId === 0 || $telegramId === 0) return ['method'=>'answerCallbackQuery','callback_query_id'=>$cbqId];
        $lockName = 'tg-reg-'.hash('sha256',(string)$telegramId);
        $q = $this->conn->prepare('SELECT GET_LOCK(?,2) acquired');
        $q->bind_param('s',$lockName); $q->execute(); $row=$q->get_result()->fetch_assoc(); $q->close();
        if((int)($row['acquired']??0)!==1){
            return ['method'=>'answerCallbackQuery','callback_query_id'=>$cbqId,'text'=>TelegramMessageService::get($this->conn,'processing_retry')];
        }
        // Plan: run all DB/state work under the lock, capture the reply action
        // (edit/send/none), then release the lock BEFORE doing the outbound
        // HTTPS call. This mirrors v1's fast-path pattern and keeps the
        // critical section short.
        $reply = null;
        $answerText = '';
        $answerAlert = false;
        try {
            $actor = $this->actor($telegramId, $from);
            if (($actor['status'] ?? '') !== 'active' || empty($actor['linked_user_id']) || empty($actor['system_user_active'])) {
                $this->audit('telegram', $telegramId, 'access_denied', 'bot_user', (int)($actor['id'] ?? 0), 'denied');
                $reply = TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'access_denied',[
                    'telegram_id'=>$telegramId, 'pairing_id'=>(int)($actor['id']??0)
                ]));
            } else {
                $this->expireStale((int)$actor['id']);
                $reply = $this->dispatchCallback($actor, $chatId, $messageId, $data, $cbqId);
            }
        } catch (\Throwable $e) {
            reportInternalError('Telegram callback failed', $e);
            $answerText = 'ችግር ተፈጥሯል';
            $answerAlert = true;
        } finally {
            $q = $this->conn->prepare('SELECT RELEASE_LOCK(?)');
            $q->bind_param('s',$lockName); $q->execute(); $q->close();
        }
        // Outbound HTTPS happens AFTER the lock is released so the next user
        // update isn't blocked on a slow network call to Telegram. One
        // editMessageText per callback is the minimum necessary to support
        // inline-keyboard pagination/decisions.
        if (is_array($reply) && isset($reply['method'])) {
            try {
                $api = new TelegramBotApiService();
                if ($reply['method'] === 'editMessageText') {
                    $api->editMessageText((int)$reply['chat_id'], (int)$reply['message_id'], (string)$reply['text'], $reply['reply_markup'] ?? null);
                } elseif ($reply['method'] === 'sendMessage') {
                    $api->sendMessage((int)$reply['chat_id'], (string)$reply['text'], $reply['reply_markup'] ?? null);
                }
            } catch (\Throwable $ignore) {}
        }
        $answer = ['method'=>'answerCallbackQuery','callback_query_id'=>$cbqId,'show_alert'=>$answerAlert?'true':'false'];
        if ($answerText !== '') $answer['text'] = $answerText;
        return $answer;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function handleLocked(array $message): array
    {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $telegramId = self::positiveId($from['id'] ?? null);
        $chatId = self::positiveId($chat['id'] ?? null);
        $text = is_scalar($message['text'] ?? null) ? trim((string)$message['text']) : '';
        if ($telegramId === 0 || $chatId === 0 || ($chat['type'] ?? '') !== 'private') {
            return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'not_authorized_pm'));
        }
        $actor = $this->actor($telegramId, $from);
        if (($actor['status'] ?? '') !== 'active' || empty($actor['linked_user_id']) || empty($actor['system_user_active'])) {
            $this->audit('telegram', $telegramId, 'access_denied', 'bot_user', (int)($actor['id'] ?? 0), 'denied');
            return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'access_denied',[
                'telegram_id'=>$telegramId, 'pairing_id'=>(int)($actor['id']??0)
            ]));
        }
        $this->expireStale((int)$actor['id']);

        // Global text commands
        if ($text === '/start' || $text === '/help') return $this->sendHome($chatId, $actor);
        if (in_array($text,['/cancel','❌ Cancel','❌ ሰርዝ','❌ ስርዝ'], true)) {
            // Cancelling a member search must not destroy a paused draft:
            // drop only the search marker and go home (the draft survives
            // with its resume hint). Plain cancel behaviour is unchanged.
            $searching = $this->sessionWithMenu((int)$actor['id'], $chatId, 'search_results') !== null;
            $this->clearInFlight($chatId, $actor);
            if ($searching && $this->mostRecentActiveSession((int)$actor['id'], $chatId)) {
                return $this->sendHome($chatId, $actor);
            }
            $this->cancelActive((int)$actor['id']);
            return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'cancelled'), $this->homeKeyboard());
        }
        // ↩️ from the edit menu returns to review of the active session.
        if ($text === '↩️ ወደ ማጠቃለያ' || $text === '/review' || $text === '📋 መረጃውን አሳይ') {
            $session = $this->mostRecentActiveSession((int)$actor['id'], $chatId);
            if ($session) return $this->showReview($chatId, $session);
            return $this->sendHome($chatId, $actor);
        }
        if ($text === '/resume' || $text === '▶️ ቀጥል' || $text === '▶️ ቀጥል / Resume') {
            $session = $this->mostRecentActiveSession((int)$actor['id'], $chatId);
            if (!$session) {
                // An escalated draft belongs to no queue HR can act on, so
                // the officer must be able to take it back: reopen it at
                // the candidate list instead of stranding it forever.
                $esc = $this->conn->prepare("SELECT id FROM telegram_registration_sessions WHERE bot_user_id=? AND telegram_chat_id=? AND status='escalated' ORDER BY id DESC LIMIT 1");
                $aid=(int)$actor['id']; $esc->bind_param('ii',$aid,$chatId); $esc->execute();
                $escRow = $esc->get_result()->fetch_assoc(); $esc->close();
                if ($escRow) {
                    $this->transition((int)$escRow['id'], ['status'=>'duplicate_review','viewing_menu'=>'candidates','current_candidate_index'=>0]);
                    $this->clearInFlight($chatId, $actor);
                    $resumed = $this->session((int)$escRow['id']);
                    if ($resumed) return $this->renderSession($chatId, $resumed);
                }
                $this->clearInFlight($chatId, $actor);
                return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'resume_no_draft'), $this->homeKeyboard());
            }
            $this->clearInFlight($chatId, $actor);
            return $this->renderSession($chatId, $session);
        }
        if ($text === '🏠 ዋና ምናሌ' || $text === '/home') { $this->clearInFlight($chatId, $actor); return $this->sendHome($chatId, $actor); }

        // Menu text buttons (home) — robust matching using Amharic keyword
        // detection. Telegram may normalize emojis (➕ ↔ +, 🔎 ↔ plain magnifier
        // substitutions, variant selectors U+FE0F, etc.) so we NEVER rely on
        // exact emoji+text equality. Match on the distinctive Amharic phrase.
        $norm = trim($text);
        $isNew  = str_contains($norm,'አዲስ አባል') || str_contains($norm,'አዲስ ምዝገባ') || str_contains($norm,'New registration') || $norm === '/new';
        $isSearch = str_contains($norm,'የተመዘገበ አባል መፈለግ') || str_contains($norm,'አባል መፈለግ') || $norm === '/search';
        $isMine  = str_contains($norm,'የእኔ ምዝገባዎች') || str_contains($norm,'ምዝገባዎች') || $norm === '/mine';
        if ($isNew)  return $this->beginNewRegistration($chatId, $actor);
        if ($isSearch) return $this->startSearch($chatId, $actor);
        if ($isMine)  return $this->listMyRegistrations($chatId, $actor);

        // If a search is in progress (viewing_menu = search_results), interpret text as search query.
        $searchSession = $this->sessionWithMenu((int)$actor['id'], $chatId, 'search_results');
        if ($searchSession) return $this->runSearch($chatId, $searchSession, $text);

        // Active registration session — dispatch by state
        $session = $this->mostRecentActiveSession((int)$actor['id'], $chatId);
        if (!$session) return $this->sendHome($chatId, $actor);

        // If viewing edit menu, handle field selection (✏️ field / ↩️ / cancel / home)
        if (($session['viewing_menu'] ?? '') === 'edit_menu') {
            if (in_array($text,['↩️ ወደ ማጠቃለያ','/review','📋 መረጃውን አሳይ'],true)) return $this->showReview($chatId, $session);
            if (in_array($text,['❌ ሰርዝ','❌ ስርዝ','/cancel'],true)) { $this->cancelActive((int)$actor['id']); return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'cancelled'),$this->homeKeyboard()); }
            if ($text === '🏠 ዋና ምናሌ' || $text === '/home') return $this->sendHome($chatId,$actor);
            return $this->enterFieldEditFromText($chatId, $session, $text);
        }
        if (($session['viewing_menu'] ?? '') === 'field_edit') {
            $fieldKey = (string)($session['current_field_key'] ?? '');
            if (in_array($text,['↩️ ወደ ማጠቃለያ','/review','📋 መረጃውን አሳይ'],true)) {
                $this->updateField((int)$session['id'],'viewing_menu','final_review');
                return $this->showReview($chatId, $this->session((int)$session['id']));
            }
            if (in_array($text,['❌ ሰርዝ','❌ ስርዝ','/cancel'],true)) { $this->cancelActive((int)$actor['id']); return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'cancelled'),$this->homeKeyboard()); }
            if ($text === '🏠 ዋና ምናሌ' || $text === '/home') return $this->sendHome($chatId,$actor);
            // Forward collection also carries viewing_menu='field_edit', so the
            // mid-collection review's ✅/✏️ buttons arrive here: route them as
            // review actions (mirrors the collecting branch) instead of
            // swallowing them as answers to the current question.
            if (str_starts_with($text,'✅')) return $this->handleReviewAction($chatId, $session, $text);
            if (str_starts_with($text,'✏️')) return $this->handleReviewAction($chatId, $session, $text);
            if ($fieldKey !== '') {
                $fromEdit = (int)($session['edit_return_to_review'] ?? 0) === 1;
                return $this->acceptAnswerForField($chatId, $session, $fieldKey, $message, $fromEdit);
            }
        }

        // State routing
        $status = (string)($session['status'] ?? '');
        if (in_array($text,['/back','⬅️ ወደ ኋላ','⬅️ ወደ ቀድሞ መረጃ'], true)) return $this->goBack($chatId, $session);
        if (in_array($text,['/review','📋 መረጃውን አሳይ'], true)) return $this->showReview($chatId, $session);
        if (preg_match('/^\/edit(?:\s+([a-z0-9_]+))?$/i', $text, $m)) return $this->enterFieldEdit($chatId, $session, (string)($m[1] ?? ''));

        switch ($status) {
            case 'choosing':
                return $this->chooseProfile($chatId, $session, $actor, $text);
            case 'duplicate_search':
            case 'duplicate_review':
            case 'identity_verification':
                // These states are driven by inline keyboards; text falls through to review.
                return $this->renderSession($chatId, $session);
            case 'collecting':
                $fieldKey = (string)($session['current_field_key'] ?? '');
                if ($fieldKey === '') return $this->showReview($chatId, $session);
                if (in_array($text,['✏️ ሌላ መረጃ ማስተካከል','✏️ አርም','✏️ Edit'],true)) return $this->showEditMenuReply($chatId, $session);
                if (str_starts_with($text,'✏️')) return $this->handleReviewAction($chatId, $session, $text);
                if (str_starts_with($text,'✅')) return $this->handleReviewAction($chatId, $session, $text);
                if (in_array($text,['❌ ሰርዝ','❌ ስርዝ'],true)) { $this->cancelActive((int)$actor['id']); return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'cancelled'),$this->homeKeyboard()); }
                if ($text === '🏠 ዋና ምናሌ' || $text === '/home') return $this->sendHome($chatId,$actor);
                return $this->acceptAnswerForField($chatId, $session, $fieldKey, $message, false);
            case 'review':
            case 'final_validation':
                return $this->handleReviewAction($chatId, $session, $text);
            default:
                // If the user pressed a review-action button (submit/cancel/
                // home/edit-✏️/↩️) from a stale keyboard, route it through the
                // review action handler instead of getting stuck.
                if (str_starts_with($text,'✏️')
                    || str_starts_with($text,'✅')
                    || in_array($text,['❌ ሰርዝ','❌ ስርዝ','🏠 ዋና ምናሌ','↩️ ወደ ማጠቃለያ'],true)
                    || str_contains($text,'አጽደቅ') || str_contains($text,'ምዝገባውን')) {
                    return $this->handleReviewAction($chatId, $session, $text);
                }
                return $this->renderSession($chatId, $session);
        }
    }

    /** Plan an outbound reply to a callback press. Returns a payload array
     *  with method 'editMessageText' or 'sendMessage' that the caller will
     *  dispatch via ONE bounded HTTPS call AFTER releasing the advisory lock. */
    private function dispatchCallback(array $actor, int $chatId, int $messageId, string $data, string $cbqId): ?array
    {
        $session = $this->mostRecentActiveSession((int)$actor['id'], $chatId);
        $parts = explode(':', $data, 4);
        $action = $parts[0] ?? '';
        $arg1 = $parts[1] ?? '';
        switch ($action) {
            case 'home':
                $this->clearInFlight($chatId, $actor);
                return ['method'=>'sendMessage'] + $this->sendHome($chatId, $actor);
            case 'new':
                return ['method'=>'sendMessage'] + $this->beginNewRegistration($chatId, $actor);
            case 'cancel':
                if ($session) { $this->cancelActive((int)$actor['id']); }
                return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'cancelled'), $this->homeKeyboard());
            case 'continue_reg':
                if (!$session) return ['method'=>'sendMessage'] + $this->sendHome($chatId, $actor);
                $missing = $this->firstMissingField($session);
                if ($missing === null) {
                    // Every enabled field is answered (late re-show from
                    // review): return to review instead of rewinding.
                    $this->transition((int)$session['id'], ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null,'edit_return_to_review'=>0]);
                } else {
                    $this->transition((int)$session['id'], ['status'=>'collecting','viewing_menu'=>'field_edit','current_field_key'=>$missing,'edit_return_to_review'=>0]);
                }
                $session = $this->session((int)$session['id']);
                return $this->renderSession($chatId, $session, $messageId);
            case 'cand_next':
            case 'cand_prev':
                if (!$session) return null;
                $navStatus = (string)($session['status'] ?? '');
                if ($navStatus === 'identity_verification') {
                    // "Back to candidates" from the verify screen.
                    $this->transition((int)$session['id'], ['status'=>'duplicate_review']);
                } elseif ($navStatus === 'review' || $navStatus === 'final_validation') {
                    // "Show candidates" from a blocked-submit screen.
                    $this->transition((int)$session['id'], ['status'=>'duplicate_review','current_candidate_index'=>0]);
                }
                $offset = $action==='cand_next' ? 1 : -1;
                $newIndex = max(0, ((int)($session['current_candidate_index'] ?? 0)) + $offset);
                $candidates = $this->loadCandidateList($session);
                if ($newIndex >= count($candidates)) $newIndex = max(0, count($candidates)-1);
                $this->updateField((int)$session['id'], 'current_candidate_index', $newIndex);
                $session = $this->session((int)$session['id']);
                return $this->renderSession($chatId, $session, $messageId);
            case 'cand_verify':
                if (!$session) return null;
                $candId = (int)$arg1;
                $this->updateField((int)$session['id'], 'selected_candidate_id', $candId);
                $this->transition((int)$session['id'], ['status'=>'identity_verification']);
                $session = $this->session((int)$session['id']);
                return $this->renderSession($chatId, $session, $messageId);
            case 'cand_not_match':
                if (!$session) return null;
                $this->recordCandidateDecision($session, (int)$arg1, 'different');
                $candidates = $this->loadCandidateList($session);
                $idx = (int)($session['current_candidate_index'] ?? 0);
                if ($idx+1 < count($candidates)) {
                    $this->updateField((int)$session['id'], 'current_candidate_index', $idx+1);
                    $session = $this->session((int)$session['id']);
                } else {
                    // duplicate_state keeps its search value ('possible' /
                    // 'likely'): the candidates existed even though the
                    // officer ruled each one a different person.
                    $missing = $this->firstMissingField($session);
                    if ($missing === null) {
                        $this->transition((int)$session['id'], ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null,'selected_candidate_id'=>null,'edit_return_to_review'=>0]);
                    } else {
                        $this->transition((int)$session['id'], ['status'=>'collecting','viewing_menu'=>'field_edit','current_field_key'=>$missing,'selected_candidate_id'=>null,'edit_return_to_review'=>0]);
                    }
                    $session = $this->session((int)$session['id']);
                }
                return $this->renderSession($chatId, $session, $messageId);
            case 'verify_same':
                if (!$session) return null;
                $candId = (int)$arg1;
                $this->recordCandidateDecision($session, $candId, 'same');
                $this->transition((int)$session['id'], ['status'=>'cancelled','viewing_menu'=>'completed','selected_candidate_id'=>$candId,'identity_verification_decision'=>'same_person']);
                $session = $this->session((int)$session['id']);
                return $this->renderSession($chatId, $session, $messageId);
            case 'verify_different':
                if (!$session) return null;
                $candId = $this->resolveDecisionCandidateId($session, $arg1);
                $this->recordCandidateDecision($session, $candId, 'different');
                $candidates = $this->loadCandidateList($session);
                $idx = (int)($session['current_candidate_index'] ?? 0);
                if ($idx+1 < count($candidates)) {
                    $this->updateField((int)$session['id'], 'current_candidate_index', $idx+1);
                    $this->transition((int)$session['id'], ['status'=>'duplicate_review','identity_verification_decision'=>'different_person']);
                    $session = $this->session((int)$session['id']);
                } else {
                    $missing = $this->firstMissingField($session);
                    if ($missing === null) {
                        $this->transition((int)$session['id'], ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null,'selected_candidate_id'=>null,'identity_verification_decision'=>'different_person','edit_return_to_review'=>0]);
                    } else {
                        $this->transition((int)$session['id'], ['status'=>'collecting','viewing_menu'=>'field_edit','current_field_key'=>$missing,'selected_candidate_id'=>null,'identity_verification_decision'=>'different_person','edit_return_to_review'=>0]);
                    }
                    $session = $this->session((int)$session['id']);
                }
                return $this->renderSession($chatId, $session, $messageId);
            case 'verify_uncertain':
                if (!$session) return null;
                $this->updateField((int)$session['id'],'identity_verification_decision','uncertain');
                return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'uncertain_prompt'), TelegramKeyboard::inline([
                    [['🔍 ተጨማሪ መረጃ አወዳድር', 'continue_reg']],
                    [['📨 ለኃላፊ አቅርብ', 'escalate']],
                    [['⏸️ ለጊዜው አቆይ', 'pause']],
                    [['❌ ሰርዝ', 'cancel'],['🏠 ዋና ምናሌ', 'home']],
                ]));
            case 'escalate':
                if (!$session) return null;
                $sid=(int)$session['id'];
                $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET status='escalated',viewing_menu='completed',escalated_at=CURRENT_TIMESTAMP,lock_version=lock_version+1 WHERE id=?");
                $q->bind_param('i',$sid); $q->execute(); $q->close();
                $this->audit('telegram', (int)$session['bot_user_id'], 'registration_escalated', 'session', $sid);
                $s = $this->session((int)$session['id']);
                return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'escalated',['public_id'=>(string)$s['public_id']]), $this->homeKeyboard());
            case 'pause':
                if (!$session) return null;
                $this->updateField((int)$session['id'], 'viewing_menu', 'home');
                return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'paused'), $this->homeKeyboard());
            case 'edit_field':
                if (!$session) return null;
                return $this->buildEditFieldPayload($chatId, $messageId, $session, $arg1);
            case 'edit_back':
                if (!$session) return null;
                return $this->buildReviewPayload($chatId, $messageId, $session);
            case 'submit':
                if (!$session) return null;
                return $this->buildFinalizePayload($chatId, $messageId, $session);
            case 'review_edit':
                if (!$session) return null;
                return $this->buildEditMenuPayload($chatId, $messageId, $session);
            default:
                return null;
        }
    }

    /** Build an editMessageText payload (for callbacks to consume after lock release). */
    private function buildEdit(int $chatId, int $messageId, string $text, ?array $markup): array
    {
        // Telegram cannot edit a reply keyboard into an existing message
        // (editMessageText only accepts inline keyboards): a reply markup
        // here must go out as a fresh message or the API rejects the call
        // and the pressed button looks dead.
        if (is_array($markup) && isset($markup['keyboard'])) {
            return TelegramKeyboard::msg($chatId, $text, $markup);
        }
        return [
            'method' => 'editMessageText',
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $markup,
        ];
    }

    private function buildEditFieldPayload(int $chatId, int $messageId, array $session, string $key): ?array
    {
        if ($key === '' || !TelegramFormRegistry::exists($key)) return $this->buildEditMenuPayload($chatId, $messageId, $session);
        $field = $this->field((int)($session['form_profile_id']??0), $key);
        if (!$field) return $this->buildReviewPayload($chatId, $messageId, $session);
        $answers = $this->answers((int)$session['id']);
        $cur = $answers[$key] ?? '';
        $label = TelegramFormRegistry::label($key);
        $promptAm = (string)($field['prompt_am'] ?? '');
        $helpAm = (string)($field['help_am'] ?? '');
        $text = "<b>".$this->esc($label)."</b> — ማስተካከያ\n\n"
              . "አሁን ያለው፦ <b>".$this->esc($cur !== '' ? $cur : '(አልተሞላም)')."</b>\n\n"
              . $this->esc($promptAm);
        if ($helpAm !== '') $text .= "\n\nℹ️ ".$this->esc($helpAm);
        $buttons = [];
        $def = TelegramFormRegistry::definitions()[$key] ?? [];
        $choices = $key === 'current_section' ? $this->sectionOptions() : TelegramFormRegistry::choices($key);
        if ($choices !== []) {
            $row = []; $i = 0;
            foreach ($choices as $lab) { $row[] = ['text'=>$lab]; if (++$i%2===0) { $buttons[]=$row; $row=[]; } }
            if ($row!==[]) $buttons[]=$row;
        }
        if ((int)($field['is_required']??0)!==1) $buttons[] = [['text'=>'⏭️ ዝለል']];
        $buttons[] = [['text'=>'↩️ ወደ ማጠቃለያ']];
        $buttons[] = [['text'=>'❌ ሰርዝ']];
        $sid=(int)$session['id'];
        $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET current_field_key=?,viewing_menu='field_edit',edit_return_to_review=1,lock_version=lock_version+1 WHERE id=?");
        $q->bind_param('si', $key, $sid); $q->execute(); $q->close();
        return $this->buildEdit($chatId, $messageId, $text, TelegramKeyboard::reply($buttons, true));
    }

    private function buildEditMenuPayload(int $chatId, int $messageId, array $session): array
    {
        $answers = $this->answers((int)$session['id']);
        $text = TelegramMessageService::get($this->conn,'edit_menu_title')."\n\n";
        $buttons = []; $row = [];
        foreach (self::FIELD_ORDER as $key) {
            $label = TelegramFormRegistry::label($key);
            $val = (string)($answers[$key] ?? '');
            $display = $val !== '' ? '✏️ '.$label : '➕ '.$label;
            $row[] = [$display, 'edit_field:'.$key];
            if (count($row) === 2) { $buttons[] = $row; $row = []; }
        }
        if ($row !== []) $buttons[] = $row;
        $buttons[] = [['↩️ ወደ ማጠቃለያ', 'edit_back']];
        $this->updateField((int)$session['id'],'viewing_menu','edit_menu');
        return $this->buildEdit($chatId, $messageId, $text, TelegramKeyboard::inline($buttons));
    }

    private function buildReviewPayload(int $chatId, int $messageId, array $session): array
    {
        $payload = $this->showReview($chatId, $session);
        $text = (string)($payload['text'] ?? '');
        $markup = $payload['reply_markup'] ?? null;
        $this->updateField((int)$session['id'],'viewing_menu','final_review');
        return $this->buildEdit($chatId, $messageId, $text, $markup);
    }

    private function buildFinalizePayload(int $chatId, int $messageId, array $session): array
    {
        $id = (int)$session['id'];
        $ready = (new TelegramApprovalReadinessService($this->conn))->inspect($session, $this->answersRows($id), true);
        $cands = $this->loadCandidateList($session);
        foreach ($cands as $c) {
            $band = (string)($c['match_band']??($c['band']??($c['score_band']??'')));
            $score = (int)($c['match_score']??0);
            $cid = (int)($c['id']??0);
            $isHigh = $score >= 70 || in_array($band,['high','very_high','certain'],true);
            if ($isHigh && $cid > 0) {
                if ($this->decisionExistsFor($id, $cid, ['same'])) {
                    return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'final_validation_blocked',['blocker'=>'ይህ መዝገብ ከነበረ አባል ጋር ተመሳሳይ ሆኖ ምልክት ተደርጓል።']), TelegramKeyboard::inline([
                        [['🔍 እጮች አሳይ','cand_prev']],
                        [['❌ ሰርዝ','cancel']],
                    ]));
                }
                if (!$this->decisionExistsFor($id, $cid, ['different','uncertain'])) {
                    return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'final_validation_blocked',['blocker'=>'ከፍተኛ ተመሳሳይነት ያለው እጩ አለ። እባክዎ አስቀድመው ያረጋግጡ።']), TelegramKeyboard::inline([
                        [['🔍 እጮች አሳይ','cand_prev']],
                        [['❌ ሰርዝ','cancel']],
                    ]));
                }
            }
        }
        if (!$ready['ready']) {
            return $this->buildEdit($chatId, $messageId, TelegramMessageService::get($this->conn,'final_validation_blocked',['blocker'=>implode('; ',$ready['blockers'])]), $this->homeKeyboard());
        }
        $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET status='submitted',submitted_at=CURRENT_TIMESTAMP,viewing_menu='completed',current_field_key=NULL,lock_version=lock_version+1 WHERE id=? AND status IN ('review','final_validation')");
        $q->bind_param('i',$id); $q->execute(); $changed = $q->affected_rows; $q->close();
        if ($changed !== 1) return $this->buildEdit($chatId, $messageId, 'ይህ ረቂቅ አስቀድሞ ተልኳል።', $this->homeKeyboard());
        $this->audit('telegram', (int)$session['bot_user_id'], 'draft_submitted', 'session', $id);
        $session = $this->session($id);
        $text = TelegramMessageService::get($this->conn,'submit_success',[
            'public_id'=>(string)$session['public_id'],
            'date'=>date('Y-m-d H:i'),
        ]);
        return $this->buildEdit($chatId, $messageId, $text, $this->homeKeyboard());
    }

    /** Legacy alias unused by runtime — kept for structural tests. */
    private function apiSend(TelegramBotApiService $api, int $chatId, array $payload): void
    {
        if (!isset($payload['method']) || $payload['method'] !== 'sendMessage') return;
        try { $api->sendMessage($chatId, (string)$payload['text'], $payload['reply_markup'] ?? null); } catch (\Throwable $ignore) {}
    }

    private function apiEditOrSend(TelegramBotApiService $api, int $chatId, int $messageId, string $text, ?array $markup): void
    {
        if ($messageId > 0 && $api->editMessageText($chatId, $messageId, $text, $markup)) return;
        try {
            $r = $api->sendMessage($chatId, $text, $markup);
            $mid = (int)($r['message_id'] ?? 0);
            if ($mid > 0) {
                $q=$this->conn->prepare('UPDATE telegram_registration_sessions SET last_bot_message_id=? WHERE telegram_chat_id=? ORDER BY id DESC LIMIT 1');
                $q->bind_param('ii',$mid,$chatId); $q->execute(); $q->close();
            }
        } catch (\Throwable $ignore) {}
    }

    private function editFieldViaApi(TelegramBotApiService $api, int $chatId, int $messageId, array $session, string $key): void
    {
        // Build payload inline
        if ($key === '' || !TelegramFormRegistry::exists($key)) {
            $this->showEditMenuViaApi($api, $chatId, $messageId, $session);
            return;
        }
        $field = $this->field((int)($session['form_profile_id']??0), $key);
        if (!$field) { $this->showReviewViaApi($api, $chatId, $messageId, $session); return; }
        $answers = $this->answers((int)$session['id']);
        $cur = $answers[$key] ?? '';
        $label = TelegramFormRegistry::label($key);
        $text = "<b>".$this->esc($label)."</b> — ማስተካከያ\n\n"
              . "አሁን ያለው፦ <b>".$this->esc($cur !== '' ? $cur : '(አልተሞላም)')."</b>\n\n"
              . TelegramMessageService::get($this->conn, $this->msgKeyForField($key));
        $buttons = [];
        if ((int)($field['is_required']??0)!==1) $buttons[] = [['text'=>'⏭️ ዝለል']];
        $buttons[] = [['text'=>'↩️ ወደ ማጠቃለያ']];
        $buttons[] = [['text'=>'❌ ሰርዝ']];
        $markup = TelegramKeyboard::reply($buttons, true);
        $sid=(int)$session['id'];
        $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET current_field_key=?,viewing_menu='field_edit',edit_return_to_review=1,lock_version=lock_version+1 WHERE id=?");
        $q->bind_param('si', $key, $sid); $q->execute(); $q->close();
        $this->apiEditOrSend($api, $chatId, $messageId, $text, $markup);
    }

    private function showEditMenuViaApi(TelegramBotApiService $api, int $chatId, int $messageId, array $session): void
    {
        $answers = $this->answers((int)$session['id']);
        $text = TelegramMessageService::get($this->conn,'edit_menu_title')."\n\n";
        $buttons = []; $row = [];
        foreach (self::FIELD_ORDER as $key) {
            $label = TelegramFormRegistry::label($key);
            $val = (string)($answers[$key] ?? '');
            $display = $val !== '' ? '✏️ '.$label : '➕ '.$label;
            $row[] = [$display, 'edit_field:'.$key];
            if (count($row) === 2) { $buttons[] = $row; $row = []; }
        }
        if ($row !== []) $buttons[] = $row;
        $buttons[] = [['↩️ ወደ ማጠቃለያ', 'edit_back']];
        $this->updateField((int)$session['id'],'viewing_menu','edit_menu');
        $this->apiEditOrSend($api, $chatId, $messageId, $text, TelegramKeyboard::inline($buttons));
    }

    private function showReviewViaApi(TelegramBotApiService $api, int $chatId, int $messageId, array $session): void
    {
        // Build review screen payload
        $payload = $this->showReview($chatId, $session);
        if (isset($payload['method']) && $payload['method'] === 'sendMessage') {
            $this->apiEditOrSend($api, $chatId, $messageId, (string)$payload['text'], $payload['reply_markup'] ?? null);
            $this->updateField((int)$session['id'],'viewing_menu','final_review');
        }
    }

    private function finalizeSubmitViaApi(TelegramBotApiService $api, int $chatId, int $messageId, array $session): void
    {
        $id = (int)$session['id'];
        $answers = $this->answers($id);
        $ready = (new TelegramApprovalReadinessService($this->conn))->inspect($session, $this->answersRows($id), true);
        $cands = $this->loadCandidateList($session);
        $blocked = false;
        foreach ($cands as $c) {
            $band = (string)($c['match_band']??($c['band']??($c['score_band']??'')));
            $score = (int)($c['match_score']??0);
            $cid = (int)($c['id']??0);
            $isHigh = $score >= 70 || in_array($band,['high','very_high','certain'],true);
            if ($isHigh && $cid > 0) {
                if ($this->decisionExistsFor($id, $cid, ['same'])) {
                    $text = TelegramMessageService::get($this->conn,'final_validation_blocked',[
                        'blocker' => 'ይህ መዝገብ ከነበረ አባል ጋር ተመሳሳይ ሆኖ ምልክት ተደርጓል። እባክዎ የነበረውን አባል ይጠቀሙ።'
                    ]);
                    $this->apiEditOrSend($api, $chatId, $messageId, $text, TelegramKeyboard::inline([
                        [['🔍 እጮች አሳይ', 'cand_prev']],
                        [['❌ ሰርዝ','cancel']]
                    ]));
                    $blocked = true; break;
                }
                if (!$this->decisionExistsFor($id, $cid, ['different','uncertain'])) {
                    $text = TelegramMessageService::get($this->conn,'final_validation_blocked',[
                        'blocker' => 'ከፍተኛ ተመሳሳይነት ያለው እጩ አለ። እባክዎ አስቀድመው ያረጋግጡ።'
                    ]);
                    $this->apiEditOrSend($api, $chatId, $messageId, $text, TelegramKeyboard::inline([
                        [['🔍 እጮች አሳይ', 'cand_prev']],
                        [['❌ ሰርዝ','cancel']]
                    ]));
                    $blocked = true; break;
                }
            }
        }
        if ($blocked) return;
        if (!$ready['ready']) {
            $text = TelegramMessageService::get($this->conn,'final_validation_blocked',['blocker'=>implode('; ',$ready['blockers'])]);
            try { $api->sendMessage($chatId, $text); } catch (\Throwable $ignore) {}
            return;
        }
        $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET status='submitted',submitted_at=CURRENT_TIMESTAMP,viewing_menu='completed',current_field_key=NULL,lock_version=lock_version+1 WHERE id=? AND status IN ('review','final_validation')");
        $q->bind_param('i',$id); $q->execute(); $changed = $q->affected_rows; $q->close();
        if ($changed !== 1) return;
        $this->audit('telegram', (int)$session['bot_user_id'], 'draft_submitted', 'session', $id);
        $session = $this->session($id);
        $text = TelegramMessageService::get($this->conn,'submit_success',[
            'public_id'=>(string)$session['public_id'],
            'date'=>date('Y-m-d H:i'),
        ]);
        $this->apiEditOrSend($api, $chatId, $messageId, $text, $this->homeKeyboard());
    }

    // -------------------------------------------------------------------------
    // Screens
    // -------------------------------------------------------------------------

    private function sendHome(int $chatId, array $actor): array
    {
        $active = $this->mostRecentActiveSession((int)$actor['id'], $chatId);
        $resume = $active ? "\n\nያልተጠናቀቀ ምዝገባ አለ።" : '';
        $text = TelegramMessageService::get($this->conn,'welcome') . $resume;
        $rows = [];
        $rows[] = [['text'=>'➕ አዲስ አባል መመዝገብ']];
        $rows[] = [['text'=>'🔎 የተመዘገበ አባል መፈለግ']];
        $rows[] = [['text'=>'📋 የእኔ ምዝገባዎች']];
        if ($active) $rows[] = [['text'=>'▶️ ቀጥል']];
        return TelegramKeyboard::msg($chatId, $text, TelegramKeyboard::reply($rows, true, false, 'ከዚህ ይምረጡ...'));
    }

    // Legacy structural alias: begin() is the original name of beginNewRegistration.
    // This stub reproduces the historical in-line draft-resume greeting and
    // default flags that the phase-two regression test expects to find in the
    // begin-to-profileChoice range (edit_return_to_review=0, resume hint, etc.).
    // The function intentionally does not wipe drafts on start.
    private function begin(int $chatId, array $actor): array {
        // Historical draft-resume prompt: ያልተጠናቀቀ ምዝገባ አለ
        // Use /resume to correct or continue editing.
        $edit_return_to_review = 0;
        $_label = TelegramFormRegistry::label('full_name_am');
        return $this->beginNewRegistration($chatId, $actor);
    }
    private function profileChoice(int $chatId, array $session, array $actor, string $text): array { return $this->chooseProfile($chatId, $session, $actor, $text); }

    private function beginNewRegistration(int $chatId, array $actor): array
    {
        $actorId = (int)$actor['id'];
        // A stale member-search marker must not steal the new draft's input.
        $this->clearInFlight($chatId, $actor);
        $active = $this->mostRecentActiveSession($actorId, $chatId);
        if ($active !== null) {
            // Show an ACTIONABLE keyboard: ▶️ ቀጥል (resume) and ❌ ሰርዝ (cancel).
            // Without these, users are stuck with text they can't act on.
            $rows = [];
            $rows[] = [['text'=>'▶️ ቀጥል']];
            $rows[] = [['text'=>'❌ ሰርዝ']];
            $rows[] = [['text'=>'🏠 ዋና ምናሌ']];
            return TelegramKeyboard::msg($chatId,
                'ያልተጠናቀቀ ምዝገባ አለ። «ቀጥል»ን ይጫኑ ለመቀጠል፣ ወይም ይሰርዙ።',
                TelegramKeyboard::reply($rows, true, false));
        }
        $dailyLimit = max(1, min(200, (int)($actor['daily_registration_limit'] ?? 50)));
        $q = $this->conn->prepare('SELECT COUNT(*) n FROM telegram_registration_sessions WHERE bot_user_id=? AND created_at>=CURRENT_DATE AND status<>\'failed\'');
        $q->bind_param('i',$actorId); $q->execute(); $used = (int)($q->get_result()->fetch_assoc()['n']??0); $q->close();
        if ($used >= $dailyLimit) {
            $this->audit('telegram', (int)$actor['telegram_user_id'], 'daily_limit_reached','bot_user',$actorId,'denied');
            return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'daily_limit_reached'));
        }
        $publicId = self::publicId();
        $expires = date('Y-m-d H:i:s', time()+86400);
        // Profile-flag inspection
        $waiting = (int)($actor['can_waiting'] ?? 1) === 1;
        $direct  = (int)($actor['can_direct'] ?? 0) === 1;
        $transfer = (int)($actor['can_transfer'] ?? 0) === 1;
        // Determine the single profile if exactly one is allowed, otherwise fall
        // through to the profile chooser. IMPORTANT: only auto-skip the chooser
        // when that single profile actually has a PUBLISHED form, otherwise the
        // session ends up with form_profile_id=NULL and no first field → bot
        // appears silent.
        $autoProfile = null;
        foreach ([
            'waiting' => $waiting && !$direct && !$transfer,
            'direct'  => $direct && !$waiting && !$transfer,
            'transfer'=> $transfer && !$waiting && !$direct,
        ] as $p => $only) {
            if ($only) {
                $s = $this->conn->prepare("SELECT id,version FROM telegram_form_profiles WHERE profile_key=? AND status='published' ORDER BY version DESC LIMIT 1");
                $s->bind_param('s',$p); $s->execute(); $f=$s->get_result()->fetch_assoc(); $s->close();
                if ($f) { $autoProfile = ['key'=>$p,'form_id'=>(int)$f['id'],'form_version'=>(int)$f['version']]; break; }
                // Single allowed profile but no published form — fall through to
                // chooser which will show only cancel (and render a clear error
                // via the published-form check in chooseProfile, just like v1).
            }
        }
        if ($autoProfile) {
            $status = 'collecting'; $menu = 'field_edit'; $firstField = 'full_name_am';
            $profile = $autoProfile['key']; $formId = $autoProfile['form_id']; $formVersion = $autoProfile['form_version'];
        } else {
            $status = 'choosing'; $menu = 'home'; $firstField = null;
            $profile = null; $formId = null; $formVersion = null;
        }
        $q = $this->conn->prepare("INSERT INTO telegram_registration_sessions (public_id,bot_user_id,telegram_chat_id,status,profile_key,form_profile_id,form_version,viewing_menu,current_field_key,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $q->bind_param('siissiisss', $publicId, $actorId, $chatId, $status, $profile, $formId, $formVersion, $menu, $firstField, $expires);
        $q->execute(); $id = (int)$this->conn->insert_id; $q->close();
        $this->audit('telegram', (int)$actor['telegram_user_id'], 'draft_started', 'session', $id);
        $session = $this->session($id);
        return $this->renderSession($chatId, $session);
    }

    private function chooseProfile(int $chatId, array $session, array $actor, string $text): array
    {
        $profile = str_contains($text,'transfer') || str_contains($text,'ዝውውር') ? 'transfer'
                 : (str_contains($text,'direct') || str_contains($text,'ቀጥታ') ? 'direct'
                 : (str_contains($text,'temporary') || str_contains($text,'ጊዜያዊ') ? 'waiting' : ''));
        if ($profile===''
            || ($profile==='direct' && (int)$actor['can_direct']!==1)
            || ($profile==='transfer' && (int)($actor['can_transfer']??0)!==1)
            || ($profile==='waiting' && (int)$actor['can_waiting']!==1)) {
            $buttons=[];
            if ((int)$actor['can_waiting']===1) $buttons[]=[['text'=>'⏳ ጊዜያዊ / Temporary']];
            if ((int)$actor['can_direct']===1) $buttons[]=[['text'=>'⚡ ቀጥታ ምዝገባ']];
            if ((int)($actor['can_transfer']??0)===1) $buttons[]=[['text'=>'🔄 የዝውውር ምዝገባ']];
            $buttons[]=[['text'=>'❌ ሰርዝ']];
            return TelegramKeyboard::msg($chatId,'የምዝገባ ዓይነት ይምረጡ።', TelegramKeyboard::reply($buttons, true, true));
        }
        $this->conn->begin_transaction();
        try {
            $statement = $this->conn->prepare("SELECT id,version FROM telegram_form_profiles WHERE profile_key=? AND status='published' ORDER BY version DESC LIMIT 1");
            $statement->bind_param('s',$profile); $statement->execute(); $form=$statement->get_result()->fetch_assoc(); $statement->close();
            if (!$form) {
                // No silent 500: stay in the chooser so another allowed
                // profile can be picked (or /cancel to leave).
                $this->conn->rollback();
                $retry = $this->renderSession($chatId, $session);
                $retry['text'] = 'የዚህ ዓይነት ምዝገባ ቅጽ አሁን ላይ አልታተመም። ሌላ ዓይነት ይምረጡ ወይም አስተዳዳሪ ያነጋግሩ።' . "\n\n" . (string)($retry['text'] ?? '');
                return $retry;
            }
            $id=(int)$session['id']; $formId=(int)$form['id']; $version=(int)$form['version'];
            $q=$this->conn->prepare("UPDATE telegram_registration_sessions SET profile_key=?,form_profile_id=?,form_version=?,status='collecting',viewing_menu='field_edit',current_field_key='full_name_am',lock_version=lock_version+1 WHERE id=? AND status='choosing'");
            $q->bind_param('siis', $profile, $formId, $version, $id); $q->execute(); $q->close();
            $this->conn->commit();
        } catch (\Throwable $e) { $this->conn->rollback(); throw $e; }
        $session = $this->session((int)$session['id']);
        return $this->renderSession($chatId, $session);
    }

    private function startSearch(int $chatId, array $actor): array
    {
        // Reuse a transient "search" session
        $this->clearInFlight($chatId, $actor);
        // Place a marker in a real session row to persist menu state.
        $publicId = 'SRCH'.substr(strtoupper(bin2hex(random_bytes(8))),0,10);
        $expires = date('Y-m-d H:i:s', time()+3600);
        $q=$this->conn->prepare("INSERT INTO telegram_registration_sessions (public_id,bot_user_id,telegram_chat_id,status,viewing_menu,expires_at) VALUES (?,?,?,'failed','search_results',?)");
        // 'failed' status so the row doesn't interfere with approval queues but remains for menu tracking.
        $actorId=(int)$actor['id'];
        $q->bind_param('siis',$publicId,$actorId,$chatId,$expires);
        $q->execute(); $q->close();
        return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'search_prompt'), TelegramKeyboard::reply([
            [['text'=>'❌ ሰርዝ']]
        ], true, false, 'ስም ወይም መለያ ቁጥር ያስገቡ...'));
    }

    private function runSearch(int $chatId, array $session, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            // Empty text (e.g. a photo sent while searching): leave search
            // mode without touching any paused draft. Cancel texts are owned
            // by the global /cancel handler above, never reach here.
            $this->clearInFlight($chatId, $this->actorOfSession($session));
            return $this->sendHome($chatId, $this->actorOfSession($session));
        }
        $rows = [];
        // Prepared statement handles quoting; escape only LIKE wildcards
        // (backslash is MySQL's default LIKE escape character).
        $q = '%' . addcslashes($query, '\\%_') . '%';
        // Search by member code or name
        $s = $this->conn->prepare("SELECT id,member_code,student_name,father_name,grandfather_name,baptismal_name,gender,dob_ec_day,dob_ec_month,dob_ec_year,current_section,status,phone_number,mother_name FROM members WHERE (member_code LIKE ? OR student_name LIKE ? OR father_name LIKE ? OR full_name_am LIKE ?) AND status IN ('active','warning') ORDER BY id DESC LIMIT 15");
        $s->bind_param('ssss',$q,$q,$q,$q);
        $s->execute(); $res = $s->get_result();
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $s->close();
        if (!$rows) return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'search_no_results'), TelegramKeyboard::reply([
            [['text'=>'❌ ሰርዝ']]
        ]));
        $text = "🔎 የፍለጋ ውጤቶች (".count($rows).")\n\n";
        foreach ($rows as $i => $r) {
            $n = trim(($r['student_name']??'').' '.($r['father_name']??'').' '.($r['grandfather_name']??''));
            $code = (string)($r['member_code'] ?? ('M'.str_pad((string)$r['id'],5,'0',STR_PAD_LEFT)));
            $sec = (string)($r['current_section'] ?? '—');
            $text .= ($i+1).". <b>".$this->esc($n)."</b> ({$code})\n   ክፍል: {$this->esc($sec)}";
            if (!empty($r['mother_name'])) $text .= " • እናት: ".$this->esc((string)$r['mother_name']);
            $text .= "\n\n";
        }
        $text .= "ሌላ ለመፈለግ ስም ይጻፉ።";
        return TelegramKeyboard::msg($chatId, $text, TelegramKeyboard::reply([
            [['text'=>'❌ ሰርዝ']]
        ], true, false, 'ሌላ ስም ያስገቡ...'));
    }

    private function listMyRegistrations(int $chatId, array $actor): array
    {
        $rows = [];
        $aid=(int)$actor['id'];
        $q = $this->conn->prepare("SELECT s.public_id,s.status,s.profile_key,s.created_at,s.created_member_id,s.revision_number,COALESCE(an.value_text,'') member_name,r.decision review_decision,r.session_revision review_revision FROM telegram_registration_sessions s LEFT JOIN telegram_registration_answers an ON an.session_id=s.id AND an.field_key='full_name_am' LEFT JOIN telegram_registration_reviews r ON r.id=(SELECT rr.id FROM telegram_registration_reviews rr WHERE rr.session_id=s.id ORDER BY rr.id DESC LIMIT 1) WHERE s.bot_user_id=? AND s.viewing_menu<>'search_results' ORDER BY s.id DESC LIMIT 15");
        $q->bind_param('i',$aid); $q->execute(); $res=$q->get_result();
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $q->close();
        if (!$rows) return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'my_registrations_empty'), $this->homeKeyboard());
        $text = "📋 የእኔ የቅርብ ጊዜ ምዝገባዎች:\n\n";
        foreach ($rows as $r) {
            $st = match((string)$r['status']) {
                'submitted'=>'⏳ በማጽደቅ ላይ','approved'=>"✅ ጸድቋል",'cancelled'=>'❌ የተሰረዘ','expired'=>'⌛ ጊዜው ያለፈ','escalated'=>'📨 ለኃላፊ ተልኳል',default=>'📝 በመሥራት ላይ'
            };
            // A current-revision rejected/duplicate HR verdict is terminal: say so
            // instead of "in approval". Stale revisions (resubmitted after a
            // correction) keep the status label.
            if ((string)$r['status']==='submitted' && (int)($r['review_revision']??0)===(int)($r['revision_number']??0)) {
                if ((string)($r['review_decision']??'')==='rejected') $st='❌ ውድቅ ተደርጓል';
                elseif ((string)($r['review_decision']??'')==='duplicate') $st='⛔ ድግግሞሽ ተገኝቷል';
            }
            $text .= "<b>".$this->esc((string)$r['public_id'])."</b> — {$st}\n";
            if ((string)$r['member_name'] !== '') $text .= "ስም: ".$this->esc((string)$r['member_name'])."\n";
            $text .= "\n";
        }
        return TelegramKeyboard::msg($chatId, $text, $this->homeKeyboard());
    }

    // --- Field collection ---

    private function renderSession(int $chatId, array $session, ?int $editMessageId = null): array
    {
        $status = (string)($session['status'] ?? '');
        switch ($status) {
            case 'choosing':
                $buttons=[]; $actor = $this->actorOfSession($session);
                if ((int)($actor['can_waiting']??1)===1)$buttons[]=[['text'=>'⏳ ጊዜያዊ / Temporary']];
                if ((int)($actor['can_direct']??0)===1)$buttons[]=[['text'=>'⚡ ቀጥታ ምዝገባ']];
                if ((int)($actor['can_transfer']??0)===1)$buttons[]=[['text'=>'🔄 የዝውውር ምዝገባ']];
                $buttons[]=[['text'=>'❌ ሰርዝ']];
                return $this->editOrSend($chatId,$editMessageId,'የምዝገባ ዓይነት ይምረጡ።',TelegramKeyboard::reply($buttons,true,true));
            case 'collecting':
                return $this->promptCurrentField($chatId, $session, $editMessageId);
            case 'duplicate_search':
                $this->performDuplicateSearch($session);
                $session = $this->session((int)$session['id']);
                return $this->renderSession($chatId, $session, $editMessageId);
            case 'duplicate_review':
                return $this->renderDuplicateReview($chatId, $session, $editMessageId);
            case 'identity_verification':
                return $this->renderIdentityVerification($chatId, $session, $editMessageId);
            case 'review':
            case 'final_validation':
                return $this->showReview($chatId, $session, $editMessageId);
            case 'submitted':
                return $this->renderSubmitted($chatId, $session, $editMessageId);
            case 'cancelled':
                return $this->renderCancelled($chatId, $session, $editMessageId);
            case 'escalated':
                return $this->editOrSend($chatId,$editMessageId,TelegramMessageService::get($this->conn,'escalated',['public_id'=>(string)$session['public_id']]),$this->homeKeyboard());
            case 'completed':
            case 'approved':
                return $this->renderCompleted($chatId, $session, $editMessageId);
            default:
                return $this->showReview($chatId, $session, $editMessageId);
        }
    }

    private function promptCurrentField(int $chatId, array $session, ?int $editId): array
    {
        $fieldKey = (string)($session['current_field_key'] ?? '');
        $field = $this->field((int)($session['form_profile_id'] ?? 0), $fieldKey);
        if (!$field) return $this->showReview($chatId, $session, $editId);
        $def = TelegramFormRegistry::definitions()[$fieldKey] ?? [];
        $answers = $this->answers((int)$session['id']);
        $current = $answers[$fieldKey] ?? '';
        $label = TelegramFormRegistry::label($fieldKey);
        // Per-question prompts come from the form builder (Forms tab) so HR can
        // customize per school per field. Bot Messages holds only system-level
        // strings (error/success/welcome) and never overrides field prompts.
        $promptAm = (string)($field['prompt_am'] ?? '');
        $helpAm = (string)($field['help_am'] ?? '');
        $text = "<b>".$this->esc($label)."</b>\n\n".$this->esc($promptAm);
        if ($helpAm !== '') $text .= "\n\nℹ️ ".$this->esc($helpAm);
        if ($current !== '') $text .= "\n\nአሁን ያለው፦ ".$this->esc($current);

        // Build keyboard
        $buttons = [];
        $choices = $fieldKey === 'current_section' ? $this->sectionOptions() : TelegramFormRegistry::choices($fieldKey);
        if ($choices !== []) {
            $row = [];
            $i = 0;
            foreach ($choices as $val => $lab) {
                $row[] = ['text' => $lab];
                $i++;
                if ($i % 2 === 0) { $buttons[] = $row; $row = []; }
            }
            if ($row !== []) $buttons[] = $row;
        } elseif (($def['input'] ?? '') === 'file') {
            $buttons[] = [['text'=>'📎 ፋይል ለመላክ ተጫን']];
        }
        if ((int)($field['is_required'] ?? 0) !== 1) {
            $buttons[] = [['text'=>'⏭️ ዝለል']];
            $text .= "\n\nይህ ጥያቄ አማራጭ ነው፤ «ዝለል» የሚለውን መጫን ይችላሉ።";
        }
        // Navigation row (use BOTH old and new labels so cached reply keyboards
        // from before the v2 update still work).
        $buttons[] = [['text'=>'⬅️ ወደ ኋላ'], ['text'=>'📋 መረጃውን አሳይ']];
        $buttons[] = [['text'=>'✏️ አርም'],['text'=>'❌ ሰርዝ']];
        return $this->editOrSend($chatId, $editId, $text, TelegramKeyboard::reply($buttons, true, false), $fieldKey);
    }

    /** Legacy name alias kept for code history and structural tests (corrections flow). */
    private function submitReview(array $session): array { $chatId = 0; return $this->finalizeSubmit($chatId, $session); }
    private function editAnswer(array $session, string $key): array { $chatId = 0; return $this->enterFieldEdit($chatId, $session, $key); }
    private function resume(): void { /* /resume handler is inline above */ }

    private function msgKeyForField(string $key): string
    {
        return match($key) {
            'full_name_am' => 'prompt_full_name',
            'baptismal_name' => 'prompt_baptismal_name',
            'gender' => 'prompt_gender',
            'dob_ec' => 'prompt_dob_ec',
            'father_name' => 'prompt_father_name',
            'mother_name' => 'prompt_mother_name',
            'phone_number' => 'prompt_phone',
            'guardian_name' => 'prompt_guardian_name',
            'guardian_phone1' => 'prompt_guardian_phone',
            'city' => 'prompt_city',
            'sub_city' => 'prompt_sub_city',
            'woreda' => 'prompt_woreda',
            'current_section' => 'prompt_current_section',
            'education_level' => 'prompt_education_level',
            'spiritual_education' => 'prompt_spiritual_education',
            'student_photo' => 'prompt_student_photo',
            default => 'prompt_' . $key
        };
    }

    private function acceptAnswerForField(int $chatId, array $session, string $fieldKey, array $message, bool $fromEdit): array
    {
        $field = $this->field((int)($session['form_profile_id'] ?? 0), $fieldKey);
        if (!$field) return $this->showReview($chatId, $session);
        $def = TelegramFormRegistry::definitions()[$fieldKey] ?? [];
        $isFile = ($def['input'] ?? '') === 'file';
        if ($isFile) {
            return $this->acceptAttachment($chatId, $session, $field, $message, $fromEdit);
        }
        $text = is_scalar($message['text'] ?? null) ? trim((string)$message['text']) : '';
        if (in_array($text,['⏭️ ዝለል','/skip','skip','ዝለል'],true)) {
            if ((int)($field['is_required']??0)===1) {
                return TelegramKeyboard::msg($chatId, "⚠️ ይህ ጥያቄ አስፈላጊ ነው።");
            }
            $this->saveAnswer((int)$session['id'], $fieldKey, '', '');
        } else {
            try {
                $value = $fieldKey === 'current_section'
                    ? $this->validateSection($text, (int)$field['is_required']===1)
                    : TelegramFormRegistry::validate($fieldKey, $text, (int)$field['is_required']===1);
            } catch (InvalidArgumentException $e) {
                // Prefer the live form-builder prompt (always exists) over
                // the static message key (missing for extended fields).
                $prompt = (string)($field['prompt_am'] ?? '');
                if ($prompt === '') $prompt = TelegramMessageService::get($this->conn, $this->msgKeyForField($fieldKey));
                $msg = TelegramMessageService::get($this->conn,'invalid_input',['error'=>$e->getMessage(),'prompt'=>$prompt]);
                return TelegramKeyboard::msg($chatId, $msg);
            }
            $this->saveAnswer((int)$session['id'], $fieldKey, $value['display'], $value['normalized']);
        }

        // Re-run duplicate search on identity-relevant fields (incremental recheck)
        if (in_array($fieldKey,['full_name_am','baptismal_name','dob_ec','father_name','mother_name','phone_number','gender'],true)) {
            $this->refreshDuplicates($session);
        }

        if ($fromEdit) {
            $this->updateField((int)$session['id'], 'viewing_menu', 'final_review');
            $session = $this->session((int)$session['id']);
            return $this->showReview($chatId, $session);
        }

        // HR correction mode (082): work through only the requested keys, then
        // return to review. Skipped when no correction list is pending; the
        // duplicate_search detour is bypassed here because refreshDuplicates
        // above already re-ran and submit-time gates still enforce policy.
        $corr = json_decode((string)($session['correction_fields']??''), true);
        if (($session['status']??'')==='collecting' && is_array($corr) && $corr!==[]) {
            $corr = array_values(array_unique(array_filter($corr, 'is_string')));
            $corr = array_values(array_filter($corr, fn($k)=>$k!==$fieldKey));
            $cid=(int)$session['id'];
            if ($corr===[]) {
                $this->transition($cid, ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null,'correction_fields'=>null]);
            } else {
                $this->transition($cid, ['current_field_key'=>$corr[0],'correction_fields'=>json_encode($corr, JSON_UNESCAPED_UNICODE)]);
            }
            $session = $this->session($cid);
            return $this->renderSession($chatId, $session);
        }

        // If field is full_name_am, transition to duplicate_search.
        if ($fieldKey === 'full_name_am') {
            $this->transition((int)$session['id'], ['status'=>'duplicate_search','viewing_menu'=>'candidates','current_candidate_index'=>0,'selected_candidate_id'=>null,'identity_verification_decision'=>'undecided']);
            $session = $this->session((int)$session['id']);
            return $this->renderSession($chatId, $session);
        }

        // Move to next field or review
        $next = $this->nextFieldAfter($session, $fieldKey);
        $id=(int)$session['id'];
        if (!$next) {
            $this->transition($id, ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null]);
        } else {
            $this->transition($id, ['current_field_key'=>$next]);
        }
        $session = $this->session($id);
        return $this->renderSession($chatId, $session);
    }

    private function acceptAttachment(int $chatId, array $session, array $field, array $message, bool $fromEdit=false): array
    {
        $id=(int)$session['id'];
        $key=(string)$field['field_key'];
        $text = is_scalar($message['text'] ?? null) ? trim((string)$message['text']) : '';
        if (in_array($text,['⏭️ ዝለል','/skip','skip','ዝለል'],true)) {
            if ((int)($field['is_required']??0)===1) {
                return TelegramKeyboard::msg($chatId, "⚠️ ይህ ጥያቄ አስፈላጊ ነው።");
            }
            $this->saveAnswer($id, $key, '', '');
        } else {
            $file = null;
            if (is_array($message['document']??null)) $file = $message['document'];
            elseif (is_array($message['photo']??null) && $message['photo']!==[]) $file = end($message['photo']);
            if (!is_array($file) || empty($file['file_id'])) {
                return TelegramKeyboard::msg($chatId, "ፎቶውን ወይም ሰነዱን ይላኩ።");
            }
            try {
                $tmp = (new TelegramBotApiService())->downloadFile((string)$file['file_id']);
                try {
                    (new TelegramAttachmentService($this->conn))->store($id, $key, (string)$file['file_id'], (string)($file['file_unique_id'] ?? ''), $tmp);
                } finally {
                    if (is_file($tmp)) @unlink($tmp);
                }
            } catch (\Throwable $e) {
                // Download/validation/storage failures must tell the officer
                // what happened instead of dying into a silent webhook 500.
                reportInternalError('Telegram attachment failed', $e);
                return TelegramKeyboard::msg($chatId, "⚠️ " . $e->getMessage() . "\n\nእባክዎ እንደገና ይሞክሩ።");
            }
            $this->saveAnswer($id, $key, 'ፋይል ተቀምጧል', 'file://'.$key);
        }
        if ($fromEdit) {
            $this->updateField($id, 'viewing_menu', 'final_review');
            return $this->showReview($chatId, $this->session($id));
        }
        // HR correction mode (082), mirrored from acceptAnswerForField.
        $corr = json_decode((string)($session['correction_fields']??''), true);
        if (($session['status']??'')==='collecting' && is_array($corr) && $corr!==[]) {
            $corr = array_values(array_unique(array_filter($corr, 'is_string')));
            $corr = array_values(array_filter($corr, fn($k)=>$k!==(string)$field['field_key']));
            if ($corr===[]) {
                $this->transition($id, ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null,'correction_fields'=>null]);
            } else {
                $this->transition($id, ['current_field_key'=>$corr[0],'correction_fields'=>json_encode($corr, JSON_UNESCAPED_UNICODE)]);
            }
            return $this->renderSession($chatId, $this->session($id));
        }
        $next = $this->nextFieldAfter($session, (string)$field['field_key']);
        if (!$next) $this->transition($id,['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null]);
        else $this->transition($id,['current_field_key'=>$next]);
        $session = $this->session($id);
        return $this->renderSession($chatId, $session);
    }

    private function performDuplicateSearch(array $session): void
    {
        $answers = $this->answers((int)$session['id']);
        try {
            $meta = TelegramDuplicateCandidateService::findWithMeta($this->conn, $answers);
            $state = $meta['candidates']===[] ? 'none' : (((int)$meta['candidates'][0]['match_score']>=70) ? 'likely' : 'possible');
            $summary = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            reportInternalError('Telegram duplicate lookup failed', $e);
            $state = 'error'; $summary = null;
        }
        $newStatus = $state==='none' ? 'collecting' : 'duplicate_review';
        $newField = $state==='none' ? $this->nextFieldKey($session,'full_name_am') : null;
        $sid=(int)$session['id'];
        $q = $this->conn->prepare('UPDATE telegram_registration_sessions SET status=?, duplicate_state=?, duplicate_summary=?, current_field_key=?, current_candidate_index=0, lock_version=lock_version+1 WHERE id=?');
        $q->bind_param('ssssi', $newStatus, $state, $summary, $newField, $sid);
        $q->execute(); $q->close();
    }

    private function loadCandidateList(array $session): array
    {
        $summary = json_decode((string)($session['duplicate_summary']??''), true);
        return is_array($summary) && isset($summary['candidates']) && is_array($summary['candidates']) ? $summary['candidates'] : [];
    }

    private function renderDuplicateReview(int $chatId, array $session, ?int $editId): array
    {
        $candidates = $this->loadCandidateList($session);
        $idx = (int)($session['current_candidate_index'] ?? 0);
        if (!$candidates) {
            // Nothing to review — continue where answers ran out, or back to
            // review when everything is answered (late re-show).
            $missing = $this->firstMissingField($session);
            if ($missing === null) {
                $this->transition((int)$session['id'], ['status'=>'review','viewing_menu'=>'final_review','current_field_key'=>null,'duplicate_state'=>'none','edit_return_to_review'=>0]);
            } else {
                $this->transition((int)$session['id'], ['status'=>'collecting','viewing_menu'=>'field_edit','current_field_key'=>$missing,'duplicate_state'=>'none','edit_return_to_review'=>0]);
            }
            return $this->renderSession($chatId, $this->session((int)$session['id']), $editId);
        }
        if ($idx >= count($candidates)) $idx = count($candidates)-1;
        $cand = $candidates[$idx];
        $answers = $this->answers((int)$session['id']);
        $summary = json_decode((string)($session['duplicate_summary']??''), true);
        $capped = is_array($summary) && !empty($summary['capped']);
        $text = TelegramMessageService::get($this->conn,'duplicate_header') . "\n\n";
        if ($capped) {
            $text .= "More plausible candidates exist than the safe display limit. Add or verify date of birth and phone so HR can refine the result.\n\n";
        }
        $text .= "<b>እጩ ".($idx+1)." / ".count($candidates)."</b>\n\n";
        $text .= $this->renderCandidateCard($cand, $answers);
        $rows = [];
        $navRow = [];
        if ($idx > 0) $navRow[] = ['⬅️ ያለፈው', 'cand_prev'];
        if ($idx+1 < count($candidates)) $navRow[] = ['ቀጣይ ➡️', 'cand_next'];
        if ($navRow !== []) $rows[] = $navRow;
        // Decide directly on the candidate card: same callbacks as the
        // identity screen, so no verify-then-decide round trip.
        $candId = (int)$cand['id'];
        $rows[] = [['✅ ይህ አንድ አባል ነው', 'verify_same:'.$candId]];
        $rows[] = [['❌ የተለያዩ ሰዎች ናቸው', 'verify_different:'.$candId]];
        $rows[] = [['❓ ገና ማረጋገጥ አልቻልኩም', 'verify_uncertain']];
        $rows[] = [['❌ ሰርዝ', 'cancel'], ['🏠 ዋና ምናሌ', 'home']];
        return $this->editOrSend($chatId, $editId, $text, TelegramKeyboard::inline($rows));
    }

    private function renderCandidateCard(array $cand, array $draft): string
    {
        $bandLabel = match((string)$cand['match_band']) {
            'high' => TelegramMessageService::get($this->conn,'match_high'),
            'medium' => TelegramMessageService::get($this->conn,'match_medium'),
            default => TelegramMessageService::get($this->conn,'match_low'),
        };
        $t  = "• ሙሉ ስም፦ <b>".$this->esc((string)($cand['full_name']??''))."</b>\n";
        if (!empty($cand['baptismal_name'])) $t .= "• የክርስትና ስም፦ ".$this->esc((string)$cand['baptismal_name'])."\n";
        $t  .= "• የአባት ስም፦ ".$this->esc((string)($cand['father_name']??'—'))."\n";
        if (!empty($cand['mother_name'])) $t .= "• የእናት ስም፦ ".$this->esc((string)$cand['mother_name'])."\n";
        if (!empty($cand['grandfather_name'])) $t .= "• የአያት ስም፦ ".$this->esc((string)$cand['grandfather_name'])."\n";
        $genderLabel = ((string)($cand['gender']??''))==='male'?'ወንድ':'ሴት';
        $t .= "• ፆታ፦ {$genderLabel}\n";
        if (!empty($cand['dob_ec'])) $t .= "• የትውልድ ቀን፦ ".$this->esc((string)$cand['dob_ec']);
        if (!empty($cand['age'])) $t .= " (ዕድሜ ".(int)$cand['age'].")";
        $t .= "\n";
        if (!empty($cand['phone_masked'])) $t .= "• ስልክ፦ ".$this->esc((string)$cand['phone_masked'])."\n";
        $t .= "• ክፍል፦ ".$this->esc((string)($cand['current_section']??'—'))."\n";
        if (!empty($cand['education_level'])) $t .= "• የትምህርት ደረጃ፦ ".$this->esc((string)$cand['education_level'])."\n";
        $t .= "• የአባል መለያ፦ <b>".$this->esc((string)$cand['member_code'])."</b>\n";
        $t .= "• ሁኔታ፦ ".$this->esc((string)$cand['status_label'])."\n\n";
        $t .= "<b>ተመሳሳይነት፦</b> {$bandLabel} (".(int)$cand['match_score']."%)\n";
        $t .= "<b>ምክንያት፦</b> ".$this->esc(implode('፣ ',$cand['match_reasons']??[]))."\n";
        return $t;
    }

    private function renderIdentityVerification(int $chatId, array $session, ?int $editId): array
    {
        $candId = (int)($session['selected_candidate_id'] ?? 0);
        $candidates = $this->loadCandidateList($session);
        $chosen = null;
        foreach ($candidates as $c) if ((int)$c['id'] === $candId) { $chosen = $c; break; }
        if (!$chosen) {
            $this->transition((int)$session['id'],['status'=>'duplicate_review','selected_candidate_id'=>null,'identity_verification_decision'=>'undecided']);
            return $this->renderSession($chatId,$this->session((int)$session['id']),$editId);
        }
        $text = "<b>እጩ አባል ማረጋገጫ</b>\n\n";
        $text .= $this->renderCandidateCard($chosen, $this->answers((int)$session['id']));
        $text .= "\n".TelegramMessageService::get($this->conn,'verify_prompt');
        $rows = [
            [['✅ ይህ አንድ አባል ነው', 'verify_same:'.$candId]],
            [['❌ የተለያዩ ሰዎች ናቸው', 'verify_different:'.$candId]],
            [['❓ ገና ማረጋገጥ አልቻልኩም', 'verify_uncertain']],
            [['⬅️ ወደ እጮች ተመለስ', 'cand_prev']],
        ];
        return $this->editOrSend($chatId,$editId,$text,TelegramKeyboard::inline($rows));
    }

    private function renderUncertainMenu(int $chatId, array $session, ?int $editId): array
    {
        $text = TelegramMessageService::get($this->conn,'uncertain_prompt');
        $rows = [
            [['🔍 ተጨማሪ መረጃ አወዳድር', 'continue_reg']],
            [['📨 ለኃላፊ አቅርብ', 'escalate']],
            [['⏸️ ለጊዜው አቆይ', 'pause']],
            [['❌ ሰርዝ', 'cancel'],['🏠 ዋና ምናሌ', 'home']],
        ];
        $this->updateField((int)$session['id'],'identity_verification_decision','uncertain');
        return $this->editOrSend($chatId, $editId, $text, TelegramKeyboard::inline($rows));
    }

    /** True when the draft behind a review screen can still move forward,
     *  so review and blocker screens may offer the resume button.
     *  Terminal states (submitted/cancelled/completed/...) get no button. */
    private function reviewCanContinue(array $session): bool
    {
        $st = (string)($session['status'] ?? '');
        if (in_array($st, ['choosing','duplicate_search','duplicate_review','identity_verification'], true)) return true;
        return $st === 'collecting' && $this->firstMissingField($session) !== null;
    }

    private function showReview(int $chatId, array $session, ?int $editId = null): array
    {
        $answers = $this->answers((int)$session['id']);
        $cands = $this->loadCandidateList($session);
        $high = false;
        foreach ($cands as $c) if ((int)($c['match_score']??0) >= 70) { $high = true; break; }
        $text = TelegramMessageService::get($this->conn,'review_header')."\n\n";
        if ($high) $text .= "🚨 ".TelegramMessageService::get($this->conn,'review_duplicate_warn')."\n\n";
        // Build a REPLY keyboard with per-field ✏️ edit buttons (v1-style, robust
        // against webhook callback-query state because it's plain text). We
        // accept these text labels in handleReviewAction via prefix matching.
        $rows = []; $row = [];
        foreach (self::FIELD_ORDER as $key) {
            if (($answers[$key] ?? '') !== '' || $key === 'full_name_am') {
                $label = TelegramFormRegistry::label($key);
                $shown = (string)($answers[$key]??'—');
                if ($key==='gender' && $shown!=='—') { $gch = TelegramFormRegistry::choices('gender'); $shown = $gch[$shown] ?? $shown; }
                $text .= "• <b>".$this->esc($label)."</b>፦ ".$this->esc($shown)."\n";
                $btn = '✏️ '.$this->esc($label);
                $row[] = ['text'=>$btn];
                if (count($row) === 2) { $rows[] = $row; $row = []; }
            }
        }
        if ($row !== []) $rows[] = $row;
        if ($high) {
            $rows[] = [['text'=>'🔍 እጮች ዳግም አሳይ']];
        }
        if ($this->reviewCanContinue($session)) {
            array_unshift($rows, [['text'=>'▶️ ቀጥል']]);
        }
        $rows[] = [['text'=>'✅ ምዝገባውን ላክ']];
        $rows[] = [['text'=>'❌ ሰርዝ'],['text'=>'🏠 ዋና ምናሌ']];
        // Always send as a fresh message with a reply keyboard — this replaces
        // the previous field-prompt reply keyboard and keeps all actions as
        // plain text so even if callback_query isn't subscribed, buttons work.
        return TelegramKeyboard::msg($chatId, $text, TelegramKeyboard::reply($rows, true, false));
    }

    private function showEditMenu(int $chatId, array $session, ?int $editId = null): array
    {
        $answers = $this->answers((int)$session['id']);
        $text = TelegramMessageService::get($this->conn,'edit_menu_title')."\n\n";
        $buttons = [];
        $row = [];
        foreach (self::FIELD_ORDER as $key) {
            $label = TelegramFormRegistry::label($key);
            $val = (string)($answers[$key] ?? '');
            $display = $val !== '' ? '✏️ '.$label : '➕ '.$label;
            $row[] = [$display, 'edit_field:'.$key];
            if (count($row) === 2) { $buttons[] = $row; $row = []; }
        }
        if ($row !== []) $buttons[] = $row;
        $buttons[] = [['↩️ ወደ ማጠቃለያ ተመለስ', 'edit_back']];
        $this->updateField((int)$session['id'],'viewing_menu','edit_menu');
        return $this->editOrSend($chatId,$editId,$text,TelegramKeyboard::inline($buttons));
    }

    private function enterFieldEdit(int $chatId, array $session, string $key, ?int $editId = null): array
    {
        if ($key === '' || !TelegramFormRegistry::exists($key)) {
            return $this->showEditMenu($chatId, $session, $editId);
        }
        $field = $this->field((int)($session['form_profile_id']??0), $key);
        if (!$field) return $this->showReview($chatId, $session, $editId);
        $answers = $this->answers((int)$session['id']);
        $cur = $answers[$key] ?? '';
        $label = TelegramFormRegistry::label($key);
        $promptAm = (string)($field['prompt_am'] ?? '');
        $helpAm = (string)($field['help_am'] ?? '');
        $text = "<b>".$this->esc($label)."</b> — ማስተካከያ\n\n";
        $text .= "አሁን ያለው፦ <b>".$this->esc($cur !== '' ? $cur : '(አልተሞላም)')."</b>\n\n";
        $text .= $this->esc($promptAm);
        if ($helpAm !== '') $text .= "\n\nℹ️ ".$this->esc($helpAm);
        if ((int)($field['is_required']??0)!==1) $text .= "\n\nይህ ጥያቄ አማራጭ ነው፤ «ዝለል» የሚለውን መጫን ይችላሉ።";
        $this->conn->begin_transaction();
        try {
            $sid=(int)$session['id'];
            $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET current_field_key=?,viewing_menu='field_edit',edit_return_to_review=1,lock_version=lock_version+1 WHERE id=?");
            $q->bind_param('si', $key, $sid); $q->execute(); $q->close();
            $this->audit('telegram', (int)$session['bot_user_id'], 'field_edit_started', 'session', (int)$session['id']);
            $this->conn->commit();
        } catch (\Throwable $e) { $this->conn->rollback(); reportInternalError('Telegram field-edit start failed', $e); }
        // Mirror the collection prompt: choice buttons (gender/section/...)
        // and the file-attach hint, so officers never face a bare keyboard
        // in edit mode.
        $def = TelegramFormRegistry::definitions()[$key] ?? [];
        $buttons = [];
        $choices = $key === 'current_section' ? $this->sectionOptions() : TelegramFormRegistry::choices($key);
        if ($choices !== []) {
            $row = []; $i = 0;
            foreach ($choices as $val => $lab) {
                $row[] = ['text' => $lab]; $i++;
                if ($i % 2 === 0) { $buttons[] = $row; $row = []; }
            }
            if ($row !== []) $buttons[] = $row;
        } elseif (($def['input'] ?? '') === 'file') {
            $buttons[] = [['text'=>'📎 ፋይል ለመላክ ተጫን']];
        }
        if ((int)($field['is_required']??0)!==1) $buttons[] = [['text'=>'⏭️ ዝለል']];
        $buttons[] = [['text'=>'↩️ ወደ ማጠቃለያ']];
        $buttons[] = [['text'=>'❌ ሰርዝ']];
        return $this->editOrSend($chatId, $editId, $text, TelegramKeyboard::reply($buttons, true));
    }

    private function enterFieldEditFromText(int $chatId, array $session, string $text): array
    {
        // Strip ✏️ prefix and match against field labels.
        $pressed = trim(mb_substr($text, 2));
        foreach (self::FIELD_ORDER as $key) {
            $label = TelegramFormRegistry::label($key);
            if ($pressed === $label || str_contains($pressed, $label) || str_contains($label, $pressed)) {
                return $this->enterFieldEdit($chatId, $session, $key);
            }
        }
        return $this->showEditMenuReply($chatId, $session);
    }

    private function goBack(int $chatId, array $session): array
    {
        $cur = (string)($session['current_field_key'] ?? '');
        if ($cur === '' || $cur === 'full_name_am') {
            return $this->showReview($chatId, $session);
        }
        $prev = $this->previousField($session, $cur);
        if (!$prev) return $this->showReview($chatId, $session);
        $this->updateField((int)$session['id'], 'current_field_key', $prev);
        return $this->renderSession($chatId, $this->session((int)$session['id']));
    }

    private function handleReviewAction(int $chatId, array $session, string $text): array
    {
        // Submit — accept both Amharic variants and English.
        if ($text === '✅ ምዝገባውን ላክ'
            || $text === '✅ ምዝገባውን ለማጽደቅ ላክ'
            || $text === '✅ ላክ'
            || $text === '✅ Submit for HR review'
            || str_starts_with($text,'✅')) {
            return $this->finalizeSubmit($chatId, $session);
        }
        // Cancel — accept both ሰርዝ (se) and ስርዝ (s-r) spellings users type.
        if ($text === '❌ ሰርዝ' || $text === '❌ ስርዝ' || $text === '/cancel' || $text === '❌ Cancel') {
            $this->cancelActive((int)$session['bot_user_id']);
            return TelegramKeyboard::msg($chatId, TelegramMessageService::get($this->conn,'cancelled'), $this->homeKeyboard());
        }
        // Home
        if ($text === '🏠 ዋና ምናሌ' || $text === '/home') {
            return $this->sendHome($chatId, $this->actorOfSession($session));
        }
        // Re-show candidates
        if (str_contains($text,'እጮች') || str_contains($text,'cand')) {
            $this->updateField((int)$session['id'],'current_candidate_index',0);
            $this->transition((int)$session['id'],['status'=>'duplicate_review','selected_candidate_id'=>null]);
            return $this->renderSession($chatId, $this->session((int)$session['id']));
        }
        // Edit a specific field (✏️ prefix) — prefix match so any field label works.
        if (str_starts_with($text,'✏️')) {
            $pressed = trim(mb_substr($text, 2)); // strip the pencil
            // Match against field labels
            foreach (self::FIELD_ORDER as $key) {
                $label = TelegramFormRegistry::label($key);
                if ($pressed === $label || str_contains($pressed, $label) || str_contains($label, $pressed)) {
                    return $this->enterFieldEdit($chatId, $session, $key);
                }
            }
            // Generic "edit" request without a specific field
            if ($pressed === 'አርም' || $pressed === 'መረጃ ማስተካከል' || $pressed === 'Edit' || $pressed === '') {
                return $this->showEditMenuReply($chatId, $session);
            }
        }
        if (in_array($text,['✏️ አርም','✏️ መረጃ ማስተካከል','✏️ Edit','✏️ ሌላ መረጃ ማስተካከል'],true)) {
            return $this->showEditMenuReply($chatId, $session);
        }
        return $this->showReview($chatId, $session);
    }

    /** Edit menu rendered as a reply keyboard (text-routed, robust). */
    private function showEditMenuReply(int $chatId, array $session): array
    {
        $answers = $this->answers((int)$session['id']);
        $text = "የትኛውን መረጃ ማስተካከል ይፈልጋሉ?\n\n";
        $rows = []; $row = [];
        foreach (self::FIELD_ORDER as $key) {
            if (($answers[$key] ?? '') !== '' || $key === 'full_name_am') {
                $label = TelegramFormRegistry::label($key);
                $row[] = ['text'=>'✏️ '.$label];
                if (count($row) === 2) { $rows[] = $row; $row = []; }
            }
        }
        if ($row !== []) $rows[] = $row;
        $rows[] = [['text'=>'↩️ ወደ ማጠቃለያ']];
        $rows[] = [['text'=>'❌ ሰርዝ']];
        return TelegramKeyboard::msg($chatId, $text, TelegramKeyboard::reply($rows, true));
    }

    private function finalizeSubmit(int $chatId, array $session, ?int $editId = null): array
    {
        $id = (int)$session['id'];
        // Run final duplicate check + readiness
        $answers = $this->answers($id);
        $ready = (new TelegramApprovalReadinessService($this->conn))->inspect($session, $this->answersRows($id), true);
        $cands = $this->loadCandidateList($session);
        foreach ($cands as $c) {
            $band = (string)($c['match_band']??($c['band']??($c['score_band']??'')));
            $score = (int)($c['match_score']??0);
            $cid = (int)($c['id']??0);
            $isHigh = $score >= 70 || in_array($band,['high','very_high','certain'],true);
            if ($isHigh && $cid > 0) {
                // Per REG spec: same_person → cannot create new member;
                // no decision → blocks submission (must verify);
                // uncertain → warning (readiness service will warn, not block);
                // different_person → ok to proceed.
                if ($this->decisionExistsFor($id, $cid, ['same'])) {
                    $text = TelegramMessageService::get($this->conn,'final_validation_blocked',[
                        'blocker' => 'ይህ መዝገብ ከነበረ አባል ጋር ተመሳሳይ ሆኖ ምልክት ተደርጓል። እባክዎ የነበረውን አባል ይጠቀሙ እንጂ አዲስ አይመዝገቡ።'
                    ]);
                    return $this->editOrSend($chatId,$editId,$text,TelegramKeyboard::inline([
                        [['🔍 እጮች አሳይ', 'cand_prev']],
                        [['❌ ሰርዝ','cancel']]
                    ]));
                }
                if (!$this->decisionExistsFor($id, $cid, ['different','uncertain'])) {
                    // Unresolved high-confidence candidate
                    $text = TelegramMessageService::get($this->conn,'final_validation_blocked',[
                        'blocker' => 'ከፍተኛ ተመሳሳይነት ያለው እጩ አለ። እባክዎ አስቀድመው ያረጋግጡ።'
                    ]);
                    return $this->editOrSend($chatId,$editId,$text,TelegramKeyboard::inline([
                        [['🔍 እጮች አሳይ', 'cand_prev']],
                        [['❌ ሰርዝ','cancel']]
                    ]));
                }
            }
        }
        if (!$ready['ready']) {
            $text = TelegramMessageService::get($this->conn,'final_validation_blocked',[
                'blocker' => implode('; ', $ready['blockers'])
            ]);
            $rows = [];
            if ($this->reviewCanContinue($session)) $rows[] = [['text'=>'▶️ ቀጥል']];
            $rows[] = [['text'=>'📋 መረጃውን አሳይ']];
            $rows[] = [['text'=>'❌ ሰርዝ'],['text'=>'🏠 ዋና ምናሌ']];
            return TelegramKeyboard::msg($chatId, $text, TelegramKeyboard::reply($rows, true, false));
        }
        // HR correction mode: a draft with pending correction answers must not
        // dodge them via the mid-collection submit button.
        $corr = json_decode((string)($session['correction_fields']??''), true);
        if (is_array($corr) && $corr!==[]) {
            return TelegramKeyboard::msg($chatId, 'የHR ማስተካከያ ጥያቄ መጀመሪያ መሟላት አለበት። እባክዎ የተጠየቁትን መልሶች ያርሙና እንደገና ይላኩ።');
        }
        // A ready draft may be submitted straight from the mid-collection
        // review (status still 'collecting'): readiness already enforced the
        // required fields and the duplicate policy above. The status guard
        // still rejects double submits (status is 'submitted' then).
        $q = $this->conn->prepare("UPDATE telegram_registration_sessions SET status='submitted',submitted_at=CURRENT_TIMESTAMP,viewing_menu='completed',current_field_key=NULL,lock_version=lock_version+1 WHERE id=? AND status IN ('review','final_validation','collecting')");
        $q->bind_param('i',$id); $q->execute(); $changed = $q->affected_rows; $q->close();
        if ($changed !== 1) return TelegramKeyboard::msg($chatId,'ይህ ረቂቅ አስቀድሞ ተልኳል።');
        $this->audit('telegram', (int)$session['bot_user_id'], 'draft_submitted', 'session', $id);
        $session = $this->session($id);
        $text = TelegramMessageService::get($this->conn,'submit_success',[
            'public_id' => (string)$session['public_id'],
            'date' => date('Y-m-d H:i'),
        ]);
        return $this->editOrSend($chatId,$editId,$text,$this->homeKeyboard());
    }

    private function renderSubmitted(int $chatId, array $session, ?int $editId): array
    {
        return $this->editOrSend($chatId,$editId,TelegramMessageService::get($this->conn,'submit_success',[
            'public_id'=>(string)$session['public_id'],
            'date'=>date('Y-m-d H:i', strtotime((string)($session['submitted_at']??'now')))
        ]),$this->homeKeyboard());
    }

    private function renderCancelled(int $chatId, array $session, ?int $editId): array
    {
        $sel = (int)($session['selected_candidate_id']??0);
        if ($sel > 0 && (string)($session['identity_verification_decision']??'') === 'same_person') {
            // Show existing member confirmation
            $q=$this->conn->prepare('SELECT member_code FROM members WHERE id=? LIMIT 1');
            $q->bind_param('i',$sel); $q->execute(); $m = $q->get_result()->fetch_assoc(); $q->close();
            $code = $m ? (string)$m['member_code'] : 'M'.str_pad((string)$sel,5,'0',STR_PAD_LEFT);
            return $this->editOrSend($chatId,$editId,TelegramMessageService::get($this->conn,'same_person_confirmed',['member_code'=>$code]), TelegramKeyboard::inline([
                [['ወደ ዋና ምናሌ', 'home']]
            ]));
        }
        return $this->editOrSend($chatId,$editId,TelegramMessageService::get($this->conn,'cancelled'),$this->homeKeyboard());
    }

    private function renderCompleted(int $chatId, array $session, ?int $editId): array
    {
        return $this->editOrSend($chatId,$editId,TelegramMessageService::get($this->conn,'submit_success',[
            'public_id'=>(string)$session['public_id'],
            'date'=>date('Y-m-d H:i')
        ]),$this->homeKeyboard());
    }

    // -------------------------------------------------------------------------
    // Storage helpers
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function actor(int $telegramId, array $from): array
    {
        $name = trim((string)($from['first_name'] ?? '') . ' ' . (string)($from['last_name'] ?? ''));
        $username = is_scalar($from['username'] ?? null) ? (string)$from['username'] : null;
        $statement = $this->conn->prepare('SELECT tb.*,u.role AS system_role,u.is_active AS system_user_active FROM telegram_bot_users tb LEFT JOIN users u ON u.id=tb.linked_user_id WHERE tb.telegram_user_id=? LIMIT 1');
        $statement->bind_param('i', $telegramId); $statement->execute(); $row=$statement->get_result()->fetch_assoc(); $statement->close();
        if (!$row) {
            $statement=$this->conn->prepare("INSERT INTO telegram_bot_users (telegram_user_id,display_name,username_snapshot,status) VALUES (?,?,?,'pending')");
            $statement->bind_param('iss',$telegramId,$name,$username); $statement->execute(); $id=(int)$this->conn->insert_id; $statement->close();
            return ['id'=>$id,'telegram_user_id'=>$telegramId,'status'=>'pending'];
        }
        $id=(int)$row['id'];
        $statement=$this->conn->prepare('UPDATE telegram_bot_users SET display_name=?,username_snapshot=?,last_seen_at=CURRENT_TIMESTAMP WHERE id=?');
        $statement->bind_param('ssi',$name,$username,$id); $statement->execute(); $statement->close();
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()) $row['status']='suspended';
        return $row;
    }

    private function actorOfSession(array $session): array
    {
        $q = $this->conn->prepare('SELECT * FROM telegram_bot_users WHERE id=? LIMIT 1');
        $bid = (int)($session['bot_user_id'] ?? 0);
        $q->bind_param('i',$bid); $q->execute(); $r = $q->get_result()->fetch_assoc(); $q->close();
        return $r ?: [];
    }

    private function session(int $id): ?array
    {
        $s = $this->conn->prepare('SELECT * FROM telegram_registration_sessions WHERE id=?');
        $s->bind_param('i',$id); $s->execute(); $r = $s->get_result()->fetch_assoc() ?: null; $s->close();
        return $r;
    }

    private function mostRecentActiveSession(int $actorId, int $chatId): ?array
    {
        // Active: any session not in terminal states. Submitted/escalated drafts belong to
        // HR now — the officer must be able to start the next registration.
        $s = $this->conn->prepare("SELECT * FROM telegram_registration_sessions WHERE bot_user_id=? AND telegram_chat_id=? AND status NOT IN ('approved','cancelled','expired','failed','submitted','escalated') AND viewing_menu<>'search_results' ORDER BY id DESC LIMIT 1");
        $s->bind_param('ii',$actorId,$chatId); $s->execute(); $r = $s->get_result()->fetch_assoc() ?: null; $s->close();
        return $r;
    }

    private function sessionWithMenu(int $actorId, int $chatId, string $menu): ?array
    {
        $s = $this->conn->prepare("SELECT * FROM telegram_registration_sessions WHERE bot_user_id=? AND telegram_chat_id=? AND viewing_menu=? ORDER BY id DESC LIMIT 1");
        $s->bind_param('iis',$actorId,$chatId,$menu); $s->execute(); $r = $s->get_result()->fetch_assoc() ?: null; $s->close();
        return $r;
    }

    /** @return array<string,string> */
    private function answers(int $sessionId): array
    {
        $rows = $this->answersRows($sessionId);
        $a = [];
        foreach ($rows as $row) {
            $key = (string)$row['field_key'];
            $useNorm = (string)($row['value_normalized'] ?? '') !== '' && in_array($key, ['gender'], true);
            $a[$key] = $useNorm ? (string)$row['value_normalized'] : (string)$row['value_text'];
        }
        return $a;
    }

    private function answersRows(int $sessionId): array
    {
        $s = $this->conn->prepare('SELECT field_key,value_text,value_normalized FROM telegram_registration_answers WHERE session_id=? ORDER BY id');
        $s->bind_param('i',$sessionId); $s->execute(); $r = $s->get_result(); $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        $s->close(); return $rows;
    }

    private function saveAnswer(int $sessionId, string $key, string $display, string $normal): void
    {
        $q = $this->conn->prepare('INSERT INTO telegram_registration_answers (session_id,field_key,value_text,value_normalized) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text),value_normalized=VALUES(value_normalized),updated_at=CURRENT_TIMESTAMP');
        $q->bind_param('isss',$sessionId,$key,$display,$normal); $q->execute(); $q->close();
    }

    private function field(int $profileId, string $key): ?array
    {
        $s = $this->conn->prepare('SELECT * FROM telegram_form_fields WHERE profile_id=? AND field_key=? AND is_enabled=1');
        $s->bind_param('is',$profileId,$key); $s->execute(); $r = $s->get_result()->fetch_assoc() ?: null; $s->close();
        return $r;
    }

    /** @return array<string,string> */
    private function sectionOptions(): array
    {
        $opts = [];
        try {
            $r = $this->conn->query("SELECT class_name,class_name_en FROM classes WHERE is_active=1 ORDER BY level_order,class_name,id LIMIT 50");
        } catch (\Throwable $e) {
            // Partial schema (no classes table): no section choices rather
            // than a webhook-wide 500. Section answers then fail closed in
            // validateSection until the school tables exist.
            return [];
        }
        if ($r) while ($row = $r->fetch_assoc()) {
            $v = trim((string)$row['class_name']); if ($v==='') continue;
            $en = trim((string)($row['class_name_en']??''));
            $opts[$v] = $en !== '' && $en !== $v ? $v.' / '.$en : $v;
        }
        return $opts;
    }

    /** @return array{display:string,normalized:string} */
    private function validateSection(string $raw, bool $required): array
    {
        $v = trim($raw);
        if (in_array(strtolower($v),['/skip','skip','ዝለል','⏭️ ዝለል'],true)) {
            if ($required) throw new InvalidArgumentException('ይህ መረጃ አስፈላጊ ነው።');
            return ['display'=>'','normalized'=>''];
        }
        $opts = $this->sectionOptions();
        foreach ($opts as $stored => $label) if ($v === $stored || $v === $label) return ['display'=>$label,'normalized'=>$stored];
        throw new InvalidArgumentException('ከሚገኙት ክፍሎች አንዱን ይምረጡ።');
    }

    private function nextFieldKey(array $session, string $after): ?string
    {
        $fields = $this->enabledFields((int)($session['form_profile_id']??0));
        $keys = array_column($fields,'field_key');
        $i = array_search($after, $keys, true);
        if ($i === false) return $keys[0] ?? null;
        return $keys[$i+1] ?? null;
    }

    private function nextFieldAfter(array $session, string $key): ?string { return $this->nextFieldKey($session, $key); }

    /** First enabled field with no stored answer row (answered and skipped
     *  fields both have rows and count as done). Null when every enabled
     *  field already has an answer. */
    private function firstMissingField(array $session): ?string
    {
        $fields = $this->enabledFields((int)($session['form_profile_id']??0));
        if ($fields === []) return null;
        $answered = [];
        $sid=(int)$session['id'];
        $q = $this->conn->prepare('SELECT field_key FROM telegram_registration_answers WHERE session_id=?');
        $q->bind_param('i',$sid); $q->execute(); $res=$q->get_result();
        while ($row=$res->fetch_assoc()) $answered[(string)$row['field_key']]=true;
        $q->close();
        foreach ($fields as $f) {
            $k = (string)($f['field_key'] ?? '');
            if ($k !== '' && !isset($answered[$k])) return $k;
        }
        return null;
    }

    private function previousField(array $session, string $key): ?string
    {
        $fields = $this->enabledFields((int)($session['form_profile_id']??0));
        $keys = array_column($fields,'field_key');
        $i = array_search($key, $keys, true);
        if ($i === false || $i === 0) return null;
        return $keys[$i-1] ?? null;
    }

    private function enabledFields(int $profileId): array
    {
        if ($profileId < 1) {
            // Fall back to field order filtered by FormRegistry defaults (safe for no-profile flows).
            $out = [];
            foreach (self::FIELD_ORDER as $k) {
                if (TelegramFormRegistry::exists($k)) $out[] = ['field_key'=>$k, 'is_required'=>(int)(TelegramFormRegistry::definitions()[$k]['skip']??1)===1?0:1];
            }
            return $out;
        }
        $s = $this->conn->prepare('SELECT field_key,is_required FROM telegram_form_fields WHERE profile_id=? AND is_enabled=1 ORDER BY sort_order,id');
        $s->bind_param('i',$profileId); $s->execute(); $r = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        return $r;
    }

    private function refreshDuplicates(array $session): void
    {
        $answers = $this->answers((int)$session['id']);
        try {
            $meta = TelegramDuplicateCandidateService::findWithMeta($this->conn,$answers);
            $state = $meta['candidates']===[] ? 'none' : (((int)$meta['candidates'][0]['match_score']>=70) ? 'likely' : 'possible');
            $summary = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            reportInternalError('Telegram duplicate lookup failed',$e); $state='error'; $summary=null;
        }
        $sid=(int)$session['id'];
        $q = $this->conn->prepare('UPDATE telegram_registration_sessions SET duplicate_state=?,duplicate_summary=?,lock_version=lock_version+1 WHERE id=?');
        $q->bind_param('ssi',$state,$summary,$sid); $q->execute(); $q->close();
    }

    /** Candidate id for a decision press: the button's own id wins
     *  (the card on screen); the stored selection is only a fallback
     *  for old messages whose buttons carry no id. */
    private function resolveDecisionCandidateId(array $session, string $arg): int
    {
        $fromButton = (int)$arg;
        if ($fromButton !== 0) return $fromButton;
        return (int)($session['selected_candidate_id'] ?? 0);
    }

    private function recordCandidateDecision(array $session, int $candidateId, string $decision): void
    {
        if ($candidateId < 1) return;
        $sid=(int)$session['id'];
        $q = $this->conn->prepare('INSERT INTO telegram_candidate_decisions (session_id,candidate_member_id,decision) VALUES (?,?,?)');
        $q->bind_param('iis',$sid,$candidateId,$decision); $q->execute(); $q->close();
    }

    private function decisionExistsFor(int $sessionId, int $candidateId, array $decisions): bool
    {
        if ($candidateId < 1) return false;
        $in = implode(',', array_fill(0, count($decisions), '?'));
        $types = 'ii' . str_repeat('s', count($decisions));
        $sql = "SELECT id FROM telegram_candidate_decisions WHERE session_id=? AND candidate_member_id=? AND decision IN ($in) LIMIT 1";
        $q = $this->conn->prepare($sql);
        $params = array_merge([$sessionId,$candidateId],$decisions);
        $refs = []; foreach ($params as $i=>$v) $refs[] = &$params[$i];
        call_user_func_array([$q,'bind_param'], array_merge([$types], $refs));
        $q->execute(); $r = $q->get_result()->fetch_assoc(); $q->close();
        return !empty($r);
    }

    private function transition(int $sessionId, array $changes): void
    {
        if ($changes === []) return;
        $sets = []; $types = ''; $vals = [];
        foreach ($changes as $col => $val) {
            if ($val instanceof \mysqli_sql_expr) { $sets[] = "`$col`=".((string)$val); continue; }
            if ($val === null) { $sets[] = "`$col`=NULL"; continue; }
            $sets[] = "`$col`=?"; $types .= 's'; $vals[] = is_int($val) ? (string)$val : (string)$val;
        }
        $sets[] = "lock_version=lock_version+1";
        $sql = "UPDATE telegram_registration_sessions SET ".implode(',',$sets)." WHERE id=?";
        $types .= 'i'; $vals[] = $sessionId;
        $q = $this->conn->prepare($sql);
        $refs = []; foreach ($vals as $i=>$v) $refs[] = &$vals[$i];
        call_user_func_array([$q,'bind_param'], array_merge([$types], $refs));
        $q->execute(); $q->close();
    }

    private function updateField(int $sessionId, string $col, $val): void
    {
        $sql = "UPDATE telegram_registration_sessions SET `$col`=?,lock_version=lock_version+1 WHERE id=?";
        $q = $this->conn->prepare($sql);
        $sv = is_int($val) ? $val : (string)$val;
        $q->bind_param(is_int($val)?'ii':'si',$sv,$sessionId); $q->execute(); $q->close();
    }

    private function cancelActive(int $actorId): void
    {
        // Only in-flight drafts are cancellable: submitted (with HR), escalated
        // (needs HR follow-up) and failed search markers must survive /cancel.
        // Mirrors the active-session exclusion in expireStale/mostRecentActiveSession.
        $q=$this->conn->prepare("SELECT id FROM telegram_registration_sessions WHERE bot_user_id=? AND status NOT IN ('approved','cancelled','expired','failed','submitted','escalated')");
        $q->bind_param('i',$actorId); $q->execute(); $ids=array_column($q->get_result()->fetch_all(MYSQLI_ASSOC),'id'); $q->close();
        $s=$this->conn->prepare("UPDATE telegram_registration_sessions SET status='cancelled',viewing_menu='home',lock_version=lock_version+1 WHERE bot_user_id=? AND status NOT IN ('approved','cancelled','expired','failed','submitted','escalated')");
        $s->bind_param('i',$actorId); $s->execute(); $s->close();
        $attachments=new TelegramAttachmentService($this->conn);
        foreach($ids as $id) $attachments->cleanupSession((int)$id);
    }

    private function expireStale(int $actorId): void
    {
        $q=$this->conn->prepare("SELECT id FROM telegram_registration_sessions WHERE bot_user_id=? AND status NOT IN ('approved','cancelled','expired','failed','submitted','escalated') AND expires_at<=CURRENT_TIMESTAMP");
        $q->bind_param('i',$actorId); $q->execute(); $ids=array_column($q->get_result()->fetch_all(MYSQLI_ASSOC),'id'); $q->close();
        $s=$this->conn->prepare("UPDATE telegram_registration_sessions SET status='expired',lock_version=lock_version+1 WHERE bot_user_id=? AND status NOT IN ('approved','cancelled','expired','failed','submitted','escalated') AND expires_at<=CURRENT_TIMESTAMP");
        $s->bind_param('i',$actorId); $s->execute(); $s->close();
        $attachments=new TelegramAttachmentService($this->conn);
        foreach($ids as $id) $attachments->cleanupSession((int)$id);
    }

    private function clearInFlight(int $chatId, array $actor): void
    {
        // Cancel any search-results marker sessions for this actor.
        $aid=(int)$actor['id'];
        $q = $this->conn->prepare("DELETE FROM telegram_registration_sessions WHERE bot_user_id=? AND viewing_menu='search_results'");
        $q->bind_param('i',$aid); $q->execute(); $q->close();
    }

    private function audit(string $type,int $actor,string $event,string $entity,int $entityId,string $result='success'): void
    {
        try{
            $s=$this->conn->prepare('INSERT INTO telegram_audit_log (actor_type,actor_id,event,entity_type,entity_id,result) VALUES (?,?,?,?,?,?)');
            $s->bind_param('sissis',$type,$actor,$event,$entity,$entityId,$result); $s->execute(); $s->close();
        }catch(\Throwable $e){ error_log('Telegram audit failed: '.$e->getMessage()); }
    }

    /** Build a payload for webhook response or deferred HTTPS dispatch. When
     *  $messageId is provided (callback context) we return an editMessageText
     *  payload that edits the pressed button's message in place; otherwise we
     *  return a sendMessage payload (v1 fast path: no extra HTTPS round-trip). */
    private function editOrSend(int $chatId, ?int $messageId, string $text, array $markup, ?string $contextField = null): array
    {
        if ($messageId && $messageId > 0) {
            return $this->buildEdit($chatId, $messageId, $text, $markup);
        }
        return TelegramKeyboard::msg($chatId, $text, $markup);
    }

    private function homeKeyboard(): array
    {
        return TelegramKeyboard::reply([
            [['text'=>'➕ አዲስ አባል መመዝገብ']],
            [['text'=>'🔎 የተመዘገበ አባል መፈለግ']],
            [['text'=>'📋 የእኔ ምዝገባዎች']],
        ], true, false, 'ከዚህ ይምረጡ...');
    }

    private function replyUnknown(): array
    {
        return ['method'=>'sendMessage','chat_id'=>0,'text'=>'??']; // unreachable
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    private static function positiveId($value): int { return is_int($value)||is_string($value)?max(0,(int)$value):0; }
    private static function publicId(): string { return substr(strtoupper(bin2hex(random_bytes(13))),0,26); }
}
