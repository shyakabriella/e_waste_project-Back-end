<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\WasteCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WasteCategoryController extends BaseController
{
    /**
     * Display a listing of waste categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WasteCategory::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('waste_nature') && $request->waste_nature !== 'all') {
            $query->where('waste_nature', $request->waste_nature);
        }

        if ($request->filled('is_hazardous') && $request->is_hazardous !== 'all') {
            $query->where('is_hazardous', filter_var($request->is_hazardous, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->get('per_page', 50);
        $perPage = max(5, min($perPage, 100));

        $categories = $query
            ->orderBy('name')
            ->paginate($perPage);

        return $this->sendResponse($categories, 'Waste categories retrieved successfully.');
    }

    /**
     * Store a newly created waste category.
     */
    public function store(Request $request): JsonResponse
    {
        $adminError = $this->ensureAdmin($request, 'Only admin can create waste categories.');

        if ($adminError) {
            return $adminError;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('waste_categories', 'slug'),
            ],

            'description' => ['nullable', 'string'],

            'waste_nature' => [
                'required',
                'string',
                Rule::in(['ibibora', 'ibitabora']),
            ],

            'is_e_waste' => ['nullable', 'boolean'],
            'is_hazardous' => ['nullable', 'boolean'],

            'average_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'min_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],

            'price_per_kg' => ['nullable', 'numeric', 'min:0'],
            'price_per_item' => ['nullable', 'numeric', 'min:0'],

            'currency' => ['nullable', 'string', 'max:10'],

            'status' => [
                'nullable',
                'string',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));

        if ($slug === '') {
            $slug = Str::slug($name, '_');
        }

        $category = WasteCategory::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $request->input('description'),

            'waste_nature' => $request->input('waste_nature', 'ibitabora'),
            'is_e_waste' => $request->boolean('is_e_waste', true),
            'is_hazardous' => $request->boolean('is_hazardous', false),

            'average_weight_kg' => $request->input('average_weight_kg'),
            'min_weight_kg' => $request->input('min_weight_kg'),
            'max_weight_kg' => $request->input('max_weight_kg'),

            'price_per_kg' => $request->input('price_per_kg'),
            'price_per_item' => $request->input('price_per_item'),
            'currency' => $request->input('currency', 'RWF'),

            'status' => $request->input('status', 'active'),
        ]);

        return $this->sendResponse($category, 'Waste category created successfully.');
    }

    /**
     * Display the specified waste category.
     */
    public function show(WasteCategory $wasteCategory): JsonResponse
    {
        return $this->sendResponse($wasteCategory, 'Waste category retrieved successfully.');
    }

    /**
     * Update the specified waste category.
     */
    public function update(Request $request, WasteCategory $wasteCategory): JsonResponse
    {
        $adminError = $this->ensureAdmin($request, 'Only admin can update waste categories.');

        if ($adminError) {
            return $adminError;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('waste_categories', 'slug')->ignore($wasteCategory->id),
            ],

            'description' => ['nullable', 'string'],

            'waste_nature' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['ibibora', 'ibitabora']),
            ],

            'is_e_waste' => ['nullable', 'boolean'],
            'is_hazardous' => ['nullable', 'boolean'],

            'average_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'min_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],

            'price_per_kg' => ['nullable', 'numeric', 'min:0'],
            'price_per_item' => ['nullable', 'numeric', 'min:0'],

            'currency' => ['nullable', 'string', 'max:10'],

            'status' => [
                'nullable',
                'string',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only([
            'name',
            'slug',
            'description',
            'waste_nature',
            'average_weight_kg',
            'min_weight_kg',
            'max_weight_kg',
            'price_per_kg',
            'price_per_item',
            'currency',
            'status',
        ]);

        if ($request->has('name') && empty($data['slug'])) {
            $data['slug'] = Str::slug((string) $request->input('name'), '_');
        }

        if ($request->has('is_e_waste')) {
            $data['is_e_waste'] = $request->boolean('is_e_waste');
        }

        if ($request->has('is_hazardous')) {
            $data['is_hazardous'] = $request->boolean('is_hazardous');
        }

        $wasteCategory->update($data);

        return $this->sendResponse($wasteCategory->fresh(), 'Waste category updated successfully.');
    }

    /**
     * Remove the specified waste category.
     */
    public function destroy(Request $request, WasteCategory $wasteCategory): JsonResponse
    {
        $adminError = $this->ensureAdmin($request, 'Only admin can delete waste categories.');

        if ($adminError) {
            return $adminError;
        }

        $wasteCategory->delete();

        return $this->sendResponse([], 'Waste category deleted successfully.');
    }

    /**
     * Sync standard e-waste category library.
     *
     * This is the main reference used by AI listing:
     * item/component -> normal kg -> price -> bill estimate.
     */
    public function syncStandardLibrary(Request $request): JsonResponse
    {
        $adminError = $this->ensureAdmin($request, 'Only admin can sync standard e-waste library.');

        if ($adminError) {
            return $adminError;
        }

        $items = $this->standardEwasteLibrary();

        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $category = WasteCategory::where('slug', $item['slug'])->first();

            if ($category) {
                $category->update($item);
                $updated++;
            } else {
                WasteCategory::create($item);
                $created++;
            }
        }

        return $this->sendResponse(
            [
                'total_library_items' => count($items),
                'created' => $created,
                'updated' => $updated,
            ],
            'Standard e-waste category library synced successfully.'
        );
    }

    /**
     * Gemini AI suggestion for one category.
     */
    public function aiSuggest(Request $request): JsonResponse
    {
        $adminError = $this->ensureAdmin($request, 'Only admin can use AI category suggestion.');

        if ($adminError) {
            return $adminError;
        }

        if (!$this->getGeminiUseAi()) {
            return $this->sendError(
                'AI is disabled.',
                ['error' => 'Please set GEMINI_USE_AI=true in .env.'],
                422
            );
        }

        $apiKey = $this->getGeminiApiKey();

        if (!$apiKey) {
            return $this->sendError(
                'Gemini API key missing.',
                ['error' => 'Please set GEMINI_API_KEY in .env.'],
                422
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if (!$request->filled('name') && !$request->hasFile('image')) {
            return $this->sendError(
                'Missing input.',
                ['error' => 'Type a category name or upload an image.'],
                422
            );
        }

        $name = trim((string) $request->input('name', ''));

        $prompt = <<<PROMPT
You are helping an e-waste recycling platform in Rwanda create a realistic waste category.

Return ONLY raw JSON.
No markdown.
No code fences.
No explanation.
No extra text before or after JSON.

Use this exact JSON structure:
{
  "name": "string",
  "slug": "string",
  "description": "string",
  "waste_nature": "ibitabora",
  "is_e_waste": true,
  "is_hazardous": false,
  "average_weight_kg": 0,
  "min_weight_kg": 0,
  "max_weight_kg": 0,
  "price_per_kg": 0,
  "price_per_item": 0,
  "currency": "RWF",
  "status": "active"
}

Rules:
- Use realistic e-waste values.
- Currency must be RWF.
- Status must be active.
- waste_nature must be either "ibitabora" or "ibibora".
- Use "ibitabora" for recyclable or processable e-waste.
- Mark batteries, CRT screens, damaged power supplies, and dangerous electronics as hazardous.
- Prices should be realistic and simple for Rwanda.
- If an image is provided, identify the item from the image.
- If a name is provided, use it as the main category idea.
- Description must be simple and professional.

Reference weights:
Laptop 2.5kg, LCD monitor 4kg, CRT monitor 15kg, desktop CPU 8kg, keyboard 0.8kg,
mouse 0.2kg, small printer 8kg, large printer/copier 20kg, UPS 7kg,
small battery 1.2kg, large battery 10kg, cable batch 5kg, mixed small parts batch 8kg,
mixed bulk e-waste pile 150kg or more.

Category name from user: {$name}
PROMPT;

        $parts = [
            [
                'text' => $prompt,
            ],
        ];

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image->getMimeType(),
                    'data' => base64_encode(file_get_contents($image->getRealPath())),
                ],
            ];
        }

        $model = $this->getGeminiModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(60)->post($url, [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->getGeminiTemperature(),
                'maxOutputTokens' => $this->getGeminiMaxOutputTokens(),
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (!$response->successful()) {
            return $this->sendError(
                'Gemini request failed.',
                [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ],
                500
            );
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        $suggestion = $this->decodeGeminiJson((string) $text);

        if (!is_array($suggestion)) {
            $suggestion = $this->fallbackSuggestion($name);
        }

        $data = $this->sanitizeSuggestion($suggestion, $name);

        return $this->sendResponse($data, 'AI category suggestion generated successfully.');
    }

    /**
     * Standard e-waste category library.
     *
     * These categories are used by AI listing to estimate kilograms and bill.
     */
    private function standardEwasteLibrary(): array
    {
        return [
            $this->ewasteItem('Laptop', 'laptop', 2.5, 1.5, 4, 800, 2500, false, 'Used or damaged laptops for e-waste recycling.'),
            $this->ewasteItem('Desktop CPU Tower', 'desktop_cpu_tower', 8, 5, 15, 700, 5000, false, 'Desktop computer CPU/tower units.'),
            $this->ewasteItem('LCD Monitor', 'lcd_monitor', 4, 2, 7, 700, 3000, false, 'Flat screen LCD/LED monitors.'),
            $this->ewasteItem('CRT Monitor', 'crt_monitor', 15, 10, 25, 900, 6000, true, 'Heavy CRT monitors requiring careful handling.'),
            $this->ewasteItem('Keyboard', 'keyboard', 0.8, 0.4, 1.5, 500, 500, false, 'Computer keyboards.'),
            $this->ewasteItem('Mouse', 'mouse', 0.2, 0.1, 0.4, 400, 200, false, 'Computer mouse devices.'),
            $this->ewasteItem('Small Printer', 'small_printer', 8, 4, 12, 700, 4000, false, 'Small office printers.'),
            $this->ewasteItem('Large Printer / Copier', 'large_printer_copier', 20, 12, 50, 700, 12000, false, 'Large printers, photocopiers, and office machines.'),
            $this->ewasteItem('UPS', 'ups', 7, 3, 20, 900, 5000, true, 'UPS backup power units, often containing batteries.'),
            $this->ewasteItem('Small Battery', 'small_battery', 1.2, 0.2, 3, 1200, 1000, true, 'Small electronic batteries requiring safe handling.'),
            $this->ewasteItem('Large Battery', 'large_battery', 10, 5, 30, 1200, 8000, true, 'Large batteries requiring safe handling.'),
            $this->ewasteItem('Mobile Phone', 'mobile_phone', 0.18, 0.1, 0.3, 1200, 500, true, 'Mobile phones and smartphones.'),
            $this->ewasteItem('Tablet', 'tablet', 0.6, 0.3, 1, 1000, 1000, true, 'Tablet devices and small touch screens.'),
            $this->ewasteItem('Server Unit', 'server_unit', 18, 10, 35, 800, 15000, false, 'Rack servers and heavy IT equipment.'),
            $this->ewasteItem('Network Router', 'network_router', 0.8, 0.3, 2, 600, 800, false, 'Routers and networking devices.'),
            $this->ewasteItem('Network Switch', 'network_switch', 2.5, 1, 6, 600, 1500, false, 'Network switches and rack networking devices.'),
            $this->ewasteItem('Cable Batch', 'cable_batch', 5, 1, 20, 700, 2500, false, 'Mixed electrical and data cable batches.'),
            $this->ewasteItem('Charger / Adapter', 'charger_adapter', 0.3, 0.1, 1, 600, 300, false, 'Power chargers and adapters.'),
            $this->ewasteItem('Power Supply Unit', 'power_supply_unit', 1.5, 0.8, 3, 800, 1000, true, 'Computer and electronic power supply units.'),
            $this->ewasteItem('Motherboard', 'motherboard', 0.7, 0.3, 1.5, 1500, 1200, true, 'Computer motherboards and circuit boards.'),
            $this->ewasteItem('Circuit Board Batch', 'circuit_board_batch', 5, 1, 20, 1500, 4000, true, 'Mixed PCB and circuit board batches.'),
            $this->ewasteItem('Hard Disk Drive', 'hard_disk_drive', 0.6, 0.3, 1, 1000, 700, false, 'Computer hard disk drives.'),
            $this->ewasteItem('SSD Drive', 'ssd_drive', 0.1, 0.05, 0.2, 1000, 300, false, 'Solid state drives.'),
            $this->ewasteItem('RAM Stick', 'ram_stick', 0.05, 0.02, 0.1, 1200, 200, false, 'Computer memory modules.'),
            $this->ewasteItem('Speaker', 'speaker', 2, 0.5, 8, 500, 1000, false, 'Audio speakers and sound equipment.'),
            $this->ewasteItem('Television LCD / LED', 'television_lcd_led', 10, 5, 25, 700, 6000, false, 'Flat screen televisions.'),
            $this->ewasteItem('Television CRT', 'television_crt', 25, 15, 50, 900, 10000, true, 'Heavy CRT televisions requiring safe handling.'),
            $this->ewasteItem('Microwave Oven', 'microwave_oven', 12, 8, 20, 500, 5000, false, 'Microwave ovens and small home appliances.'),
            $this->ewasteItem('Electric Kettle', 'electric_kettle', 1.2, 0.6, 2, 400, 500, false, 'Electric kettles and small appliances.'),
            $this->ewasteItem('Fan', 'fan', 3, 1.5, 8, 400, 1000, false, 'Electric fans and cooling devices.'),
            $this->ewasteItem('Mixed Small Electronic Parts Batch', 'mixed_small_electronic_parts_batch', 8, 2, 30, 700, 4000, true, 'Mixed small electronic components and parts.'),
            $this->ewasteItem('Mixed E-Waste Bulk Pile', 'mixed_e_waste_bulk_pile', 150, 50, 1000, 700, 0, true, 'Large mixed e-waste pile for bulk collection and staff verification.'),
        ];
    }

    private function ewasteItem(
        string $name,
        string $slug,
        float $averageWeightKg,
        float $minWeightKg,
        float $maxWeightKg,
        float $pricePerKg,
        float $pricePerItem,
        bool $isHazardous,
        string $description
    ): array {
        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'waste_nature' => 'ibitabora',
            'is_e_waste' => true,
            'is_hazardous' => $isHazardous,
            'average_weight_kg' => $averageWeightKg,
            'min_weight_kg' => $minWeightKg,
            'max_weight_kg' => $maxWeightKg,
            'price_per_kg' => $pricePerKg,
            'price_per_item' => $pricePerItem,
            'currency' => 'RWF',
            'status' => 'active',
        ];
    }

    private function fallbackSuggestion(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            $name = 'Electronic Waste';
        }

        $lowerName = strtolower($name);

        if (str_contains($lowerName, 'battery')) {
            return $this->ewasteItem(
                $name,
                Str::slug($name, '_'),
                1.2,
                0.2,
                10,
                1200,
                1000,
                true,
                'Used electronic batteries that require safe handling, sorting, and recycling.'
            );
        }

        if (str_contains($lowerName, 'laptop')) {
            return $this->ewasteItem(
                $name,
                Str::slug($name, '_'),
                2.5,
                1,
                5,
                800,
                2500,
                false,
                'Used or damaged laptops collected for electronic waste recycling and material recovery.'
            );
        }

        if (str_contains($lowerName, 'monitor') || str_contains($lowerName, 'screen')) {
            return $this->ewasteItem(
                $name,
                Str::slug($name, '_'),
                4,
                2,
                9,
                700,
                3000,
                str_contains($lowerName, 'crt'),
                'Old or damaged computer screens collected for proper electronic waste recycling.'
            );
        }

        return $this->ewasteItem(
            $name,
            Str::slug($name, '_'),
            2,
            0.5,
            8,
            700,
            1500,
            false,
            'Electronic waste category for collection, sorting, recycling, and safe material recovery.'
        );
    }

    private function sanitizeSuggestion(array $suggestion, string $fallbackName): array
    {
        $suggestedName = trim((string) ($suggestion['name'] ?? $fallbackName));

        if ($suggestedName === '') {
            $suggestedName = 'Electronic Waste';
        }

        $wasteNature = $suggestion['waste_nature'] ?? 'ibitabora';

        if (!in_array($wasteNature, ['ibitabora', 'ibibora'], true)) {
            $wasteNature = 'ibitabora';
        }

        $status = $suggestion['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        return [
            'name' => $suggestedName,
            'slug' => Str::slug((string) ($suggestion['slug'] ?? $suggestedName), '_'),
            'description' => (string) ($suggestion['description'] ?? ''),
            'waste_nature' => $wasteNature,
            'is_e_waste' => (bool) ($suggestion['is_e_waste'] ?? true),
            'is_hazardous' => (bool) ($suggestion['is_hazardous'] ?? false),
            'average_weight_kg' => $this->numberOrNull($suggestion['average_weight_kg'] ?? null),
            'min_weight_kg' => $this->numberOrNull($suggestion['min_weight_kg'] ?? null),
            'max_weight_kg' => $this->numberOrNull($suggestion['max_weight_kg'] ?? null),
            'price_per_kg' => $this->numberOrNull($suggestion['price_per_kg'] ?? null),
            'price_per_item' => $this->numberOrNull($suggestion['price_per_item'] ?? null),
            'currency' => 'RWF',
            'status' => $status,
        ];
    }

    private function ensureAdmin(Request $request, string $message): ?JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return $this->sendError(
                'Access denied.',
                ['error' => $message],
                403
            );
        }

        return null;
    }

    private function decodeGeminiJson(string $text): ?array
    {
        $text = trim($text);

        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonOnly = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($jsonOnly, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function getGeminiApiKey(): ?string
    {
        return config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
    }

    private function getGeminiModel(): string
    {
        return config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    private function getGeminiTemperature(): float
    {
        return (float) (config('services.gemini.temperature') ?: env('GEMINI_TEMPERATURE', 0.3));
    }

    private function getGeminiMaxOutputTokens(): int
    {
        return (int) (config('services.gemini.max_output_tokens') ?: env('GEMINI_MAX_OUTPUT_TOKENS', 800));
    }

    private function getGeminiUseAi(): bool
    {
        $value = config('services.gemini.use_ai');

        if ($value === null) {
            $value = env('GEMINI_USE_AI', false);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
