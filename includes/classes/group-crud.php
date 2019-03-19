<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles all operations a custom taxtonomy 'Admin Group'
 */
class GroupCrud {

	// For user group taxonomy
	private static $taxonomies	= array();	

	public function __construct() {
		// Taxonomies
		add_action('registered_taxonomy',		array($this, 'registered_taxonomy'), 10, 3);
		
		// Menus
		add_action('admin_menu',				array($this, 'admin_menu'));
		add_filter('parent_file',				array($this, 'parent_menu'));
		
		// User Profiles
		add_action('show_user_profile',			array($this, 'user_profile'));
		add_action('edit_user_profile',			array($this, 'user_profile'));
		add_action('personal_options_update',	array($this, 'save_profile'));
		add_action('edit_user_profile_update',	array($this, 'save_profile'));
		add_filter('sanitize_user',				array($this, 'restrict_username'));

		add_action( 'init', array($this, 'registerUserGroupsTaxonomy'), 0 );
	}

	/**
	 * This is our way into manipulating registered taxonomies
	 * It's fired at the end of the register_taxonomy function
	 * 
	 * @param String $taxonomy	- The name of the taxonomy being registered
	 * @param String $object	- The object type the taxonomy is for; We only care if this is "user"
	 * @param Array $args		- The user supplied + default arguments for registering the taxonomy
	 */
	public function registered_taxonomy($taxonomy, $object, $args) {
		global $wp_taxonomies;
		
		// Only modify user taxonomies, everything else can stay as is
		if($object != 'user') return;
		
		// We're given an array, but expected to work with an object later on
		$args	= (object) $args;
		
		// Register any hooks/filters that rely on knowing the taxonomy now
		add_filter("manage_edit-{$taxonomy}_columns",	array($this, 'set_user_column'));
		add_action("manage_{$taxonomy}_custom_column",	array($this, 'set_user_column_values'), 10, 3);
		
		// Set the callback to update the count if not already set
		if(empty($args->update_count_callback)) {
			$args->update_count_callback	= array($this, 'update_count');
		}
		
		// We're finished, make sure we save out changes
		$wp_taxonomies[$taxonomy]		= $args;
		self::$taxonomies[$taxonomy]	= $args;
	}
	
	/**
	 * We need to manually update the number of users for a taxonomy term
	 * 
	 * @see	_update_post_term_count()
	 * @param Array $terms		- List of Term taxonomy IDs
	 * @param Object $taxonomy	- Current taxonomy object of terms
	 */
	public function update_count($terms, $taxonomy) {
		global $wpdb;
		
		foreach((array) $terms as $term) {
			$count	= $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term));
			
			do_action('edit_term_taxonomy', $term, $taxonomy);
			$wpdb->update($wpdb->term_taxonomy, compact('count'), array('term_taxonomy_id'=>$term));
			do_action('edited_term_taxonomy', $term, $taxonomy);
		}
	}
	
	/**
	 * Add each of the taxonomies to the Users menu
	 * They will behave in the same was as post taxonomies under the Posts menu item
	 * Taxonomies will appear in alphabetical order
	 */
	public function admin_menu() {
		// Put the taxonomies in alphabetical order
		$taxonomies	= self::$taxonomies;
		ksort($taxonomies);
		
		foreach($taxonomies as $key=>$taxonomy) {
			add_users_page(
				$taxonomy->labels->menu_name, 
				$taxonomy->labels->menu_name, 
				$taxonomy->cap->manage_terms, 
				"edit-tags.php?taxonomy={$key}"
			);
		}
	}
	
	/**
	 * Fix a bug with highlighting the parent menu item
	 * By default, when on the edit taxonomy page for a user taxonomy, the Posts tab is highlighted
	 * This will correct that bug
	 */
	public function parent_menu($parent = '') {
		global $pagenow;
		
		// If we're editing one of the user taxonomies
		// We must be within the users menu, so highlight that
		if(!empty($_GET['taxonomy']) && $pagenow == 'edit-tags.php' && isset(self::$taxonomies[$_GET['taxonomy']])) {
			$parent	= 'users.php';
		}
		
		return $parent;
	}
	
	/**
	 * Correct the column names for user taxonomies
	 * Need to replace "Posts" with "Users"
	 */
	public function set_user_column($columns) {
		unset($columns['posts']);
		$columns['users']	= __('Users');
		return $columns;
	}
	
	/**
	 * Set values for custom columns in user taxonomies
	 */
	public function set_user_column_values($display, $column, $term_id) {
		if('users' === $column) {
			$term	= get_term($term_id, $_GET['taxonomy']);
			echo $term->count;
		}
	}
	
	/**
	 * Add the taxonomies to the user view/edit screen
	 * 
	 * @param Object $user	- The user of the view/edit screen
	 */
	public function user_profile($user) {
		// Using output buffering as we need to make sure we have something before outputting the header
		// But we can't rely on the number of taxonomies, as capabilities may vary
		ob_start();

		$userid = get_current_user_id();

		$userGroupsTerms = wp_get_object_terms($userid, 'user_group');
		$groupTermId = count($userGroupsTerms) > 0 ? $userGroupsTerms[0]->term_id : 0; 		
		
		foreach(self::$taxonomies as $key=>$taxonomy):
			$isGroupAdmin = get_user_meta($user->ID, 'group_admin', 'key');

			// Check the current user can assign terms for this taxonomy
			if(!current_user_can($taxonomy->cap->assign_terms)) continue;
			
			// Get all the terms in this taxonomy
			$terms	= get_terms($key, array('hide_empty'=>false));
			?>
			<table class="form-table ets-wgo-group-inputs">
				<tr>
					<th><label for=""><?php _e("Select {$taxonomy->labels->singular_name}")?></label></th>
					<td>
						<?php if(!empty($terms)):?>
							<?php foreach($terms as $term):?>
								<input class="user-group-radio" type="radio" name="<?php echo $key?>" id="<?php echo "{$key}-{$term->slug}"?>" value="<?php echo $term->slug?>" <?php checked(true, is_object_in_term($user->ID, $key, $term))?> />
								<label for="<?php echo "{$key}-{$term->slug}"?>"><?php echo $term->name?></label>
								<br />
								<br />
							<?php endforeach; // Terms?>
								<a class="clr-user-group" href="javascript:void(0);">Clear</a>
								<br />							
						<?php else:?>
							<?php _e("There are no {$taxonomy->labels->name} available.")?>
						<?php endif?>
					</td>
				</tr>
				<tr>
					<th><label for="ets_group_admin"><?php _e('Group Admin', 'ets-wgo'); ?></label></th>
					<td><input id="ets_group_admin" type="checkbox" name="group_admin" value="1" <?php echo $isGroupAdmin ? 'checked' : ''; ?>></td>
				</tr>
			</table>
			<?php
		endforeach; // Taxonomies
		
		// Output the above if we have anything, with a heading
		$output	= ob_get_clean();
		if(!empty($output)) {
			echo '<h3>', __('User Group'), '</h3>';
			echo $output;
		}
	}
	
	/**
	 * Save the custom user taxonomies when saving a users profile
	 * 
	 * @param Integer $user_id	- The ID of the user to update
	 */
	public function save_profile($user_id) {
		foreach(self::$taxonomies as $key=>$taxonomy) {
			// Check the current user can edit this user and assign terms for this taxonomy
			if(!current_user_can('edit_user', $user_id) && current_user_can($taxonomy->cap->assign_terms)) return false;
			
			// Save the data
			$term	= esc_attr($_POST[$key]);
			wp_set_object_terms($user_id, array($term), $key, false);
			clean_object_term_cache($user_id, $key);
		}

		update_user_meta($user_id, 'group_admin', (int) $_POST['group_admin']);
	}
	
	/**
	 * Usernames can't match any of our user taxonomies
	 * As otherwise it will cause a URL conflict
	 * This method prevents that happening
	 */
	public function restrict_username($username) {
		if(isset(self::$taxonomies[$username])) return '';
		
		return $username;
	}

	/**
	 * Register user group as Taxonomy
	 */
	public function registerUserGroupsTaxonomy() {

		$labels = array(
			'name'                       => _x( 'User Groups', 'Taxonomy General Name', 'ets-wgo' ),
			'singular_name'              => _x( 'User Group', 'Taxonomy Singular Name', 'ets-wgo' ),
			'menu_name'                  => __( 'User Groups', 'ets-wgo' ),
			'all_items'                  => __( 'All User Groups', 'ets-wgo' ),
			'parent_item'                => __( 'Parent Group', 'ets-wgo' ),
			'parent_item_colon'          => __( 'Parent Group:', 'ets-wgo' ),
			'new_item_name'              => __( 'New Group Name', 'ets-wgo' ),
			'add_new_item'               => __( 'Add New Group', 'ets-wgo' ),
			'edit_item'                  => __( 'Edit Group', 'ets-wgo' ),
			'update_item'                => __( 'Update Group', 'ets-wgo' ),
			'separate_items_with_commas' => __( 'Separate group with commas', 'ets-wgo' ),
			'search_items'               => __( 'Search Groups', 'ets-wgo' ),
			'add_or_remove_items'        => __( 'Add or remove groups', 'ets-wgo' ),
			'choose_from_most_used'      => __( 'Choose from the most used group', 'ets-wgo' ),
			'not_found'                  => __( 'Not Found', 'ets-wgo' ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
		);
		register_taxonomy( 'user_group', 'user', $args );
	}	
}