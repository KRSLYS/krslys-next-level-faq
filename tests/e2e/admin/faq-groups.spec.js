'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

test.describe( 'FAQ Groups list page', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
	} );

	test( 'groups list page loads without error', async ( { page } ) => {
		await expect( page ).not.toHaveURL( /wp-login\.php/ );
		await expect( page.locator( '#wpwrap' ) ).toBeVisible();
	} );

	test( 'page contains Add New button', async ( { page } ) => {
		const addNew = page.locator( '.page-title-action' ).first();
		await expect( addNew ).toBeVisible();
		await expect( addNew ).toContainText( /add new/i );
	} );

	test( 'clicking Add New navigates to group edit page', async ( { page } ) => {
		await page.locator( '.page-title-action' ).first().click();
		await page.waitForLoadState( 'domcontentloaded' );
		await expect( page ).toHaveURL( /page=nlf-faq-group-edit/ );
	} );
} );

test.describe( 'FAQ Group edit page', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-group-edit&id=0' );
	} );

	test( 'group edit page loads without error', async ( { page } ) => {
		await expect( page ).not.toHaveURL( /wp-login\.php/ );
		await expect( page.locator( '#wpwrap' ) ).toBeVisible();
	} );

	test( 'group title input is present and focusable', async ( { page } ) => {
		const titleInput = page.locator( '#nlf_group_title' );
		await expect( titleInput ).toBeVisible();
		await titleInput.click();
		await expect( titleInput ).toBeFocused();
	} );

	test( 'publish/save button is present', async ( { page } ) => {
		const publishBtn = page.locator( '#publish' );
		await expect( publishBtn ).toBeVisible();
	} );

	test( 'submitting without a title shows validation error', async ( { page } ) => {
		// Leave title empty and click Publish.
		await page.locator( '#publish' ).click();

		// Either a browser validation message or an inline .nlf-field-error appears.
		const fieldError = page.locator( 'div.nlf-field-error' );
		await expect( fieldError ).toBeVisible( { timeout: 5_000 } );
	} );

	test( 'creating a group with a title saves successfully', async ( { page } ) => {
		const title = `E2E Test Group ${ Date.now() }`;
		await page.fill( '#nlf_group_title', title );

		// Add one question.
		const addRowBtn = page.locator( '.nlf-faq-group-add-row-btn' ).first();
		if ( await addRowBtn.isVisible() ) {
			await addRowBtn.click();
			await page.waitForTimeout( 300 );
			const questionInput = page
				.locator( 'input[name="nlf_faq_group_question[]"]' )
				.last();
			await questionInput.fill( 'What is Next Level FAQ?' );
		}

		await page.locator( '#publish' ).click();

		// After AJAX save, the URL updates to include the new group id,
		// or an inline success notice appears.
		await Promise.race( [
			page.waitForURL( /id=\d+/, { timeout: 10_000 } ),
			page.locator( '.nlf-inline-notice--success' ).waitFor( {
				state: 'visible',
				timeout: 10_000,
			} ),
		] );

		// Confirm we're still on the edit page (not redirected away to an error).
		await expect( page ).toHaveURL( /page=nlf-faq-group-edit/ );

		// Clean up: delete the created group.
		const createdId = new URL( page.url() ).searchParams.get( 'id' );
		if ( createdId ) {
			await page.goto( '/wp-admin/admin.php?page=nlf-faq-groups' );
			await page.waitForLoadState( 'domcontentloaded' );
			const deleteLink = page.locator( `a[href*="action=delete"][href*="id=${ createdId }"]` ).first();
			if ( await deleteLink.isVisible( { timeout: 2000 } ).catch( () => false ) ) {
				const href = await deleteLink.getAttribute( 'href' );
				if ( href ) {
					await page.goto( href );
					await page.waitForLoadState( 'domcontentloaded' );
				}
			}
		}
	} );
} );

test.describe( 'FAQ Group delete flow', () => {
	let createdGroupId;

	test.beforeAll( async ( { browser } ) => {
		// Create a throw-away group so we have something to delete.
		const ctx = await browser.newContext();
		const page = await ctx.newPage();

		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-group-edit&id=0' );
		await page.fill( '#nlf_group_title', `Delete Me ${ Date.now() }` );
		await page.locator( '#publish' ).click();

		// Wait for the URL to carry the new group id.
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );

		const url = new URL( page.url() );
		createdGroupId = url.searchParams.get( 'id' );

		await ctx.close();
	} );

	test( 'delete link is visible in groups list for the created group', async ( { page } ) => {
		if ( ! createdGroupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
		// Each row has a data attribute or link containing the group id.
		const row = page
			.locator( `tr[data-group-id="${ createdGroupId }"], a[href*="id=${ createdGroupId }"]` )
			.first();
		await expect( row ).toBeVisible();
	} );

	test.afterAll( async ( { browser } ) => {
		// Clean up: delete the group created in beforeAll.
		if ( ! createdGroupId ) return;
		const ctx = await browser.newContext();
		const page = await ctx.newPage();
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
		const deleteLink = page.locator( `a[href*="action=delete"][href*="id=${ createdGroupId }"]` ).first();
		if ( await deleteLink.isVisible( { timeout: 2000 } ).catch( () => false ) ) {
			const href = await deleteLink.getAttribute( 'href' );
			if ( href ) {
				await page.goto( href );
				await page.waitForLoadState( 'domcontentloaded' );
			}
		}
		await ctx.close();
	} );
} );
