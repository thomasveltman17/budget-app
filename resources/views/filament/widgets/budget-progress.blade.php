<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Budget Groups</x-slot>
        <x-slot name="description">{{ $periodLabel }}</x-slot>

        @if ($budgets->isEmpty())
            <p style="font-size:0.875rem; color:#6b7280;">No budget groups set up yet.</p>
        @else
            <div>
                @foreach ($budgets as $budget)
                    @php
                        $pct       = $budget['pct'];
                        $spent     = $budget['spent'];
                        $amount    = $budget['amount'];
                        $remaining = $amount - $spent;

                        $barColor    = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#f59e0b' : '#22c55e');
                        $pctColor    = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#d97706' : '#16a34a');
                        $remainColor = $remaining >= 0 ? '#16a34a' : '#ef4444';
                    @endphp

                    <div style="border:1px solid #e5e7eb; border-radius:12px; padding:1rem; margin-bottom:0.875rem;">

                        {{-- Top row: name + badge + usage % --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.625rem;">
                            <div>
                                <span style="font-weight:600; font-size:0.9rem; color:#111827;">{{ $budget['name'] }}</span>
                                <span style="margin-left:8px; font-size:0.72rem; background:#f3f4f6; padding:2px 9px; border-radius:999px; color:#6b7280; font-weight:500;">
                                    {{ $budget['percentage'] }}% of income
                                </span>
                            </div>
                            <span style="font-weight:700; font-size:0.9rem; color:{{ $pctColor }};">{{ $pct }}%</span>
                        </div>

                        {{-- Progress bar --}}
                        <div style="background:#f3f4f6; border-radius:999px; height:10px; margin-bottom:0.75rem; overflow:hidden;">
                            <div style="background:{{ $barColor }}; height:10px; border-radius:999px; width:{{ $pct }}%;"></div>
                        </div>

                        {{-- Bottom row: spent / remaining --}}
                        <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:#6b7280;">
                            <span>
                                <strong style="color:#374151;">€{{ number_format($spent / 100, 2, '.', ',') }}</strong>
                                spent of €{{ number_format($amount / 100, 2, '.', ',') }}
                            </span>
                            <span style="color:{{ $remainColor }}; font-weight:600;">
                                @if ($remaining >= 0)
                                    €{{ number_format($remaining / 100, 2, '.', ',') }} left
                                @else
                                    €{{ number_format(abs($remaining) / 100, 2, '.', ',') }} over
                                @endif
                            </span>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
