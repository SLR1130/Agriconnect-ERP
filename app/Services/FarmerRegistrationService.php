<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Farmer;
use App\Models\FarmerRegistrationHistory;
use App\Models\Role;
use App\Models\User;
use App\Notifications\FarmerRegistrationStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FarmerRegistrationService
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $farmerRole = Role::query()->where('slug', 'farmer')->first();

            if (! $farmerRole) {
                throw new RuntimeException('The Farmer role is not configured.');
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'activated_at' => now(),
            ]);
            $user->roles()->attach($farmerRole);

            $farmer = Farmer::create([
                'user_id' => $user->id,
                'farmer_code' => 'FMR-'.now()->format('Y').'-'.Str::padLeft((string) $user->id, 6, '0'),
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'barangay' => $data['barangay'] ?? null,
                'registration_status' => 'pending',
            ]);

            FarmerRegistrationHistory::create([
                'farmer_id' => $farmer->id,
                'to_status' => 'pending',
                'reason' => 'Farmer portal registration submitted.',
            ]);

            ActivityLog::log('created', 'Farmer registration submitted', $farmer->farmer_code, $user->id, 'ffrms');

            return $user;
        });
    }

    public function transition(Farmer $farmer, string $status, ?string $reason, User $processor): Farmer
    {
        if (! in_array($status, ['verified', 'returned', 'rejected'], true)) {
            throw new RuntimeException('Unsupported farmer registration status.');
        }

        return DB::transaction(function () use ($farmer, $status, $reason, $processor) {
            $farmer = Farmer::query()->lockForUpdate()->findOrFail($farmer->id);
            $fromStatus = $farmer->registration_status;

            if ($fromStatus === $status) {
                return $farmer;
            }

            $farmer->update([
                'registration_status' => $status,
                'review_notes' => $reason,
                'verified_at' => $status === 'verified' ? now() : null,
                'verified_by' => $status === 'verified' ? $processor->id : null,
            ]);

            FarmerRegistrationHistory::create([
                'farmer_id' => $farmer->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'reason' => $reason,
                'processed_by' => $processor->id,
            ]);

            $farmer->user->notify(new FarmerRegistrationStatusChanged($farmer, $status, $reason));
            ActivityLog::log($status, 'Farmer registration '.$status, $farmer->farmer_code, $processor->id, 'ffrms');

            return $farmer->refresh();
        });
    }
}
