<?php

namespace App\Jobs;

use App\Mail\MonthlySummaryMail;
use App\Models\User;
use App\Services\BudgetPeriodService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendMonthlySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * Runs daily via the scheduler. Each user's effective budget start day is
     * checked against today so the email only fires on the right day per user.
     */
    public function handle(BudgetPeriodService $periodService): void
    {
        $today = Carbon::today();

        User::where('onboarding_completed', true)->each(
            function (User $user) use ($today, $periodService): void {
                // Respect short-month edge cases (e.g. start_day=31 in February)
                $effectiveDay = min($user->budget_start_day, $today->daysInMonth);

                if ($today->day !== $effectiveDay) {
                    return;
                }

                // The period that just ENDED is last month's period
                $lastMonth   = $today->copy()->subMonthNoOverflow();
                $periodStart = $periodService->getPeriodStart($user, $lastMonth->year, $lastMonth->month);
                $periodEnd   = $today->copy()->subDay()->endOfDay();

                Mail::to($user->email)
                    ->queue(new MonthlySummaryMail($user, $periodStart, $periodEnd));
            }
        );
    }
}
