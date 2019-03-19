<?php
/**
 * Plugin Name: WooCommerce Order Groups By ETS
 * Plugin URI: https://www.expresstechsoftwares.com/
 * Description: Avails super admin to add and assign user groups to give users the ability to view all orders of belonging group.
 * Version: 1.0.0
 * Author: ExpressTech Software Solutions Pvt. Ltd.
 * Author URI: https://www.expresstechsoftwares.com/
 * Text Domain: ets-wgo
 * WC requires at least: 2.6.14
 * WC tested up to: 3.4.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ETS_WOO_GROUP_ORDERS {

	protected $pluginUrl;
	protected $pluginDir;

	// if logged in user is group admin
	protected $isGroupAdmin;

	public function __construct() {
		if ( !function_exists('is_plugin_active') ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}

		$this->pluginUrl = plugin_dir_url( __FILE__ );
		$this->pluginDir = plugin_dir_path( __FILE__ );

		$this->loadClasses();

		add_action( 'init', array( $this, 'initiateHooks' ) );
		add_action( 'init', array($this, 'addGroupOrderEndpoint') );
		add_action('init', array($this, 'viewOrderCapabilitytoAdmin'), 11);

		new GroupCrud;
	}

	/**
	 * Loads utility classes
	 */
	protected function loadClasses() {
		include_once($this->pluginDir . "includes/classes/group-crud.php");
	}

	/**
	 * Loads all hooks
	 */
	public function initiateHooks() {
		$this->isGroupAdmin = get_user_meta(get_current_user_id(), 'group_admin', 'key');

		if ($this->isGroupAdmin) {
			// New menu 'Group Orders' ---------
			add_filter( 'woocommerce_account_menu_items', array($this, 'addMenuItem'), 10, 1 );
			add_action( 'woocommerce_account_group-orders_endpoint', array($this, 'displayGroupOrders') );
			// ---------------------------------

			add_action('woocommerce_order_details_after_order_table', array($this, 'enableBillingAddressForAdmin'));

			add_action( 'wp_enqueue_scripts', array( $this, 'frontAssets' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'adminAssets' ) );
	}

	/**
	 * Adds new menu 'Group Orders'
	 */
	public function addMenuItem( $items ) {
		$originalItems = $items;

		$dowloadsEndPoint = get_option( 'woocommerce_myaccount_downloads_endpoint', 'customer-logout' );
		unset($items[$dowloadsEndPoint]);
		$EditAddressEndPoint = get_option( 'woocommerce_myaccount_edit_address_endpoint', 'customer-logout' );
		unset($items[$EditAddressEndPoint]);
		$paymentMethodsEndPoint = get_option( 'woocommerce_myaccount_payment_methods_endpoint', 'customer-logout' );
		unset($items[$paymentMethodsEndPoint]);
		$editAccountEndPoint = get_option( 'woocommerce_myaccount_edit_account_endpoint', 'customer-logout' );
		unset($items[$editAccountEndPoint]);	
		$LogoutEndPoint = get_option( 'woocommerce_logout_endpoint', 'customer-logout' );
		unset($items[$LogoutEndPoint]);									

	    $items['group-orders'] = __( 'Group Orders', 'ets-wgo' );
	    $items[$dowloadsEndPoint] = __( 'Downloads', 'woocommerce' );
	    $items[$EditAddressEndPoint] = __( 'Addresses', 'woocommerce' );

	    if ( isset($originalItems[$paymentMethodsEndPoint]) )
	    	$items[$paymentMethodsEndPoint] = __( 'Payment methods', 'woocommerce' );
	    
	    $items[$editAccountEndPoint] = __( 'Account details', 'woocommerce' );
	    $items[$LogoutEndPoint] = __( 'Logout', 'woocommerce' );
	    return $items;
	}

	/**
	 * Adds new endpoint to view all group order
	 * under my account
	 */
	public function addGroupOrderEndpoint() {
    	add_rewrite_endpoint( 'group-orders', EP_PAGES );
	}

	/**
	 * Returns all user IDs belonging to the
	 * logged in user's group
	 */
	protected function _getUsersInGroup() {
		$userid = get_current_user_id();

		$userGroupsTerms = wp_get_object_terms($userid, 'user_group');
		$groupTermId = count($userGroupsTerms) > 0 ? $userGroupsTerms[0]->term_id : 0; 

		return (array) get_objects_in_term($groupTermId, 'user_group');		
	}

	/**
	 * Displays group orders tables
	 */
	public function displayGroupOrders() {
		$allUsersOfGroup = $this->_getUsersInGroup();

		$groupOrders = get_posts( array(
		    'numberposts' => -1,
		    // 'meta_key'    => '_customer_user',
		    // 'meta_value'  => get_current_user_id(),
		    'post_type'   => wc_get_order_types(),
		    'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_query' => array(
			  array(
			     'key'     => '_customer_user',
			     'value'   => $allUsersOfGroup,
			     'compare' => 'IN'
			  )
			)		    
		) );

		?>
		<table id="<?php echo $groupOrders ? "ets-group-orders" : ""; ?>">
			<thead>
				<tr>
					<th><?php _e('Order', 'woocommerce'); ?></th>
					<th><?php _e('User', 'woocommerce'); ?></th>
					<th><?php _e('Status', 'woocommerce'); ?></th>
					<th><?php _e('Total', 'woocommerce'); ?></th>
					<th><?php _e('Date', 'woocommerce'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $groupOrders ): ?>
			<?php foreach($groupOrders as $order): ?>
				<?php
					$orderDetails = wc_get_order( $order->ID );
				?>
				<tr>
					<td><a href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id') ) . get_option('woocommerce_myaccount_view_order_endpoint') . "/{$order->ID}"; ?>"><?php echo "#{$order->ID}"; ?></td>
					<td><?php echo $orderDetails->get_user()->display_name; ?></td>
					<td><?php echo $orderDetails->get_status(); ?></td>
					<td><?php echo get_woocommerce_currency_symbol() . $orderDetails->get_total() . ' ' . __('for', 'ets-wgo') . ' ' . $orderDetails->get_item_count() . ' ' . __('items', 'ets-wgo'); ?></td>
					<td data-sort="<?php echo strtotime(@$orderDetails->order_date); ?>"><?php echo @date('F j, Y', strtotime($orderDetails->order_date)); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php else: ?>
				<tr>
					<td colspan="5"><?php _e('No Records Found.', 'ets-wgo'); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * JS, CSS for front end
	 */
	public function frontAssets() {
		wp_enqueue_script('ets-datatable-js', 'https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js', array( 'jquery' ));
		wp_enqueue_script('ets-woo-group', $this->pluginUrl . 'assets/js/ets-wgo.js', array('ets-datatable-js'));
		wp_enqueue_style('ets-datatable-css', 'https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css');
	}

	/**
	 * JS, CSS for admin
	 */
	public function adminAssets() {
		wp_enqueue_script('ets-woo-group-admin', $this->pluginUrl . 'assets/admin/js/ets-wgo-admin.js');
	}

	/**
	 * Enabling order view capability for group admin
	 */
	public function viewOrderCapabilitytoAdmin() {
		if ( $this->isGroupAdmin ) {
			$segments = explode('/', $_SERVER['REQUEST_URI']);
			$viewOrderEndpoint = get_option('woocommerce_myaccount_view_order_endpoint', 'view-order');

			$orderId = array_pop($segments);
			$endpointName = array_pop($segments);
			if ( $endpointName == $viewOrderEndpoint ) {
				$order = wc_get_order($orderId);

				$user = new WP_User( get_current_user_id() );
				// If order belongs to current user group
				if ( in_array($order->get_user_id(), $this->_getUsersInGroup()) ) {
					$user->add_cap( 'view_order' );
				} else {
					// $user->remove_cap( 'view_order' );
					wp_redirect( get_permalink(get_option('woocommerce_myaccount_page_id')) );
					die();
				}
			}
		}
	}

	/**
	 * Show billing address on order details
	 * to group admins
	 */
	public function enableBillingAddressForAdmin($order) {
		if ( $this->isGroupAdmin && $order->get_user_id() !== get_current_user_id() )
			wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
	}
}

$EtsWooGroupOrders = new ETS_WOO_GROUP_ORDERS();