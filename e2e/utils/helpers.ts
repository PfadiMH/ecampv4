import {
  expect,
  request,
  test,
  type APIRequestContext,
  type Page,
} from '@playwright/test'
export const API_ROOT_URL = process.env.API_ROOT_URL || 'http://localhost:3000/api'
export const API_ROOT_URL_CACHED =
  process.env.API_ROOT_URL_CACHED || 'http://localhost:3004'
export const FRONTEND_URL = process.env.FRONTEND_URL || 'http://localhost:3000'

// eCamp3 login is OIDC-only. In CI/dev a mock OIDC provider (mock-oauth2-server,
// see docker-compose.ci.yml / docker-compose.override.yml) stands in for a real
// identity provider. Its `username` form field becomes the OIDC subject and the
// `email` claim is matched to the seeded eCamp3 account, so logging in is just a
// matter of driving the provider's interactive login form.

/**
 * Authenticate an APIRequestContext by replaying the OIDC redirect flow over HTTP,
 * leaving the API's session cookies in the context's cookie jar. Unlike the old
 * password login, this supports any seeded user via their email.
 */
export async function login(
  request: APIRequestContext,
  identifier: string,
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  password: string = 'test'
) {
  // 1. Start the flow at the API; it redirects to the provider's authorize URL
  //    and sets the OIDC state cookie on this context.
  const start = await request.get(
    `${API_ROOT_URL}/auth/oidc?callback=${encodeURIComponent(`${FRONTEND_URL}/`)}`,
    { maxRedirects: 0 }
  )
  const authorizeUrl = start.headers()['location']
  expect(authorizeUrl, 'OIDC start should redirect to the provider').toBeTruthy()

  // 2. Submit the user selection to the mock provider. It issues an authorization
  //    code and redirects back to the API callback.
  const authorize = await request.post(authorizeUrl, {
    form: {
      username: identifier,
      claims: JSON.stringify({ email: identifier }),
    },
    maxRedirects: 0,
  })
  const callbackUrl = authorize.headers()['location']
  expect(callbackUrl, 'provider should redirect back to the API callback').toBeTruthy()

  // 3. Hit the API callback. The authenticator exchanges the code, resolves the
  //    user and sets the session cookies on this context.
  const callback = await request.get(callbackUrl, { maxRedirects: 0 })
  expect(
    [301, 302, 303],
    `OIDC callback should redirect after login (got ${callback.status()})`
  ).toContain(callback.status())
}

/**
 * Log in through the browser via the OIDC flow and leave the session cookie on the
 * page. `user` is the seeded user's email, which must appear in the mock provider's
 * login page (infra/mock-oidc/login.html).
 */
export async function loginAndSetCookie(
  page: Page,
  _: unknown,
  user: string,
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  password: string = 'test'
) {
  // '/' redirects unauthenticated users to '/login'.
  await page.goto('/login')
  // Start the OIDC redirect to the mock provider.
  await page.locator('.ec-login-button').click()
  // On the provider's login page, pick the user whose email claim matches.
  await page.locator(`form:has(input[value*="${user}"]) button`).click()
  await page.waitForURL('**/camps', { timeout: 60000 })
}

export async function getAuthContext(user: string): Promise<APIRequestContext> {
  const defaultRequest = await request.newContext()
  await login(defaultRequest, user)
  const state = await defaultRequest.storageState()
  await defaultRequest.dispose()
  return await request.newContext({
    storageState: state,
  })
}

export { getPdfProperties } from './getPdfProperties'

export async function expectCacheHeader(
  request: APIRequestContext,
  uri: string,
  expectedHeader: string
) {
  await test.step(
    `Check Header: ${expectedHeader}`,
    async () => {
      const response = await request.get(`${API_ROOT_URL_CACHED}${uri}.jsonhal`)
      expect(response.headers()['x-cache']).toBe(expectedHeader)
    },
    { box: true }
  )
}

export async function expectCacheHit(request: APIRequestContext, uri: string) {
  await test.step(
    'Expect Cache HIT',
    async () => {
      await expectCacheHeader(request, uri, 'HIT')
    },
    { box: true }
  )
}

export async function expectCacheMiss(request: APIRequestContext, uri: string) {
  await test.step(
    'Expect Cache MISS',
    async () => {
      await expectCacheHeader(request, uri, 'MISS')
    },
    { box: true }
  )
}

export async function expectCachePass(request: APIRequestContext, uri: string) {
  await test.step(
    'Expect Cache PASS',
    async () => {
      await expectCacheHeader(request, uri, 'PASS')
    },
    { box: true }
  )
}

export async function waitForCacheMiss(request: APIRequestContext, uri: string) {
  await test.step(
    `Wait for Cache MISS on ${uri}`,
    async () => {
      await expect
        .poll(
          async () => {
            const response = await request.get(`${API_ROOT_URL_CACHED}${uri}.jsonhal`)
            return response.headers()['x-cache']
          },
          { timeout: 10000 }
        )
        .toBe('MISS')
    },
    { box: true }
  )
}

export async function apiGet(request: APIRequestContext, uri: string) {
  return await request.get(`${API_ROOT_URL_CACHED}${uri}.jsonhal`)
}

export async function apiPatch(
  request: APIRequestContext,
  uri: string,
  body: Record<string, unknown>
) {
  return await request.patch(`${API_ROOT_URL_CACHED}${uri}.jsonhal`, {
    data: body,
    headers: {
      'Content-Type': 'application/merge-patch+json',
    },
  })
}

export async function apiPost(
  request: APIRequestContext,
  uri: string,
  body: Record<string, unknown>
) {
  return await request.post(`${API_ROOT_URL_CACHED}${uri}.jsonhal`, {
    data: body,
    headers: {
      'Content-Type': 'application/hal+json',
    },
  })
}

export async function apiDelete(request: APIRequestContext, uri: string) {
  return await request.delete(`${API_ROOT_URL_CACHED}${uri}.jsonhal`)
}

export async function mockDateNow(page: Page, date: string = 'April 30 2026 13:00:00') {
  const fakeNow = new Date(date).valueOf()

  await page.addInitScript(`{
    Date = class extends Date {
      constructor(...args) {
        if (args.length === 0) {
          super(${fakeNow});
        } else {
          super(...args);
        }
      }
    }

    const __DateNowOffset = ${fakeNow} - Date.now();
    const __DateNow = Date.now;
    Date.now = () => __DateNow() + __DateNowOffset;
  }`)
}
