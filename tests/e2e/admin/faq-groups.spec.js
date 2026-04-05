'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );
const {
	CLEANUP_ENABLED,
	createGroupViaAjax,
	deleteGroupViaAdmin,
} = require( '../helpers/faq-group-helpers' );

// ═══════════════════════════════════════════════════════════════════
// FAQ Groups list page
// ═══════════════════════════════════════════════════════════════════

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

// ═══════════════════════════════════════════════════════════════════
// FAQ Group edit page
// ═══════════════════════════════════════════════════════════════════

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
		await expect( page.locator( '#publish' ) ).toBeVisible();
	} );

	test( 'submitting without a title shows validation error', async ( { page } ) => {
		await page.locator( '#publish' ).click();
		await expect( page.locator( 'div.nlf-field-error' ) ).toBeVisible( { timeout: 5_000 } );
	} );

	test( 'creating a group via AJAX saves successfully', async ( { page } ) => {
		const title = `E2E Test Group ${ Date.now() }`;
		const nonce = await page.locator( '#nlf_faq_group_nonce' ).inputValue();

		const result = await page.evaluate(
			async ( { n, t } ) => {
				const form = new URLSearchParams();
				form.append( 'action', 'nlf_save_faq_group_ajax' );
				form.append( 'nlf_faq_group_nonce', n );
				form.append( 'group_id', '0' );
				form.append( 'nlf_group_title', t );
				form.append( 'nlf_faq_group_question[]', 'What is Next Level FAQ?' );
				form.append( 'nlf_faq_group_answer[]', 'It is a FAQ plugin.' );
				form.append( 'nlf_faq_group_visible[0]', '1' );

				const res = await fetch( '/wp-admin/admin-ajax.php', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: form.toString(),
				} );
				return res.json();
			},
			{ n: nonce, t: title }
		);

		expect( result.success ).toBe( true );
		expect( result.data.group_id ).toBeGreaterThan( 0 );

		const createdId = String( result.data.group_id );

		// Verify persisted data.
		await page.goto( `/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ createdId }` );
		await page.waitForLoadState( 'domcontentloaded' );
		await expect( page.locator( '#nlf_group_title' ) ).toHaveValue( title );
		await expect(
			page.locator( 'input[name="nlf_faq_group_question[]"]' ).first()
		).toHaveValue( 'What is Next Level FAQ?' );

		// Clean up.
		if ( CLEANUP_ENABLED ) {
			await page.goto( '/wp-admin/admin.php?page=nlf-faq-groups' );
			await page.waitForLoadState( 'domcontentloaded' );
			const deleteLink = page
				.locator( `a[href*="action=delete"][href*="id=${ createdId }"]` )
				.first();
			if ( await deleteLink.isVisible( { timeout: 5000 } ).catch( () => false ) ) {
				const href = await deleteLink.getAttribute( 'href' );
				if ( href ) {
					await page.goto( href );
					await page.waitForLoadState( 'domcontentloaded' );
				}
			}
		}
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// FAQ Group delete flow
// ═══════════════════════════════════════════════════════════════════

test.describe( 'FAQ Group delete flow', () => {
	let createdGroupId;

	test.beforeAll( async ( { browser } ) => {
		createdGroupId = await createGroupViaAjax( browser, {
			title: `Delete Me ${ Date.now() }`,
		} );
	} );

	test( 'group appears in groups list', async ( { page } ) => {
		if ( ! createdGroupId ) { test.skip(); return; }
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
		const row = page
			.locator( `tr[data-group-id="${ createdGroupId }"], a[href*="id=${ createdGroupId }"]` )
			.first();
		await expect( row ).toBeVisible();
	} );

	test( 'group shows correct question count', async ( { page } ) => {
		if ( ! createdGroupId ) { test.skip(); return; }
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
		const row = page.locator( `tr:has(a[href*="id=${ createdGroupId }"])` ).first();
		await expect( row ).toBeVisible();
		await expect( row.locator( 'td' ).nth( 2 ) ).toContainText( '1' );
	} );

	test( 'delete link removes the group', async ( { page } ) => {
		if ( ! createdGroupId ) { test.skip(); return; }
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );

		const deleteLink = page
			.locator( `a[href*="action=delete"][href*="id=${ createdGroupId }"]` )
			.first();
		await expect( deleteLink ).toBeVisible( { timeout: 5000 } );

		const href = await deleteLink.getAttribute( 'href' );
		await page.goto( href );
		await page.waitForLoadState( 'domcontentloaded' );

		const deletedRow = page.locator(
			`a[href*="id=${ createdGroupId }"][href*="page=nlf-faq-group-edit"]`
		);
		await expect( deletedRow ).toHaveCount( 0 );

		createdGroupId = null;
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, createdGroupId );
	} );
} );
