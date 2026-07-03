<?php

namespace App\Observers;

use App\Models\Property;
use App\Services\OpenAIEmbeddingService;

/**
 * Queue embedding generation when a property is saved (optional AI upgrade).
 * Runs only when OPENAI_API_KEY is configured — never blocks page loads.
 */
class PropertyObserver
{
    public function __construct(
        protected OpenAIEmbeddingService $embeddingService,
    ) {}

    public function saved(Property $property): void
    {
        if (!$this->embeddingService->isConfigured()) {
            return;
        }

        if ($property->status !== 'approved') {
            return;
        }

        // Run synchronously for capstone simplicity; swap to a queued job in production.
        $this->embeddingService->generateAndStore($property);
    }
}
