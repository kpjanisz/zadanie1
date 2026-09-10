<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\Contract\NotificationInterface;
use App\Notification\Entity\OperationLog;
use App\Notification\Enum\OperationAction;
use App\Notification\Service\OperationLogRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationLogRecorder::class)]
final class OperationLogRecorderTest extends TestCase
{
    public function testBuildsAnAuditRowDescribingTheNotification(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $log = new OperationLogRecorder($entityManager)->record($this->notification());

        self::assertInstanceOf(OperationLog::class, $log);
        self::assertSame(OperationLogRecorder::CHANNEL, $log->getChannel());
        self::assertSame(OperationAction::Created, $log->getAction());
        self::assertSame('Product', $log->getSubjectType());
        self::assertSame(42, $log->getSubjectId());
        self::assertSame(['name' => 'Pralka', 'price' => '1299.50'], $log->getPayload());
    }

    /**
     * The contract that makes the audit atomic with the write it describes: the
     * recorder persists but never commits, so the row joins the caller's
     * transaction instead of opening a second one that could fail on its own.
     */
    public function testNeverFlushesOnItsOwn(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::never())->method('commit');

        new OperationLogRecorder($entityManager)->record($this->notification());
    }

    private function notification(): NotificationInterface
    {
        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('getType')->willReturn('product.changed');
        $notification->method('getAction')->willReturn(OperationAction::Created);
        $notification->method('getSubjectType')->willReturn('Product');
        $notification->method('getSubjectId')->willReturn(42);
        $notification->method('getContext')->willReturn(['name' => 'Pralka', 'price' => '1299.50']);

        return $notification;
    }
}
