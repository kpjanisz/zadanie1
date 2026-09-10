<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Notification\Channel\EmailChannel;
use App\Notification\Channel\PsrLoggerChannel;
use App\Notification\Enum\OperationAction;
use App\Notification\NotificationDispatcher;
use App\Notification\Repository\OperationLogRepository;
use App\Notification\Service\OperationLogRecorder;
use App\Product\Dto\ProductChangedNotification;
use App\Product\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The extensibility claim, verified against the real container.
 *
 * None of these channels appears in services.yaml: they are wired solely by
 * implementing NotificationChannelInterface, which carries #[AutoconfigureTag].
 * If that mechanism ever breaks, this test fails — and adding Slack or SMS is
 * the same one-class change.
 */
#[CoversNothing]
final class ChannelRegistrationTest extends KernelTestCase
{
    public function testEveryChannelIsWiredByTheTagAloneAndReceivesTheNotification(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $dispatcher = $container->get(NotificationDispatcher::class);
        self::assertInstanceOf(NotificationDispatcher::class, $dispatcher);

        $report = $dispatcher->dispatch(
            ProductChangedNotification::fromProduct($this->persistedProduct(), OperationAction::Created),
        );

        self::assertSame([], $report->failed, 'No channel may fail in a healthy configuration.');
        // Order, not just membership: PsrLoggerChannel declares a higher tag
        // priority so the cheap local record exists before the channel that
        // makes a network call is attempted.
        self::assertSame(
            [PsrLoggerChannel::NAME, EmailChannel::NAME],
            $report->delivered,
        );
    }

    public function testTheAuditRecorderLeavesAQueryableTrail(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $recorder = $container->get(OperationLogRecorder::class);
        self::assertInstanceOf(OperationLogRecorder::class, $recorder);

        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $recorder->record(ProductChangedNotification::fromProduct($this->persistedProduct(), OperationAction::Created));
        $entityManager->flush();

        $logs = $container->get(OperationLogRepository::class);
        self::assertInstanceOf(OperationLogRepository::class, $logs);

        $entries = $logs->findForSubject('Product', 12345);

        self::assertCount(1, $entries);
        self::assertSame(OperationLogRecorder::CHANNEL, $entries[0]->getChannel());
        self::assertSame(OperationAction::Created, $entries[0]->getAction());
        self::assertSame('Pralka', $entries[0]->getPayload()['name']);
    }

    private function persistedProduct(): Product
    {
        $product = new Product('Pralka', '1299.50');

        // Stands in for Doctrine: the notification refuses an unpersisted product.
        new \ReflectionProperty($product, 'id')->setValue($product, 12345);

        return $product;
    }
}
