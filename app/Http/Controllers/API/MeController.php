<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->sendResponse(
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'institution_name' => $user->institution_name,
                    'institution_type' => $user->institution_type,
                    'district' => $user->district,
                    'sector' => $user->sector,
                    'cell' => $user->cell,
                    'village' => $user->village,
                    'wallet_balance' => $user->wallet_balance,
                    'points_balance' => $user->points_balance,
                    'created_at' => $user->created_at,
                ],
            ],
            'Current user retrieved successfully.'
        );
    }
}
