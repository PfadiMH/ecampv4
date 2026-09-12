<?php

namespace App\Tests\Command;

use App\Entity\Camp;
use App\Tests\Api\ECampApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class TemplatesCommandTest extends ECampApiTestCase {
    public function testImportPreservesTemplateTreesIsIdempotentAndPruneDefaultsToDryRun() {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $import = new CommandTester($application->find('app:import-templates'));
        $import->execute([]);
        $import->assertCommandIsSuccessful();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $repo = $em->getRepository(Camp::class);
        $templates = json_decode(file_get_contents(dirname(__DIR__, 2).'/templates/ecamp_templates.json'), true, 512, JSON_THROW_ON_ERROR)['templates'];
        foreach ($templates as $expected) {
            $camp = $repo->findOneBy(['title' => $expected['title'], 'isPrototype' => true]);
            $this->assertNotNull($camp);
            $this->assertCount(count($expected['categories']), $camp->categories);
            foreach ($camp->categories as $category) {
                $this->assertNotNull($category->getRootContentNode());
            }
        }
        $count = $repo->count(['isPrototype' => true]);
        $import->execute([]);
        $import->assertCommandIsSuccessful();
        $this->assertSame($count, $repo->count(['isPrototype' => true]));

        $prune = new CommandTester($application->find('app:prune-templates'));
        $prune->execute(['--keep' => ['Basic'], '--rename' => ['J+S Lager (Deutsch)=Review template']]);
        $prune->assertCommandIsSuccessful();
        $em->clear();
        $this->assertSame($count, $repo->count(['isPrototype' => true]));
        $this->assertNotNull($repo->findOneBy(['title' => 'J+S Lager (Deutsch)', 'isPrototype' => true]));
        $this->assertNull($repo->findOneBy(['title' => 'Review template', 'isPrototype' => true]));
    }
}
