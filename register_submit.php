<?php
/** Public website enquiry/registration LEADS, not member-account creation. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/admin/backend/services/SecurityRateLimiter.php';
require_once __DIR__ . '/admin/backend/services/LedgerValidation.php';
require_once __DIR__ . '/site_lang.php';

use App\Services\LedgerValidation as Input;
use App\Services\LedgerInputException;

// The public form posts the visitor's language, so the JSON message that the
// browser shows comes back in the language they are reading. Any other value
// falls back to English, which is what the endpoint has always returned.
$siteLang = strtolower(trim((string)($_POST['lang'] ?? '')));
$msg = static fn(string $en, string $am): string => $siteLang === 'am' ? $am : $en;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jsonResponse(['status'=>'error','message'=>$msg('Use the registration form to submit your request.', 'ጥያቄዎን በምዝገባ ቅጹ ይላኩ።')], 405);
}
if (!($conn instanceof mysqli) || $conn->connect_error) {
    jsonResponse(['status'=>'error','message'=>$msg('Service temporarily unavailable. Please try again later.', 'አገልግሎቱ ለጊዜው አይገኝም። እባክዎ በኋላ ይሞክሩ።')], 503);
}

// Honeypot replies do not store a row or identify the anti-spam check.
if (!empty($_POST['website'])) {
    jsonResponse(['status'=>'success','message'=>$msg('Thank you! We will contact you soon.', 'እናመሰግናለን! በቅርቡ እናገኝዎታለን።')]);
}

try {
    $fullName = Input::text($_POST['full_name'] ?? '', 'Full name', 150, true);
    $phone = Input::text($_POST['phone'] ?? '', 'Phone number', 30, true);
    $email = Input::text($_POST['email'] ?? '', 'Email', 120);
    $age = Input::text($_POST['age'] ?? '', 'Age', 10);
    $gender = Input::choice($_POST['gender'] ?? '', ['', 'male', 'female'], 'Gender');
    $address = Input::text($_POST['address'] ?? '', 'Address', 255);
    $program = Input::text($_POST['program_interest'] ?? '', 'Program', 150);
    $message = Input::text($_POST['message'] ?? '', 'Message', 5000);
    $normalPhone = validatePhone($phone);
    if ($normalPhone === null) throw new LedgerInputException($msg('Please enter a valid phone number.', 'እባክዎ ትክክለኛ ስልክ ቁጥር ያስገቡ።'));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new LedgerInputException($msg('Email is not valid.', 'የኢሜይል አድራሻው ትክክል አይደለም።'));
    // Every age is accepted, with no upper limit for adult members; the
    // bounds only reject impossible values, and no age is guessed.
    if ($age !== '') $age = (string)Input::integer($age, 'Age', 1, 120);
    $phone = $normalPhone;

    // Reserve atomically before INSERT. The old read/increment/write JSON
    // file admitted concurrent submissions and lost increments.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limiter = new \App\Services\SecurityRateLimiter($pdo, __DIR__ . '/admin/uploads/cache');
    $limit = $limiter->consume('public-registration', $ip, 5, 3600);
    if (!$limit['allowed']) {
        header('Retry-After: ' . max(1, (int)$limit['retry_after']));
        jsonResponse(['status'=>'error','message'=>$msg('Too many submissions. Please try again later.', 'ጥያቄዎች በጣም ብዙ ናቸው። እባክዎ በኋላ ይሞክሩ።')], 429);
    }

    $stmt = $conn->prepare('INSERT INTO cms_registration_submissions
        (full_name,phone,email,age,gender,address,program_interest,message,ip_address)
        VALUES (?,?,?,?,?,?,?,?,?)');
    $emailVal = $email !== '' ? $email : null;
    $stmt->bind_param('sssssssss', $fullName, $phone, $emailVal, $age, $gender, $address, $program, $message, $ip);
    $stmt->execute(); $stmt->close();
    jsonResponse(['status'=>'success','message'=>$msg('Thank you! Your registration request has been received. We will contact you soon.', 'እናመሰግናለን! የምዝገባ ጥያቄዎ ደርሷል። በቅርቡ እናገኝዎታለን።')]);
} catch (LedgerInputException $error) {
    jsonResponse(['status'=>'error','message'=>$error->publicMessage], $error->httpStatus);
} catch (Throwable $error) {
    reportInternalError('Public registration submission failed', $error);
    jsonResponse(['status'=>'error','message'=>$msg('Something went wrong. Please try again.', 'የተሳሳተ ነገር ተፈጥሯል። እባክዎ እንደገና ይሞክሩ።')], 500);
}
