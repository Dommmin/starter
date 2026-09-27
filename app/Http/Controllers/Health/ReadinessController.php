<?php

namespace App\Http\Controllers\Health;

use App\Enums\HealthStatus;
use App\Http\Controllers\Controller;
use App\Services\Health\ReadinessProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /health/ready — stateless readiness for load balancers, the deploy
 * smoke test and uptime monitors. Anonymous callers get only the overall
 * status; per-check results need the X-Health-Token header (HEALTH_TOKEN).
 * Never includes hostnames, versions, exception messages or secrets.
 */
class ReadinessController extends Controller
{
    public function __invoke(Request $request, ReadinessProbe $probe): JsonResponse
    {
        $report = $probe->run();

        $payload = ['status' => $report->status->value];

        if ($this->mayReadDetails($request)) {
            $payload['checks'] = $report->checksToArray();
        }

        return response()->json(
            $payload,
            $report->status === HealthStatus::Fail ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK,
            [
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }

    private function mayReadDetails(Request $request): bool
    {
        $token = config('ops.health.token');
        $provided = $request->header('X-Health-Token');

        return is_string($token) && $token !== ''
            && is_string($provided) && hash_equals($token, $provided);
    }
}
