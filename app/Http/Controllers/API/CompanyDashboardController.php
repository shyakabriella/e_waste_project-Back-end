<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyDashboardController extends BaseController
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $listingIds = $this->ownedQuery('waste_listings', (int) $user->id)
            ->pluck('id')
            ->values()
            ->all();

        return $this->sendResponse(
            [
                'user' => $this->userPayload($user),

                'summary' => [
                    'listings' => count($listingIds),
                    'pending_offers' => $this->countRelatedTable(
                        table: 'waste_offers',
                        userId: (int) $user->id,
                        listingIds: $listingIds,
                        statusColumnCandidates: ['status', 'offer_status'],
                        statusValues: ['pending', 'sent', 'submitted', 'waiting', 'awaiting_response']
                    ),
                    'pickups' => $this->countRelatedTable(
                        table: 'pickups',
                        userId: (int) $user->id,
                        listingIds: $listingIds
                    ),
                    'active_pickups' => $this->countRelatedTable(
                        table: 'pickups',
                        userId: (int) $user->id,
                        listingIds: $listingIds,
                        statusColumnCandidates: ['status', 'pickup_status'],
                        statusValues: ['pending', 'scheduled', 'assigned', 'in_progress']
                    ),
                    'qr_tags' => $this->countRelatedTable(
                        table: 'qr_tags',
                        userId: (int) $user->id,
                        listingIds: $listingIds
                    ),
                ],

                'latest_listings' => $this->latestListings((int) $user->id),
            ],
            'Company dashboard retrieved successfully.'
        );
    }

    public function listings(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min((int) $request->query('per_page', 50), 100);

        $columns = $this->availableColumns('waste_listings', $this->listingColumns());

        $listings = $this->ownedQuery('waste_listings', (int) $user->id)
            ->select($columns)
            ->latest('id')
            ->paginate($perPage);

        return $this->sendResponse(
            [
                'items' => $listings->items(),
                'pagination' => [
                    'current_page' => $listings->currentPage(),
                    'per_page' => $listings->perPage(),
                    'total' => $listings->total(),
                    'last_page' => $listings->lastPage(),
                ],
            ],
            'Company waste listings retrieved successfully.'
        );
    }

    public function listingDetails(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $columns = $this->availableColumns('waste_listings', $this->listingColumns());

        $listing = $this->ownedQuery('waste_listings', (int) $user->id)
            ->where('id', $id)
            ->select($columns)
            ->first();

        if (!$listing) {
            return $this->sendError(
                'Listing not found.',
                ['listing' => 'This listing does not belong to your company or does not exist.'],
                404
            );
        }

        return $this->sendResponse(
            [
                'listing' => (array) $listing,
                'ai_analysis' => $this->latestRelatedRecord('waste_ai_analyses', $id),
                'verification' => $this->latestRelatedRecord('waste_verifications', $id),
                'offer' => $this->latestRelatedRecord('waste_offers', $id),
                'qr_tag' => $this->latestRelatedRecord('qr_tags', $id),
                'pickup' => $this->latestRelatedRecord('pickups', $id),
                'photos' => $this->relatedRecords('waste_photos', $id, 10),
            ],
            'Listing details retrieved successfully.'
        );
    }

    private function listingColumns(): array
    {
        return [
            'id',
            'institution_id',
            'waste_category_id',
            'title',
            'description',
            'quantity',
            'estimated_weight_kg',
            'ai_estimated_weight_kg',
            'verified_weight_kg',
            'ai_detected_item',
            'ai_detected_category',
            'ai_waste_nature',
            'ai_is_e_waste',
            'ai_confidence',
            'ai_analysis_note',
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
            'status',
            'verified_by',
            'verified_at',
            'verification_notes',
            'created_at',
            'updated_at',
        ];
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

    private function ownedQuery(string $table, int $userId): Builder
    {
        $query = DB::table($table);

        if (!Schema::hasTable($table)) {
            return $query->whereRaw('1 = 0');
        }

        $ownerColumns = [
            'institution_id',
            'institution_user_id',
            'company_user_id',
            'client_user_id',
            'company_id',
            'client_id',
            'user_id',
            'created_by',
            'created_by_user_id',
            'owner_id',
        ];

        $existingOwnerColumns = [];

        foreach ($ownerColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $existingOwnerColumns[] = $column;
            }
        }

        if (empty($existingOwnerColumns)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($subQuery) use ($existingOwnerColumns, $userId) {
            foreach ($existingOwnerColumns as $index => $column) {
                if ($index === 0) {
                    $subQuery->where($column, $userId);
                } else {
                    $subQuery->orWhere($column, $userId);
                }
            }
        });
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

    private function countRelatedTable(
        string $table,
        int $userId,
        array $listingIds,
        array $statusColumnCandidates = [],
        array $statusValues = []
    ): int {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (Schema::hasColumn($table, 'waste_listing_id')) {
            if (empty($listingIds)) {
                return 0;
            }

            $query->whereIn('waste_listing_id', $listingIds);
        } else {
            $query = $this->ownedQuery($table, $userId);
        }

        foreach ($statusColumnCandidates as $statusColumn) {
            if (Schema::hasColumn($table, $statusColumn)) {
                $query->whereIn($statusColumn, $statusValues);
                break;
            }
        }

        return (int) $query->count();
    }

    private function latestListings(int $userId): array
    {
        if (!Schema::hasTable('waste_listings')) {
            return [];
        }

        $columns = $this->availableColumns('waste_listings', [
            'id',
            'title',
            'status',
            'pickup_address',
            'estimated_weight_kg',
            'ai_estimated_weight_kg',
            'verified_weight_kg',
            'expected_price',
            'final_price',
            'created_at',
        ]);

        return $this->ownedQuery('waste_listings', $userId)
            ->select($columns)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'phone' => $user->phone,
            'institution_name' => $user->institution_name,
        ];
    }
}
