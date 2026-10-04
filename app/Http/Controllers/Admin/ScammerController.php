<?php

namespace App\Http\Controllers\Admin;

use App\Application\Scammer\Commands\CreateScammerContactCommand;
use App\Application\Scammer\Commands\CreateScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\DeleteScammerCommand;
use App\Application\Scammer\Commands\HasScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\ListScammersCommand;
use App\Application\Scammer\Commands\LoadScammerCommand;
use App\Application\Scammer\Commands\RestoreScammerCommand;
use App\Application\Scammer\Commands\StoreScammerCommand;
use App\Application\Scammer\Commands\UpdateScammerCommand;
use App\Application\Scammer\Commands\UpdateScammerContactCommand;
use App\Application\Scammer\Usecases\ScammerUsecaseInterface;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactRequest;
use App\Http\Requests\Admin\PaymentMethodRequest;
use App\Http\Requests\Admin\StoreScammerRequest;
use App\Http\Requests\Admin\UpdateScammerRequest;
use App\Models\Contact;
use App\Models\Scammer;

class ScammerController extends Controller
{
    public function __construct(private ScammerUsecaseInterface $scammers) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json($this->scammers->list(new ListScammersCommand));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScammerRequest $request)
    {
        $scammer = $this->scammers->store(StoreScammerCommand::fromValidated($request->validated()));

        return response()->json($scammer, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Scammer $scammer)
    {
        return response()->json($this->scammers->load(new LoadScammerCommand($scammer)));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScammerRequest $request, Scammer $scammer)
    {
        $scammer = $this->scammers->update(UpdateScammerCommand::fromValidated($scammer, $request->validated()));

        return response()->json($scammer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Scammer $scammer)
    {
        $this->scammers->delete(new DeleteScammerCommand($scammer));

        return response()->json(null, 204);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $scammer)
    {
        $model = $this->scammers->restore(new RestoreScammerCommand($scammer));

        return response()->json($model);
    }

    /**
     * Edit contact information of a scammer
     */
    public function updateContact(ContactRequest $request, Scammer $scammer, Contact $contact)
    {
        $updated = $this->scammers->updateContact(UpdateScammerContactCommand::fromInput(
            $scammer,
            $contact,
            $request->all(),
            $request->has('platform'),
        ));

        if ($updated === null) {
            abort(404);
        }

        return response()->json([
            'id' => $updated->id,
            'platform' => $updated->platform_name,
            'reference' => $updated->reference,
            'is_active' => $updated->is_active,
            'created_at' => $updated->created_at,
            'updated_at' => $updated->updated_at,
        ]);
    }

    // Create contact of a scammer
    public function createContact(ContactRequest $request, Scammer $scammer)
    {
        $contactModel = $this->scammers->createContact(CreateScammerContactCommand::fromValidated($scammer, $request->validated()));

        return response()->json([
            'id' => $contactModel->id,
            'platform' => $contactModel->platform_name,
            'reference' => $contactModel->reference,
            'is_active' => $contactModel->is_active,
            'created_at' => $contactModel->created_at,
            'updated_at' => $contactModel->updated_at,
        ], 201);
    }

    /**
     * Add a payment method to a scammer
     */
    public function createPaymentMethod(PaymentMethodRequest $request, Scammer $scammer)
    {
        $data = $request->validated();
        $type = PaymentMethodType::from((int) $data['type']);
        if ($this->scammers->hasPaymentMethod(new HasScammerPaymentMethodCommand($scammer, $type, (string) $data['reference']))) {
            return response()->json(['error' => 'Payment method with the same reference already exists for this scammer'], 422);
        }

        $paymentMethodModel = $this->scammers->createPaymentMethod(CreateScammerPaymentMethodCommand::fromValidated($scammer, $data));

        $response = $paymentMethodModel->only(['id', 'reference', 'type_name', 'is_active', 'created_at']);
        $response['updated_at'] = $paymentMethodModel->modified_at;

        return response()->json($response, 201);
    }
}
