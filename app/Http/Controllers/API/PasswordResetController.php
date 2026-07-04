<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\ForgotPasswordOtpNotification;
use App\Notifications\NewPasswordGeneratedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PasswordResetController extends BaseController
{
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $email = strtolower(trim((string) $request->email));

        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->sendError(
                'Account not found.',
                ['email' => 'No account was found with this email address.'],
                404
            );
        }

        if ($user->status !== 'active') {
            return $this->sendError(
                'Account blocked.',
                ['email' => 'Your account is not active. Please contact admin.'],
                403
            );
        }

        $otp = (string) random_int(100000, 999999);

        PasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        PasswordResetOtp::create([
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            $user->notify(new ForgotPasswordOtpNotification($otp));
        } catch (Throwable $exception) {
            report($exception);

            return $this->sendError(
                'Email Error.',
                ['email' => 'OTP could not be sent. Please try again.'],
                500
            );
        }

        return $this->sendResponse(
            [
                'email' => $email,
                'expires_in_minutes' => 10,
            ],
            'OTP has been sent to your email.'
        );
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'otp' => [
                'required',
                'digits:6',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $email = strtolower(trim((string) $request->email));
        $otp = trim((string) $request->otp);

        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->sendError(
                'Account not found.',
                ['email' => 'No account was found with this email address.'],
                404
            );
        }

        $otpRecord = PasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$otpRecord) {
            return $this->sendError(
                'Invalid OTP.',
                ['otp' => 'No active OTP was found. Please request a new OTP.'],
                422
            );
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->update([
                'used_at' => now(),
            ]);

            return $this->sendError(
                'OTP expired.',
                ['otp' => 'This OTP has expired. Please request a new OTP.'],
                422
            );
        }

        if (!Hash::check($otp, $otpRecord->otp_hash)) {
            return $this->sendError(
                'Invalid OTP.',
                ['otp' => 'The OTP entered is incorrect.'],
                422
            );
        }

        $newPassword = $this->generateNewPassword();

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        $otpRecord->update([
            'used_at' => now(),
        ]);

        try {
            $user->notify(new NewPasswordGeneratedNotification($newPassword));
        } catch (Throwable $exception) {
            report($exception);

            return $this->sendError(
                'Password Reset Completed, Email Failed.',
                ['email' => 'Password was reset, but email could not be sent. Please contact admin.'],
                500
            );
        }

        return $this->sendResponse(
            [
                'email' => $email,
            ],
            'OTP verified successfully. A new password has been sent to your email.'
        );
    }

    private function generateNewPassword(): string
    {
        return 'Ewaste@' . random_int(100000, 999999);
    }
}
