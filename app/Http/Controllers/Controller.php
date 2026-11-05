<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The Site a page works on: whatever the request picked (an empty
     * pick means "all sites" on a filter), or — on first load — the
     * user's duty site from the topbar, so site pickers start on it.
     */
    protected function dutySiteId(Request $request, string $key = 'site_id'): ?int
    {
        if ($request->has($key)) {
            return $request->filled($key) ? $request->integer($key) : null;
        }

        return session('current_site_id', $request->user()?->current_site_id);
    }
}
