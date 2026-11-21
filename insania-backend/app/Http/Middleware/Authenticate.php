<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // This server has no web sign-in page: the staff app signs in through the API. A request without a valid token always gets a plain
        // JSON 401, never an attempt to redirect to a page that does not exist (which used to end in an error).
        return null;
    }
}
