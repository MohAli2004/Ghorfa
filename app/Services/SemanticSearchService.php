<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyEmbedding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * AI-powered semantic search for Ghorfa.
 *
 * Pipeline:
 * 1. Expand query across Arabic ↔ English variants (بعلبك → baalbek, baalbak…).
 * 2. Keyword-score properties on title, description, address, city (prioritized).
 * 3. Embed enriched query + rank by cosine similarity on stored property vectors.
 * 4. Final order: keyword matches first, then blended score.
 */
class SemanticSearchService
{
    /** @var array<int, float> Property ID => display score for the current request */
    protected array $lastScores = [];

    /** @var list<string> */
    protected array $lastVariants = [];

    public function __construct(
        protected OpenAIEmbeddingService $embeddingService,
        protected SearchQueryLocalizationService $localizationService,
    ) {}

    public function isAvailable(): bool
    {
        return config('semantic_search.enabled', true)
            && $this->embeddingService->isConfigured();
    }

    public function extractQueryFromRequest(Request $request): ?string
    {
        $query = trim((string) $request->input('q', ''));

        if ($query !== '') {
            return $query;
        }

        $location = trim((string) $request->input('location', ''));

        return $location !== '' ? $location : null;
    }

    public function shouldUseSemanticSearch(Request $request): bool
    {
        if (!$this->isAvailable() || !$this->extractQueryFromRequest($request)) {
            return false;
        }

        $sort = $request->input('sort', 'recommended');

        if ($sort === 'semantic') {
            return true;
        }

        return config('semantic_search.auto_when_query', true)
            && ($sort === 'recommended' || $sort === null || $sort === '');
    }

    /**
     * @return array<int, float>
     */
    public function getLastScores(): array
    {
        return $this->lastScores;
    }

    /**
     * @return list<string>
     */
    public function getLastVariants(): array
    {
        return $this->lastVariants;
    }

    /**
     * @return array{query: Builder, scores: array<int, float>, active: bool, variants: list<string>}
     */
    public function rankProperties(Builder $query, string $searchText): array
    {
        $this->lastScores = [];
        $this->lastVariants = [];

        $searchText = trim($searchText);

        if ($searchText === '') {
            return ['query' => $query, 'scores' => [], 'active' => false, 'variants' => []];
        }

        $variants = $this->localizationService->expand($searchText);
        $this->lastVariants = $variants;

        $embeddingInput = $this->localizationService->embeddingText($searchText, $variants);
        $queryVector = $this->embedQuery($embeddingInput, $searchText);

        $maxCandidates = (int) config('semantic_search.max_candidates', 500);
        $properties = (clone $query)
            ->with(['embedding', 'amenities'])
            ->limit($maxCandidates)
            ->get();

        if ($properties->isEmpty()) {
            return ['query' => $query, 'scores' => [], 'active' => false, 'variants' => $variants];
        }

        $minSimilarity = (float) config('semantic_search.min_similarity', 0.25);
        $keywordWeight = (float) config('semantic_search.keyword_weight', 0.60);
        $semanticWeight = (float) config('semantic_search.semantic_weight', 0.40);

        $ranked = [];

        foreach ($properties as $property) {
            $keywordScore = $this->keywordScore($property, $variants);
            $semanticScore = 0.0;

            if ($queryVector && $property->embedding?->embedding) {
                $semanticScore = $this->embeddingService->cosineSimilarity(
                    $queryVector,
                    $property->embedding->embedding
                );
            }

            // Keep properties with a strong bilingual keyword hit OR good semantic similarity.
            if ($keywordScore <= 0 && $semanticScore < $minSimilarity) {
                continue;
            }

            $finalScore = ($keywordScore * $keywordWeight) + ($semanticScore * $semanticWeight);

            // Keyword matches (e.g. بعلبك ↔ baalbek in city/title) are ranked above semantic-only hits.
            if ($keywordScore > 0) {
                $finalScore += 0.35;
            }

            $ranked[$property->id] = [
                'keyword' => $keywordScore,
                'semantic' => $semanticScore,
                'final' => min(1.0, $finalScore),
            ];
        }

        uasort($ranked, function (array $a, array $b) {
            if ($a['keyword'] !== $b['keyword']) {
                return $b['keyword'] <=> $a['keyword'];
            }

            return $b['final'] <=> $a['final'];
        });

        if (empty($ranked)) {
            return ['query' => $query, 'scores' => [], 'active' => false, 'variants' => $variants];
        }

        $this->lastScores = array_map(fn (array $row) => $row['final'], $ranked);

        $orderedIds = array_keys($ranked);
        $idList = implode(',', array_map('intval', $orderedIds));

        $orderedQuery = (clone $query)
            ->whereIn('properties.id', $orderedIds)
            ->orderByRaw('(FIELD(properties.id, ' . $idList . ') = 0)')
            ->orderByRaw('FIELD(properties.id, ' . $idList . ')');

        return [
            'query' => $orderedQuery,
            'scores' => $this->lastScores,
            'active' => true,
            'variants' => $variants,
        ];
    }

    /**
     * Score how well property text matches any Arabic/English variant.
     */
    protected function keywordScore(Property $property, array $variants): float
    {
        $fields = [
            'city' => 1.0,
            'title' => 0.95,
            'address' => 0.9,
            'description' => 0.8,
            'country' => 0.7,
        ];

        $best = 0.0;

        foreach ($variants as $variant) {
            $needle = mb_strtolower(trim($variant));
            if ($needle === '' || mb_strlen($needle) < 2) {
                continue;
            }

            foreach ($fields as $field => $weight) {
                $value = mb_strtolower((string) ($property->{$field} ?? ''));
                if ($value === '') {
                    continue;
                }

                if (mb_strpos($value, $needle) !== false) {
                    $best = max($best, $weight);
                }
            }

            foreach ($property->amenities as $amenity) {
                $name = mb_strtolower((string) $amenity->name);
                if ($name !== '' && mb_strpos($name, $needle) !== false) {
                    $best = max($best, 0.65);
                }
            }
        }

        return $best;
    }

    public function embedQuery(string $embeddingInput, string $cacheKeySource): ?array
    {
        $embeddingInput = trim($embeddingInput);
        $cacheKeySource = trim($cacheKeySource);

        if ($embeddingInput === '') {
            return null;
        }

        $cacheKey = 'semantic_search.query.' . md5(mb_strtolower($cacheKeySource));
        $ttl = (int) config('semantic_search.query_cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, fn () => $this->embeddingService->embedText($embeddingInput));
    }
}
