<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\OpenAIEmbeddingService;
use Illuminate\Console\Command;

class GeneratePropertyEmbeddings extends Command
{
    protected $signature = 'properties:generate-embeddings {--property= : Generate for a single property ID}';

    protected $description = 'Generate and store OpenAI embeddings for property semantic similarity (optional AI upgrade)';

    public function handle(OpenAIEmbeddingService $embeddingService): int
    {
        if (!$embeddingService->isConfigured()) {
            $this->error('OPENAI_API_KEY is not configured in .env');

            return self::FAILURE;
        }

        $query = Property::query()->where('status', 'approved');

        if ($propertyId = $this->option('property')) {
            $query->whereKey($propertyId);
        }

        $properties = $query->get();
        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $success = 0;

        foreach ($properties as $property) {
            $result = $embeddingService->generateAndStore($property);
            if ($result) {
                $success++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Generated embeddings for {$success} / {$properties->count()} properties.");

        return self::SUCCESS;
    }
}
