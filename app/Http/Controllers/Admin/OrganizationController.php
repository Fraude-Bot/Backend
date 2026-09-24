<?php

namespace App\Http\Controllers\Admin;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethodRequest;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Http\Resources\Admin\BasicOrganizationResource;
use App\Http\Resources\Admin\BasicPaymentMethodResource;
use App\Http\Resources\Admin\BasicScammerResource;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Organization::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request)
    {
        $organization = Organization::create($request->validated());

        $resource = new BasicOrganizationResource($organization);

        return response()->json($resource, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        $organizationData = $organization->toArray();

        if (request()->query('withScammers') === 'basic') {
            $organizationData['scammers'] = BasicScammerResource::collection($organization->scammers);
        }

        return response()->json($organizationData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $organization->update($request->validated());

        return response()->json($organization);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response()->json(null, 204);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $organization)
    {
        $model = Organization::onlyTrashed()->findOrFail($organization);
        $model->restore();

        $resource = new BasicOrganizationResource($model);

        return response()->json($resource);
    }

    /**
     * Add a scammer to the given organization.
     */
    public function addScammer(Organization $organization, Scammer $scammer)
    {
        $organization->scammers()->syncWithoutDetaching([$scammer->id]);

        return response()->json(['message' => 'Scammer added successfully'], 201);
    }

    public function createPaymentMethod(
        PaymentMethodRequest $request,
        Organization $organization,
    ) {
        $data = $request->validated();
        if ($organization->paymentMethods()->where([
            'reference' => $data['reference'],
            'type' => $data['type'],
        ])->exists()) {
            return response()->json(['error' => 'Payment method already exists for this organization'], 422);
        }

        $paymentMethod = DB::transaction(function () use ($organization, $data): PaymentMethod {
            $identity = [
                'type' => PaymentMethodType::from((int) $data['type']),
                'reference' => trim($data['reference']),
            ];
            $paymentMethod = PaymentMethod::withTrashed()->firstOrCreate(
                $identity,
                ['is_active' => $data['is_active'] ?? true],
            );
            if ($paymentMethod->trashed()) {
                $paymentMethod->restore();
            }

            $organization->paymentMethods()->syncWithoutDetaching([$paymentMethod->id]);
            SearchCache::invalidate();

            return $paymentMethod;
        });

        $resource = new BasicPaymentMethodResource($paymentMethod);

        return response()->json($resource, 201);
    }

    /**
     * Display all scammers that belong to the organization.
     */
    public function getScammers(Organization $organization)
    {
        return response()->json($organization->scammers);
    }
}
