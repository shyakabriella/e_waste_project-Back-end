<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyOfferController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!Schema::hasTable('waste_listings')) {
            return $this->sendResponse([
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 50,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], 'Company offers retrieved successfully.');
        }

        if (!Schema::hasTable('waste_offers')) {
            return $this->sendResponse([
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 50,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], 'Company offers retrieved successfully.');
        }

        $perPage = min((int) $request->query('per_page', 50), 100);

        $listingIds = DB::table('waste_listings')
            ->where('institution_id', (int) $user->id)
            ->pluck('id')
            ->values()
            ->all();

        if (empty($listingIds)) {
            return $this->sendResponse([
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], 'Company offers retrieved successfully.');
        }

        $offerColumns = $this->availableColumns('waste_offers', [
            'id',
            'waste_listing_id',
            'offer_number',
            'amount',
            'offer_amount',
            'price',
            'total_amount',
            'currency',
            'status',
            'offer_status',
            'message',
            'notes',
            'expires_at',
            'responded_at',
            'created_by',
            'created_at',
            'updated_at',
        ]);

        $offers = DB::table('waste_offers')
            ->whereIn('waste_listing_id', $listingIds)
            ->select($offerColumns)
            ->latest('id')
            ->paginate($perPage);

        $listingMap = DB::table('waste_listings')
            ->whereIn('id', $listingIds)
            ->select($this->availableColumns('waste_listings', [
                'id',
                'title',
                'description',
                'estimated_weight_kg',
                'ai_estimated_weight_kg',
                'verified_weight_kg',
                'expected_price',
                'final_price',
                'status',
                'pickup_address',
                'created_at',
            ]))
            ->get()
            ->keyBy('id');

        $items = collect($offers->items())
            ->map(function ($offer) use ($listingMap) {
                $offerArray = (array) $offer;
                $offerArray['listing'] = isset($offer->waste_listing_id)
                    ? (array) ($listingMap[$offer->waste_listing_id] ?? [])
                    : [];

                return $offerArray;
            })
            ->values()
            ->all();

        return $this->sendResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $offers->currentPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
                'last_page' => $offers->lastPage(),
            ],
        ], 'Company offers retrieved successfully.');
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
