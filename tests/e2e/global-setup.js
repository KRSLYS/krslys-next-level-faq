'use strict';

const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

const WP_ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const WP_ADMIN_PASS = process.env.WP_ADMIN_PASS || 'admin';
const BASE_URL = process.env.WP_BASE_URL || 'http://wp.local';
const STORAGE_STATE_PATH = path.join( __dirname, '.auth', 'admin.json' );

/**
 * Global setup: log in once and save the browser storage state (cookies)
 * so every test can reuse the session without repeating the login flow.
 */
module.exports = async function globalSetup() {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto( `${ BASE_URL }/wp-login.php` );
	await page.fill( '#user_login', WP_ADMIN_USER );
	await page.fill( '#user_pass', WP_ADMIN_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
	await page.waitForLoadState( 'domcontentloaded' );

	await page.context().storageState( { path: STORAGE_STATE_PATH } );

	await browser.close();
};
