<?php
/**
 * Jalali (Shamsi) date formatting helpers.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Jalali
 */
class WPQA_Jalali {

	/**
	 * Persian month names.
	 *
	 * @var array
	 */
	private static $months = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	/**
	 * Format a MySQL datetime as Jalali for display.
	 *
	 * @param string $mysql_datetime Local MySQL datetime.
	 * @param bool   $with_time      Include time.
	 * @return string
	 */
	public static function format( $mysql_datetime, $with_time = false ) {
		$mysql_datetime = trim( (string) $mysql_datetime );

		if ( '' === $mysql_datetime || '0000-00-00 00:00:00' === $mysql_datetime || '0000-00-00' === $mysql_datetime ) {
			return '';
		}

		$ts = strtotime( $mysql_datetime );
		if ( false === $ts ) {
			return '';
		}

		$gy = (int) gmdate( 'Y', $ts );
		$gm = (int) gmdate( 'n', $ts );
		$gd = (int) gmdate( 'j', $ts );

		// strtotime without timezone uses server TZ; prefer components from local string.
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/', $mysql_datetime, $m ) ) {
			$gy = (int) $m[1];
			$gm = (int) $m[2];
			$gd = (int) $m[3];
			$h  = isset( $m[4] ) ? (int) $m[4] : 0;
			$i  = isset( $m[5] ) ? (int) $m[5] : 0;
		} else {
			$h = (int) date( 'G', $ts ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$i = (int) date( 'i', $ts ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		}

		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );

		$month_name = isset( self::$months[ $jm ] ) ? self::$months[ $jm ] : (string) $jm;
		$formatted  = sprintf( '%d %s %d', $jd, $month_name, $jy );

		if ( $with_time ) {
			$formatted .= sprintf( '، %02d:%02d', $h, $i );
		}

		return self::to_persian_digits( $formatted );
	}

	/**
	 * Convert Western digits to Persian digits.
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	public static function to_persian_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}

	/**
	 * Convert Gregorian date to Jalali.
	 *
	 * @param int $gy Year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return array{0:int,1:int,2:int} [jy, jm, jd]
	 */
	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$gy = (int) $gy;
		$gm = (int) $gm;
		$gd = (int) $gd;

		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];

		$jy = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}
}
