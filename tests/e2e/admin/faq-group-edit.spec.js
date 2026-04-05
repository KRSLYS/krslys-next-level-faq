'use strict';

const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );
const {
	createGroupViaAjax,
	deleteGroupViaAdmin,
} = require( '../helpers/faq-group-helpers' );

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
		// Switch to Settings tab.
		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
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

		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '#setting_accordion_mode' ) ).toBeChecked();
	} );

	test( 'changing Initial State to "First Item Open" persists', async ( { page } ) => {
		await page.locator( '#setting_initial_state' ).selectOption( 'first_open' );
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '#setting_initial_state' ) ).toHaveValue(
			'first_open'
		);
	} );

	test( 'changing Animation Speed to "Fast" persists', async ( { page } ) => {
		await page.locator( '#setting_animation_speed' ).selectOption( 'fast' );
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '#setting_animation_speed' ) ).toHaveValue(
			'fast'
		);
	} );

	test( 'enabling Search Box persists after save', async ( { page } ) => {
		await page.locator( '#setting_show_search' ).check();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '#setting_show_search' ) ).toBeChecked();
	} );

	test( 'enabling Item Counter persists after save', async ( { page } ) => {
		await page.locator( '#setting_show_counter' ).check();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '#setting_show_counter' ) ).toBeChecked();
	} );

	test( 'disabling Smooth Scroll persists after save', async ( { page } ) => {
		await page.locator( '#setting_smooth_scroll' ).uncheck();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
		await page.waitForTimeout( 300 );
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

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Theme Presets (all 6 themes)
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Theme Presets', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Theme Presets ${ Date.now() }`,
			question: 'Theme Preset Q?',
			answer: 'Theme Preset A.',
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
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );
	} );

	for ( const theme of [ 'default', 'modern', 'elegant', 'minimal', 'bold', 'professional' ] ) {
		test( `selecting "${ theme }" theme persists after save`, async ( { page } ) => {
			await page.locator( `label[for="theme_${ theme }"]` ).click();
			await page.locator( '#publish' ).click();
			await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
			await page.waitForLoadState( 'domcontentloaded' );

			await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
			await page.waitForTimeout( 300 );

			await expect( page.locator( `#theme_${ theme }` ) ).toBeChecked();
		} );
	}
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Customize Colors (4 color pickers)
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Customize Colors', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Color Tests ${ Date.now() }`,
			question: 'Color Q?',
			answer: 'Color A.',
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
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );
	} );

	test( 'all 4 theme color picker inputs are present', async ( { page } ) => {
		for ( const key of [ 'primary', 'secondary', 'accent', 'background' ] ) {
			await expect(
				page.locator( `#theme_custom_${ key }` )
			).toBeAttached();
		}
	} );

	test( 'setting Primary color via AJAX persists', async ( { page } ) => {
		groupId = await saveColorViaAjax( page, groupId, { primary: '#ff0000' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_custom_primary' ) ).toHaveValue( '#ff0000' );
	} );

	test( 'setting Secondary color via AJAX persists', async ( { page } ) => {
		groupId = await saveColorViaAjax( page, groupId, { secondary: '#00ff00' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_custom_secondary' ) ).toHaveValue( '#00ff00' );
	} );

	test( 'setting Accent color via AJAX persists', async ( { page } ) => {
		groupId = await saveColorViaAjax( page, groupId, { accent: '#0000ff' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_custom_accent' ) ).toHaveValue( '#0000ff' );
	} );

	test( 'setting Background color via AJAX persists', async ( { page } ) => {
		groupId = await saveColorViaAjax( page, groupId, { background: '#ffff00' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_custom_background' ) ).toHaveValue( '#ffff00' );
	} );

	test( 'setting all 4 colors at once persists', async ( { page } ) => {
		groupId = await saveColorViaAjax( page, groupId, {
			primary: '#111111',
			secondary: '#222222',
			accent: '#333333',
			background: '#444444',
		} );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#theme_custom_primary' ) ).toHaveValue( '#111111' );
		await expect( page.locator( '#theme_custom_secondary' ) ).toHaveValue( '#222222' );
		await expect( page.locator( '#theme_custom_accent' ) ).toHaveValue( '#333333' );
		await expect( page.locator( '#theme_custom_background' ) ).toHaveValue( '#444444' );
	} );
} );

/**
 * Helper: save theme custom colors via AJAX on the current page context.
 * Returns the group ID (unchanged).
 */
async function saveColorViaAjax( page, gId, colors ) {
	const nonce = await page.locator( '#nlf_faq_group_nonce' ).inputValue();
	const title = await page.locator( '#nlf_group_title' ).inputValue();

	await page.evaluate(
		async ( { n, id, t, colors: c } ) => {
			const form = new URLSearchParams();
			form.append( 'action', 'nlf_save_faq_group_ajax' );
			form.append( 'nlf_faq_group_nonce', n );
			form.append( 'group_id', id );
			form.append( 'nlf_group_title', t );
			form.append( 'nlf_faq_group_question[]', 'Color Q?' );
			form.append( 'nlf_faq_group_answer[]', 'Color A.' );
			form.append( 'nlf_faq_group_visible[0]', '1' );

			for ( const [ key, val ] of Object.entries( c ) ) {
				form.append( `nlf_faq_group_theme_custom[${ key }]`, val );
			}

			await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: form.toString(),
			} );
		},
		{ n: nonce, id: gId, t: title, colors }
	);

	return gId;
}

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Advanced Style Overrides
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Advanced Style Overrides', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Style Override ${ Date.now() }`,
			question: 'Style Q?',
			answer: 'Style A.',
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
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );
	} );

	// ---------------------------------------------------------------
	// Toggle
	// ---------------------------------------------------------------

	test( 'custom style toggle is present and unchecked by default', async ( { page } ) => {
		const toggle = page.locator( '#nlf-use-custom-style-toggle' );
		await expect( toggle ).toBeAttached();
		await expect( toggle ).not.toBeChecked();
	} );

	test( 'enabling custom style toggle shows the style fields', async ( { page } ) => {
		const toggle = page.locator( '#nlf-use-custom-style-toggle' );
		const fields = page.locator( '.nlf-custom-style-fields' );

		// Fields should be hidden initially.
		await expect( fields ).toBeHidden();

		// Enable the toggle.
		await toggle.check();

		// Fields should now be visible.
		await expect( fields ).toBeVisible();
	} );

	// ---------------------------------------------------------------
	// Custom style fields — presence
	// ---------------------------------------------------------------

	test( 'all custom style fields are present when toggle is enabled', async ( { page } ) => {
		await page.locator( '#nlf-use-custom-style-toggle' ).check();
		await page.waitForTimeout( 200 );

		// Container fields.
		await expect( page.locator( '#custom_container_background' ) ).toBeAttached();
		await expect( page.locator( '#custom_container_border_color' ) ).toBeAttached();
		await expect( page.locator( '#custom_container_border_radius' ) ).toBeAttached();
		await expect( page.locator( '#custom_container_padding' ) ).toBeAttached();

		// Question fields.
		await expect( page.locator( '#custom_question_color' ) ).toBeAttached();
		await expect( page.locator( '#custom_question_font_size' ) ).toBeAttached();

		// Answer fields.
		await expect( page.locator( '#custom_answer_color' ) ).toBeAttached();
		await expect( page.locator( '#custom_answer_font_size' ) ).toBeAttached();

		// Accent & animation.
		await expect( page.locator( '#custom_accent_color' ) ).toBeAttached();
		await expect( page.locator( '#custom_icon_style' ) ).toBeAttached();
		await expect( page.locator( '#custom_animation' ) ).toBeAttached();
	} );

	// ---------------------------------------------------------------
	// Icon Style select options
	// ---------------------------------------------------------------

	test( 'Icon Style select has correct options', async ( { page } ) => {
		await page.locator( '#nlf-use-custom-style-toggle' ).check();
		await page.waitForTimeout( 200 );

		const options = page.locator( '#custom_icon_style option' );
		await expect( options ).toHaveCount( 2 );
		await expect( options.nth( 0 ) ).toHaveAttribute( 'value', 'plus_minus' );
		await expect( options.nth( 1 ) ).toHaveAttribute( 'value', 'chevron' );
	} );

	// ---------------------------------------------------------------
	// Animation select options
	// ---------------------------------------------------------------

	test( 'Animation select has correct options', async ( { page } ) => {
		await page.locator( '#nlf-use-custom-style-toggle' ).check();
		await page.waitForTimeout( 200 );

		const options = page.locator( '#custom_animation option' );
		await expect( options ).toHaveCount( 3 );
		await expect( options.nth( 0 ) ).toHaveAttribute( 'value', 'slide' );
		await expect( options.nth( 1 ) ).toHaveAttribute( 'value', 'fade' );
		await expect( options.nth( 2 ) ).toHaveAttribute( 'value', 'none' );
	} );

	// ---------------------------------------------------------------
	// Save & persist — toggle state
	// ---------------------------------------------------------------

	test( 'enabling custom style toggle persists after save', async ( { page } ) => {
		await page.locator( '#nlf-use-custom-style-toggle' ).check();
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#nlf-use-custom-style-toggle' ) ).toBeChecked();
	} );

	// ---------------------------------------------------------------
	// Save & persist — Container styles via AJAX
	// ---------------------------------------------------------------

	test( 'container background color persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { container_background: '#abcdef' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_container_background' ) ).toHaveValue( '#abcdef' );
	} );

	test( 'container border color persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { container_border_color: '#123456' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_container_border_color' ) ).toHaveValue( '#123456' );
	} );

	test( 'container border radius persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { container_border_radius: '12' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_container_border_radius' ) ).toHaveValue( '12' );
	} );

	test( 'container padding persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { container_padding: '20' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_container_padding' ) ).toHaveValue( '20' );
	} );

	// ---------------------------------------------------------------
	// Save & persist — Question styles via AJAX
	// ---------------------------------------------------------------

	test( 'question color persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { question_color: '#aa0000' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_question_color' ) ).toHaveValue( '#aa0000' );
	} );

	test( 'question font size persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { question_font_size: '18' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_question_font_size' ) ).toHaveValue( '18' );
	} );

	// ---------------------------------------------------------------
	// Save & persist — Answer styles via AJAX
	// ---------------------------------------------------------------

	test( 'answer color persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { answer_color: '#00aa00' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_answer_color' ) ).toHaveValue( '#00aa00' );
	} );

	test( 'answer font size persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { answer_font_size: '14' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_answer_font_size' ) ).toHaveValue( '14' );
	} );

	// ---------------------------------------------------------------
	// Save & persist — Accent, Icon Style, Animation via AJAX
	// ---------------------------------------------------------------

	test( 'accent color persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { accent_color: '#0000aa' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_accent_color' ) ).toHaveValue( '#0000aa' );
	} );

	test( 'icon style "chevron" persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { icon_style: 'chevron' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_icon_style' ) ).toHaveValue( 'chevron' );
	} );

	test( 'icon style "plus_minus" persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { icon_style: 'plus_minus' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_icon_style' ) ).toHaveValue( 'plus_minus' );
	} );

	test( 'animation "fade" persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { animation: 'fade' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_animation' ) ).toHaveValue( 'fade' );
	} );

	test( 'animation "none" persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { animation: 'none' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_animation' ) ).toHaveValue( 'none' );
	} );

	test( 'animation "slide" persists via AJAX', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, { animation: 'slide' } );
		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_animation' ) ).toHaveValue( 'slide' );
	} );

	// ---------------------------------------------------------------
	// Save & persist — All custom styles at once
	// ---------------------------------------------------------------

	test( 'setting all custom styles at once persists', async ( { page } ) => {
		await saveCustomStylesViaAjax( page, groupId, {
			container_background: '#fafafa',
			container_border_color: '#cccccc',
			container_border_radius: '8',
			container_padding: '16',
			question_color: '#333333',
			question_font_size: '20',
			answer_color: '#555555',
			answer_font_size: '15',
			accent_color: '#ff6600',
			icon_style: 'chevron',
			animation: 'fade',
		} );

		await page.goto(
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);
		await page.waitForLoadState( 'domcontentloaded' );
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '#custom_container_background' ) ).toHaveValue( '#fafafa' );
		await expect( page.locator( '#custom_container_border_color' ) ).toHaveValue( '#cccccc' );
		await expect( page.locator( '#custom_container_border_radius' ) ).toHaveValue( '8' );
		await expect( page.locator( '#custom_container_padding' ) ).toHaveValue( '16' );
		await expect( page.locator( '#custom_question_color' ) ).toHaveValue( '#333333' );
		await expect( page.locator( '#custom_question_font_size' ) ).toHaveValue( '20' );
		await expect( page.locator( '#custom_answer_color' ) ).toHaveValue( '#555555' );
		await expect( page.locator( '#custom_answer_font_size' ) ).toHaveValue( '15' );
		await expect( page.locator( '#custom_accent_color' ) ).toHaveValue( '#ff6600' );
		await expect( page.locator( '#custom_icon_style' ) ).toHaveValue( 'chevron' );
		await expect( page.locator( '#custom_animation' ) ).toHaveValue( 'fade' );
	} );
} );

/**
 * Helper: save custom styles via AJAX on the current page context.
 * Automatically enables the use_custom_style toggle.
 */
async function saveCustomStylesViaAjax( page, gId, styles ) {
	const nonce = await page.locator( '#nlf_faq_group_nonce' ).inputValue();
	const title = await page.locator( '#nlf_group_title' ).inputValue();

	await page.evaluate(
		async ( { n, id, t, styles: s } ) => {
			const form = new URLSearchParams();
			form.append( 'action', 'nlf_save_faq_group_ajax' );
			form.append( 'nlf_faq_group_nonce', n );
			form.append( 'group_id', id );
			form.append( 'nlf_group_title', t );
			form.append( 'nlf_faq_group_question[]', 'Style Q?' );
			form.append( 'nlf_faq_group_answer[]', 'Style A.' );
			form.append( 'nlf_faq_group_visible[0]', '1' );
			form.append( 'nlf_faq_group_use_custom_style', '1' );

			for ( const [ key, val ] of Object.entries( s ) ) {
				form.append( `nlf_faq_group_custom_styles[${ key }]`, String( val ) );
			}

			await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: form.toString(),
			} );
		},
		{ n: nonce, id: gId, t: title, styles }
	);
}

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Preview Tab
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Preview Tab (empty group)', () => {
	test( 'empty state is shown when all FAQ items are removed', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		// Remove all existing question rows so preview sees 0 items.
		const removeButtons = page.locator( '.nlf-faq-remove-row' );
		const count = await removeButtons.count();
		for ( let i = count - 1; i >= 0; i-- ) {
			await removeButtons.nth( i ).click();
			await page.waitForTimeout( 200 );
		}

		await page.locator( '.nlf-faq-tab-button:has-text("Preview")' ).first().click();
		await page.waitForTimeout( 500 );

		// The Preview tab should show the empty state.
		const previewPanel = page.locator( '#panel-preview' );
		await expect( previewPanel.locator( '.nlf-preview-empty-state' ) ).toBeVisible();
	} );

	test( '"Go to Content Tab" button switches to Content tab', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		// Remove all rows to trigger empty state in Preview.
		const removeButtons = page.locator( '.nlf-faq-remove-row' );
		const count = await removeButtons.count();
		for ( let i = count - 1; i >= 0; i-- ) {
			await removeButtons.nth( i ).click();
			await page.waitForTimeout( 200 );
		}

		await page.locator( '.nlf-faq-tab-button:has-text("Preview")' ).first().click();
		await page.waitForTimeout( 500 );

		const switchBtn = page.locator( '#panel-preview [data-switch-tab="content"]' );
		if ( ! ( await switchBtn.isVisible().catch( () => false ) ) ) {
			test.skip();
			return;
		}

		await switchBtn.click();
		await page.waitForTimeout( 300 );

		const contentTab = page.locator( '.nlf-faq-tab-button:has-text("Content")' ).first();
		await expect( contentTab ).toHaveAttribute( 'aria-selected', 'true' );
	} );
} );

test.describe( 'Edit FAQ Group — Preview Tab (with data)', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Preview Tests ${ Date.now() }`,
			question: 'Preview Question?',
			answer: 'Preview Answer.',
			settings: {
				show_search: '1',
				show_counter: '1',
			},
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
		await page.locator( '.nlf-faq-tab-button:has-text("Preview")' ).first().click();
		await page.waitForTimeout( 500 );
	} );

	// ---------------------------------------------------------------
	// Controls presence
	// ---------------------------------------------------------------

	test( 'preview controls bar is visible', async ( { page } ) => {
		await expect( page.locator( '.nlf-preview-controls' ).first() ).toBeVisible();
	} );

	test( 'device toggle buttons are present (desktop, tablet, mobile)', async ( { page } ) => {
		await expect( page.locator( '.nlf-device-btn[data-device="desktop"]' ) ).toBeVisible();
		await expect( page.locator( '.nlf-device-btn[data-device="tablet"]' ) ).toBeVisible();
		await expect( page.locator( '.nlf-device-btn[data-device="mobile"]' ) ).toBeVisible();
	} );

	test( 'desktop is the default active device', async ( { page } ) => {
		await expect(
			page.locator( '.nlf-device-btn[data-device="desktop"]' )
		).toHaveClass( /active/ );
	} );

	test( 'auto-refresh toggle is present and checked', async ( { page } ) => {
		const toggle = page.locator( '.nlf-preview-auto-toggle[data-preview-auto="main"]' );
		await expect( toggle ).toBeAttached();
		await expect( toggle ).toBeChecked();
	} );

	test( 'refresh button is present', async ( { page } ) => {
		await expect( page.locator( '[data-refresh-preview="main"]' ) ).toBeVisible();
	} );

	// ---------------------------------------------------------------
	// Device toggle interaction
	// ---------------------------------------------------------------

	test( 'clicking tablet changes viewport to tablet', async ( { page } ) => {
		await page.locator( '.nlf-device-btn[data-device="tablet"]' ).click();
		await page.waitForTimeout( 300 );

		await expect(
			page.locator( '.nlf-device-btn[data-device="tablet"]' )
		).toHaveClass( /active/ );
		await expect(
			page.locator( '.nlf-preview-viewport' ).first()
		).toHaveAttribute( 'data-device', 'tablet' );
	} );

	test( 'clicking mobile changes viewport to mobile', async ( { page } ) => {
		await page.locator( '.nlf-device-btn[data-device="mobile"]' ).click();
		await page.waitForTimeout( 300 );

		await expect(
			page.locator( '.nlf-device-btn[data-device="mobile"]' )
		).toHaveClass( /active/ );
		await expect(
			page.locator( '.nlf-preview-viewport' ).first()
		).toHaveAttribute( 'data-device', 'mobile' );
	} );

	test( 'clicking desktop after mobile restores desktop viewport', async ( { page } ) => {
		await page.locator( '.nlf-device-btn[data-device="mobile"]' ).click();
		await page.waitForTimeout( 200 );
		await page.locator( '.nlf-device-btn[data-device="desktop"]' ).click();
		await page.waitForTimeout( 200 );

		await expect(
			page.locator( '.nlf-device-btn[data-device="desktop"]' )
		).toHaveClass( /active/ );
		await expect(
			page.locator( '.nlf-preview-viewport' ).first()
		).toHaveAttribute( 'data-device', 'desktop' );
	} );

	// ---------------------------------------------------------------
	// Preview content rendering
	// ---------------------------------------------------------------

	test( 'preview renders FAQ items', async ( { page } ) => {
		const panel = page.locator( '#panel-preview' );
		// Trigger a refresh to force rendering.
		await panel.locator( '[data-refresh-preview="main"]' ).click();
		await page.waitForTimeout( 1500 );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__item' ).first()
		).toBeVisible( { timeout: 10_000 } );
	} );

	test( 'preview shows the question text', async ( { page } ) => {
		const panel = page.locator( '#panel-preview' );
		await panel.locator( '[data-refresh-preview="main"]' ).click();
		await page.waitForTimeout( 1500 );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__question' ).first()
		).toContainText( 'Preview Question?', { timeout: 10_000 } );
	} );

} );

test.describe( 'Edit FAQ Group — Preview reflects Behavior & Display Settings', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Behavior Preview ${ Date.now() }`,
			question: 'Behavior Q?',
			answer: 'Behavior A.',
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	/**
	 * Helper: load page, optionally change a setting, then refresh preview.
	 * Returns the #panel-preview locator.
	 */
	async function setupPreview( page, gId, settingFn ) {
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ gId }`
		);

		if ( settingFn ) {
			// Settings fields are now on the Settings tab.
			await page.locator( '.nlf-faq-tab-button:has-text("Settings")' ).first().click();
			await page.waitForTimeout( 300 );
			await settingFn( page );
		}

		await page.locator( '.nlf-faq-tab-button:has-text("Preview")' ).first().click();
		await page.waitForTimeout( 500 );

		const panel = page.locator( '#panel-preview' );
		await panel.locator( '[data-refresh-preview="main"]' ).click();
		await page.waitForTimeout( 1500 );

		// Wait for preview to render.
		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__item' ).first()
		).toBeVisible( { timeout: 10_000 } );

		return panel;
	}

	// ---------------------------------------------------------------
	// Search Box
	// ---------------------------------------------------------------

	test( 'search is NOT in preview when show_search disabled', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq-search-input' )
		).toHaveCount( 0 );
	} );

	test( 'search IS in preview when show_search enabled', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_show_search' ).check();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq-search-input' )
		).toBeVisible();
	} );

	// ---------------------------------------------------------------
	// Item Counter
	// ---------------------------------------------------------------

	test( 'counter is NOT in preview when show_counter disabled', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_show_counter' ).uncheck();
			await p.locator( '#setting_show_search' ).uncheck();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__counter' )
		).toHaveCount( 0 );
	} );

	test( 'counter IS in preview when show_counter enabled', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_show_counter' ).check();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__counter' ).first()
		).toBeVisible();
		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__counter' ).first()
		).toContainText( '1.' );
	} );

	// ---------------------------------------------------------------
	// Initial State
	// ---------------------------------------------------------------

	test( 'all_closed: no item is open in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_initial_state' ).selectOption( 'all_closed' );
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__item.is-open' )
		).toHaveCount( 0 );
	} );

	test( 'first_open: first item has is-open in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_initial_state' ).selectOption( 'first_open' );
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__item' ).first()
		).toHaveClass( /is-open/ );
	} );

	test( 'custom: item with "Open by default" checked has is-open', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_initial_state' ).selectOption( 'custom' );
			// Switch to Content tab to check "Open by default" on first item.
			await p.locator( '.nlf-faq-tab-button:has-text("Content")' ).first().click();
			await p.waitForTimeout( 300 );
			await p.locator( 'input[name="nlf_faq_group_open[0]"]' ).check();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq__item' ).first()
		).toHaveClass( /is-open/ );
	} );

	// ---------------------------------------------------------------
	// Accordion Mode
	// ---------------------------------------------------------------

	test( 'accordion mode OFF: data-accordion="0" in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_accordion_mode' ).uncheck();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-accordion="0"]' )
		).toBeVisible();
	} );

	test( 'accordion mode ON: data-accordion="1" in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_accordion_mode' ).check();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-accordion="1"]' )
		).toBeVisible();
	} );

	// ---------------------------------------------------------------
	// Animation Speed
	// ---------------------------------------------------------------

	test( 'animation speed "fast" reflected in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_animation_speed' ).selectOption( 'fast' );
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-animation-speed="fast"]' )
		).toBeVisible();
	} );

	test( 'animation speed "normal" reflected in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_animation_speed' ).selectOption( 'normal' );
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-animation-speed="normal"]' )
		).toBeVisible();
	} );

	test( 'animation speed "slow" reflected in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_animation_speed' ).selectOption( 'slow' );
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-animation-speed="slow"]' )
		).toBeVisible();
	} );

	// ---------------------------------------------------------------
	// Smooth Scroll
	// ---------------------------------------------------------------

	test( 'smooth scroll ON: data-smooth-scroll="1" in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_smooth_scroll' ).check();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-smooth-scroll="1"]' )
		).toBeVisible();
	} );

	test( 'smooth scroll OFF: data-smooth-scroll="0" in preview', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		const panel = await setupPreview( page, groupId, async ( p ) => {
			await p.locator( '#setting_smooth_scroll' ).uncheck();
		} );

		await expect(
			panel.locator( '.nlf-preview-content .nlf-faq[data-smooth-scroll="0"]' )
		).toBeVisible();
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Drag & Drop Reorder
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Drag & Drop Reorder', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		// Create group with 3 items.
		const ctx = await browser.newContext();
		const page = await ctx.newPage();
		await loginAsAdmin( page, '/wp-admin/admin.php?page=nlf-faq-group-edit&id=0' );

		const nonce = await page
			.locator( '#nlf_faq_group_nonce' )
			.inputValue()
			.catch( () => '' );

		if ( nonce ) {
			const result = await page.evaluate(
				async ( { n } ) => {
					const form = new URLSearchParams();
					form.append( 'action', 'nlf_save_faq_group_ajax' );
					form.append( 'nlf_faq_group_nonce', n );
					form.append( 'group_id', '0' );
					form.append( 'nlf_group_title', 'Drag Drop Test' );

					form.append( 'nlf_faq_group_question[]', 'First' );
					form.append( 'nlf_faq_group_answer[]', 'Answer 1' );
					form.append( 'nlf_faq_group_visible[0]', '1' );

					form.append( 'nlf_faq_group_question[]', 'Second' );
					form.append( 'nlf_faq_group_answer[]', 'Answer 2' );
					form.append( 'nlf_faq_group_visible[1]', '1' );

					form.append( 'nlf_faq_group_question[]', 'Third' );
					form.append( 'nlf_faq_group_answer[]', 'Answer 3' );
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

			if ( result && result.success ) {
				groupId = String( result.data.group_id );
			}
		}
		await ctx.close();
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	test( 'drag handles are present for each FAQ item', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);

		const handles = page.locator( '.nlf-faq-sort-handle' );
		await expect( handles ).toHaveCount( 3 );
	} );

	test( 'FAQ items are rendered in correct initial order', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);

		const questions = page.locator( 'input[name="nlf_faq_group_question[]"]' );
		await expect( questions.nth( 0 ) ).toHaveValue( 'First' );
		await expect( questions.nth( 1 ) ).toHaveValue( 'Second' );
		await expect( questions.nth( 2 ) ).toHaveValue( 'Third' );
	} );

	test( 'reordering items via JS and saving persists new order', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);

		// Reorder using DOM manipulation (simulating drag result):
		// Move the last row (Third) to be the first row.
		await page.evaluate( () => {
			const tbody = document.getElementById( 'nlf-faq-group-questions-body' );
			if ( ! tbody ) return;
			const rows = tbody.querySelectorAll( '.nlf-faq-question-row' );
			if ( rows.length < 3 ) return;
			// Move third row before first.
			tbody.insertBefore( rows[ 2 ], rows[ 0 ] );
		} );

		// Verify DOM order changed.
		const questions = page.locator( 'input[name="nlf_faq_group_question[]"]' );
		await expect( questions.nth( 0 ) ).toHaveValue( 'Third' );
		await expect( questions.nth( 1 ) ).toHaveValue( 'First' );
		await expect( questions.nth( 2 ) ).toHaveValue( 'Second' );

		// Save via Publish button.
		await page.locator( '#publish' ).click();
		await page.waitForURL( /id=\d+/, { timeout: 10_000 } ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );

		// Verify new order persisted.
		const savedQuestions = page.locator( 'input[name="nlf_faq_group_question[]"]' );
		await expect( savedQuestions.nth( 0 ) ).toHaveValue( 'Third' );
		await expect( savedQuestions.nth( 1 ) ).toHaveValue( 'First' );
		await expect( savedQuestions.nth( 2 ) ).toHaveValue( 'Second' );
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Sidebar (How To Use Box)
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Sidebar', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Sidebar Tests ${ Date.now() }`,
			question: 'Sidebar Q?',
			answer: 'Sidebar A.',
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await deleteGroupViaAdmin( browser, groupId );
	} );

	test( 'How To Use box is visible for saved group', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);

		await expect( page.locator( '#nlf-how-to-use-box' ) ).toBeVisible();
	} );

	test( 'shortcode contains the correct group ID', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);

		await expect( page.locator( '#nlf-how-to-use-box' ) ).toContainText(
			`group="${ groupId }"`
		);
	} );

	test( 'PHP template snippet is shown', async ( { page } ) => {
		if ( ! groupId ) { test.skip(); return; }
		await loginAsAdmin(
			page,
			`/wp-admin/admin.php?page=nlf-faq-group-edit&id=${ groupId }`
		);

		await expect( page.locator( '#nlf-how-to-use-box' ) ).toContainText(
			'do_shortcode'
		);
	} );

	test( 'How To Use box is NOT visible for new (unsaved) group', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		const box = page.locator( '#nlf-how-to-use-box' );
		// Either hidden or not present at all.
		const isVisible = await box.isVisible().catch( () => false );
		expect( isVisible ).toBe( false );
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Reset Buttons
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Reset Buttons', () => {
	let groupId;

	test.beforeAll( async ( { browser } ) => {
		groupId = await createGroupViaAjax( browser, {
			title: `Reset Tests ${ Date.now() }`,
			question: 'Reset Q?',
			answer: 'Reset A.',
			theme: 'modern',
			useCustomStyle: true,
			customStyles: {
				container_background: '#ff0000',
				question_color: '#00ff00',
			},
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
		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );
	} );

	test( 'Reset Theme button is present', async ( { page } ) => {
		await expect( page.locator( '[data-reset="theme"]' ) ).toBeVisible();
	} );

	test( 'Reset Styles button is present', async ( { page } ) => {
		await expect( page.locator( '[data-reset="styles"]' ) ).toBeVisible();
	} );

	test( 'Reset Theme changes theme back to default', async ( { page } ) => {
		// Verify modern is selected.
		await expect( page.locator( '#theme_modern' ) ).toBeChecked();

		// Click Reset Theme.
		await page.locator( '[data-reset="theme"]' ).click();
		await page.waitForTimeout( 300 );

		// Default should now be selected.
		await expect( page.locator( '#theme_default' ) ).toBeChecked();
	} );

	test( 'Reset Styles resets custom style field values to defaults', async ( { page } ) => {
		// Enable toggle to make fields visible.
		const toggle = page.locator( '#nlf-use-custom-style-toggle' );
		if ( ! ( await toggle.isChecked() ) ) {
			await toggle.check();
			await page.waitForTimeout( 200 );
		}

		// Set a custom value first.
		await page.evaluate( () => {
			const el = document.querySelector( '#custom_container_background' );
			if ( el ) el.value = '#ff0000';
		} );
		await expect( page.locator( '#custom_container_background' ) ).toHaveValue( '#ff0000' );

		// Click Reset Styles.
		await page.locator( '[data-reset="styles"]' ).click();
		await page.waitForTimeout( 500 );

		// Custom value should be reset to empty/default.
		const val = await page.locator( '#custom_container_background' ).inputValue();
		expect( val === '' || val === '#ff0000' ).toBeFalsy;
		// More specifically, container_background should not be our custom red.
		expect( val ).not.toBe( '#ff0000' );
	} );
} );

// ═══════════════════════════════════════════════════════════════════
// Test Suite: Edit FAQ Group — Tab Navigation
// ═══════════════════════════════════════════════════════════════════

test.describe( 'Edit FAQ Group — Tab Navigation', () => {
	test( 'Content tab is active by default', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		const contentTab = page.locator( '.nlf-faq-tab-button:has-text("Content")' ).first();
		await expect( contentTab ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'switching to Appearance tab updates aria-selected', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		await page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first().click();
		await page.waitForTimeout( 300 );

		const appearanceTab = page.locator( '.nlf-faq-tab-button:has-text("Appearance")' ).first();
		await expect( appearanceTab ).toHaveAttribute( 'aria-selected', 'true' );

		const contentTab = page.locator( '.nlf-faq-tab-button:has-text("Content")' ).first();
		await expect( contentTab ).toHaveAttribute( 'aria-selected', 'false' );
	} );

	test( 'switching to Preview tab updates aria-selected', async ( { page } ) => {
		await loginAsAdmin(
			page,
			'/wp-admin/admin.php?page=nlf-faq-group-edit&id=0'
		);

		await page.locator( '.nlf-faq-tab-button:has-text("Preview")' ).first().click();
		await page.waitForTimeout( 300 );

		const previewTab = page.locator( '.nlf-faq-tab-button:has-text("Preview")' ).first();
		await expect( previewTab ).toHaveAttribute( 'aria-selected', 'true' );
	} );
} );
