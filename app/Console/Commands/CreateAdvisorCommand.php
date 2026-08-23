<?php

namespace App\Console\Commands;

use App\Actions\Auth\CreateAdvisorAction;
use App\DTOs\Auth\CreateAdvisorData;
use Illuminate\Console\Command;
use Throwable;

final class CreateAdvisorCommand extends Command
{
    protected $signature = 'app:create-advisor
        {username : Alphanumeric advisor username}
        {name : Advisor display name}
        {--email= : Optional advisor email}
        {--phone= : Optional mobile phone allowed by PHONE_ALLOWED_COUNTRY_CODES}';

    protected $description = 'Create an Advisor account with a CSPRNG temporary password.';

    public function handle(CreateAdvisorAction $createAdvisor): int
    {
        try {
            $result = $createAdvisor->execute(new CreateAdvisorData(
                username: (string) $this->argument('username'),
                name: (string) $this->argument('name'),
                email: $this->option('email') === null ? null : (string) $this->option('email'),
                phone: $this->option('phone') === null ? null : (string) $this->option('phone'),
            ));
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('Advisor created.');
        $this->line('Username: '.$result['user']->username);
        $this->line('Temporary password: '.$result['temporary_password']);
        $this->warn('Store this password securely outside the repository. It is shown only for CLI handoff.');

        return self::SUCCESS;
    }
}
