'use strict';

const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

const BASE_URL = process.env.WP_BASE_URL || 'http://wp.local';
const ADMIN_USER = process.env.WP_ADMIN_USER || 'root';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'root';
const AUTH_FILE = path.join( __dirname, '.auth', 'admin.json' );

module.exports = async function globalSetup() {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto( `${ BASE_URL }/wp-login.php` );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( '**/wp-admin/**' );

	await page.context().storageState( { path: AUTH_FILE } );
	await browser.close();
};
