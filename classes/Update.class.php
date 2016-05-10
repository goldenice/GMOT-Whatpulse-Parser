<?php

/**
 * Update Class
 */

class Update {
	public function __construct() {}
	public static function update() {
		// Check if GitHub is up, if not, don't bother to update
		echo "started\n";
		$updateFiles = readExternalFile('https://raw.githubusercontent.com/goldenice/GMOT-Whatpulse-Parser/master/updatefiles.txt');
		if ($updateFiles == false)
		{
			echo "updatefile does noet exist\n";
			return;
		}
		$updateFiles = str_replace(array("\r", "\r\n"), "\n", $updateFiles);
		$updateFiles = explode("\n", $updateFiles);
		foreach ($updateFiles as $file)
		{
			echo "downloading $file\n";
			$new = readExternalFile('https://raw.githubusercontent.com/goldenice/GMOT-Whatpulse-Parser/master/' . $file);
			if ($new != false)
			{
				if (md5($new) != md5_file('../' . $file));
				{
					file_put_contents("_$file", $new);
					echo "Added $file\n";
				}
			}
		}
	}
}