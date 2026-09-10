<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\Contract\NotificationChannelInterface;
use App\Notification\Contract\NotificationInterface;
use App\Notification\Enum\OperationAction;
use App\Notification\NotificationDispatcher;
use App\Tests\Unit\Notification\Double\RecordingChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(NotificationDispatcher::class)]
final class NotificationDispatcherTest extends TestCase
{
    public function testDeliversToEveryChannelThatSupportsTheNotification(): void
    {
        $notification = $this->notification();

        $first = $this->channel('first', supports: true);
        $first->expects(self::once())->method('send')->with($notification);

        $second = $this->channel('second', supports: true);
        $second->expects(self::once())->method('send')->with($notification);

        $report = $this->dispatcher([$first, $second])->dispatch($notification);

        self::assertSame(['first', 'second'], $report->delivered);
        self::assertSame([], $report->failed);
        self::assertFalse($report->hasFailures());
    }

    public function testSkipsChannelsThatDoNotSupportTheNotification(): void
    {
        $notification = $this->notification();

        $interested = $this->channel('interested', supports: true);
        $interested->expects(self::once())->method('send');

        $uninterested = $this->channel('uninterested', supports: false);
        $uninterested->expects(self::never())->method('send');

        $report = $this->dispatcher([$interested, $uninterested])->dispatch($notification);

        self::assertSame(['interested'], $report->delivered);
    }

    /**
     * The behaviour that matters most: notifying is a side effect of a write that
     * already succeeded. A dead SMTP server must not turn a successful
     * POST /api/products into a 500, nor stop the other channels from delivering.
     */
    public function testOneFailingChannelNeitherPropagatesNorBlocksTheOthers(): void
    {
        $notification = $this->notification();

        $before = $this->channel('before', supports: true);
        $before->expects(self::once())->method('send');

        $broken = $this->stubChannel('broken', supports: true);
        $broken->method('send')->willThrowException(new \RuntimeException('SMTP is down'));

        $after = $this->channel('after', supports: true);
        $after->expects(self::once())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::isString(),
                self::callback(static fn (array $context): bool => 'broken' === $context['channel']
                    && 'product.changed' === $context['notification']
                    && $context['exception'] instanceof \RuntimeException),
            );

        $report = $this->dispatcher([$before, $broken, $after], $logger)->dispatch($notification);

        self::assertSame(['before', 'after'], $report->delivered);
        self::assertSame(['broken' => 'SMTP is down'], $report->failed);
        self::assertTrue($report->hasFailures());
        self::assertSame(2, $report->deliveredCount());
    }

    public function testChannelsAreVisitedInTheOrderTheyAreRegistered(): void
    {
        $notification = $this->notification();

        $report = $this->dispatcher([
            $this->stubChannel('third', supports: true),
            $this->stubChannel('first', supports: true),
            $this->stubChannel('second', supports: true),
        ])->dispatch($notification);

        self::assertSame(['third', 'first', 'second'], $report->delivered);
    }

    /**
     * Extensibility contract: the dispatcher holds no list of its own, so a
     * channel it has never heard of is delivered to purely by being present in
     * the tagged iterator. Adding Slack or SMS is exactly this and nothing more.
     */
    public function testAChannelTheDispatcherHasNeverSeenIsUsedWithoutAnyChange(): void
    {
        $notification = $this->notification();

        $slack = new RecordingChannel('slack');

        $report = $this->dispatcher([$slack])->dispatch($notification);

        self::assertSame(['slack'], $report->delivered);
        self::assertSame($notification, $slack->last());
    }

    public function testDispatchingWithNoChannelsIsHarmless(): void
    {
        $report = $this->dispatcher([])->dispatch($this->notification());

        self::assertSame([], $report->delivered);
        self::assertFalse($report->hasFailures());
    }

    /**
     * @param list<NotificationChannelInterface> $channels
     */
    private function dispatcher(array $channels, ?LoggerInterface $logger = null): NotificationDispatcher
    {
        return new NotificationDispatcher($channels, $logger ?? $this->createStub(LoggerInterface::class));
    }

    /**
     * A channel whose calls the test asserts on.
     */
    private function channel(string $name, bool $supports): NotificationChannelInterface&MockObject
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('getName')->willReturn($name);
        $channel->method('supports')->willReturn($supports);

        return $channel;
    }

    /**
     * A channel that is only scenery — no expectations, so a stub rather than a mock.
     */
    private function stubChannel(string $name, bool $supports): NotificationChannelInterface&Stub
    {
        $channel = $this->createStub(NotificationChannelInterface::class);
        $channel->method('getName')->willReturn($name);
        $channel->method('supports')->willReturn($supports);

        return $channel;
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
