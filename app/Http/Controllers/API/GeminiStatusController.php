<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeminiStatusController extends BaseController
{
    public function status(Request $request): JsonResponse
    {
        $apiKey = env('GEMINI_API_KEY');
        $model = env('GEMINI_MODEL', env('GEMINI_AI_MODEL', 'gemini-2.5-flash-lite'));

        if (!$apiKey) {
            return $this->sendResponse([
                'gemini_available' => false,
                'status' => 'missing_api_key',
                'message' => 'GEMINI_API_KEY is missing in .env.',
                'model' => $model,
            ], 'Gemini API key is missing.');
        }

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => 'Return only this JSON: {"ok":true,"message":"gemini_working"}',
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

            if ($response->successful()) {
                return $this->sendResponse([
                    'gemini_available' => true,
                    'status' => 'working',
                    'model' => $model,
                    'http_status' => $response->status(),
                    'response_preview' => $response->json('candidates.0.content.parts.0.text'),
                ], 'Gemini is working.');
            }

            $body = $response->json();
            $errorStatus = data_get($body, 'error.status');
            $errorMessage = data_get($body, 'error.message', $response->body());

            $status = 'failed';

            if (
                $response->status() === 429
                || $errorStatus === 'RESOURCE_EXHAUSTED'
                || str_contains((string) $errorMessage, 'quota')
                || str_contains((string) $errorMessage, 'Quota')
            ) {
                $status = 'quota_exhausted';
            }

            return $this->sendResponse([
                'gemini_available' => false,
                'status' => $status,
                'model' => $model,
                'http_status' => $response->status(),
                'error_status' => $errorStatus,
                'error_message' => $errorMessage,
            ], $status === 'quota_exhausted'
                ? 'Gemini quota is exhausted.'
                : 'Gemini request failed.'
            );
        } catch (\Throwable $exception) {
            return $this->sendResponse([
                'gemini_available' => false,
                'status' => 'exception',
                'model' => $model,
                'error' => $exception->getMessage(),
            ], 'Gemini status check failed.');
        }
    }
}
