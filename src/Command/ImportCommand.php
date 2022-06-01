<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Command;

use EBlick\ContaoOpenImmoImport\Import\Data\OpenImmoArchive;
use EBlick\ContaoOpenImmoImport\Import\Importer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class ImportCommand extends Command
{
    private Filesystem $filesystem;

    protected static $defaultName = 'openimmo:import';
    protected static $defaultDescription = 'Synchronize the database/filesystem from OpenImmo .zip files.';

    public function __construct(
        private readonly Importer $importer,
        private readonly string $projectDir,
    ) {
        $this->filesystem = new Filesystem();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('source-dir', InputArgument::REQUIRED, 'directory to search for sources');

        $this->addOption('backup-dir', 'b', InputOption::VALUE_REQUIRED, 'if a backup directory is specified, the source file will be moved there after processing');

        $this->addOption('max-files', 'm', InputOption::VALUE_REQUIRED, 'maximum number of files to be processed', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = (new Finder())
            ->in(Path::makeAbsolute($input->getArgument('source-dir'), $this->projectDir))
            ->depth('< 1')
            ->files()
            ->name('*.zip')
        ;

        if (null !== ($backup = $input->getOption('backup-dir'))) {
            $backup = Path::makeAbsolute($backup, $this->projectDir);

            if (!is_dir($backup)) {
                throw new InvalidArgumentException('The backup directory does not exist.');
            }
        }

        $maxFiles = (int) $input->getOption('max-files');
        $iteration = 0;

        // Iterate directory and import
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Starting the OpenImmo import…');
        $start = microtime(true);

        $success = true;

        foreach ($files as $file) {
            if ($maxFiles > $iteration) {
                ++$iteration;
            } else {
                break;
            }

            $success &= $this->importFromFile($file->getPathname(), $io);

            // Backup or delete file
            if ($backup) {
                $target = Path::join($backup, $file->getFilename());
                $index = 1;

                while ($this->filesystem->exists($target)) {
                    $target = Path::join(
                        $backup,
                        sprintf(
                            '%s_%d.%s',
                            $file->getFilenameWithoutExtension(),
                            $index++,
                            $file->getExtension()
                        )
                    );
                }

                $this->filesystem->rename($file->getPathname(), $target);

                continue;
            }

            $this->filesystem->remove($file);
        }

        if ($success) {
            $io->success(
                sprintf(
                    'Import of %s file(s) completed in %ss.',
                    $iteration,
                    round(microtime(true) - $start, 2)
                )
            );

            return Command::SUCCESS;
        }

        $io->error(
            sprintf(
                'Import of %s file(s) completed in %ss. There were unresolvable errors.',
                $iteration,
                round(microtime(true) - $start, 2)
            )
        );

        return Command::FAILURE;
    }

    private function importFromFile(string $filePath, SymfonyStyle $io): bool
    {
        $io->title(sprintf('Importing "%s"', basename($filePath)));

        try {
            $stats = $this->importer->import(new OpenImmoArchive($filePath));
        } catch (\Throwable $e) {
            $io->warning(sprintf("Import failed with an %s.\n\n%s\n%s", \get_class($e), $e->getMessage(), $e->getTraceAsString()));

            return false;
        }

        $rows = [];

        foreach ($stats as $anbieterNr => $diffStats) {
            $rows[] = [
                $anbieterNr,
                $diffStats['created'],
                $diffStats['updated'],
                $diffStats['deleted'],
            ];
        }

        $io->table(['OpenImmo ID', 'created', 'updated', 'deleted'], $rows);

        return true;
    }
}
