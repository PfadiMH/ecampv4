<?php

namespace App\OAuth;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class UrlGeneratorDecorator implements UrlGeneratorInterface {
    public function __construct(
        private readonly UrlGeneratorInterface $decorated,
        // Whether the deployment is served over https (COOKIE_SECURE). When true,
        // OAuth redirect URIs are forced to https; over http (dev / e2e) they are
        // left untouched so the browser can actually reach the callback.
        private readonly bool $forceHttps
    ) {}

    public function setContext(RequestContext $context): void {
        $this->decorated->setContext($context);
    }

    public function getContext(): RequestContext {
        return $this->decorated->getContext();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string {
        $url = $this->decorated->generate($name, $parameters, $referenceType);
        if ($this->forceHttps) {
            $url = preg_replace('/^http:\/\//', 'https://', $url);
            if (is_null($url)) {
                throw new \Exception('Unexpected redirect URI');
            }
        }

        return $url;
    }
}
