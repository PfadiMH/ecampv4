<?php

namespace App\Tests\Api\Contributors;

use App\Entity\CampCollaboration;
use App\Tests\Api\ECampApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @internal
 */
class ContributorPermissionsTest extends ECampApiTestCase {
    public function testContributorCanEditContentButCannotMoveScheduleOrAssignResponsibles() {
        $client = static::createClientWithCredentials(['email' => static::getFixture('user2member')->getEmail()]);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $collaboration = $em->find(CampCollaboration::class, static::getFixture('campCollaboration2member')->getId());
        $collaboration->role = CampCollaboration::ROLE_CONTRIBUTOR;
        $em->flush();
        $em->clear();

        $client->request('PATCH', $this->getIriFor('activity1'), [
            'json' => ['title' => 'Contributor edited activity'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['title' => 'Contributor edited activity']);

        $client->request('PATCH', $this->getIriFor('scheduleEntry1period1camp1'), [
            'json' => ['start' => '2022-07-01T11:00:00+00:00'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $client->request('POST', '/activity_responsibles', ['json' => [
            'activity' => $this->getIriFor('activity1'),
            'campCollaboration' => $this->getIriFor('campCollaboration2member'),
        ]]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('DELETE', $this->getIriFor('activityResponsible1'));
        $this->assertResponseStatusCodeSame(403);

        $client->request('POST', '/day_responsibles', ['json' => [
            'day' => $this->getIriFor('day1period1'),
            'campCollaboration' => $this->getIriFor('campCollaboration2member'),
        ]]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('DELETE', $this->getIriFor('dayResponsible1'));
        $this->assertResponseStatusCodeSame(403);
    }
}
