<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import;

use Doctrine\DBAL\Connection;
use EBlick\ContaoOpenImmoImport\Import\Data\NormalizationException;
use EBlick\ContaoOpenImmoImport\Import\Data\Normalizer;
use EBlick\ContaoOpenImmoImport\Import\Data\ObjectData;
use EBlick\ContaoOpenImmoImport\Import\Data\OpenImmoArchive;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Ujamii\OpenImmo\API\Anbieter;
use Ujamii\OpenImmo\API\Immobilie;

class Importer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Normalizer $normalizer,
        private readonly DatabaseSynchronizer $synchronizer,
        private readonly ResourceUtil $fileUtil,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Import a @see OpenImmoArchive by extracting files and synchronizing the database.
     *
     * @return array<string, array{created: int, updated: int, deleted: int}>
     */
    public function import(OpenImmoArchive $archive): array
    {
        $anbieterMap = array_map(
            'intval',
            $this->connection->fetchAllKeyValue(
                "SELECT onoffice_anbieter_nummer, id FROM cc_fiba_anbieter WHERE onoffice_konverter = '1'"
            )
        );

        $stats = [];

        foreach ($archive->getOpenImmoData()->getAnbieter() as $anbieter) {
            $anbieterNr = $anbieter->getAnbieternr();

            if (null === ($ccFibaAnbieterId = $anbieterMap[$anbieterNr] ?? null)) {
                continue;
            }

            $objects = $this->getObjects($anbieterNr, $anbieter);

            // Extract files from archive and make them available in the DBAFS
            $this->createAndSyncFiles($objects, $archive);

            // Synchronize the database
            $stats[$anbieterNr] = $this->synchronizer->mergeObjects($ccFibaAnbieterId, $objects);

            $this->logger->info(
                sprintf(
                    'OpenImmo: Import für Anbieter "%s" abgeschlossen (%d erstellt | %d aktualisiert | %d entfernt).',
                    $anbieterNr,
                    $stats[$anbieterNr]['created'],
                    $stats[$anbieterNr]['updated'],
                    $stats[$anbieterNr]['deleted']
                )
            );
        }

        return $stats;
    }

    /**
     * @return list<ObjectData>
     */
    private function getObjects(string $anbieterNr, Anbieter $anbieter): array
    {
        return array_filter(
            array_map(
                function (Immobilie $immobilie) use ($anbieterNr): ObjectData|null {
                    try {
                        return $this->normalizer->normalize($anbieterNr, $immobilie);
                    } catch (NormalizationException $e) {
                        $this->logger?->warning(
                            sprintf(
                                'OpenImmo: Immobilie "%s" konnte nicht importiert werden. %s',
                                $immobilie->getFreitexte()?->getObjekttitel() ?? '[unbekannt]',
                                $e->getMessage()
                            )
                        );

                        return null;
                    }
                },
                $anbieter->getImmobilie()
            )
        );
    }

    /**
     * @param list<ObjectData> $objects
     */
    private function createAndSyncFiles(array $objects, OpenImmoArchive $archive): void
    {
        $filesystem = new Filesystem();

        foreach ($objects as $object) {
            $basePath = $this->fileUtil->getResourceBasePath($object);

            if (!$filesystem->exists($basePath)) {
                $filesystem->mkdir($basePath);
            }

            $archive->extractResourceFiles($basePath, ...$object->getResourceFiles());
            $this->fileUtil->synchronizeResources($object);
        }
    }
}
