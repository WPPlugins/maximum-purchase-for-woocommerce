<?php
class VTMAX_Rule_delete {
	
	public function __construct(){
     
    }
    
  public  function vtmax_delete_rule () {
    global $post, $vtmax_info, $vtmax_rules_set, $vtmax_rule;
    $post_id = $post->ID;    
    $vtmax_rules_set = get_option( 'vtmax_rules_set' ) ;
    $sizeof_rules_set = sizeof($vtmax_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 
       if ($vtmax_rules_set[$i]->post_id == $post_id) {
          unset ($vtmax_rules_set[$i]);   //this is the 'delete'
          $i =  $sizeof_rules_set; 
       }
    }
   
    if (count($vtmax_rules_set) == 0) {
      delete_option( 'vtmax_rules_set' );
    } else {
      update_option( 'vtmax_rules_set', $vtmax_rules_set );
    }
 }  
 
  /* Change rule status to 'pending'
        if status is 'pending', the rule will not be executed during cart processing 
  */ 
  public  function vtmax_trash_rule () {
    global $post, $vtmax_info, $vtmax_rules_set, $vtmax_rule;
    $post_id = $post->ID;    
    $vtmax_rules_set = get_option( 'vtmax_rules_set' ) ;
    $sizeof_rules_set = sizeof($vtmax_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 
       if ($vtmax_rules_set[$i]->post_id == $post_id) {
          if ( $vtmax_rules_set[$i]->rule_status =  'publish' ) {    //only update if necessary, may already be pending
            $vtmax_rules_set[$i]->rule_status =  'pending';
            update_option( 'vtmax_rules_set', $vtmax_rules_set ); 
          }
          $i =  $sizeof_rules_set; //set to done
       }
    }
 }  

  /*  Change rule status to 'publish' 
        if status is 'pending', the rule will not be executed during cart processing  
  */
  public  function vtmax_untrash_rule () {
    global $post, $vtmax_info, $vtmax_rules_set, $vtmax_rule;
    $post_id = $post->ID;     
    $vtmax_rules_set = get_option( 'vtmax_rules_set' ) ;
    $sizeof_rules_set = sizeof($vtmax_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 
       if ($vtmax_rules_set[$i]->post_id == $post_id) {
          if  ( sizeof($vtmax_rules_set[$i]->rule_error_message) > 0 ) {   //if there are error message, the status remains at pending
            //$vtmax_rules_set[$i]->rule_status =  'pending';   status already pending
            global $wpdb;
            $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id ) );    //match the post status to pending, as errors exist.
          }  else {
            $vtmax_rules_set[$i]->rule_status =  'publish';
            update_option( 'vtmax_rules_set', $vtmax_rules_set );  
          }
          $i =  $sizeof_rules_set;   //set to done
       }
    }
 }  
 
     
  public  function vtmax_nuke_all_rules() {
    global $post, $vtmax_info;
    
   //DELETE all posts from CPT
   $myPosts = get_posts( array( 'post_type' => 'vtmax-rule', 'number' => 500, 'post_status' => array ('draft', 'publish', 'pending', 'future', 'private', 'trash' ) ) );
   //$mycustomposts = get_pages( array( 'post_type' => 'vtmax-rule', 'number' => 500) );
   foreach( $myPosts as $mypost ) {
     // Delete's each post.
     wp_delete_post( $mypost->ID, true);
    // Set to False if you want to send them to Trash.
   }
    
   //DELETE matching option array
   delete_option( 'vtmax_rules_set' );
 }  
     
  public  function vtmax_nuke_all_rule_cats() {
    global $vtmax_info;
    
   //DELETE all rule category entries
   $terms = get_terms($vtmax_info['rulecat_taxonomy'], 'hide_empty=0&parent=0' );
   $count = count($terms);
   if ( $count > 0 ){  
       foreach ( $terms as $term ) {
          wp_delete_term( $term->term_id, $vtmax_info['rulecat_taxonomy'] );
       }
   } 
 }  
      
  public  function vtmax_repair_all_rules() {
    global $wpdb, $post, $vtmax_info, $vtmax_rules_set, $vtmax_rule;    
    $vtmax_rules_set = get_option( 'vtmax_rules_set' ) ;
    $sizeof_rules_set = sizeof($vtmax_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 
       $test_post = get_post($vtmax_rules_set[$i]->post_id );
       if ( !$test_post ) {
           unset ($vtmax_rules_set[$i]);   //this is the 'delete'
       }
    } 
    
    if (count($vtmax_rules_set) == 0) {
      delete_option( 'vtmax_rules_set' );
    } else {
      update_option( 'vtmax_rules_set', $vtmax_rules_set );
    }
 }
       
  public  function vtmax_nuke_max_purchase_history() {
    global $wpdb;    
    $purchaser_table = $wpdb->prefix.'vtmax_rule_purchaser';
    $product_table = $wpdb->prefix.'vtmax_rule_product';
    $wpdb->query("DROP TABLE IF EXISTS $purchaser_table");
    $wpdb->query("DROP TABLE IF EXISTS $product_table");

  }

} //end class
