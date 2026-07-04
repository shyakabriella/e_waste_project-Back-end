<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DriverPickupController extends BaseController
{
    private array $columnCache = [];

    private array $historyStatuses = [
        'completed',
        'complete',
        'done',
        'closed',
        'cancelled',
        'canceled',
    ];

    private array $completedStatuses = [
        'completed',
        'complete',
        'done',
        'closed',
    ];

    private array $cancelledStatuses = [
        'cancelled',
        'canceled',
    ];

    /**
     * Driver Dashboard:
     * assigned pickups, completed pickups, pending confirmations.
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $user = $request->user();

        $assignedQuery = $this->driverPickupQuery($user);
        $this->applyActiveFilter($assignedQuery);
        $assignedPickups = (clone $assignedQuery)->count();

        $completedQuery = $this->driverPickupQuery($user);
        $this->applyCompletedFilter($completedQuery);
        $completedPickups = (clone $completedQuery)->count();

        $pendingQuery = $this->driverPickupQuery($user);
        $this->applyPendingConfirmationFilter($pendingQuery);
        $pendingConfirmations = (clone $pendingQuery)->count();

        $latestQuery = $this->driverPickupQuery($user);
        $this->applyActiveFilter($latestQuery);
        $this->applyPickupOrdering($latestQuery);

        $latestPickups = $latestQuery
            ->limit(5)
            ->get()
            ->map(fn ($pickup) => $this->formatPickup($pickup))
            ->values();

        return $this->sendResponse(
            [
                'user' => $this->formatUser($user),
                'summary' => [
                    'assigned_pickups' => $assignedPickups,
                    'completed_pickups' => $completedPickups,
                    'pending_confirmations' => $pendingConfirmations,
                ],
                'latest_pickups' => $latestPickups,
            ],
            'Driver dashboard retrieved successfully.'
        );
    }

    /**
     * Assigned pickups list.
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $user = $request->user();

        $query = $this->driverPickupQuery($user);
        $this->applyActiveFilter($query);
        $this->applyPickupOrdering($query);

        $perPage = (int) $request->get('per_page', 50);
        $perPage = max(5, min($perPage, 100));

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())
            ->map(fn ($pickup) => $this->formatPickup($pickup))
            ->values();

        return $this->sendResponse(
            [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
            'Assigned pickups retrieved successfully.'
        );
    }

    /**
     * Pickup details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $pickup = $this->driverPickupQuery($request->user())
            ->where('id', $id)
            ->first();

        if (!$pickup) {
            return $this->sendError(
                'Pickup not found.',
                ['pickup' => 'Pickup was not found or is not assigned to this driver.'],
                404
            );
        }

        return $this->sendResponse(
            $this->pickupDetails($pickup),
            'Pickup details retrieved successfully.'
        );
    }

    /**
     * Scan QR tag.
     */
    public function scanQr(Request $request, int $id): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'qr_code' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $pickup = $this->driverPickupQuery($request->user())
            ->where('id', $id)
            ->first();

        if (!$pickup) {
            return $this->sendError(
                'Pickup not found.',
                ['pickup' => 'Pickup was not found or is not assigned to this driver.'],
                404
            );
        }

        $pickupArray = $this->toArray($pickup);
        $listing = $this->listingForPickup($pickupArray);
        $listingId = $listing['id'] ?? $this->valueFromArray($pickupArray, ['waste_listing_id', 'listing_id']);

        $qrTag = $this->findMatchingQrTag(
            trim((string) $request->input('qr_code')),
            $listingId ? (int) $listingId : null,
            (int) $pickupArray['id']
        );

        if (!$qrTag) {
            return $this->sendError(
                'Invalid QR code.',
                ['qr_code' => 'QR tag does not match this pickup.'],
                422
            );
        }

        $this->updatePickup($id, [
            'qr_scanned_at' => now(),
            'scanned_at' => now(),
            'qr_verified_at' => now(),
            'qr_code' => trim((string) $request->input('qr_code')),
            'status' => 'qr_scanned',
            'pickup_status' => 'qr_scanned',
            'collection_status' => 'qr_scanned',
        ]);

        $pickup = DB::table('pickups')->where('id', $id)->first();

        return $this->sendResponse(
            [
                'pickup' => $this->formatPickup($pickup),
                'qr_tag' => $qrTag,
            ],
            'QR tag verified successfully.'
        );
    }

    /**
     * Confirm collection with notes/photo.
     */
    public function confirmCollection(Request $request, int $id): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $pickup = $this->driverPickupQuery($request->user())
            ->where('id', $id)
            ->first();

        if (!$pickup) {
            return $this->sendError(
                'Pickup not found.',
                ['pickup' => 'Pickup was not found or is not assigned to this driver.'],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'notes' => ['nullable', 'string'],
            'collection_notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'collection_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $notes = trim((string) ($request->input('collection_notes') ?: $request->input('notes')));
        $photoPath = $this->storeCollectionPhoto($request, $id);

        $updates = [
            'notes' => $notes,
            'driver_notes' => $notes,
            'collection_notes' => $notes,
            'collection_photo_path' => $photoPath,
            'photo_path' => $photoPath,
            'collected_at' => now(),
            'collection_confirmed_at' => now(),
            'driver_confirmed_at' => now(),
            'status' => 'collected',
            'pickup_status' => 'collected',
            'collection_status' => 'collected',
        ];

        $this->updatePickup($id, $updates);

        $pickup = DB::table('pickups')->where('id', $id)->first();

        return $this->sendResponse(
            $this->pickupDetails($pickup),
            'Collection confirmed successfully.'
        );
    }

    /**
     * Mark pickup completed.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $pickup = $this->driverPickupQuery($request->user())
            ->where('id', $id)
            ->first();

        if (!$pickup) {
            return $this->sendError(
                'Pickup not found.',
                ['pickup' => 'Pickup was not found or is not assigned to this driver.'],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'notes' => ['nullable', 'string'],
            'completion_notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $notes = trim((string) ($request->input('completion_notes') ?: $request->input('notes')));

        $this->updatePickup($id, [
            'notes' => $notes,
            'driver_notes' => $notes,
            'completion_notes' => $notes,
            'completed_at' => now(),
            'status' => 'completed',
            'pickup_status' => 'completed',
            'collection_status' => 'completed',
        ]);

        $pickup = DB::table('pickups')->where('id', $id)->first();

        return $this->sendResponse(
            $this->pickupDetails($pickup),
            'Pickup completed successfully.'
        );
    }

    /**
     * Driver history: completed/cancelled pickups.
     */
    public function history(Request $request): JsonResponse
    {
        if ($error = $this->denyIfNotDriver($request)) {
            return $error;
        }

        $query = $this->driverPickupQuery($request->user());
        $this->applyHistoryFilter($query);
        $this->applyPickupOrdering($query);

        $perPage = (int) $request->get('per_page', 50);
        $perPage = max(5, min($perPage, 100));

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())
            ->map(fn ($pickup) => $this->formatPickup($pickup))
            ->values();

        return $this->sendResponse(
            [
                'items' => $items,
                'history' => $items,
                'pickups' => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
            'Driver history retrieved successfully.'
        );
    }

    private function denyIfNotDriver(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthenticated.', ['error' => 'Login is required.'], 401);
        }

        $role = strtolower(str_replace(['-', ' '], '_', (string) $user->role));

        if (
            $role !== 'driver'
            && $role !== 'enviroserve_driver'
            && $role !== 'pickup_driver'
            && $role !== 'collection_driver'
            && !str_contains($role, 'driver')
        ) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only drivers can access this endpoint.'],
                403
            );
        }

        return null;
    }

    private function driverPickupQuery(User $user)
    {
        $query = DB::table('pickups');

        if (!Schema::hasTable('pickups')) {
            return $query->whereRaw('1 = 0');
        }

        $columns = $this->columns('pickups');

        $idColumns = [
            'driver_id',
            'assigned_driver_id',
            'driver_user_id',
            'assigned_to',
            'collector_id',
            'staff_id',
        ];

        $emailColumns = [
            'driver_email',
            'assigned_driver_email',
        ];

        $phoneColumns = [
            'driver_phone',
            'assigned_driver_phone',
        ];

        $hasFilter = false;

        foreach (array_merge($idColumns, $emailColumns, $phoneColumns) as $column) {
            if (in_array($column, $columns, true)) {
                $hasFilter = true;
                break;
            }
        }

        if (!$hasFilter) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($builder) use ($user, $idColumns, $emailColumns, $phoneColumns) {
            $hasCondition = false;

            foreach ($idColumns as $column) {
                if ($this->hasColumn('pickups', $column)) {
                    $this->addDriverWhere($builder, $hasCondition, $column, $user->id);
                }
            }

            foreach ($emailColumns as $column) {
                if ($this->hasColumn('pickups', $column) && $user->email) {
                    $this->addDriverWhere($builder, $hasCondition, $column, strtolower((string) $user->email));
                }
            }

            foreach ($phoneColumns as $column) {
                if ($this->hasColumn('pickups', $column) && $user->phone) {
                    $this->addDriverWhere($builder, $hasCondition, $column, (string) $user->phone);
                }
            }
        });
    }

    private function addDriverWhere($builder, bool &$hasCondition, string $column, mixed $value): void
    {
        if (!$hasCondition) {
            $builder->where($column, $value);
            $hasCondition = true;

            return;
        }

        $builder->orWhere($column, $value);
    }

    private function applyActiveFilter($query): void
    {
        $statusColumn = $this->firstColumn('pickups', ['status', 'pickup_status', 'collection_status']);

        if ($statusColumn) {
            $query->where(function ($builder) use ($statusColumn) {
                $builder
                    ->whereNull($statusColumn)
                    ->orWhereNotIn($statusColumn, $this->historyStatuses);
            });
        }
    }

    private function applyHistoryFilter($query): void
    {
        $statusColumn = $this->firstColumn('pickups', ['status', 'pickup_status', 'collection_status']);

        $query->where(function ($builder) use ($statusColumn) {
            $hasCondition = false;

            if ($statusColumn) {
                $builder->whereIn($statusColumn, $this->historyStatuses);
                $hasCondition = true;
            }

            foreach (['completed_at', 'cancelled_at', 'canceled_at'] as $column) {
                if ($this->hasColumn('pickups', $column)) {
                    $hasCondition
                        ? $builder->orWhereNotNull($column)
                        : $builder->whereNotNull($column);

                    $hasCondition = true;
                }
            }

            if (!$hasCondition) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    private function applyCompletedFilter($query): void
    {
        $statusColumn = $this->firstColumn('pickups', ['status', 'pickup_status', 'collection_status']);

        $query->where(function ($builder) use ($statusColumn) {
            $hasCondition = false;

            if ($statusColumn) {
                $builder->whereIn($statusColumn, $this->completedStatuses);
                $hasCondition = true;
            }

            if ($this->hasColumn('pickups', 'completed_at')) {
                $hasCondition
                    ? $builder->orWhereNotNull('completed_at')
                    : $builder->whereNotNull('completed_at');

                $hasCondition = true;
            }

            if (!$hasCondition) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    private function applyPendingConfirmationFilter($query): void
    {
        $this->applyActiveFilter($query);

        $confirmationColumns = [
            'institution_confirmed_at',
            'staff_confirmed_at',
            'confirmed_by_institution_at',
            'confirmed_by_staff_at',
            'collection_confirmed_at',
            'driver_confirmed_at',
        ];

        $existing = array_values(array_filter(
            $confirmationColumns,
            fn ($column) => $this->hasColumn('pickups', $column)
        ));

        if (!$existing) {
            return;
        }

        $query->where(function ($builder) use ($existing) {
            foreach ($existing as $index => $column) {
                $index === 0
                    ? $builder->whereNull($column)
                    : $builder->orWhereNull($column);
            }
        });
    }

    private function applyPickupOrdering($query): void
    {
        $column = $this->firstColumn('pickups', [
            'scheduled_at',
            'pickup_date',
            'scheduled_date',
            'created_at',
            'id',
        ]);

        if ($column === 'id') {
            $query->orderByDesc('id');

            return;
        }

        if ($column) {
            $query->orderByDesc($column);
        }
    }

    private function pickupDetails(object $pickup): array
    {
        $pickupArray = $this->toArray($pickup);
        $listing = $this->listingForPickup($pickupArray);
        $company = $listing ? $this->companyForListing($listing) : null;

        return [
            'pickup' => $pickupArray,
            'listing' => $listing,
            'waste_listing' => $listing,
            'company' => $company,
            'institution' => $company,
            'driver' => $this->driverForPickup($pickupArray),
            'qr_tag' => $this->latestRelatedRecord('qr_tags', $listing['id'] ?? null, $pickupArray['id'] ?? null),
            'offer' => $this->latestRelatedRecord('waste_offers', $listing['id'] ?? null, $pickupArray['id'] ?? null),
            'verification' => $this->latestRelatedRecord('waste_verifications', $listing['id'] ?? null, $pickupArray['id'] ?? null),
            'photos' => $this->relatedRecords('waste_photos', $listing['id'] ?? null, $pickupArray['id'] ?? null),
        ];
    }

    private function formatPickup(object $pickup): array
    {
        $pickupArray = $this->toArray($pickup);
        $listing = $this->listingForPickup($pickupArray);

        if ($listing) {
            $pickupArray['listing'] = $listing;
            $pickupArray['waste_listing'] = $listing;
            $pickupArray['company'] = $this->companyForListing($listing);
        }

        return $pickupArray;
    }

    private function listingForPickup(array $pickup): ?array
    {
        if (!Schema::hasTable('waste_listings')) {
            return null;
        }

        $listingId = $this->valueFromArray($pickup, ['waste_listing_id', 'listing_id']);

        if (!$listingId) {
            return null;
        }

        $listing = DB::table('waste_listings')
            ->where('id', $listingId)
            ->first();

        return $listing ? $this->toArray($listing) : null;
    }

    private function companyForListing(array $listing): ?array
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $companyId = $this->valueFromArray($listing, [
            'institution_id',
            'company_id',
            'customer_id',
            'client_id',
            'user_id',
        ]);

        if (!$companyId) {
            return null;
        }

        $columns = array_values(array_intersect(
            [
                'id',
                'name',
                'email',
                'role',
                'status',
                'phone',
                'address',
                'institution_name',
                'institution_type',
                'district',
                'sector',
                'cell',
                'village',
            ],
            $this->columns('users')
        ));

        $company = DB::table('users')
            ->select($columns ?: ['id'])
            ->where('id', $companyId)
            ->first();

        return $company ? $this->toArray($company) : null;
    }

    private function driverForPickup(array $pickup): ?array
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $driverId = $this->valueFromArray($pickup, [
            'driver_id',
            'assigned_driver_id',
            'driver_user_id',
            'collector_id',
            'staff_id',
        ]);

        if (!$driverId) {
            return null;
        }

        $columns = array_values(array_intersect(
            [
                'id',
                'name',
                'email',
                'role',
                'status',
                'phone',
                'staff_code',
                'staff_position',
                'vehicle_plate',
                'vehicle_number',
                'vehicle_type',
                'license_number',
            ],
            $this->columns('users')
        ));

        $driver = DB::table('users')
            ->select($columns ?: ['id'])
            ->where('id', $driverId)
            ->first();

        return $driver ? $this->toArray($driver) : null;
    }

    private function latestRelatedRecord(string $table, mixed $listingId, mixed $pickupId): ?array
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        $query = DB::table($table);

        $hasCondition = false;

        $query->where(function ($builder) use ($table, $listingId, $pickupId, &$hasCondition) {
            foreach (['waste_listing_id', 'listing_id'] as $column) {
                if ($listingId && $this->hasColumn($table, $column)) {
                    $hasCondition
                        ? $builder->orWhere($column, $listingId)
                        : $builder->where($column, $listingId);

                    $hasCondition = true;
                }
            }

            foreach (['pickup_id'] as $column) {
                if ($pickupId && $this->hasColumn($table, $column)) {
                    $hasCondition
                        ? $builder->orWhere($column, $pickupId)
                        : $builder->where($column, $pickupId);

                    $hasCondition = true;
                }
            }

            if (!$hasCondition) {
                $builder->whereRaw('1 = 0');
            }
        });

        $orderColumn = $this->firstColumn($table, ['created_at', 'id']);

        if ($orderColumn) {
            $query->orderByDesc($orderColumn);
        }

        $record = $query->first();

        return $record ? $this->toArray($record) : null;
    }

    private function relatedRecords(string $table, mixed $listingId, mixed $pickupId): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);

        $hasCondition = false;

        $query->where(function ($builder) use ($table, $listingId, $pickupId, &$hasCondition) {
            foreach (['waste_listing_id', 'listing_id'] as $column) {
                if ($listingId && $this->hasColumn($table, $column)) {
                    $hasCondition
                        ? $builder->orWhere($column, $listingId)
                        : $builder->where($column, $listingId);

                    $hasCondition = true;
                }
            }

            if ($pickupId && $this->hasColumn($table, 'pickup_id')) {
                $hasCondition
                    ? $builder->orWhere('pickup_id', $pickupId)
                    : $builder->where('pickup_id', $pickupId);

                $hasCondition = true;
            }

            if (!$hasCondition) {
                $builder->whereRaw('1 = 0');
            }
        });

        $orderColumn = $this->firstColumn($table, ['created_at', 'id']);

        if ($orderColumn) {
            $query->orderByDesc($orderColumn);
        }

        return $query
            ->limit(10)
            ->get()
            ->map(fn ($record) => $this->toArray($record))
            ->values()
            ->all();
    }

    private function findMatchingQrTag(string $qrCode, ?int $listingId, ?int $pickupId): ?array
    {
        if (!Schema::hasTable('qr_tags')) {
            return null;
        }

        $codeColumns = array_values(array_filter(
            ['qr_code', 'code', 'tag_code', 'qr_identifier', 'uuid', 'token', 'reference'],
            fn ($column) => $this->hasColumn('qr_tags', $column)
        ));

        if (!$codeColumns) {
            return null;
        }

        $query = DB::table('qr_tags');

        $query->where(function ($builder) use ($codeColumns, $qrCode) {
            foreach ($codeColumns as $index => $column) {
                $index === 0
                    ? $builder->where($column, $qrCode)
                    : $builder->orWhere($column, $qrCode);
            }
        });

        $relationColumns = [];

        foreach (['waste_listing_id', 'listing_id'] as $column) {
            if ($listingId && $this->hasColumn('qr_tags', $column)) {
                $relationColumns[] = [$column, $listingId];
            }
        }

        if ($pickupId && $this->hasColumn('qr_tags', 'pickup_id')) {
            $relationColumns[] = ['pickup_id', $pickupId];
        }

        if ($relationColumns) {
            $query->where(function ($builder) use ($relationColumns) {
                foreach ($relationColumns as $index => [$column, $value]) {
                    $index === 0
                        ? $builder->where($column, $value)
                        : $builder->orWhere($column, $value);
                }
            });
        }

        $tag = $query->first();

        return $tag ? $this->toArray($tag) : null;
    }

    private function storeCollectionPhoto(Request $request, int $pickupId): ?string
    {
        $file = $request->file('photo') ?: $request->file('collection_photo');

        if (!$file || !$file->isValid()) {
            return null;
        }

        return $file->store("driver-pickups/{$pickupId}", 'public');
    }

    private function updatePickup(int $pickupId, array $updates): void
    {
        if (!Schema::hasTable('pickups')) {
            return;
        }

        $cleanUpdates = [];

        foreach ($updates as $column => $value) {
            if ($value !== null && $this->hasColumn('pickups', $column)) {
                $cleanUpdates[$column] = $value;
            }
        }

        if ($this->hasColumn('pickups', 'updated_at')) {
            $cleanUpdates['updated_at'] = now();
        }

        if ($cleanUpdates) {
            DB::table('pickups')
                ->where('id', $pickupId)
                ->update($cleanUpdates);
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'phone' => $user->phone,
            'address' => $user->address,
            'staff_code' => $user->staff_code,
            'staff_position' => $user->staff_position,
            'vehicle_plate' => $user->vehicle_plate,
            'vehicle_number' => $user->vehicle_number,
            'vehicle_type' => $user->vehicle_type,
            'license_number' => $user->license_number,
        ];
    }

    private function valueFromArray(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return null;
    }

    private function firstColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function columns(string $table): array
    {
        if (array_key_exists($table, $this->columnCache)) {
            return $this->columnCache[$table];
        }

        if (!Schema::hasTable($table)) {
            return $this->columnCache[$table] = [];
        }

        return $this->columnCache[$table] = Schema::getColumnListing($table);
    }

    private function toArray(mixed $value): array
    {
        return json_decode(json_encode($value), true) ?: [];
    }
}
