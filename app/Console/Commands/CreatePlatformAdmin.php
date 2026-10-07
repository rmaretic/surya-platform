<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin {email} {--name=}';

    protected $description = 'Create a platform administrator using an interactive hidden password prompt';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Use an interactive terminal; passwords are never accepted as command arguments.');

            return self::FAILURE;
        }
        $data = [
            'email' => mb_strtolower(trim((string) $this->argument('email'))),
            'name' => $this->option('name') ?: $this->ask('Name'),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:platform_admins,normalized_email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        PlatformAdmin::query()->create($validator->safe()->only(['name', 'email', 'password']));
        $this->info('Platform administrator created. Sign in and request email verification.');

        return self::SUCCESS;
    }
}
