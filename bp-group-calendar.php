<?php
/*
Plugin Name: BP Group Calendar
Version: 1.0.1
Plugin URI: http://incsub.com
Description: Adds event calendar functionality to Buddypress Groups. Must be activated site-wide.
Author: Aaron Edwards at uglyrobot.com (for Incsub)
Author URI: http://uglyrobot.com
Site Wide Only: true

Copyright 2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


$bp_group_calendar_current_version = '1.0.0';

//------------------------------------------------------------------------//

//---Config---------------------------------------------------------------//

//------------------------------------------------------------------------//

//include calendar class
require_once(WP_PLUGIN_DIR . '/bp-group-calendar/groupcalendar/calendar.class.php');

//------------------------------------------------------------------------//

//---Hook-----------------------------------------------------------------//

//------------------------------------------------------------------------//

//check for activating

if ($_GET['key'] == '' || $_GET['key'] === '') {
	add_action('admin_head', 'bp_group_calendar_make_current');
}

add_action( 'wp', 'bp_group_calendar_menu');
add_action( 'admin_menu', 'bp_group_calendar_menu');
add_action( 'plugins_loaded', 'bp_group_calendar_menu' );
add_action( 'plugins_loaded', 'bp_group_calendar_localization' );
add_action( 'groups_group_after_save', 'bp_group_calendar_settings_save' );
add_action( 'bp_after_group_settings_creation_step', 'bp_group_calendar_settings');
add_action( 'bp_after_group_settings_admin', 'bp_group_calendar_settings');
add_action( 'wp_head', 'bp_group_calendar_css_output');
add_action( 'bp_after_group_activity', 'bp_group_calendar_widget_upcoming_events');

//------------------------------------------------------------------------//

//---Functions------------------------------------------------------------//

//------------------------------------------------------------------------//

function bp_group_calendar_localization() {
  // Load up the localization file if we're using WordPress in a different language
	// Place it in this plugin's "languages" folder and name it "bp-group-calendar-[value in wp-config].mo"
	load_plugin_textdomain( 'groupcalendar', FALSE, '/bp-group-calendar/languages' );
}

function bp_group_calendar_make_current() {

	global $wpdb, $bp_group_calendar_current_version;

	if (get_site_option( "bp_group_calendar_version" ) == '') {
		add_site_option( 'bp_group_calendar_version', '0.0.0' );
	}

	if (get_site_option( "bp_group_calendar_version" ) == $bp_group_calendar_current_version) {
		// do nothing
	} else {
		//update to current version
		update_site_option( "bp_group_calendar_installed", "no" );
		update_site_option( "bp_group_calendar_version", $bp_group_calendar_current_version );
	}
	bp_group_calendar_global_install();
}


function bp_group_calendar_global_install() {

	global $wpdb, $bp_group_calendar_current_version;

	if (get_site_option( "bp_group_calendar_installed" ) == '') {
		add_site_option( 'bp_group_calendar_installed', 'no' );
	}

	if (get_site_option( "bp_group_calendar_installed" ) == "yes") {
		// do nothing
	} else {
		$bp_group_calendar_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "bp_groups_calendars` (
                                  `id` bigint(20) unsigned NOT NULL auto_increment,
                                  `group_id` bigint(20) NOT NULL default '0',
                                  `user_id` bigint(20) NOT NULL default '0',
                                  `event_time` DATETIME NOT NULL,
                                  `event_title` TEXT NOT NULL,
                                  `event_description` TEXT,
                                  `event_location` TEXT,
                                  `event_map` BOOL NOT NULL,
                                  `created_stamp` bigint(30) NOT NULL,
                                  `last_edited_id` bigint(20) NOT NULL default '0',
                                  `last_edited_stamp` bigint(30) NOT NULL,
                                  PRIMARY KEY  (`id`)                              
                                ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";

		$wpdb->query( $bp_group_calendar_table1 );
		update_site_option( "bp_group_calendar_installed", "yes" );
	}
}


function bp_group_calendar_menu() {

	global $bp, $current_blog, $group_obj;

	if ( $group_id = BP_Groups_Group::group_exists($bp->current_action) ) {

		/* This is a single group page. */
		$bp->is_single_item = true;
		$bp->groups->current_group = &new BP_Groups_Group( $group_id );

		/* Pre 1.1 backwards compatibility - use $bp->groups->current_group instead */
		$group_obj = &$bp->groups->current_group;

	}	

	//$groups_link = $bp->loggedin_user->domain . $bp->groups->slug . '/';
	$groups_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/';

	/* Add the subnav item only to the single group nav item */
	if ( $bp->is_single_item )
    bp_core_new_subnav_item( array( 'name' => __( 'Calendar', 'groupcalendar' ), 'slug' => 'calendar', 'parent_url' => $groups_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'bp_group_calendar', 'position' => 35, 'item_css_id' => 'group-calendar' ) );

}


function bp_group_calendar() {

	global $bp;

	add_action( 'bp_template_content', 'bp_group_calendar_output' );

	bp_core_load_template( 'plugin-template' );

}



function bp_group_calendar_settings_save($group) {

	global $wpdb;

	if ( !empty($_POST['group-calendar-moderator-capabilities']) ) {

		groups_update_groupmeta( $group->id, 'group_calendar_moderator_capabilities', $_POST['group-calendar-moderator-capabilities']);

	}

	if ( !empty($_POST['group-calendar-member-capabilities']) ) {

		groups_update_groupmeta( $group->id, 'group_calendar_member_capabilities', $_POST['group-calendar-member-capabilities']);

	}

}


function bp_group_calendar_event_save() {
  global $wpdb, $current_user;

  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  if (isset($_POST['create-event'])) {
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'bp_group_calendar')) {
      bp_core_add_message( __('There was a security problem', 'groupcalendar'), 'error' );
      return false;
    }
    
    //reject unqualified users
    if ($calendar_capabilities == 'none') {
      bp_core_add_message( __("You don't have permission to edit events", 'groupcalendar'), 'error' );
      return false;
    }
    
    //prepare fields
    $group_id = (int)$_POST['group-id'];
    $event_title = htmlentities(strip_tags(trim($_POST['event-title'])));
    
    //check that required title isset after filtering
    if (empty($event_title)) {
      bp_core_add_message( __("An event title is required", 'groupcalendar'), 'error' );
      return false;
    }
    
    $tmp_date = $_POST['event-date'].' '.$_POST['event-hour'].':'.$_POST['event-minute'].$_POST['event-ampm'];
    $tmp_date = strtotime($tmp_date);
    //check for valid date/time
    if ($tmp_date && strtotime($_POST['event-date']) && strtotime($_POST['event-date']) != -1) {
      $event_date = date('Y-m-d H:i:s', $tmp_date);
    } else {
      bp_core_add_message( __("Please enter a valid event date.", 'groupcalendar'), 'error' );
      return false;
    }
    
    $event_description = wp_filter_post_kses(wpautop($_POST['event-desc']));
    $event_location = htmlentities(strip_tags(trim($_POST['event-loc'])));
    $event_map = ($_POST['event-map']==1) ? 1 : 0;
    
    //editing previous event
    if (isset($_POST['event-id'])) {
    
      //can user modify this event?
      if ($calendar_capabilities == 'limited') {
        $creator_id = $wpdb->get_var("SELECT user_id FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE id = ".(int)$_POST['event-id']." AND group_id = $group_id");
        
        if ($creator_id != $current_user->ID) {
          bp_core_add_message( __("You don't have permission to edit that event", 'groupcalendar'), 'error' );
          return false;
        }
      }
      
      $query = $wpdb->prepare("UPDATE " . $wpdb->base_prefix . "bp_groups_calendars
                            	SET event_time = %s, event_title = %s, event_description = %s, event_location = %s, event_map = %d, last_edited_id = %d, last_edited_stamp = %d 
                            	WHERE id = %d AND group_id = %d LIMIT 1", 
                              $event_date, $event_title, $event_description, $event_location, $event_map, $current_user->ID, time(), (int)$_POST['event-id'], $group_id );
      
      if ($wpdb->query($query)) {
        bp_core_add_message( __("Event saved", 'groupcalendar') );
        bp_group_calendar_event_add_action_message(false, (int)$_POST['event-id'], $event_date, $event_title);
        return true;
      } else {
        bp_core_add_message( __("There was a problem saving to the DB", 'groupcalendar'), 'error' );
        return false;
      }
      
    } else { //new event
      
      $query = $wpdb->prepare("INSERT INTO " . $wpdb->base_prefix . "bp_groups_calendars
                            	( group_id, user_id, event_time, event_title, event_description, event_location, event_map, created_stamp, last_edited_id, last_edited_stamp )
                            	VALUES ( %d, %d, %s, %s, %s, %s, %d, %d, %d, %d )", 
                              $group_id, $current_user->ID, $event_date, $event_title, $event_description, $event_location, $event_map, time(), $current_user->ID, time() );
      
      if ($wpdb->query($query)) {
        bp_core_add_message( __("Event saved", 'groupcalendar') );
        bp_group_calendar_event_add_action_message(true, $wpdb->insert_id, $event_date, $event_title);
        return true;
      } else {
        bp_core_add_message( __("There was a problem saving to the DB", 'groupcalendar'), 'error' );
        return false;
      }
      
    }
    
  }
}

//adds actions to the group recent actions
function bp_group_calendar_event_add_action_message($new, $event_id, $event_date, $event_title) {
  global $bp;
  
  $url = bp_group_calendar_create_event_url($event_id);
  
  if ($new) {
    $created_type = __('created', 'groupcalendar');
    $component_action = 'new_calendar_event';
  } else {
    $created_type = __('modified', 'groupcalendar');
    $component_action = 'edit_calendar_event';
  }
  
  $date = date(__('m/d/Y g:ia', 'groupcalendar'), strtotime($event_date));
  
  /* Record this in group activity stream */
	$activity_content = sprintf( __( '%s %s an event for the group %s:', 'groupcalendar'), bp_core_get_userlink( $bp->loggedin_user->id ), $created_type, '<a href="' . bp_get_group_permalink( $bp->groups->current_group ) . '">' . attribute_escape( $bp->groups->current_group->name ) . '</a>' );
	$activity_content .= '<blockquote><a href="'.$url.'" title="'.__('View Event', 'groupcalendar').'">'.stripslashes($event_title).': '.$date.'</a></blockquote>';
	
	groups_record_activity( array(
		'content' => $activity_content, 
		'primary_link' => $url,
		'component_action' => $component_action,
		'item_id' => $bp->groups->current_group->id,
		'secondary_item_id' => $event_id
	) );

}


function bp_group_calendar_get_capabilities() {

  if ( bp_group_is_admin() ) {
  	return 'full';
  } else if ( bp_group_is_mod() ) {  
  
  	$group_calendar_moderator_capabilities = groups_get_groupmeta( bp_get_group_id(), 'group_calendar_moderator_capabilities' );  
    if ( empty( $group_calendar_moderator_capabilities ) ){
  		return 'full';
  	} else {  
  		return $group_calendar_moderator_capabilities;  
  	}
  	
  } else if ( bp_group_is_member() ) {
  
  	$group_calendar_member_capabilities = groups_get_groupmeta( bp_get_group_id(), 'group_calendar_member_capabilities' );
  	if ( empty( $group_calendar_member_capabilities ) ){ 
  		return 'limited'; 
  	} else {  
  		return $group_calendar_member_capabilities; 
  	} 
  	
  } else { 
  	return 'none';
  }
   
}

function bp_group_calendar_url_parse() {

	global $wpdb, $current_site;

	$calendar_url = $_SERVER['REQUEST_URI'];

	$calendar_url_clean = explode("?",$calendar_url);

	$calendar_url = $calendar_url_clean[0];

	$calendar_url_sections = explode("/calendar/",$calendar_url);

	$calendar_url = $calendar_url_sections[1];

	$calendar_url = ltrim($calendar_url, "/");

	$calendar_url = rtrim($calendar_url, "/");

	$base = $calendar_url_sections[0] . '/calendar/';

	$base = ltrim($base, "/");

	$base = rtrim($base, "/");

	if ( !empty( $calendar_url ) ) {

		$calendar_url = ltrim($calendar_url, "/");
		$calendar_url = rtrim($calendar_url, "/");
		$calendar_url_sections = explode("/",$calendar_url);		
    
    //check for valid dates.
    if ($calendar_url_sections[0] >= 2000 && $calendar_url_sections[0] < 3000)
		  $date['year'] = (int)$calendar_url_sections[0];
		  
		if ($date['year'] && $calendar_url_sections[1] >= 1 && $calendar_url_sections[1] <= 12)  
		  $date['month'] = (int)$calendar_url_sections[1];
		  
		if ($date['month'] && $calendar_url_sections[2] >= 1 && $calendar_url_sections[2] <= 31)
		  $date['day'] = (int)$calendar_url_sections[2];

	}

	$date['base'] = $base;

	return $date;

}

function bp_group_calendar_event_url_parse() {
  
	$url = $_SERVER['REQUEST_URI'];
	if (strpos("/calendar/event/", $url) !== false)
    return false;
	
	$url_clean = explode("?", $url);
	$url = $url_clean[0];
	$url_sections = explode("/calendar/event/", $url);
	$url = $url_sections[1];
	$url = ltrim($url, "/");
	$url = rtrim($url, "edit/");
	$url = rtrim($url, "delete/");
	$url = rtrim($url, "/");
  
  if (is_numeric($url))
    return (int)$url;
  else
    return false;

}

function bp_group_calendar_event_is_edit() {

	$url = $_SERVER['REQUEST_URI'];
	if (strpos($url, "/edit/"))
    return true;
  else
    return false;

}

function bp_group_calendar_event_is_delete() {
  global $wpdb, $current_user, $bp;
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  $event_id = bp_group_calendar_event_url_parse();
  $group_id = $bp->groups->current_group->id;
  $url = $_SERVER['REQUEST_URI'];
	if (strpos($url, "/delete/") && $event_id) {
	
    //check nonce
	  if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'event-delete-link')) {
      bp_core_add_message( __('There was a security problem', 'groupcalendar'), 'error' );
      return false;
    }
    
    if ($calendar_capabilities=='none') {
      bp_core_add_message( __('You do not have permission to delete events', 'groupcalendar'), 'error' );
      return false;
    }
    
    //can user modify this event?
    if ($calendar_capabilities == 'limited') {
      $creator_id = $wpdb->get_var("SELECT user_id FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE id = $event_id AND group_id = $group_id");
      
      if ($creator_id != $current_user->ID) {
        bp_core_add_message( __("You don't have permission to delete that event", 'groupcalendar'), 'error' );
        return false;
      }
    }
    
    //delete event
    $result = $wpdb->query("DELETE FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE id = $event_id AND group_id = $group_id LIMIT 1");
    
    if (!$result) {
      bp_core_add_message( __("There was a problem deleting", 'groupcalendar'), 'error' );
      return false;
    }
    
    //success!
    bp_core_add_message( __('Event deleted successfully', 'groupcalendar') );
    return true;
    
  } else {
    return false;
  }

}


function bp_group_calendar_create_event_url($event_id, $edit=false) {
  global $bp;
  
  $url = bp_get_group_permalink( $bp->groups->current_group );
  $url .= '/calendar/event/'.$event_id.'/';
  
  if ($edit)
    $url .= "edit/";

  return $url;
  
}

//------------------------------------------------------------------------//

//---Output Functions-----------------------------------------------------//

//------------------------------------------------------------------------//



function bp_group_calendar_settings() {

	global $wpdb, $current_site, $groups_template;

	if (!empty($groups_template->group)) {

		$group = $groups_template->group;

	}

	if ( !empty($group) ) {

		$group_calendar_moderator_capabilities = groups_get_groupmeta( $group->id, 'group_calendar_moderator_capabilities' );

	}

	if ( empty( $group_calendar_moderator_capabilities ) ){

		$group_calendar_moderator_capabilities = 'full';

	}

	if ( !empty($group) ) {

		$group_calendar_member_capabilities = groups_get_groupmeta( $group->id, 'group_calendar_member_capabilities' );

	}

	if ( empty( $group_calendar_member_capabilities ) ){

		$group_calendar_member_capabilities = 'limited';

	}

	?>

				<label for="group-calendar-moderator-capabilities"><?php _e('Group Calendar: Moderator Capabilities', 'groupcalendar') ?> <? //_e( '(required)', 'groupcalendar' )?></label>

                <select name="group-calendar-moderator-capabilities" id="group-calendar-moderator-capabilities">

                    <option value="full" <?php if ( $group_calendar_moderator_capabilities == 'full') { echo 'selected="selected"'; } ?>><?php _e('Create events / Edit all events', 'groupcalendar'); ?></option>

                    <option value="limited" <?php if ( $group_calendar_moderator_capabilities == 'limited') { echo 'selected="selected"'; } ?>><?php _e('Create events / Edit own events', 'groupcalendar'); ?></option>

                    <option value="none" <?php if ( $group_calendar_moderator_capabilities == 'none') { echo 'selected="selected"'; } ?>><?php _e('No capabilities', 'groupcalendar'); ?></option>

                </select>

				<label for="group-calendar-member-capabilities"><?php _e('Group Calendar: Member Capabilities', 'groupcalendar') ?> <? //_e( '(required)', 'buddypress' )?></label>

                <select name="group-calendar-member-capabilities" id="group-calendar-member-capabilities">

                    <option value="full" <?php if ( $group_calendar_member_capabilities == 'full') { echo 'selected="selected"'; } ?>><?php _e('Create events / Edit all events', 'groupcalendar'); ?></option>

                    <option value="limited" <?php if ( $group_calendar_member_capabilities == 'limited') { echo 'selected="selected"'; } ?>><?php _e('Create events / Edit own events', 'groupcalendar'); ?></option>

                    <option value="none" <?php if ( $group_calendar_member_capabilities == 'none') { echo 'selected="selected"'; } ?>><?php _e('No capabilities', 'groupcalendar'); ?></option>

                </select>

	<?php

}


function bp_group_calendar_output() {

	if ( file_exists( STYLESHEETPATH . '/groupcalendar/calendar.php' ) ) {

	    load_template( STYLESHEETPATH . '/groupcalendar/calendar.php' );

	} else {

    	load_template( WP_PLUGIN_DIR . '/bp-group-calendar/groupcalendar/calendar.php' );

	}

}

function bp_group_calendar_css_output() {
  //display css
  $css_url = plugins_url('/bp-group-calendar/groupcalendar/');
	if (get_option('current_theme') == 'BuddyPress Default') {
    ?>
    <style type="text/css">
      li a#group-calendar { 
        background: url(<?php echo $css_url; ?>calendar_bullet.gif) 88% 52% no-repeat;
        display:block;
        margin-right:0.85em;
        padding:0.55em 3.1em 0.55em 0 !important;
      }
    </style>
    <?php
  }
  
  if (strpos($_SERVER['REQUEST_URI'], '/calendar/') !== false) {
    ?>
    <link type="text/css" href="<?php echo $css_url; ?>group_calendar.css" rel="stylesheet" />
    <link type="text/css" href="<?php echo $css_url; ?>datepicker/css/ui-lightness/jquery-ui-1.7.2.custom.css" rel="stylesheet" />	
  	<script type="text/javascript" src="<?php echo $css_url; ?>datepicker/js/jquery-ui-1.7.2.custom.min.js"></script>
  	<script type="text/javascript">
  	  jQuery(document).ready(function () {
  		  jQuery('#event-date').datepicker({changeMonth: true, changeYear: true});
  		  jQuery('a#event-delete-link').click(function() {
            var answer = confirm("<?php _e('Are you sure you want to delete this event?', 'groupcalendar'); ?>")
            if (answer){
                return true;
            } else {
                return false;
            };
        });
  		});
  	</script>
    <?php
  }
}

function bp_group_calendar_highlighted_events($date='') {
  global $wpdb;
  
  if ($date) {
    $start_date = date('Y-m-', strtotime($date)).'01 00:00:00';
    $end_date = date('Y-m-d H:i:s', strtotime("-1 second", strtotime("+1 month", strtotime($start_date))));
  } else {
    $start_date = date('Y-m-', time()).'01 00:00:00';
    $end_date = date('Y-m-d H:i:s', strtotime("-1 second", strtotime("+1 month", strtotime($start_date))));
  }
  $filter = " WHERE event_time >= '$start_date' AND event_time <= '$end_date'";
  
  $events = $wpdb->get_col( "SELECT event_time FROM ".$wpdb->base_prefix."bp_groups_calendars".$filter." ORDER BY event_time ASC" );
  
  if ($events) {
    
    $highlighted_events = array();
    foreach ($events as $event) {
      $highlighted_events[] = date('Y-m-d', strtotime($event));
    }
    
    return $highlighted_events;
    
  } else {
    return array();
  }
  
}

function bp_group_calendar_list_events($group_id, $range, $date='', $calendar_capabilities, $show_all = 0) {
  global $wpdb, $current_user;
  
  $date_format = __('m/d/Y g:ia', 'groupcalendar');
  
  if ($range == 'all') {
    
    $filter = "";
    $empty_message = __('There are no scheduled events', 'groupcalendar');
    
  } else if ($range == 'month') {
    
    if ($date) {
      $start_date = date('Y-m-', strtotime($date)).'01 00:00:00';
      $end_date = date('Y-m-d H:i:s', strtotime("-1 second", strtotime("+1 month", strtotime($start_date))));
    } else {
      $start_date = date('Y-m-', time()).'01 00:00:00';
      $end_date = date('Y-m-d H:i:s', strtotime("-1 second", strtotime("+1 month", strtotime($start_date))));
    }
    $filter = " WHERE event_time >= '$start_date' AND event_time <= '$end_date'";
    $empty_message = __('There are no events scheduled for this month', 'groupcalendar');
    
  } else if ($range == 'day') {
  
    $start_date = date('Y-m-d H:i:s', strtotime($date));
    $end_date = date('Y-m-d', strtotime($date)).' 23:59:59';
    $filter = " WHERE event_time >= '$start_date' AND event_time <= '$end_date'";
    $empty_message = __('There are no events scheduled for this day', 'groupcalendar');
    $date_format = 'g:ia';
    
  } else if ($range == 'upcoming') {
  
    $filter = " WHERE event_time >= '".date('Y-m-d H:i:s')."'";
    $empty_message = __('There are no upcoming events', 'groupcalendar');
    
  } else if ($range == 'mine') {
  
    $filter = " WHERE event_time >= '".date('Y-m-d H:i:s')."' AND user_id = ".$current_user->ID;
    $empty_message = __('You have no scheduled events', 'groupcalendar');
    
  }
  
  if (!$show_all)
    $limit = " LIMIT 10";
  
  $events = $wpdb->get_results( "SELECT * FROM ".$wpdb->base_prefix."bp_groups_calendars".$filter." ORDER BY event_time ASC".$limit );
  
  if ($events) {
  
    $events_list = '<ul class="events-list">';
    //loop through events
    foreach ($events as $event) {
      $class = ($event->user_id==$current_user->ID) ? ' class="my_event"' : '';
      
      $events_list .= "\n<li".$class.">";
      $events_list .= '<a href="'.bp_group_calendar_create_event_url($event->id).'" title="'.__('View Event', 'groupcalendar').'">'.stripslashes($event->event_title).': '.date($date_format, strtotime($event->event_time)).'</a>';
      
      //add edit link if allowed
      if ($calendar_capabilities == 'full' || ($calendar_capabilities == 'limited' && $event->user_id==$current_user->ID)) {
          $events_list .= ' | <a href="'.bp_group_calendar_create_event_url($event->id, true).'" title="'.__('Edit Event', 'groupcalendar').'">'.__('Edit', 'groupcalendar').' &raquo;</a>';
      }
      
      $events_list .= "</li>";
    }
    $events_list .= "</ul>";
    
  } else { //no events for query
    $events_list = '<div id="message" class="info"><p>'.$empty_message.'</p></div>';
  }
  
  echo $events_list;
}

//------------------------------------------------------------------------//

//---Page Output Functions------------------------------------------------//

//------------------------------------------------------------------------//

//widgets

function bp_group_calendar_widget_day($date) {
  global $bp;
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  $day = $date['year'].'-'.$date['month'].'-'.$date['day'];

  $cal = new Calendar($date['day'], $date['year'], $date['month']);
  $url = bp_get_group_permalink( $bp->groups->current_group ).'/calendar';
  $cal->formatted_link_to = $url.'/%Y/%m/%d/';
  ?>
  <div class="bp-widget">
		<h4><?php _e('Day View', 'groupcalendar'); ?>: <?php echo date(__('m/d/Y', 'groupcalendar'), strtotime($day)); ?></h4>
		<table class="calendar-view">
      <tr>
        <td class="cal-left">
          <?php print($cal->output_calendar()); ?>
        </td>
        <td class="cal-right">
          <h5 class="events-title"><?php _e("Events For", 'groupcalendar'); ?> <?php echo date(__('l, F jS, Y', 'groupcalendar'), strtotime($day)); ?>:</h5>
          <?php bp_group_calendar_list_events(bp_get_group_id(), 'day', $day, $calendar_capabilities); ?>
        </td>
      </tr> 
    </table>
  </div>
  <?php
}

function bp_group_calendar_widget_month($date) {
  global $bp;
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  if (isset($date['month'])) {
    //show selected month
    $cal = new Calendar('', $date['year'], $date['month']);
  } else {
    //show current month
    $cal = new Calendar();
  }
  
  $url = bp_get_group_permalink( $bp->groups->current_group ).'/calendar';
  $cal->formatted_link_to = $url.'/%Y/%m/%d/';
  
  //first day of month for calulation previous and next months
  $first_day = $cal->year . "-" . $cal->month . "-01";
  $cal->highlighted_dates = bp_group_calendar_highlighted_events($first_day);
  $previous_month = $url.date("/Y/m/", strtotime("-1 month", strtotime($first_day)));
  $next_month = $url.date("/Y/m/", strtotime("+1 month", strtotime($first_day)));
  $this_year = $url.date("/Y/", strtotime($first_day));
  ?>
  <div class="bp-widget">
		<h4>
    <?php _e('Month View', 'groupcalendar'); ?>: <?php echo date(__('M, Y', 'groupcalendar'), strtotime($first_day)); ?>
      <span>
        <a title="<?php _e('Previous Month', 'groupcalendar'); ?>" href="<?php echo $previous_month; ?>">&larr; <?php _e('Previous', 'groupcalendar'); ?></a> | 
        <a title="<?php _e('Full Year', 'groupcalendar'); ?>" href="<?php echo $this_year; ?>"><?php _e('Year', 'groupcalendar'); ?></a> | 
        <a title="<?php _e('Next Month', 'groupcalendar'); ?>" href="<?php echo $next_month; ?>"><?php _e('Next', 'groupcalendar'); ?> &rarr;</a>
      </span>
    </h4>
    <table class="calendar-view">
      <tr>
        <td class="cal-left">
          <?php print($cal->output_calendar()); ?>
        </td>
        <td class="cal-right">
          <h5 class="events-title"><?php _e('Events For', 'groupcalendar'); ?> <?php echo date(__('F, Y', 'groupcalendar'), strtotime($first_day)); ?>:</h5>
    		  <?php bp_group_calendar_list_events(bp_get_group_id(), 'month', $first_day, $calendar_capabilities); ?>
        </td>
      </tr>   
    </table>
		
  </div>
  <?php
}

function bp_group_calendar_widget_year($date) {
  global $bp;
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  $year = $date['year'];
  $month = 1;
    
  $url = bp_get_group_permalink( $bp->groups->current_group ).'/calendar';
  //first day of month for calulation previous and next years
  $first_day = $year . "-01-01";
  $previous_year = $url.date("/Y/", strtotime("-1 year", strtotime($first_day)));
  $next_year = $url.date("/Y/", strtotime("+1 year", strtotime($first_day)));
  
  ?>
  <div class="bp-widget">
		<h4>
      <?php _e('Year View', 'groupcalendar'); ?>: <?php echo date('Y', strtotime($first_day)); ?>
      <span>
        <a title="<?php _e('Previous Year', 'groupcalendar'); ?>" href="<?php echo $previous_year; ?>">&larr; <?php _e('Previous', 'groupcalendar'); ?></a> | 
        <a title="<?php _e('Next Year', 'groupcalendar'); ?>" href="<?php echo $next_year; ?>"><?php _e('Next', 'groupcalendar'); ?> &rarr;</a>
      </span>
    </h4>
    <?php
    //loop through years
    for ($i = 1; $i <= 12; $i++) {
      $cal = new Calendar('', $year, $month);
      $cal->formatted_link_to = $url.'/%Y/%m/%d/';
      $first_day = $cal->year . "-" . $cal->month . "-01";
      $cal->highlighted_dates = bp_group_calendar_highlighted_events($first_day);
      echo '<div class="year-cal-item">';
      print($cal->output_calendar());
      echo '</div>';
      
      //first day of month for calulation of next month
      $first_day = $year . "-" . $month . "-01";
      $next_stamp = strtotime("+1 month", strtotime($first_day));
      $year = date("Y", $next_stamp);
      $month = date("m", $next_stamp);
    }
    ?>
    <div class="clear"></div>
  </div>
  <?php
}

function bp_group_calendar_widget_upcoming_events() {
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  ?>
  <div class="bp-widget">
		<h4><?php _e('Upcoming Events', 'groupcalendar'); ?></h4>
	  <?php bp_group_calendar_list_events(bp_get_group_id(), 'upcoming', '', $calendar_capabilities); ?>
  </div>
  <?php
}

function bp_group_calendar_widget_my_events() {

  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  ?>
  <div class="bp-widget">
		<h4><?php _e('My Events', 'groupcalendar'); ?></h4>
	  <?php bp_group_calendar_list_events(bp_get_group_id(), 'mine', '', $calendar_capabilities); ?>
  </div>
  <?php
}


function bp_group_calendar_widget_event_display($event_id) {
  global $wpdb, $current_user;
  
  $group_id = bp_get_group_id();
   
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  $event = $wpdb->get_row("SELECT * FROM ".$wpdb->base_prefix."bp_groups_calendars WHERE group_id = $group_id AND id = $event_id");
  
  //do nothing if invalid event_id
  if (!$event)
    return;
  
  //creat edit link if capable
  if ($calendar_capabilities == 'full' || ($calendar_capabilities == 'limited' && $current_user->ID == $event->user_id))
    $edit_link = '<span><a href="'.bp_group_calendar_create_event_url($event_id, true).'" title="'.__('Edit Event', 'groupcalendar').'">'.__('Edit', 'groupcalendar').' &rarr;</a></span>';
  
  $map_url = 'http://maps.google.com/maps?hl=en&q='.urlencode(stripslashes($event->event_location));
  
  $event_created_by = bp_core_get_userlink($event->user_id);
  $event_created = date(__('m/d/Y \a\t g:ia', 'groupcalendar'), $event->created_stamp);
  $event_modified_by = bp_core_get_userlink($event->last_edited_id);
  $event_last_modified = date(__('m/d/Y \a\t g:ia', 'groupcalendar'), $event->last_edited_stamp);
  
  $event_meta = '<span class="event-meta">'.sprintf(__('Created by %1$s on %2$s. Last modified by %3$s on %4$s.', 'groupcalendar'), $event_created_by, $event_created, $event_modified_by, $event_last_modified).'</span>';

  ?>
  <div class="bp-widget">
		<h4>
      <?php _e('Event Details', 'groupcalendar'); ?>
      <?php echo $edit_link; ?>
    </h4>
    
    <h5 class="events-title"><?php echo stripslashes($event->event_title); ?></h5>
    <span class="activity"><?php echo date(__('l, F jS, Y \a\t g:ia', 'groupcalendar'), strtotime($event->event_time)); ?></span>
    
    <?php if ($event->event_description) : ?>
      <h6 class="event-label"><?php _e('Description:', 'groupcalendar'); ?></h6>
  	  <div class="event-description">
  	    <?php echo stripslashes($event->event_description); ?>
  	  </div>
	  <?php endif; ?>
	  
	  <?php if ($event->event_location) : ?>
	    <h6 class="event-label"><?php _e('Location:', 'groupcalendar'); ?></h6>
  	  <div class="event-location">
  	  
  	    <?php echo stripslashes($event->event_location); ?>
  	  
  	  <?php if ($event->event_map) : ?>
    	  <span class="event-map">
    	    <a href="<?php echo $map_url; ?>" target="_blank" title="<?php _e('View Google Map of Event Location', 'groupcalendar'); ?>"><?php _e('Map', 'groupcalendar'); ?> &raquo;</a>
    	  </span>
  	  <?php endif; ?>
      </div>
	  <?php endif; ?>
	  
	  <?php echo $event_meta; ?>
	  
  </div>
  <?php
}


function bp_group_calendar_widget_create_event($date) {
  global $bp;
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  //don't display widget if no capabilities
  if ($calendar_capabilities == 'none')
    return;
    
  //if date given and valid, default form to it
  if ( !empty($date['year']) && !empty($date['month']) && !empty($date['day']) ) {
    $timestamp = strtotime($date['month'].'/'.$date['day'].'/'.$date['year']);
    if ($timestamp >= time())
      $default_date = date('m/d/Y', $timestamp);
  }
  
  $url = bp_get_group_permalink( $bp->groups->current_group ).'/calendar/';

  ?>
  <div class="bp-widget">
		<h4><?php _e('Create Event', 'groupcalendar'); ?></h4>
    <form action="<?php echo $url; ?>" name="add-event-form" id="add-event-form" class="standard-form" method="post" enctype="multipart/form-data">
			<label for="event-title"><?php _e('Title', 'groupcalendar'); ?> *</label>
			<input name="event-title" id="event-title" value="" type="text">
			
			<label for="event-date"><?php _e('Date', 'groupcalendar'); ?> *
			<input name="event-date" id="event-date" value="<?php echo $default_date; ?>" type="text"></label>
			
			<label for="event-time"><?php _e('Time', 'groupcalendar'); ?> *
			<select name="event-hour" id="event-hour">
		    <?php
        for ($i = 1; $i <= 12; $i++) {
          $hour_check = ($i == 7) ? ' selected="selected"' : '';
          echo '<option value="'.$i.'"'.$hour_check.'>'.$i."</option>\n";
        }
        ?>
			</select>
      <select name="event-minute" id="event-minute">
		    <option value="00">:00</option>
		    <option value="15">:15</option>
		    <option value="30">:30</option>
		    <option value="45">:45</option>
			</select>
			<select name="event-ampm" id="event-ampm">
		    <option value="am">am</option>
		    <option value="pm">pm</option>
			</select>
      </label>
			
			<label for="event-desc"><?php _e('Description', 'groupcalendar'); ?></label>
			<textarea name="event-desc" id="event-desc" rows="5"></textarea>

			<label for="event-loc"><?php _e('Location', 'groupcalendar'); ?></label>
			<input name="event-loc" id="event-loc" value="" type="text">
			
			<label for="event-map"><?php _e('Show Map Link?', 'groupcalendar'); ?>
			<input name="event-map" id="event-map" value="1" type="checkbox" checked="checked" />
      <small><?php _e('(Note: Location must be an address)', 'groupcalendar'); ?></small>
      </label>
			
			<input name="create-event" id="create-event" value="1" type="hidden">
      <input name="group-id" id="group-id" value="<?php echo bp_get_group_id(); ?>" type="hidden">
      <?php wp_nonce_field('bp_group_calendar'); ?>
      
			<p><input value="<?php _e('Create Event', 'groupcalendar'); ?> &raquo;" id="save" name="save" type="submit"></p>
			
	 </form>
		
  </div>
  <?php
}


function bp_group_calendar_widget_edit_event($event_id=false) {
  global $wpdb, $current_user, $bp;
  
  $url = bp_get_group_permalink( $bp->groups->current_group ).'/calendar/';
  
  $group_id = bp_get_group_id();
  
  $calendar_capabilities = bp_group_calendar_get_capabilities();
  
  //don't display widget if no capabilities
  if ($calendar_capabilities == 'none') {
    bp_core_add_message( __("You don't have permission to edit events", 'groupcalendar'), 'error' );
    return false;
  }
  
  if ($event_id) { //load from DB
    
    $url .= 'event/'.$event_id.'/';
    
    $event = $wpdb->get_row("SELECT * FROM ".$wpdb->base_prefix."bp_groups_calendars WHERE group_id = $group_id AND id = $event_id");
    
    if (!$event)
      return false;
      
    //check limited capability users
    if ($calendar_capabilities == 'limited' && $current_user->ID != $event->id) {
      bp_core_add_message( __("You don't have permission to edit that event", 'groupcalendar'), 'error' );
      return false;
    }
      
    $event_title = stripslashes($event->event_title);
    
    //check for valid date/time
    $tmp_date = strtotime($event->event_time);
    if ($tmp_date) {
      $event_date = date('m/d/Y', $tmp_date);
      $event_hour = date('g', $tmp_date);
      $event_minute = date('i', $tmp_date);
      $event_ampm = date('a', $tmp_date);
    } else {
      $event_hour = 7;
    }
    
    $event_description = stripslashes($event->event_description);
    $event_location = stripslashes($event->event_location);
    $event_map = ($event->event_map == 1) ? ' checked="checked"' : '';
    
    $event_created_by = bp_core_get_userlink($event->user_id);
    $event_created = date(__('m/d/Y \a\t g:ia', 'groupcalendar'), $event->created_stamp);
    $event_modified_by = bp_core_get_userlink($event->last_edited_id);
    $event_last_modified = date(__('m/d/Y \a\t g:ia', 'groupcalendar'), $event->last_edited_stamp);
    
    $event_meta = '<span class="event-meta">'.sprintf(__('Created by %1$s on %2$s. Last modified by %3$s on %4$s.', 'groupcalendar'), $event_created_by, $event_created, $event_modified_by, $event_last_modified).'</span>';
    
    $delete_url = $url.'delete/';
    $delete_url = wp_nonce_url($delete_url, 'event-delete-link');
    $delete_link = '<span><a id="event-delete-link" href="'.$delete_url.'" title="'.__('Delete Event', 'groupcalendar').'">'.__('Delete', 'groupcalendar').' &rarr;</a></span>';
    
  } else { //load POST variables
  
    if (!isset($_POST['create-event']))
      return false;
    
    $event_title = htmlentities(strip_tags(trim(stripslashes($_POST['event-title']))));
    $tmp_date = $_POST['event-date'].' '.$_POST['event-hour'].':'.$_POST['event-minute'].$_POST['event-ampm'];
    $tmp_date = strtotime($tmp_date);
    
    //check for valid date/time
    if ($tmp_date && $tmp_date >= time()) {
      $event_date = date('m/d/Y', $tmp_date);
      $event_hour = date('g', $tmp_date);
      $event_minute = date('i', $tmp_date);
      $event_ampm = date('a', $tmp_date);
    } else {
      $event_hour = 7;
    }
    
    $event_description = stripslashes(wp_filter_post_kses($_POST['event-desc']));
    $event_location = htmlentities(strip_tags(trim(stripslashes($_POST['event-loc']))));
    $event_map = ($_POST['event-map']==1) ? ' checked="checked"' : '';
    
  }
  
  ?>
  <div class="bp-widget">
		<h4>
      <?php _e('Edit Event', 'groupcalendar'); ?>
      <?php echo $delete_link; ?>
    </h4>
    
    <form action="<?php echo $url; ?>" name="add-event-form" id="add-event-form" class="standard-form" method="post" enctype="multipart/form-data">
			<label for="event-title"><?php _e('Title', 'groupcalendar'); ?> *</label>
			<input name="event-title" id="event-title" value="<?php echo $event_title; ?>" type="text">
			
			<label for="event-date"><?php _e('Date', 'groupcalendar'); ?> *
			<input name="event-date" id="event-date" value="<?php echo $event_date; ?>" type="text"></label>
			
			<label for="event-time"><?php _e('Time', 'groupcalendar'); ?> *
			<select name="event-hour" id="event-hour">
			  <?php
        for ($i = 1; $i <= 12; $i++) {
          $hour_check = ($i == $event_hour) ? ' selected="selected"' : '';
          echo '<option value="'.$i.'"'.$hour_check.'>'.$i."</option>\n";
        }
        ?>
			</select>
      <select name="event-minute" id="event-minute">
		    <option value="00"<?php echo ($event_minute=='00') ? ' selected="selected"' : ''; ?>>:00</option>
		    <option value="15"<?php echo ($event_minute=='15') ? ' selected="selected"' : ''; ?>>:15</option>
		    <option value="30"<?php echo ($event_minute=='30') ? ' selected="selected"' : ''; ?>>:30</option>
		    <option value="45"<?php echo ($event_minute=='45') ? ' selected="selected"' : ''; ?>>:45</option>
			</select>
			<select name="event-ampm" id="event-ampm">
		    <option value="am"<?php echo ($event_ampm=='am') ? ' selected="selected"' : ''; ?>>am</option>
		    <option value="pm"<?php echo ($event_ampm=='pm') ? ' selected="selected"' : ''; ?>>pm</option>
			</select>
      </label>
			
			<label for="event-desc"><?php _e('Description', 'groupcalendar'); ?></label>
			<textarea name="event-desc" id="event-desc" rows="5"><?php echo $event_description; ?></textarea>

			<label for="event-loc"><?php _e('Location', 'groupcalendar'); ?></label>
			<input name="event-loc" id="event-loc" value="<?php echo $event_location; ?>" type="text">
			
			<label for="event-map"><?php _e('Show Map Link?', 'groupcalendar'); ?>
			<input name="event-map" id="event-map" value="1" type="checkbox"<?php echo $event_map; ?> />
      <small><?php _e('(Note: Location must be an address)', 'groupcalendar'); ?></small>
      </label>
			
			<?php if ($event_id) : ?>
			<input name="event-id" id="event-id" value="<?php echo $event_id; ?>" type="hidden">
			<?php endif; ?>
			
      <input name="create-event" id="create-event" value="1" type="hidden">
      <input name="group-id" id="group-id" value="<?php echo bp_get_group_id(); ?>" type="hidden">
      <?php wp_nonce_field('bp_group_calendar'); ?>
      
      <?php echo $event_meta; ?>
      
			<p><input value="<?php _e('Save Event', 'groupcalendar'); ?> &raquo;" id="save" name="save" type="submit"></p>
			
	 </form>
		
  </div>
  <?php
  
  //return true if all is well
  return true;
}
//------------------------------------------------------------------------//

//---Support Functions----------------------------------------------------//

//------------------------------------------------------------------------//



?>