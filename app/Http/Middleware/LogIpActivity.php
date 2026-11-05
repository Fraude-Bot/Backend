<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\ScammerController as AdminScammerController;
use App\Http\Controllers\Public\OrganizationController as PublicOrganizationController;
use App\Http\Controllers\Public\ReportController;
use App\Http\Controllers\Public\ScammerController as PublicScammerController;
use App\Models\IpActivity;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogIpActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request, $response)) {
            return $response;
        }

        $activity = $this->activity((string) $request->route()?->getActionName());

        if ($activity === null) {
            return $response;
        }

        $subjectId = $activity['source'] === 'response'
            ? $this->responseSubjectId($response)
            : $this->routeSubjectId($request);

        $ipAddress = $request->ip();

        if ($subjectId === null || $ipAddress === null || $ipAddress === '') {
            Log::warning('Unable to persist IP activity.', [
                'path' => $request->path(),
                'action' => $activity['action'],
            ]);

            return $response;
        }

        try {
            $actor = $request->user();

            IpActivity::create([
                'action' => $activity['action'],
                'subject_type' => $activity['subject_type'],
                'subject_id' => $subjectId,
                'ip_address' => $ipAddress,
                'actor_id' => $actor instanceof User ? $actor->id : null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to persist IP activity.', [
                'exception' => $exception::class,
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    /**
     * @return array{action: string, subject_type: string, source: string}|null
     */
    private function activity(string $action): ?array
    {
        return match ($action) {
            PublicScammerController::class.'@updateProfile' => $this->describe('scammer.profile.updated', 'scammer', 'route'),
            PublicScammerController::class.'@createContact' => $this->describe('scammer.contact.created', 'scammer', 'route'),
            PublicScammerController::class.'@createPaymentMethod' => $this->describe('scammer.payment_method.created', 'scammer', 'route'),
            PublicScammerController::class.'@createOrganization' => $this->describe('scammer.organization.created', 'scammer', 'route'),
            PublicOrganizationController::class.'@updateProfile' => $this->describe('organization.profile.updated', 'organization', 'route'),
            PublicOrganizationController::class.'@createContact' => $this->describe('organization.contact.created', 'organization', 'route'),
            PublicOrganizationController::class.'@createPaymentMethod' => $this->describe('organization.payment_method.created', 'organization', 'route'),
            PublicOrganizationController::class.'@createScammer' => $this->describe('organization.scammer.created', 'organization', 'route'),
            ReportController::class.'@storeScammer', ReportController::class.'@storeOrganization' => $this->describe('report.created', 'report', 'response'),
            AdminScammerController::class.'@store' => $this->describe('scammer.created', 'scammer', 'response'),
            AdminScammerController::class.'@update' => $this->describe('scammer.updated', 'scammer', 'route'),
            AdminScammerController::class.'@destroy' => $this->describe('scammer.deleted', 'scammer', 'route'),
            AdminScammerController::class.'@restore' => $this->describe('scammer.restored', 'scammer', 'route'),
            AdminScammerController::class.'@updateContact' => $this->describe('scammer.contact.updated', 'scammer', 'route'),
            AdminScammerController::class.'@createContact' => $this->describe('scammer.contact.created', 'scammer', 'route'),
            AdminScammerController::class.'@createPaymentMethod' => $this->describe('scammer.payment_method.created', 'scammer', 'route'),
            AdminOrganizationController::class.'@store' => $this->describe('organization.created', 'organization', 'response'),
            AdminOrganizationController::class.'@update' => $this->describe('organization.updated', 'organization', 'route'),
            AdminOrganizationController::class.'@destroy' => $this->describe('organization.deleted', 'organization', 'route'),
            AdminOrganizationController::class.'@restore' => $this->describe('organization.restored', 'organization', 'route'),
            AdminOrganizationController::class.'@addScammer' => $this->describe('organization.scammer.created', 'organization', 'route'),
            AdminOrganizationController::class.'@createContact' => $this->describe('organization.contact.created', 'organization', 'route'),
            AdminOrganizationController::class.'@createPaymentMethod' => $this->describe('organization.payment_method.created', 'organization', 'route'),
            default => null,
        };
    }

    /**
     * @return array{action: string, subject_type: string, source: string}
     */
    private function describe(string $action, string $subjectType, string $source): array
    {
        return [
            'action' => $action,
            'subject_type' => $subjectType,
            'source' => $source,
        ];
    }

    private function routeSubjectId(Request $request): ?int
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        foreach (['id', 'scammer', 'organization'] as $parameter) {
            $id = $this->integerId($route->parameter($parameter));

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private function responseSubjectId(Response $response): ?int
    {
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! array_key_exists('id', $decoded)) {
            return null;
        }

        return $this->integerId($decoded['id']);
    }

    private function integerId(mixed $value): ?int
    {
        if (is_object($value) && isset($value->id)) {
            $value = $value->id;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            return null;
        }

        return $id;
    }
}
