<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyPickupController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->query('per_page', 50), 100);

        if (!Schema::hasTable('waste_listings') || !Schema::hasTable('pickups')) {
            return $this->emptyResponse($perPage);
        }

        $listingIds = $this->companyListingIds((int) $user->id);

        if (empty($listingIds)) {
            return $this->emptyResponse($perPage);
        }

        $pickups = DB::table('pickups')
            ->whereIn('waste_listing_id', $listingIds)
            ->select($this->pickupColumns())
            ->latest('id')
            ->paginate($perPage);

        $listingMap = $this->listingMap($listingIds);

        $items = collect($pickups->items())
            ->map(function ($pickup) use ($listingMap) {
                $pickupArray = (array) $pickup;
                $pickupArray['listing'] = isset($pickup->waste_listing_id)
                    ? (array) ($listingMap[$pickup->waste_listing_id] ?? [])
                    : [];

                return $pickupArray;
            })
            ->values()
            ->all();

        return $this->sendResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $pickups->currentPage(),
                'per_page' => $pickups->perPage(),
                'total' => $pickups->total(),
                'last_page' => $pickups->lastPage(),
            ],
        ], 'Company pickups retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!Schema::hasTable('waste_listings') || !Schema::hasTable('pickups')) {
            return $this->sendError(
                'Pickup not found.',
                ['pickup' => 'Pickup table or listing table does not exist.'],
                404
            );
        }

        $listingIds = $this->companyListingIds((int) $user->id);

        $pickup = DB::table('pickups')
            ->where('id', $id)
            ->whereIn('waste_listing_id', $listingIds)
            ->select($this->pickupColumns())
            ->first();

        if (!$pickup) {
            return $this->sendError(
                'Pickup not found.',
                ['pickup' => 'This pickup does not belong to your company or does not exist.'],
                404
            );
        }

        $listingId = (int) $pickup->waste_listing_id;

        return $this->sendResponse([
            'pickup' => (array) $pickup,
            'listing' => $this->listingById($listingId),
            'qr_tag' => $this->latestRelatedRecord('qr_tags', $listingId),
            'offer' => $this->latestRelatedRecord('waste_offers', $listingId),
            'verification' => $this->latestRelatedRecord('waste_verifications', $listingId),
            'photos' => $this->relatedRecords('waste_photos', $listingId, 10),
        ], 'Pickup details retrieved successfully.');
    }

    private function companyListingIds(int $userId): array
    {
        return DB::table('waste_listings')
            ->where('institution_id', $userId)
            ->pluck('id')
            ->values()
            ->all();
    }

    private function pickupColumns(): array
    {
        return $this->availableColumns('pickups', [
            'id',
            'waste_listing_id',
            'pickup_number',
            'scheduled_at',
            'pickup_date',
            'pickup_time',
            'status',
            'pickup_status',
            'collection_status',
            'pickup_address',
            'address',
            'location',
            'district',
            'sector',
            'cell',
            'village',
            'latitude',
            'longitude',
            'driver_name',
            'driver_phone',
            'vehicle_plate',
            'vehicle_number',
            'institution_confirmed_at',
            'staff_confirmed_at',
            'confirmed_by_institution_at',
            'confirmed_by_staff_at',
            'collected_at',
            'completed_at',
            'cancelled_at',
            'notes',
            'created_at',
            'updated_at',
        ]);
    }

    private function listingMap(array $listingIds)
    {
        return DB::table('waste_listings')
            ->whereIn('id', $listingIds)
            ->select($this->availableColumns('waste_listings', [
                'id',
                'title',
                'description',
                'status',
                'estimated_weight_kg',
                'ai_estimated_weight_kg',
                'verified_weight_kg',
                'expected_price',
                'final_price',
                'pickup_address',
                'created_at',
            ]))
            ->get()
            ->keyBy('id');
    }

    private function listingById(int $listingId): ?array
    {
        $listing = DB::table('waste_listings')
            ->where('id', $listingId)
            ->select($this->availableColumns('waste_listings', [
                'id',
                'title',
                'description',
                'status',
                'estimated_weight_kg',
                'ai_estimated_weight_kg',
                'verified_weight_kg',
                'ai_detected_item',
                'ai_detected_category',
                'expected_price',
                'final_price',
                'currency',
                'pickup_address',
                'district',
                'sector',
                'cell',
                'village',
                'latitude',
                'longitude',
                'created_at',
                'updated_at',
            ]))
            ->first();

        return $listing ? (array) $listing : null;
    }

    private function latestRelatedRecord(string $table, int $listingId): ?array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'waste_listing_id')) {
            return null;
        }

        $record = DB::table($table)
            ->where('waste_listing_id', $listingId)
            ->latest('id')
            ->first();

        return $record ? (array) $record : null;
    }

    private function relatedRecords(string $table, int $listingId, int $limit = 10): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'waste_listing_id')) {
            return [];
        }

        return DB::table($table)
            ->where('waste_listing_id', $listingId)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    private function emptyResponse(int $perPage): JsonResponse
    {
        return $this->sendResponse([
            'items' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
        ], 'Company pickups retrieved successfully.');
    }

    private function availableColumns(string $table, array $candidates): array
    {
        if (!Schema::hasTable($table)) {
            return ['id'];
        }

        $columns = [];

        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                $columns[] = $column;
            }
        }

        return empty($columns) ? ['id'] : $columns;
    }
}
