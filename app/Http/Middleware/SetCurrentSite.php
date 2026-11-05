<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user's Current Site for this request.
 *
 * - 1 assigned site  -> auto-selected, no picker shown.
 * - >1 assigned sites -> must have picked a duty site in this session (so
 *   once after every login), otherwise redirected to the site picker (see
 *   ERP Architecture Guideline v1.0). The topbar switches it afterwards.
 * - 0 assigned sites  -> Admin/Super Admin default to "All Sites" (null);
 *   anyone else simply has no site context yet (nothing to enforce until
 *   a site-aware module needs one).
 */
class SetCurrentSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('sites.select', 'sites.switch', 'logout')) {
            return $next($request);
        }

        $assignedSiteIds = $user->sites()->pluck('sites.id');

        if ($assignedSiteIds->count() === 1) {
            $onlySiteId = $assignedSiteIds->first();

            if ($user->current_site_id !== $onlySiteId) {
                $user->update(['current_site_id' => $onlySiteId]);
            }

            session(['current_site_id' => $onlySiteId]);
        } elseif ($assignedSiteIds->count() > 1) {
            // Only this session's own pick counts — the site saved on the
            // user from a previous login doesn't skip the picker.
            $current = session('current_site_id');
            $picked = session()->has('current_site_id')
                && ($current === null ? $user->seesAllSites() : $assignedSiteIds->contains($current));

            if (! $picked) {
                return redirect()->route('sites.select');
            }
        } elseif ($user->seesAllSites()) {
            session(['current_site_id' => session('current_site_id', $user->current_site_id)]);
        }

        return $next($request);
    }
}
