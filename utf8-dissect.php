<?php
// PHP port of http://software.hixie.ch/utilities/cgi/unicode-decoder/utf8-decoder
// Unknown license
// with some enhancements by Alex Kirk in 2015 and 2023.

class UTF8_Dissect {
	private static $nameslist;
	public static $names = '', $result = '', $entities = '';
	const DOWNLOAD_TO_TEMP = false;

	private static function loadNames() {
		if ( self::$nameslist ) {
			return;
		}

		$filename = __DIR__ . '/NamesList.txt';
		if ( ! file_exists( $filename ) ) {
			$filename = sys_get_temp_dir() . '/NamesList.txt';
		}

		if ( file_exists( $filename ) ) {
			self::$nameslist = file_get_contents( $filename );
			return;
		}

		$nameslist_txt = 'http://www.unicode.org/Public/UNIDATA/NamesList.txt';

		if ( ! self::DOWNLOAD_TO_TEMP ) {
			echo 'Please download NamesList.txt from ', $nameslist_txt;
			exit;
		}

		echo 'Downloading NamesList.txt...<br/>';
		self::$nameslist = file_get_contents( $nameslist_txt );
		file_put_contents( $filename, self::$nameslist );

		if ( ! file_exists( $filename ) || ! filesize( $filename) ) {
			echo 'Could not write ' . $filename . '!';
			exit;
		}
	}

	public static function getName( $code, $bytes ) {
		self::loadNames();

		$separator = "\n";
		$data = strtok( self::$nameslist, $separator );

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
		self::$result .= 'U+' . $code;
		$charname = trim( $data );
		if ( hexdec($code) < 0x7F ) {
			if (hexdec($code) <= 0x20 || (hexdec($code) >= 0x61 && hexdec($code) <= 0x7A)
				|| (hexdec($code) >= 0x41 && hexdec($code) <= 0x5A)) {
				$charitself = ': ' . chr(hexdec($code));
		} else {
			$charitself = ': ' . chr(hexdec($code));
		}
	} else {
		$charitself = ': ' . $bytes;
	}
	self::$names .= "U+$charname character$charitself\n";

	while ( $data !== false && substr( $data, 0, 1 ) === "\t" ) {
		self::$result .= $data;
		$data = strtok( $separator );
	}

}

public static function dissect( $query ) {

	$bytes = unpack( 'C*', $query );

	if ( ! $bytes ) {
		return false;
	}

	self::$result = '';
	self::$entities = '';
	self::$names = '';
	$remaining = 0;
	$count = 0;
	$scratch = 0;
	$index = 0;
	$byte_sequence = '';

	foreach ( $bytes as $raw ) {
		++$index;
		$byte_sequence .= chr( $raw );

		self::$result .= sprintf("\nByte number $index is decimal %d, hex 0x%02X, octal %04o, binary %08b\n", $raw, $raw, $raw, $raw);
			if ($raw == 0xFE or $raw == 0xFF) { // UTF-8 validity test
				echo "Not a valid UTF-8 byte.\n";
				return false;
			}
			if (($raw & 0b11000000) == 0b10000000) {
				// continuation byte
				// check we're expecting one
				if (! $remaining) {
					echo "Unexpected continuation byte.\n";
					return false;
				}
				$r = $remaining - 1;
				self::$result .= "This is continuation byte $count, expecting $r more.\n";
				// strip off the high bit
				$raw &=~ 0b10000000;
			} else {
				// check we're not expecting more continuation bytes
				if ($remaining) {
					self::$result .= "Previous UTF-8 multibyte sequence incomplete, earlier bytes dropped.\n";
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
					return false;
				}
				if ($remaining > 1) {
					self::$result .= "This is the first byte of a $remaining byte sequence.\n";
				}
			}
			// add the current byte to the pending number
			--$remaining;

			++$count;
			$scratch += $raw << (6 * $remaining);
			if (!$remaining) {
				self::$entities .= '&#x' . sprintf('%04x', $scratch) . ';';
				$scratch = sprintf('%04X', $scratch);
				self::$result .= "\n";
				self::getName( $scratch, $byte_sequence );
				self::$result .= "\n";
				$byte_sequence = '';
			}
		};

		if ($remaining) {
			self::$result .= "End of file during multibyte sequence, some bytes dropped\n";
		}

		return true;
	}
}

$query = empty( $_GET['query'] ) ? false : mb_substr( $_GET['query'], 0, 255 );

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
	<?php if ( UTF8_Dissect::dissect( $query ) ): ?>
		<h2>Dissection result</h2>
		<p>As character names:</p>
		<pre><?php echo htmlentities( UTF8_Dissect::$names ); ?></pre>
		<p>As raw characters:</p>
		<pre><?php echo UTF8_Dissect::$entities; ?></pre>
		<p>As a string of HTML entities:</p>
		<pre><?php echo htmlentities( UTF8_Dissect::$entities ); ?></pre>
		<p>Decoder output:</p>
		<pre><?php echo htmlentities( UTF8_Dissect::$result ); ?></pre>
	<?php endif; ?>
</body>
</html>
