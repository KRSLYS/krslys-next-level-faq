'use strict';

const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const WP_PATH = process.env.WP_PATH || 'wordpress';

/**
 * Run a WP-CLI command and return trimmed output.
 */
function wp( cmd ) {
	return execSync( `wp ${ cmd } --path=${ WP_PATH }`, { encoding: 'utf-8' } ).trim();
}

// ---------------------------------------------------------------------------
// Tests: FAQ Lifecycle
// ---------------------------------------------------------------------------

test.describe( 'FAQ lifecycle', () => {
	let pageId;

	test.beforeAll( () => {
		// Create group + item via WP-CLI (db eval).
		wp( `eval '
			\\Krslys\\NextLevelFaqAccordion\\Database::create_tables( true );
			$gid = \\Krslys\\NextLevelFaqAccordion\\Groups_Repository::create_group([
				"title" => "E2E FAQ Test",
				"type"  => "faq",
			]);
			\\Krslys\\NextLevelFaqAccordion\\Repository::save_item( 0, $gid, "What is 1+1?", "It is 2.", 1, 0 );
			echo $gid;
		'` );

		// Get group ID.
		const groupId = wp( `eval '
			$groups = \\Krslys\\NextLevelFaqAccordion\\Groups_Repository::get_all_groups( null, "created_at", "DESC", "faq" );
			echo $groups[0]->id;
		'` );

		// Create a page with shortcode.
		pageId = wp( `post create --post_type=page --post_title="E2E FAQ Page" --post_status=publish --post_content='[krslys_nlfa group="${ groupId }"]' --porcelain` );
	} );

	test.afterAll( () => {
		if ( pageId ) {
			try { wp( `post delete ${ pageId } --force` ); } catch ( e ) { /* ignore */ }
		}
	} );

	test( 'FAQ container renders on the page', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		await expect( page.locator( '.nlf-faq' ) ).toBeVisible();
	} );

	test( 'question text is correct', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		await expect( page.locator( '.nlf-faq__question' ).first() ).toContainText( 'What is 1+1?' );
	} );

	test( 'click opens answer with correct text', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();

		await question.click();
		await expect( item ).toHaveClass( /is-open/ );
		await expect( page.locator( '.nlf-faq__answer' ).first() ).toContainText( 'It is 2.' );
	} );

	test( 'click again closes the answer', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();

		await question.click();
		await expect( item ).toHaveClass( /is-open/ );
		await question.click();
		await expect( item ).not.toHaveClass( /is-open/ );
	} );
} );

// ---------------------------------------------------------------------------
// Tests: Accordion Lifecycle
// ---------------------------------------------------------------------------

test.describe( 'Accordion lifecycle', () => {
	let pageId;

	test.beforeAll( () => {
		const groupId = wp( `eval '
			$gid = \\Krslys\\NextLevelFaqAccordion\\Groups_Repository::create_group([
				"title" => "E2E Accordion Test",
				"type"  => "accordion",
			]);
			\\Krslys\\NextLevelFaqAccordion\\Repository::save_item( 0, $gid, "How does it work?", "Click to expand.", 1, 0 );
			echo $gid;
		'` );

		pageId = wp( `post create --post_type=page --post_title="E2E Accordion Page" --post_status=publish --post_content='[krslys_nlfa group="${ groupId }"]' --porcelain` );
	} );

	test.afterAll( () => {
		if ( pageId ) {
			try { wp( `post delete ${ pageId } --force` ); } catch ( e ) { /* ignore */ }
		}
	} );

	test( 'Accordion container renders on the page', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		await expect( page.locator( '.nlf-faq' ) ).toBeVisible();
	} );

	test( 'item text is correct', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		await expect( page.locator( '.nlf-faq__question' ).first() ).toContainText( 'How does it work?' );
	} );

	test( 'click opens and closes content', async ( { page } ) => {
		await page.goto( `/?page_id=${ pageId }` );
		const question = page.locator( '.nlf-faq__question' ).first();
		const item = page.locator( '.nlf-faq__item' ).first();

		await question.click();
		await expect( item ).toHaveClass( /is-open/ );
		await expect( page.locator( '.nlf-faq__answer' ).first() ).toContainText( 'Click to expand.' );

		await question.click();
		await expect( item ).not.toHaveClass( /is-open/ );
	} );
} );
