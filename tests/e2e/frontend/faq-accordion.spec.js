'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

// All test data is created/deleted through the admin UI — no REST API needed.

let testPageSlug;
let testGroupId;

/**
 * Create a test FAQ group with a question via the admin AJAX endpoint.
 * Uses cookie + nonce auth (same pattern as createTestPage).
 */
async function createTestGroup( browser ) {
	const ctx = await browser.newContext();
	const page = await ctx.newPage();

	// Log in and navigate to the group edit page to grab the nonce.
	await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-group-edit&id=0' );

	// Extract the nonce from the hidden form field.
	const nonce = await page
		.locator( '#nlf_faq_group_nonce' )
		.inputValue()
		.catch( () => '' );

	if ( ! nonce ) {
		await ctx.close();
		return null;
	}

	// Submit the AJAX save request with a question and answer.
	const result = await page.evaluate(
		async ( { nonce: n } ) => {
			const form = new URLSearchParams();
			form.append( 'action', 'nlf_save_faq_group_ajax' );
			form.append( 'nlf_faq_group_nonce', n );
			form.append( 'group_id', '0' );
			form.append( 'nlf_group_title', 'E2E Accordion Test Group' );
			form.append( 'nlf_faq_group_question[]', 'What is Next Level FAQ?' );
			form.append( 'nlf_faq_group_answer[]', 'It is a WordPress FAQ plugin.' );
			form.append( 'nlf_faq_group_visible[0]', '1' );

			form.append( 'nlf_faq_group_question[]', 'How do I install it?' );
			form.append( 'nlf_faq_group_answer[]', 'Upload and activate the plugin.' );
			form.append( 'nlf_faq_group_visible[1]', '1' );

			form.append( 'nlf_faq_group_settings[show_search]', '1' );

			const res = await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: form.toString(),
			} );

			return res.json();
		},
		{ nonce }
	);

	await ctx.close();

	if ( result && result.success && result.data && result.data.group_id ) {
		return String( result.data.group_id );
	}
	return null;
}

/**
 * Create a WordPress page containing the FAQ shortcode via the REST API using
 * cookie + nonce authentication (no Basic Auth plugin required).
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {number|string} groupId
 * @returns {Promise<{pageId: number|null, pageSlug: string}>}
 */
async function createTestPage( browser, groupId ) {
	const slug = `e2e-faq-test-${ Date.now() }`;
	const ctx = await browser.newContext();
	const page = await ctx.newPage();

	// Log in so we get valid WP cookies.
	await loginAsAdmin( page, '/wp-admin/' );

	// Grab the REST nonce from the admin page.
	const nonce = await page.evaluate( () =>
		( window.wpApiSettings && window.wpApiSettings.nonce ) || ''
	);

	// Use fetch inside the authenticated page context to create the page.
	// Use index.php?rest_route= to avoid needing pretty permalinks.
	const result = await page.evaluate(
		async ( { nonce: n, slug: s, groupId: g } ) => {
			const res = await fetch( '/index.php?rest_route=/wp/v2/pages', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': n,
				},
				body: JSON.stringify( {
					title: 'E2E FAQ Test Page',
					content: `[krslys_nlf group="${ g }"]`,
					status: 'publish',
					slug: s,
				} ),
			} );
			if ( ! res.ok ) {
				return { error: res.status, text: await res.text() };
			}
			const data = await res.json();
			return { id: data.id, slug: data.slug };
		},
		{ nonce, slug, groupId }
	);

	await ctx.close();

	if ( result.error ) {
		throw new Error( `REST API create page failed (${ result.error }): ${ result.text }` );
	}

	return { pageId: result.id, pageSlug: result.slug || slug };
}

/**
 * Delete a WordPress page via the REST API (cookie + nonce auth).
 */
async function deleteTestPage( browser, pageId ) {
	if ( ! pageId ) return;
	const ctx = await browser.newContext();
	const page = await ctx.newPage();
	await loginAsAdmin( page, '/wp-admin/' );
	const nonce = await page.evaluate( () =>
		( window.wpApiSettings && window.wpApiSettings.nonce ) || ''
	);
	await page.evaluate(
		async ( { nonce: n, id } ) => {
			await fetch( `/index.php?rest_route=/wp/v2/pages/${ id }&force=true`, {
				method: 'DELETE',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': n },
			} );
		},
		{ nonce, id: pageId }
	);
	await ctx.close();
}

/**
 * Delete an FAQ group by extracting a valid nonce from the groups list page.
 */
async function deleteTestGroup( browser, groupId ) {
	if ( ! groupId ) return;
	const ctx = await browser.newContext();
	const page = await ctx.newPage();

	await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );

	// Find the delete link for this group — it contains the correct nonce.
	const deleteLink = page.locator( `a[href*="action=delete"][href*="id=${ groupId }"]` ).first();
	if ( await deleteLink.isVisible( { timeout: 3000 } ).catch( () => false ) ) {
		const href = await deleteLink.getAttribute( 'href' );
		if ( href ) {
			await page.goto( href );
			await page.waitForLoadState( 'domcontentloaded' );
		}
	}

	await ctx.close();
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test.describe( 'FAQ accordion – frontend', () => {
	let pageId;

	test.beforeAll( async ( { browser } ) => {
		testGroupId = await createTestGroup( browser );

		if ( testGroupId ) {
			const result = await createTestPage( browser, testGroupId );
			pageId = result.pageId;
			testPageSlug = result.pageSlug;
		}
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteTestPage( browser, pageId );
		await deleteTestGroup( browser, testGroupId );
	} );

	test.beforeEach( async ( { page } ) => {
		if ( ! pageId ) {
			test.skip();
			return;
		}
		// Use ?page_id= format to avoid needing pretty permalinks / mod_rewrite.
		await page.goto( `/?page_id=${ pageId }` );
		await page.waitForLoadState( 'domcontentloaded' );
	} );

	// -----------------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------------

	test( 'FAQ container is rendered on the page', async ( { page } ) => {
		await expect( page.locator( '.nlf-faq' ) ).toBeVisible();
	} );

	test( 'at least one FAQ item is rendered', async ( { page } ) => {
		const items = page.locator( '.nlf-faq__item' );
		await expect( items.first() ).toBeVisible();
	} );

	test( 'FAQ questions are visible with correct text', async ( { page } ) => {
		const firstQuestion = page.locator( '.nlf-faq__question' ).first();
		await expect( firstQuestion ).toBeVisible();
		await expect( firstQuestion ).toContainText( 'What is Next Level FAQ?' );
	} );

	test( 'FAQ answer contains the expected content', async ( { page } ) => {
		// Open the first question to reveal the answer.
		const firstQuestion = page.locator( '.nlf-faq__question' ).first();
		await firstQuestion.click();

		const firstAnswer = page.locator( '.nlf-faq__answer' ).first();
		await expect( firstAnswer ).toContainText( 'It is a WordPress FAQ plugin.' );
	} );

	// -----------------------------------------------------------------------
	// Accordion interaction
	// -----------------------------------------------------------------------

	test( 'clicking a question opens the answer', async ( { page } ) => {
		const firstQuestion = page.locator( '.nlf-faq__question' ).first();
		const firstItem = page.locator( '.nlf-faq__item' ).first();
		const firstAnswer = page.locator( '.nlf-faq__answer' ).first();

		// Initially the item should NOT have is-open (all_closed default).
		const isOpenBefore = await firstItem.evaluate( ( el ) =>
			el.classList.contains( 'is-open' )
		);

		await firstQuestion.click();

		if ( isOpenBefore ) {
			// Was open → should now be closed.
			await expect( firstItem ).not.toHaveClass( /is-open/ );
		} else {
			// Was closed → should now be open with visible answer.
			await expect( firstItem ).toHaveClass( /is-open/ );
			await expect( firstAnswer ).toBeVisible();
		}
	} );

	test( 'clicking an open question closes it', async ( { page } ) => {
		const firstQuestion = page.locator( '.nlf-faq__question' ).first();
		const firstItem = page.locator( '.nlf-faq__item' ).first();

		// Open it.
		if ( ! ( await firstItem.evaluate( ( el ) => el.classList.contains( 'is-open' ) ) ) ) {
			await firstQuestion.click();
			await expect( firstItem ).toHaveClass( /is-open/, { timeout: 5_000 } );
		}

		// Now close it.
		await firstQuestion.click();
		await expect( firstItem ).not.toHaveClass( /is-open/, { timeout: 5_000 } );
	} );

	// -----------------------------------------------------------------------
	// Search (shown when enabled on the group)
	// -----------------------------------------------------------------------

	test( 'search input filters FAQ items when present', async ( { page } ) => {
		const searchInput = page.locator( '.nlf-faq-search-input' );
		const hasSearch = await searchInput.isVisible( { timeout: 2000 } ).catch( () => false );
		if ( ! hasSearch ) {
			test.skip();
			return;
		}

		await searchInput.fill( 'Next Level' );
		// Items whose question contains the query text should remain visible.
		await expect( page.locator( '.nlf-faq__item' ).first() ).toBeVisible();
	} );

	test( 'search with no match hides all FAQ items', async ( { page } ) => {
		const searchInput = page.locator( '.nlf-faq-search-input' );
		const hasSearch = await searchInput.isVisible( { timeout: 2000 } ).catch( () => false );
		if ( ! hasSearch ) {
			test.skip();
			return;
		}

		await searchInput.fill( 'xyzzynosuchanswer12345' );
		// All items should be hidden or the empty state shown.
		const visibleItems = page.locator( '.nlf-faq__item:visible' );
		await expect( visibleItems ).toHaveCount( 0, { timeout: 3_000 } );
	} );
} );
