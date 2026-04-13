'use strict';

const { test, expect } = require( '@playwright/test' );

const BASE_URL = process.env.WP_BASE_URL || 'http://wp.local';
const ADMIN_USER = process.env.WP_ADMIN_USER || 'root';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'root';

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
 * Helper: create FAQ group via AJAX (same method the admin UI uses).
 */
async function createGroup( page, title, question, answer ) {
	await ensureLoggedIn( page );
	await page.goto( '/wp-admin/admin.php?page=nlf-faq-groups&action=new' );

	const groupId = await page.evaluate( async ( { t, q, a } ) => {
		const form = new FormData();
		form.append( 'action', 'nlf_faq_save_group' );
		form.append( 'nlf_faq_group_nonce', document.querySelector( '[name="nlf_faq_group_nonce"]' )?.value || '' );
		form.append( 'nlf_group_title', t );
		form.append( 'nlf_faq_group_question[]', q );
		form.append( 'nlf_faq_group_answer[]', a );
		form.append( 'nlf_faq_group_status[]', '1' );
		form.append( 'nlf_faq_group_item_id[]', '0' );

		const res = await fetch( '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			body: form,
		} );
		const json = await res.json();
		return json.success ? json.data.group_id : null;
	}, { t: title, q: question, a: answer } );

	return groupId;
}

/**
 * Helper: create a WP page with shortcode via REST API.
 */
async function createPage( page, groupId ) {
	await ensureLoggedIn( page );

	const nonce = await page.evaluate( () =>
		window.wpApiSettings?.nonce || ''
	);

	const result = await page.evaluate( async ( { n, g } ) => {
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
	}, { n: nonce, g: groupId } );

	return result;
}

/**
 * Helper: delete page via REST API.
 */
async function deletePage( page, pageId ) {
	if ( ! pageId ) return;
	await ensureLoggedIn( page );
	const nonce = await page.evaluate( () => window.wpApiSettings?.nonce || '' );
	await page.evaluate( async ( { n, id } ) => {
		await fetch( `/index.php?rest_route=/wp/v2/pages/${ id }&force=true`, {
			method: 'DELETE',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': n },
		} );
	}, { n: nonce, id: pageId } );
}

// ---------------------------------------------------------------------------
// Tests: FAQ Full Lifecycle
// ---------------------------------------------------------------------------

test.describe( 'FAQ lifecycle', () => {
	let groupId;
	let pageId;

	test.afterAll( async ( { page } ) => {
		await deletePage( page, pageId );
	} );

	test( 'admin can create a FAQ group', async ( { page } ) => {
		groupId = await createGroup( page, 'E2E Test FAQ', 'What is 1+1?', 'It is 2.' );
		expect( groupId ).toBeTruthy();
	} );

	test( 'FAQ group appears in admin list', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=nlf-faq-groups' );
		await expect( page.locator( 'text=E2E Test FAQ' ) ).toBeVisible();
	} );

	test( 'FAQ renders on frontend page', async ( { page } ) => {
		pageId = await createPage( page, groupId );
		expect( pageId ).toBeTruthy();

		await page.goto( `/?page_id=${ pageId }` );
		await expect( page.locator( '.nlf-faq' ) ).toBeVisible();
		await expect( page.locator( '.nlf-faq__question' ).first() ).toContainText( 'What is 1+1?' );
	} );

	test( 'clicking question shows answer', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );

		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();

		await question.click();
		await expect( item ).toHaveClass( /is-open/ );

		const answer = page.locator( '.nlf-faq__answer' ).first();
		await expect( answer ).toContainText( 'It is 2.' );
	} );

	test( 'clicking again closes the answer', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );

		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();

		await question.click(); // open
		await expect( item ).toHaveClass( /is-open/ );

		await question.click(); // close
		await expect( item ).not.toHaveClass( /is-open/ );
	} );
} );
