<?php

namespace App\Http\Controllers\Admin;

use App\Application\Organization\Commands\AddOrganizationScammerCommand;
use App\Application\Organization\Commands\CreateOrganizationCommand;
use App\Application\Organization\Commands\CreateOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\DeleteOrganizationCommand;
use App\Application\Organization\Commands\HasOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\ListOrganizationScammersCommand;
use App\Application\Organization\Commands\ListOrganizationsCommand;
use App\Application\Organization\Commands\LoadOrganizationCommand;
use App\Application\Organization\Commands\RestoreOrganizationCommand;
use App\Application\Organization\Commands\UpdateOrganizationCommand;
use App\Application\Organization\Usecases\OrganizationUsecaseInterface;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
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
        return response()->json($this->organizations->list(new ListOrganizationsCommand));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request)
    {
        $organization = $this->organizations->create(CreateOrganizationCommand::fromValidated($request->validated()));

        $resource = new BasicOrganizationResource($organization);

        return response()->json($resource, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        $organization = $this->organizations->load(new LoadOrganizationCommand($organization));
        $organizationData = $organization->toArray();

        if (request()->query('withScammers') === 'basic') {
            $organizationData['scammers'] = BasicScammerResource::collection(
                $this->organizations->scammers(new ListOrganizationScammersCommand($organization)),
            );
        }

        return response()->json($organizationData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $organization = $this->organizations->update(UpdateOrganizationCommand::fromValidated($organization, $request->validated()));

        return response()->json($organization);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        $this->organizations->delete(new DeleteOrganizationCommand($organization));

        return response()->json(null, 204);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $organization)
    {
        $model = $this->organizations->restore(new RestoreOrganizationCommand($organization));

        $resource = new BasicOrganizationResource($model);

        return response()->json($resource);
    }

    /**
     * Add a scammer to the given organization.
     */
    public function addScammer(Organization $organization, Scammer $scammer)
    {
        $this->organizations->addScammer(new AddOrganizationScammerCommand($organization, $scammer));

        return response()->json(['message' => 'Scammer added successfully'], 201);
    }

    public function createPaymentMethod(
        PaymentMethodRequest $request,
        Organization $organization,
    ) {
        $data = $request->validated();
        $type = PaymentMethodType::from((int) $data['type']);
        if ($this->organizations->hasPaymentMethod(new HasOrganizationPaymentMethodCommand($organization, $type, (string) $data['reference']))) {
            return response()->json(['error' => 'Payment method already exists for this organization'], 422);
        }

        $paymentMethod = $this->organizations->createPaymentMethod(CreateOrganizationPaymentMethodCommand::fromValidated($organization, $data));

        $resource = new BasicPaymentMethodResource($paymentMethod);

        return response()->json($resource, 201);
    }

    /**
     * Display all scammers that belong to the organization.
     */
    public function getScammers(Organization $organization)
    {
        return response()->json($this->organizations->scammers(new ListOrganizationScammersCommand($organization)));
    }
}
