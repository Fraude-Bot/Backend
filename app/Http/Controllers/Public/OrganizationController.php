<?php

namespace App\Http\Controllers\Public;

use App\Application\Organization\Commands\CreateOrganizationContactCommand;
use App\Application\Organization\Commands\CreateOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\HasOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\ListOrganizationContactsCommand;
use App\Application\Organization\Commands\ListOrganizationPaymentMethodsCommand;
use App\Application\Organization\Commands\ListOrganizationReportsCommand;
use App\Application\Organization\Commands\ListPublicOrganizationScammersCommand;
use App\Application\Organization\Commands\OrganizationCalendarCommand;
use App\Application\Organization\Commands\OrganizationMapCommand;
use App\Application\Organization\Commands\ShowOrganizationCommand;
use App\Application\Organization\Commands\SuggestOrganizationsCommand;
use App\Application\Organization\Usecases\OrganizationUsecaseInterface;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreOrganizationContactRequest;
use App\Http\Requests\Public\StorePaymentMethodRequest;
use App\Http\Resources\Public\ContactResource;
use App\Http\Resources\Public\OrganizationResource;
use App\Http\Resources\Public\OrganizationScammerResource;
use App\Http\Resources\Public\PaymentMethodResource;
use App\Http\Resources\Public\ReportResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationUsecaseInterface $organizations,
    ) {}

    public function show(Request $request, string $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $organization = $this->organizations->show(new ShowOrganizationCommand((int) $id));

        if (! $organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        return response()->json((new OrganizationResource($organization))->resolve());
    }

    public function calendar(Request $request, string $id, string $year)
    {
        if (
            filter_var($id, FILTER_VALIDATE_INT) === false
            || filter_var($year, FILTER_VALIDATE_INT) === false
            || (int) $year < 2000
            || (int) $year > now()->year + 1
        ) {
            return response()->json(['message' => 'Invalid organization ID or year'], 400);
        }

        $calendar = $this->organizations->calendar(new OrganizationCalendarCommand((int) $id, (int) $year));

        if (! $calendar) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        return response()->json($calendar);
    }

    public function contacts(Request $request, string $id)
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);
        $platform = $request->input('platform', null);

        if (is_string($platform) && $platform !== '') {
            $platform = strtolower($platform);
        }

        if (! is_string($platform)) {
            $platform = null;
        }

        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid organization ID, page or count'], 400);
        }

        $contacts = $this->organizations->contacts(new ListOrganizationContactsCommand((int) $id, (int) $page, (int) $count, $platform));

        if (! $contacts) {
            return response()->json(['message' => 'Organization contacts not found'], 404);
        }

        return response()->json([
            'data' => ContactResource::collection($contacts->items)->resolve(),
            'total' => $contacts->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function createContact(StoreOrganizationContactRequest $request, string $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $organization = $this->organizations->show(new ShowOrganizationCommand((int) $id));

        if (! $organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        try {
            $contact = $this->organizations->createContact(CreateOrganizationContactCommand::fromValidated(
                $organization,
                $request->validated(),
            ));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'reference' => [$exception->getMessage()],
            ]);
        }

        return response()->json((new ContactResource($contact))->resolve(), 201);
    }

    public function paymentMethods(Request $request, string $id)
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);
        $type = $request->input('type', null);

        if (is_string($type) && $type !== '') {
            $type = strtolower($type);
        }

        if (! is_string($type)) {
            $type = null;
        }

        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid organization ID, page or count'], 400);
        }

        $paymentMethods = $this->organizations->paymentMethods(new ListOrganizationPaymentMethodsCommand((int) $id, (int) $page, (int) $count, $type));

        if (! $paymentMethods) {
            return response()->json(['message' => 'Organization payment methods not found'], 404);
        }

        return response()->json([
            'data' => PaymentMethodResource::collection($paymentMethods->items)->resolve(),
            'total' => $paymentMethods->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function createPaymentMethod(StorePaymentMethodRequest $request, string $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $organization = $this->organizations->show(new ShowOrganizationCommand((int) $id));

        if (! $organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $data = $request->validated();
        $type = PaymentMethodType::fromReference((string) $data['reference']);
        if ($this->organizations->hasPaymentMethod(new HasOrganizationPaymentMethodCommand($organization, $type, (string) $data['reference']))) {
            return response()->json(['error' => 'Payment method already exists for this organization'], 422);
        }

        $paymentMethod = $this->organizations->createPaymentMethod(CreateOrganizationPaymentMethodCommand::fromValidated($organization, $data));

        return response()->json((new PaymentMethodResource($paymentMethod))->resolve(), 201);
    }

    public function reports(Request $request, string $id)
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);

        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid organization ID, page or count'], 400);
        }

        $reports = $this->organizations->reports(new ListOrganizationReportsCommand((int) $id, (int) $page, (int) $count));

        if (! $reports) {
            return response()->json(['message' => 'Organization reports not found'], 404);
        }

        return response()->json([
            'data' => ReportResource::collection($reports->items)->resolve(),
            'total' => $reports->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function scammers(Request $request, string $id)
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);

        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid organization ID, page or count'], 400);
        }

        $scammers = $this->organizations->listScammers(new ListPublicOrganizationScammersCommand((int) $id, (int) $page, (int) $count));

        if (! $scammers) {
            return response()->json(['message' => 'Organization scammers not found'], 404);
        }

        return response()->json([
            'data' => OrganizationScammerResource::collection($scammers->items)->resolve(),
            'total' => $scammers->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function map(Request $request, string $id)
    {
        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            (int) $id < 1
        ) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $map = $this->organizations->map(new OrganizationMapCommand((int) $id));

        if (! $map) {
            return response()->json(['message' => 'Organization map not found'], 404);
        }

        if ($map->isEmpty()) {
            return response()->json(['message' => 'Organization map is empty'], 404);
        }

        return response()->json($map->toArray());
    }

    public function suggest(Request $request)
    {
        $query = $request->input('q');

        return response()->json($this->organizations->suggest(new SuggestOrganizationsCommand(is_string($query) ? $query : '')));
    }
}
