<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import\Data;

use EBlick\ContaoOpenImmoImport\Import\ImportMode;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Filesystem\Path;
use Ujamii\OpenImmo\API\Openimmo;
use Ujamii\OpenImmo\API\Uebertragung;
use Ujamii\OpenImmo\Handler\DateTimeHandler;

class OpenImmoArchive
{
    private \ZipArchive $archive;

    /**
     * @var list<string>
     */
    private array $resourceFiles = [];

    private Openimmo|null $data = null;

    /**
     * @var false|resource
     */
    private $xmlResource = false;

    public function __construct(string $fileName)
    {
        $this->archive = new \ZipArchive();

        if (!$this->archive->open($fileName)) {
            throw new \InvalidArgumentException(sprintf('Could not open archive in "%s".', $fileName));
        }

        // Index files
        for ($i = 0; $i < $this->archive->numFiles; ++$i) {
            $stat = $this->archive->statIndex($i);

            if ('xml' === Path::getExtension($name = $stat['name'], true)) {
                $this->xmlResource = $this->archive->getStream($name);
                continue;
            }

            $this->resourceFiles[] = $name;
        }

        if (false === $this->xmlResource) {
            throw new \RuntimeException(sprintf('Archive "%s" does not contain a .xml file.', $fileName));
        }
    }

    public function __destruct()
    {
        $this->archive->close();
    }

    public function getOpenImmoData(): Openimmo
    {
        if (null !== $this->data) {
            return $this->data;
        }

        $serializer = SerializerBuilder::create()
            ->configureHandlers(
                static function (HandlerRegistryInterface $registry): void {
                    $registry->registerSubscribingHandler(new DateTimeHandler());
                }
            )
            ->build()
        ;

        return $this->data = $serializer->deserialize(
            stream_get_contents($this->xmlResource),
            Openimmo::class,
            'xml'
        );
    }

    public function getImportMode(): ImportMode
    {
        $uebertragung = $this->getOpenImmoData()->getUebertragung();

        if (Uebertragung::UMFANG_VOLL === $uebertragung?->getUmfang()) {
            return ImportMode::Synchronize;
        }

        $mode = $uebertragung?->getModus();

        // Flowfact does not seem to transmit a global import mode but adds
        // information on each object instead. Until we support synchronizing
        // on a per-element level, we can only try to emulate the global mode.
        // This works if ALL objects feature the exact same mode.
        if (null === $mode) {
            $modes = [];

            foreach ($this->getOpenImmoData()->getAnbieter() as $anbieter) {
                foreach ($anbieter->getImmobilie() as $immobilie) {
                    $modes[] = $immobilie->getVerwaltungTechn()?->getAktion()?->getAktionart();
                }
            }

            if (1 === \count($modes = array_unique($modes))) {
                $mode = $modes[0];
            }
        }

        return match ($mode) {
            Uebertragung::MODUS_NEW, Uebertragung::MODUS_CHANGE => ImportMode::Patch,
            Uebertragung::MODUS_DELETE => ImportMode::Delete,
            default => throw new \InvalidArgumentException(sprintf('Could not parse transmit mode, got "%s".', $mode))
        };
    }

    public function getSenderSoftware(): string|null
    {
        return $this->getOpenImmoData()->getUebertragung()?->getSendersoftware();
    }

    /**
     * @return list<string>
     */
    public function getResourceFiles(): array
    {
        return $this->resourceFiles;
    }

    public function extractResourceFiles(string $destination, string ...$resourceFiles): void
    {
        $this->archive->extractTo($destination, $resourceFiles);
    }
}
