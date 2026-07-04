<?php

namespace App\Services;

use App\Support\FuzzyTextMatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expands property search queries across Arabic ↔ English (and common spellings).
 *
 * Example: بعلبك → baalbek, baalbak, baalbeck
 * Used to boost keyword matches before / alongside embedding similarity.
 */
class SearchQueryLocalizationService
{
    /**
     * Fast offline aliases for common Lebanese locations and terms.
     *
     * @var array<string, list<string>>
     */
    protected array $locationAliases = [
        'بعلبك' => ['baalbek', 'baalbak', 'baalbeck', 'baalbek lebanon'],
        'بيروت' => ['beirut', 'bayrut', 'beyrouth', 'beirut lebanon'],
        'صيدا' => ['saida', 'sidon', 'saida lebanon'],
        'صور' => ['tyre', 'sour', 'tyre lebanon'],
        'طرابلس' => ['tripoli', 'tripoli lebanon', 'trablous'],
        'جونيه' => ['jounieh', 'junieh', 'jbeil coast'],
        'جبيل' => ['byblos', 'jbeil', 'jbayl'],
        'زحلة' => ['zahle', 'zahlé', 'zahleh'],
        'الأوزاعي' => ['ouzai', 'ouzaai', 'awzai', 'awza\'i', 'al ouzai'],
        'اوزاعي' => ['ouzai', 'ouzaai', 'awzai', 'awza\'i'],
        'ازاعي' => ['ouzai', 'ouzaai', 'awzai'],
        'الحمرا' => ['hamra', 'al hamra'],
        'المزرعة' => ['mazraa', 'al mazraa'],
        'مونت اللبنان' => ['mount lebanon', 'mont liban'],
        'لبنان' => ['lebanon', 'lubnan'],
    ];

    public function __construct(
        protected OpenAIEmbeddingService $embeddingService,
    ) {}

    /**
     * @return list<string> Unique search variants (original + translations / spellings).
     */
    public function expand(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $cacheKey = 'semantic_search.variants.v2.' . md5(mb_strtolower($query));
        $ttl = (int) config('semantic_search.query_cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($query) {
            $variants = [$query];

            foreach ($this->dictionaryExpand($query) as $term) {
                $variants[] = $term;
            }

            // Fuzzy dictionary pass for typos like بعبلك → بعلبك
            if (config('semantic_search.fuzzy_match_enabled', true)) {
                foreach ($this->dictionaryFuzzyExpand($query) as $term) {
                    $variants[] = $term;
                }
            }

            $variants = $this->normalizeVariants($variants);

            // AI fallback when dictionary/fuzzy found few useful variants
            if (count($variants) < 3 || $this->needsAiTypoExpansion($query, $variants)) {
                foreach ($this->aiExpand($query) as $term) {
                    $variants[] = $term;
                }
            }

            return $this->normalizeVariants($variants);
        });
    }

    /**
     * Richer text sent to OpenAI embeddings (original + bilingual variants).
     */
    public function embeddingText(string $query, array $variants): string
    {
        $others = array_values(array_filter($variants, fn (string $v) => mb_strtolower($v) !== mb_strtolower($query)));

        if (empty($others)) {
            return $query;
        }

        return $query . "\nSearch also matches: " . implode(', ', array_slice($others, 0, 12));
    }

    /**
     * @return list<string>
     */
    protected function dictionaryExpand(string $query): array
    {
        $found = [];
        $lowerQuery = mb_strtolower($query);

        foreach ($this->locationAliases as $key => $aliases) {
            $lowerKey = mb_strtolower($key);

            if (mb_strpos($lowerQuery, $lowerKey) !== false || mb_strpos($lowerKey, $lowerQuery) !== false) {
                $found[] = $key;
                $found = array_merge($found, $aliases);
            }

            foreach ($aliases as $alias) {
                $lowerAlias = mb_strtolower($alias);
                if (
                    $lowerQuery === $lowerAlias
                    || mb_strpos($lowerQuery, $lowerAlias) !== false
                    || mb_strpos($lowerAlias, $lowerQuery) !== false
                ) {
                    $found[] = $key;
                    $found = array_merge($found, $aliases);
                }
            }
        }

        return $found;
    }

    /**
     * Fuzzy match query to dictionary keys/aliases (handles بعبلك → بعلبك).
     *
     * @return list<string>
     */
    protected function dictionaryFuzzyExpand(string $query): array
    {
        $found = [];
        $minSimilarity = (float) config('semantic_search.fuzzy_min_similarity', 0.72);

        foreach ($this->locationAliases as $key => $aliases) {
            $candidates = array_merge([$key], $aliases);
            $best = 0.0;

            foreach ($candidates as $candidate) {
                $best = max($best, FuzzyTextMatcher::similarity($query, $candidate));
            }

            if ($best >= $minSimilarity) {
                $found[] = $key;
                $found = array_merge($found, $aliases);
            }
        }

        return $found;
    }

    /**
     * True when query looks like a typo (no exact dictionary hit but short location-like text).
     *
     * @param  list<string>  $variants
     */
    protected function needsAiTypoExpansion(string $query, array $variants): bool
    {
        if (count($variants) > 4) {
            return false;
        }

        $normalizedQuery = FuzzyTextMatcher::normalize($query);

        foreach ($this->locationAliases as $key => $aliases) {
            $all = array_merge([$key], $aliases);
            foreach ($all as $term) {
                if (FuzzyTextMatcher::normalize($term) === $normalizedQuery) {
                    return false;
                }
                if (mb_strpos(FuzzyTextMatcher::normalize($term), $normalizedQuery) !== false) {
                    return false;
                }
            }
        }

        return mb_strlen($query) >= 3;
    }

    /**
     * Optional OpenAI translation / transliteration for queries not in the dictionary.
     *
     * @return list<string>
     */
    protected function aiExpand(string $query): array
    {
        if (!config('semantic_search.use_ai_translation', true) || !$this->embeddingService->isConfigured()) {
            return [];
        }

        $apiKey = config('openai.api_key');
        if (!$apiKey) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('openai.timeout', 60))
                ->connectTimeout(20)
                ->withToken($apiKey)
                ->post(rtrim(config('openai.base_url'), '/') . '/chat/completions', [
                    'model' => config('openai.chat_model', 'gpt-4o-mini'),
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You expand Lebanese property search queries across Arabic and English. '
                                . 'Return JSON: {"variants":["term1","term2"]}. '
                                . 'Include the original query, corrected Arabic spelling, English translation, and common Latin transliterations. '
                                . 'Fix typos and missing letters (e.g. بعلب or بعبلك → بعلبك, baalbek, baalbak). '
                                . 'Only property-relevant location/place terms, no sentences.',
                        ],
                        ['role' => 'user', 'content' => $query],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Search query localization failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $content = $response->json('choices.0.message.content');
            $decoded = json_decode((string) $content, true);

            return is_array($decoded['variants'] ?? null) ? $decoded['variants'] : [];
        } catch (\Throwable $e) {
            Log::error('Search query localization exception', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  list<string>  $variants
     * @return list<string>
     */
    protected function normalizeVariants(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            $variant = trim((string) $variant);
            if ($variant === '') {
                continue;
            }

            $key = mb_strtolower($variant);
            if (!isset($normalized[$key])) {
                $normalized[$key] = $variant;
            }
        }

        return array_values($normalized);
    }
}
