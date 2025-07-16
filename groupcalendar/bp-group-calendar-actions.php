<?php
/**
 *
 * If the BuddyPress Hide Widgets plugin is active, then also the option for Group calendar widgets are displayed there
 *
 * @since 2.0
 *
 */

add_action( 'bp_hide_widgets_unregister', 'bp_group_calendar_bp_hide_widgets_unregister' );

function bp_group_calendar_bp_hide_widgets_unregister() {
	if ( bp_get_option( 'BP_Group_Calendar_Widget', '0' ) ) {
		add_action( 'widgets_init', 'bp_group_calendar_bp_hide_widget_unregister_group_calendar_widget', 21 ); //run after bp
	}
	if ( bp_get_option( 'BP_Group_Calendar_Widget_Single', '0' ) ) {
		add_action( 'widgets_init', 'bp_group_calendar_bp_hide_widget_unregister_group_calendar_single_widget', 21 ); //run after bp
	}
	if ( bp_get_option( 'BP_Group_Calendar_Widget_User_Groups', '0' ) ) {
		add_action( 'widgets_init', 'bp_group_calendar_bp_hide_widget_unregister_group_calendar_user_widget', 21 ); //run after bp
	}
}


add_action( 'bp_hide_widgets_register_settings', 'bp_group_calendar_bp_hide_widgets_register_settings' );

function bp_group_calendar_bp_hide_widgets_register_settings() {
	add_settings_field( 'BP_Group_Calendar_Widget', __( 'Groups Events', 'groupcalendar' ), 'bp_hide_widgets_admin_bp_group_calendar', 'buddypress', 'bp_hide_widgets' );
	register_setting( 'buddypress', 'BP_Group_Calendar_Widget', 'intval' );

	add_settings_field( 'BP_Group_Calendar_Widget_Single', __( 'Single Group Events', 'groupcalendar' ), 'bp_hide_widgets_admin_bp_group_calendar_single', 'buddypress', 'bp_hide_widgets' );
	register_setting( 'buddypress', 'BP_Group_Calendar_Widget_Single', 'intval' );

	add_settings_field( 'BP_Group_Calendar_Widget_User_Groups', __( 'User\'s Group Events', 'groupcalendar' ), 'bp_hide_widgets_admin_bp_group_calendar_user', 'buddypress', 'bp_hide_widgets' );
	register_setting( 'buddypress', 'BP_Group_Calendar_Widget_User_Groups', 'intval' );
}



function bp_hide_widgets_admin_bp_group_calendar() {
	?>
	<label><input type="radio" name="BP_Group_Calendar_Widget"<?php checked( bp_get_option( 'BP_Group_Calendar_Widget', '0' ) ); ?>value="1" /> <?php _e( 'Main', 'bp_hide_widgets' ); ?></label> &nbsp;
	<label><input type="radio" name="BP_Group_Calendar_Widget"<?php checked( ! bp_get_option( 'BP_Group_Calendar_Widget', '0' ) ); ?>value="0" /> <?php _e( 'All', 'bp_hide_widgets' ); ?></label>
	<?php
}

function bp_hide_widgets_admin_bp_group_calendar_single() {
	?>
	<label><input type="radio" name="BP_Group_Calendar_Widget_Single"<?php checked( bp_get_option( 'BP_Group_Calendar_Widget_Single', '0' ) ); ?>value="1" /> <?php _e( 'Main', 'bp_hide_widgets' ); ?></label> &nbsp;
	<label><input type="radio" name="BP_Group_Calendar_Widget_Single"<?php checked( ! bp_get_option( 'BP_Group_Calendar_Widget_Single', '0' ) ); ?>value="0" /> <?php _e( 'All', 'bp_hide_widgets' ); ?></label>
	<?php
}

function bp_hide_widgets_admin_bp_group_calendar_user() {
	?>
	<label><input type="radio" name="BP_Group_Calendar_Widget_User_Groups"<?php checked( bp_get_option( 'BP_Group_Calendar_Widget_User_Groups', '0' ) ); ?>value="1" /> <?php _e( 'Main', 'bp_hide_widgets' ); ?></label> &nbsp;
	<label><input type="radio" name="BP_Group_Calendar_Widget_User_Groups"<?php checked( ! bp_get_option( 'BP_Group_Calendar_Widget_User_Groups', '0' ) ); ?>value="0" /> <?php _e( 'All', 'bp_hide_widgets' ); ?></label>
	<?php
}


function bp_group_calendar_bp_hide_widget_unregister_group_calendar_widget() {
	return unregister_widget( 'BP_Group_Calendar_Widget' );
}

function bp_group_calendar_bp_hide_widget_unregister_group_calendar_single_widget() {
	return unregister_widget( 'BP_Group_Calendar_Widget_Single' );
}
function bp_group_calendar_bp_hide_widget_unregister_group_calendar_user_widget() {
	return unregister_widget( 'BP_Group_Calendar_Widget_User_Groups' );
}
