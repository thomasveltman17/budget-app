<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCategorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCategorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiCategorizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_ai.api_key' => 'fake-key']);
        $this->service = new AiCategorizationService();
    }

    private function fakeGeminiResponse(array $assignments): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode($assignments),
                        ]],
                    ],
                ]],
            ], 200),
        ]);
    }

    public function test_categorize_assigns_category_id_when_api_returns_valid_response(): void
    {
        $category = Category::factory()->create(['name' => 'Groceries']);
        $transaction = Transaction::factory()->create([
            'description' => 'Albert Heijn 1234',
            'category_id' => null,
        ]);

        $this->fakeGeminiResponse([
            ['index' => 0, 'category_id' => $category->id],
        ]);

        $count = $this->service->categorize([$transaction]);

        $this->assertEquals(1, $count);
        $this->assertEquals($category->id, $transaction->fresh()->category_id);
        $this->assertTrue($transaction->fresh()->ai_categorized);
    }

    public function test_categorize_skips_transactions_that_already_have_category(): void
    {
        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
        ]);

        Http::fake();

        $count = $this->service->categorize([$transaction]);

        $this->assertEquals(0, $count);
        Http::assertNothingSent();
    }

    public function test_categorize_handles_null_from_api_gracefully(): void
    {
        $transaction = Transaction::factory()->create([
            'description' => 'Unknown merchant XYZ',
            'category_id' => null,
        ]);

        $this->fakeGeminiResponse([
            ['index' => 0, 'category_id' => null],
        ]);

        $count = $this->service->categorize([$transaction]);

        $this->assertEquals(0, $count);
        $this->assertNull($transaction->fresh()->category_id);
    }

    public function test_categorize_handles_malformed_json_without_throwing(): void
    {
        $transaction = Transaction::factory()->create([
            'category_id' => null,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'this is not valid json {{{',
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $count = $this->service->categorize([$transaction]);

        $this->assertEquals(0, $count);
    }

    public function test_categorize_chunks_large_batches_into_multiple_api_calls(): void
    {
        $category = Category::factory()->create();

        // 30 transactions exceeds batch size of 25
        $transactions = Transaction::factory()->count(30)->create([
            'category_id' => null,
        ])->all();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => json_encode(
                            array_map(fn ($i) => ['index' => $i, 'category_id' => $category->id], range(0, 24))
                        )]]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => json_encode(
                            array_map(fn ($i) => ['index' => $i, 'category_id' => $category->id], range(0, 4))
                        )]]],
                    ]],
                ], 200),
        ]);

        $count = $this->service->categorize($transactions);

        $this->assertEquals(30, $count);
        Http::assertSentCount(2);
    }

    public function test_categorize_one_returns_false_when_transaction_already_has_category(): void
    {
        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
        ]);

        Http::fake();

        $result = $this->service->categorizeOne($transaction);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_categorize_one_returns_true_when_category_assigned(): void
    {
        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create([
            'category_id' => null,
        ]);

        $this->fakeGeminiResponse([
            ['index' => 0, 'category_id' => $category->id],
        ]);

        $result = $this->service->categorizeOne($transaction);

        $this->assertTrue($result);
    }

    public function test_categorize_returns_zero_when_no_categories_exist(): void
    {
        $transaction = Transaction::factory()->create([
            'category_id' => null,
        ]);

        Http::fake();

        $count = $this->service->categorize([$transaction]);

        $this->assertEquals(0, $count);
        Http::assertNothingSent();
    }

    public function test_user_api_key_takes_priority_over_global_config(): void
    {
        config(['services.google_ai.api_key' => 'global-key']);

        $user = User::factory()->create(['google_ai_api_key' => 'user-key']);
        $this->actingAs($user);

        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create(['category_id' => null]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        ['index' => 0, 'category_id' => $category->id],
                    ])]]],
                ]],
            ], 200),
        ]);

        $this->service->categorize([$transaction]);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'key=user-key'));
    }

    public function test_global_config_used_when_user_has_no_api_key(): void
    {
        config(['services.google_ai.api_key' => 'global-key']);

        $user = User::factory()->create(['google_ai_api_key' => null]);
        $this->actingAs($user);

        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create(['category_id' => null]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        ['index' => 0, 'category_id' => $category->id],
                    ])]]],
                ]],
            ], 200),
        ]);

        $this->service->categorize([$transaction]);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'key=global-key'));
    }

    public function test_categorize_returns_zero_when_no_api_key_configured(): void
    {
        config(['services.google_ai.api_key' => null]);
        $user = User::factory()->create(['google_ai_api_key' => null]);
        $this->actingAs($user);

        $transaction = Transaction::factory()->create(['category_id' => null]);
        Category::factory()->create();

        Http::fake();

        $count = $this->service->categorize([$transaction]);

        $this->assertEquals(0, $count);
        Http::assertNothingSent();
    }

    public function test_user_model_preference_is_used_in_api_call(): void
    {
        $user = User::factory()->create([
            'google_ai_api_key' => 'user-key',
            'google_ai_model'   => 'gemini-1.5-pro',
        ]);
        $this->actingAs($user);

        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create(['category_id' => null]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        ['index' => 0, 'category_id' => $category->id],
                    ])]]],
                ]],
            ], 200),
        ]);

        $this->service->categorize([$transaction]);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'gemini-1.5-pro'));
    }

    public function test_test_connection_returns_success_when_api_responds(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'OK']]],
                ]],
            ], 200),
        ]);

        $result = $this->service->testConnection('fake-key', 'gemini-2.0-flash');

        $this->assertTrue($result['success']);
        $this->assertEquals('gemini-2.0-flash', $result['model']);
    }

    public function test_test_connection_returns_failure_on_401(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'API key not valid.'],
            ], 401),
        ]);

        $result = $this->service->testConnection('bad-key', 'gemini-2.0-flash');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid or unauthorized API key', $result['error']);
    }

    public function test_test_connection_returns_failure_on_429(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 429),
        ]);

        $result = $this->service->testConnection('fake-key', 'gemini-2.0-flash');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Quota exceeded', $result['error']);
    }

    public function test_test_connection_returns_failure_when_no_api_key(): void
    {
        config(['services.google_ai.api_key' => null]);
        Http::fake();

        $result = $this->service->testConnection(null, null);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No API key configured', $result['error']);
        Http::assertNothingSent();
    }

    public function test_test_connection_returns_failure_on_connection_exception(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->service->testConnection('fake-key', 'gemini-2.0-flash');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection failed', $result['error']);
    }
}
