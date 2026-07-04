<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Services\LocalEwasteVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LocalWasteVisionController extends BaseController
{
    public function localAiPreview(
        Request $request,
        LocalEwasteVisionService $localEwasteVisionService
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthenticated.', ['error' => 'Login is required.'], 401);
        }

        $allowedListingRoles = [
            'customer',
            'company',
            'institution',
            'organization',
            'client',
            'admin',
        ];

        if (!in_array($user->role, $allowedListingRoles, true)) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only customer, company, institution, organization, client or admin can analyze waste listing.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'pickup_address' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            $result = $localEwasteVisionService->analyze(
                $request->file('image'),
                $request->all()
            );

            return $this->sendResponse($result, 'Local e-waste vision preview generated successfully.');
        } catch (\Throwable $exception) {
            Log::error('Local e-waste vision preview failed', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->sendError(
                'Local e-waste vision preview failed.',
                [
                    'error' => $exception->getMessage(),
                    'fix' => 'Check python3, python3-opencv, python3-numpy, local detector script, and ewaste_items table.',
                ],
                422
            );
        }
    }
}
