<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\OpenAIEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TestOpenAIConnection extends Command
{
    protected $signature = 'openai:test {--property= : Also store an embedding for this property ID}';

    protected $description = 'Verify OpenAI API key and embedding generation for the recommendation system';

    public function handle(OpenAIEmbeddingService $embeddingService): int
    {
        $this->info('OpenAI configuration');
        $this->table(['Setting', 'Value'], [
            ['API key set', config('openai.api_key') ? 'yes' : 'no'],
            ['Model', config('openai.embedding_model')],
            ['Base URL', config('openai.base_url')],
        ]);

        if (!$embeddingService->isConfigured()) {
            $this->error('OPENAI_API_KEY is missing. Add it to .env and run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('Calling OpenAI embeddings API...');

        try {
            $response = Http::timeout((int) config('openai.timeout', 60))
                ->connectTimeout(20)
                ->retry(2, 500)
                ->withToken(config('openai.api_key'))
                ->post(rtrim(config('openai.base_url'), '/') . '/embeddings', [
                    'model' => config('openai.embedding_model'),
                    'input' => 'Ghorfa recommendation system connectivity test',
                ]);
        } catch (ConnectionException $e) {
            $this->error('Could not reach api.openai.com (network timeout).');
            $this->line('Your API key is loaded, but the server could not connect. Check internet/firewall/VPN.');
            $this->line('Error: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('OpenAI request failed (HTTP ' . $response->status() . ')');
            $this->line($response->body());

            return self::FAILURE;
        }

        $vector = $response->json('data.0.embedding');
        $this->info('SUCCESS — received embedding with ' . count($vector) . ' dimensions.');

        $propertyId = $this->option('property');

        if (!$propertyId) {
            $property = Property::where('status', 'approved')->first();
            $propertyId = $property?->id;
        }

        if ($propertyId) {
            $property = Property::find($propertyId);
            if (!$property) {
                $this->warn("Property #{$propertyId} not found.");

                return self::SUCCESS;
            }

            $stored = $embeddingService->generateAndStore($property);
            if ($stored) {
                $this->info("Stored embedding for property #{$property->id}: {$property->title}");
            } else {
                $this->warn("Could not store embedding for property #{$property->id}.");
            }
        }

        return self::SUCCESS;
    }
}
