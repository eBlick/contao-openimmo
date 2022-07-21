<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import;

enum ImportMode
{
    // Perform a full diff and create/update/delete items accordingly.
    case Synchronize;

    // Only create/update items.
    case Patch;

    // Delete referenced items instead of merging them.
    case Delete;
}
