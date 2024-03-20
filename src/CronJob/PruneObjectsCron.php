<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\CronJob;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Doctrine\DBAL\Connection;
use EBlick\ContaoOpenImmoImport\Import\ResourceUtil;
use Symfony\Component\Filesystem\Path;

class PruneObjectsCron
{
    private const PRUNE_CONDITION = '-10 days';

    public function __construct(
        private readonly Connection $connection,
        private readonly VirtualFilesystemInterface $filesStorage,
        private readonly ResourceUtil $resourceUtil,
        private readonly string $uploadDir,
    ) {
    }

    public function __invoke(): void
    {
        $result = $this->connection
            ->executeQuery(
                "SELECT a.onoffice_anbieter_nummer as anbieterNr, o.property_number as objectId
                 FROM cc_fiba_objekte o
                 LEFT JOIN eblick_fiba.cc_fiba_anbieter a ON o.pid = a.id
                 WHERE published='' AND o.tstamp < ?",
                [strtotime(self::PRUNE_CONDITION)]
            )
            ->fetchAllAssociative()
        ;

        foreach ($result as $row) {
            $path = Path::makeRelative(
                $this->resourceUtil->getResourceBasePath($row['anbieterNr'], $row['objectId']),
                $this->uploadDir
            );

            if ($this->filesStorage->directoryExists($path)) {
                $this->filesStorage->deleteDirectory($path);
            }
        }
    }
}
