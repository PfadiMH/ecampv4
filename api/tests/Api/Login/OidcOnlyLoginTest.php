<?php

declare(strict_types=1);

namespace App\Tests\Api\Login;

use App\Tests\Api\ECampApiTestCase;

/**
 * Login is OIDC-only: password login, self-registration, password reset and account
 * activation are all disabled, and the API root advertises only the OIDC entry point.
 *
 * @internal
 */
class OidcOnlyLoginTest extends ECampApiTestCase {
    public function testPasswordLoginIsDisabled() {
        $response = static::createBasicClient()->request('POST', '/authentication_token', [
            'json' => ['identifier' => 'test@example.com', 'password' => 'test'],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    public function testSelfRegistrationIsDisabled() {
        $response = static::createBasicClient()->request('POST', '/users', ['json' => []]);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testPasswordResetIsDisabled() {
        $response = static::createBasicClient()->request('POST', '/auth/reset_password', [
            'json' => ['email' => 'test@example.com', 'recaptchaToken' => 'x'],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    public function testResendActivationIsDisabled() {
        $response = static::createBasicClient()->request('POST', '/auth/resend_activation', [
            'json' => ['email' => 'test@example.com', 'recaptchaToken' => 'x'],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    public function testApiRootAdvertisesOnlyOidcLogin() {
        $response = static::createBasicClient()->request('GET', '/');
        $links = $response->toArray()['_links'];

        $this->assertArrayHasKey('oauthOidc', $links);
        $this->assertArrayNotHasKey('login', $links);
        $this->assertArrayNotHasKey('oauthGoogle', $links);
        $this->assertArrayNotHasKey('resetPassword', $links);
    }
}
