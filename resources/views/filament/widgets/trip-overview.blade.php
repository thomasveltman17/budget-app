@php
    $money = fn (int $cents): string => '€' . number_format($cents / 100, 2, '.', ',');

    $outstanding = $summary['outstanding'];
    $budget      = $summary['budget'];
@endphp

<x-filament-widgets::widget>
    <div style="display:flex; flex-direction:column; gap:1rem;">

        {{-- Headline numbers --}}
        <x-filament::section>
            <x-slot name="heading">What this trip cost you</x-slot>
            <x-slot name="description">
                {{ $trip->start_date->format('d M Y') }} – {{ $trip->end_date->format('d M Y') }}
                · {{ $trip->durationInDays() }} {{ Str::plural('day', $trip->durationInDays()) }}
                · {{ $trip->participants }} {{ Str::plural('person', $trip->participants) }}
            </x-slot>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:0.875rem;">

                <div style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem;">
                    <div style="font-size:0.75rem; color:#6b7280; margin-bottom:0.25rem;">Your share</div>
                    <div style="font-size:1.5rem; font-weight:700; color:#111827;">{{ $money($summary['my_share']) }}</div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">
                        {{ $money($summary['per_day']) }} per day
                    </div>
                </div>

                <div style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem;">
                    <div style="font-size:0.75rem; color:#6b7280; margin-bottom:0.25rem;">Out of pocket</div>
                    <div style="font-size:1.5rem; font-weight:700; color:#111827;">{{ $money($summary['out_of_pocket']) }}</div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">Cash that left your account</div>
                </div>

                <div style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem;">
                    <div style="font-size:0.75rem; color:#6b7280; margin-bottom:0.25rem;">Still open</div>
                    @if ($outstanding === 0)
                        <div style="font-size:1.5rem; font-weight:700; color:#16a34a;">Settled</div>
                        <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">Everything is squared up</div>
                    @elseif ($outstanding > 0)
                        <div style="font-size:1.5rem; font-weight:700; color:#d97706;">{{ $money($outstanding) }}</div>
                        <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">The group still owes you</div>
                    @else
                        <div style="font-size:1.5rem; font-weight:700; color:#ef4444;">{{ $money(abs($outstanding)) }}</div>
                        <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">You still owe the group</div>
                    @endif
                </div>

                @if ($budget['budget'] !== null)
                    <div style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem;">
                        <div style="font-size:0.75rem; color:#6b7280; margin-bottom:0.25rem;">Planned budget</div>
                        <div style="font-size:1.5rem; font-weight:700; color:{{ $budget['is_over'] ? '#ef4444' : '#16a34a' }};">
                            {{ $money($budget['budget']) }}
                        </div>
                        <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">
                            @if ($budget['is_over'])
                                {{ $money(abs($budget['remaining'])) }} over
                            @else
                                {{ $money($budget['remaining']) }} left
                            @endif
                        </div>
                    </div>
                @endif

                @if ($savedSoFar !== null)
                    <div style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem;">
                        <div style="font-size:0.75rem; color:#6b7280; margin-bottom:0.25rem;">Saved up</div>
                        <div style="font-size:1.5rem; font-weight:700; color:#111827;">{{ $money($savedSoFar) }}</div>
                        <div style="font-size:0.75rem; color:#6b7280; margin-top:0.25rem;">
                            via {{ $trip->savingsGoal->name }}
                        </div>
                    </div>
                @endif

            </div>

            @if ($budget['percentage'] !== null)
                @php $pct = min(100, $budget['percentage']); @endphp
                <div style="margin-top:1rem;">
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:#6b7280; margin-bottom:0.375rem;">
                        <span>{{ $money($budget['spent']) }} of {{ $money($budget['budget']) }}</span>
                        <span style="font-weight:600; color:{{ $budget['is_over'] ? '#ef4444' : '#16a34a' }};">{{ $budget['percentage'] }}%</span>
                    </div>
                    <div style="background:#f3f4f6; border-radius:999px; height:10px; overflow:hidden;">
                        <div style="background:{{ $budget['is_over'] ? '#ef4444' : ($budget['percentage'] >= 80 ? '#f59e0b' : '#22c55e') }}; height:10px; border-radius:999px; width:{{ $pct }}%;"></div>
                    </div>
                </div>
            @endif

            @if (! $trip->counts_toward_budget)
                <p style="margin-top:0.875rem; font-size:0.8rem; color:#6b7280;">
                    Ring-fenced — this trip's spending is left out of your monthly category budgets.
                </p>
            @endif
        </x-filament::section>

        {{-- Where the money went --}}
        <x-filament::section>
            <x-slot name="heading">Where your money went</x-slot>
            <x-slot name="description">Your share only — the part you fronted for others is excluded.</x-slot>

            @if ($categories->isEmpty())
                <p style="font-size:0.875rem; color:#6b7280;">No expenses tagged to this trip yet.</p>
            @else
                @foreach ($categories as $row)
                    <div style="margin-bottom:0.875rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; margin-bottom:0.375rem;">
                            <span style="display:flex; align-items:center; gap:0.5rem; color:#111827;">
                                <span style="width:10px; height:10px; border-radius:999px; background:{{ $row['color'] }}; display:inline-block;"></span>
                                {{ $row['name'] }}
                            </span>
                            <span style="color:#6b7280;">
                                <strong style="color:#374151;">{{ $money($row['amount']) }}</strong>
                                · {{ $row['percentage'] }}%
                            </span>
                        </div>
                        <div style="background:#f3f4f6; border-radius:999px; height:8px; overflow:hidden;">
                            <div style="background:{{ $row['color'] }}; height:8px; border-radius:999px; width:{{ min(100, $row['percentage']) }}%;"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </x-filament::section>

        {{-- Day by day --}}
        <x-filament::section>
            <x-slot name="heading">Day by day</x-slot>

            @foreach ($days as $day)
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem;">
                    <span style="font-size:0.8rem; color:#6b7280; width:88px; flex-shrink:0;">{{ $day['label'] }}</span>
                    <div style="flex:1; background:#f3f4f6; border-radius:999px; height:8px; overflow:hidden;">
                        <div style="background:{{ $trip->color }}; height:8px; border-radius:999px; width:{{ (int) round($day['amount'] / $peakDay * 100) }}%;"></div>
                    </div>
                    <span style="font-size:0.8rem; color:#374151; width:80px; text-align:right; flex-shrink:0;">{{ $money($day['amount']) }}</span>
                </div>
            @endforeach
        </x-filament::section>

    </div>
</x-filament-widgets::widget>
