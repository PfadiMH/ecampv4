import { test, expect } from '@playwright/test'
import { loginAndSetCookie } from '@/utils/helpers'
import { castorUser } from '@/utils/constants'

const originalPassword = 'test'
const newPassword = 'new-password-test'
const restorePassword = 'testtest1234'

test('can change the password from the profile page', async ({ page, request }) => {
  await loginAndSetCookie(page, request, castorUser, originalPassword)

  await page.goto('/profile')
  await expect(page.locator('body')).toContainText('Profil:')

  await page
    .locator('.e-profile--password')
    .getByRole('button', { name: /Ändern/ })
    .click()
  const dialog = page.locator('.v-dialog.v-overlay--active')
  await expect(dialog).toContainText('Passwort ändern')

  // fill in the form: current password, new password, confirmation
  const passwordInputs = dialog.locator('input[type="password"]')
  await passwordInputs.nth(0).fill(originalPassword)
  await passwordInputs.nth(1).fill(newPassword)
  await passwordInputs.nth(2).fill(newPassword)

  await dialog.getByRole('button', { name: /Abschicken/ }).click()

  await expect(dialog).toContainText('Dein Passwort wurde erfolgreich geändert.')

  await dialog.getByRole('button', { name: /Schliessen/ }).click()

  // Login is OIDC-only in this fork, so the new password cannot be verified by logging
  // out and in through a password form (there is none). Instead, the restore step below
  // must authenticate the change with the new password as the current password, which
  // only succeeds if the new password was actually stored.
  await page.goto('/profile')
  await page
    .locator('.e-profile--password')
    .getByRole('button', { name: /Ändern/ })
    .click()
  const restoreDialog = page.locator('.v-dialog.v-overlay--active')
  await expect(restoreDialog).toContainText('Passwort ändern')

  const restoreInputs = restoreDialog.locator('input[type="password"]')
  await restoreInputs.nth(0).fill(newPassword)
  await restoreInputs.nth(1).fill(restorePassword)
  await restoreInputs.nth(2).fill(restorePassword)

  await restoreDialog.getByRole('button', { name: /Abschicken/ }).click()
  await expect(restoreDialog).toContainText('Dein Passwort wurde erfolgreich geändert.')
})
