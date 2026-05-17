<?php

declare(strict_types=1);

namespace Slave\Actions\User;

use App\Models\User;
use Core\Base\Repositories\User\UserInterface;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

class RecordUserLoginAction
{
    public function __construct(
        private readonly UserInterface $userRepository,
    ) {}

    /**
     * สร้างบัญชีผู้ใช้ใหม่ในระบบ
     *
     * @param  array<string, mixed>  $userData
     *
     * @throws InvalidArgumentException หากข้อมูลที่จำเป็นขาดหายไป
     * @throws RuntimeException หากอีเมลซ้ำในระบบ
     */
    public function execute(array $userData): User
    {
        $email = (string) ($userData['email'] ?? '');
        $name = (string) ($userData['name'] ?? '');
        $password = (string) ($userData['password'] ?? '');

        if ($email === '' || $name === '' || $password === '') {
            throw new InvalidArgumentException('email, name และ password จำเป็นต้องระบุ');
        }

        if ($this->userRepository->emailExists($email)) {
            throw new RuntimeException("อีเมล [{$email}] มีผู้ใช้งานในระบบแล้ว");
        }

        return $this->userRepository->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }
}
