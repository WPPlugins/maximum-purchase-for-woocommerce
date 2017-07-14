<?php
   
class VTMAX_Rule_update {
	

	public function __construct(){  
        $this->vtmax_update_rule();
    }
            
  public  function vtmax_update_rule () {
      global $post, $vtmax_rule; 
      $post_id = $post->ID;                                                                                                                                                          
      $vtmax_rule_new = new VTMAX_Rule();   //  always  start with fresh copy
      $selected = 's';

      $vtmax_rule = $vtmax_rule_new;  //otherwise vtmax_rule is not addressable!
       
     //*****************************************
     //  FILL / upd VTMAX_RULE...
     //*****************************************
     //   Candidate Population
     
     $vtmax_rule->post_id = $post_id;

     if ( ($_REQUEST['post_title'] > ' ' ) ) {
       //do nothing
     }
     else { 
       $vtmax_rule->rule_error_message[] = __('The Rule needs to have a title, but title is empty.', 'vtmax');
     }
      
     $vtmax_rule->inpop_selection = $_REQUEST['popChoice'];
     switch( $vtmax_rule->inpop_selection ) {
        case 'groups':
              $vtmax_rule->inpop[1]['user_input'] = $selected;
              //  $vtmax_checkbox_classes = new VTMAX_Checkbox_classes;
                  //get all checked taxonomies/roles as arrays

             if(!empty($_REQUEST['tax-input-role-in'])) {
                $vtmax_rule->role_in_checked = $_REQUEST['tax-input-role-in'];
             }
             if ((!$vtmax_rule->prodcat_in_checked) && (!$vtmax_rule->rulecat_in_checked) && (!$vtmax_rule->role_in_checked))  {
                $vtmax_rule->rule_error_message[] = __('In Cart Search Criteria Selection Metabox, "Use Selection Groups" was chosen, but no Categories or Roles checked', 'vtmax');
             }                                  
             //   And/Or switch for category/role relationship  
             $vtmax_rule->role_and_or_in_selection  = $_REQUEST['andorChoice']; 
             $this->vtmax_set_default_or_values();            
          break;
        
      }

  
      
          
     //   Population Handling Specifics   
     $vtmax_rule->specChoice_in_selection = $_REQUEST['specChoice'];
     
     switch( $vtmax_rule->specChoice_in_selection ) {
        case 'all':
            $vtmax_rule->specChoice_in[0]['user_input'] = $selected;
          break;
        case 'each':
            $vtmax_rule->specChoice_in[1]['user_input'] = $selected;
          break;
        case 'any':
            $vtmax_rule->specChoice_in[2]['user_input'] = $selected;
            if (empty($_REQUEST['anyChoice-max'])) {
                $vtmax_rule->rule_error_message[] = __('In Select Rule Application cs Metabox, "*Any* in the Population" was chosen, but Maximum products count not filled in', 'vtmax');
            } else { 
                $vtmax_rule->anyChoice_max['value'] = $_REQUEST['anyChoice-max'];
                if ($vtmax_rule->anyChoice_max['value'] == ' '){
                  $vtmax_rule->rule_error_message[] = __('In Select Rule Application  Metabox, "*Any* in the Population" was chosen, but Maximum products count not filled in', 'vtmax');
                } 
                if ( is_numeric($vtmax_rule->anyChoice_max['value'])  === false  ) {
                   $vtmax_rule->rule_error_message[] = __('In Select Rule Application  Metabox, "*Any* in the Population" was chosen, but Maximum products count not numeric', 'vtmax');              
                }
          }    
          break;
      }
              
       
     //   Maximum Amount for this role
     $vtmax_rule->amtSelected_selection = $_REQUEST['amtSelected']; 
     
     switch( $vtmax_rule->amtSelected_selection ) {
        case 'quantity':
            $vtmax_rule->amtSelected[0]['user_input'] = $selected;
          break;
        case 'currency':
            $vtmax_rule->amtSelected[1]['user_input'] = $selected;
          break;
     } 
     if (empty($_REQUEST['amtChoice-count'])) {
        $vtmax_rule->rule_error_message[] = __('In Maximum Amount for this role Metabox, Maximum Amount not filled in', 'vtmax');
     } else { 
        $vtmax_rule->maximum_amt['value'] = $_REQUEST['amtChoice-count'];
        if ($vtmax_rule->maximum_amt['value'] == ' '){
          $vtmax_rule->rule_error_message[] = __('In Maximum Amount for this role Metabox, Maximum Amount not filled in', 'vtmax');
        }  
        if ( is_numeric($vtmax_rule->maximum_amt['value']) === false  ) {
           $vtmax_rule->rule_error_message[] = __('In Maximum Amount for this role Metabox, Maximum Amount not numeric', 'vtmax');              
        }
     }
     
    
     //   Max Rule Type choice                                                           
     $vtmax_rule->maxRule_typeSelected_selection = $_REQUEST['maxRule-typeSelected']; 
     
     switch( $vtmax_rule->maxRule_typeSelected_selection ) {
        case 'cart':
            $vtmax_rule->maxRule_typeSelected[0]['user_input'] = $selected;
          break;
        case 'lifetime':
            $vtmax_rule->maxRule_typeSelected[1]['user_input'] = $selected;
          break;
     }

     //v1.07 begin
     $vtmax_rule->custMsg_text = $_REQUEST['cust-msg-text'];
     global $vtmax_info; 
     if ( $vtmax_rule->custMsg_text == $vtmax_info['default_full_msg']) {
        $vtmax_rule->custMsg_text = '';   //re-initialize if default msg still there...
     }   
     //v1.07 end       
    //*****************************************
    //  If errors were found, the error message array will be displayed by the UI on next screen send.
    //*****************************************
    if  ( sizeof($vtmax_rule->rule_error_message) > 0 ) {
      $vtmax_rule->rule_status = 'pending';
    } else {
      $vtmax_rule->rule_status = 'publish';
    }
   
    $rules_set_found = false;
    $vtmax_rules_set = get_option( 'vtmax_rules_set' ); 
    if ($vtmax_rules_set) {
      $rules_set_found = true;
    }
          
    if ($rules_set_found) {
      $rule_found = false;
      $sizeof_rules_set = sizeof($vtmax_rules_set);
      for($i=0; $i < $sizeof_rules_set; $i++) { 
         if ($vtmax_rules_set[$i]->post_id == $post_id) {
            $vtmax_rules_set[$i] = $vtmax_rule;
            $i =  $sizeof_rules_set;
            $rule_found = true; 
         }
      }
      if (!$rule_found) {
         $vtmax_rules_set[] = $vtmax_rule;
      } 
    } else {
      $vtmax_rules_set = array ();
      $vtmax_rules_set[] = $vtmax_rule;
    }
  
    if ($rules_set_found) {
      update_option( 'vtmax_rules_set',$vtmax_rules_set );
    } else {
      add_option( 'vtmax_rules_set',$vtmax_rules_set );
    }
     
  } //end function

 //default to 'OR', as the default value goes away and may be needed if the user switches back to 'groups'...
  public function vtmax_set_default_or_values () {
    global $vtmax_rule;  
    $vtmax_rule->role_and_or_in[1]['user_input'] = 's'; //'s' = 'selected'
    $vtmax_rule->role_and_or_in_selection = 'or'; 
  } 

  
} //end class
