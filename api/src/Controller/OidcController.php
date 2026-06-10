<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class OidcController extends AbstractController {
    public function __construct(private readonly ClientRegistry $clientRegistry) {}

    /**
     * Link to this controller to start the "connect" process.
     */
    #[Route('/auth/oidc', name: 'connect_oidc_start')]
    public function connect(Request $request) {
        return $this->clientRegistry
            ->getClient('oidc') // key used in config/packages/knpu_oauth2_client.yaml
            ->redirect([], ['additionalData' => ['callback' => $request->query->get('callback')]])
        ;
    }

    /**
     * After authenticating at the OIDC provider, the user is redirected back here
     * because this is the "redirect_route" configured in
     * config/packages/knpu_oauth2_client.yaml. The OidcAuthenticator handles it.
     */
    #[Route('/auth/oidc/callback', name: 'connect_oidc_check')]
    public function connectCheck() {
        // left blank on purpose: see App\Security\OAuth\OidcAuthenticator
    }
}
