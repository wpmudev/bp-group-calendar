<?php
/*
Plugin Name: BP Group Calendar
Version: 1.1
Plugin URI: http://premium.wpmudev.org/project/buddypress-group-calendar
Description: Adds event calendar functionality to Buddypress Groups. Must be activated site-wide in WPMU.
Author: Aaron Edwards (Incsub)
Author URI: http://uglyrobot.com
Site Wide Only: true
WDP ID: 109

Copyright 2009-2010 Incsub (http://incsub.com)

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

/* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
function bp_group_calendar_init() {
  require( dirname( __FILE__ ) . '/groupcalendar/bp-group-calendar.php' );
}
add_action( 'bp_init', 'bp_group_calendar_init' );

?>