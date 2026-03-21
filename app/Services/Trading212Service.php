<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Trading212Service
{
    public function __construct(private readonly Account $account) {}

    public function getPortfolioValue(): int
    {
        $response = Http::withBasicAuth($this->account->api_key, '')
            ->timeout(10)
            ->get('https://live.trading212.com/api/v0/equity/account/cash');

        if ($response->failed()) {
            throw new \RuntimeException(
                "Trading212 API error [{$response->status()}]: {$response->body()}"
            );
        }

        $total = $response->json('total');

        if (!is_numeric($total)) {
            throw new \RuntimeException(
                "Trading212 API returned unexpected response: missing or invalid 'total' field"
            );
        }

        return (int) round((float) $total * 100);
    }

    public function syncPortfolioValue(Account $account): void
    {
        $value = $this->getPortfolioValue();

        $account->update(['balance' => $value]);

        Log::info('Trading212 portfolio synced', [
            'account_id' => $account->id,
            'balance'    => $value,
        ]);
    }
}
