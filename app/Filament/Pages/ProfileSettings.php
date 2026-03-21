<?php

namespace App\Filament\Pages;

use App\Services\AiCategorizationService;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileSettings extends EditProfile
{
    // Use the full panel layout (with sidebar) instead of SimplePage's stripped layout.
    protected string $view = 'filament.pages.profile-settings';

    protected static string $layout = 'filament-panels::components.layout.index';

    public static function getLabel(): string
    {
        return 'Profile & Settings';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Never expose the stored API key — leave the field blank.
        // The placeholder will indicate whether a key is already saved.
        $data['google_ai_api_key'] = null;
        $data['google_ai_model'] ??= 'gemini-2.5-flash-lite';

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->description('Update your name, email address and password.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('current_password')
                            ->label('Current password')
                            ->password()
                            ->revealable()
                            ->rule('current_password')
                            ->requiredWith('password')
                            ->dehydrated(false),

                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->confirmed()
                            ->dehydrated(fn ($state): bool => filled($state)),

                        TextInput::make('password_confirmation')
                            ->label('Confirm new password')
                            ->password()
                            ->revealable()
                            ->dehydrated(false),
                    ]),

                Section::make('AI Auto-categorization')
                    ->description('Connect Google Gemini to automatically categorize imported transactions. Get a free API key at Google AI Studio.')
                    ->headerActions([
                        Action::make('testApiKey')
                            ->label('Test connection')
                            ->icon('heroicon-o-signal')
                            ->color('gray')
                            ->visible(fn (): bool => filled(auth()->user()?->google_ai_api_key))
                            ->action(function (): void {
                                $user = auth()->user();
                                $result = app(AiCategorizationService::class)->testConnection(
                                    apiKey: $user->google_ai_api_key,
                                    model: $user->google_ai_model,
                                );

                                if ($result['success']) {
                                    Notification::make()
                                        ->title('Connection successful')
                                        ->body('Gemini responded correctly using model: ' . $result['model'])
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Connection failed')
                                        ->body($result['error'])
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])
                    ->schema([
                        TextInput::make('google_ai_api_key')
                            ->label('Google AI API Key')
                            ->password()
                            ->revealable()
                            ->placeholder(fn (): string => filled(auth()->user()?->google_ai_api_key)
                                ? '(key saved — enter a new one to replace)'
                                : 'AIza...')
                            ->helperText('Your key is encrypted and stored securely. Leave blank to keep your existing key.')
                            ->dehydrated(fn ($state): bool => filled($state)),

                        Select::make('google_ai_model')
                            ->label('Model')
                            ->options([
                                'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite — recommended · 1,000 req/day free',
                                'gemini-2.5-flash'      => 'Gemini 2.5 Flash — faster & smarter · 250 req/day free',
                                'gemini-2.5-pro'        => 'Gemini 2.5 Pro — most capable · 100 req/day free',
                                'gemini-2.0-flash'      => 'Gemini 2.0 Flash — previous gen (retiring June 2026)',
                            ])
                            ->default('gemini-2.5-flash-lite')
                            ->selectablePlaceholder(false)
                            ->helperText('All listed models have a free tier. Limits reset daily.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        $user->name  = $data['name'];
        $user->email = $data['email'];

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        if (filled($data['google_ai_api_key'] ?? null)) {
            $user->google_ai_api_key = $data['google_ai_api_key'];
        }

        $user->google_ai_model = $data['google_ai_model'];
        $user->save();

        // Re-fill so password fields clear and API key placeholder updates.
        $this->fillForm();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /** @return Action[] */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDashboard')
                ->label('Dashboard')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => route('filament.admin.pages.dashboard')),

            Action::make('save')
                ->label('Save changes')
                ->submit('save')
                ->icon('heroicon-o-check')
                ->color('primary'),
        ];
    }
}
