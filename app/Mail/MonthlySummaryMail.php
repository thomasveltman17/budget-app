<?php

namespace App\Mail;

use App\Models\Budget;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MonthlySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Monthly Budget Summary — ' . $this->periodStart->format('F Y'),
        );
    }

    public function content(): Content
    {
        $income = Transaction::whereBetween('date', [$this->periodStart, $this->periodEnd])
            ->where('type', 'income')
            ->sum('amount');

        $expenses = Transaction::whereBetween('date', [$this->periodStart, $this->periodEnd])
            ->where('type', 'expense')
            ->sum('amount');

        $netSavings = $income - $expenses;

        // Top 3 spending categories by total amount
        $topCategories = Transaction::whereBetween('date', [$this->periodStart, $this->periodEnd])
            ->where('type', 'expense')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(3)
            ->with('category')
            ->get();

        // Budget performance: load active groups and compute spending for the period
        $budgets = Budget::active()
            ->with('categories')
            ->get()
            ->map(fn (Budget $b) => [
                'name'      => $b->name,
                'percentage'=> $b->percentage,
                'allocated' => $b->allocatedAmount($this->user),
                'spent'     => $b->spentInPeriod($this->periodStart, $this->periodEnd),
            ]);

        // Active savings goals ordered by nearest deadline first
        $savingsGoals = SavingsGoal::where('is_completed', false)
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->get();

        return new Content(
            view: 'emails.monthly-summary',
            with: compact(
                'income',
                'expenses',
                'netSavings',
                'topCategories',
                'budgets',
                'savingsGoals',
            ),
        );
    }
}
