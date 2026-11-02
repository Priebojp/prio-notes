<?php

namespace App\Http\Middleware;

use App\Services\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSignedIn
{
    public function __construct(private readonly Account $account) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->account->isSignedIn()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
