'use strict';

const { loginAsAdmin } = require( './admin-auth' );

/**
 * Whether test cleanup is enabled.
 * Disable by running: E2E_CLEANUP=false npx playwright test
 */
const CLEANUP_ENABLED = ( process.env.E2E_CLEANUP || 'true' ).toLowerCase() !== 'false';

/**
 * Create a FAQ group via AJAX with full data.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {object} [opts]
 * @param {string} [opts.title]
 * @param {string} [opts.question]
 * @param {string} [opts.answer]
 * @param {object} [opts.settings]        – keys like show_search, accordion_mode, etc.
 * @param {object} [opts.themeCustom]     – keys like primary, secondary, accent, background.
 * @param {string} [opts.theme]           – theme slug (default, modern, elegant, etc.)
 * @param {boolean} [opts.useCustomStyle] – enable Advanced Style Overrides.
 * @param {object} [opts.customStyles]    – keys like container_background, question_color, etc.
 * @returns {Promise<string|null>} group ID or null
 */
async function createGroupViaAjax( browser, opts = {} ) {
	const title    = opts.title    || `E2E Group ${ Date.now() }`;
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
		async ( { nonce: n, title: t, question: q, answer: a, settings: s,
			theme: th, themeCustom: tc, useCustomStyle: ucs, customStyles: cs } ) => {
			const form = new URLSearchParams();
			form.append( 'action', 'nlf_save_faq_group_ajax' );
			form.append( 'nlf_faq_group_nonce', n );
			form.append( 'group_id', '0' );
			form.append( 'nlf_group_title', t );
			form.append( 'nlf_faq_group_question[]', q );
			form.append( 'nlf_faq_group_answer[]', a );
			form.append( 'nlf_faq_group_visible[0]', '1' );

			// Theme.
			if ( th ) {
				form.append( 'nlf_faq_group_theme', th );
			}

			// Theme custom colors.
			if ( tc ) {
				for ( const [ key, val ] of Object.entries( tc ) ) {
					form.append( `nlf_faq_group_theme_custom[${ key }]`, val );
				}
			}

			// Display settings.
			if ( s ) {
				for ( const [ key, val ] of Object.entries( s ) ) {
					form.append( `nlf_faq_group_settings[${ key }]`, String( val ) );
				}
			}

			// Advanced custom styles.
			if ( ucs ) {
				form.append( 'nlf_faq_group_use_custom_style', '1' );
			}
			if ( cs ) {
				for ( const [ key, val ] of Object.entries( cs ) ) {
					form.append( `nlf_faq_group_custom_styles[${ key }]`, String( val ) );
				}
			}

			const res = await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: form.toString(),
			} );
			return res.json();
		},
		{
			nonce, title, question, answer,
			settings: opts.settings || null,
			theme: opts.theme || null,
			themeCustom: opts.themeCustom || null,
			useCustomStyle: opts.useCustomStyle || false,
			customStyles: opts.customStyles || null,
		}
	);

	await ctx.close();

	if ( result && result.success && result.data && result.data.group_id ) {
		return String( result.data.group_id );
	}
	return null;
}

/**
 * Delete a FAQ group using real nonce from the groups list page.
 * Respects E2E_CLEANUP env var — skips deletion when set to "false".
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {string|null} groupId
 */
async function deleteGroupViaAdmin( browser, groupId ) {
	if ( ! groupId || ! CLEANUP_ENABLED ) return;

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

module.exports = {
	CLEANUP_ENABLED,
	createGroupViaAjax,
	deleteGroupViaAdmin,
};
