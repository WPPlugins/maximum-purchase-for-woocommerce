<?php

class VTMAX_Backbone{   
	
	public function __construct(){
		  $this->vtmax_register_post_types();
      $this->vtmax_add_dummy_rule_category();
   //   add_filter( 'post_row_actions', array(&$this, 'vtmax_remove_row_actions'), 10, 2 );

	}
  
  public function vtmax_register_post_types() {
   global $vtmax_info;
  
  $tax_labels = array(
		'name' => _x( 'Maximum Purchase Categories', 'taxonomy general name', 'vtmax' ),
		'singular_name' => _x( 'Maximum Purchase Category', 'taxonomy singular name', 'vtmax' ),
		'search_items' => __( 'Search Maximum Purchase Category', 'vtmax' ),
		'all_items' => __( 'All Maximum Purchase Categories', 'vtmax' ),
		'parent_item' => __( 'Maximum Purchase Category', 'vtmax' ),
		'parent_item_colon' => __( 'Maximum Purchase Category:', 'vtmax' ),
		'edit_item' => __( 'Edit Maximum Purchase Category', 'vtmax' ),
		'update_item' => __( 'Update Maximum Purchase Category', 'vtmax' ),
		'add_new_item' => __( 'Add New Maximum Purchase Category', 'vtmax' ),
		'new_item_name' => __( 'New Maximum Purchase Category', 'vtmax' )
  ); 	

  
  $tax_args = array(
    'hierarchical' => true,
		'labels' => $tax_labels,
		'show_ui' => true,
		'query_var' => false,
    'rewrite' => array( 'slug' => 'vtmax_rule_category',  'with_front' => false, 'hierarchical' => true )
  ) ;            

  $taxonomy_name =  'vtmax_rule_category';
 
  
   //REGISTER TAXONOMY 
  	register_taxonomy($taxonomy_name, $vtmax_info['applies_to_post_types'], $tax_args); 
    
        
 //REGISTER POST TYPE
 $post_labels = array(
				'name' => _x( 'Maximum Purchase Rules', 'post type name', 'vtmax' ),
        'singular_name' => _x( 'Maximum Purchase Rule', 'post type singular name', 'vtmax' ),
        'add_new' => _x( 'Add New', 'admin menu: add new Maximum Purchase Rule', 'vtmax' ),
        'add_new_item' => __('Add New Maximum Purchase Rule', 'vtmax' ),
        'edit_item' => __('Edit Maximum Purchase Rule', 'vtmax' ),
        'new_item' => __('New Maximum Purchase Rule', 'vtmax' ),
        'view_item' => __('View Maximum Purchase Rule', 'vtmax' ),
        'search_items' => __('Search Maximum Purchase Rules', 'vtmax' ),
        'not_found' =>  __('No Maximum Purchase Rules found', 'vtmax' ),
        'not_found_in_trash' => __( 'No Maximum Purchase Rules found in Trash', 'vtmax' ),
        'parent_item_colon' => '',
        'menu_name' => __( 'Maximum Purchase Rules', 'vtmax' )
			);
      
	register_post_type( 'vtmax-rule', array(
		  'capability_type' => 'post',
      'hierarchical' => true,
		  'exclude_from_search' => true,
      'labels' => $post_labels,
			'public' => true,
			'show_ui' => true,
      'query_var' => true,
      'rewrite' => false,     
      'supports' => array('title' )	 //remove 'revisions','editor' = no content/revisions boxes 
		)
	);
 
//	$role = get_role( 'administrator' );      v1.07 removed for conflict
//	$role->add_cap( 'read_vtmax-rule' );      v1.07 removed for conflict
}

  public function vtmax_add_dummy_rule_category () {
      $category_list = get_terms( 'vtmax_rule_category', 'hide_empty=0&parent=0' );
    	if ( count( $category_list ) == 0 ) {
    		wp_insert_term( __( 'Maximum Purchase Category', 'vtmax' ), 'vtmax_rule_category', "parent=0" );
      }
  }


/*------------------------------------------------------------------------------------
  	remove quick edit for custom post type 
  ------------------------------------------------------------------------------------*/
 /*
  public function vtmax_remove_row_actions( $actions, $post )
  {
    global $current_screen;
  	if( $current_screen->post_type = 'vtmax-rule' ) {
    	unset( $actions['edit'] );
    	unset( $actions['view'] );
    	unset( $actions['trash'] );
    	unset( $actions['inline hide-if-no-js'] );
  	//$actions['inline hide-if-no-js'] .= __( 'Quick&nbsp;Edit' );
     }
  	return $actions;
  }
*/





function vtmax_register_settings() {
    register_setting( 'vtmax_options', 'vtmax_rules' );
} 


} //end class
$vtmax_backbone = new VTMAX_Backbone;
  
  
  
  class VTMAX_Functions {   
	
	public function __construct(){

	}
    
  function vtmax_getSystemMemInfo() 
  {       
      $data = explode("\n", file_get_contents("/proc/meminfo"));
      $meminfo = array();
      foreach ($data as $line) {
          list($key, $val) = explode(":", $line);
          $meminfo[$key] = trim($val);
      }
      return $meminfo;
  }
  
  } //end class