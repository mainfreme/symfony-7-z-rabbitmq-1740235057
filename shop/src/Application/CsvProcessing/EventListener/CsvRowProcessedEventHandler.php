<?php

namespace App\Application\CsvProcessing\EventListener;

use App\Domain\CsvProcessing\Enum\CsvFileUploadStatusEnum;
use App\Domain\CsvProcessing\Event\CsvRowProcessedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

class CsvRowProcessedEventHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private CacheInterface           $cache,
        private readonly RequestStack    $requestStack,
    ) {
    }

    #[AsEventListener(event: CsvRowProcessedEvent::class)]
    public function onCsvRowProcessed(CsvRowProcessedEvent $event): void
    {
        $progressData = [
            'progress' => (int)(($event->getRow() / $event->getTotalRow()) * 100),
            'status' => CsvFileUploadStatusEnum::PENDING,
        ];
        $this->cache->get('csv_status_' . $event->getUuid(), function ($item) use ($progressData) {
            $item->expiresAfter(3600);
            return $progressData;
        });

        $message = "Obsługa wiersza CSV z: {$event->getUuid()}, Linia: {$event->getRow()}";
        $this->logger->info($message);

        $data = $event->getData();

        // Tutaj możemy zwalidować dane i zapisywać dane do bazy
        $session = $this->requestStack->getSession();
        $session->set("file_progress_{$event->getUuid()}", $progressData['progress']);


        $this->cache->get('csv_' . $event->getUuid(), function ($item) use ($data) {
            $item->expiresAfter(3600);
            return ['csv' => $data];
        });

        $this->logger->info("Przetworzono linię {$event->getRow()}");
    }
}
