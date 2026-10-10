<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveSchoolContext
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * Resolve the active tenant from the session-stored school id (set only
     * after membership validation) and reject requests without a valid one.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            $this->currentSchool->clear();

            return $next($request);
        }

        $schoolId = $request->session()->get('current_school_id');

        if (! is_int($schoolId) && ! ctype_digit((string) $schoolId)) {
            $this->currentSchool->clear();

            return redirect()->route('schools.select');
        }

        if (! $this->currentSchool->resolve($user, (int) $schoolId)) {
            $request->session()->forget('current_school_id');

            return redirect()->route('schools.select')
                ->withErrors(['school' => 'Sekolah tidak lagi dapat diakses.']);
        }

        return $next($request);
    }
}
