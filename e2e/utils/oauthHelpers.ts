import { type Page, type Browser } from '@playwright/test'

export async function loginWithOAuth(
  page: Page,
  username: string = 'test@example.com'
): Promise<void> {
  await page.goto('/login')
  await page.locator('.ec-login-button').click()
  await page.locator(`form:has(input[value*="${username}"]) button`).click()
  await page.waitForURL('/camps', { timeout: 30_000 })
}

export async function withOAuthSession<T>(
  browser: Browser,
  username: string,
  fn: (page: Page) => Promise<T>
): Promise<T> {
  const ctx = await browser.newContext()
  const page = await ctx.newPage()
  try {
    await loginWithOAuth(page, username)
    return await fn(page)
  } finally {
    await ctx.close()
  }
}

export async function getProfileEmail(page: Page): Promise<string> {
  await page.goto('/profile')
  return page.locator('.e-profile--email input').inputValue()
}
