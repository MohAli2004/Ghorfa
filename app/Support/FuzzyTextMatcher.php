<?php

namespace App\Support;

/**
 * Fuzzy text matching for Arabic/English property search (typos, partial queries).
 */
class FuzzyTextMatcher
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = str_replace(['ى'], 'ي', $text);
        $text = str_replace(['ة'], 'ه', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public static function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        if (mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false) {
            $shorter = min(mb_strlen($a), mb_strlen($b));
            $longer = max(mb_strlen($a), mb_strlen($b));

            return 0.88 + (0.12 * ($shorter / max($longer, 1)));
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));
        $distance = self::levenshteinMb($a, $b);
        $maxAllowed = self::maxEditDistance($maxLen);

        if ($distance > $maxAllowed) {
            return 0.0;
        }

        $ratio = 1.0 - ($distance / max($maxLen, 1));

        // Boost scores inside the allowed edit-distance window (typos like بعبلك → بعلبك).
        return min(1.0, 0.55 + ($ratio * 0.45));
    }

    public static function maxEditDistance(int $length): int
    {
        if ($length <= 3) {
            return 1;
        }

        if ($length <= 7) {
            return 2;
        }

        return (int) max(2, floor($length * 0.3));
    }

    public static function matches(string $haystack, string $needle, float $minSimilarity = 0.72): bool
    {
        return self::bestSimilarity($haystack, $needle) >= $minSimilarity;
    }

    /**
     * Compare needle to the full field and to individual tokens (words).
     */
    public static function bestSimilarity(string $haystack, string $needle): float
    {
        $haystack = trim($haystack);
        $needle = trim($needle);

        if ($haystack === '' || $needle === '') {
            return 0.0;
        }

        $best = self::similarity($haystack, $needle);

        $tokens = preg_split('/[\s,،\-\/]+/u', $haystack) ?: [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '' || mb_strlen($token) < 2) {
                continue;
            }

            $best = max($best, self::similarity($token, $needle));
        }

        return $best;
    }

    public static function levenshteinMb(string $a, string $b): int
    {
        $aChars = self::chars($a);
        $bChars = self::chars($b);
        $aLen = count($aChars);
        $bLen = count($bChars);

        if ($aLen === 0) {
            return $bLen;
        }

        if ($bLen === 0) {
            return $aLen;
        }

        $matrix = [];

        for ($i = 0; $i <= $bLen; $i++) {
            $matrix[$i][0] = $i;
        }

        for ($j = 0; $j <= $aLen; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $bLen; $i++) {
            for ($j = 1; $j <= $aLen; $j++) {
                $cost = $bChars[$i - 1] === $aChars[$j - 1] ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        return (int) $matrix[$bLen][$aLen];
    }

    /**
     * @return list<string>
     */
    protected static function chars(string $text): array
    {
        $text = self::normalize($text);

        if ($text === '') {
            return [];
        }

        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
