<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Contact\ContactEntity;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactRequest;
use App\Http\Requests\Admin\PaymentMethodRequest;
use App\Http\Requests\Admin\StoreScammerRequest;
use App\Http\Requests\Admin\UpdateScammerRequest;
use App\Models\Contact;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;
use Illuminate\Support\Facades\DB;

class ScammerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Scammer::with(['contacts', 'paymentMethods', 'organizations'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScammerRequest $request)
    {
        $data = $request->validated();

        $scammer = DB::transaction(function () use ($data): Scammer {
            $scammer = Scammer::create([
                'name' => trim($data['name']),
                'country' => $data['country'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['contacts'] ?? [] as $contactData) {
                $entity = new ContactEntity(
                    id: null,
                    name: $contactData['name'],
                    platformType: PlatformType::from((int) $contactData['platform']),
                    reference: $contactData['reference'],
                    isActive: $contactData['is_active'] ?? true,
                );
                $values = $entity->toArray();
                unset($values['id']);

                $contact = Contact::withTrashed()->firstOrCreate(
                    ['platform' => $values['platform'], 'reference' => $values['reference']],
                    ['name' => $values['name'], 'is_active' => $values['is_active']],
                );
                if ($contact->trashed()) {
                    $contact->restore();
                }
                $scammer->contacts()->syncWithoutDetaching([$contact->id]);
            }

            foreach ($data['paymentMethods'] ?? [] as $paymentMethodData) {
                $paymentMethod = PaymentMethod::withTrashed()->firstOrCreate(
                    [
                        'type' => PaymentMethodType::from((int) $paymentMethodData['type']),
                        'reference' => trim($paymentMethodData['reference']),
                    ],
                    ['is_active' => $paymentMethodData['is_active'] ?? true],
                );
                if ($paymentMethod->trashed()) {
                    $paymentMethod->restore();
                }
                $scammer->paymentMethods()->syncWithoutDetaching([$paymentMethod->id]);
            }

            SearchCache::invalidate();

            return $scammer->load(['contacts', 'paymentMethods']);
        });

        return response()->json($scammer, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Scammer $scammer)
    {
        return response()->json($scammer->load(['contacts', 'paymentMethods', 'organizations']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScammerRequest $request, Scammer $scammer)
    {
        $scammer->update($request->validated());

        return response()->json($scammer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Scammer $scammer)
    {
        $scammer->delete();

        return response()->json(null, 204);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $scammer)
    {
        $model = Scammer::onlyTrashed()->findOrFail($scammer);
        $model->restore();

        return response()->json($model);
    }

    /**
     * Edit contact information of a scammer
     */
    public function updateContact(ContactRequest $request, Scammer $scammer, Contact $contact)
    {
        abort_unless($scammer->contacts()->whereKey($contact->id)->exists(), 404);

        $platform = $contact->platform;

        if ($request->has('platform')) {
            $platform = PlatformType::from((int) $request->validated('platform'));
        }

        $contactEntity = new ContactEntity(
            id: $contact->id,
            name: $request->input('name', $contact->name),
            platformType: $platform,
            reference: $request->input('reference', $contact->reference),
            isActive: $request->input('is_active', $contact->is_active),
        );

        $contact->update($contactEntity->toArray());

        return response()->json([
            'id' => $contact->id,
            'name' => $contact->name,
            'platform' => $contact->platform_name,
            'reference' => $contact->reference,
            'is_active' => $contact->is_active,
            'created_at' => $contact->created_at,
            'updated_at' => $contact->updated_at,
        ]);
    }

    // Create contact of a scammer
    public function createContact(ContactRequest $request, Scammer $scammer)
    {
        $data = $request->validated();

        $contactModel = DB::transaction(function () use ($scammer, $data): Contact {
            $entity = new ContactEntity(
                id: null,
                name: $data['name'],
                platformType: PlatformType::from((int) $data['platform']),
                reference: $data['reference'],
                isActive: $data['is_active'] ?? true,
            );
            $values = $entity->toArray();
            unset($values['id']);

            $contact = Contact::withTrashed()->firstOrCreate(
                ['platform' => $values['platform'], 'reference' => $values['reference']],
                ['name' => $values['name'], 'is_active' => $values['is_active']],
            );
            if ($contact->trashed()) {
                $contact->restore();
            }

            $scammer->contacts()->syncWithoutDetaching([$contact->id]);
            SearchCache::invalidate();

            return $contact;
        });

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
        if ($scammer->paymentMethods()->where(['reference' => $data['reference'], 'type' => $data['type']])->exists()) {
            return response()->json(['error' => 'Payment method with the same reference already exists for this scammer'], 422);
        }

        $paymentMethodModel = DB::transaction(function () use ($scammer, $data): PaymentMethod {
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

            $scammer->paymentMethods()->syncWithoutDetaching([$paymentMethod->id]);
            SearchCache::invalidate();

            return $paymentMethod;
        });

        $response = $paymentMethodModel->only(['id', 'reference', 'type_name', 'is_active', 'created_at']);
        $response['updated_at'] = $paymentMethodModel->modified_at;

        return response()->json($response, 201);
    }
}
