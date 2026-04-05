'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

/**
 * Helper: create a FAQ group via AJAX with full data (question + answer).
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {object} [opts]
 * @param {string} [opts.title]
 * @param {string} [opts.question]
 * @param {string} [opts.answer]
 * @returns {Promise<string|null>} group ID or null
 */
async function createGroupViaAjax( browser, opts = {} ) {
	const title    = opts.title    || `E2E Edit Test ${ Date.now() }`;
	const question = opts.question || 'Test Question?';
	const answer   = opts.answer   || 'Test Answer.';

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
		async ( { nonce: n, title: t, question: q, answer: a } ) => {
			const form = new URLSearchParams();
			form.append( 'action', 'nlf_save_faq_group_ajax' );
			form.append( 'nlf_faq_group_nonce', n );
			form.append( 'group_id', '0' );
			form.append( 'nlf_group_title', t );
			form.append( 'nlf_faq_group_question[]', q );
			form.append( 'nlf_faq_group_answer[]', a );
			form.append( 'nlf_faq_group_visible[0]', '1' );

			const res = await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: form.toString(),
			} );
			return res.json();
		},
		{ nonce, title, question, answer }
	);

	await ctx.close();

	if ( result && result.success && result.data && result.data.group_id ) {
		return String( result.data.group_id );
	}
	return null;
}

/**
 * Helper: delete a FAQ group using real nonce from the groups list page.
 */
async function deleteGroupViaAdmin( browser, groupId ) {
	if ( ! groupId ) return;
	const ctx  = await browser.newContext();
	const page = await ctx.newPage();

	await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-groups' );
	const deleteLink = page
		.locator( `a[href*="action=delete"][href*="id=${ groupId }"]` )
		.first();

	if ( await deleteLink.isVisible( { timeout: 3000 } ).catch( () => false ) ) {
		const href = await deleteLink.getAttribute( 'href' );
		if ( href ) {
			await page.goto( href );
			await page.waitForLoadState( 'domcontentloaded' );
		}
	}
	await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Content Tab
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Content Tab', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Content Tests ${ Date.now() }`,
			question: 'Original Question?',
			answer: 'Original Answer.',
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	test.beforeEach( async ( { page } ) => {
		if ( ! groupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
	} );

	// ---------------------------------------------------------------
	// Page structure
	// ---------------------------------------------------------------

	test( 'edit page loads with correct group title', async ( { page } ) => {
		const title = page.locator( '#nlf_group_title' );
		await expect( title ).toBeVisible();
		await expect( title ).toHaveValue( /Content Tests/ );
	} );

	test( 'Content, Appearance, and Preview tabs are visible', async ( { page } ) => {
		for ( const label of [ 'Content', 'Appearance', 'Preview' ] ) {
			await expect(
				page.locator( `.nlf-faq-tab-button:has-text("${ label }")` ).first()
			).toBeVisible();
		}
	} );

	test( 'Publish/Update button is present', async ( { page } ) => {
		await expect( page.locator( '#publish' ) ).toBeVisible();
	} );

	test( 'shortcode box shows the correct group id', async ( { page } ) => {
		const sidebar = page.locator( '#nlf-how-to-use-box' );
		await expect( sidebar ).toBeVisible();
		await expect( sidebar ).toContainText( `group="${ groupId }"` );
	} );

	// ---------------------------------------------------------------
	// FAQ item — question & answer
	// ---------------------------------------------------------------

	test( 'existing question is displayed', async ( { page } ) => {
		const question = page
			.locator( 'input[name="nlf_faq_group_question[]"]' )
			.first();
		await expect( question ).toHaveValue( 'Original Question?' );
	} );

	test( 'existing answer is displayed', async ( { page } ) => {
		const answer = page
			.locator( 'textarea[name="nlf_faq_group_answer[]"]' )
			.first();
		await expect( answer ).toHaveValue( 'Original Answer.' );
	} );

	// ---------------------------------------------------------------
	// FAQ item — options checkboxes
	// ---------------------------------------------------------------

	test( 'Show checkbox is checked by default', async ( { page } ) => {
		const show = page
			.locator( 'input[name="nlf_faq_group_visible[0]"]' )
			.first();
		await expect( show ).toBeChecked();
	} );

	test( 'Open by default checkbox is unchecked by default', async ( { page } ) => {
		const open = page
			.locator( 'input[name="nlf_faq_group_open[0]"]' )
			.first();
		await expect( open ).not.toBeChecked();
	} );

	test( 'Highlight checkbox is unchecked by default', async ( { page } ) => {
		const highlight = page
			.locator( 'input[name="nlf_faq_group_highlight[0]"]' )
			.first();
		await expect( highlight ).not.toBeChecked();
	} );

	// ---------------------------------------------------------------
	// Add / remove FAQ items
	// ---------------------------------------------------------------

	test( 'Add Question button adds a new empty row', async ( { page } ) => {
		const before = await page
			.locator( 'input[name="nlf_faq_group_question[]"]' )
			.count();

		await page.locator( '.nlf-faq-group-add-row-btn' ).first().click();
		await page.waitForTimeout( 400 );

		const after = await page
			.locator( 'input[name="nlf_faq_group_question[]"]' )
			.count();

		expect( after ).toBe( before + 1 );
	} );

	test( 'remove button deletes a row', async ( { page } ) => {
		// Add a row first.
		await page.locator( '.nlf-faq-group-add-row-btn' ).first().click();
		await page.waitForTimeout( 400 );

		const before = await page
			.locator( 'input[name="nlf_faq_group_question[]"]' )
			.count();

		// Click remove on the last row.
		const removeBtn = page.locator( '.nlf-faq-remove-row' ).last();
		await removeBtn.click();
		await page.waitForTimeout( 400 );

		const after = await page
			.locator( 'input[name="nlf_faq_group_question[]"]' )
			.count();

		expect( after ).toBe( before - 1 );
	} );

	// ---------------------------------------------------------------
	// Save and verify persistence
	// ---------------------------------------------------------------

	test( 'editing question and answer persists after save', async ( { page } ) => {
		// Update question via input.
		const questionInput = page
			.locator( 'input[name="nlf_faq_group_question[]"]' )
			.first();
		await questionInput.fill( 'Updated Question?' );

		// Update answer via textarea.
		const answerTextarea = page
			.locator( 'textarea[name="nlf_faq_group_answer[]"]' )
			.first();
		await answerTextarea.fill( 'Updated Answer.' );

		// Save.
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		// Verify question persisted.
		await expect(
			page.locator( 'input[name="nlf_faq_group_question[]"]' ).first()
		).toHaveValue( 'Updated Question?' );

		// Verify answer persisted.
		await expect(
			page.locator( 'textarea[name="nlf_faq_group_answer[]"]' ).first()
		).toHaveValue( 'Updated Answer.' );
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Behavior & Display Settings
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Behavior & Display Settings', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Settings Tests ${ Date.now() }`,
			question: 'Settings Q?',
			answer: 'Settings A.',
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	test.beforeEach( async ( { page } ) => {
		if ( ! groupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
	} );

	// ---------------------------------------------------------------
	// Default values
	// ---------------------------------------------------------------

	test( 'Accordion Mode is unchecked by default', async ( { page } ) => {
		await expect( page.locator( '#setting_accordion_mode' ) ).not.toBeChecked();
	} );

	test( 'Initial State defaults to "All Closed"', async ( { page } ) => {
		await expect( page.locator( '#setting_initial_state' ) ).toHaveValue(
			'all_closed'
		);
	} );

	test( 'Animation Speed defaults to "Normal"', async ( { page } ) => {
		await expect( page.locator( '#setting_animation_speed' ) ).toHaveValue(
			'normal'
		);
	} );

	test( 'Search Box is unchecked by default', async ( { page } ) => {
		await expect( page.locator( '#setting_show_search' ) ).not.toBeChecked();
	} );

	test( 'Item Counter is unchecked by default', async ( { page } ) => {
		await expect( page.locator( '#setting_show_counter' ) ).not.toBeChecked();
	} );

	test( 'Smooth Scroll is checked by default', async ( { page } ) => {
		await expect( page.locator( '#setting_smooth_scroll' ) ).toBeChecked();
	} );

	// ---------------------------------------------------------------
	// Initial State dropdown options
	// ---------------------------------------------------------------

	test( 'Initial State has correct dropdown options', async ( { page } ) => {
		const options = page.locator( '#setting_initial_state option' );
		await expect( options ).toHaveCount( 3 );
		await expect( options.nth( 0 ) ).toHaveAttribute( 'value', 'all_closed' );
		await expect( options.nth( 1 ) ).toHaveAttribute( 'value', 'first_open' );
		await expect( options.nth( 2 ) ).toHaveAttribute( 'value', 'custom' );
	} );

	// ---------------------------------------------------------------
	// Animation Speed dropdown options
	// ---------------------------------------------------------------

	test( 'Animation Speed has correct dropdown options', async ( { page } ) => {
		const options = page.locator( '#setting_animation_speed option' );
		await expect( options ).toHaveCount( 3 );
		await expect( options.nth( 0 ) ).toHaveAttribute( 'value', 'fast' );
		await expect( options.nth( 1 ) ).toHaveAttribute( 'value', 'normal' );
		await expect( options.nth( 2 ) ).toHaveAttribute( 'value', 'slow' );
	} );

	// ---------------------------------------------------------------
	// Settings save & persist
	// ---------------------------------------------------------------

	test( 'enabling Accordion Mode persists after save', async ( { page } ) => {
		await page.locator( '#setting_accordion_mode' ).check();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await expect( page.locator( '#setting_accordion_mode' ) ).toBeChecked();
	} );

	test( 'changing Initial State to "First Item Open" persists', async ( { page } ) => {
		await page.locator( '#setting_initial_state' ).selectOption( 'first_open' );
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await expect( page.locator( '#setting_initial_state' ) ).toHaveValue(
			'first_open'
		);
	} );

	test( 'changing Animation Speed to "Fast" persists', async ( { page } ) => {
		await page.locator( '#setting_animation_speed' ).selectOption( 'fast' );
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await expect( page.locator( '#setting_animation_speed' ) ).toHaveValue(
			'fast'
		);
	} );

	test( 'enabling Search Box persists after save', async ( { page } ) => {
		await page.locator( '#setting_show_search' ).check();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await expect( page.locator( '#setting_show_search' ) ).toBeChecked();
	} );

	test( 'enabling Item Counter persists after save', async ( { page } ) => {
		await page.locator( '#setting_show_counter' ).check();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await expect( page.locator( '#setting_show_counter' ) ).toBeChecked();
	} );

	test( 'disabling Smooth Scroll persists after save', async ( { page } ) => {
		await page.locator( '#setting_smooth_scroll' ).uncheck();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await expect( page.locator( '#setting_smooth_scroll' ) ).not.toBeChecked();
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Appearance Tab (Theme Selection)
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Appearance Tab', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Theme Tests ${ Date.now() }`,
			question: 'Theme Q?',
			answer: 'Theme A.',
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	test.beforeEach( async ( { page } ) => {
		if ( ! groupId ) {
			test.skip();
			return;
		}
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		// Switch to Appearance tab.
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );
	} );

	test( 'Appearance tab shows theme radio buttons', async ( { page } ) => {
		const themes = page.locator( 'input[name="nlf_faq_group_theme"]' );
		await expect( themes ).toHaveCount( 6 );
	} );

	test( 'default theme is selected by default', async ( { page } ) => {
		await expect( page.locator( '#theme_default' ) ).toBeChecked();
	} );

	test( 'all 6 theme options are present', async ( { page } ) => {
		for ( const id of [
			'theme_default',
			'theme_modern',
			'theme_elegant',
			'theme_minimal',
			'theme_bold',
			'theme_professional',
		] ) {
			await expect( page.locator( `#${ id }` ) ).toBeVisible();
		}
	} );

	test( 'selecting "modern" theme persists after save', async ( { page } ) => {
		await page.locator( 'label[for="theme_modern"]' ).click();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		// Switch back to Appearance tab to verify.
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_modern' ) ).toBeChecked();
	} );

	test( 'selecting "elegant" theme persists after save', async ( { page } ) => {
		await page.locator( 'label[for="theme_elegant"]' ).click();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_elegant' ) ).toBeChecked();
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Validation
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Validation', () => {
	test( 'saving with empty title shows validation error', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		// Leave title empty, click Publish.
		await page.locator( '#publish' ).click();
		await expect( page.locator( 'div.nlf-field-error' ) ).toBeVisible( {
			timeout: 5_000,
		} );
	} );

	test( 'title with only whitespace shows validation error', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		await page.fill( '#nlf_group_title', '   ' );
		await page.locator( '#publish' ).click();
		await expect( page.locator( 'div.nlf-field-error' ) ).toBeVisible( {
			timeout: 5_000,
		} );
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Multiple FAQ Items
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Multiple FAQ Items', () => {
	let groupId;

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	test( 'creating a group with multiple items saves all items', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		const nonce = await page.locator( '#nlf_faq_group_nonce' ).inputValue();

		// Use AJAX to create group with 3 items.
		const result = await page.evaluate(
			async ( { n } ) => {
				const form = new URLSearchParams();
				form.append( 'action', 'nlf_save_faq_group_ajax' );
				form.append( 'nlf_faq_group_nonce', n );
				form.append( 'group_id', '0' );
				form.append( 'nlf_group_title', 'Multi Item Test' );

				form.append( 'nlf_faq_group_question[]', 'First Question?' );
				form.append( 'nlf_faq_group_answer[]', 'First Answer.' );
				form.append( 'nlf_faq_group_visible[0]', '1' );

				form.append( 'nlf_faq_group_question[]', 'Second Question?' );
				form.append( 'nlf_faq_group_answer[]', 'Second Answer.' );
				form.append( 'nlf_faq_group_visible[1]', '1' );

				form.append( 'nlf_faq_group_question[]', 'Third Question?' );
				form.append( 'nlf_faq_group_answer[]', 'Third Answer.' );
				form.append( 'nlf_faq_group_visible[2]', '1' );

				const res = await fetch( '/wp-admin/admin-ajax.php', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: form.toString(),
				} );
				return res.json();
			},
			{ n: nonce }
		);

		expect( result.success ).toBe( true );
		groupId = String( result.data.group_id );

		// Navigate to the edit page and verify all 3 items.
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );

		const questions = page.locator( 'input[name="nlf_faq_group_question[]"]' );
		await expect( questions ).toHaveCount( 3 );
		await expect( questions.nth( 0 ) ).toHaveValue( 'First Question?' );
		await expect( questions.nth( 1 ) ).toHaveValue( 'Second Question?' );
		await expect( questions.nth( 2 ) ).toHaveValue( 'Third Question?' );

		const answers = page.locator( 'textarea[name="nlf_faq_group_answer[]"]' );
		await expect( answers ).toHaveCount( 3 );
		await expect( answers.nth( 0 ) ).toHaveValue( 'First Answer.' );
		await expect( answers.nth( 1 ) ).toHaveValue( 'Second Answer.' );
		await expect( answers.nth( 2 ) ).toHaveValue( 'Third Answer.' );
	} );
} );
