<?php

namespace Tests\Feature\Mail;

use App\Notifications\OrganisationInvitationNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailThemeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
    }

    private function lastHtml(): string
    {
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertNotEmpty($messages, 'Aucun e-mail capturé.');

        // Corps HTML décodé (le MIME brut est en quoted-printable et coupe les mots).
        return (string) $messages[count($messages) - 1]->getOriginalMessage()->getHtmlBody();
    }

    public function test_invitation_email_renders_with_vulkain_branding(): void
    {
        NotificationFacade::route('mail', 'invite@exemple.fr')
            ->notify(new OrganisationInvitationNotification('SAMU 62', 'http://app.vulkain.test/invite/abc', 10080));

        $html = $this->lastHtml();

        // Marque, contenu métier et bouton d'action présents.
        $this->assertStringContainsString('VULKAIN', $html);
        $this->assertStringContainsString('SAMU 62', $html);
        $this->assertStringContainsString('Activer mon compte', $html);
        // Le thème braise est bien inliné sur le bouton.
        $this->assertStringContainsString('#C6362B', $html);
    }

    public function test_password_reset_email_renders_with_theme(): void
    {
        NotificationFacade::route('mail', 'reset@exemple.fr')
            ->notify(new ResetPasswordNotification('token-xyz'));

        $html = $this->lastHtml();

        // NB : le corps est encodé en quoted-printable ; on cible des sous-chaînes ASCII.
        $this->assertStringContainsString('VULKAIN', $html);
        $this->assertStringContainsString('mot de passe', $html);
        $this->assertStringContainsString('#C6362B', $html);
    }
}
