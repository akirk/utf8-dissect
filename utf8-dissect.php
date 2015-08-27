<?php
// PHP port of http://software.hixie.ch/utilities/cgi/unicode-decoder/utf8-decoder
// Unknown license
// with some enhancements by Alex Kirk in 2015

class UTF8 {
	private static $names;

	private static function loadNames() {
		if ( !self::$names ) {
			self::$names = file_get_contents( __DIR__ . '/NamesList.txt' );
		}
	}

	public static function getName( $code ) {
		$result = '';
		$names = '';

		self::loadNames();

		$separator = "\n";
		$data = strtok( self::$names, $separator );

		while ($data !== false) {
			if ( preg_match( "#^$code\t#", $data ) ) {
				$data = substr( $data, strlen( $code ) );
				break;
			}

			$data = strtok( $separator );
		}

		if ( ! $data) {
			return false;
		}
		$result .= 'U+' . $code;
		$charname = trim( $data );
		$charitself = hexdec($code) < 0x7F ?
			(hexdec($code) <= 0x20 || (hexdec($code) >= 0x61 && hexdec($code) <= 0x7A)
			|| (hexdec($code) >= 0x41 && hexdec($code) <= 0x5A)) ? '' : ' (' . chr(hexdec($code)) . ')' : " (&#x$code;)";
		$names .= "U+$charname character$charitself\n";

		while ( $data !== false && substr( $data, 0, 1 ) === "\t" ) {
			$result .= $data;
			$data = strtok( $separator );
		}

		return array( $result, $names );
	}
}

$names = $query = false;
if (!empty( $_GET['query'] ) ) {

	$query = $_GET['query'];
	$bytes = unpack( 'C*', $query );

	$result = '';
	$entities = '';
	$names = '';
	$remaining = 0;
	$count = 0;
	$scratch = 0;
	$index = 0;

	foreach ( $bytes as $raw ) {
		++$index;

		$result .= sprintf("\nByte number $index is decimal %d, hex 0x%02X, octal %04o, binary %08b\n", $raw, $raw, $raw, $raw);
		if ($raw == 0xFE or $raw == 0xFF) { // UTF-8 validity test
			echo "Not a valid UTF-8 byte.\n";
			exit;
		}
		if (($raw & 0b11000000) == 0b10000000) {
			// continuation byte
			// check we're expecting one
			if (! $remaining) {
				echo "Unexpected continuation byte.\n";
				exit;
			}
			$r = $remaining - 1;
			$result .= "This is continuation byte $count, expecting $r more.\n";
			// strip off the high bit
			$raw &=~ 0b10000000;
		} else {
			// check we're not expecting more continuation bytes
			if ($remaining) {
				$result .= "Previous UTF-8 multibyte sequence incomplete, earlier bytes dropped.\n";
			}
			// count how many leading bits are on
			$bit = 7;
			$remaining = 0;
			$count = 0;
			$scratch = 0;
			while (($bit >= 0) and ($raw & (1 << $bit)) > 0) {
				// one more byte expected
				++$remaining;
				// turn off the bit
				$raw &=~ (1 << $bit);
				// ready for next bit
				--$bit;
			}
			// $remaining must be 0, 2, 3, 4, 5, or 6
			if ( $remaining == 0 ) $remaining = 1;
			if ($remaining < 1 or $remaining > 6) {
				echo "Not a valid UTF-8 byte (internal error during decoding: remaining == $remaining).\n";
				exit;
			}
			if ($remaining > 1) {
				$result .= "This is the first byte of a $remaining byte sequence.\n";
			}
		}
		// add the current byte to the pending number
		--$remaining;

		++$count;
		$scratch += $raw << (6 * $remaining);
		if (!$remaining) {
			$entities .= '&#x' . sprintf('%04x', $scratch) . ';';
			$scratch = sprintf('%04X', $scratch);
			$result .= "\n";
			if ( $data = UTF8::getName( $scratch ) ) {
				$result .= $data[0];
				$names .= $data[1];
			}
			$result .= "\n";
		}
	};

	if ($remaining) {
		$result .= "End of file during multibyte sequence, some bytes dropped\n";
	}

}

header( 'Content-Type: text/html;charset=utf-8' );

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0//EN">
<html lang="en">
<head>
	<title>utf8-dissect</title>
	<style type="text/css">
		body { margin: 1em; }
		pre { margin-bottom: 1em; padding-bottom: 1em; border-bottom: solid thin; }
	</style>
</head>
<body>
	<h1>utf8-dissect</h1>
	<form>
		Enter your UTF8 text: <input type="text" name="query" value="<?php echo htmlentities( $query ); ?>" />
		<button>Submit</button>
	</form>
	<?php if ($names): ?>
		<p>As character names:</p>
		<pre><?php echo htmlentities( $names ); ?></pre>
		<p>As raw characters:</p>
		<pre><?php echo $entities; ?></pre>
		<p>As a string of HTML entities:</p>
		<pre><?php echo htmlentities( $entities ); ?></pre>
		<p>Decoder output:</p>
		<pre><?php echo htmlentities( $result ); ?></pre>
	<?php endif; ?>
</body>
</html>
