'use strict';

const { test, expect } = require( '@playwright/test' );

const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'password';

/**
 * Helper: log in if session expired.
 */
async function ensureLoggedIn( page ) {
	await page.goto( '/wp-admin/' );
	if ( page.url().includes( 'wp-login.php' ) ) {
		await page.fill( '#user_login', ADMIN_USER );
		await page.fill( '#user_pass', ADMIN_PASS );
		await page.click( '#wp-submit' );
		await page.waitForURL( '**/wp-admin/**' );
	}
}

/**
 * Helper: create FAQ group via AJAX.
 */
async function createGroup( page, title, question, answer, type ) {
	await ensureLoggedIn( page );

	const groupPage = type === 'accordion'
		? '/wp-admin/admin.php?page=nlf-accordion-groups&action=new'
		: '/wp-admin/admin.php?page=nlf-faq-groups&action=new';

	await page.goto( groupPage );
	await page.waitForLoadState( 'networkidle' );

	const groupId = await page.evaluate( async ( { t, q, a, tp } ) => {
		const nonceEl = document.querySelector( '[name="nlf_faq_group_nonce"]' );
		if ( ! nonceEl ) {
			// Debug: log what's on the page so CI failures are diagnosable.
			console.error( 'Nonce field not found. Page title:', document.title, 'URL:', location.href );
			return null;
		}

		const form = new FormData();
		form.append( 'action', 'nlf_faq_save_group' );
		form.append( 'nlf_faq_group_nonce', nonceEl.value );
		form.append( 'nlf_group_title', t );
		if ( tp === 'accordion' ) form.append( 'nlf_faq_group_type', 'accordion' );
		form.append( 'nlf_faq_group_question[]', q );
		form.append( 'nlf_faq_group_answer[]', a );
		form.append( 'nlf_faq_group_status[]', '1' );
		form.append( 'nlf_faq_group_item_id[]', '0' );

		try {
			const res = await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				body: form,
			} );
			const json = await res.json();
			return json.success ? json.data.group_id : null;
		} catch ( e ) {
			return null;
		}
	}, { t: title, q: question, a: answer, tp: type || 'faq' } );

	return groupId;
}

/**
 * Helper: create a WP page with shortcode via REST API.
 */
async function createPage( page, groupId ) {
	await ensureLoggedIn( page );
	await page.waitForLoadState( 'networkidle' );

	const nonce = await page.evaluate( () =>
		window.wpApiSettings?.nonce || ''
	);

	if ( ! nonce ) return null;

	const result = await page.evaluate( async ( { n, g } ) => {
		try {
			const res = await fetch( '/index.php?rest_route=/wp/v2/pages', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': n },
				body: JSON.stringify( {
					title: 'E2E Test',
					content: `[krslys_nlfa group="${ g }"]`,
					status: 'publish',
				} ),
			} );
			const data = await res.json();
			return data.id || null;
		} catch ( e ) {
			return null;
		}
	}, { n: nonce, g: groupId } );

	return result;
}

/**
 * Helper: delete page via REST API using a fresh browser context.
 */
async function deletePageViaBrowser( browser, pageId ) {
	if ( ! pageId ) return;
	const ctx = await browser.newContext();
	const page = await ctx.newPage();
	await ensureLoggedIn( page );
	const nonce = await page.evaluate( () => window.wpApiSettings?.nonce || '' );
	if ( nonce ) {
		await page.evaluate( async ( { n, id } ) => {
			await fetch( `/index.php?rest_route=/wp/v2/pages/${ id }&force=true`, {
				method: 'DELETE',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': n },
			} );
		}, { n: nonce, id: pageId } );
	}
	await ctx.close();
}

// ---------------------------------------------------------------------------
// Tests: FAQ Lifecycle
// ---------------------------------------------------------------------------

test.describe( 'FAQ lifecycle', () => {
	let groupId;
	let pageId;

	test.afterAll( async ( { browser } ) => {
		await deletePageViaBrowser( browser, pageId );
	} );

	test( 'admin can create a FAQ group', async ( { page } ) => {
		groupId = await createGroup( page, 'E2E Test FAQ', 'What is 1+1?', 'It is 2.' );
		expect( groupId ).toBeTruthy();
	} );

	test( 'FAQ group appears in admin list', async ( { page } ) => {
		test.skip( ! groupId, 'group creation failed' );
		await page.goto( '/wp-admin/admin.php?page=nlf-faq-groups' );
		await expect( page.locator( 'text=E2E Test FAQ' ) ).toBeVisible();
	} );

	test( 'FAQ renders on frontend with correct question', async ( { page } ) => {
		test.skip( ! groupId, 'group creation failed' );
		pageId = await createPage( page, groupId );
		expect( pageId ).toBeTruthy();

		await page.goto( `/?page_id=${ pageId }` );
		await page.waitForLoadState( 'networkidle' );
		await expect( page.locator( '.nlf-faq' ) ).toBeVisible();
		await expect( page.locator( '.nlf-faq__question' ).first() ).toContainText( 'What is 1+1?' );
	} );

	test( 'click opens answer, click again closes it', async ( { page } ) => {
		test.skip( ! pageId, 'page creation failed' );
		await page.goto( `/?page_id=${ pageId }` );
		await page.waitForLoadState( 'networkidle' );

		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();
		const answer = page.locator( '.nlf-faq__answer' ).first();

		await question.click();
		await expect( item ).toHaveClass( /is-open/ );
		await expect( answer ).toContainText( 'It is 2.' );

		await question.click();
		await expect( item ).not.toHaveClass( /is-open/ );
	} );
} );

// ---------------------------------------------------------------------------
// Tests: Accordion Lifecycle
// ---------------------------------------------------------------------------

test.describe( 'Accordion lifecycle', () => {
	let groupId;
	let pageId;

	test.afterAll( async ( { browser } ) => {
		await deletePageViaBrowser( browser, pageId );
	} );

	test( 'admin can create an Accordion group', async ( { page } ) => {
		groupId = await createGroup( page, 'E2E Test Accordion', 'How does it work?', 'Click to expand.', 'accordion' );
		expect( groupId ).toBeTruthy();
	} );

	test( 'Accordion group appears in admin list', async ( { page } ) => {
		test.skip( ! groupId, 'group creation failed' );
		await page.goto( '/wp-admin/admin.php?page=nlf-accordion-groups' );
		await expect( page.locator( 'text=E2E Test Accordion' ) ).toBeVisible();
	} );

	test( 'Accordion renders on frontend with correct content', async ( { page } ) => {
		test.skip( ! groupId, 'group creation failed' );
		pageId = await createPage( page, groupId );
		expect( pageId ).toBeTruthy();

		await page.goto( `/?page_id=${ pageId }` );
		await page.waitForLoadState( 'networkidle' );
		await expect( page.locator( '.nlf-faq' ) ).toBeVisible();
		await expect( page.locator( '.nlf-faq__question' ).first() ).toContainText( 'How does it work?' );
	} );

	test( 'Accordion click opens and closes content', async ( { page } ) => {
		test.skip( ! pageId, 'page creation failed' );
		await page.goto( `/?page_id=${ pageId }` );
		await page.waitForLoadState( 'networkidle' );

		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();
		const answer = page.locator( '.nlf-faq__answer' ).first();

		await question.click();
		await expect( item ).toHaveClass( /is-open/ );
		await expect( answer ).toContainText( 'Click to expand.' );

		await question.click();
		await expect( item ).not.toHaveClass( /is-open/ );
	} );
} );
