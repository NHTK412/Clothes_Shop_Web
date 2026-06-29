<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('admin.name'));
        $email = trim((string) config('admin.email'));
        $password = (string) config('admin.password');
        $phone = trim((string) config('admin.phone'));

        if ($email === '' || $password === '') {
            $this->command?->warn(
                'Bỏ qua tạo admin: cần cấu hình ADMIN_EMAIL và ADMIN_PASSWORD trong .env.'
            );

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Tài khoản {$email} đã tồn tại, bỏ qua tạo admin.");

            return;
        }

        $admin = new User;
        $admin->name = $name !== '' ? $name : 'Administrator';
        $admin->email = $email;
        $admin->phone = $phone !== '' ? $phone : null;
        $admin->password = Hash::make($password);
        $admin->role = 'ROLE_ADMIN';
        $admin->status = 'ACTIVE';
        $admin->save();

        $this->command?->info("Đã tạo tài khoản admin {$email}.");
    }
}
