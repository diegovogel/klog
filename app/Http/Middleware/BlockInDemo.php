<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockInDemo
{
    /**
     * Block actions that are unsafe on a public demo (package installs that
     * shell out to composer/npm, outbound invite emails, account changes that
     * could lock out the shared demo user). Outside demo mode this is a no-op.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('klog.is_demo')) {
            return $next($request);
        }

        return back()->with('error', 'This action is disabled in the demo.');
    }
}
