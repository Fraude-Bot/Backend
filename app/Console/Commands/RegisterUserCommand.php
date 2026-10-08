<?php

namespace App\Console\Commands;

use App\Application\Auth\Commands\RegisterCommand;
use App\Application\Auth\Usecases\AuthUsecaseInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'user:register')]
class RegisterUserCommand extends Command
{
    protected $signature = 'user:register {email}';

    protected $description = 'Register a reporter and print an API token';

    public function handle(AuthUsecaseInterface $auth): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:rfc', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        try {
            $result = $auth->register(new RegisterCommand(email: $email));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->line($result['token']);

        return self::SUCCESS;
    }
}
