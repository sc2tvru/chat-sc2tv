<?php
echo time()."<br/>";

echo 'result'. var_dump( IsStringCaps( '[b]MEGAKILLER[/b] 4' ) );

	function IsStringCaps( $str ) {
		// обращения вроде [b]MEGAKILLER[/b]
		$tempStr = preg_replace( '/^\[b\][^\]]+\[\/b\]|[\s]+/uis', '',  $str );
		
		if ( $tempStr == '' ) {
			return true;
		}
		echo $tempStr;
		$len = mb_strlen( $tempStr );
		
		preg_match_all( '/[A-ZА-Я]/u', $tempStr, $matches );
		$capsCount = count( $matches[ 0 ] );
		print_r( $matches );
		echo $len . ';' . $capsCount;
		if( $capsCount >= 5 && $capsCount > ( $len / 2 ) ) {
			return true;
		}
		else {
			return false;
		}
	}
echo "<pre>" . print_r( $_COOKIE, 1 ) . "</pre>";
echo "<pre>" . print_r( $_SERVER, 1 ) . "</pre>";

?>