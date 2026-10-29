<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtectPublicForm
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strlen($request->getContent()) > 16384) {
            return response()->json(['message' => 'This form is too large. Shorten your message or notes and try again.'], 413);
        }
        if ($request->input('website') !== null && $request->input('website') !== '') {
            throw ValidationException::withMessages(['website' => 'Your submission could not be accepted. Leave the website field empty.']);
        }

        return $next($request);
    }
}
