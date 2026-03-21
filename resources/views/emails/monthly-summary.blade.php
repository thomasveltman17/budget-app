<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Budget Summary</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

                    {{-- ── Header ── --}}
                    <tr>
                        <td style="background-color:#1f2937;border-radius:12px 12px 0 0;padding:32px 40px;text-align:center;">
                            <p style="margin:0 0 4px;font-size:13px;color:#9ca3af;letter-spacing:0.08em;text-transform:uppercase;">Budget App</p>
                            <h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#ffffff;">Monthly Budget Summary</h1>
                            <p style="margin:0;font-size:15px;color:#d1d5db;">
                                {{ $periodStart->format('j M') }} – {{ $periodEnd->format('j M Y') }}
                            </p>
                        </td>
                    </tr>

                    {{-- ── Overview Stats ── --}}
                    <tr>
                        <td style="background-color:#ffffff;padding:32px 40px 0;">
                            <h2 style="margin:0 0 20px;font-size:16px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.05em;">Overview</h2>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    {{-- Income --}}
                                    <td width="32%" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px 16px;text-align:center;">
                                        <p style="margin:0 0 4px;font-size:12px;color:#16a34a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Income</p>
                                        <p style="margin:0;font-size:22px;font-weight:700;color:#15803d;">
                                            €{{ number_format($income / 100, 2) }}
                                        </p>
                                    </td>
                                    <td width="4%"></td>
                                    {{-- Expenses --}}
                                    <td width="32%" style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:20px 16px;text-align:center;">
                                        <p style="margin:0 0 4px;font-size:12px;color:#dc2626;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Expenses</p>
                                        <p style="margin:0;font-size:22px;font-weight:700;color:#b91c1c;">
                                            €{{ number_format($expenses / 100, 2) }}
                                        </p>
                                    </td>
                                    <td width="4%"></td>
                                    {{-- Net Savings --}}
                                    <td width="32%" style="background-color:{{ $netSavings >= 0 ? '#f0fdf4' : '#fef2f2' }};border:1px solid {{ $netSavings >= 0 ? '#bbf7d0' : '#fecaca' }};border-radius:8px;padding:20px 16px;text-align:center;">
                                        <p style="margin:0 0 4px;font-size:12px;color:{{ $netSavings >= 0 ? '#16a34a' : '#dc2626' }};font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Net Savings</p>
                                        <p style="margin:0;font-size:22px;font-weight:700;color:{{ $netSavings >= 0 ? '#15803d' : '#b91c1c' }};">
                                            @if($netSavings < 0)
                                                (€{{ number_format(abs($netSavings) / 100, 2) }})
                                            @else
                                                €{{ number_format($netSavings / 100, 2) }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── Top Spending Categories ── --}}
                    @if($topCategories->isNotEmpty())
                    <tr>
                        <td style="background-color:#ffffff;padding:32px 40px 0;">
                            <h2 style="margin:0 0 16px;font-size:16px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.05em;">Top Spending Categories</h2>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <th style="padding:8px 12px 8px 0;text-align:left;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">#</th>
                                    <th style="padding:8px 12px 8px 0;text-align:left;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Category</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Amount</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">% of Total</th>
                                </tr>
                                @foreach($topCategories as $i => $cat)
                                    @php
                                        $pct = $expenses > 0 ? round(($cat->total / $expenses) * 100, 1) : 0;
                                        $catName = $cat->category?->parent
                                            ? $cat->category->parent->name . ' › ' . $cat->category->name
                                            : ($cat->category?->name ?? '—');
                                    @endphp
                                    <tr style="border-bottom:1px solid #f3f4f6;">
                                        <td style="padding:12px 12px 12px 0;font-size:14px;color:#9ca3af;font-weight:600;">{{ $i + 1 }}</td>
                                        <td style="padding:12px 12px 12px 0;font-size:14px;color:#1f2937;font-weight:500;">{{ $catName }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:#374151;font-weight:500;">€{{ number_format($cat->total / 100, 2) }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:#6b7280;">{{ $pct }}%</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- ── Budget Performance ── --}}
                    @if($budgets->isNotEmpty())
                    <tr>
                        <td style="background-color:#ffffff;padding:32px 40px 0;">
                            <h2 style="margin:0 0 16px;font-size:16px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.05em;">Budget Performance</h2>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <th style="padding:8px 12px 8px 0;text-align:left;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Category</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Budgeted</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Spent</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Remaining</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Status</th>
                                </tr>
                                @foreach($budgets as $budget)
                                    @php
                                        $remaining = $budget->amount - $budget->spent;
                                        $isOver    = $budget->spent > $budget->amount;
                                        $catName   = $budget->category?->name ?? '—';
                                    @endphp
                                    <tr style="border-bottom:1px solid #f3f4f6;">
                                        <td style="padding:12px 12px 12px 0;font-size:14px;color:#1f2937;font-weight:500;">{{ $catName }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:#374151;">€{{ number_format($budget->amount / 100, 2) }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:{{ $isOver ? '#b91c1c' : '#374151' }};">€{{ number_format($budget->spent / 100, 2) }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:{{ $isOver ? '#b91c1c' : '#16a34a' }};">
                                            @if($isOver)
                                                −€{{ number_format(abs($remaining) / 100, 2) }}
                                            @else
                                                €{{ number_format($remaining / 100, 2) }}
                                            @endif
                                        </td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;">
                                            @if($isOver)
                                                <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background-color:#fef2f2;color:#b91c1c;">⚠ Over</span>
                                            @else
                                                <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background-color:#f0fdf4;color:#16a34a;">✓ Under</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- ── Savings Goals ── --}}
                    @if($savingsGoals->isNotEmpty())
                    <tr>
                        <td style="background-color:#ffffff;padding:32px 40px 0;">
                            <h2 style="margin:0 0 16px;font-size:16px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.05em;">Savings Goals</h2>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <th style="padding:8px 12px 8px 0;text-align:left;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Goal</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Target</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Saved</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Progress</th>
                                    <th style="padding:8px 0 8px 12px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Deadline</th>
                                </tr>
                                @foreach($savingsGoals as $goal)
                                    @php
                                        $pct = $goal->target_amount > 0
                                            ? min(100, round(($goal->current_amount / $goal->target_amount) * 100, 1))
                                            : 0;
                                        $isOnTrack = $pct >= 50;
                                    @endphp
                                    <tr style="border-bottom:1px solid #f3f4f6;">
                                        <td style="padding:12px 12px 12px 0;font-size:14px;color:#1f2937;font-weight:500;">{{ $goal->name }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:#374151;">€{{ number_format($goal->target_amount / 100, 2) }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:#374151;">€{{ number_format($goal->current_amount / 100, 2) }}</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;font-weight:600;color:{{ $isOnTrack ? '#16a34a' : '#d97706' }};">{{ $pct }}%</td>
                                        <td style="padding:12px 0 12px 12px;text-align:right;font-size:14px;color:#6b7280;">
                                            {{ $goal->deadline ? $goal->deadline->format('j M Y') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- ── Divider before footer ── --}}
                    <tr>
                        <td style="background-color:#ffffff;padding:32px 40px 0;">
                            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">
                        </td>
                    </tr>

                    {{-- ── Footer ── --}}
                    <tr>
                        <td style="background-color:#ffffff;border-radius:0 0 12px 12px;padding:24px 40px 32px;text-align:center;">
                            <p style="margin:0 0 4px;font-size:13px;color:#9ca3af;">
                                You're receiving this because you use Budget App.
                            </p>
                            <p style="margin:0;font-size:13px;color:#d1d5db;">
                                Generated on {{ now()->format('j M Y') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
