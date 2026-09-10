<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Mail\AbstractMail;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns an AbstractMail into a message and hands it to the transport.
 *
 * The From header comes from framework.mailer.headers (env MAILER_FROM), so no
 * mail class has to know the sender.
 */
final readonly class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function send(AbstractMail $mail): void
    {
        $email = new TemplatedEmail()
            ->subject($this->translator->trans($mail->getSubject(), $mail->getSubjectParameters()))
            ->htmlTemplate(\sprintf('mail/%s/content.html.twig', $mail->getTemplateName()))
            ->context($mail->getTemplateData());

        foreach ($mail->getTo() as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);
    }
}
