import { test, expect } from '@playwright/test'
import { loginWithOAuth, withOAuthSession, getProfileEmail } from '@/utils/oauthHelpers'

test.describe('OAuth login', () => {
  const users = [
    'test@example.com',
    'admin@example.com',
    'castor@example.com',
    'bruce@wayne.com',
  ]

  for (const user of users) {
    test(
      `OIDC ${user}: shows camp list after login`,
      { tag: '@mature' },
      async ({ page }) => {
        await loginWithOAuth(page, user)
        await expect(page).toHaveURL('/camps')
      }
    )
  }
})

test.describe('OAuth login – account re-use', () => {
  test(
    'OIDC: second login returns the same account',
    { tag: '@mature' },
    async ({ browser }) => {
      const email1 = await withOAuthSession(browser, 'admin@example.com', getProfileEmail)
      const email2 = await withOAuthSession(browser, 'admin@example.com', getProfileEmail)
      expect(email2).toBe(email1)
    }
  )
})

test.describe('OIDC-only login – legacy providers stay blocked', () => {
  test(
    'Google and MiData cannot replace an OIDC session',
    { tag: '@mature' },
    async ({ browser, request }) => {
      const email1 = await withOAuthSession(
        browser,
        'castor@example.com',
        getProfileEmail
      )
      for (const provider of ['google', 'pbsmidata']) {
        const response = await request.get(`/api/auth/${provider}`, {
          maxRedirects: 0,
        })
        expect(response.status()).toBe(401)
      }
      const email2 = await withOAuthSession(
        browser,
        'castor@example.com',
        getProfileEmail
      )
      expect(email2).toBe(email1)
    }
  )

  test(
    'CeviDB and JublaDB cannot replace an OIDC session',
    { tag: '@mature' },
    async ({ browser, request }) => {
      const email1 = await withOAuthSession(
        browser,
        'castor@example.com',
        getProfileEmail
      )
      for (const provider of ['cevidb', 'jubladb']) {
        const response = await request.get(`/api/auth/${provider}`, {
          maxRedirects: 0,
        })
        expect(response.status()).toBe(401)
      }
      const email2 = await withOAuthSession(
        browser,
        'castor@example.com',
        getProfileEmail
      )
      expect(email2).toBe(email1)
    }
  )
})

test.describe('OAuth login – separate users', () => {
  test(
    'two different usernames produce two different accounts',
    { tag: '@mature' },
    async ({ browser }) => {
      const email1 = await withOAuthSession(browser, 'test@example.com', getProfileEmail)
      const email2 = await withOAuthSession(
        browser,
        'castor@example.com',
        getProfileEmail
      )
      expect(email1).not.toBe(email2)
    }
  )
})
