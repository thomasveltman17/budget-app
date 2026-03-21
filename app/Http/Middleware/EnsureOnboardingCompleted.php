<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Onboarding;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user
            && ! $user->onboarding_completed
            && ! str_ends_with($request->path(), '/onboarding')
        ) {
            return redirect(Onboarding::getUrl());
        }

        return $next($request);
    }
}
