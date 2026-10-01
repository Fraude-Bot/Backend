<?php

namespace App\Http\Controllers\Admin;

use App\Application\Organization\OrganizationUsecaseInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethodRequest;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Http\Resources\Admin\BasicOrganizationResource;
use App\Http\Resources\Admin\BasicPaymentMethodResource;
use App\Http\Resources\Admin\BasicScammerResource;
use App\Models\Organization;
use App\Models\Scammer;

class OrganizationController extends Controller
{
    public function __construct(private OrganizationUsecaseInterface $organizations) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json($this->organizations->list());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request)
    {
        $organization = $this->organizations->create($request->validated());

        $resource = new BasicOrganizationResource($organization);

        return response()->json($resource, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        $organization = $this->organizations->load($organization);
        $organizationData = $organization->toArray();

        if (request()->query('withScammers') === 'basic') {
            $organizationData['scammers'] = BasicScammerResource::collection($this->organizations->scammers($organization));
        }

        return response()->json($organizationData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $organization = $this->organizations->update($organization, $request->validated());

        return response()->json($organization);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        $this->organizations->delete($organization);

        return response()->json(null, 204);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $organization)
    {
        $model = $this->organizations->restore($organization);

        $resource = new BasicOrganizationResource($model);

        return response()->json($resource);
    }

    /**
     * Add a scammer to the given organization.
     */
    public function addScammer(Organization $organization, Scammer $scammer)
    {
        $this->organizations->addScammer($organization, $scammer);

        return response()->json(['message' => 'Scammer added successfully'], 201);
    }

    public function createPaymentMethod(
        PaymentMethodRequest $request,
        Organization $organization,
    ) {
        $data = $request->validated();
        if ($this->organizations->hasPaymentMethod($organization, $data['type'], $data['reference'])) {
            return response()->json(['error' => 'Payment method already exists for this organization'], 422);
        }

        $paymentMethod = $this->organizations->createPaymentMethod($organization, $data);

        $resource = new BasicPaymentMethodResource($paymentMethod);

        return response()->json($resource, 201);
    }

    /**
     * Display all scammers that belong to the organization.
     */
    public function getScammers(Organization $organization)
    {
        return response()->json($this->organizations->scammers($organization));
    }
}
