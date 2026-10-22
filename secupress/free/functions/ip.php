<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Get the IP address of the current user.
 *
 * @since 2.7.2 Trust CF-Connecting-IP only when REMOTE_ADDR is a Cloudflare proxy.
 * @since 2.2.3 Security Fix: Only get IP from REMOTE_ADDR
 * @since 1.4.3 Add $priority param
 * @since 1.0
 *
 * @param (string) $priority Contains a key from $keys to be read first.
 * @return (string)
 */
function secupress_get_ip() {
	$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	$ip          = $remote_addr;

	if ( secupress_ip_is_cloudflare_proxy( $remote_addr ) && isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && is_string( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$cloudflare_ip = trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		if ( secupress_ip_is_valid( $cloudflare_ip ) ) {
			$ip = $cloudflare_ip;
		}
	}

	/**
	 * Filter the IP address.
	 *
	 * @since 1.0
	 *
	 * @param (string) $ip The IP address.
	 */
	return apply_filters( 'secupress.ip.get_ip', $ip );
}

/**
 * Get the official Cloudflare proxy ranges.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array)
 */
function secupress_get_cloudflare_ip_ranges() {
	$ranges = [
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	];

	/**
	 * Filter the trusted Cloudflare proxy ranges.
	 *
	 * @since 2.7.2
	 *
	 * @param (array) $ranges Cloudflare IPv4 and IPv6 ranges.
	 */
	$ranges = apply_filters( 'secupress.cloudflare.ip_ranges', $ranges );

	return is_array( $ranges ) ? $ranges : [];
}

/**
 * Tell if an IP address belongs to a trusted Cloudflare proxy.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $ip An IP address.
 *
 * @return (bool)
 */
function secupress_ip_is_cloudflare_proxy( $ip ) {
	if ( ! secupress_ip_is_valid( $ip ) ) {
		return false;
	}

	return secupress_is_ip_in_range( $ip, secupress_get_cloudflare_ip_ranges() );
}


/**
 * Tell if an IP address is valid.
 *
 * @since 2.3.1 $flag back to default=0, else deprecated > PHP 8
 * @since 2.2.6 $flag - null by default
 * @since 1.0
 * @author Julio Potier
 *
 * @param (string) $ip An IP address.
 * @param (bool) $range_format If we have to check in ranges format.
 * @param (null|int) $flag Flags from filter_var() or NULL but not 0 !
 *
 * @return (bool) True is valid IP
 */
function secupress_ip_is_valid( $ip, $range_format = false , $flag = 0 ) {
	if ( ! $ip || ! is_string( $ip ) ) {
		return false;
	}

	$ip = trim( $ip );
	if ( filter_var( $ip, FILTER_VALIDATE_IP, $flag ) ) {
		return true;
	}

	if ( ! $range_format ) {
		return false;
	}

	if ( strpos( $ip, '*' ) > 0 ) {
		$ipv4 = str_replace( '*', '0', $ip );
		$ipv6 = str_replace( '*', '', $ip );
		$ipv6 = secupress_get_full_ipv6( $ipv6, '0' );

		if ( FILTER_FLAG_IPV6 === $flag ) {
			return (bool) filter_var( $ipv6, FILTER_VALIDATE_IP, $flag );
		} elseif ( FILTER_FLAG_IPV4 === $flag ) {
			return (bool) filter_var( $ipv4, FILTER_VALIDATE_IP, $flag );
		} elseif ( 0 === $flag ) {
			return (bool) ( filter_var( $ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || filter_var( $ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) );
		}
	}

	if ( strpos( $ip, '/' ) > 0 ) {
		$ip = explode( '/', $ip, 2 );
		return (bool) filter_var( $ip[0], FILTER_VALIDATE_IP, $flag ) && is_numeric( $ip[1] ) && $ip[1] <= 128 && $ip[1] > 0;
	}

	if ( strpos( $ip, '-' ) > 0 ) {
		$ip = explode( '-', $ip, 2 );
		return (bool) filter_var( $ip[0], FILTER_VALIDATE_IP, $flag ) && is_numeric( $ip[1] )  && $ip[1] <= 255 && $ip[1] > 0;
	}

	return false;
}

/**
 * Transform a non complete IPv6 form to its complete form (from '-' range)
 *
 * @since 1.4.9
 * @author Julio Potier
 *
 * @param (string) $ipv6 Non complete IPv6 form like fedc:6482:cafe::-fedc:6482:cafe:ffff:ffff:ffff:ffff:ffff
 * @param (string) $mask Either '0' of 'f' to complete the ipv6@
 * @return (string) The final ipv6 form
 **/
function secupress_get_full_ipv6( $ipv6, $mask ) {
	$ipv6 = explode( ':', $ipv6 );
	$ipv6 = array_filter( $ipv6 );
	$ipv6 = array_merge( $ipv6, array_fill( count( $ipv6 ), 8 - count( $ipv6 ), '0/ffff' ) );
	$ipv6 = implode( ':', $ipv6 );
	$temp = explode( '::-', $ipv6 );
	$ipv6 = $temp[0] . str_repeat( ':0/ffff', 7 - substr_count( $temp[0], ':' ) );
	$ipv6 = str_replace( '0/ffff', $mask, $ipv6 );
	return $ipv6;
}

/**
 * Tell if an IP address is whitelisted.
 *
 * @since 2.2.3 0.0.0.0 is not whitelisted anymore
 * @since 1.4.9 $in_range param
 * @since 1.0
 *
 * @param (string) $ip An IP address. If not provided, the current IP by default.
 * @param (bool) $in_range Specify if the whitelist should aso be tested including ranged ips
 *
 * @return (bool).
 */
function secupress_ip_is_whitelisted( $ip = null, $in_range = true ) {
	$ip = $ip ? $ip : secupress_get_ip();

	/**
	 * Filter the possibility for an IP to bypass the function
	 *
	 * @since 2.2.6
	 *
	 * @param (void|bool) null, a bool will cut the answer
	 * @param (string) $ip The IP address.
	 */
	$early_return = apply_filters( 'secupress.ip_is_allowed', null, $ip );
	if ( is_bool( $early_return ) ) {
		return $early_return;
	}

	if ( ! secupress_ip_is_valid( $ip ) ) {
		return false;
	}

	// Some hardcoded IPs that are always whitelisted.
	$whitelist = [
		'::1'  => 1,
		// '0.0.0.0' => 1, // now blacklisted by default
		'127.0.0.1' => 1,
		'46.30.211.168' => 1,
		'46.30.212.76' => 1,
		'46.30.212.77' => 1,
		'46.30.212.78' => 1,
		'46.30.212.79' => 1,
		'46.30.211.69' => 1,
		'46.30.212.200' => 1,
		'46.30.212.201' => 1,
		'46.30.212.202' => 1,
		'46.30.212.203' => 1,
		'46.30.211.203' => 1,
		'46.30.212.204' => 1,
		'46.30.212.205' => 1,
		'46.30.212.206' => 1,
		'46.30.212.207' => 1,
		'46.30.211.236' => 1,
		'5.249.224.8' => 1,
		'5.249.224.9' => 1,
		'5.249.224.10' => 1,
		'5.249.224.11' => 1,
		'2a02:2350:4:200::/55' => 1,
		// WP Umbrella
		'212.129.45.77' => 1,
		'212.83.142.5' => 1,
		'212.83.175.107' => 1,
		'62.4.26.31' => 1,
		// Manage WP
		'34.211.180.66' => 1,
		'54.70.65.107' => 1,
		'34.210.224.7' => 1,
		'52.41.5.108' => 1,
		'52.35.72.129' => 1,
		'54.191.137.17' => 1,
		'35.162.254.253' => 1,
		'52.11.12.231' => 1,
		'52.11.29.70' => 1,
		'52.11.54.161' => 1,
		'52.24.142.159' => 1,
		'52.25.191.255' => 1,
		'52.34.126.117' => 1,
		'52.34.254.47' => 1,
		'52.35.82.99' => 1,
		'52.36.28.80' => 1,
		'52.39.177.152' => 1,
		'52.41.237.12' => 1,
		'52.43.13.71' => 1,
		'52.43.76.224' => 1,
		'52.88.96.110' => 1,
		'52.89.155.51' => 1,
		'54.187.92.57' => 1,
		'54.191.32.65' => 1,
		'54.191.67.23' => 1,
		'54.191.80.119' => 1,
		'54.191.135.209' => 1,
		'54.191.136.176' => 1,
		'54.191.148.85' => 1,
		'54.191.149.8' => 1,
		'52.26.122.21' => 1,
		'52.24.187.29' => 1,
		'52.89.85.107' => 1,
		'54.186.128.167' => 1,
		'54.191.40.136' => 1,
		'52.88.119.122' => 1,
		'52.89.94.121' => 1,
		'52.25.116.116' => 1,
		'52.88.215.225' => 1,
		'54.186.143.184' => 1,
		'52.88.197.180' => 1,
		'52.27.171.126' => 1,
		'34.211.178.241' => 1,
		'52.24.232.158' => 1,
		'52.26.187.210' => 1,
		'52.42.189.119' => 1,
		'54.186.244.128' => 1,
		'54.71.54.102' => 1,
		'34.210.35.214' => 1,
		'34.213.77.188' => 1,
		'34.218.121.176' => 1,
		'52.10.190.191' => 1,
		'52.10.225.96' => 1,
		'52.11.187.168' => 1,
		'52.25.139.76' => 1,
		'52.43.127.200' => 1,
		'54.191.108.9' => 1,
		'54.70.201.228' => 1,
		'44.224.174.169' => 1,
		'52.32.57.81' => 1,
		'44.225.177.160' => 1,
		'34.223.186.249' => 1,
		'44.224.135.238' => 1,
		'44.226.111.14' => 1,
		'44.225.203.104' => 1,
		'44.226.100.122' => 1,
		'44.224.250.144' => 1,
		'44.225.118.211' => 1,
		'54.189.93.69' => 1,
		'44.231.184.112' => 1,
		'44.238.10.27' => 1,
		'54.185.116.30' => 1,
		'44.238.58.95' => 1,
		'52.13.23.154' => 1,
		'54.149.16.35' => 1,
		'44.226.97.20' => 1,
		'54.244.242.144' => 1,
		'44.238.67.135' => 1,
		'44.235.15.76' => 1,
		'54.214.47.164' => 1,
		'34.214.48.135' => 1,
		'54.184.234.227' => 1,
		'44.238.241.95' => 1,
		'52.37.217.170' => 1,
		'34.214.212.42' => 1,
		'54.203.109.179' => 1,
		'35.162.140.124' => 1,
		'54.184.226.94' => 1,
		'3.218.31.131' => 1,
		'54.162.61.185' => 1,
		// https://my.pingdom.com/probes/ipv4
		'13.232.220.164' => 1,
		'23.22.2.46' => 1,
		'23.83.129.219' => 1,
		'23.92.127.2' => 1,
		'23.106.37.99' => 1,
		'23.111.152.74' => 1,
		'23.111.159.174' => 1,
		'43.225.198.122' => 1,
		'43.229.84.12' => 1,
		'46.20.45.18' => 1,
		'46.246.122.10' => 1,
		'50.2.185.66' => 1,
		'50.16.153.186' => 1,
		'52.0.204.16' => 1,
		'52.24.42.103' => 1,
		'52.48.244.35' => 1,
		'52.52.34.158' => 1,
		'52.52.95.213' => 1,
		'52.52.118.192' => 1,
		'52.57.132.90' => 1,
		'52.59.46.112' => 1,
		'52.59.147.246' => 1,
		'52.62.12.49' => 1,
		'52.63.142.2' => 1,
		'52.63.164.147' => 1,
		'52.63.167.55' => 1,
		'52.67.148.55' => 1,
		'52.73.209.122' => 1,
		'52.89.43.70' => 1,
		'52.194.115.181' => 1,
		'52.197.31.124' => 1,
		'52.197.224.235' => 1,
		'52.198.25.184' => 1,
		'52.201.3.199' => 1,
		'52.209.34.226' => 1,
		'52.209.186.226' => 1,
		'52.210.232.124' => 1,
		'54.68.48.199' => 1,
		'54.70.202.58' => 1,
		'54.94.206.111' => 1,
		'64.237.49.203' => 1,
		'64.237.55.3' => 1,
		'66.165.229.130' => 1,
		'66.165.233.234' => 1,
		'72.46.130.18' => 1,
		'72.46.131.10' => 1,
		'76.164.234.106' => 1,
		'76.164.234.130' => 1,
		'82.103.136.16' => 1,
		'82.103.139.165' => 1,
		'82.103.145.126' => 1,
		'85.195.116.134' => 1,
		'89.163.146.247' => 1,
		'89.163.242.206' => 1,
		'94.75.211.73' => 1,
		'94.75.211.74' => 1,
		'94.247.174.83' => 1,
		'96.47.225.18' => 1,
		'103.47.211.210' => 1,
		'104.129.24.154' => 1,
		'104.129.30.18' => 1,
		'107.182.234.77' => 1,
		'108.181.70.3' => 1,
		'148.72.170.233' => 1,
		'148.72.171.17' => 1,
		'151.106.52.134' => 1,
		'162.218.67.34' => 1,
		'162.253.128.178' => 1,
		'168.1.203.46' => 1,
		'169.51.2.18' => 1,
		'169.54.70.214' => 1,
		'172.241.112.86' => 1,
		'173.248.147.18' => 1,
		'173.254.206.242' => 1,
		'174.34.156.130' => 1,
		'175.45.132.20' => 1,
		'178.162.206.244' => 1,
		'179.50.12.212' => 1,
		'184.75.210.90' => 1,
		'184.75.210.226' => 1,
		'184.75.214.66' => 1,
		'184.75.214.98' => 1,
		'185.39.146.214' => 1,
		'185.39.146.215' => 1,
		'185.70.76.23' => 1,
		'185.93.3.65' => 1,
		'185.136.156.82' => 1,
		'185.152.65.167' => 1,
		'185.180.12.65' => 1,
		'185.246.208.82' => 1,
		'190.120.230.7' => 1,
		'196.240.207.18' => 1,
		'196.244.191.18' => 1,
		'196.245.151.42' => 1,
		'199.87.228.66' => 1,
		'200.58.101.248' => 1,
		'201.33.21.5' => 1,
		'207.244.80.239' => 1,
		'209.58.139.193' => 1,
		'209.58.139.194' => 1,
		'209.95.50.14' => 1,
		'212.78.83.12' => 1,
		'212.78.83.16' => 1,
		// https://uptimerobot.com/inc/files/ips/IPv4.txt
		'216.144.250.150' => 1,
		'69.162.124.226' => 1,
		'69.162.124.227' => 1,
		'69.162.124.228' => 1,
		'69.162.124.229' => 1,
		'69.162.124.230' => 1,
		'69.162.124.231' => 1,
		'69.162.124.232' => 1,
		'69.162.124.233' => 1,
		'69.162.124.234' => 1,
		'69.162.124.235' => 1,
		'69.162.124.236' => 1,
		'69.162.124.237' => 1,
		'63.143.42.242' => 1,
		'63.143.42.243' => 1,
		'63.143.42.244' => 1,
		'63.143.42.245' => 1,
		'63.143.42.246' => 1,
		'63.143.42.247' => 1,
		'63.143.42.248' => 1,
		'63.143.42.249' => 1,
		'63.143.42.250' => 1,
		'63.143.42.251' => 1,
		'63.143.42.252' => 1,
		'63.143.42.253' => 1,
		'216.245.221.82' => 1,
		'216.245.221.83' => 1,
		'216.245.221.84' => 1,
		'216.245.221.85' => 1,
		'216.245.221.86' => 1,
		'216.245.221.87' => 1,
		'216.245.221.88' => 1,
		'216.245.221.89' => 1,
		'216.245.221.90' => 1,
		'216.245.221.91' => 1,
		'216.245.221.92' => 1,
		'216.245.221.93' => 1,
		'46.137.190.132' => 1,
		'122.248.234.23' => 1,
		'188.226.183.141' => 1,
		'178.62.52.237' => 1,
		'54.79.28.129' => 1,
		'54.94.142.218' => 1,
		'104.131.107.63' => 1,
		'54.67.10.127' => 1,
		'54.64.67.106' => 1,
		'159.203.30.41' => 1,
		'46.101.250.135' => 1,
		'18.221.56.27' => 1,
		'52.60.129.180' => 1,
		'159.89.8.111' => 1,
		'146.185.143.14' => 1,
		'139.59.173.249' => 1,
		'165.227.83.148' => 1,
		'128.199.195.156' => 1,
		'138.197.150.151' => 1,
		'34.233.66.117' => 1,
		// https://app.statuscake.com/Workfloor/Locations.php?format=txt
		'216.144.250.150' => 1,
		'69.162.124.226' => 1,
		'69.162.124.227' => 1,
		'69.162.124.228' => 1,
		'69.162.124.229' => 1,
		'69.162.124.230' => 1,
		'69.162.124.231' => 1,
		'69.162.124.232' => 1,
		'69.162.124.233' => 1,
		'69.162.124.234' => 1,
		'69.162.124.235' => 1,
		'69.162.124.236' => 1,
		'69.162.124.237' => 1,
		'69.162.124.238' => 1,
		'63.143.42.242' => 1,
		'63.143.42.243' => 1,
		'63.143.42.244' => 1,
		'63.143.42.245' => 1,
		'63.143.42.246' => 1,
		'63.143.42.247' => 1,
		'63.143.42.248' => 1,
		'63.143.42.249' => 1,
		'63.143.42.250' => 1,
		'63.143.42.251' => 1,
		'63.143.42.252' => 1,
		'63.143.42.253' => 1,
		'216.245.221.82' => 1,
		'216.245.221.83' => 1,
		'216.245.221.84' => 1,
		'216.245.221.85' => 1,
		'216.245.221.86' => 1,
		'216.245.221.87' => 1,
		'216.245.221.88' => 1,
		'216.245.221.89' => 1,
		'216.245.221.90' => 1,
		'216.245.221.91' => 1,
		'216.245.221.92' => 1,
		'216.245.221.93' => 1,
		'208.115.199.18' => 1,
		'208.115.199.19' => 1,
		'208.115.199.20' => 1,
		'208.115.199.21' => 1,
		'208.115.199.22' => 1,
		'208.115.199.23' => 1,
		'208.115.199.24' => 1,
		'208.115.199.25' => 1,
		'208.115.199.26' => 1,
		'208.115.199.27' => 1,
		'208.115.199.28' => 1,
		'208.115.199.29' => 1,
		'208.115.199.30' => 1,
		'216.144.248.18' => 1,
		'216.144.248.19' => 1,
		'216.144.248.20' => 1,
		'216.144.248.21' => 1,
		'216.144.248.22' => 1,
		'216.144.248.23' => 1,
		'216.144.248.24' => 1,
		'216.144.248.25' => 1,
		'216.144.248.26' => 1,
		'216.144.248.27' => 1,
		'216.144.248.28' => 1,
		'216.144.248.29' => 1,
		'216.144.248.30' => 1,
		'46.137.190.132' => 1,
		'122.248.234.23' => 1,
		'167.99.209.234' => 1,
		'178.62.52.237' => 1,
		'54.79.28.129' => 1,
		'54.94.142.218' => 1,
		'104.131.107.63' => 1,
		'54.67.10.127' => 1,
		'54.64.67.106' => 1,
		'159.203.30.41' => 1,
		'46.101.250.135' => 1,
		'18.221.56.27' => 1,
		'52.60.129.180' => 1,
		'159.89.8.111' => 1,
		'146.185.143.14' => 1,
		'139.59.173.249' => 1,
		'165.227.83.148' => 1,
		'128.199.195.156' => 1,
		'138.197.150.151' => 1,
		'34.233.66.117' => 1,
		'52.70.84.165' => 1,
		'54.225.82.45' => 1,
		'54.224.73.211' => 1,
		'3.79.92.117' => 1,
		'3.21.136.87' => 1,
		'35.170.215.196' => 1,
		'35.153.243.148' => 1,
		'18.116.158.121' => 1,
		'18.223.50.16' => 1,
		'54.241.175.147' => 1,
		'3.212.128.62' => 1,
		'52.22.236.30' => 1,
		'54.167.223.174' => 1,
		'3.12.251.153' => 1,
		'52.15.147.27' => 1,
		'18.116.205.62' => 1,
		'3.20.63.178' => 1,
		'13.56.33.4' => 1,
		'52.8.208.143' => 1,
		'34.198.201.66' => 1,
		'35.84.118.171' => 1,
		'44.227.38.253' => 1,
		'35.166.228.98' => 1,
		'99.80.173.191' => 1,
		'99.80.1.74' => 1,
		'3.111.88.158' => 1,
		'13.127.188.124' => 1,
		'18.180.208.214' => 1,
		'54.249.170.27' => 1,
		'3.105.190.221' => 1,
		'3.105.133.239' => 1,
		'78.47.98.55' => 1,
		'157.90.155.240' => 1,
		'49.13.24.81' => 1,
		'168.119.96.239' => 1,
		'157.90.156.63' => 1,
		'88.99.80.227' => 1,
		'49.13.134.145' => 1,
		'49.13.130.29' => 1,
		'168.119.53.160' => 1,
		'142.132.180.39' => 1,
		'49.13.164.148' => 1,
		'128.140.106.114' => 1,
		'78.47.173.76' => 1,
		'159.69.158.189' => 1,
		'128.140.41.193' => 1,
		'167.235.143.113' => 1,
		'49.13.167.123' => 1,
		'78.46.215.1' => 1,
		'78.46.190.63' => 1,
		'168.119.123.75' => 1,
		'135.181.154.9' => 1,
		'37.27.87.149' => 1,
		'37.27.34.49' => 1,
		'37.27.82.220' => 1,
		'65.109.129.165' => 1,
		'37.27.28.153' => 1,
		'37.27.29.68' => 1,
		'37.27.30.213' => 1,
		'65.109.142.78' => 1,
		'65.109.8.202' => 1,
		'5.161.75.7' => 1,
		'5.161.61.238' => 1,
		'5.78.87.38' => 1,
		'5.78.118.142' => 1,
		// https://updown.io/about
		'45.32.74.41' => 1,
		'104.238.136.194' => 1,
		'192.99.37.47' => 1,
		'198.27.83.55' => 1,
		'91.121.222.175' => 1,
		'104.238.159.87' => 1,
		'135.181.102.135' => 1,
		'45.32.107.181' => 1,
		'45.76.104.117' => 1,
		'45.63.29.207' => 1,
		// Facebook / Meta inc
		'57.144.0.0/14' => 1,
		'57.146.78.172' => 1,
		'57.142.0.0/15' => 1,
		'57.142.79.75' => 1,
		'57.148.0.0/15' => 1,
		'57.148.77.228' => 1,
		'129.134.0.0/16' => 1,
		'129.134.241.234' => 1,
		'157.240.0.0/16' => 1,
		'157.240.35.2' => 1,
		'57.141.0.0/16' => 1,
		'57.141.118.59' => 1,
		'173.252.64.0/18' => 1,
		'173.252.121.92' => 1,
		'31.13.64.0/18' => 1,
		'31.13.107.143' => 1,
		'69.171.224.0/19' => 1,
		'69.171.227.217' => 1,
		'54.85.224.0/20' => 1,
		'54.85.234.109' => 1,
		'66.220.144.0/20' => 1,
		'66.220.152.150' => 1,
		'69.63.176.0/20' => 1,
		'69.63.188.121' => 1,
		'185.60.216.0/22' => 1,
		'185.60.216.123' => 1,
		'199.201.64.0/22' => 1,
		'199.201.66.47' => 1,
		'204.15.20.0/22' => 1,
		'204.15.23.17' => 1,
		'74.119.76.0/22' => 1,
		'74.119.79.228' => 1,
		'208.255.115.0/24' => 1,
		'208.255.115.30' => 1,
		'31.13.102.0/24' => 1,
		'31.13.102.77' => 1,
		'31.13.110.0/24' => 1,
		'31.13.110.136' => 1,
		'31.13.65.0/24' => 1,
		'31.13.65.158' => 1,
		'31.13.66.0/24' => 1,
		'31.13.66.58' => 1,
		'31.13.69.0/24' => 1,
		'31.13.69.122' => 1,
		'31.13.70.0/24' => 1,
		'31.13.70.10' => 1,
		'31.13.71.0/24' => 1,
		'31.13.71.125' => 1,
		'31.13.73.0/24' => 1,
		'31.13.73.34' => 1,
		'31.13.74.0/24' => 1,
		'31.13.74.255' => 1,
		'31.13.75.0/24' => 1,
		'31.13.75.250' => 1,
		'31.13.76.0/24' => 1,
		'31.13.76.245' => 1,
		'31.13.77.0/24' => 1,
		'31.13.77.81' => 1,
		'31.13.78.0/24' => 1,
		'31.13.78.150' => 1,
		'31.13.97.0/24' => 1,
		'31.13.97.219' => 1,
		'31.13.98.0/24' => 1,
		'31.13.98.126' => 1,
		'31.13.99.0/24' => 1,
		'31.13.99.234' => 1,
		'208.65.104.192/27' => 1,
		'208.65.104.207' => 1,
		'38.104.95.112/29' => 1,
		'38.104.95.117' => 1,
		'38.104.95.120/29' => 1,
		'38.104.95.127' => 1,
		'38.142.35.192/29' => 1,
		'38.142.35.196' => 1,
		'38.88.39.0/29' => 1,
		'2a03:2880::/29' => 1,
		// Google
		'142.250.0.0/15' => 1,
		'142.250.62.25' => 1,
		'192.178.0.0/15' => 1,
		'192.179.61.157' => 1,
		'172.217.0.0/16' => 1,
		'172.217.189.81' => 1,
		'172.253.0.0/16' => 1,
		'172.253.11.52' => 1,
		'74.125.0.0/16' => 1,
		'74.125.68.229' => 1,
		'108.177.0.0/17' => 1,
		'108.177.51.112' => 1,
		'209.85.128.0/17' => 1,
		'209.85.132.97' => 1,
		'23.255.128.0/17' => 1,
		'23.255.215.103' => 1,
		'99.198.128.0/17' => 1,
		'99.198.249.241' => 1,
		'108.170.192.0/18' => 1,
		'108.170.240.202' => 1,
		'45.56.0.0/18' => 1,
		'45.56.10.77' => 1,
		'72.14.192.0/18' => 1,
		'72.14.205.6' => 1,
		'192.200.224.0/19' => 1,
		'192.200.241.160' => 1,
		'216.58.192.0/19' => 1,
		'216.58.218.37' => 1,
		'64.233.160.0/19' => 1,
		'64.233.190.53' => 1,
		'64.9.224.0/19' => 1,
		'64.9.231.13' => 1,
		'66.249.64.0/19' => 1,
		'66.249.65.182' => 1,
		'70.32.128.0/19' => 1,
		'70.32.146.28' => 1,
		'192.119.16.0/20' => 1,
		'192.119.16.110' => 1,
		'207.223.160.0/20' => 1,
		'207.223.167.16' => 1,
		'209.107.176.0/20' => 1,
		'209.107.183.235' => 1,
		'216.21.160.0/20' => 1,
		'216.21.165.100' => 1,
		'216.239.48.0/20' => 1,
		'216.239.62.233' => 1,
		'66.102.0.0/20' => 1,
		'66.102.11.127' => 1,
		'172.102.8.0/21' => 1,
		'172.102.13.228' => 1,
		// CenturyLink Communications, LLC
		'64.68.88.0/21' => 1,
		'64.68.89.106' => 1,
		'8.34.208.0/21' => 1,
		'8.34.212.2' => 1,
		'8.34.216.0/21' => 1,
		'8.34.223.9' => 1,
		'8.35.192.0/21' => 1,
		'8.35.192.12' => 1,
		'8.6.48.0/21' => 1,
		'8.6.50.183' => 1,
		'89.207.224.0/21' => 1,
		'89.207.228.213' => 1,
		'162.216.148.0/22' => 1,
		'162.216.148.58' => 1,
		'199.192.112.0/22' => 1,
		'199.192.114.231' => 1,
		'216.239.36.0/22' => 1,
		'216.239.38.175' => 1,
		'216.239.40.0/22' => 1,
		'216.239.41.73' => 1,
		'216.252.220.0/22' => 1,
		'216.252.221.153' => 1,
		'104.132.0.0/23' => 1,
		'104.132.1.99' => 1,
		'104.133.8.0/23' => 1,
		'104.133.8.1' => 1,
		'104.134.132.0/23' => 1,
		'104.134.133.207' => 1,
		'104.134.160.0/23' => 1,
		'104.134.161.222' => 1,
		'192.104.160.0/23' => 1,
		'192.104.160.171' => 1,
		'208.68.108.0/23' => 1,
		'208.68.108.45' => 1,
		'216.239.32.0/23' => 1,
		'216.239.32.23' => 1,
		'216.239.46.0/23' => 1,
		'216.239.46.165' => 1,
		'104.132.106.0/24' => 1,
		'104.132.106.107' => 1,
		'104.132.108.0/24' => 1,
		'104.132.108.190' => 1,
		'104.132.110.0/24' => 1,
		'104.132.110.172' => 1,
		'104.132.114.0/24' => 1,
		'104.132.114.98' => 1,
		'104.132.123.0/24' => 1,
		'104.132.123.175' => 1,
		'104.132.129.0/24' => 1,
		'104.132.129.85' => 1,
		'104.132.133.0/24' => 1,
		'104.132.133.48' => 1,
		'104.132.179.0/24' => 1,
		'104.132.179.196' => 1,
		'104.132.194.0/24' => 1,
		'104.132.194.149' => 1,
		'104.132.211.0/24' => 1,
		'104.132.211.104' => 1,
		'104.132.213.0/24' => 1,
		'104.132.213.99' => 1,
		'104.132.214.0/24' => 1,
		'104.132.214.2' => 1,
		'104.132.215.0/24' => 1,
		'104.132.215.39' => 1,
		'104.132.247.0/24' => 1,
		'104.132.247.167' => 1,
		'104.132.248.0/24' => 1,
		'104.132.248.172' => 1,
		'104.132.251.0/24' => 1,
		'104.132.251.106' => 1,
		'104.132.27.0/24' => 1,
		'104.132.27.57' => 1,
		'104.132.34.0/24' => 1,
		'104.132.34.226' => 1,
		'104.132.45.0/24' => 1,
		'104.132.45.201' => 1,
		'104.132.5.0/24' => 1,
		'104.132.5.23' => 1,
		'104.132.53.0/24' => 1,
		'104.132.53.244' => 1,
		'104.132.55.0/24' => 1,
		'104.132.55.168' => 1,
		'104.132.59.0/24' => 1,
		'104.132.59.217' => 1,
		'104.132.66.0/24' => 1,
		'104.132.66.165' => 1,
		'104.132.7.0/24' => 1,
		'104.132.7.63' => 1,
		'104.132.72.0/24' => 1,
		'104.132.72.32' => 1,
		'104.132.73.0/24' => 1,
		'104.132.73.30' => 1,
		'104.132.77.0/24' => 1,
		'104.132.77.44' => 1,
		'104.132.8.0/24' => 1,
		'104.132.8.41' => 1,
		'104.133.106.0/24' => 1,
		'104.133.106.222' => 1,
		'104.133.126.0/24' => 1,
		'104.133.126.123' => 1,
		'104.133.142.0/24' => 1,
		'104.133.142.195' => 1,
		'104.133.202.0/24' => 1,
		'104.133.202.23' => 1,
		'104.133.227.0/24' => 1,
		'104.133.227.117' => 1,
		'104.133.24.0/24' => 1,
		'104.133.24.73' => 1,
		'104.133.29.0/24' => 1,
		'104.133.29.199' => 1,
		'104.133.55.0/24' => 1,
		'104.133.55.111' => 1,
		'104.133.65.0/24' => 1,
		'104.133.65.39' => 1,
		'104.133.7.0/24' => 1,
		'104.133.7.227' => 1,
		'104.133.71.0/24' => 1,
		'104.133.71.90' => 1,
		'104.134.0.0/24' => 1,
		'104.134.0.99' => 1,
		'104.134.1.0/24' => 1,
		'104.134.1.187' => 1,
		'104.134.129.0/24' => 1,
		'104.134.129.56' => 1,
		'104.134.131.0/24' => 1,
		'104.134.131.20' => 1,
		'104.134.137.0/24' => 1,
		'104.134.137.244' => 1,
		'104.134.140.0/24' => 1,
		'104.134.140.135' => 1,
		'104.134.141.0/24' => 1,
		'104.134.141.38' => 1,
		'104.134.147.0/24' => 1,
		'104.134.147.68' => 1,
		'104.134.150.0/24' => 1,
		'104.134.150.113' => 1,
		'104.134.151.0/24' => 1,
		'104.134.151.142' => 1,
		'104.134.152.0/24' => 1,
		'104.134.152.66' => 1,
		'104.134.153.0/24' => 1,
		'104.134.153.81' => 1,
		'104.134.154.0/24' => 1,
		'104.134.154.133' => 1,
		'104.134.155.0/24' => 1,
		'104.134.155.96' => 1,
		'104.134.158.0/24' => 1,
		'104.134.158.223' => 1,
		'104.134.162.0/24' => 1,
		'104.134.162.196' => 1,
		'2a00:1450::/29' => 1,
		'2001:4860::/32' => 1,
		'2404:f340::/32' => 1,
		'2607:f8b0::/32' => 1,
		'2620:0:1000::/40' => 1,
		'2a00:79e0::/40' => 1,
		'2401:fa00::/48' => 1,
		'2401:fa00:1::/48' => 1,
		'2401:fa00:10::/48' => 1,
		'2401:fa00:13::/48' => 1,
		'2401:fa00:18::/48' => 1,
		'2401:fa00:19::/48' => 1,
		'2401:fa00:1c::/48' => 1,
		'2401:fa00:1d::/48' => 1,
		'2401:fa00:1f::/48' => 1,
		'2401:fa00:2::/48' => 1,
		'2401:fa00:21::/48' => 1,
		'2401:fa00:23::/48' => 1,
		'2401:fa00:24::/48' => 1,
		'2401:fa00:26::/48' => 1,
		'2401:fa00:2a::/48' => 1,
		'2401:fa00:2b::/48' => 1,
		'2401:fa00:2d::/48' => 1,
	];

	if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
		$whitelist[ $_SERVER['SERVER_ADDR'] ] = 1;
	}
	// The IPs from the settings page.
	$_whitelist = get_site_option( SECUPRESS_WHITE_IP );
	if ( $_whitelist ) {
		$_whitelist = array_flip( array_keys( $_whitelist ) );
		$whitelist  = array_merge( $whitelist, $_whitelist );
	}
	/**
	 * Filter the IPs whitelist.
	 *
	 * @since 1.0
	 *
	 * @param (array)  $whitelist The whitelist. IPs are the array keys.
	 * @param (string) $ip        The IP address.
	 */
	$whitelist = apply_filters( 'secupress.ip.ips_whitelist', $whitelist, $ip );
	if ( isset( $whitelist[ $ip ] ) ) {
		return true;
	}

	if ( $in_range ) {
		// Handle IP ranges lately
		$whitelist = array_keys( $whitelist );
		$whitelist = array_filter( $whitelist, function( $item ) {
			return strpos( $item, '*' ) > 0 || strpos( $item, '/' ) > 0 || strpos( $item, '-' ) > 0;
		} );

		return secupress_is_ip_in_range( $ip, $whitelist );
	}

	return false;
}

/**
 * Tell if an IP address is blacklisted.
 *
 * @since 2.2.3 0.0.0.0 is now blacklisted
 * @since 1.4.9
 *
 * @param (string) $ip An IP address. If not provided, the current IP by default.
 *
 * @return (bool).
 */
function secupress_ip_is_blacklisted( $ip = null ) {
	$ip = $ip ? $ip : secupress_get_ip();

	if ( ! secupress_ip_is_valid( $ip ) ) {
		return false;
	}

	// The IPs from the settings page.
	$blacklist            = get_site_option( SECUPRESS_BAN_IP );
	$blacklist            = array_flip( array_keys( $blacklist ) );
	$blacklist['0.0.0.0'] = 1;
	/**
	 * Filter the IPs blacklist.
	 *
	 * @since 1.4.9
	 *
	 * @param (array)  $blacklist The blacklist. IPs are the array keys.
	 * @param (string) $ip        The IP address.
	 */
	$blacklist = apply_filters( 'secupress.ip.ips_blacklist', $blacklist, $ip );
	if ( isset( $blacklist[ $ip ] ) ) {
		return true;
	}
	// Handle IP ranges lately
	$blacklist = array_keys( $blacklist );
	$blacklist = array_filter( $blacklist, function( $item ) {
		return strpos( $item, '*' ) > 0 || strpos( $item, '/' ) > 0 || strpos( $item, '-' ) > 0;
	} );
	return secupress_is_ip_in_range( $ip, $blacklist );
}

/**
 * Check if the asked IP is in the asked range :
 * • 123.123.123.0-24 = from 123.123.123.0 to 123.123.123.24
 * • 123.123.123.0/24 = from 123.123.123.0 to 123.123.123.255
 * • 123.123.*.*      = from 123.123.0.0   to 123.123.255.255
 *
 * • fedc:6482:cafe::-fedc:6482:cafe:ffff:ffff:ffff:ffff:ffff = from fedc:6482:cafe:0:0:0:0:0 to fedc:6482:cafe:ffff:ffff:ffff:ffff:ffff
 * • fedc:6482:cafe::/32 = from fedc:6482:cafe:0:0:0:0:0 to fedc:cafe:FFFF:ffff:ffff:ffff:ffff:ffff
 * • fedc:6482:cafe:* = from fedc:6482:cafe:0:0:0:0:0    to fedc:6482:FFFF:ffff:ffff:ffff:ffff:ffff
 *
 * @since 2.7.2 Compare the full 128-bit IPv6 prefix.
 * @since 2.2.3 Use "continue" instad of "return" too soon.
 * @since 1.4.9
 * @author Julio Potier
 *
 * @param (string) $ip The $ip to be checked
 * @param (array) $ips The IPS whitelist
 * @return (bool) True if in range
 **/
function secupress_is_ip_in_range( $ip, $ips ) {
	if ( empty( $ips ) || ! is_array( $ips ) ) {
		return false;
	}
	foreach ( $ips as $_ips ) {
		if ( secupress_ip_is_valid( $ip, true, FILTER_FLAG_IPV4 ) && secupress_ip_is_valid( $_ips, true, FILTER_FLAG_IPV4 ) ) {
			if ( 0 === strcmp( $ip, $_ips ) ) {
				return true;
			}
			if ( strpos( $_ips, '-' ) ) {
				list( $first_ip, $mask ) = explode( '-', $_ips );
				$_ip      = explode('.', $first_ip);
				$_ip[3]   = $mask;
				$last_ip  = implode('.', $_ip);

				if ( ip2long( $ip ) >= ip2long( $first_ip ) && ip2long( $ip ) <= ip2long( $last_ip ) ) {
					return true;
				}
				continue;
			}
			if ( strpos( $_ips, '/' ) ) {
				list( $first_ip, $mask ) = explode( '/', $_ips );
				if ( $mask === '0' ) {
					return true;
				}
				if ( $mask < 0 || $mask > 32 ) {
					continue;
				}
				if ( 0 === substr_compare( sprintf( '%032b', ip2long( $ip ) ), sprintf( '%032b', ip2long( $first_ip ) ), 0, $mask ) ) {
					return true;
				}
				continue;
			}
			if ( strpos( $_ips, '*' ) ) {
				$mask     = str_replace( '*', '', $_ips );
				$mask     = explode( '.', $mask );
				$mask     = array_filter( $mask );
				$mask     = $mask + array_fill( count( $mask ), 4 - count( $mask ), '0/255' );
				$mask     = implode( '.', $mask );
				$first_ip = str_replace( '0/255', '0', $mask );
				$last_ip  = str_replace( '0/255', '255', $mask );

				if ( ip2long( $ip ) >= ip2long( $first_ip ) && ip2long( $ip ) <= ip2long( $last_ip ) ) {
					return true;
				}
				continue;
			}
		} elseif ( secupress_ip_is_valid( $ip, true, FILTER_FLAG_IPV6 ) && secupress_ip_is_valid( $_ips, true, FILTER_FLAG_IPV6 ) ) {
			if ( 0 === strcmp( $ip, $_ips ) ) {
				return true;
			}
			if ( strpos( $_ips, '::-' ) ) {
				$temp     = explode( '::-', $_ips );
				$first_ip = $temp[0] . str_repeat( ':0', 7 - substr_count( $temp[0], ':' ) );
				$last_ip  = $temp[1];

				if ( secupress_ipv6_numeric( $ip ) >= secupress_ipv6_numeric( $first_ip ) && secupress_ipv6_numeric( $ip ) <= secupress_ipv6_numeric( $last_ip ) ) {
					return true;
				}
				continue;
			}
			if ( strpos( $_ips, '/' ) ) {
				list( $first_ip, $mask ) = explode( '/', $_ips, 2 );
				$mask = (int) $mask;
				if ( $mask < 1 || $mask > 128 ) {
					continue;
				}
				$range_bin = inet_pton( $first_ip );
				$ip_bin    = inet_pton( $ip );
				if ( false === $range_bin || false === $ip_bin ) {
					continue;
				}
				$full_bytes     = intdiv( $mask, 8 );
				$remaining_bits = $mask % 8;
				if ( $full_bytes > 0 && substr( $range_bin, 0, $full_bytes ) !== substr( $ip_bin, 0, $full_bytes ) ) {
					continue;
				}
				if ( $remaining_bits > 0 ) {
					$bitmask = ( 0xff << ( 8 - $remaining_bits ) ) & 0xff;
					if ( ( ord( $range_bin[ $full_bytes ] ) & $bitmask ) !== ( ord( $ip_bin[ $full_bytes ] ) & $bitmask ) ) {
						continue;
					}
				}
				return true;
			}
			if ( strpos( $_ips, '*' ) ) {
				$_ips     = str_replace( '*', '', $_ips );
				$first_ip = secupress_get_full_ipv6( $_ips, '0' );
				$last_ip  = secupress_get_full_ipv6( $_ips, 'ffff' );
				if ( secupress_ipv6_numeric( $ip ) >= secupress_ipv6_numeric( $first_ip ) && secupress_ipv6_numeric( $ip ) <= secupress_ipv6_numeric( $last_ip ) ) {
					return true;
				}
				continue;
			}
		}
	}
	return false;
}

/**
 * Ban an IP address if not whitelisted.
 * Will add the IP to the list of banned IPs. Will maybe write the IPs in the `.htaccess` file. Will maybe forbid access to the user by displaying a message.
 *
 * @since 1.0
 *
 * @param (int)    $time_ban Ban duration in minutes. Only used in the message.
 * @param (string) $ip       The IP to ban.
 * @param (array)   $die      True to forbid access to the user by displaying a message.
 */
function secupress_ban_ip( $time_ban = 5, $ip = null, $args = [] ) {
	$ip = $ip ? $ip : secupress_get_ip();

	if ( secupress_ip_is_whitelisted( $ip ) ) {
		return;
	}

	$time_ban = (int) $time_ban > 0 ? (int) $time_ban : 5;
	$ban_ips  = get_site_option( SECUPRESS_BAN_IP );
	$ban_ips  = is_array( $ban_ips ) ? $ban_ips : array();
	if ( is_bool( $args ) ) {
		$args['die'] = true;
	}
	$die             = isset( $args['die'] ) && $args['die'];
	$attack_type     = isset( $args['attack_type'] ) ? $args['attack_type'] : 'ban_ip';

	$ban_ips[ $ip ]  = time();

	update_site_option( SECUPRESS_BAN_IP, $ban_ips );

	/**
	 * Fires once a IP is banned.
	 *
	 * @since 1.0
	 *
	 * @param (string) $ip      The IP banned.
	 * @param (array)  $ban_ips The list of IPs banned (keys) and the time they were banned (values).
	 */
	do_action( 'secupress.ban.ip_banned', $ip, $ban_ips );

	if ( $die ) {
		secupress_die( sprintf(
			_n( 'Your IP address %1$s has been banned for %2$s minute, please do not retry until then.', 'Your IP address %1$s has been banned for %2$s minutes, please do not retry until then.', $time_ban, 'secupress' ),
			'<code>' . esc_html( $ip ) . '</code>',
			'<strong>' . number_format_i18n( $time_ban ) . '</strong>'
		), [ 'force_die' => true, 'context' => 'ban_ip', 'attack_type' => $attack_type ] );
	}
}


/**
 * Returns if the user-agent is a real bot (true) or not, a fake one (false).
 *
 * @since 2.6.2 Use secupress.me instead of google for testing, use PTR records for IP to hostname lookup
 * @since 1.4.2 Add $test param + revamp
 * @since 1.4
 *
 * @param (bool) $test Set to TRUE to just get the googlebot hostname test result (transient enabled).
 * @return (bool) true mean the IP is a good bot, false is a fake bot.
 *
 * @author Julio Potier
 **/
function secupress_check_bot_ip( $test = false ) {
	static $test_result;

	if ( secupress_ip_is_whitelisted( secupress_get_ip() ) ) {
		return true;
	}

	if ( $test && isset( $test_result ) ) {
		return $test_result;
	}
	if ( $test && ( false !== ( $test_result = get_site_transient( 'secupress-test-hostname' ) ) ) ) {
		return $test_result;
	}

	if ( ! $test ) {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? trim( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$bot_regex  = '';
		if ( preg_match( '/google/i', $user_agent ) ) {
			$bot_regex = '/\.(googlebot|google)\.com$/i';
		} elseif ( preg_match( '/bingbot|msnbot/i', $user_agent ) ) {
			$bot_regex = '/\.(search\.)?msn\.com$/i';
		} elseif ( preg_match( '/facebot|facebook/i', $user_agent ) ) {
			$bot_regex = '/\.(facebook|fb)\.com$/i';
		} elseif ( preg_match( '/meta-/i', $user_agent ) ) {
			$bot_regex = '/\.(meta-)\.com$/i';
		} elseif ( preg_match( '/slurp/i', $user_agent ) ) {
			$bot_regex = '/\.yahoo\.(com|net)$/i';
		} elseif ( preg_match( '/baiduspider/i', $user_agent ) ) {
			$bot_regex = '/\.baidu\.(com|jp)$/i';
		} elseif ( preg_match( '/yandexbot/i', $user_agent ) ) {
			$bot_regex = '/\.yandex\.(com|ru|net)$/i';
		} elseif ( preg_match( '/duckduckbot/i', $user_agent ) ) {
			$bot_regex = '/\.duckduckgo\.com$/i';
		} elseif ( preg_match( '/ia_archiver/i', $user_agent ) ) {
			$bot_regex = '/\.(alexa|amazonaws)\.com$/i';
		} else {
			return false;
		}
		$ip = secupress_get_ip();
	} else {
		$ip = '109.234.167.151'; // SecuPress.me
	}

	$hostname_addr = gethostbyaddr( $ip );
	$hostname      = is_string( $hostname_addr ) && ! secupress_ip_is_valid( $hostname_addr ) ? strtolower( trim( $hostname_addr ) ) : '';
	$real_ip       = $hostname ? gethostbyname( $hostname ) : '';
	$real_ip_match = ( $real_ip && $real_ip !== $hostname && $real_ip === $ip );

	if ( ! $real_ip_match && $hostname ) {
		$real_ips = gethostbynamel( $hostname );
		if ( is_array( $real_ips ) && in_array( $ip, $real_ips, true ) ) {
			$real_ip_match = true;
		}
	}

	if ( ! $real_ip_match && $hostname && function_exists( 'dns_get_record' ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$real_ips = dns_get_record( $hostname, DNS_AAAA );
		if ( is_array( $real_ips ) ) {
			foreach ( $real_ips as $record ) {
				if ( ! empty( $record['ipv6'] ) && $record['ipv6'] === $ip ) {
					$real_ip_match = true;
					break;
				}
			}
		}
	}
	
	if ( true === $test ) {
		$test_result = (int) ( preg_match( '/109-234-167-151\.reverse\.odns\.fr$/i', $hostname ) && $real_ip_match );
		set_site_transient( 'secupress-test-hostname', $test_result, WEEK_IN_SECONDS );
		return (bool) $test_result;
	}

	if ( $real_ip_match && preg_match( $bot_regex, $hostname ) ) {
		return true;
	}

	return false;
}

/**
 * Convert a IPv6 into decimal value, stripping it to $length (19 to match a bigint)
 *
 * @since 1.4.9
 * @author Julio Potier
 *
 * @source https://stackoverflow.com/questions/18276757/php-convert-ipv6-to-number
 *
 * @param (string)  $ip     The IPv6 to be converted
 * @param (integer) $length The max length of the decimal representation, "19" by default
 * @return (string) Decimal representation, stripped
 **/
function secupress_ipv6_numeric( $ip, $length = 19 ) {
	$bin = '';
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		return '0';
	}
	foreach ( unpack( 'C*', inet_pton( $ip ) ) as $byte ) {
		$bin .= str_pad( decbin( $byte ), 8, '0', STR_PAD_LEFT );
	}
	return substr( base_convert( ltrim( $bin, '0'), 2, 10 ), 0, $length );
}

/**
 * Get a headers array from the licence data to build the Authorization
 *
 * @since 2.2.6
 * @author Julio Potier
 *
 * @param (string) $consumer_email
 * @param (string) $consumer_key
 * 
 * @return (array) Contains HTTP Basic Auth
 **/
function secupress_get_basic_auth_headers( $consumer_email = '', $consumer_key = '' ) {
	$consumer_email = $consumer_email ?: secupress_get_consumer_email();
	$consumer_key   = $consumer_key   ?: secupress_get_consumer_key();

	return [ 'Authorization' => 'Basic ' . base64_encode( $consumer_email . ':' . $consumer_key ) ];
}

/**
 * Returns true if 2 given IPs are close enough
 *
 * @since 2.6
 * @author Julio Potier
 * 
 * @param (string) $ip1
 * @param (string) $ip2, default is current visitor IP
 * @param (int) $network, default is 255
 * 
 * @return (bool)
 **/
function secupress_ips_are_close( $ip1, $ip2 = '', $network = 255 ) {
	$ip2 = $ip2 ?: secupress_get_ip();

	if ( ! secupress_ip_is_valid( $ip1 ) || ! secupress_ip_is_valid( $ip2 ) ) {
		return false;
	}

	$is_ipv4_1 = filter_var( $ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
	$is_ipv4_2 = filter_var( $ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
	$is_ipv6_1 = filter_var( $ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
	$is_ipv6_2 = filter_var( $ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );

	if ( $is_ipv4_1 && $is_ipv4_2 ) {
		$network1 = preg_replace( '/\.[^.]+$/', '', $ip1 );
		$network2 = preg_replace( '/\.[^.]+$/', '', $ip2 );

		if ( 0 === strcmp( $network1, $network2 ) ) {
			return true;
		}

		$ip_diff = abs( ip2long( $ip1 ) - ip2long( $ip2 ) );
		return $ip_diff <= $network;
	}

	if ( $is_ipv6_1 && $is_ipv6_2 ) {
		$bin1 = inet_pton( $ip1 );
		$bin2 = inet_pton( $ip2 );

		if ( false === $bin1 || false === $bin2 ) {
			return false;
		}

		$bytes1 = unpack( 'n*', $bin1 );
		$bytes2 = unpack( 'n*', $bin2 );

		if ( ! $bytes1 || ! $bytes2 ) {
			return false;
		}

		$segments_to_compare = min( 4, ceil( $network / 16 ) );

		for ( $i = 1; $i <= $segments_to_compare; $i++ ) {
			if ( ! isset( $bytes1[ $i ] ) || ! isset( $bytes2[ $i ] ) ) {
				return false;
			}
			if ( $bytes1[ $i ] !== $bytes2[ $i ] ) {
				return false;
			}
		}

		return true;
	}

	return false;
}
