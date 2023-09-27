<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import;

use Contao\CoreBundle\Filesystem\Dbafs\Dbafs;
use EBlick\ContaoOpenImmoImport\Import\Data\ObjectData;
use Symfony\Component\Filesystem\Path;

class ResourceUtil
{
    public function __construct(
        private readonly string $uploadDir,
        private readonly string $immoDir,
        private readonly Dbafs $dbafs,
    ) {
    }

    public function getResourceBasePath(ObjectData $objectData): string
    {
        return Path::join(
            $this->uploadDir,
            $this->immoDir,
            $objectData->getAnbieterNr(),
            $objectData->getObjectId()
        );
    }

    /**
     * Returns a mapping of archive file names to DBAFS paths.
     *
     * @return array<string, string>
     */
    public function getResourcePathMap(ObjectData $objectData): array
    {
        $map = [];

        foreach ($objectData->getResourceFiles() as $path) {
            // Skip paths that do not reference files in the archive
            if (str_contains(Path::canonicalize($path), '/')) {
                continue;
            }

            $map[$path] = Path::makeRelative(
                Path::join($this->getResourceBasePath($objectData), $path),
                Path::getDirectory($this->uploadDir)
            );
        }

        return $map;
    }

    /**
     * Synchronizes the DBAFS for files in the object's resource base path.
     */
    public function synchronizeResources(ObjectData $object): void
    {
        $this->dbafs->sync(Path::makeRelative($this->getResourceBasePath($object), $this->uploadDir));
    }
}
