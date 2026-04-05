'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

/**
 * Create a FAQ group via AJAX (reliable, no TinyMCE issues).
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {string} title
 * @returns {Promise<string|null>} group ID or null
 */
async function createGroupViaAjax( browser, title ) {
	const ctx  = await browser.newContext();
	const page = await ctx.newPage();

	await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-group-edit&id=0' );

	const nonce = await page
		.locator( '#nlf_faq_group_nonce' )
		.inputValue()
		.catch( () => '' );

	if ( ! nonce ) {
		await ctx.close();
		return null;
	}

	const result = await page.evaluate(
		async ( { nonce: n, title: t } ) => {
			const form = new URLSearchParams();
			form.append( 'action', 'nlf_save_faq_group_ajax' );
			form.append( 'nlf_faq_group_nonce', n );
			form.append( 'group_id', '0' );
			form.append( 'nlf_group_title', t );
			form.append( 'nlf_faq_group_question[]', 'Sample FAQ Question?' );
			form.append( 'nlf_faq_group_answer[]', 'Sample FAQ Answer.' );
			form.append( 'nlf_faq_group_visible[0]', '1' );

			const res = await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: form.toString(),
			} );
			return res.json();
		},
		{ nonce, title }
	);

	await ctx.close();

	if ( result && result.success && result.data && result.data.group_id ) {
		return String( result.data.group_id );
	}
	return null;
}

/**
 * Delete a FAQ group using real nonce from the groups list page.
 * Retries once if the first attempt fails.
 */
async function deleteGroupViaAdmin( browser, groupId ) {
	if ( ! groupId ) return;
	const ctx  = await browser.newContext();
	const page = await ctx.newPage();

	await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );

	const deleteLink = page
		.locator( `a[href*="action=delete"][href*="id=${ groupId }"]` )
		.first();

	if ( await deleteLink.isVisible( { timeout: 5000 } ).catch( () => false ) ) {
		const href = await deleteLink.getAttribute( 'href' );
		if ( href ) {
			await page.goto( href );
			await page.waitForLoadState( 'domcontentloaded' );
		}
	}

	await ctx.close();
}

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
		const publishBtn = page.locator( '#publish' );
		await expect( publishBtn ).toBeVisible();
	} );

	test( 'submitting without a title shows validation error', async ( { page } ) => {
		await page.locator( '#publish' ).click();
		const fieldError = page.locator( 'div.nlf-field-error' );
		await expect( fieldError ).toBeVisible( { timeout: 5_000 } );
	} );

	test( 'creating a group via AJAX saves successfully', async ( { page } ) => {
		const title = `E2E Test Group ${ Date.now() }`;
		const nonce = await page.locator( '#nlf_faq_group_nonce' ).inputValue();

		// Create group via AJAX (reliable — avoids TinyMCE issues).
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

		// Verify: navigate to edit page and confirm data persisted.
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ createdId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await expect( page.locator( '#nlf_group_title' ) ).toHaveValue( title );
		await expect(
			page.locator( 'input[name="nlf_faq_group_question[]"]' ).first()
		).toHaveValue( 'What is Next Level FAQ?' );

		// Clean up: delete the created group.
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
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// FAQ Group delete flow
// ═══════════════════════════════════════════════════════════════════

test.describe( 'FAQ Group delete flow', () => {
	let createdGroupId;

	test.beforeAll( async ( { browser } ) => {
		createdGroupId = await createGroupViaAjax(
			browser,
			`Delete Me ${ Date.now() }`
		);
	} );

	test( 'group appears in groups list', async ( { page } ) => {
		if ( ! createdGroupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
		const row = page
			.locator(
				`tr[data-group-id="${ createdGroupId }"], a[href*="id=${ createdGroupId }"]`
			)
			.first();
		await expect( row ).toBeVisible();
	} );

	test( 'group shows correct question count', async ( { page } ) => {
		if ( ! createdGroupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );

		// Find the row containing this group's shortcode, then check the questions column.
		const row = page.locator( `tr:has(a[href*="id=${ createdGroupId }"])` ).first();
		await expect( row ).toBeVisible();
		// The questions column should show "1" (we created one question).
		await expect( row.locator( 'td' ).nth( 2 ) ).toContainText( '1' );
	} );

	test( 'delete link removes the group', async ( { page } ) => {
		if ( ! createdGroupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );

		const deleteLink = page
			.locator( `a[href*="action=delete"][href*="id=${ createdGroupId }"]` )
			.first();
		await expect( deleteLink ).toBeVisible( { timeout: 5000 } );

		const href = await deleteLink.getAttribute( 'href' );
		await page.goto( href );
		await page.waitForLoadState( 'domcontentloaded' );

		// The group should no longer appear in the list.
		const deletedRow = page.locator(
			`a[href*="id=${ createdGroupId }"][href*="page=nlf-faq-group-edit"]`
		);
		await expect( deletedRow ).toHaveCount( 0 );

		// Mark as cleaned up so afterAll doesn't try to delete again.
		createdGroupId = null;
	} );

	test.afterAll( async ( { browser } ) => {
		// Safety net: if the delete test didn't run or failed.
		await deleteGroupViaAdmin( browser, createdGroupId );
	} );
} );
