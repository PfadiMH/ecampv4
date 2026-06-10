<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Camp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renames and/or prunes prototype camp templates. Dry-run unless --force is given.
 *
 *   bin/console app:prune-templates \
 *     --rename "J+S Lager (Deutsch)=Pfadi Lager" --rename "J+S Kurs (Deutsch)=Pfadi Kurs" \
 *     --keep "Basic" --keep "Pfadi Lager" --keep "Pfadi Kurs" --force
 */
#[AsCommand(name: 'app:prune-templates', description: 'Rename and/or prune prototype camp templates')]
class PruneTemplatesCommand extends Command {
    public function __construct(private readonly EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addOption('rename', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Rename a prototype: "old title=new title"', [])
            ->addOption('keep', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Title of a prototype to keep (all others are deleted)', [])
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually apply changes (otherwise dry-run)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $renames = [];
        foreach ($input->getOption('rename') as $pair) {
            [$old, $new] = array_pad(explode('=', $pair, 2), 2, null);
            if (null === $new) {
                $io->error("Invalid --rename value (expected old=new): {$pair}");

                return Command::FAILURE;
            }
            $renames[trim($old)] = trim($new);
        }
        $keep = array_map('trim', $input->getOption('keep'));

        $repo = $this->em->getRepository(Camp::class);

        /** @var Camp[] $prototypes */
        $prototypes = $repo->findBy(['isPrototype' => true]);

        // 1) Renames
        foreach ($prototypes as $camp) {
            if (isset($renames[$camp->title])) {
                $new = $renames[$camp->title];
                $io->writeln(($force ? 'RENAME ' : 'would rename ')."\"{$camp->title}\" -> \"{$new}\"");
                // Apply in memory so the keep-check below sees the new title; only --force flushes.
                $camp->title = $new;
                $camp->name = mb_substr($new, 0, 32);
            }
        }

        // 2) Deletions: every prototype whose (possibly new) title is not in --keep
        $toDelete = [];
        foreach ($prototypes as $camp) {
            if (!in_array($camp->title, $keep, true)) {
                $toDelete[] = $camp;
            }
        }
        foreach ($toDelete as $camp) {
            $io->writeln(($force ? 'DELETE ' : 'would delete ')."\"{$camp->title}\"");
            if ($force) {
                // Mirror CampRemoveProcessor: remove root content nodes explicitly first.
                foreach ($camp->activities->getIterator() as $activity) {
                    if (null !== $activity->getRootContentNode()) {
                        $this->em->refresh($activity->getRootContentNode());
                        $this->em->remove($activity->getRootContentNode());
                    }
                }
                foreach ($camp->categories->getIterator() as $category) {
                    if (null !== $category->getRootContentNode()) {
                        $this->em->refresh($category->getRootContentNode());
                        $this->em->remove($category->getRootContentNode());
                    }
                }
                $this->em->remove($camp);
            }
        }

        if ($force) {
            $this->em->flush();
            $io->success('Applied. renamed='.count($renames).', deleted='.count($toDelete));
        } else {
            $io->warning('Dry-run only. Re-run with --force to apply.');
        }

        return Command::SUCCESS;
    }
}
