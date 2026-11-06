<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Abort with 403 unless the given user ID is the authenticated user.
     */
    protected function ensureIsCurrentUser($userId): void
    {
        abort_unless((int) $userId === (int) auth()->id(), 403, 'You can only act on your own account.');
    }
}
