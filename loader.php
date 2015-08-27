<?php
/*
Plugin Name: BuddyPress Group Calendar
Version: 1.4.4
Plugin URI: https://premium.wpmudev.org/project/buddypress-group-calendar/
Description: Adds event calendar functionality to BuddyPress Groups. Maintain, update and share upcoming group events with really swish calendar functionality.
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Network: true
Text Domain: groupcalendar
Domain Path: /languages
WDP ID: 109

Copyright 2009-2015 Incsub (http://incsub.com)
Author - Aaron Edwards
Contributors - 

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

//default permissions for existing groups. Choose: full, limited, or none
if ( ! defined( 'BGC_MODERATOR_DEFAULT' ) ) {
	define( 'BGC_MODERATOR_DEFAULT', 'full' );
}

if ( ! defined( 'BGC_MEMBER_DEFAULT' ) ) {
	define( 'BGC_MEMBER_DEFAULT', 'limited' );
}

//default for sending email notifications for new events. Group admins can overwrite.
if ( ! defined( 'BGC_EMAIL_DEFAULT' ) ) {
	define( 'BGC_EMAIL_DEFAULT', 'yes' );
} //yes or no

$bp_group_calendar_current_version = '1.4.4';

/* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
function bp_group_calendar_init() {
	require( dirname( __FILE__ ) . '/groupcalendar/bp-group-calendar.php' );
}

add_action( 'bp_include', 'bp_group_calendar_init' );

function bp_group_calendar_localization() {
	global $bgc_locale;
	// Load up the localization file if we're using WordPress in a different language
	// Place it in this plugin's "languages" folder and name it "groupcalendar-[value in wp-config].mo"
	load_plugin_textdomain( 'groupcalendar', false, '/bp-group-calendar/languages' );
	if ( get_locale() ) {
		setlocale( LC_TIME, get_locale() );
	} //for date translations in php

	//get display settings
	$temp_locales              = explode( '_', get_locale() );
	$bgc_locale['code']        = ( $temp_locales[0] ) ? $temp_locales[0] : 'en';
	$bgc_locale['time_format'] = ( false !== strpos( get_option( 'time_format' ), 'H' ) || false !== strpos( get_option( 'time_format' ), 'G' ) ) ? 24 : 12;
	$bgc_locale['week_start']  = ( get_option( 'start_of_week' ) == '0' ) ? 7 : get_option( 'start_of_week' );
}

add_action( 'plugins_loaded', 'bp_group_calendar_localization' );

include_once( dirname( __FILE__ ) . '/groupcalendar/dash-notice/wpmudev-dash-notification.php' );