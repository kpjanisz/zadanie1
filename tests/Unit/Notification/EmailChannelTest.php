<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\Channel\EmailChannel;
use App\Notification\Contract\NotificationInterface;
use App\Notification\Enum\OperationAction;
use App\Shared\Service\MailerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Uses the real MailerService with a mocked transport: that keeps the assertions
 * on the message that would actually be sent, rather than on a mock of our own
 * code. Twig is never rendered here — the transport does that.
 */
#[CoversClass(EmailChannel::class)]
#[CoversClass(MailerService::class)]
final class EmailChannelTest extends TestCase
{
    public function testBuildsAMessageForTheConfiguredRecipient(): void
    {
        $sent = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (RawMessage $message) use (&$sent): bool {
                $sent = $message;

                return true;
            }));

        $channel = new EmailChannel($this->mailerService($mailer), 'ops@example.test');
        $channel->send($this->notification());

        self::assertInstanceOf(TemplatedEmail::class, $sent);
        self::assertSame(['ops@example.test'], array_map(
            static fn ($address): string => $address->getAddress(),
            $sent->getTo(),
        ));

        // The subject arrives as a translation key, never a hard-coded string.
        self::assertSame('translated:notification.product.changed.subject', $sent->getSubject());
        self::assertSame('mail/NotificationMail/content.html.twig', $sent->getHtmlTemplate());

        $context = $sent->getContext();
        self::assertSame('product.changed', $context['type']);
        self::assertSame(OperationAction::Created, $context['action']);
        self::assertSame('Product', $context['subjectType']);
        self::assertSame(42, $context['subjectId']);
        self::assertSame(['name' => 'Pralka'], $context['context']);
    }

    public function testIsDisabledWhenNoRecipientIsConfigured(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $channel = new EmailChannel($this->mailerService($mailer), '   ');

        // An unconfigured recipient switches the channel off instead of throwing
        // on every single write.
        self::assertFalse($channel->supports($this->notification()));
    }

    public function testIsEnabledWhenARecipientIsConfigured(): void
    {
        $channel = new EmailChannel(
            $this->mailerService($this->createStub(MailerInterface::class)),
            'ops@example.test',
        );

        self::assertTrue($channel->supports($this->notification()));
    }

    private function mailerService(MailerInterface $mailer): MailerService
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => 'translated:'.$id,
        );

        return new MailerService($mailer, $translator);
    }

    private function notification(): NotificationInterface
    {
        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('getType')->willReturn('product.changed');
        $notification->method('getAction')->willReturn(OperationAction::Created);
        $notification->method('getSubjectType')->willReturn('Product');
        $notification->method('getSubjectId')->willReturn(42);
        $notification->method('getSubjectKey')->willReturn('notification.product.changed.subject');
        $notification->method('getContext')->willReturn(['name' => 'Pralka']);

        return $notification;
    }
}
