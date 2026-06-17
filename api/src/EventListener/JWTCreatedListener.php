<?php

declare(strict_types=1);

namespace App\EventListener;

use ApiPlatform\Metadata\IriConverterInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Adds the IRI of the newly logged in user to the JWT token payload. This is useful for frontends
 * to know where to fetch personal profile information.
 *
 * Also adds a unique token id (jti) so that every issued token is distinct. Among other things this
 * matters for the HTTP cache, which is keyed on the JWT cookie: without a unique claim, two logins of
 * the same user in the same second would produce a byte-identical token (iat/exp are second-precise)
 * and therefore share a cache entry.
 */
class JWTCreatedListener {
    public function __construct(private readonly Security $security, private readonly IriConverterInterface $iriConverter) {}

    public function onJWTCreated(JWTCreatedEvent $event) {
        $payload = $event->getData();

        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $payload['user'] = $this->iriConverter->getIriFromResource($user);
        $payload['jti'] = bin2hex(random_bytes(16));
        $event->setData($payload);
    }
}
