<?php
    define("DEV",0);
    define("OBFUSCATED",0);
    
    // check for updates
    update::check();
    update::obfuscate();
    
    class update {
	const pshare = "https://raw.github.com/sn0/pshare/master/index.php";
	const shasum = "https://raw.github.com/sn0/pshare/master/index.php.sha";
	
	private static $sha_local;
	private static $sha_remote;
	
	// obfuscate code
	static public function obfuscate() {
	    if (!DEV && !OBFUSCATED) {
		$pshare = file_get_contents("./index.php");
		$pshare = str_replace("<?php","",$pshare);
		$pshare = str_replace("?>","",$pshare);
		$pshare = str_replace("define(\"OBFUSCATED\",0);","define(\"OBFUSCATED\",1);",$pshare);
		$pshare = "/* ".sha1(uniqid("",true))." */\n".$pshare."\n/* ".sha1(uniqid("",true))."\n */";
		$pshare = "<?php eval(base64_decode(\"".base64_encode($pshare)."\")); ?>";
		if (is_writeable("./index.php")) {
		    file_put_contents("./index.php",$pshare);
		}
	    }
	}
	
	static public function check() {
	    self::$sha_local = @file_get_contents("./index.php.sha");
	    self::$sha_remote = @file_get_contents(self::shasum);
	    
	    // no update while DEV is set
	    // if (DEV) return;
	    
	    // no remote sha - something wrong, maybe with internet connection - leave it
	    if (!self::$sha_remote) {
		error::write("couldnt reach git repo (index.php.sha)");
	    }
	    
	    // no local sha - initial update
	    if (!self::$sha_local) {
		error::write("no local hash - update");
		self::download();
	    }
	    
	    // sha hashes differ - update
	    else if (self::$sha_local != self::$sha_remote) {
		error::write("hashes mismatch - update");
		self::download();
	    }
	    
	    // just for information
	    else {
		error::write("we are up-to-date");
	    }
	}
	
	static private function download() {
	    $pshare = @file_get_contents(self::pshare);
	    
	    // try write files to disk
	    if ($pshare && self::$sha_remote) {
		if (is_writeable("./index.php")) {
		    if (is_writeable("./index.php.sha") || !file_exists("./index.php.sha")) {
		    
			// write files
			file_put_contents("./index.php",$pshare);
			file_put_contents("./index.php.sha",self::$sha_remote);
		    } else {
			error::fatal("cannot write ./index.php.sha - please check permissions");
		    }
		} else {
		    error::fatal("cannot write ./index.php - please check permissions");
		}
	    } else {
	        error::write("couldnt reach git repo (index.php)");
	    }
	}
    }
    
    class error {
	static public function write ($msg) {
	    self::really_write($msg,DEV);
	}
	
	static public function fatal ($msg) {
	    self::really_write($msg,1);
	    exit;
	}
	
	static private function really_write($msg,$dev) {
	    if ($dev)
		echo "[".date("d.m.y h:i:s")."] $msg\n";
	}
    }
?>
