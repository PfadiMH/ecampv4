<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActivityProgressLabel;
use App\Entity\Camp;
use App\Entity\Category;
use App\Entity\ContentNode\ChecklistNode;
use App\Entity\ContentNode\ColumnLayout;
use App\Entity\ContentNode\MaterialNode;
use App\Entity\ContentNode\MultiSelect;
use App\Entity\ContentNode\ResponsiveLayout;
use App\Entity\ContentNode\SingleText;
use App\Entity\ContentNode\Storyboard;
use App\Entity\ContentType;
use App\Entity\MaterialList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Imports prototype camp templates from a JSON file previously extracted from another
 * eCamp instance (see api/templates/ecamp_templates.json). Idempotent: skips templates
 * whose title already exists as a prototype.
 */
#[AsCommand(name: 'app:import-templates', description: 'Import prototype camp templates from an extracted JSON file')]
class ImportTemplatesCommand extends Command {
    private const TYPE_MAP = [
        'column_layouts' => ColumnLayout::class,
        'single_texts' => SingleText::class,
        'material_nodes' => MaterialNode::class,
        'multi_selects' => MultiSelect::class,
        'storyboards' => Storyboard::class,
        'responsive_layouts' => ResponsiveLayout::class,
        'checklist_nodes' => ChecklistNode::class,
    ];

    public function __construct(private readonly EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Path to the templates JSON', \dirname(__DIR__, 2).'/templates/ecamp_templates.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        if (!is_file($file)) {
            $io->error("File not found: {$file}");

            return Command::FAILURE;
        }
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        // Map content types by name (this instance's ids).
        $ctByName = [];
        foreach ($this->em->getRepository(ContentType::class)->findAll() as $ct) {
            $ctByName[$ct->name] = $ct;
        }

        // Verify all referenced content type names exist before writing anything.
        $missing = [];
        foreach ($data['templates'] as $tpl) {
            foreach ($tpl['categories'] as $cat) {
                foreach ($cat['contentTypeNames'] ?? [] as $n) {
                    if (!isset($ctByName[$n])) {
                        $missing[$n] = true;
                    }
                }
            }
            foreach ($tpl['contentNodes'] as $node) {
                if (!empty($node['contentTypeName']) && !isset($ctByName[$node['contentTypeName']])) {
                    $missing[$node['contentTypeName']] = true;
                }
            }
        }
        if ($missing) {
            $io->error('Missing content types in this instance: '.implode(', ', array_keys($missing)));

            return Command::FAILURE;
        }

        $campRepo = $this->em->getRepository(Camp::class);
        $imported = 0;
        $skipped = 0;

        foreach ($data['templates'] as $tpl) {
            if ($campRepo->findOneBy(['title' => $tpl['title'], 'isPrototype' => true])) {
                $io->writeln("  skip (already exists): {$tpl['title']}");
                ++$skipped;
                continue;
            }

            $camp = new Camp();
            $camp->title = $tpl['title'];
            $camp->name = mb_substr($tpl['title'], 0, 32);
            $camp->motto = $tpl['motto'] ?? null;
            $camp->isPrototype = true;
            $camp->isPublic = true;
            $this->em->persist($camp);

            foreach ($tpl['materialLists'] as $ml) {
                $list = new MaterialList();
                $list->name = $ml['name'];
                $camp->addMaterialList($list);
                $this->em->persist($list);
            }

            foreach ($tpl['progressLabels'] as $pl) {
                $label = new ActivityProgressLabel();
                $label->title = $pl['title'];
                $label->position = $pl['position'] ?? -1;
                $camp->addProgressLabel($label);
                $this->em->persist($label);
            }

            // Group content nodes by the root of their category tree.
            $nodesByRoot = [];
            foreach ($tpl['contentNodes'] as $node) {
                $nodesByRoot[$node['rootSrcId']][] = $node;
            }

            foreach ($tpl['categories'] as $catData) {
                $cat = new Category();
                $cat->short = $catData['short'];
                $cat->name = $catData['name'];
                $cat->color = $catData['color'];
                $cat->numberingStyle = $catData['numberingStyle'];
                $camp->addCategory($cat);
                foreach ($catData['contentTypeNames'] ?? [] as $ctn) {
                    $cat->preferredContentTypes->add($ctByName[$ctn]);
                }
                $this->em->persist($cat);

                $nodes = $nodesByRoot[$catData['rootSrcId']] ?? [];
                // First pass: instantiate every node.
                $entById = [];
                foreach ($nodes as $node) {
                    $cls = self::TYPE_MAP[$node['type']] ?? null;
                    if (null === $cls) {
                        continue;
                    }
                    $entity = new $cls();
                    $entity->slot = $node['slot'];
                    $entity->position = $node['position'] ?? -1;
                    $entity->instanceName = $node['instanceName'];
                    $entity->data = $node['data'];
                    if (!empty($node['contentTypeName'])) {
                        $entity->contentType = $ctByName[$node['contentTypeName']];
                    }
                    $entById[$node['srcId']] = $entity;
                }
                /** @var ColumnLayout|null $rootEntity */
                $rootEntity = $entById[$catData['rootSrcId']] ?? null;
                // Second pass: wire the tree (root self-references; children point to parent).
                foreach ($nodes as $node) {
                    $entity = $entById[$node['srcId']] ?? null;
                    if (null === $entity) {
                        continue;
                    }
                    $entity->root = $rootEntity;
                    if (!empty($node['parentSrcId']) && isset($entById[$node['parentSrcId']])) {
                        $entity->parent = $entById[$node['parentSrcId']];
                    }
                    $this->em->persist($entity);
                }
                if (null !== $rootEntity) {
                    $cat->setRootContentNode($rootEntity);
                }
            }

            ++$imported;
            $io->writeln("  imported: {$tpl['title']} (".count($tpl['categories']).' categories, '.count($tpl['contentNodes']).' nodes)');
        }

        $this->em->flush();
        $io->success("Done. imported={$imported}, skipped={$skipped}");

        return Command::SUCCESS;
    }
}
