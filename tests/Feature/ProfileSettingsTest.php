<?php

namespace Tests\Feature;

use App\Filament\Pages\ProfileSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithApiKey(): User
    {
        $user = User::factory()->create([
            'google_ai_api_key' => 'fake-api-key',
            'google_ai_model'   => 'gemini-2.0-flash',
        ]);
        $this->actingAs($user);

        return $user;
    }

    private function userWithoutApiKey(): User
    {
        $user = User::factory()->create([
            'google_ai_api_key' => null,
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_test_api_key_action_is_visible_when_user_has_api_key(): void
    {
        $this->userWithApiKey();

        Livewire::test(ProfileSettings::class)
            ->assertActionVisible('testApiKey');
    }

    public function test_test_api_key_action_is_hidden_when_user_has_no_api_key(): void
    {
        $this->userWithoutApiKey();

        Livewire::test(ProfileSettings::class)
            ->assertActionHidden('testApiKey');
    }

    public function test_test_api_key_action_sends_success_notification_on_valid_key(): void
    {
        $this->userWithApiKey();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'OK']]],
                ]],
            ], 200),
        ]);

        Livewire::test(ProfileSettings::class)
            ->callAction('testApiKey')
            ->assertNotified('Connection successful');
    }

    public function test_test_api_key_action_sends_danger_notification_on_invalid_key(): void
    {
        $this->userWithApiKey();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'API key not valid.'],
            ], 401),
        ]);

        Livewire::test(ProfileSettings::class)
            ->callAction('testApiKey')
            ->assertNotified('Connection failed');
    }

    public function test_back_to_dashboard_action_is_always_visible(): void
    {
        $this->userWithApiKey();

        Livewire::test(ProfileSettings::class)
            ->assertActionVisible('backToDashboard');
    }
}
