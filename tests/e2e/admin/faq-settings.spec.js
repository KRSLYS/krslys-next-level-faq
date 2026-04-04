'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

test.describe( 'FAQ Style & Layout settings page', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-style' );
	} );

	// -----------------------------------------------------------------------
	// Page structure
	// -----------------------------------------------------------------------

	test( 'settings page loads without error', async ( { page } ) => {
		await expect( page ).not.toHaveURL( /wp-login\.php/ );
		await expect( page.locator( '#wpwrap' ) ).toBeVisible();
	} );

	test( 'style settings form is present', async ( { page } ) => {
		await expect( page.locator( '#nlf-faq-style-form' ) ).toBeVisible();
	} );

	test( 'save button is present and enabled', async ( { page } ) => {
		const saveBtn = page.locator( '#nlf-faq-style-form [type="submit"]' ).first();
		await expect( saveBtn ).toBeVisible();
		await expect( saveBtn ).toBeEnabled();
	} );

	test( 'page title contains Style', async ( { page } ) => {
		const heading = page.locator( '.wrap h1, .wrap h2' ).first();
		await expect( heading ).toContainText( /style/i );
	} );

	// -----------------------------------------------------------------------
	// Preset selector
	// -----------------------------------------------------------------------

	test( 'preset options are present', async ( { page } ) => {
		// There should be at least one preset radio or select option.
		const presetInputs = page.locator(
			'input[name*="preset"], select[name*="preset"], input[name*="active_preset"]'
		);
		await expect( presetInputs.first() ).toBeVisible();
	} );

	// -----------------------------------------------------------------------
	// AJAX save
	// -----------------------------------------------------------------------

	test( 'AJAX save shows success notice', async ( { page } ) => {
		// Click the first submit button inside the style form.
		const saveBtn = page.locator( '#nlf-faq-style-form [type="submit"]' ).first();
		await saveBtn.click();

		// The JS inserts a .nlf-ajax-notice after a successful AJAX call.
		const notice = page.locator( '.nlf-ajax-notice' );
		await expect( notice ).toBeVisible( { timeout: 10_000 } );
		await expect( notice ).toHaveClass( /notice-success/ );
	} );
} );

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
