<?php

namespace App\Http\Controllers\Admin;

use App\Application\Scammer\ScammerUsecaseInterface;
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
        return response()->json($this->scammers->list());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScammerRequest $request)
    {
        $scammer = $this->scammers->store($request->validated());

        return response()->json($scammer, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Scammer $scammer)
    {
        return response()->json($this->scammers->load($scammer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScammerRequest $request, Scammer $scammer)
    {
        $scammer = $this->scammers->update($scammer, $request->validated());

        return response()->json($scammer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Scammer $scammer)
    {
        $this->scammers->delete($scammer);

        return response()->json(null, 204);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $scammer)
    {
        $model = $this->scammers->restore($scammer);

        return response()->json($model);
    }

    /**
     * Edit contact information of a scammer
     */
    public function updateContact(ContactRequest $request, Scammer $scammer, Contact $contact)
    {
        $updated = $this->scammers->updateContact(
            $scammer,
            $contact,
            $request->all(),
            $request->has('platform'),
        );

        if ($updated === null) {
            abort(404);
        }

        return response()->json([
            'id' => $updated->id,
            'name' => $updated->name,
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
        $contactModel = $this->scammers->createContact($scammer, $request->validated());

        return response()->json([
            'id' => $contactModel->id,
            'name' => $contactModel->name,
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
        if ($this->scammers->hasPaymentMethod($scammer, $data['type'], $data['reference'])) {
            return response()->json(['error' => 'Payment method with the same reference already exists for this scammer'], 422);
        }

        $paymentMethodModel = $this->scammers->createPaymentMethod($scammer, $data);

        $response = $paymentMethodModel->only(['id', 'reference', 'type_name', 'is_active', 'created_at']);
        $response['updated_at'] = $paymentMethodModel->modified_at;

        return response()->json($response, 201);
    }
}
