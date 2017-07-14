<?php
/*
VarkTech Maximum Purchase for WooCommerce
Woo-specific functions
Parent Plugin Integration
*/


class VTMAX_Parent_Cart_Validation {
	
	public function __construct(){
     global $vtmax_info, $woocommerce; //$woocommerce_checkout = $woocommerce->checkout();
     /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++   
     *        Apply Maximum Amount Rules to ecommerce activity
     *                                                          
     *          WOO-Specific Checkout Logic and triggers 
     *                                               
     *  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++   */
                                
    //  add actions for early entry into Woo's 3 shopping cart-related pages, and the "place order" button -    
    add_action( 'woocommerce_before_checkout_process', array(&$this, 'vtmax_woo_check_click_to_pay') );  //v1.07.4 

    //v1.07.4  changed to be direct, wp_loaded is correct!! ...
    add_action( 'wp_loaded',                                array(&$this, 'vtmax_woo_apply_checkout_cntl'),99,1 ); //loaded passes no values, but needed for other call!!!
       
    
    //save info to Lifetime tables following purchase       

     add_action('woocommerce_checkout_order_processed', array( &$this, 'vtmax_pre_purchase_save_session' ) );  // v1.07.2
     add_action('woocommerce_thankyou',                 array( &$this, 'vtmax_post_purchase_save_info' ) );    // v1.07.2  

    /*  =============+ */      
                                                                                
	}


           
  /* ************************************************
  **   Application - Apply Rules at E-Commerce Checkout
  *************************************************** */
	public function vtmax_woo_apply_checkout_cntl(){
    global $vtmax_cart, $vtmax_cart_item, $vtmax_rules_set, $vtmax_rule, $vtmax_info, $woocommerce;
    vtmax_debug_options();  //v1.07
    
    //v1.07.4 begin
    if (is_admin() ) {
      return;
    }
    //v1.07.4  end 
      
    //v1.07.6 begin
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }
    //v1.07.6 end
    

    //v1.07.4 begin

/* //v1.07.6  MOVED TO vt-maximum-purchase main file  
    $vtmax_info['woo_cart_url']      =  $this->vtmax_woo_get_url('cart'); 
    $vtmax_info['woo_checkout_url']  =  $this->vtmax_woo_get_url('checkout');
    $vtmax_info['currPageURL']       =  $this->vtmax_currPageURL();
*/  

    $currPageURL      = $vtmax_info['currPageURL'];
    $woo_cart_url     = $vtmax_info['woo_cart_url'];
    $woo_checkout_url = $vtmax_info['woo_checkout_url'];
    
    // if an ITEM HAS BEEN REMOVED, url is apemnded to (&...) , can't look for equality - look for a substring
    //     (if CUSTOM MESSAGE not used, JS message does NOT come across in the situation where all was good, and then an item is removed)
    if ( (strpos($currPageURL,$woo_cart_url )     !== false) ||  //BOOLEAN == true...
         (strpos($currPageURL,$woo_checkout_url ) !== false) ) {  //BOOLEAN == true...
    //v1.09.5  end     
     $carry_on;     
     
    } else {      
      return;
    } 
      //v1.07.4 end
        
     //v1.07.6 begin  clears old messages first
     // wc_clear_notices DOES NOT WORK when a product is deleted!!!!!!!  

     
     if ( (isset ($_SESSION['error_message_sent'])) &&
         ($_SESSION['error_message_sent']) ) {
       $_SESSION['error_message_sent'] = false;
       wc_clear_notices(); //- CLEAR does not work when an item is DELETED, added JS via filter   
     }
     //v1.07.6 end      
          
     $vtmax_apply_rules = new VTMAX_Apply_Rules;   
    
    //ERROR Message Path
    if ( sizeof($vtmax_cart->error_messages) > 0 ) {      
      
       // wc_clear_notices(); //v1.07.5  - clear out existing notices  removed v1.07.6
        
      //v1.07 changes begin
        switch( $vtmax_cart->error_messages_are_custom ) {  
          case 'all':
               $this->vtmax_display_custom_messages();
            break;
          case 'some':    
               $this->vtmax_display_custom_messages();
               $this->vtmax_display_standard_messages();

               //v1.07.4 removed
               /*
               //v1.07.3 begin  
               //  Fixes an AJAX issue - with standard msgs, the inserted JS never gets where it needs to go.
               //     rather than do the standard method, just show  the msgs and FORCE an AJAX exit.
    
               if ( $vtmax_setup_options[max_purch_rule_lifetime_limit_by_ip] != 'yes' )  { //v1.07.6 if ip check included, the msg will be there from previous!!
                 $this->vtmax_display_rule_error_msg_at_checkout('yes');
                 exit();
               } 
               //v1.07.3 end
               */                 
            break;           
          default:  //'none' / no state set yet
               $this->vtmax_display_standard_messages();

               //v1.07.4 removed
               /*             
              $current_version =  WOOCOMMERCE_VERSION;
              if( (version_compare(strval('2.1.0'), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower     
                $woocommerce->add_error(  __('Maximum Purchase error found.', 'vtmin') );  //supplies an error msg and prevents payment from completing 
              } else {
               //added in woo 2.1
                wc_add_notice( __('Maximum Purchase error found.', 'vtmin'), $notice_type = 'error' );   //supplies an error msg and prevents payment from completing   v1.07
              } 
               
               //v1.07.3 begin  
               //  Fixes an AJAX issue - with standard msgs, the inserted JS never gets where it needs to go.
               //     rather than do the standard method, just show  the msgs and FORCE an AJAX exit.
               if ( $vtmax_setup_options[max_purch_rule_lifetime_limit_by_ip] != 'yes' )  { //v1.07.6 if ip check included, the msg will be there from previous!! 
                 $this->vtmax_display_rule_error_msg_at_checkout('yes');
                 exit();
               }  
               //v1.07.3 end
               */                              
            break;                    
        }

      //v1.07 changes end 
    }     
  }

  /* ************************************************
  **   v1.07 New Function
  *************************************************** */
  public function vtmax_display_standard_messages() {
    global $vtmax_cart, $vtmax_cart_item, $vtmax_rules_set, $vtmax_rule, $vtmax_info, $woocommerce;
    //insert error messages into checkout page
    add_action( "wp_enqueue_scripts", array($this, 'vtmax_enqueue_error_msg_css') );
    add_action('wp_head', array(&$this, 'vtmax_display_rule_error_msg_at_checkout') );  //JS to insert error msgs 
    $vtmax_cart->error_messages_processed = 'yes';
  } 

  /* ************************************************
  **   v1.07 New Function
  *************************************************** */
  public function vtmax_display_custom_messages() {
    global $vtmax_cart, $vtmax_cart_item, $vtmax_rules_set, $vtmax_rule, $vtmax_info, $woocommerce;
            
  	//v1.07.6 begin
  	if(!isset($_SESSION)){
  	  session_start();
  	  header("Cache-Control: no-cache");
  	  header("Pragma: no-cache");
  	}
  	//v1.07.6 end
  
    for($i=0; $i < sizeof($vtmax_cart->error_messages); $i++) { 
       if ($vtmax_cart->error_messages[$i]['msg_is_custom'] == 'yes') {  //v1.08 ==>> show custom messages here...
          
              //v1.07 begin
              $current_version =  WOOCOMMERCE_VERSION;
              if( (version_compare(strval('2.1.0'), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower     
                $woocommerce->add_error(  $vtmax_cart->error_messages[$i]['msg_text'] );  //supplies an error msg and prevents payment from completing 
              } else {
               //added in woo 2.1
                wc_add_notice( stripslashes($vtmax_cart->error_messages[$i]['msg_text']), $notice_type = 'error' );
                $_SESSION['error_message_sent'] = true;  //v1.07.6
              } 
              //v1.07 end               
       } //end if
    }  //end 'for' loop    
  }   
  
        
           
  /* ************************************************
  **   Application - Apply Rules at Woo E-Commerce  ==> AT Place Order Time <==
  *************************************************** */
	public function vtmax_woo_check_click_to_pay(){
    global $vtmax_cart, $vtmax_cart_item, $vtmax_rules_set, $vtmax_rule, $vtmax_info, $woocommerce;
        
    //input and output to the apply_rules routine in the global variables.
    //    results are put into $vtmax_cart
    
     vtmax_debug_options();  //v1.07    
     $vtmax_apply_rules = new VTMAX_Apply_Rules;   
    
  /*  *********************************************************************************************************
      These two add_actiions cannot be used to display error msgs in this situation, as they are not executed when
      errors are found at "place order" time in woo land.  They depend on a screen refresh, and woo doesn't do one...
         // add_action( "wp_enqueue_scripts", array($this, 'vtmax_enqueue_error_msg_css') );
         // add_action('wp_head', array(&$this, 'vtmax_display_rule_error_msg_at_checkout') );  //JS to insert error msgs 
      *********************************************************************************************************  
    */


    //v1.07.6 begin
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }
                   
     //clears old messages first  
     if ( (isset ($_SESSION['error_message_sent'])) &&
         ($_SESSION['error_message_sent']) ) {
       $_SESSION['error_message_sent'] = false;
       wc_clear_notices();   
     }

    //v1.07.6 end

    
    //ERROR Message Path
    if ( sizeof($vtmax_cart->error_messages) > 0 ) {  
      
       //v1.07.4 removed
       /*    
      //insert error messages into checkout page
      //this echo may result in multiple versions of the css file being called for, can't be helped.
      echo '<link rel="stylesheet" type="text/css" media="all" href="'.VTMAX_URL.'/core/css/vtmax-error-style.css" />' ;     //mwnt
            
      // WOO crazy error display, in this situation only:
          {"result":"failure","messages":"
            \n\t\t\t
            Maximum Purchase error found.<\/li>\n\t<\/ul>","refresh":"false"}     
      
      //  These are the incorrectly displayed contens of the 'add_notice' function below, and are only a problem in this particular situation
      echo '<div class="woo-apply-checkout-cntl">';  // This 'echo' allows the incorrectly displayed error msg to fall within the 'woo-apply-checkout-cntl' div, and be deleted by following JS
      $woo_apply_checkout_cntl = 'yes';

      //display VTMAX error msgs   
      $this->vtmax_display_rule_error_msg_at_checkout();      
      
      $vtmax_cart->error_messages_processed = 'yes';
      */
      
        
       // wc_clear_notices(); //v1.07.5  - clear out existing notices  v1.07.6 removed
      
      //tell WOO that an error has occurred, and not to proceed further
      
      //v1.07 changes begin
        switch( $vtmax_cart->error_messages_are_custom ) {  
          case 'all':
               $this->vtmax_display_custom_messages();
            break;
          case 'some':    
               $this->vtmax_display_custom_messages();
               $this->vtmax_display_standard_messages();
               
  
                //v1.07.4 added
                for($i=0; $i < sizeof($vtmax_cart->error_messages); $i++) { 
                 if ($vtmax_cart->error_messages[$i]['msg_is_custom'] != 'yes') {  //v1.08 ==>> don't show custom messages here...             
                    $message = '<div class="vtmax-error" id="line-cnt' . $vtmax_info['line_cnt'] .  '"><h3 class="error-title">Maximum Purchase Error</h3><p>' . $vtmax_cart->error_messages[$i]['msg_text']. '</p></div>';
                    wc_add_notice( $message, 'error' );
                    $_SESSION['error_message_sent'] = true;  //v1.07.6
                  }
                }
               
               
               //v1.07.4 removed
               /*  
               //v1.07.3 begin  
               //  Fixes an AJAX issue - with standard msgs, the inserted JS never gets where it needs to go.
               //     rather than do the standard method, just show  the msgs and FORCE an AJAX exit.
    
               if ( $vtmam_setup_options[max_purch_rule_lifetime_limit_by_ip] != 'yes' )  { //v1.07.6 if ip check included, the msg will be there from previous!!
                 $this->vtmam_display_rule_error_msg_at_checkout('yes');
                 exit();
               } 
               //v1.07.3 end
               */
               
                          
			break;           
          default:  //'none' / no state set yet
               $this->vtmax_display_standard_messages();
              
  
                //v1.07.4 added
                for($i=0; $i < sizeof($vtmax_cart->error_messages); $i++) { 
                 if ($vtmax_cart->error_messages[$i]['msg_is_custom'] != 'yes') {  //v1.08 ==>> don't show custom messages here...             
                    $message = '<div class="vtmax-error" id="line-cnt' . $vtmax_info['line_cnt'] .  '"><h3 class="error-title">Maximum Purchase Error</h3><p>' . $vtmax_cart->error_messages[$i]['msg_text']. '</p></div>';
                    wc_add_notice( $message, 'error' );
                    $_SESSION['error_message_sent'] = true;  //v1.07.6
                  }
                }
              
               //v1.07.4 removed
               /*  
              $current_version =  WOOCOMMERCE_VERSION;
              if( (version_compare(strval('2.1.0'), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower     
                $woocommerce->add_error(  __('Maximum Purchase error found.', 'vtmin') );  //supplies an error msg and prevents payment from completing 
              } else {
               //added in woo 2.1
                wc_add_notice( __('Maximum Purchase error found.', 'vtmin'), $notice_type = 'error' );   //supplies an error msg and prevents payment from completing   v1.07
              }  
               
               //v1.07.3 begin  
               //  Fixes an AJAX issue - with standard msgs, the inserted JS never gets where it needs to go.
               //     rather than do the standard method, just show  the msgs and FORCE an AJAX exit.
               if ( $vtmax_setup_options[max_purch_rule_lifetime_limit_by_ip] != 'yes' )  { //v1.07.6 if ip check included, the msg will be there from previous!! 
                 $this->vtmax_display_rule_error_msg_at_checkout('yes');
                 exit();
               }  
               //v1.07.3 end
               */    
			  
            break;                    
        }
        //v1.07 end 
     } //end-if
  }  

  
  /* ************************************************
  **   Application - On Error Display Message on E-Commerce Checkout Screen  
  *************************************************** */ 
  public function vtmax_display_rule_error_msg_at_checkout($woo_apply_checkout_cntl = null){
    global $vtmax_info, $vtmax_cart, $vtmax_setup_options;
      //v1.07.4 removed all JS messaging
      
      //v1.07.6 begin
      if(!isset($_SESSION)){
        session_start();
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
      }
      //v1.07.6 end  
           
      //v1.07.4 added
      for($i=0; $i < sizeof($vtmax_cart->error_messages); $i++) { 
       if ($vtmax_cart->error_messages[$i]['msg_is_custom'] != 'yes') {  //v1.08 ==>> don't show custom messages here...             
          $message = '<div class="vtmax-error" id="line-cnt' . $vtmax_info['line_cnt'] .  '"><h3 class="error-title">Maximum Purchase Error</h3><p>' . $vtmax_cart->error_messages[$i]['msg_text']. '</p></div>';
          wc_add_notice( $message, 'error' );
          $_SESSION['error_message_sent'] = true;  //v1.07.6 
        }
      }
              
     /* ***********************************
        CUSTOM ERROR MSG CSS AT CHECKOUT
        *********************************** */
     if ($vtmax_setup_options[custom_error_msg_css_at_checkout] > ' ' )  {
        echo '<style type="text/css">';
        echo $vtmax_setup_options[custom_error_msg_css_at_checkout];
        echo '</style>';
     }
     
     /*
      Turn off the messages processed switch.  As this function is only executed out
      of wp_head, the switch is only cleared when the next screenful is sent.
     */
     $vtmax_cart->error_messages_processed = 'no';   
 } 
 

  /* ************************************************
  **   Application - On Error enqueue error style
  *************************************************** */
  public function vtmax_enqueue_error_msg_css() {
    wp_register_style( 'vtmax-error-style', VTMAX_URL.'/core/css/vtmax-error-style.css' );  
    wp_enqueue_style('vtmax-error-style');
  } 
 
 
  // v1.07.2 begin
  /* ************************************************
  **   before purchase, save info to session
  *************************************************** */ 
  function vtmax_pre_purchase_save_session() { 
    global $post, $wpdb, $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info;
              
      if(!isset($_SESSION)){
        session_start();
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
      } 
      $data_chain = array();
      $data_chain[] = $vtmax_rules_set;
      $data_chain[] = $vtmax_cart;
      $data_chain[] = $vtmax_info;
      $_SESSION['data_chain'] = serialize($data_chain);  
    
    return; 
    
  } 
  // v1.07.2 end
  
  /* ************************************************
  **   After purchase, store max purchase info for lifetime rules on db
  *************************************************** */ 
  function vtmax_post_purchase_save_info() { 
          
    if(defined('VTMAX_PRO_DIRNAME')) {
      require ( VTMAX_PRO_DIRNAME . '/woo-integration/vtmax-save-purchase-info.php');
    }
    
    return; 
    
  } // end  function vtmax_store_max_purchaser_info() 
 
 
   // v1.07.2 begin
   function vtmax_get_data_chain() {
         
      if(!isset($_SESSION)){
        session_start();
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
      }   
      global $vtmax_rules_set, $vtmax_cart, $vtmax_info;
      
      if (isset($_SESSION['data_chain'])) {
        $data_chain      = unserialize($_SESSION['data_chain']);
      } else {
        $data_chain = array();
      }
         
      //v1.07.4 begin
      /*  REMOVED - do data chain moves ALWAYS
      if ($vtmax_rules_set == '') {        
        $vtmax_rules_set = $data_chain[0];
        $vtmax_cart      = $data_chain[1];
        $vtmax_info      = $data_chain[2];
      }
      */  
      $vtmax_rules_set = $data_chain[0];
      $vtmax_cart      = $data_chain[1];
      $vtmax_info      = $data_chain[2];
      //v1.07.4 end
      

      return $data_chain;
   }
   // v1.07.2  end
   
} //end class
$vtmax_parent_cart_validation = new VTMAX_Parent_Cart_Validation;
