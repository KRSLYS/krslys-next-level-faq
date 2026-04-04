// @ts-check
'use strict';

require( 'dotenv' ).config();
const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

const BASE_URL = process.env.WP_BASE_URL || 'http://wp.local';
const STORAGE_STATE_PATH = path.join( __dirname, 'tests', 'e2e', '.auth', 'admin.json' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	globalSetup: './tests/e2e/global-setup.js',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? 'github' : 'list',
	timeout: 30_000,

	use: {
		baseURL: BASE_URL,
		storageState: STORAGE_STATE_PATH,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		actionTimeout: 10_000,
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
