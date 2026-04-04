'use strict';

const WP_ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const WP_ADMIN_PASS = process.env.WP_ADMIN_PASS || 'admin';

/**
 * Log in to the WordPress admin and navigate to the given path.
 *
 * When storageState is configured in playwright.config.js, the browser context
 * already has valid cookies. This function detects that and skips the login
 * form, only navigating to the requested path.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} [redirectPath] - Admin path to visit after login, e.g. '/wp-admin/'.
 */
async function loginAsAdmin( page, redirectPath = '/wp-admin/' ) {
	// Try navigating directly — if storageState has valid cookies, this works.
	await page.goto( redirectPath );
	await page.waitForLoadState( 'domcontentloaded' );

	// If we landed on the login page, the session expired — log in manually.
	if ( page.url().includes( 'wp-login.php' ) ) {
		await page.fill( '#user_login', WP_ADMIN_USER );
		await page.fill( '#user_pass', WP_ADMIN_PASS );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.waitForLoadState( 'domcontentloaded' );

		if ( redirectPath !== '/wp-admin/' ) {
			await page.goto( redirectPath );
			await page.waitForLoadState( 'domcontentloaded' );
		}
	}
}

module.exports = { loginAsAdmin };
