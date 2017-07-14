<?php
/*
VarkTech Maximum Purchase for WooCommerce
WOO-specific functions
Parent Plugin Integration
*/


	function vtmax_load_vtmax_cart_for_processing(){
      global $wpdb,  $woocommerce, $vtmax_cart, $vtmax_cart_item, $vtmax_info; 
      
      // from Woocommerce/templates/cart/mini-cart.php  and  Woocommerce/templates/checkout/review-order.php
        
      if (sizeof($woocommerce->cart->get_cart())>0) {
					$vtmax_cart = new VTMAX_Cart;  
          foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
						$_product = $cart_item['data'];
						if ($_product->exists() && $cart_item['quantity']>0) {
							$vtmax_cart_item                = new VTMAX_Cart_Item;
             
              //the product id does not change in woo if variation purchased.  
              //  Load expected variation id, if there, along with constructed product title.
              $varLabels = ' ';
              if ($cart_item['variation_id'] > ' ') {      
                 
                  // get parent title
                  $parent_post = get_post($cart_item['product_id']);
                  
                  // get variation names to string onto parent title
                  foreach($cart_item['variation'] as $key => $value) {          
                    $varLabels .= $value . '&nbsp;';           
                  }
                  
                  $vtmax_cart_item->product_id    = $cart_item['variation_id'];
                  $vtmax_cart_item->product_name  = $parent_post->post_title . '&nbsp;' . $varLabels ;

              } else { 
                  $vtmax_cart_item->product_id    = $cart_item['product_id'];
                  $vtmax_cart_item->product_name  = $_product->get_title().$woocommerce->cart->get_item_data( $cart_item );
              }
  
              
              $vtmax_cart_item->quantity      = $cart_item['quantity'];
              $vtmax_cart_item->unit_price    = get_option( 'woocommerce_display_cart_prices_excluding_tax' ) == 'yes' || $woocommerce->customer->is_vat_exempt() ? $_product->get_price_excluding_tax() : $_product->get_price();
              /*
              $quantity = 1; //v1.07 vat fix
              $vtmax_cart_item->unit_price    = get_option( 'woocommerce_display_cart_prices_excluding_tax' ) == 'yes' || $woocommerce->customer->is_vat_exempt() ? $_product->get_price_excluding_tax() : $_product->get_price_including_tax( $quantity ); //$_product->get_price();   //v1.08 vat fix
              */
                            
              $vtmax_cart_item->total_price   = $vtmax_cart_item->quantity * $vtmax_cart_item->unit_price;
              /*  *********************************
              ***  JUST the cat *ids* please...
              ************************************ */
              $vtmax_cart_item->prod_cat_list = wp_get_object_terms( $cart_item['product_id'], $vtmax_info['parent_plugin_taxonomy'], $args = array('fields' => 'ids') );
              $vtmax_cart_item->rule_cat_list = wp_get_object_terms( $cart_item['product_id'], $vtmax_info['rulecat_taxonomy'], $args = array('fields' => 'ids') );
        
              //add cart_item to cart array
              $vtmax_cart->cart_items[]       = $vtmax_cart_item;

              $vtmax_cart->purchaser_ip_address = $vtmax_info['purchaser_ip_address']; // v1.07.2
				    }
        } //	endforeach;
        
              /*
       ($vtmax_info['get_purchaser_info'] == 'yes') is set in parent-cart-validation.php in 
       function vtmax_wpsc_checkout_form_validation only.  This is executed only at 'pay' button,
       the only time we can be sure that the purchaser info is there.
      */ 
       //     if( defined('VTMAX_PRO_DIRNAME') && ($vtmax_info['get_purchaser_info'] == 'yes') )  {
      if(defined('VTMAX_PRO_DIRNAME')) {
        //v1.07.5 begin
        require ( VTMAX_PRO_DIRNAME . '/woo-integration/vtmax-get-purchaser-info.php' ); 
        //require_once ( VTMAX_PRO_DIRNAME . '/woo-integration/vtmax-get-purchaser-info.php' );   
        //v1.07.5 end 
      }
        
        
		} //end  if (sizeof($woocommerce->cart->get_cart())>0) 
           
  }      

 
   //  checked_list (o) - selection list from previous iteration of rule selection                                 
    function vtmax_fill_variations_checklist ($tax_class, $checked_list = NULL, $product_ID, $product_variation_IDs) { 
        global $post;
        // *** ------------------------------------------------------------------------------------------------------- ***
        // additional code from:  woocommerce/admin/post-types/writepanels/writepanel-product-type-variable.php
        // *** ------------------------------------------------------------------------------------------------------- ***
        //    woo doesn't keep the variation title in post title of the variation ID post, additional logic constructs the title ...
        
        $parent_post = get_post($product_ID);
        
        $attributes = (array) maybe_unserialize( get_post_meta($product_ID, '_product_attributes', true) );
    
        $parent_post_terms = wp_get_post_terms( $post->ID, $attribute['name'] );
       
        // woo parent product title only carried on parent post
        echo '<h3>' .$parent_post->post_title.    ' - Variations</h3>'; 
        
        foreach ($product_variation_IDs as $product_variation_ID) {     //($product_variation_IDs as $product_variation_ID => $info)
            // $variation_post = get_post($product_variation_ID);
         
            $output  = '<li id='.$product_variation_ID.'>' ;
            $output  .= '<label class="selectit">' ;
            $output  .= '<input id="'.$product_variation_ID.'_'.$tax_class.' " ';
            $output  .= 'type="checkbox" name="tax-input-' .  $tax_class . '[]" ';
            $output  .= 'value="'.$product_variation_ID.'" ';
            if ($checked_list) {
                if (in_array($product_variation_ID, $checked_list)) {   //if variation is in previously checked_list   
                   $output  .= 'checked="checked"';
                }                
            }
            $output  .= '>'; //end input statement
 
            $variation_label = ''; //initialize label
            
            //get the variation names
            foreach ($attributes as $attribute) :

									// Only deal with attributes that are variations
									if ( !$attribute['is_variation'] ) continue;

									// Get current value for variation (if set)
									$variation_selected_value = get_post_meta( $product_variation_ID, 'attribute_' . sanitize_title($attribute['name']), true );

									// Get terms for attribute taxonomy or value if its a custom attribute
									if ($attribute['is_taxonomy']) :
										$post_terms = wp_get_post_terms( $product_ID, $attribute['name'] );
										foreach ($post_terms as $term) :
											if ($variation_selected_value == $term->slug) {
                          $variation_label .= $term->name . '&nbsp;&nbsp;' ;
                      }
										endforeach;
									else :
										$options = explode('|', $attribute['value']);
										foreach ($options as $option) :
											if ($variation_selected_value == $option) {
                        $variation_label .= ucfirst($option) . '&nbsp;&nbsp;' ;
                      }
										endforeach;
									endif;

						endforeach;
                
            $output  .= '&nbsp;&nbsp; #' .$product_variation_ID. '&nbsp;&nbsp; - &nbsp;&nbsp;' .$variation_label;
            $output  .= '</label>';            
            $output  .= '</li>'; 
            echo $output ;             
         }         
        return;   
    }
    

  /* ************************************************
  **   Get all variations for product
  *************************************************** */
  function vtmax_get_variations_list($product_ID) {
        
    //sql from woocommerce/classes/class-wc-product.php
   $variations = get_posts( array(
			'post_parent' 	=> $product_ID,
			'posts_per_page'=> -1,
			'post_type' 	  => 'product_variation',
			'fields' 		    => 'ids',
			'post_status'	  => 'publish',
      'order'         => 'ASC'
	  ));
   if ($variations)  {    
      $product_variations_list = array();
      foreach ( $variations as $variation) {
        $product_variations_list [] = $variation;             
    	}
    } else  {
      $product_variations_list;
    }
    
    return ($product_variations_list);
  } 
  
  
  function vtmax_test_for_variations ($prod_ID) { 
      
     $vartest_response = 'no';
     
     /* Commented => DB access method uses more IO/CPU cycles than array processing below...
     //sql from woocommerce/classes/class-wc-product.php
     $variations = get_posts( array(
    			'post_parent' 	=> $prod_ID,
    			'posts_per_page'=> -1,
    			'post_type' 	=> 'product_variation',
    			'fields' 		=> 'ids',
    			'post_status'	=> 'publish'
    		));
     if ($variations)  {
        $vartest_response = 'yes';
     }  */
     
     // code from:  woocommerce/admin/post-types/writepanels/writepanel-product-type-variable.php
     $attributes = (array) maybe_unserialize( get_post_meta($prod_ID, '_product_attributes', true) );
     foreach ($attributes as $attribute) {
       if ($attribute['is_variation'])  {
          $vartest_response = 'yes';
          break;
       }
     }
     
     return ($vartest_response);   
  }     
          
  //fill in currency symbol 
  function vtmax_get_currency_symbol () {       //mwntSYM  
    
     $currency_symbol =  get_woocommerce_currency_symbol();
     
     define('VTMAX_PARENT_PLUGIN_CURRENCY_SYMBOL',  $currency_symbol );
     return;   
  }
  
 
  //v1.07 begin
    
   function vtmax_format_money_element($price) { 
      //from woocommerce/woocommerce-core-function.php   function woocommerce_price
    	$return          = '';
    	$num_decimals    = (int) get_option( 'woocommerce_price_num_decimals' );
    	$currency_pos    = get_option( 'woocommerce_currency_pos' );
    	$currency_symbol = get_woocommerce_currency_symbol();
    	$decimal_sep     = wp_specialchars_decode( stripslashes( get_option( 'woocommerce_price_decimal_sep' ) ), ENT_QUOTES );
    	$thousands_sep   = wp_specialchars_decode( stripslashes( get_option( 'woocommerce_price_thousand_sep' ) ), ENT_QUOTES );
    
    	$price           = apply_filters( 'raw_woocommerce_price', (double) $price );
    	$price           = number_format( $price, $num_decimals, $decimal_sep, $thousands_sep );
    
    	if ( get_option( 'woocommerce_price_trim_zeros' ) == 'yes' && $num_decimals > 0 )
    		$price = woocommerce_trim_zeros( $price );
    
    	//$return = '<span class="amount">' . sprintf( get_woocommerce_price_format(), $currency_symbol, $price ) . '</span>'; 

    $current_version =  WOOCOMMERCE_VERSION;
    if( (version_compare(strval('2'), strval($current_version), '>') == 1) ) {   //'==1' = 2nd value is lower     
      $formatted = number_format( $price, $num_decimals, stripslashes( get_option( 'woocommerce_price_decimal_sep' ) ), stripslashes( get_option( 'woocommerce_price_thousand_sep' ) ) );
      $formatted = $currency_symbol . $formatted;
    } else {
      $formatted = sprintf( get_woocommerce_price_format(), $currency_symbol, $price );
    }
          
     return $formatted;
   }

  function vtmax_debug_options(){   
    global $vtmax_setup_options;
    if ( ( isset( $vtmax_setup_options['debugging_mode_on'] )) &&
         ( $vtmax_setup_options['debugging_mode_on'] == 'yes' ) ) {  
      error_reporting(E_ALL);  
    }  else {
      error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);    //only allow FATAL error types 
    }
  }

  //v1.07 end


  //*************************************************************
  //v1.07.6 begin - MOVED HERE from parent-cart-validation
  //*************************************************************
  // get rid of '$this->' !!!
  // get rid of 'public'
  
  // from woocommerce/classes/class-wc-cart.php 
  function vtmax_woo_get_url ($pageName) {            
     global $woocommerce;
      $checkout_page_id = vtmax_woo_get_page_id($pageName);
  		if ( $checkout_page_id ) {
  			if ( is_ssl() )
  				return str_replace( 'http:', 'https:', get_permalink($checkout_page_id) );
  			else
  				return apply_filters( 'woocommerce_get_checkout_url', get_permalink($checkout_page_id) );
  		}
  }
      
  // from woocommerce/woocommerce-core-functions.php 
  function vtmax_woo_get_page_id ($pageName) { 
    $page = apply_filters('woocommerce_get_' . $pageName . '_page_id', get_option('woocommerce_' . $pageName . '_page_id'));
		return ( $page ) ? $page : -1;
  }    
 /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++    */
    
 
   //v1.07 begin
  /* ************************************************
  **   Application - get current page url
  *       
  *       The code checking for 'www.' is included since
  *       some server configurations do not respond with the
  *       actual info, as to whether 'www.' is part of the 
  *       URL.  The additional code balances out the currURL,
  *       relative to the Parent Plugin's recorded URLs           
  *************************************************** */ 
 function vtmax_currPageURL() {
     global $vtmax_info;
     $currPageURL = vtmax_get_currPageURL();
     $www = 'www.';
     
     $curr_has_www = 'no';
     if (strpos($currPageURL, $www )) {
         $curr_has_www = 'yes';
     }
     
     //use checkout URL as an example of all setup URLs
     $checkout_has_www = 'no';
     if (strpos($vtmax_info['woo_checkout_url'], $www )) {
         $checkout_has_www = 'yes';
     }     
         
     switch( true ) {
        case ( ($curr_has_www == 'yes') && ($checkout_has_www == 'yes') ):
        case ( ($curr_has_www == 'no')  && ($checkout_has_www == 'no') ): 
            //all good, no action necessary
          break;
        case ( ($curr_has_www == 'no') && ($checkout_has_www == 'yes') ):
            //reconstruct the URL with 'www.' included.
            $currPageURL = vtmax_get_currPageURL($www); 
          break;
        case ( ($curr_has_www == 'yes') && ($checkout_has_www == 'no') ): 
            //all of the woo URLs have no 'www.', and curr has it, so remove the string 
            $currPageURL = str_replace($www, "", $currPageURL);
          break;
     } 
 
     return $currPageURL;
  } 
 function vtmax_get_currPageURL($www = null) {
     global $vtmax_info;
     $pageURL = 'http';
     //if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
     if ( isset( $_SERVER["HTTPS"] ) && strtolower( $_SERVER["HTTPS"] ) == "on" ) { $pageURL .= "s";}
     $pageURL .= "://";
     $pageURL .= $www;   //mostly null, only active rarely, 2nd time through - see above
     
     //NEVER create the URL with the port name!!!!!!!!!!!!!!
     $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     /* 
     if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
     } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     }
     */
     return $pageURL;
  }  
   //v1.07 end    

  //*************************************************************
  //v1.07.6 END - MOVED HERE from parent-cart-validation
  //*************************************************************
