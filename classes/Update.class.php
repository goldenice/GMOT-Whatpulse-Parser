<?php

/**
 * Update Class
 */

class Update {
	public function __construct() {}
	public static function update() {
		// Check if update has already been done last 24 hours
		if (file_get_contents('lastupdate.txt') > time() - 86400 && isset($_GET['update']) == false)
		{
			return;
		}
		// Check if GitHub is up, if not, don't bother to update
		echo "Started auto update\n";
		$updateFiles = readExternalFile('https://raw.githubusercontent.com/goldenice/GMOT-Whatpulse-Parser/master/updatefiles.txt');
		if ($updateFiles == false)
		{
			echo "GitHub is down\nStopping auto update";
			return;
		}
		file_put_contents('lastupdate.txt', time());
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
					echo "Changed/added $file\n";
				}
				else
				{
					echo "$file has not been modified\n";
				}
			}
		}
	}
}