<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCategorizationService
{
    private const DEFAULT_MODEL = 'gemini-2.5-flash-lite';

    private const BATCH_SIZE = 25;

    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * Categorize a collection of transactions using AI.
     * Skips transactions that already have a category.
     *
     * @param  Transaction[]  $transactions
     * @return int Number of transactions that were newly categorized
     */
    public function categorize(array $transactions): int
    {
        $uncategorized = array_values(array_filter(
            $transactions,
            fn (Transaction $t) => $t->category_id === null
        ));

        if (empty($uncategorized)) {
            return 0;
        }

        if (blank($this->resolveApiKey())) {
            return 0;
        }

        $categoryList = $this->buildCategoryList();

        if (empty($categoryList)) {
            return 0;
        }

        $count = 0;

        foreach (array_chunk($uncategorized, self::BATCH_SIZE) as $chunk) {
            $assignments = $this->callApi($chunk, $categoryList);
            foreach ($assignments as $assignment) {
                $index = $assignment['index'] ?? null;
                $categoryId = $assignment['category_id'] ?? null;

                if ($index === null || $categoryId === null) {
                    continue;
                }

                if (! isset($chunk[$index])) {
                    continue;
                }

                $chunk[$index]->updateQuietly([
                    'category_id' => $categoryId,
                    'ai_categorized' => true,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Categorize a single transaction using AI.
     * Returns false if already categorized, no API key, or API call fails.
     */
    public function categorizeOne(Transaction $transaction): bool
    {
        if ($transaction->category_id !== null) {
            return false;
        }

        $count = $this->categorize([$transaction]);

        return $count > 0;
    }

    private function buildCategoryList(): string
    {
        $categories = Category::whereNull('deleted_at')->get();

        if ($categories->isEmpty()) {
            return '';
        }

        return $categories
            ->map(fn (Category $c) => "{$c->id}: {$c->name} ({$c->type->value})")
            ->implode("\n");
    }

    /**
     * @param  Transaction[]  $chunk
     * @return array<int, array{index: int, category_id: int|null}>
     */
    private function callApi(array $chunk, string $categoryList): array
    {
        $transactionList = collect($chunk)
            ->map(fn (Transaction $t, int $i) => "{$i}: \"{$t->description}\"")
            ->implode("\n");

        $prompt = <<<PROMPT
You are a personal finance categorization assistant.

Available categories (id: name (type)):
{$categoryList}

Transaction descriptions to categorize (index: "description"):
{$transactionList}

Return a JSON array. For each transaction, provide the best matching category_id from the list above.
If you are not confident about a match, use null for that entry.
Only use category IDs from the list above. Do not invent new ones.

Response format (JSON array only, no markdown, no explanation):
[{"index": 0, "category_id": 42}, {"index": 1, "category_id": null}]
PROMPT;

        $apiKey = $this->resolveApiKey();
        $model = $this->resolveModel();
        $url = sprintf(self::API_URL, $model);

        try {
            $response = Http::withQueryParameters(['key' => $apiKey])
                ->timeout(30)
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Gemini AI categorization failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $text = $response->json('candidates.0.content.parts.0.text') ?? '';

            return $this->parseResponse($text);
        } catch (\Throwable $e) {
            Log::error('Gemini AI categorization exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Test that the configured API key can reach the Gemini API.
     * Returns ['success' => true, 'model' => '...'] or ['success' => false, 'error' => '...'].
     *
     * @param  string|null  $apiKey  Override the resolved API key (e.g. a freshly entered key)
     * @param  string|null  $model   Override the resolved model
     * @return array{success: bool, model?: string, error?: string}
     */
    public function testConnection(?string $apiKey = null, ?string $model = null): array
    {
        $apiKey ??= $this->resolveApiKey();
        $model ??= $this->resolveModel();

        if (blank($apiKey)) {
            return ['success' => false, 'error' => 'No API key configured. Save a key first.'];
        }

        $url = sprintf(self::API_URL, $model);

        try {
            $response = Http::withQueryParameters(['key' => $apiKey])
                ->timeout(10)
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => 'Reply with the single word: OK']]],
                    ],
                ]);

            if (! $response->successful()) {
                $status = $response->status();
                $message = $response->json('error.message') ?? $response->body();

                $isInvalidKey = str_contains($message, 'API_KEY_INVALID') || str_contains($message, 'API Key not found');

                $friendlyError = match (true) {
                    $isInvalidKey => 'API key not recognized by Google. Make sure you created it at aistudio.google.com/app/apikey — keys from Google Cloud Console need the Generative Language API enabled separately.',
                    $status === 400 => "Bad request: {$message}",
                    $status === 401 || $status === 403 => 'Invalid or unauthorized API key.',
                    $status === 404 => "Model not found: {$message}",
                    $status === 429 => 'Quota exceeded. Check your Google AI Studio usage limits.',
                    default => "API error {$status}: {$message}",
                };

                return ['success' => false, 'error' => $friendlyError];
            }

            return ['success' => true, 'model' => $model];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Resolve the API key: authenticated user first, then DB user (queue context), then global config.
     */
    private function resolveApiKey(): ?string
    {
        $user = auth()->user() ?? User::first();

        if ($user instanceof User && filled($user->google_ai_api_key)) {
            return $user->google_ai_api_key;
        }

        return config('services.google_ai.api_key') ?: null;
    }

    /**
     * Resolve the model: authenticated user first, then DB user (queue context), then default.
     */
    private function resolveModel(): string
    {
        $user = auth()->user() ?? User::first();

        if ($user instanceof User && filled($user->google_ai_model)) {
            return $user->google_ai_model;
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * @return array<int, array{index: int, category_id: int|null}>
     */
    private function parseResponse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            Log::warning('Gemini AI returned invalid JSON', ['raw' => $json]);

            return [];
        }

        return array_filter($decoded, fn ($item) => isset($item['index']));
    }
}
