<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyEmbedding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional AI upgrade: generates and stores OpenAI embeddings for semantic similarity.
 * Embeddings are created on property create/update — never during page loads.
 */
class OpenAIEmbeddingService
{
    public function isConfigured(): bool
    {
        return !empty(config('openai.api_key'));
    }

    public function buildSourceText(Property $property): string
    {
        $property->loadMissing(['amenities']);

        $amenities = $property->amenities->pluck('name')->implode(', ');

        return trim(implode("\n", array_filter([
            $property->title,
            $property->description,
            $property->address,
            $property->city,
            $property->country,
            $property->property_type,
            $property->listing_type,
            $amenities ? 'Amenities: ' . $amenities : null,
        ])));
    }

  /**
     * Generate embedding via OpenAI API and persist to property_embeddings table.
     */
    public function generateAndStore(Property $property): ?PropertyEmbedding
    {
        if (!$this->isConfigured()) {
            Log::info('OpenAI API key not configured; skipping embedding generation.', [
                'property_id' => $property->id,
            ]);

            return null;
        }

        $sourceText = $this->buildSourceText($property);

        if ($sourceText === '') {
            return null;
        }

        $vector = $this->requestEmbedding($sourceText);

        if ($vector === null) {
            return null;
        }

        return PropertyEmbedding::updateOrCreate(
            ['property_id' => $property->id],
            [
                'model' => config('openai.embedding_model'),
                'embedding' => $vector,
                'source_text' => $sourceText,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Cosine similarity between two embedding vectors (0..1 scale).
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($vectorA), count($vectorB));

        for ($i = 0; $i < $length; $i++) {
            $dot += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] ** 2;
            $normB += $vectorB[$i] ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Embed arbitrary text (e.g. a user's search query). Not persisted to the database.
     */
    public function embedText(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return $this->requestEmbedding($text);
    }

    protected function requestEmbedding(string $text): ?array
    {
        $attempts = 2;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout((int) config('openai.timeout', 60))
                    ->connectTimeout(20)
                    ->retry(2, 500, throw: false)
                    ->withToken(config('openai.api_key'))
                    ->post(rtrim(config('openai.base_url'), '/') . '/embeddings', [
                        'model' => config('openai.embedding_model'),
                        'input' => $text,
                    ]);

                if ($response->successful()) {
                    return $response->json('data.0.embedding');
                }

                Log::warning('OpenAI embedding request failed', [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('OpenAI embedding exception', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt === $attempts) {
                    return null;
                }
            }
        }

        return null;
    }
}
