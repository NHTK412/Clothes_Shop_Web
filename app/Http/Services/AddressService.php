<?php

namespace App\Http\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function getAddresses(User $user)
    {
        return Address::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();
    }

    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $shouldBeDefault = (bool) ($data['is_default'] ?? false)
                || ! Address::where('user_id', $user->id)->exists();

            if ($shouldBeDefault) {
                $this->clearDefault($user);
            }

            $address = new Address($data);
            $address->user_id = $user->id;
            $address->is_default = $shouldBeDefault;
            $address->save();

            return $address;
        });
    }

    public function update(User $user, int $addressId, array $data): Address
    {
        return DB::transaction(function () use ($user, $addressId, $data) {
            $address = $this->findForUser($user, $addressId);
            $shouldBeDefault = (bool) ($data['is_default'] ?? false); 

            if ($shouldBeDefault) {
                $this->clearDefault($user);
            } else {
                unset($data['is_default']);
            }

            $address->fill($data);

            if ($shouldBeDefault) {
                $address->is_default = true;
            }

            $address->save();

            return $address;
        });
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId) {
            $address = $this->findForUser($user, $addressId);
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $nextAddress = Address::where('user_id', $user->id)->latest()->first();

                if ($nextAddress) {
                    $nextAddress->is_default = true;
                    $nextAddress->save();
                }
            }
        });
    }

    public function setDefault(User $user, int $addressId): Address
    {
        return DB::transaction(function () use ($user, $addressId) {
            $address = $this->findForUser($user, $addressId);
            $this->clearDefault($user);

            $address->is_default = true;
            $address->save();

            return $address;
        });
    }

    private function findForUser(User $user, int $addressId): Address
    {
        return Address::where('user_id', $user->id)->findOrFail($addressId);
    }

    private function clearDefault(User $user): void
    {
        Address::where('user_id', $user->id)->update(['is_default' => false]);
    }
}
