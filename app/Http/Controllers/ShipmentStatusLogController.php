<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShipmentStatusLogRequest;
use App\Http\Requests\UpdateShipmentStatusLogRequest;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentStatusLogController extends Controller
{
    public function __construct(private readonly CourierService $courierService) {}

    public function index(Request $request): Response
    {
        $search = $this->courierService->normalizeSearch($request->query('search'));

        return Inertia::render('shipment-status-logs/index', [
            'logs' => $this->courierService->paginateStatusLogs($search),
            'search' => $search ?? '',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('shipment-status-logs/create', $this->courierService->getStatusLogCreationData());
    }

    public function store(StoreShipmentStatusLogRequest $request): RedirectResponse
    {
        $this->courierService->createStatusLog($request->validated());

        return to_route('shipment-status-logs.index')->with('success', 'Shipment status added successfully.');
    }

    public function update(UpdateShipmentStatusLogRequest $request, int $shipmentStatusLog): RedirectResponse
    {
        $this->courierService->updateStatusLog(
            $this->courierService->findStatusLog($shipmentStatusLog),
            $request->validated(),
        );

        return to_route('shipment-status-logs.index')->with('success', 'Shipment status updated successfully.');
    }
}
