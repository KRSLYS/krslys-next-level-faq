<?php
/**
 * WP_List_Table for FAQ Groups.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * FAQ Groups list table.
 *
 * Displays groups from the custom wp_nlf_faq_groups table.
 */
class Group_List_Table extends \WP_List_Table {

	/**
	 * Content type: 'faq' or 'accordion'.
	 *
	 * @var string
	 */
	private $type = 'faq';

	/**
	 * Constructor.
	 *
	 * @param string $type Content type ('faq' or 'accordion').
	 */
	public function __construct( $type = 'faq' ) {
		$this->type = $type;
		parent::__construct(
			array(
				'singular' => 'faq_group',
				'plural'   => 'faq_groups',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'title'         => __( 'Title', 'krslys-next-level-faq' ),
			'nlf_shortcode' => __( 'Shortcode', 'krslys-next-level-faq' ),
			'nlf_questions' => $this->type === 'accordion' ? __( 'Items', 'krslys-next-level-faq' ) : __( 'Questions', 'krslys-next-level-faq' ),
			'nlf_theme'     => __( 'Theme', 'krslys-next-level-faq' ),
			'nlf_date'      => __( 'Date', 'krslys-next-level-faq' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title'    => array( 'title', false ),
			'nlf_date' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'krslys-next-level-faq' ),
		);
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in process_bulk_action(); orderby/order are safe display parameters.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in process_bulk_action(); orderby/order are safe display parameters.
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		$this->items = Groups_Repository::get_all_groups( null, $orderby, $order, $this->type );
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-faq_groups' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'krslys-next-level-faq' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete groups.', 'krslys-next-level-faq' ) );
		}

		$ids = isset( $_REQUEST['faq_group'] ) ? array_map( 'absint', wp_unslash( (array) $_REQUEST['faq_group'] ) ) : array();

		foreach ( $ids as $id ) {
			Groups_Repository::delete_group( $id );
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Group object.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="faq_group[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Title column with row actions.
	 *
	 * @param object $item Group object.
	 * @return string
	 */
	public function column_title( $item ) {
		$edit_url = add_query_arg(
			array(
				'page' => 'nlf-faq-group-edit',
				'id'   => (int) $item->id,
				'type' => $this->type,
			),
			admin_url( 'admin.php' )
		);

		$list_page = 'accordion' === $this->type ? 'nlf-accordion-groups' : 'nlf-faq-groups';

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => $list_page,
					'action' => 'delete',
					'id'     => (int) $item->id,
				),
				admin_url( 'admin.php' )
			),
			'nlf_delete_group_' . $item->id
		);

		$duplicate_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => $list_page,
					'action' => 'duplicate',
					'id'     => (int) $item->id,
				),
				admin_url( 'admin.php' )
			),
			'nlf_duplicate_group_' . $item->id
		);

		$actions = array(
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'krslys-next-level-faq' ) ),
			'duplicate' => sprintf( '<a href="%s">%s</a>', esc_url( $duplicate_url ), esc_html__( 'Duplicate', 'krslys-next-level-faq' ) ),
			'delete'    => sprintf( '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_attr__( 'Are you sure?', 'krslys-next-level-faq' ), esc_html__( 'Delete', 'krslys-next-level-faq' ) ),
		);

		return sprintf(
			'<strong><a href="%s" class="row-title">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Shortcode column.
	 *
	 * @param object $item Group object.
	 * @return string
	 */
	public function column_nlf_shortcode( $item ) {
		$shortcode = '[krslys_nlf group="' . (int) $item->id . '"]';

		return sprintf(
			'<button type="button" class="nlf-list-shortcode" data-clipboard="%1$s" title="%2$s">'
			. '<code>%1$s</code>'
			. '<span class="nlf-list-shortcode__icon dashicons dashicons-clipboard"></span>'
			. '<span class="nlf-list-shortcode__ok dashicons dashicons-yes-alt"></span>'
			. '</button>',
			esc_attr( $shortcode ),
			esc_attr__( 'Copy shortcode', 'krslys-next-level-faq' )
		);
	}

	/**
	 * Questions count column.
	 *
	 * @param object $item Group object.
	 * @return string
	 */
	public function column_nlf_questions( $item ) {
		$items   = Repository::get_items_for_group( (int) $item->id, false );
		$total   = count( $items );
		$visible = 0;

		foreach ( $items as $faq_item ) {
			if ( ! empty( $faq_item->status ) ) {
				++$visible;
			}
		}

		$hidden = $total - $visible;
		$output = sprintf(
			'<span class="nlf-list-count"><span class="nlf-list-count__number">%d</span></span>',
			$total
		);

		if ( $hidden > 0 ) {
			$output .= sprintf(
				' <span class="nlf-list-count__hidden" title="%s">(%d %s)</span>',
				esc_attr__( 'Hidden from frontend', 'krslys-next-level-faq' ),
				$hidden,
				esc_html__( 'hidden', 'krslys-next-level-faq' )
			);
		}

		return $output;
	}

	/**
	 * Theme column.
	 *
	 * @param object $item Group object.
	 * @return string
	 */
	public function column_nlf_theme( $item ) {
		$theme_slug = isset( $item->theme_settings['theme'] ) ? $item->theme_settings['theme'] : '';

		if ( empty( $theme_slug ) ) {
			$theme_slug = Presets::DEFAULT_PRESET;
		}

		$registry = Presets::get_registry();
		$accent   = '';
		$name     = ucfirst( $theme_slug );

		if ( isset( $registry[ $theme_slug ] ) ) {
			$name   = $registry[ $theme_slug ]['name'];
			$accent = $registry[ $theme_slug ]['values']['accent_color'] ?? '';
		}

		return sprintf(
			'<span class="nlf-list-theme">'
			. '<span class="nlf-list-theme__dot" style="background:%1$s;"></span>'
			. '<span class="nlf-list-theme__name">%2$s</span>'
			. '</span>',
			esc_attr( $accent ),
			esc_html( $name )
		);
	}

	/**
	 * Date column.
	 *
	 * @param object $item Group object.
	 * @return string
	 */
	public function column_nlf_date( $item ) {
		$date = isset( $item->created_at ) ? $item->created_at : '';

		if ( empty( $date ) ) {
			return '&mdash;';
		}

		$timestamp = strtotime( $date );

		return sprintf(
			'<abbr title="%s">%s</abbr>',
			esc_attr( date_i18n( 'Y/m/d g:i:s a', $timestamp ) ),
			esc_html( date_i18n( 'Y/m/d', $timestamp ) )
		);
	}

	/**
	 * No items message.
	 */
	public function no_items() {
		if ( 'accordion' === $this->type ) {
			esc_html_e( 'No accordion groups found.', 'krslys-next-level-faq' );
		} else {
			esc_html_e( 'No FAQ groups found.', 'krslys-next-level-faq' );
		}
	}
}
