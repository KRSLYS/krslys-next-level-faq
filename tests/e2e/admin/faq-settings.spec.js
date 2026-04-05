'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

test.describe( 'FAQ admin menu', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page, '/wp-admin/' );
	} );

	test( 'FAQs menu item is visible in admin sidebar', async ( { page } ) => {
		const menuItem = page.locator( '#adminmenu a[href*="page=nlf-faq"]' ).first();
		await expect( menuItem ).toBeVisible();
	} );

	test( 'clicking FAQs menu navigates to the dashboard page', async ( { page } ) => {
		const menuItem = page.locator( '#adminmenu a[href*="page=nlf-faq"]' ).first();
		await menuItem.click();
		await page.waitForLoadState( 'domcontentloaded' );
		await expect( page ).toHaveURL( /page=nlf-faq/ );
	} );
} );
