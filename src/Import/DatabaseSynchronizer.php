<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import;

use Doctrine\DBAL\Connection;
use EBlick\ContaoOpenImmoImport\Import\Data\ObjectData;

class DatabaseSynchronizer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ResourceUtil $fileUtil,
    ) {
    }

    /**
     * Synchronizes the database from a set of objects.
     *
     * @param list<ObjectData> $objects
     *
     * @return array{created: int, updated: int, deleted: int}
     */
    public function synchronize(int $ccFibaAnbieterId, array $objects, ImportMode $mode, string|null $senderSoftware): array
    {
        // Index remote data
        $remoteObjectsByObjectId = [];

        foreach ($objects as $object) {
            $remoteObjectsByObjectId[$object->getObjectId()] = $object;
        }

        // Fetch local data
        $localIdsByObjectId = array_map(
            'intval',
            $this->connection->fetchAllKeyValue(
                "SELECT property_number, id FROM cc_fiba_objekte WHERE pid=? AND published='1'",
                [$ccFibaAnbieterId]
            )
        );

        // Find out what to create/update/delete
        [$itemsToCreate, $itemsToUpdate, $itemsToDelete] = match ($mode) {
            ImportMode::Synchronize => $this->diff($remoteObjectsByObjectId, $localIdsByObjectId),
            ImportMode::Patch => array_replace(
                $this->diff($remoteObjectsByObjectId, $localIdsByObjectId),
                [2 => []]
            ),
            ImportMode::Delete => [
                [], [], array_values(array_intersect_key($localIdsByObjectId, $remoteObjectsByObjectId)),
            ]
        };

        $this->connection->beginTransaction();

        // Mark orphaned objects unpublished (= soft delete)
        if (!empty($itemsToDelete)) {
            $this->connection->executeQuery(
                "UPDATE cc_fiba_objekte SET published='', tstamp=:tstamp WHERE id IN (:ids)",
                ['ids' => $itemsToDelete, 'tstamp' => time()],
                ['ids' => Connection::PARAM_INT_ARRAY]
            );
        }

        // Create new objects
        foreach ($itemsToCreate as $index => $objectToCreate) {
            // If an object already exists, but was soft-deleted, update it instead.
            $existingId = $this->connection->fetchOne(
                'SELECT id FROM cc_fiba_objekte WHERE pid=? AND property_number=?',
                [$ccFibaAnbieterId, $objectToCreate->getObjectId()]
            );

            if ($existingId) {
                $itemsToUpdate[(int) $existingId] = $objectToCreate;
                unset($itemsToCreate[$index]);

                continue;
            }

            $this->connection->insert(
                'cc_fiba_objekte',
                array_merge(
                    $objectToCreate->getObjectProperties(),
                    $this->getResourceReferences($objectToCreate),
                    [
                        'pid' => $ccFibaAnbieterId,
                        'ptable' => 'cc_fiba_anbieter',
                        'tstamp' => $time = time(),
                        'date_create' => $time,
                        'alias' => $objectToCreate->getObjectAlias(),
                        'property_number' => $objectToCreate->getObjectId(),
                        'betreuer' => (string) $this->findBetreuerId($ccFibaAnbieterId, $objectToCreate),
                        'quelle' => $senderSoftware ?? '',

                        // Static defaults
                        'published' => '1',
                        'top_object' => '',
                        'notelist' => '1',
                        'protection_usergroup' => 2,
                    ]
                )
            );
        }

        // Update existing objects
        foreach ($itemsToUpdate as $id => $objectToUpdate) {
            $columns = array_merge(
                $objectToUpdate->getObjectProperties(),
                $this->getResourceReferences($objectToUpdate),
                ['published' => '1']
            );

            if (empty($affectedColumns = $this->getAffectedColumns($id, $columns))) {
                unset($itemsToUpdate[$id]);
                continue;
            }

            $this->connection->update(
                'cc_fiba_objekte',
                $affectedColumns,
                ['id' => $id]
            );
        }

        $this->connection->commit();

        // Return stats
        return [
            'created' => \count($itemsToCreate),
            'updated' => \count($itemsToUpdate),
            'deleted' => \count($itemsToDelete),
        ];
    }

    /**
     * Diffs the datasets and returns an array of arrays
     * [$itemsToCreate, $itemsToUpdate, $itemsToDelete] where...
     *
     *   - $itemsToCreate is a list of ObjectData objects that should get added,
     *   - $itemsToUpdate is an array of ObjectData objects indexed by the
     *     database ID, that should get updated,
     *   - $itemsToRemove is a list of database IDs, that should be removed.
     *
     * @param array<string, ObjectData> $remoteObjectsByObjectId
     * @param array<string, int>        $localIdsByObjectId
     *
     * @return array{0: list<ObjectData>, 1: array<int, ObjectData>, 2: list<int>}
     */
    private function diff(array $remoteObjectsByObjectId, array $localIdsByObjectId): array
    {
        $itemsToCreate = [];
        $itemsToUpdate = [];

        // Start with all elements marked as orphans (= to be deleted) and
        // gradually remove their entries once found.
        $orphans = $localIdsByObjectId;

        foreach ($remoteObjectsByObjectId as $objectId => $object) {
            if (null !== ($id = $localIdsByObjectId[$objectId] ?? null)) {
                $itemsToUpdate[$id] = $object;
                unset($orphans[$objectId]);

                continue;
            }

            $itemsToCreate[] = $object;
        }

        return [$itemsToCreate, $itemsToUpdate, array_values($orphans)];
    }

    /**
     * @param array<string, int|string|null> $columns
     *
     * @return array<string, int|string|null>
     */
    private function getAffectedColumns(int $id, array $columns): array
    {
        $currentData = $this->connection->fetchAssociative(
            'SELECT * FROM cc_fiba_objekte WHERE id=?',
            [$id]
        );

        $currentData = array_change_key_case($currentData, CASE_LOWER);
        $affectedColumns = [];

        foreach ($columns as $column => $value) {
            $column = strtolower($column);

            if (!\array_key_exists($column, $currentData)) {
                throw new \LogicException(sprintf('Column "cc_fiba_objekte.%s" does not exist.', $column));
            }

            if ($currentData[$column] !== $value) {
                $affectedColumns[$column] = $value;
            }
        }

        return $affectedColumns;
    }

    private function findBetreuerId(int $ccFibaAnbieterId, ObjectData $objectData): int|null
    {
        return $this->connection->fetchOne(
            'SELECT id FROM cc_fiba_anbieter_betreuer WHERE pid=? AND external_id=?',
            [$ccFibaAnbieterId, $objectData->getAgentData()['external_id']]
        ) ?: null;
    }

    /**
     * @return array<string, string>
     */
    private function getResourceReferences(ObjectData $objectData): array
    {
        $resourcePathMap = $this->fileUtil->getResourcePathMap($objectData);

        $uuidsByPath = $this->connection->fetchAllKeyValue(
            'SELECT path, uuid FROM tl_files WHERE path IN (?)',
            [array_values($resourcePathMap)],
            [Connection::PARAM_STR_ARRAY]
        );

        $getUuids = static fn (array $archivePaths) => array_filter(
            array_map(
                static fn (string $path): string => $uuidsByPath[$resourcePathMap[$path]] ?? null,
                $archivePaths,
            )
        );

        $titleImage = $getUuids([$objectData->getTitleImage()])[0] ?? null;
        $galleryImages = serialize($getUuids($objectData->getGalleryImages()));
        $documents = serialize($getUuids($objectData->getDocuments()));
        $otherAttachments = serialize($getUuids($objectData->getOtherAttachments()));

        return [
            'image' => $titleImage,
            'gallery' => $galleryImages,
            'orderSRC_gallery' => $galleryImages,
            'gallery_fullsize' => '1',
            'expose' => $documents,
            'ordersrc_expose' => $documents,
            'dokuments' => $otherAttachments,
            'ordersrc_dokuments' => $otherAttachments,
        ];
    }
}
