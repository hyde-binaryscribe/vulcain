<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTestCommandTest extends TestCase
{
    public function test_it_sends_a_test_email_to_a_valid_address(): void
    {
        // Transport en mémoire : on vérifie qu'un message est réellement produit.
        config(['mail.default' => 'array']);

        $this->artisan('vulcain:mail-test', ['email' => 'dest@exemple.fr'])
            ->assertSuccessful();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('dest@exemple.fr', $messages[0]->toString());
    }

    public function test_it_rejects_an_invalid_address(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('vulcain:mail-test', ['email' => 'pas-un-email'])
            ->assertFailed();

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
