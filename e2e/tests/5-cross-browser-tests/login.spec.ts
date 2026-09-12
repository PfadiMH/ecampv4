import { test, expect } from '@playwright/test'
import { loginAndSetCookie, mockDateNow } from '@/utils/helpers'
import { bipiUser } from '@/utils/constants'

test.describe('Login test', { tag: '@mature' }, () => {
  test.beforeEach(async ({ page }) => {
    await mockDateNow(page)
  })

  test('displays the login page', async ({ page }) => {
    // '/' redirects unauthenticated users to the OIDC-only login page.
    await page.goto('/')
    await expect(page.locator('.ec-login-button')).toBeVisible()
  })

  test('can login with default user', async ({ page }) => {
    await loginAndSetCookie(page, null, bipiUser)

    await expect(page.locator('body')).toContainText('Meine Lager')
    await expect(page.locator('body')).toContainText('GRGR')
    await expect(page.locator('body')).toContainText('Harry Potter Lager')
  })
})
