<?php

class Mirror {
    private $url;
    private $uptodate;
    
    public function __construct($url, $sourceHash) {
        $this->url = $url;
        $this->uptodate = -1;
        
        if ($sourceHash !== false) {
            $hash = readExternalFile($url . '?hash');
            
            if ($hash === $sourceHash) {
                $this->uptodate = 1;
            } elseif ($hash !== false) {
                $this->uptodate = 0;
            }
        }
    }
    
	public static function getMirrorsBBcode($mirrorList) {
		$bbcode = ''; // Return variable

		$mirrorCount = 0; // Used in output (for [mirror 1][mirror 2], etc.) and to give special output on the first mirror
		foreach ($mirrorList as $mirror) {
			if (DEVMODE) {
				echo '(Mirror ' . $mirror->url . ' ' . ($mirror->uptodate === 1 ? 'uptodate' : 'outdated/down') . ")\n";
			}

			if ($mirror->uptodate === 1) {
				if ($mirrorCount == 0) {
					$bbcode .= '[url=' . $mirror->url . ']Deze statistieken[/url] [sup]';
				}
				else {
					$bbcode .= '[[url=' . $mirror->url . ']mirror ' . $mirrorCount . '[/url]]';
				}
				$mirrorCount += 1;
			}
		}

		if (empty($bbcode)) {
			// Uh-oh. I don't know what is wrong here, but better return all mirrors instead of none.
			if (DEVMODE) {
				echo "[b]WARNING:[/b] Something went wrong checking our mirrors. Slow internet perhaps? (The timeout is 1 second.)\n";
			}

			foreach ($mirrorList as $mirror) {
				$mirror->uptodate = 1; // Let's just pretend they're all available and re-generate.
			}
			$bbcode = Mirror::getMirrorsBBcode($mirrorList);
		}

		return $bbcode;
	}

}

