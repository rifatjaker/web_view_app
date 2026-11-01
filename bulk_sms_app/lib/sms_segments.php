<?php

declare(strict_types=1);

/**
 * SMS segment count from character length.
 *
 * GSM-7: 160 septets in one part, else 153 per part (concatenation header).
 * Extension characters (^ { } \ [ ~ ] | € and form feed) count as 2.
 * Unicode / UCS-2: 70 UTF-16 code units in one part, else 67 per part.
 * One non-GSM character makes the whole message Unicode.
 */

function sms_text_stats(string $message): array
{
    if ($message === '') {
        return [
            'encoding' => 'gsm',
            'length' => 0,
            'segments' => 0,
        ];
    }

    $sets = sms_gsm_sets();
    $chars = utf8_chars($message);
    $septets = 0;
    $isGsm = true;

    foreach ($chars as $char) {
        if (isset($sets['basic'][$char])) {
            $septets++;
            continue;
        }
        if (isset($sets['ext'][$char])) {
            $septets += 2;
            continue;
        }
        $isGsm = false;
        break;
    }

    if ($isGsm) {
        return [
            'encoding' => 'gsm',
            'length' => $septets,
            'segments' => sms_part_count($septets, 160, 153),
        ];
    }

    $units = sms_utf16_length($message);

    return [
        'encoding' => 'unicode',
        'length' => $units,
        'segments' => sms_part_count($units, 70, 67),
    ];
}

function sms_cost_from_segments(int $segments, float $rate = 0.48): string
{
    $cents = $segments * (int) round($rate * 100);

    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

function sms_part_count(int $length, int $singleLimit, int $multiLimit): int
{
    if ($length <= 0) {
        return 0;
    }
    if ($length <= $singleLimit) {
        return 1;
    }

    return (int) ceil($length / $multiLimit);
}

function sms_utf16_length(string $message): int
{
    $utf16 = iconv('UTF-8', 'UTF-16BE//IGNORE', $message);
    if ($utf16 === false) {
        return 0;
    }

    return intdiv(strlen($utf16), 2);
}

function utf8_chars(string $text): array
{
    if ($text === '') {
        return [];
    }

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($chars) ? $chars : [];
}

function utf8_length(string $text): int
{
    return count(utf8_chars($text));
}

function utf8_excerpt(string $text, int $limit): string
{
    $chars = utf8_chars($text);
    if (count($chars) <= $limit) {
        return $text;
    }

    return implode('', array_slice($chars, 0, $limit)) . '…';
}

function sms_gsm_sets(): array
{
    static $sets = null;
    if ($sets !== null) {
        return $sets;
    }

    $basic = '@£$¥èéùìòÇ' . "\n" . 'Øø' . "\r"
        . 'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#$¤%&\'()*+,-./0123456789:;<=>?'
        . '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    $ext = "\f" . '^{}\\[~]|€';

    $sets = [
        'basic' => array_fill_keys(utf8_chars($basic), true),
        'ext' => array_fill_keys(utf8_chars($ext), true),
    ];

    return $sets;
}
