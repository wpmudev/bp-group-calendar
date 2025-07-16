<?php

/**
 * Calendar Generation Class
 *
 * This class provides a simple reuasable means to produce month calendars in valid html
 *
 * @version 2.8
 * @author Jim Mayes <jim.mayes@gmail.com>
 * @link *link removed as site is malware compromised*
 * @copyright Copyright (c) 2008, Jim Mayes
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GPL v2.0
 */
class Calendar {
	var $date;
	var $year;
	var $month;
	var $day;

	var $week_start_on = false;
	var $week_start    = 7;// sunday

	var $link_days = true;
	var $link_to;
	var $formatted_link_to;

	var $mark_today       = true;
	var $today_date_class = 'today';

	var $mark_selected       = true;
	var $selected_date_class = 'selected';

	var $mark_passed       = true;
	var $passed_date_class = 'passed';

	var $highlighted_dates;
	var $default_highlighted_class = 'highlighted';


	/* CONSTRUCTOR */
	function __construct( $date = null, $year = null, $month = null ) {
		$self          = htmlspecialchars( $_SERVER['PHP_SELF'] );
		$this->link_to = $self;

		if ( is_null( $year ) || is_null( $month ) ) {
			if ( ! is_null( $date ) ) {
				//-------- strtotime the submitted date to ensure correct format
				$this->date = gmdate( 'Y-m-d', strtotime( $date ) );
			} else {
				//-------------------------- no date submitted, use today's date
				$this->date = gmdate( 'Y-m-d' );
			}
			$this->set_date_parts_from_date( $this->date );
		} else {
			$this->year  = $year;
			$this->month = str_pad( $month, 2, '0', STR_PAD_LEFT );
		}
	}

	function set_date_parts_from_date( $date ) {
		$this->year  = gmdate( 'Y', strtotime( $date ) );
		$this->month = gmdate( 'm', strtotime( $date ) );
		$this->day   = gmdate( 'd', strtotime( $date ) );
	}

	function day_of_week( $date ) {
		$day_of_week = gmdate( 'N', $date );
		if ( ! is_numeric( $day_of_week ) ) {
			$day_of_week = gmdate( 'w', $date );
			if ( 0 === $day_of_week ) {
				$day_of_week = 7;
			}
		}

		return $day_of_week;
	}

	/**
	 *
	 * @global type $wp_locale
	 * @param type $year
	 * @param type $month
	 * @param type $calendar_class
	 * @return string
	 *
	 * @version 2.0
	 */
	function output_calendar( $year = null, $month = null, $calendar_class = 'calendar' ) {
		if ( false !== $this->week_start_on ) {
			echo 'The property week_start_on is replaced due to a bug present in version before 2.6. of this class! Use the property week_start instead!';
			exit;
		}

		//--------------------- override class methods if values passed directly
		$year  = ( is_null( $year ) ) ? $this->year : $year;
		$month = ( is_null( $month ) ) ? $this->month : str_pad( $month, 2, '0', STR_PAD_LEFT );

		//------------------------------------------- create first date of month
		$month_start_date = strtotime( $year . '-' . $month . '-01' );
		//------------------------- first day of month falls on what day of week
		$first_day_falls_on = $this->day_of_week( $month_start_date );
		//----------------------------------------- find number of days in month
		$days_in_month = gmdate( 't', $month_start_date );
		//-------------------------------------------- create last date of month
		$month_end_date = strtotime( $year . '-' . $month . '-' . $days_in_month );
		//----------------------- calc offset to find number of cells to prepend
		$start_week_offset = $first_day_falls_on - $this->week_start;
		$prepend           = ( $start_week_offset < 0 ) ? 7 - abs( $start_week_offset ) : $first_day_falls_on - $this->week_start;
		//-------------------------- last day of month falls on what day of week
		$last_day_falls_on = $this->day_of_week( $month_end_date );

		//------------------------------------------------- start table, caption
		$output  = '<table class="' . $calendar_class . "\">\n";
		$output .= '<caption>' . date_i18n( 'M Y', $month_start_date ) . "</caption>\n";
		$col     = '';
		$th      = '';
		for ( $i = 1, $j = $this->week_start, $t = ( 3 + $this->week_start ) * 86400; $i <= 7; $i ++, $j ++, $t += 86400 ) {
			$localized_day_name       = date_i18n( 'l', $t );
			$col                     .= '<col class="' . strtolower( $localized_day_name ) . "\" />\n";
			$localized_short_day_name = date_i18n( 'D', $t );
			$th                      .= "\t<th title=\"" . $localized_day_name . '">' . $localized_short_day_name . "</th>\n";
			$j                        = ( 7 === $j ) ? 0 : $j;
		}

		//------------------------------------------------------- markup columns
		$output .= $col;

		//----------------------------------------------------------- table head
		$output .= "<thead>\n";
		$output .= "<tr>\n";

		$output .= $th;

		$output .= "</tr>\n";
		$output .= "</thead>\n";

		//---------------------------------------------------------- start tbody
		$output .= "<tbody>\n";
		$output .= "<tr>\n";

		//---------------------------------------------- initialize week counter
		$weeks = 1;

		//--------------------------------------------------- pad start of month

		//------------------------------------ adjust for week start on saturday
		for ( $i = 1; $i <= $prepend; $i ++ ) {
			$output .= "\t<td class=\"pad\">&nbsp;</td>\n";
		}

		//--------------------------------------------------- loop days of month
		for ( $day = 1, $cell = $prepend + 1; $day <= $days_in_month; $day ++, $cell ++ ) {

			/*
			if this is first cell and not also the first day, end previous row
			*/
			if ( 1 === $cell && 1 !== $day ) {
				$output .= "<tr>\n";
			}

			//-------------- zero pad day and create date string for comparisons
			$day      = str_pad( $day, 2, '0', STR_PAD_LEFT );
			$day_date = $year . '-' . $month . '-' . $day;

			//-------------------------- compare day and add classes for matches
			if ( true === $this->mark_today && gmdate( 'Y-m-d' ) === $day_date ) {
				$classes[] = $this->today_date_class;
			}

			if ( true === $this->mark_selected && $day_date === $this->date ) {
				$classes[] = $this->selected_date_class;
			}

			if ( true === $this->mark_passed && $day_date < gmdate( 'Y-m-d' ) ) {
				$classes[] = $this->passed_date_class;
			}

			if ( is_array( $this->highlighted_dates ) ) {
				if ( in_array( $day_date, $this->highlighted_dates ) ) {
					$classes[] = $this->default_highlighted_class;
				}
			}

			//----------------- loop matching class conditions, format as string
			if ( isset( $classes ) ) {
				$day_class = ' class="';
				foreach ( $classes as $value ) {
					$day_class .= $value . ' ';
				}
				$day_class = substr( $day_class, 0, - 1 ) . '"';
			} else {
				$day_class = '';
			}

			//---------------------------------- start table cell, apply classes

			$output .= "\t<td" . $day_class . ' title="' . ucwords( date_i18n( 'l d F Y', strtotime( $day_date ) ) ) . '">';

			//----------------------------------------- unset to keep loop clean
			unset( $day_class, $classes );

			//-------------------------------------- conditional, start link tag
			switch ( $this->link_days ) {
				case 0:
					$output .= $day;
					break;

				case 1:
					if ( empty( $this->formatted_link_to ) ) {
						$output .= '<a href="' . $this->link_to . '?date=' . $day_date . '">' . $day . '</a>';
					} else {
						$output .= '<a href="' . gmdate( $this->formatted_link_to, strtotime( $day_date ) ) . '">' . $day . '</a>';
					}
					break;

				case 2:
					if ( is_array( $this->highlighted_dates ) ) {
						if ( in_array( $day_date, $this->highlighted_dates ) ) {
							if ( empty( $this->formatted_link_to ) ) {
								$output .= '<a href="' . $this->link_to . '?date=' . $day_date . '">';
							} else {
								$output .= '<a href="' . gmdate( $this->formatted_link_to, strtotime( $day_date ) ) . '">';
							}
						}
					}

					$output .= $day;

					if ( is_array( $this->highlighted_dates ) ) {
						if ( in_array( $day_date, $this->highlighted_dates ) ) {
							if ( empty( $this->formatted_link_to ) ) {
								$output .= '</a>';
							} else {
								$output .= '</a>';
							}
						}
					}
					break;
			}
			//------------------------------------------------- close table cell
			$output .= "</td>\n";

			//------- if this is the last cell, end the row and reset cell count
			if ( 7 === $cell ) {
				$output .= "</tr>\n";
				$cell    = 0;
			}
		}

		//----------------------------------------------------- pad end of month
		if ( 1 > $cell ) {
			for ( $i = $cell; $i <= 7; $i ++ ) {
				$output .= "\t<td class=\"pad\">&nbsp;</td>\n";
			}
			$output .= "</tr>\n";
		}

		//--------------------------------------------- close last row and table
		$output .= "</tbody>\n";
		$output .= "</table>\n";

		//--------------------------------------------------------------- return
		return $output;

	}

}
