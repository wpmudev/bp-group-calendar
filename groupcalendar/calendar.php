<?php

$event_id = bp_group_calendar_event_url_parse();

$edit_event = bp_group_calendar_event_is_edit();

bp_group_calendar_event_is_delete();

if (bp_group_calendar_event_save()===false)
  $edit_event = true;

$date = bp_group_calendar_url_parse();

?>

<?php do_action( 'template_notices' ); ?>

<?php if ( bp_has_groups() ) : while ( bp_groups() ) : bp_the_group(); ?>



<div class="left-menu">

	<?php load_template( TEMPLATEPATH . '/groups/single/menu.php' ); ?>

</div>



<div class="main-column">

	<div class="inner-tube">

        <div id="group-name">

            <h1><a href="<?php bp_group_permalink() ?>"><?php bp_group_name() ?></a></h1>

            <p class="status"><?php bp_group_type() ?></p>

        </div>

		<?php if ( bp_group_is_visible() ) : ?>

    <?php
    if ($edit_event) {
    
      //show edit event form
      if (!bp_group_calendar_widget_edit_event($event_id)) {

        //default to current month view
        bp_group_calendar_widget_month($date);
        bp_group_calendar_widget_upcoming_events();
        bp_group_calendar_widget_my_events();
        bp_group_calendar_widget_create_event($date);
      
      }
    
    } else if ($event_id) {
      
      //display_event
      bp_group_calendar_widget_event_display($event_id);
      
      //current month view
      bp_group_calendar_widget_month($date);
    
		} else if ( $date['year'] && !$date['month'] && !$date['day'] ) {

			//year view
      bp_group_calendar_widget_year($date);
      
      bp_group_calendar_widget_create_event($date);

		} else if ( $date['year'] && $date['month'] && !$date['day'] ) {

			//month view
      bp_group_calendar_widget_month($date);
      
      bp_group_calendar_widget_create_event($date);

		} else if ( $date['year'] && $date['month'] && $date['day'] ) {

			//day view
      bp_group_calendar_widget_day($date);
      
      bp_group_calendar_widget_create_event($date);
      
		} else {
		
      //default to current month view
      bp_group_calendar_widget_month($date);
      
      bp_group_calendar_widget_upcoming_events();
      
      bp_group_calendar_widget_my_events();
      
      bp_group_calendar_widget_create_event($date);

    }
    
    ?>
  
		<?php endif;?>

	</div>

</div>

<?php endwhile; endif; ?>