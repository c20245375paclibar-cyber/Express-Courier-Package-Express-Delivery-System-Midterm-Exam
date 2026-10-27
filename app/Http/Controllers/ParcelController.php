<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParcelRequest;
use App\Http\Requests\UpdateParcelRequest;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
    use Inertia\Response;

    class ParcelController extends Controller
    {
        public function __construct(private readonly CourierService $courierService) {}

        public function index(Request $request): Response
        {
            $search = $this->courierService->normalizeSearch($request->query('search'));

        return Inertia::render('parcels/index', [
            'parcels' => $this->courierService->paginateParcels($search),
            'search' => $search ?? '',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parcels/create');
    }

    public function store(StoreParcelRequest $request): RedirectResponse
    {
        $this->courierService->createParcel($request->validated());

        return to_route('parcels.index')->with('success', 'Parcel created successfully.');
    }

    public function update(UpdateParcelRequest $request, int $parcel): RedirectResponse
    {
        $this->courierService->updateParcel($this->courierService->findParcel($parcel), $request->validated());

        return to_route('parcels.index')->with('success', 'Parcel updated successfully.');
    }
}
