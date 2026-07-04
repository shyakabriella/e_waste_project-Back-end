<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Notifications\CompanyCredentialsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class RegisterController extends BaseController
{
    /**
     * Get users list api
     *
     * Important:
     * Only Admin can view users.
     */
    public function users(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin can view users.'],
                403
            );
        }

        $query = User::query()
            ->select([
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
                'staff_code',
                'staff_position',
                'wallet_balance',
                'points_balance',
                'created_at',
                'updated_at',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('institution_name', 'like', "%{$search}%")
                    ->orWhere('staff_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(5, min($perPage, 100));

        $users = $query
            ->latest()
            ->paginate($perPage);

        $summary = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'institutions' => User::where('role', 'institution')->count(),
            'staff' => User::where('role', 'enviroserve_staff')->count(),
            'drivers' => User::where('role', 'driver')->count(),
            'finance_officers' => User::where('role', 'finance_officer')->count(),
        ];

        return $this->sendResponse(
            [
                'users' => $users,
                'summary' => $summary,
            ],
            'Users retrieved successfully.'
        );
    }

    /**
     * Public company registration api
     *
     * Company creates account using only:
     * - name
     * - email
     * - phone
     *
     * System generates password and sends credentials to email.
     */
    public function companyRegister(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'phone' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'phone'),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => trim((string) $request->name),
            'email' => strtolower(trim((string) $request->email)),
            'phone' => trim((string) $request->phone),

            'password' => Hash::make($temporaryPassword),

            'role' => 'institution',
            'status' => 'active',

            'institution_name' => trim((string) $request->name),
            'institution_type' => null,

            'wallet_balance' => 0,
            'points_balance' => 0,
        ]);

        $emailSent = $this->sendCompanyCredentialsEmail($user, $temporaryPassword);

        $success = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'phone' => $user->phone,
                'institution_name' => $user->institution_name,
                'created_at' => $user->created_at,
            ],
            'email_sent' => $emailSent,
            'message_for_company' => $emailSent
                ? 'Account created successfully. Login credentials have been sent to your email.'
                : 'Account created successfully, but credentials email could not be sent. Please contact admin.',
        ];

        /*
         * Local development helper:
         * Shows temporary password only when APP_DEBUG=true.
         * In production, this field is hidden.
         */
        if (config('app.debug')) {
            $success['dev_temporary_password'] = $temporaryPassword;
        }

        return $this->sendResponse(
            $success,
            $emailSent
                ? 'Company account created successfully. Credentials sent to email.'
                : 'Company account created, but email sending failed.'
        );
    }

    /**
     * Register / Create user api
     *
     * Important:
     * In this system, users are created by Admin.
     * Password is generated automatically and emailed to the user.
     */
    public function register(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin can create users.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'slug'),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(['active', 'inactive', 'suspended']),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Institution / Client Fields
            |--------------------------------------------------------------------------
            */

            'institution_name' => [
                'required_if:role,institution',
                'nullable',
                'string',
                'max:255',
            ],

            'institution_type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'district' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sector' => [
                'nullable',
                'string',
                'max:100',
            ],

            'cell' => [
                'nullable',
                'string',
                'max:100',
            ],

            'village' => [
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Enviroserve Staff Fields
            |--------------------------------------------------------------------------
            */

            'staff_code' => [
                'required_if:role,enviroserve_staff',
                'nullable',
                'string',
                'max:100',
                Rule::unique('users', 'staff_code'),
            ],

            'staff_position' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => trim((string) $request->name),
            'email' => strtolower(trim((string) $request->email)),
            'password' => Hash::make($temporaryPassword),

            'role' => $request->role,
            'status' => $request->status ?? 'active',

            'phone' => $request->phone,
            'address' => $request->address,

            'institution_name' => $request->institution_name,
            'institution_type' => $request->institution_type,

            'district' => $request->district,
            'sector' => $request->sector,
            'cell' => $request->cell,
            'village' => $request->village,

            'staff_code' => $request->staff_code,
            'staff_position' => $request->staff_position,

            'wallet_balance' => 0,
            'points_balance' => 0,
        ]);

        $emailSent = $this->sendCompanyCredentialsEmail($user, $temporaryPassword);

        $success = [
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
                'staff_code' => $user->staff_code,
                'staff_position' => $user->staff_position,
                'wallet_balance' => $user->wallet_balance,
                'points_balance' => $user->points_balance,
                'created_at' => $user->created_at,
            ],
            'email_sent' => $emailSent,
        ];

        if (config('app.debug')) {
            $success['dev_temporary_password'] = $temporaryPassword;
        }

        return $this->sendResponse(
            $success,
            $emailSent
                ? 'User created successfully. Credentials sent to email.'
                : 'User created successfully, but credentials email could not be sent.'
        );
    }


    /**
     * Login api
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        if (!Auth::attempt([
            'email' => strtolower(trim((string) $request->email)),
            'password' => $request->password,
        ])) {
            return $this->sendError(
                'Unauthorised.',
                ['error' => 'Invalid email or password.'],
                401
            );
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            return $this->sendError(
                'Account blocked.',
                ['error' => 'Your account is not active. Please contact admin.'],
                403
            );
        }

        $success = [
            'token' => $user->createToken('ewaste-api-token')->plainTextToken,
            'token_type' => 'Bearer',

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
                'staff_code' => $user->staff_code,
                'staff_position' => $user->staff_position,
                'wallet_balance' => $user->wallet_balance,
                'points_balance' => $user->points_balance,
            ],
        ];

        return $this->sendResponse($success, 'User login successfully.');
    }

    private function generateTemporaryPassword(): string
    {
        return 'Ewaste@' . random_int(100000, 999999);
    }

    private function sendCompanyCredentialsEmail(User $user, string $temporaryPassword): bool
    {
        try {
            $user->notify(new CompanyCredentialsNotification($temporaryPassword));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
