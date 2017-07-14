<?php
/*
Plugin Name: VarkTech Maximum Purchase for WooCommerce
Plugin URI: http://varktech.com
Description: An e-commerce add-on for WooCommerce, supplying maximum purchase functionality.
Version: 1.07.6
Author: Vark
Author URI: http://varktech.com
*/


/*
** define Globals 
*/
   $vtmax_info;  //initialized in VTMAX_Parent_Definitions
   $vtmax_rules_set;
   $vtmax_rule;
   $vtmax_cart;
   $vtmax_cart_item;
   $vtmax_setup_options;
//   $vtmax_error_msg;
   
   //initial setup only, overriden later in function vtprd_debug_options
   error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR); //v1.07.2
              
class VTMAX_Controller{
	
	public function __construct(){    
   
		define('VTMAX_VERSION',                               '1.07.6');
    define('VTMAX_MINIMUM_PRO_VERSION',                   '1.07.3'); //V1.07.1
    define('VTMAX_LAST_UPDATE_DATE',                      '2017-04-03');
    define('VTMAX_DIRNAME',                               ( dirname( __FILE__ ) ));
    define('VTMAX_URL',                                   plugins_url( '', __FILE__ ) );
    define('VTMAX_EARLIEST_ALLOWED_WP_VERSION',           '3.3');   //To pick up wp_get_object_terms fix, which is required for vtmax-parent-functions.php
    define('VTMAX_EARLIEST_ALLOWED_PHP_VERSION',          '5');
    define('VTMAX_PLUGIN_SLUG',                           plugin_basename(__FILE__));
    define('VTMAX_PRO_PLUGIN_NAME',                      'Varktech Maximum Purchase Pro for WooCommerce');    //V1.07.1    
    

    
    
    require ( VTMAX_DIRNAME . '/woo-integration/vtmax-parent-definitions.php');
   
    /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    //  these control the rules ui, add/save/trash/modify/delete
    /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    add_action('init',          array( &$this, 'vtmax_controller_init' )); 
    add_action('admin_init',    array( &$this, 'vtmax_admin_init' ));
    
    //v1.07 begin
    add_action( 'draft_to_publish',       array( &$this, 'vtmax_admin_update_rule' )); 
    add_action( 'auto-draft_to_publish',  array( &$this, 'vtmax_admin_update_rule' ));
    add_action( 'new_to_publish',         array( &$this, 'vtmax_admin_update_rule' )); 			
    add_action( 'pending_to_publish',     array( &$this, 'vtmax_admin_update_rule' ));    
    //v1.07 end
        
    add_action('save_post',     array( &$this, 'vtmax_admin_update_rule' ));
    add_action('delete_post',   array( &$this, 'vtmax_admin_delete_rule' ));    
    add_action('trash_post',    array( &$this, 'vtmax_admin_trash_rule' ));
    add_action('untrash_post',  array( &$this, 'vtmax_admin_untrash_rule' ));
    /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    
    //get rid of bulk actions on the edit list screen, which aren't compatible with this plugin's actions...
    add_action('bulk_actions-edit-vtmax-rule', array($this, 'vtmax_custom_bulk_actions') ); 

	}   //end constructor

  	                                                             
 /* ************************************************
 **   Overhead and Init
 *************************************************** */
	public function vtmax_controller_init(){
    global $vtmax_setup_options;

    load_plugin_textdomain( 'vtmax', null, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 

    require ( VTMAX_DIRNAME . '/core/vtmax-backbone.php' );    
    require ( VTMAX_DIRNAME . '/core/vtmax-rules-classes.php');
    require ( VTMAX_DIRNAME . '/woo-integration/vtmax-parent-functions.php');
    require ( VTMAX_DIRNAME . '/woo-integration/vtmax-parent-cart-validation.php');

    //moved here v1.07
    if (get_option( 'vtmax_setup_options' ) ) {
      $vtmax_setup_options = get_option( 'vtmax_setup_options' );  //put the setup_options into the global namespace
    }        
    vtmax_debug_options();  //v1.07
            
    if (is_admin()){
        require ( VTMAX_DIRNAME . '/admin/vtmax-setup-options.php');
        //fix 02-13-2013 - register_activation_hook now at bottom of file, after class instantiates
        
        if(defined('VTMAX_PRO_DIRNAME')) {
          require ( VTMAX_PRO_DIRNAME . '/admin/vtmax-rules-ui.php' );
          require ( VTMAX_PRO_DIRNAME . '/admin/vtmax-rules-update.php');
        } else {
          require ( VTMAX_DIRNAME .     '/admin/vtmax-rules-ui.php' );
          require ( VTMAX_DIRNAME .     '/admin/vtmax-rules-update.php');
        }
        
        require ( VTMAX_DIRNAME . '/admin/vtmax-checkbox-classes.php');
        require ( VTMAX_DIRNAME . '/admin/vtmax-rules-delete.php');
        
        //V1.07.1 begin
        if ( (defined('VTMAX_PRO_DIRNAME')) &&
             (version_compare(VTMAX_PRO_VERSION, VTMAX_MINIMUM_PRO_VERSION) < 0) ) {    //'<0' = 1st value is lower  
          add_action( 'admin_notices',array(&$this, 'vtmax_admin_notice_version_mismatch') );            
        }
        //V1.07.1 begin 
             
    } 
    
    //unconditional branch for these resources needed for WOOCommerce, at "place order" button time
    require ( VTMAX_DIRNAME . '/core/vtmax-cart-classes.php');
    
    if(defined('VTMAX_PRO_DIRNAME')) {
      require ( VTMAX_PRO_DIRNAME . '/core/vtmax-apply-rules.php' );
    } else {
      require ( VTMAX_DIRNAME .     '/core/vtmax-apply-rules.php' );
    }
    
    wp_enqueue_script('jquery'); 
    
    //*****************
    //v1.07.6 begin
    //*****************
     /*
     wc_clear_notices
     doesn't clear old notices in all cases
     this takes care of it.
     
     CAN'T use is_cart() as it's not yet available.
     */
    
    if (is_admin()) {
      return;
    }
    
    global $vtmax_info;
    $vtmax_info['woo_cart_url']      =  vtmax_woo_get_url('cart'); 
    $vtmax_info['woo_checkout_url']  =  vtmax_woo_get_url('checkout');
    $vtmax_info['currPageURL']       =  vtmax_currPageURL(); 
    
    
    //ONLY USED on the cart page    
    $use_clear_cart_msgs = apply_filters('vtmax_use_clear_cart_msgs',TRUE );
    if ($use_clear_cart_msgs) {
      
      $currPageURL      = $vtmax_info['currPageURL'];
      $woo_cart_url     = $vtmax_info['woo_cart_url'];
      
      if (strpos($currPageURL,$woo_cart_url )     !== false) {
        add_action( "wp_enqueue_scripts", array(&$this, 'vtmax_enqueue_cart_resources') ); 
       }       
    }
    
    return;
    //*****************
    //v1.07.6 end
    //*****************
    
  }
  
  //*****************
  //v1.07.6 New Function
  //*****************
  function vtmax_enqueue_cart_resources() {
    wp_register_script('vtmax-clear-cart-msgs', VTMAX_URL.'/woo-integration/js/vtmax-clear-cart-msgs.js' ); 
    wp_enqueue_script ('vtmax-clear-cart-msgs', array('jquery'), false, true);
    //error_log( print_r(  'vtmax-clear-cart-msgs', true ) ); 
    wp_register_style( 'vtmax-error-style', VTMAX_URL.'/core/css/vtmax-error-style.css' );  
    wp_enqueue_style('vtmax-error-style');
  } 
  
         
  /* ************************************************
  **   Admin - Remove bulk actions on edit list screen, actions don't work the same way as onesies...
  ***************************************************/ 
  function vtmax_custom_bulk_actions($actions){
    
    ?> 
    <style type="text/css"> #delete_all {display:none;} /*kill the 'empty trash' buttons, for the same reason*/ </style>
    <?php
    
    unset( $actions['edit'] );
    unset( $actions['trash'] );
    unset( $actions['untrash'] );
    unset( $actions['delete'] );
    return $actions;
  }
    
  /* ************************************************
  **   Admin - Show Rule UI Screen
  *************************************************** 
  *  This function is executed whenever the add/modify screen is presented
  *  WP also executes it ++right after the update function, prior to the screen being sent back to the user.   
  */  
	public function vtmax_admin_init(){
     if ( !current_user_can( 'edit_posts', 'vtmax-rule' ) )
          return;

     $vtmax_rules_ui = new VTMAX_Rules_UI; 
  }
  
 
  /* ************************************************
  **   Admin - Update Rule 
  *************************************************** */
	public function vtmax_admin_update_rule(){
    /* *****************************************************************
         The delete/trash/untrash actions *will sometimes fire save_post*
         and there is a case structure in the save_post function to handle this.
    
          the delete/trash actions are sometimes fired twice, 
               so this can be handled by checking 'did_action'
     ***************************************************************** */
      
      global $post, $vtmax_rules_set;
      
      // v1.07.2 begin
      if( !isset( $post ) ) {    
        return;
      }  
      // v1.07.2  end
            
      if ( !( 'vtmax-rule' == $post->post_type )) {
        return;
      }  
      if (( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return; 
      }
     if (isset($_REQUEST['vtmax_nonce']) ) {     //nonce created in vtmax-rules-ui.php  
          $nonce = $_REQUEST['vtmax_nonce'];
          if(!wp_verify_nonce($nonce, 'vtmax-rule-nonce')) { 
            return;
          }
      } 
      if ( !current_user_can( 'edit_posts', 'vtmax-rule' ) ) {
          return;
      }

      
      /* ******************************************
       The 'SAVE_POST' action is fired at odd times during updating.
       When it's fired early, there's no post data available.
       So checking for a blank post id is an effective solution.
      *************************************************** */      
      if ( !( $post->ID > ' ' ) ) { //a blank post id means no data to proces....
        return;
      } 
      //AND if we're here via an action other than a true save, do the action and exit stage left
      $action_type = $_REQUEST['action'];
      if ( in_array($action_type, array('trash', 'untrash', 'delete') ) ) {
        switch( $action_type ) {
            case 'trash':
                $this->vtmax_admin_trash_rule();  
              break;
            case 'untrash':
                $this->vtmax_admin_untrash_rule();
              break;
            case 'delete':
                $this->vtmax_admin_delete_rule();  
              break;
        }
        return;
      }
                 
      $vtmax_rule_update = new VTMAX_Rule_update;
  }
   
  
 /* ************************************************
 **   Admin - Delete Rule
 *************************************************** */
	public function vtmax_admin_delete_rule(){
     global $post, $vtmax_rules_set; 
     if ( !( 'vtmax-rule' == $post->post_type ) ) {
      return;
     }        

     if ( !current_user_can( 'delete_posts', 'vtmax-rule' ) )  {
          return;
     }
    
    $vtmax_rule_delete = new VTMAX_Rule_delete;            
    $vtmax_rule_delete->vtmax_delete_rule();
    
        
    if(defined('VTMAX_PRO_DIRNAME')) {
      require ( VTMAX_PRO_DIRNAME . '/core/vtmax-delete-purchaser-info.php' ); 
    }
  }
  
  
  /* ************************************************
  **   Admin - Trash Rule
  *************************************************** */   
	public function vtmax_admin_trash_rule(){
     global $post, $vtmax_rules_set; 
     if ( !( 'vtmax-rule' == $post->post_type ) ) {
      return;
     }        
  
     if ( !current_user_can( 'delete_posts', 'vtmax-rule' ) )  {
          return;
     }  
     
     if(did_action('trash_post')) {    
         return;
    }
    
    $vtmax_rule_delete = new VTMAX_Rule_delete;            
    $vtmax_rule_delete->vtmax_trash_rule();

  }
  
  
 /* ************************************************
 **   Admin - Untrash Rule
 *************************************************** */   
	public function vtmax_admin_untrash_rule(){
     global $post, $vtmax_rules_set; 
     if ( !( 'vtmax-rule' == $post->post_type ) ) {
      return;
     }        

     if ( !current_user_can( 'delete_posts', 'vtmax-rule' ) )  {
          return;
     }       
    $vtmax_rule_delete = new VTMAX_Rule_delete;            
    $vtmax_rule_delete->vtmax_untrash_rule();
  }


  /* ************************************************
  **   Admin - Activation Hook
  *************************************************** */  
  function vtmax_activation_hook() {
     //the options are added at admin_init time by the setup_options.php as soon as plugin is activated!!!
    
    //verify the requirements for Vtmin.
    global $wp_version;
		if((float)$wp_version < 3.3){
			// delete_option('vtmax_setup_options');
			 wp_die( __('<strong>Looks like you\'re running an older version of WordPress, you need to be running at least WordPress 3.3 to use the Varktech Maximum Purchase plugin.</strong>', 'vtmax'), __('VT Maximum Purchase not compatible - WP', 'vtmax'), array('back_link' => true));
			return;
		}
    
           
    //fix 2-13-2013 - changed php version_compare, altered error msg   
   if (version_compare(PHP_VERSION, VTMAX_EARLIEST_ALLOWED_PHP_VERSION) < 0) {    //'<0' = 1st value is lower 
			wp_die( __('<strong><em>PLUGIN CANNOT ACTIVATE &nbsp;&nbsp;-&nbsp;&nbsp;     Varktech Maximum Purchase </em>
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   Your installation is running on an older version of PHP 
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;   - your PHP version = ', 'vtmax') .PHP_VERSION. __(' . 
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   You need to be running **at least PHP version 5** to use this plugin.  
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   Please contact your host and request an upgrade to PHP 5+ . 
      <br><br>&nbsp;&nbsp;&nbsp;&nbsp;   Then activate this plugin following the upgrade.</strong>', 'vtmax'), __('VT Min and Max Purchase not compatible - PHP', 'vtmax'), array('back_link' => true));
			return; 
		}
    
 
    //v1.07.6 begin
    /*
    if(defined('WOOCOMMERCE_VERSION') && (VTMAX_PARENT_PLUGIN_NAME == 'WooCommerce')) { 
      $new_version =      '2.6';
      $current_version =  WOOCOMMERCE_VERSION;
      if( (version_compare(strval($new_version), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower 
  			// delete_option('vtmax_setup_options');
  			 wp_die( __('<strong>Looks like you\'re running an older version of WooCommerce. <br>You need to be running at least ** WooCommerce 2.6 **, to use the Varktech Maximum Purchase plugin.</strong>', 'vtmax'), __('VT Maximum Purchase not compatible - WooCommerce', 'vtmax'), array('back_link' => true));
  			return;
  		}
    }   else 
    if (VTMAX_PARENT_PLUGIN_NAME == 'WooCommerce') {
        wp_die( __('<strong>Varktech Maximum Purchase for WooCommerce requires that WooCommerce be installed and activated.</strong>', 'vtmax'), __('WooCommerce not installed or activated', 'vtmax'), array('back_link' => true));
  			return;
    }
    */
    //v1.07.6 begin
    
   }

   //V1.07.1 begin                          
   public function vtmax_admin_notice_version_mismatch() {
      $message  =  '<strong>' . __('Please also update plugin: ' , 'vtmax') . ' &nbsp;&nbsp;'  .VTMAX_PRO_PLUGIN_NAME . '</strong>' ;
      $message .=  '<br>&nbsp;&nbsp;&bull;&nbsp;&nbsp;' . __('Your Pro Version = ' , 'vtmax') .VTMAX_PRO_VERSION. ' &nbsp;&nbsp;' . __(' The Minimum Required Pro Version = ' , 'vtmax') .VTMAX_MINIMUM_PRO_VERSION ;      
      $message .=  '<br>&nbsp;&nbsp;&bull;&nbsp;&nbsp;' . __('Please delete the old Pro plugin from your installation via ftp.'  , 'vtmax');
      $message .=  '<br>&nbsp;&nbsp;&bull;&nbsp;&nbsp;' . __('Go to ', 'vtmax');
      $message .=  '<a target="_blank" href="http://www.varktech.com/download-pro-plugins/">Varktech Downloads</a>';
      $message .=   __(', download and install the newest <strong>'  , 'vtmax') .VTMAX_PRO_PLUGIN_NAME. '</strong>' ;
      
      $admin_notices = '<div id="message" class="error fade" style="background-color: #FFEBE8 !important;"><p>' . $message . ' </p></div>';
      echo $admin_notices;
      
      //V1.07.4 added
      $plugin = VTMAX_PRO_PLUGIN_SLUG;
			if( is_plugin_active($plugin) ) {
			   deactivate_plugins( $plugin );
      }      
            
      
      return;    
  }   
   //V1.07.1 end    

  
  /* ************************************************
  **   Admin - **Uninstall** Hook and cleanup
  *************************************************** */ 
  function vtmax_uninstall_hook() {
      
      if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
      	return;
        //exit ();
      }
  
      delete_option('vtmax_setup_options');
      $vtmax_nuke = new VTMAX_Rule_delete;            
      $vtmax_nuke->vtmax_nuke_all_rules();
      $vtmax_nuke->vtmax_nuke_all_rule_cats();
      
  }
  
} //end class
$vtmax_controller = new VTMAX_Controller;

  //***************************************************************************************
  //fix 2-13-2013  -  problems with activation hook and class, solved herewith...
  //   FROM http://website-in-a-weekend.net/tag/register_activation_hook/
  //***************************************************************************************
  if (is_admin()){ 
        register_activation_hook(__FILE__, array($vtmax_controller, 'vtmax_activation_hook'));
        register_activation_hook(__FILE__, array($vtmax_controller, 'vtmax_uninstall_hook'));                                   
  }
