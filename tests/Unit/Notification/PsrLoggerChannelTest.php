<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\Channel\PsrLoggerChannel;
use App\Notification\Contract\NotificationInterface;
use App\Notification\Enum\OperationAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PsrLoggerChannel::class)]
final class PsrLoggerChannelTest extends TestCase
{
    public function testMirrorsTheNotificationIntoTheApplicationLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('product.changed', [
                'action' => 'created',
                'subject_type' => 'Product',
                'subject_id' => 42,
                'context' => ['name' => 'Pralka'],
            ]);

        new PsrLoggerChannel($logger)->send($this->notification());
    }

    public function testLogsEveryNotificationType(): void
    {
        $channel = new PsrLoggerChannel($this->createStub(LoggerInterface::class));

        self::assertTrue($channel->supports($this->notification()));
        self::assertSame(PsrLoggerChannel::NAME, $channel->getName());
    }

    private function notification(): NotificationInterface
    {
        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('getType')->willReturn('product.changed');
        $notification->method('getAction')->willReturn(OperationAction::Created);
        $notification->method('getSubjectType')->willReturn('Product');
        $notification->method('getSubjectId')->willReturn(42);
        $notification->method('getContext')->willReturn(['name' => 'Pralka']);

        return $notification;
    }
}
