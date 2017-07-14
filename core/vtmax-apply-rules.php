<?php

class VTMAX_Apply_Rules{
	
	public function __construct(){
		global $vtmax_cart, $vtmax_rules_set, $vtmax_rule;
    //get pre-formatted rules from options field
    
    $vtmax_rules_set = get_option( 'vtmax_rules_set' );

    // create a new vtmax_cart intermediary area, load with parent cart values.  results in global $vtmax_cart.
    vtmax_load_vtmax_cart_for_processing(); 
    
    $this->vtmax_maximum_purchase_check();
	}


  public function vtmax_maximum_purchase_check() { 
    global $post, $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info;
     
     
    //************************************************
    //BEGIN processing to mark product as participating in the rule or not...
    //************************************************
    
    /*  Analyze each rule, and load up any cart products found into the relevant rule
        fill rule array with product cart data :: load inpop info 
    */  
    $sizeof_vtmax_rules_set = sizeof($vtmax_rules_set);
    $sizeof_cart_items = sizeof($vtmax_cart->cart_items);
     
    for($i=0; $i < $sizeof_vtmax_rules_set; $i++) {                                                               
      if ( $vtmax_rules_set[$i]->rule_status == 'publish' ) {            
        for($k=0; $k < sizeof($vtmax_cart->cart_items); $k++) {                 
            switch( $vtmax_rules_set[$i]->inpop_selection ) {  
              case 'groups':
                  //test if product belongs in rule inpop
                  if ( $this->vtmax_product_is_in_inpop_group($i, $k) ) {
                    $this->vtmax_load_inpop_found_list($i, $k);                        
                  }
                break;
            
            }
                                              
        }   
      } 
    }  //end inpop population processing
    
                                                                                                      
    //************************************************
    //BEGIN processing to mark rules as requiring action y/n
    //************************************************
            
    /*  Analyze each Rule population, and see if they satisfy the rule
    *     identify and label each rule as requiring action = yes/no
    */
    for($i=0; $i < $sizeof_vtmax_rules_set; $i++) {         
        if ( $vtmax_rules_set[$i]->rule_status == 'publish' ) {  
          
          if ( sizeof($vtmax_rules_set[$i]->inpop_found_list) == 0 ) {
             $vtmax_rules_set[$i]->rule_requires_cart_action = 'no';   // cut out unnecessary logic...
          } else {
            
            $vtmax_rules_set[$i]->rule_requires_cart_action = 'pending';
            $sizeof_inpop_found_list = sizeof($vtmax_rules_set[$i]->inpop_found_list);
            /*
                AS only one product can be found with 'single', override to 'all' speeds things along
            */
            if ($vtmax_rules_set[$i]->inpop_selection ==  'single') {
               $vtmax_rules_set[$i]->specChoice_in_selection = 'all' ; 
            }
            
            switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
               case 'all':  //$specChoice_value = 'all'  => total up everything in the population as a unit  
                    if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency'){   //price total
                        if ($vtmax_rules_set[$i]->inpop_total_price <= $vtmax_rules_set[$i]->maximum_amt['value']) {                                                 
                          $vtmax_rules_set[$i]->rule_requires_cart_action = 'no';
                        } else {
                          $vtmax_rules_set[$i]->rule_requires_cart_action = 'yes';
                        }
                    } else {  //qty total
                        if ($vtmax_rules_set[$i]->inpop_qty_total <= $vtmax_rules_set[$i]->maximum_amt['value']) {
                          $vtmax_rules_set[$i]->rule_requires_cart_action = 'no';
                        } else {
                          $vtmax_rules_set[$i]->rule_requires_cart_action = 'yes';
                        }
                    } 
                    if ($vtmax_rules_set[$i]->rule_requires_cart_action == 'yes') {
                       for($k=0; $k < $sizeof_inpop_found_list; $k++) {
                          $this->vtmax_mark_product_as_requiring_cart_action($i,$k);                          
                       }
                    }  		
              		break;
               case 'each': //$specChoice_value = 'each' => apply the rule to each product individually across all products found         		
              		  for($k=0; $k < $sizeof_inpop_found_list; $k++) {
                        if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency'){   //price total
                            if ($vtmax_rules_set[$i]->inpop_found_list[$k]['prod_total_price'] <= $vtmax_rules_set[$i]->maximum_amt['value']){
                               $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] = 'no';
                            }  else {
                               $this->vtmax_mark_product_as_requiring_cart_action($i,$k);
                            }
                        }  else {
                            if ($vtmax_rules_set[$i]->inpop_found_list[$k]['prod_qty'] <= $vtmax_rules_set[$i]->maximum_amt['value']){
                               $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] = 'no';
                            }  else {
                               $this->vtmax_mark_product_as_requiring_cart_action($i,$k);
                            }
                        }
                    }
                        
                  break;
               case 'any':  //$specChoice_value = 'any'  =>   "You may buy a maximum of $10 for each of any of 2 products from this group."       		
              		  //Version 1.01 completely replaced the original case logic
                    $any_action_cnt = 0;
                    for($k=0; $k < $sizeof_inpop_found_list; $k++) {
                        if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency'){   //price total
                            if ($vtmax_rules_set[$i]->inpop_found_list[$k]['prod_total_price'] <= $vtmax_rules_set[$i]->maximum_amt['value']){
                               $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] = 'no';
                            }  else {
                               $this->vtmax_mark_product_as_requiring_cart_action($i,$k);
                               $any_action_cnt++;
                            }
                        }  else {
                            if ($vtmax_rules_set[$i]->inpop_found_list[$k]['prod_qty'] <= $vtmax_rules_set[$i]->maximum_amt['value']){
                               $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] = 'no';
                            }  else {
                               $this->vtmax_mark_product_as_requiring_cart_action($i,$k);
                               $any_action_cnt++;
                            }
                        }
                        //if 'any' limit reached, end the loop, don't mark any mor products as requiring cart action
                        if ($any_action_cnt >= $vtmax_rules_set[$i]->anyChoice_max['value']) {
                            $k = $sizeof_inpop_found_list;   
                        }
                    }                  
                  break;
            }
        }        
      }
    }   
    
    //****************************************************************************
    //   IF WE DON'T DO "apply multiple rules to product", rollout the multples   
    //****************************************************************************
    if ($vtmax_setup_options[apply_multiple_rules_to_product] == 'no' )  {
      $sizeof_cart_items = sizeof($vtmax_cart->cart_items);
      for($k=0; $k < $sizeof_cart_items; $k++) {             //$k = 'cart item'
         if ( sizeof($vtmax_cart->cart_items[$k]->product_participates_in_rule) > 1 ) {  
            //*****************************
            //remove product from **2ND** TO NTH rule, roll quantity and price out of totals for that rule
            //***************************** 
            for($r=1; $r < sizeof($vtmax_cart->cart_items[$k]->product_participates_in_rule); $r++) {   //$r = 'in rule'
              //disambiguation does not apply to products belonging to a varkgroup rule
              if (!$vtmax_cart->cart_items[$k]->product_participates_in_rule[$r]['inpop_selection'] == 'vargroup') {  //does not apply to vargroups!!
                  //use stored occurrences to establish addressability to this rule's info...
                  $rulesetLoc = $vtmax_cart->cart_items[$k]->product_participates_in_rule[$r]['ruleset_occurrence'];
                  $inpopLoc   = $vtmax_cart->cart_items[$k]->product_participates_in_rule[$r]['inpop_occurrence'];
                  //roll the product out of the rule totals, mark as 'no action required' for that rule!  
                  $vtmax_rules_set[$rulesetLoc]->inpop_qty_total   -= $vtmax_rules_set[$rulesetLoc]->inpop_found_list[$inpopLoc]['prod_qty'];
                  $vtmax_rules_set[$rulesetLoc]->inpop_total_price -= $vtmax_rules_set[$rulesetLoc]->inpop_found_list[$inpopLoc]['prod_total_price'];
                  $vtmax_rules_set[$rulesetLoc]->inpop_found_list[$inpopLoc]['prod_requires_action'] = 'no';
                  //if action amounts are 0, turn off action status for rule
                  if ( ($vtmax_rules_set[$rulesetLoc]->inpop_qty_total == 0) && ($vtmax_rules_set[$rulesetLoc]->inpop_total_price == 0) ) {
                    $vtmax_rules_set[$rulesetLoc]->rule_requires_cart_action = 'no'; 
                  }
                  unset ( $vtmax_cart->cart_items[$k]->product_participates_in_rule[$r] );//this array is used later in printing errors in table form 
              }
           }    
         }                                       
      }

    }
     
     
            
    //************************************************
    //BEGIN processing to produce error messages
    //************************************************
    /*
     * For those rules whose product population has failed the rules test,
     *   document the rule failure in an error message
     *   and ***** place the error message into the vtmax cart *****
     *   
     * All of the inpop_found info placed into the rules array during the apply-rules process
     *      is only temporary.  None of that info is stored on the rules array on a 
     *      more permanent basis.  Once the error messages are displayed, they too are discarded
     *      from the rules array (by simply not updating the array on the options table). 
     *      The errors are available to the rules_ui on the error-display go-round because 
     *           the info is held in the global namespace.                                   
    */
    $vtmax_info['error_message_needed'] = 'no';
    for($i=0; $i < $sizeof_vtmax_rules_set; $i++) {               
        if ( $vtmax_rules_set[$i]->rule_status == 'publish' ) {    
            switch( true ) {            
              case ($vtmax_rules_set[$i]->rule_requires_cart_action == 'no'):
                  //no error message for this rule, go to next in loop
                break;  
                  
              case ( ($vtmax_rules_set[$i]->rule_requires_cart_action == 'yes') || ($vtmax_rules_set[$i]->rule_requires_cart_action == 'pending') ):
                                     
                //************************************************
                //Create Error Messages for single or group 
                //************************************************
 
                //errmsg pre-processing
                $this->vtmax_init_recursive_work_elements($i); 
                               
                switch( $vtmax_rules_set[$i]->inpop_selection ) {
                  case 'single': 
                     $vtmax_rules_set[$i]->errProds_total_price = $vtmax_rules_set[$i]->inpop_total_price;
                     $vtmax_rules_set[$i]->errProds_qty         = $vtmax_rules_set[$i]->inpop_qty_total;
                     $vtmax_rules_set[$i]->errProds_ids []      = $vtmax_rules_set[$i]->inpop_found_list[0]['prod_id'];
                     $vtmax_rules_set[$i]->errProds_names []    = $vtmax_rules_set[$i]->inpop_found_list[0]['prod_name'];
                     $this->vtmax_create_text_error_message($i);
                     break; //Error Message Processing *Complete* for this Rule
 
                 default:  // 'groups' or 'cart' or 'vargroup'                                                 
                    
                    if ( $vtmax_rules_set[$i]->inpop_selection  == 'groups' ) {
                    
                      //BEGIN Get Category Names for rule (groups only)
                      $this->vtmax_init_cat_work_elements($i); 
                      
                      if ( ( sizeof($vtmax_rules_set[$i]->prodcat_in_checked) > 0 )  && ($vtmax_setup_options['show_prodcat_names_in_errmsg'] == 'yes' ) ) {  
                        foreach ($vtmax_rules_set[$i]->prodcat_in_checked as $cat_id) { 
                            $cat_info = get_term_by('id', $cat_id, $vtmax_info['parent_plugin_taxonomy'] ) ;
                            If ($cat_info) {
                               $vtmax_rules_set[$i]->errProds_cat_names [] = $cat_info->name;
                            }
                        }
                      }                  
                      if ( ( sizeof($vtmax_rules_set[$i]->rulecat_in_checked) > 0 ) && ($vtmax_setup_options['show_rulecat_names_in_errmsg'] == 'yes' ) ) {  
                        foreach ($vtmax_rules_set[$i]->rulecat_in_checked as $cat_id) { 
                          $cat_info = get_term_by('id', $cat_id, $vtmax_info['rulecat_taxonomy'] ) ;
                          If ($cat_info) {
                             $vtmax_rules_set[$i]->errProds_cat_names [] = $cat_info->name;
                          }
                        }
                      } 
                      //End Category Name Processing (groups only)
                    } 
                    
                    //PROCESS all ERROR products
                    $sizeof_inpop_found_list = sizeof($vtmax_rules_set[$i]->inpop_found_list);
                    for($k=0; $k < $sizeof_inpop_found_list; $k++) {
                      if ($vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] == 'yes'){
                        //aggregate totals and add name into list
                        $vtmax_rules_set[$i]->errProds_qty         += $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_qty'];
                        $vtmax_rules_set[$i]->errProds_total_price += $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_total_price'];
                        $vtmax_rules_set[$i]->errProds_ids []       = $vtmax_rules_set[$i]->inpop_found_list[0]['prod_id'];
                        $vtmax_rules_set[$i]->errProds_names []     = $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_name'];                                             

                        switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                          case 'all':
                              //Don't create a message now,message applies to the whole population, wait until 'for' loop completes to print
                            break;
                          default:  // 'each' and 'any'
                              //message applies to each product as setup in previous processing
                              $this->vtmax_create_text_error_message($i); 
                              //clear out errProds work elements
                              $this->vtmax_init_recursive_work_elements($i);                            
                            break;
                        }  
                                     
                      }
                    }
                    
                    if ( $vtmax_rules_set[$i]->specChoice_in_selection == 'all' ) {    
                       $this->vtmax_create_text_error_message($i);
                    }  
                         
              }  //end messaging
              
              break; 
            } //end proccessing for this rule
            
                           
        }    
    }   //end rule processing
   
    
    //Show error messages in table format, if desired and needed.
    if ( ( $vtmax_setup_options['show_error_messages_in_table_form'] == 'yes' ) && ($vtmax_info['error_message_needed'] == 'yes') ) {
       $this->vtmax_create_table_error_message();
    }
    
    if ( $vtmax_setup_options['debugging_mode_on'] == 'yes' ) {
      error_log( print_r(  '$vtmax_info', true ) );
      error_log( var_export($vtmax_info, true ) );
      error_log( print_r(  '$vtmax_rules_set', true ) );
      error_log( var_export($vtmax_rules_set, true ) );
      error_log( print_r(  '$vtmax_cart', true ) );
      error_log( var_export($vtmax_cart, true ) );
      error_log( print_r(  '$vtmax_setup_options', true ) );
      error_log( var_export($vtmax_setup_options, true ) );  
    }
    
  }  //end vtmax_maximum_purchase_check
  
   
   
   
        
  public function vtmax_create_table_error_message () { 
      global $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info; 
      
      $vtmax_info['line_cnt']++; //line count used in producing height parameter when messages sent to js.
      
      $vtmax_info['cart_color_cnt'] = 0;
      
      $rule_id_list = ' ';
      
      $cart_count = sizeof($vtmax_cart->cart_items);
      
      $message = __('<span id="table-error-messages">', 'vtmax');
      
      $sizeof_rules_set = sizeof($vtmax_rules_set);
      
      
      for($i=0; $i < $sizeof_rules_set; $i++) {               
        if ( $vtmax_rules_set[$i]->rule_requires_cart_action == 'yes' ) { 
          //v1.07 begin
          if ( $vtmax_rules_set[$i]->custMsg_text > ' ') { //custom msg override              
              /*
              ==>> text error msg function always executed, so msg already loaded there - don't load here
              $vtmax_cart->error_messages[] = array (
                'msg_from_this_rule_id' => $vtmax_rules_set[$i]->post_id, 
                'msg_from_this_rule_occurrence' => $i, 
                'msg_text'  => $vtmax_rules_set[$i]->custMsg_text,
                'msg_is_custom'   => 'yes' 
              );
              $this->vtmax_set_custom_msgs_status ('customMsg');
              */
              continue;
           }           
          //v1.07 end        
        
          switch ( $vtmax_rules_set[$i]->specChoice_in_selection ) {
            case  'all' :
                 $vtmax_info['action_cnt'] = 0;
                 $sizeof_inpop_found_list = sizeof($vtmax_rules_set[$i]->inpop_found_list);
                 for($k=0; $k < $sizeof_inpop_found_list; $k++) { 
                    if ($vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] == 'yes'){
                       $vtmax_info['action_cnt']++;
                    }
                 }
                switch (true) {
                  case ( ( $vtmax_rules_set[$i]->inpop_selection == ('cart' || 'groups' || 'vargroup') ) && ( $vtmax_info['action_cnt'] > 1 ) ) : 
                      //this rule = whole cart                      
                      $vtmax_info['bold_the_error_amt_on_detail_line'] = 'no';
                      $message .= $this->vtmax_table_detail_lines_cntl($i);   
                      $message .= $this->vtmax_table_totals_line($i);
                      $message .= $this->vtmax_table_text_line($i);
                    break;

                  case $vtmax_info['action_cnt'] == 1 :
                      $vtmax_info['bold_the_error_amt_on_detail_line'] = 'yes';
                      $message .= $this->vtmax_table_detail_lines_cntl($i);
                      $message .= $this->vtmax_table_text_line($i);
                    break;
                } 
              break;
            case  'each' :
                $vtmax_info['bold_the_error_amt_on_detail_line'] = 'yes';
                $message .= $this->vtmax_table_detail_lines_cntl($i);
                $message .= $this->vtmax_table_text_line($i);
              break;
            case  'any' :
                $vtmax_info['bold_the_error_amt_on_detail_line'] = 'yes';
                $message .= $this->vtmax_table_detail_lines_cntl($i);
                $message .= $this->vtmax_table_text_line($i);
              break;
          
          } 
          $message .= __('<br /><br />', 'vtmax');  //empty line between groups
        }
        
        //new color for next rule
        $vtmax_info['cart_color_cnt']++; 
      } 
    
      //close up owning span
      $message .= __('</span>', 'vtmax'); //end "table-error-messages"
      
      $vtmax_cart->error_messages[] = array (
        'msg_from_this_rule_id' => $rule_id_list, 
        'msg_from_this_rule_occurrence' => '', 
        'msg_text'  => $message,
        'msg_is_custom'   => 'no'    //v1.07 
      );       
      $this->vtmax_set_custom_msgs_status ('standardMsg');     //v1.07       
      
  } 
  
  
        
   public function vtmax_table_detail_lines_cntl ($i) {
      global $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info;
      
      $message_details = $this->vtmax_table_titles();
      
      $sizeof_inpop_found_list = sizeof($vtmax_rules_set[$i]->inpop_found_list);
      //Version 1.01  new IF structure  replaced straight 'for' loop
      if ( $vtmax_rules_set[$i]->specChoice_in_selection == 'all' ) {
         for($r=0; $r < $sizeof_inpop_found_list; $r++) { 
            $k = $vtmax_rules_set[$i]->inpop_found_list[$r]['prod_id_cart_occurrence'];
            $message_details .= $this->vtmax_table_line ($i, $k);  
          }
      } else {    // each or any
        for($r=0; $r < $sizeof_inpop_found_list; $r++) { 
            if ($vtmax_rules_set[$i]->inpop_found_list[$r]['prod_requires_action'] == 'yes'){
              $k = $vtmax_rules_set[$i]->inpop_found_list[$r]['prod_id_cart_occurrence'];
              $message_details .= $this->vtmax_table_line ($i, $k);
           }  
        }
      }
      
      return $message_details;
   }
        
   public function vtmax_table_line ($i, $k){
      global $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info;
     
     
     $message_line;
     $vtmax_info['line_cnt']++;
       
     $message_line .= __('<span class="table-msg-line">', 'vtmax');
     $message_line .= __('<span class="product-column color-grp', 'vtmax');
     $message_line .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
     $message_line .= __('">', 'vtmax');
     $message_line .= $vtmax_cart->cart_items[$k]->product_name;
     $message_line .= __('</span>', 'vtmax'); //end "product" end "color-grp"
     
     if ($vtmax_rules_set[$i]->amtSelected_selection == 'quantity')   {
        $message_line .= __('<span class="quantity-column color-grp', 'vtmax');
        $message_line .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
        if ( $vtmax_info['bold_the_error_amt_on_detail_line'] == 'yes') {
           $message_line .= __(' bold-this', 'vtmax');
        }
        $message_line .= __('">', 'vtmax');
      } else {
        $message_line .= __('<span class="quantity-column">', 'vtmax');  
      }
     $message_line .= $vtmax_cart->cart_items[$k]->quantity;
     if ( ($vtmax_rules_set[$i]->amtSelected_selection == 'quantity') && ($vtmax_info['bold_the_error_amt_on_detail_line'] == 'yes') ) {
       $message_line .= __(' &nbsp;(Error)', 'vtmax');
     }
     $message_line .= __('</span>', 'vtmax'); //end "quantity" end "color-grp"
     
     $message_line .= __('<span class="price-column">', 'vtmax');
     $message_line .= vtmax_format_money_element($vtmax_cart->cart_items[$k]->unit_price);
     //$message_line .= $vtmax_cart->cart_items[$k]->unit_price;
     $message_line .= __('</span>', 'vtmax'); //end "price"
     
     if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency')   {
        $message_line .= __('<span class="total-column color-grp', 'vtmax');
        $message_line .= $vtmax_info['cart_color_cnt'];
        if ( $vtmax_info['bold_the_error_amt_on_detail_line'] == 'yes') {
           $message_line .= __(' bold-this', 'vtmax');
        }
        $message_line .= __('">', 'vtmax');
      } else {
        $message_line .= __('<span class="total-column">', 'vtmax');   
      }
     //$message_line .= $vtmax_cart->cart_items[$k]->total_price;
     $message_line .= vtmax_format_money_element($vtmax_cart->cart_items[$k]->total_price);
     if ( ($vtmax_rules_set[$i]->amtSelected_selection == 'currency') && ($vtmax_info['bold_the_error_amt_on_detail_line'] == 'yes') ) {
       $message_line .= __(' &nbsp;(Error)', 'vtmax');
     }     
     $message_line .= __('</span>', 'vtmax'); //end "total-column"  end "color-grp"
     $message_line .= __('</span>', 'vtmax'); //end "table-msg-line"
     
     //keep a running total
     $vtmax_info['cart_grp_info']['qty']   += $vtmax_cart->cart_items[$k]->quantity; 
     $vtmax_info['cart_grp_info']['price'] += $vtmax_cart->cart_items[$k]->total_price; 
     
     return  $message_line;
   }
   
         
   public function vtmax_table_totals_line ($i){
      global $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info;
      
     $message_totals;
     $vtmax_info['line_cnt']++;
      
     $message_totals .= __('<span class="table-totals-line">', 'vtmax');
     $message_totals .= __('<span class="product-column">', 'vtmax');
     $message_totals .= __('&nbsp;', 'vtmax');
     $message_totals .= __('</span>', 'vtmax'); //end "product"
     
     if ($vtmax_rules_set[$i]->amtSelected_selection == 'quantity')   {
        $message_totals .= __('<span class="quantity-column quantity-column-total color-grp', 'vtmax');
        $message_totals .= $vtmax_info['cart_color_cnt'];
        $message_totals .= __('">(', 'vtmax');
        //grp total qty
        $message_totals .= $vtmax_info['cart_grp_info']['qty'];
        $message_totals .= __(') Error', 'vtmax');
      } else {
        $message_totals .= __('<span class="quantity-column">', 'vtmax');
        $message_totals .= __('&nbsp;', 'vtmax');                                                                                    
      }     
     $message_totals .= __('</span>', 'vtmax'); //end "quantity" "color-grp"
     
     $message_totals .= __('<span class="price-column">', 'vtmax');
     $message_totals .= __('&nbsp;', 'vtmax');
     $message_totals .= __('</span>', 'vtmax'); //end "price"
     
     if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency')   {
        $message_totals .= __('<span class="quantity-column total-column-total color-grp', 'vtmax');
        $message_totals .= $vtmax_info['cart_color_cnt'];
        $message_totals .= __('">(', 'vtmax');
        //grp total price
        $message_totals .= vtmax_format_money_element($vtmax_info['cart_grp_info']['price']);
        $message_totals .= __(') Error', 'vtmax'); 
      } else {
        $message_totals .= __('<span class="quantity-column">', 'vtmax');
        $message_totals .= __('&nbsp;', 'vtmax');
      }
     $message_totals .= __('</span>', 'vtmax'); //end "total" "color-grp"
     $message_totals .= __('</span>', 'vtmax'); //end "table-totals-line"
     
     return $message_totals;
   }
   
   public function vtmax_table_titles() {
     global $vtmax_info;
     $message_title;       
          $message_title  .= __('<span class="table-titles">', 'vtmax');
             $message_title .= __('<span class="product-column product-column-title">Product:</span>', 'vtmax');
             $message_title .= __('<span class="quantity-column quantity-column-title">Quantity:</span>', 'vtmax');
             $message_title .= __('<span class="price-column price-column-title">Price:</span>', 'vtmax');
             $message_title .= __('<span class="total-column total-column-title">Total:</span>', 'vtmax');           
          $message_title .= __('</span>', 'vtmax'); //end "table-titles"
        
      $this->vtmax_init_grp_info();
      
      return $message_title;
   }
   
   public function vtmax_init_grp_info() {
     global $vtmax_info;
     $vtmax_info['cart_grp_info'] = array( 'qty'    => 0,
                                           'price'    => 0
                                          );
   }
/* v1.07   
   public function vtmax_format_money_element($money) { 
     global $vtmax_setup_options; 
           
     $formatted = sprintf("%01.2f", $money); //yields 2places filled right of the dec
     $formatted = VTMAX_PARENT_PLUGIN_CURRENCY_SYMBOL . $formatted;      //mwntSYM
     return $formatted;
   }
*/          
   public function vtmax_table_text_line ($i){
      global $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info;
      
      $message_text;
      $vtmax_info['line_cnt']++;
     
       //SHOW TARGET MIN $/QTY AND CURRENTLY REACHED TOTAL
      
      $message_text .= __('<span class="table-error-msg"><span class="bold-this color-grp', 'vtmax');
      $message_text .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
      $message_text .= __('">', 'vtmax');
      $message_text .= __('Error => </span>', 'vtmax');   //end "color-grp"
      $message_text .= __('Maximum Purchase ', 'vtmax');  
      
      
      if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency') {
        if ( $vtmax_rules_set[$i]->specChoice_in_selection == 'all' ) {
          $message_text .= __('total', 'vtmax');
        }
      } else {
        $message_text .= __(' <span class="color-grp', 'vtmax');
        $message_text .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
        $message_text .= __('">', 'vtmax');
        $message_text .= __('quantity</span>', 'vtmax');    //end "color-grp"
      }
      $message_text .= __(' of <span class="color-grp', 'vtmax'); 
      $message_text .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
      $message_text .= __('">', 'vtmax');
      
      if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency') {
        $message_text .= vtmax_format_money_element($vtmax_rules_set[$i]->maximum_amt['value']);
        $message_text .= __('</span> allowed ', 'vtmax');     //if branch end "color-grp"
      } else {
        $message_text .= $vtmax_rules_set[$i]->maximum_amt['value']; 
        $message_text .= __(' </span>units allowed  ', 'vtmax');    //if branch end "color-grp"
      } 
      
      switch( $vtmax_rules_set[$i]->inpop_selection ) {      
         case 'single' : 
            $message_text .= __('for this product.', 'vtmax');
            break;
         case 'vargroup' : 
            switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                case 'all': 
                    $message_text .= __('for this group.', 'vtmax');
                  break;
                case 'each':
                    $message_text .= __('for each product within the group.', 'vtmax');                             
                  break;
                case 'any':
                    $message_text .= __('for the first ', 'vtmax');
                    $message_text .= __('<span class="color-grp', 'vtmax');
                    $message_text .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
                    $message_text .= __('">', 'vtmax'); 
                    $message_text .= $vtmax_rules_set[$i]->anyChoice_max['value']; 
                    $message_text .= __(' </span>product(s) found within the product group.', 'vtmax');   //end "color-grp"
                                               
                  break;
              }
            break;
         case  'groups' :
             switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                case 'all': 
                    $message_text .= __('for this group.', 'vtmax');
                  break;
                case 'each':
                    $message_text .= __('for each product within the group.', 'vtmax');                             
                  break;
                case 'any':
                    $message_text .= __('for the first ', 'vtmax');
                    $message_text .= __('<span class="color-grp', 'vtmax');
                    $message_text .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
                    $message_text .= __('">', 'vtmax'); 
                    $message_text .= $vtmax_rules_set[$i]->anyChoice_max['value']; 
                    $message_text .= __(' </span>product(s) found within the product group.', 'vtmax');   //end "color-grp"
                                               
                  break;
              }
            break;
         case  'cart' : 
             switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                case 'all': 
                    $message_text .= __('for the cart.', 'vtmax');
                  break;
                case 'each':
                    $message_text .= __('for each product the cart.', 'vtmax');                             
                  break;
                case 'any':
                    $message_text .= __('for the first ', 'vtmax');
                    $message_text .= __('<span class="color-grp', 'vtmax');
                    $message_text .= $vtmax_info['cart_color_cnt'];  //append the count which corresponds to a css color...
                    $message_text .= __('">', 'vtmax'); 
                    $message_text .= $vtmax_rules_set[$i]->anyChoice_max['value']; 
                    $message_text .= __(' </span>product(s) found within the cart.', 'vtmax');  //end "color-grp"                            
                  break;
              }
            break;
      }
      
      //show rule id in error msg      
      if ( ( $vtmax_setup_options['show_rule_ID_in_errmsg'] == 'yes' ) ||  ( $vtmax_setup_options['debugging_mode_on'] == 'yes' ) ) {
        $message_text .= __('<span class="rule-id"> (Rule ID = ', 'vtmax');
        $message_text .= $vtmax_rules_set[$i]->post_id;
        $message_text .= __(') </span>', 'vtmax');
      }
      
          
      $message_text .= __('</span>', 'vtmax'); //end "table-error-msg"  

    
     //SHOW CATEGORIES TO WHICH THIS MSG APPLIES IN GENERAL, IF RELEVANT
      if ( ( $vtmax_rules_set[$i]->inpop_selection <> 'single'  ) && ( sizeof($vtmax_rules_set[$i]->errProds_cat_names) > 0 ) ) {
        $vtmax_info['line_cnt']++;
        $message_text .= __('<span class="table-text-line">', 'vtmax');
        $vtmax_rules_set[$i]->errProds_size = sizeof($vtmax_rules_set[$i]->errProds_cat_names);
        $message_text .= __('<span class="table-text-cats">The maximum purchase rule applies to any products in the following categories: </span><span class="black-font-italic">', 'vtmax');
        for($k=0; $k < $vtmax_rules_set[$i]->errProds_size; $k++) {
            $message_text .= __(' "', 'vtmax');
            $message_text .= $vtmax_rules_set[$i]->errProds_cat_names[$k];
            $message_text .= __('" ', 'vtmax');  
        }        
        $message_text .= __('</span>', 'vtmax');  //end "table-text-cats"
        $message_text .= __('</span>', 'vtmax');  //end "table-text-line"
      } 
        
      return $message_text;     
   }
  
        
   public function vtmax_create_text_error_message ($i) { 
     global $vtmax_setup_options, $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info; 
     
     $vtmax_rules_set[$i]->rule_requires_cart_action = 'yes';
          
      //v1.07 begin
      if ( $vtmax_rules_set[$i]->custMsg_text > ' ') { //custom msg override              
          $vtmax_cart->error_messages[] = array (
            'msg_from_this_rule_id' => $vtmax_rules_set[$i]->post_id, 
            'msg_from_this_rule_occurrence' => $i, 
            'msg_text'  => $vtmax_rules_set[$i]->custMsg_text,
            'msg_is_custom'   => 'yes' 
          );
          $this->vtmax_set_custom_msgs_status('customMsg'); 
          return;
       }           
      //v1.07 end 
   
     if  ( $vtmax_setup_options['show_error_messages_in_table_form'] == 'yes' ) {
        $vtmax_info['error_message_needed'] = 'yes';
        //   $vtmax_cart->error_messages[] = array ('msg_from_this_rule_id' => $vtmax_rules_set[$i]->post_id, 'msg_from_this_rule_occurrence' => $i,'msg_text'  => '' );  
     } else {     
        //SHOW PRODUCT NAME(S) IN ERROR
        $message; //initialize $message
        switch( $vtmax_rules_set[$i]->inpop_selection ) {  
          case 'cart':
              $message .= __('<span class="errmsg-begin">Maximum Purchase Required -</span> for ', 'vtmax');
              switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                case 'all': 
                    //$message .= __('all', 'vtmax');
                  break;
                case 'each':
                    $message .= __('each of', 'vtmax');                             
                  break;
                case 'any':
                    $message .= __('each of', 'vtmax');                             
                  break;
              } 
              $message .= __(' the product(s) in this group: <span class="red-font-italic">', 'vtmax'); 
              $message .= $this->vtmax_list_out_product_names($i);
              $message .= __('</span>', 'vtmax'); 
            break;
          case 'groups':                    
              $message .= __('<span class="errmsg-begin">Maximum Purchase Required -</span> for ', 'vtmax');
              switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                case 'all': 
                    //$message .= __('all', 'vtmax');
                  break;
                case 'each':
                    $message .= __('each of', 'vtmax');                             
                  break;
                case 'any':
                    $message .= __('each of', 'vtmax');                             
                  break;
              }
              $message .= __(' the products in this group: <span class="red-font-italic">', 'vtmax');
              $message .= $this->vtmax_list_out_product_names($i);
              $message .= __('</span>', 'vtmax'); 
            break;
          case 'vargroup':
              $message .= __('<span class="errmsg-begin">Maximum Purchase Required -</span> for ', 'vtmax');
              switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
                case 'all': 
                    $message .= __(' the products in this group: <span class="red-font-italic">', 'vtmax');
                  break;
                default:
                    $message .= __(' this product: <span class="red-font-italic">', 'vtmax');;                             
                  break;

              }
              $message .= $this->vtmax_list_out_product_names($i);
              $message .= __('</span>', 'vtmax'); 
            break;
          case 'single':
              $message .= __('For this product: <span class="red-font-italic">"', 'vtmax'); 
              $message .= $vtmax_rules_set[$i]->errProds_names [0];
              $message .= __('"</span>  ', 'vtmax');
            break;
        }                    
                        
        //SHOW TARGET MIN $/QTY AND CURRENTLY REACHED TOTAL
        if ($vtmax_rules_set[$i]->amtSelected_selection == 'currency')   {
          $message .= __('<br /><span class="errmsg-text">A maximum of &nbsp;<span class="errmsg-amt-required"> ', 'vtmax'); 
          $message .= vtmax_format_money_element( $vtmax_rules_set[$i]->maximum_amt['value'] );
          switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
            case 'all': 
                $message .= __('</span> &nbsp;for the total group may be purchased.  The current total ', 'vtmax');
                $message .= __('for all the products ', 'vtmax'); 
                $message .= __('in the group is: <span class="errmsg-amt-current"> ', 'vtmax');
              break;
            default:    //each or any
                $message .= __('</span> &nbsp;for this product may be purchased.  The current total ', 'vtmax');
                $message .= __('for this product is: ', 'vtmax');                             
              break;

          }
          $message .= vtmax_format_money_element( $vtmax_rules_set[$i]->errProds_total_price );
          $message .= __(' </span></span> ', 'vtmax');

          
        } else {
          $message .= __('<br /><span class="errmsg-text">A maximum quantity of &nbsp;<span class="errmsg-amt-required"> ', 'vtmax'); 
          $message .= $vtmax_rules_set[$i]->maximum_amt['value'];
          switch( $vtmax_rules_set[$i]->specChoice_in_selection ) {
            case 'all': 
                $message .= __(' units</span> &nbsp;&nbsp;for the total group may be purchased.  The current total ', 'vtmax');  
                $message .= __('for all the products ', 'vtmax');
                $message .= __('in the group is: <span class="errmsg-amt-current"> ', 'vtmax');
              break;
            default:
                $message .= __(' units</span> &nbsp;&nbsp;for each product in the group may be purchased.  The current total ', 'vtmax'); 
                $message .= __('for this product is: ', 'vtmax');                              
              break;
          }          
          $message .= $vtmax_rules_set[$i]->errProds_qty;
          if ($vtmax_rules_set[$i]->errProds_qty > 1) {
            $message .= __(' units.</span></span> ', 'vtmax');
          } else {
            $message .= __(' unit.</span></span> ', 'vtmax');
          }
        }
                                                       
      
        //show rule id in error msg      
        if ( ( $vtmax_setup_options['show_rule_ID_in_errmsg'] == 'yes' ) ||  ( $vtmax_setup_options['debugging_mode_on'] == 'yes' ) ) {
          $message .= __('<span class="rule-id"> (Rule ID = ', 'vtmax');
          $message .= $vtmax_rules_set[$i]->post_id;
          $message .= __(') </span>', 'vtmax');
        }
  
        //SHOW CATEGORIES TO WHICH THIS MSG APPLIES IN GENERAL, IF RELEVANT
        if ( ( $vtmax_rules_set[$i]->inpop_selection <> 'single'  ) && ( sizeof($vtmax_rules_set[$i]->errProds_cat_names) > 0 ) ) {
          $vtmax_rules_set[$i]->errProds_size = sizeof($vtmax_rules_set[$i]->errProds_cat_names);
          $message .= __('<br />:: <span class="black-font">The maximum purchase rule applies to any products in the following categories: </span><span class="black-font-italic">', 'vtmax');
          for($k=0; $k < $vtmax_rules_set[$i]->errProds_size; $k++) {
              $message .= __(' "', 'vtmax');
              $message .= $vtmax_rules_set[$i]->errProds_cat_names[$k];
              $message .= __('" ', 'vtmax');
              $message .= __('</span>', 'vtmax');
          }
        }
                
        //queue the message to go back to the screen     
        $vtmax_cart->error_messages[] = array (
            'msg_from_this_rule_id' => $vtmax_rules_set[$i]->post_id,  
            'msg_from_this_rule_occurrence' => $i, 
            'msg_text'  => $message,
            'msg_is_custom'   => 'no'    //v1.07 
          );         
        $this->vtmax_set_custom_msgs_status ('standardMsg');     //v1.07  
             
      }  //end text message formatting
      /*
      if ( $vtmax_setup_options['debugging_mode_on'] == 'yes' ){   
        echo '$message'; echo '<pre>'.print_r($message, true).'</pre>' ;
        echo '$vtmax_rules_set[$i]->errProds_qty = '; echo '<pre>'.print_r($vtmax_rules_set[$i]->errProds_qty, true).'</pre>' ;
        echo '$vtmax_rules_set[$i]->errProds_total_price = ' ; echo '<pre>'.print_r($vtmax_rules_set[$i]->errProds_total_price, true).'</pre>' ;
        echo '$vtmax_rules_set[$i]->errProds_names = '; echo '<pre>'.print_r($vtmax_rules_set[$i]->errProds_names, true).'</pre>' ;
        echo '$vtmax_rules_set[$i]->errProds_cat_names = '; echo '<pre>'.print_r($vtmax_rules_set[$i]->errProds_cat_names, true).'</pre>' ;   
      } 
      */
     
  } 
      
      
   //*************************************  
   //v1.07 new function 
   //*************************************    
   public function vtmax_set_custom_msgs_status ($message_state) { 
      global $vtmax_cart;
      switch( $vtmax_cart->error_messages_are_custom ) {  
        case 'all':
             if ($message_state == 'standardMsg') {
                $vtmax_cart->error_messages_are_custom = 'some';
             }
          break;
        case 'some':
          break;          
        case 'none':
             if ($message_state == 'customMsg') {
                $vtmax_cart->error_messages_are_custom = 'some';
             }
          break; 
        default:  //no state set yet
             if ($message_state == 'standardMsg') {
                $vtmax_cart->error_messages_are_custom = 'none';
             } else {
                $vtmax_cart->error_messages_are_custom = 'all';
             }
          break;                    
      }

      return;
   }      
   //v1.07 end
  
        
   public function vtmax_product_is_in_inpop_group ($i, $k) { 
      global $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info, $vtmax_setup_options;
      /* at this point, the checked list produced at rule store time could be out of sync with the db, as the cats/roles originally selected to be
      *  part of this rule could have been deleted.  this won't affect these loops, as the deleted cats/roles will simply not be in the 
      *  'get_object_terms' list. */

      $vtmax_is_role_in_list  = $this->vtmax_is_role_in_list_test ($i, $k);
      
      if ($vtmax_is_role_in_list) {
         return true;
      }
      
      return false;
   }

  
    public function vtmax_is_role_in_list_test ($i, $k) {
    	global $vtmax_cart, $vtmax_rules_set, $vtmax_rule, $vtmax_info, $vtmax_setup_options;     
      if ( sizeof($vtmax_rules_set[$i]->role_in_checked) > 0 ) {
            if (in_array($this->vtmax_get_current_user_role(), $vtmax_rules_set[$i]->role_in_checked )) {   //if role is in previously checked_list
                  /*
                  if ( $vtmax_setup_options['debugging_mode_on'] == 'yes' ){ 
                    echo 'current user role= <pre>'.print_r($this->vtmax_get_current_user_role(), true).'</pre>' ;
                    echo 'rule id= <pre>'.print_r($vtmax_rules_set[$i]->post_id, true).'</pre>' ;  
                    echo 'role_in_checked= <pre>'.print_r($vtmax_rules_set[$i]->role_in_checked, true).'</pre>' ; 
                    echo 'i= '.$i . '<br>'; echo 'k= '.$k . '<br>';
                  }
                  */
              return true;                                
            } 
      } 
      return false;
    }
    


    public function vtmax_get_current_user_role() {
    	global $current_user;     
    	$user_roles = $current_user->roles;
    	$user_role = array_shift($user_roles);
      if  ($user_role <= ' ') {
        $user_role = 'notLoggedIn';
      }      
    	return $user_role;
      }
      
    public function vtmax_list_out_product_names($i) {
      $prodnames;
    	global $vtmax_rules_set;     
    	for($p=0; $p < sizeof($vtmax_rules_set[$i]->errProds_names); $p++) {
          $prodnames .= __(' "', 'vtmax');
          $prodnames .= $vtmax_rules_set[$i]->errProds_names[$p];
          $prodnames .= __('"  ', 'vtmax');
      } 
    	return $prodnames;
    }
      
   public function vtmax_load_inpop_found_list($i, $k) {
    	global $vtmax_cart, $vtmax_rules_set;
      $vtmax_rules_set[$i]->inpop_found_list[] = array('prod_id' => $vtmax_cart->cart_items[$k]->product_id,
                                                       'prod_name' => $vtmax_cart->cart_items[$k]->product_name,
                                                       'prod_qty' => $vtmax_cart->cart_items[$k]->quantity, 
                                                       'prod_total_price' => $vtmax_cart->cart_items[$k]->total_price,
                                                       'prod_cat_list' => $vtmax_cart->cart_items[$k]->prod_cat_list,
                                                       'rule_cat_list' => $vtmax_cart->cart_items[$k]->rule_cat_list,
                                                       'prod_id_cart_occurrence' => $k, //used to mark product in cart if failed a rule
                                                       'prod_requires_action'  => '' 
                                                      );
     $vtmax_rules_set[$i]->inpop_qty_total   += $vtmax_cart->cart_items[$k]->quantity;
     $vtmax_rules_set[$i]->inpop_total_price += $vtmax_cart->cart_items[$k]->total_price;
   }
     
  public function vtmax_init_recursive_work_elements($i){ 
    global $vtmax_rules_set;
    $vtmax_rules_set[$i]->errProds_qty = 0 ;
    $vtmax_rules_set[$i]->errProds_total_price = 0 ;
    $vtmax_rules_set[$i]->errProds_ids = array() ;
    $vtmax_rules_set[$i]->errProds_names = array() ;    
  }
  public function vtmax_init_cat_work_elements($i){ 
    global $vtmax_rules_set;
    $vtmax_rules_set[$i]->errProds_cat_names = array() ;             
  }     

  public function vtmax_mark_product_as_requiring_cart_action($i,$k){ 
    global $vtmax_rules_set, $vtmax_cart;
    //mark the product in the rules_set
    $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_requires_action'] = 'yes';
    $z = $vtmax_rules_set[$i]->inpop_found_list[$k]['prod_id_cart_occurrence'];
    //prepare for future rollout needs if a rule population conflict ensues
    $vtmax_cart->cart_items[$z]->product_participates_in_rule[] =  
        array(
          'post_id'            => $vtmax_rules_set[$i]->post_id,
          'inpop_selection'    => $vtmax_rules_set[$i]->inpop_selection, //needed to test for 'vargroup'
          'ruleset_occurrence' => $i,
          'inpop_occurrence'   => $k 
        ) ;           
  }     
  

} //end class


