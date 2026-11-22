<?php
// FILE: /admin/id_cards/libs/eth_date_helper.php

function toEthiopianDate($gregorianDate) {
    if (!$gregorianDate) return "---";
    
    $timestamp = strtotime($gregorianDate);
    if ($timestamp === false) return "---";
    $gYear = (int)date('Y', $timestamp);
    $gMonth = (int)date('n', $timestamp);
    $gDay = (int)date('j', $timestamp);

    // Simple conversion logic (approximate but effective for display)
    // For exact liturgical dates, a more complex library is needed, 
    // but this works for standard civil dates.
    
    $ethiopianMonths = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 
        5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ', 
        9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜ'
    ];

    // Standard Gregorian-to-JDN conversion with pure-PHP fallback so hosts
    // without ext-calendar never crash when rendering ID cards.
    $jd_offset = 1723856;
    if (function_exists('cal_to_jd') && defined('CAL_GREGORIAN')) {
        $jdn = cal_to_jd(CAL_GREGORIAN, $gMonth, $gDay, $gYear);
    } else {
        $a = intdiv(14 - $gMonth, 12);
        $y = $gYear + 4800 - $a;
        $m = $gMonth + 12 * $a - 3;
        $jdn = $gDay + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }
    $r = ($jdn - $jd_offset) % 1461;
    $n = ($r % 365) + 365 * intval($r / 1460);
    
    $eyear = 4 * intval(($jdn - $jd_offset) / 1461) + intval($r / 365) - intval($r / 1460);
    $emonth = intval($n / 30) + 1;
    $eday = ($n % 30) + 1;

    return isset($ethiopianMonths[$emonth]) 
        ? "$ethiopianMonths[$emonth] $eday ቀን $eyear ዓ.ም" 
        : "$eday/$emonth/$eyear";
}

// Function to check expiry
function isExpired($generatedDate) {
    if (!$generatedDate) return false;
    $gen = new DateTime($generatedDate);
    $exp = clone $gen;
    $exp->modify('+4 years'); // 4 Years Validity
    return (new DateTime() > $exp);
}
?>