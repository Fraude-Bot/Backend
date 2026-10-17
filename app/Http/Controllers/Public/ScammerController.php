<?php

namespace App\Http\Controllers\Public;

use App\Application\Scammer\Commands\CreateScammerContactCommand;
use App\Application\Scammer\Commands\ListPublicScammerOrganizationsCommand;
use App\Application\Scammer\Commands\ListScammerContactsCommand;
use App\Application\Scammer\Commands\ListScammerPaymentMethodsCommand;
use App\Application\Scammer\Commands\ListScammerReportsCommand;
use App\Application\Scammer\Commands\ScammerCalendarCommand;
use App\Application\Scammer\Commands\ScammerMapCommand;
use App\Application\Scammer\Commands\ShowScammerCommand;
use App\Application\Scammer\Commands\SuggestScammersCommand;
use App\Application\Scammer\Usecases\ScammerUsecaseInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreScammerContactRequest;
use App\Http\Resources\Public\ContactResource;
use App\Http\Resources\Public\PaymentMethodResource;
use App\Http\Resources\Public\ReportResource;
use App\Http\Resources\Public\ScammerOrganizationResource;
use App\Http\Resources\Public\ScammerResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ScammerController extends Controller
{
    public function __construct(private ScammerUsecaseInterface $scammers) {}

    public function show(Request $request, string $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid scammer ID'], 400);
        }

        $scammer = $this->scammers->show(new ShowScammerCommand((int) $id));

        if (! $scammer) {
            return response()->json(['message' => 'Scammer not found'], 404);
        }

        return response()->json((new ScammerResource($scammer))->resolve());
    }

    public function calendar(Request $request, string $id, string $year)
    {
        if (
            filter_var($id, FILTER_VALIDATE_INT) === false
            || filter_var($year, FILTER_VALIDATE_INT) === false
            || (int) $year < 2000
            || (int) $year > now()->year + 1
        ) {
            return response()->json(['message' => 'Invalid scammer ID or year'], 400);
        }

        $calendar = $this->scammers->calendar(new ScammerCalendarCommand((int) $id, (int) $year));

        if (! $calendar) {
            return response()->json(['message' => 'Scammer calendar not found'], 404);
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
            return response()->json(['message' => 'Invalid scammer ID, page or count'], 400);
        }

        $contacts = $this->scammers->contacts(new ListScammerContactsCommand((int) $id, (int) $page, (int) $count, $platform));

        if (! $contacts) {
            return response()->json(['message' => 'Scammer contacts not found'], 404);
        }

        return response()->json([
            'data' => ContactResource::collection($contacts->items)->resolve(),
            'total' => $contacts->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function createContact(StoreScammerContactRequest $request, string $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid scammer ID'], 400);
        }

        $scammer = $this->scammers->show(new ShowScammerCommand((int) $id));

        if (! $scammer) {
            return response()->json(['message' => 'Scammer not found'], 404);
        }

        try {
            $contact = $this->scammers->createContact(CreateScammerContactCommand::fromValidated(
                $scammer,
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
            return response()->json(['message' => 'Invalid scammer ID, page or count'], 400);
        }

        $paymentMethods = $this->scammers->paymentMethods(new ListScammerPaymentMethodsCommand((int) $id, (int) $page, (int) $count, $type));

        if (! $paymentMethods) {
            return response()->json(['message' => 'Scammer payment methods not found'], 404);
        }

        return response()->json([
            'data' => PaymentMethodResource::collection($paymentMethods->items)->resolve(),
            'total' => $paymentMethods->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
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
            return response()->json(['message' => 'Invalid scammer ID, page or count'], 400);
        }

        $reports = $this->scammers->reports(new ListScammerReportsCommand((int) $id, (int) $page, (int) $count));

        if (! $reports) {
            return response()->json(['message' => 'Scammer reports not found'], 404);
        }

        return response()->json([
            'data' => ReportResource::collection($reports->items)->resolve(),
            'total' => $reports->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function organizations(Request $request, string $id)
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
            return response()->json(['message' => 'Invalid scammer ID, page or count'], 400);
        }

        $organizations = $this->scammers->listOrganizations(new ListPublicScammerOrganizationsCommand((int) $id, (int) $page, (int) $count));

        if (! $organizations) {
            return response()->json(['message' => 'Scammer organizations not found'], 404);
        }

        return response()->json([
            'data' => ScammerOrganizationResource::collection($organizations->items)->resolve(),
            'total' => $organizations->total,
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
            return response()->json(['message' => 'Invalid scammer ID'], 400);
        }

        $map = $this->scammers->map(new ScammerMapCommand((int) $id));

        if (! $map) {
            return response()->json(['message' => 'Scammer map not found'], 404);
        }

        if ($map->isEmpty()) {
            return response()->json(['message' => 'Scammer map is empty'], 404);
        }

        return response()->json($map->toArray());
    }

    public function suggest(Request $request)
    {
        $query = $request->input('q');

        return response()->json($this->scammers->suggest(new SuggestScammersCommand(is_string($query) ? $query : '')));
    }
}
