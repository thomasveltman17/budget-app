<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BudgetPeriodService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BudgetPeriodServiceTest extends TestCase
{
    private BudgetPeriodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BudgetPeriodService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithStartDay(int $day): User
    {
        $user = new User();
        $user->budget_start_day = $day;

        return $user;
    }

    // --- getPeriodStart ---

    public function test_get_period_start_with_day_1(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(1), 2025, 3);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 3, 1)));
    }

    public function test_get_period_start_with_day_15(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(15), 2025, 6);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 6, 15)));
    }

    public function test_get_period_start_with_day_31_in_january(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(31), 2025, 1);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 1, 31)));
    }

    public function test_get_period_start_with_day_31_in_april_clamps_to_30(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(31), 2025, 4);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 4, 30)));
    }

    public function test_get_period_start_with_day_31_in_february_non_leap_year_clamps_to_28(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(31), 2025, 2);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 2, 28)));
    }

    public function test_get_period_start_with_day_31_in_february_leap_year_clamps_to_29(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(31), 2024, 2);

        $this->assertTrue($result->isSameDay(Carbon::create(2024, 2, 29)));
    }

    public function test_get_period_start_with_day_29_in_february_non_leap_year_clamps_to_28(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(29), 2025, 2);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 2, 28)));
    }

    public function test_get_period_start_with_day_29_in_february_leap_year_returns_29(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(29), 2024, 2);

        $this->assertTrue($result->isSameDay(Carbon::create(2024, 2, 29)));
    }

    public function test_get_period_start_with_day_30_in_february_non_leap_year_clamps_to_28(): void
    {
        $result = $this->service->getPeriodStart($this->userWithStartDay(30), 2025, 2);

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 2, 28)));
    }

    // --- getCurrentPeriodStart ---

    public function test_get_current_period_start_when_today_is_on_start_day(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 3, 15));

        $result = $this->service->getCurrentPeriodStart($this->userWithStartDay(15));

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 3, 15)));
    }

    public function test_get_current_period_start_when_today_is_after_start_day(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 3, 20));

        $result = $this->service->getCurrentPeriodStart($this->userWithStartDay(15));

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 3, 15)));
    }

    public function test_get_current_period_start_when_today_is_before_start_day(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 3, 10));

        $result = $this->service->getCurrentPeriodStart($this->userWithStartDay(15));

        $this->assertTrue($result->isSameDay(Carbon::create(2025, 2, 15)));
    }

    public function test_get_current_period_start_wraps_to_previous_year_in_january(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 5));

        $result = $this->service->getCurrentPeriodStart($this->userWithStartDay(15));

        $this->assertTrue($result->isSameDay(Carbon::create(2024, 12, 15)));
    }

    public function test_get_current_period_start_with_day_31_when_before_end_of_month(): void
    {
        // In March (31 days), today is the 20th — start day 31 hasn't passed yet this month
        Carbon::setTestNow(Carbon::create(2025, 3, 20));

        $result = $this->service->getCurrentPeriodStart($this->userWithStartDay(31));

        // Period started Feb 28 (February has 28 days in 2025)
        $this->assertTrue($result->isSameDay(Carbon::create(2025, 2, 28)));
    }

    // --- getCurrentPeriodEnd ---

    public function test_get_current_period_end_is_day_before_next_period_start(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 3, 20));

        $result = $this->service->getCurrentPeriodEnd($this->userWithStartDay(15));

        // Period: Mar 15 – Apr 14
        $this->assertTrue($result->isSameDay(Carbon::create(2025, 4, 14)));
    }

    public function test_get_current_period_end_with_day_31_clamps_correctly_across_months(): void
    {
        // Today: Jan 31 — period just started (Jan 31)
        Carbon::setTestNow(Carbon::create(2025, 1, 31));

        $result = $this->service->getCurrentPeriodEnd($this->userWithStartDay(31));

        // Next period starts Feb 28 (2025 is not a leap year), so end is Feb 27
        $this->assertTrue($result->isSameDay(Carbon::create(2025, 2, 27)));
    }

    public function test_get_current_period_end_wraps_year_correctly(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 20));

        $result = $this->service->getCurrentPeriodEnd($this->userWithStartDay(15));

        // Period: Dec 15 – Jan 14 (next year)
        $this->assertTrue($result->isSameDay(Carbon::create(2026, 1, 14)));
    }
}
