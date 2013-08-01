<?php
    define("DEV",0);
    define("OBFUSCATED",0);
    define("VERSION",1);
    
    // get database ready
    $db = new database();
    
    // check for updates
    update::check();
    update::obfuscate();
    
    
    
    class database extends SQLite3 {
        function __construct() {
            $this->open('index.db');
            
            // check config table
            if ($this->dbq("SELECT name FROM sqlite_master WHERE type='table' AND name='config';")) {
                
                // check database version
                if ($version = $this->dbq("SELECT version FROM config")) {
                    if ($version[0][0]<VERSION) {
                        
                        // require update
                        $this->update();
                    }
                }
                
            } else {
                error::write("no config table - create");
                $this->exec("CREATE TABLE IF NOT EXISTS config (version INT, updateTime INT)");
                $this->exec("INSERT INTO config (version) VALUES ('".VERSION."')");
            }
        }
        
        // update database to current version
        private function update() {
            error::write("updating database");
            
        }
        
        // query the database
        public function dbq($sql) {
            $result = $this->query($sql);
            while ($row = $result->fetchArray()) {
                $return[] = $row;
            }
            return $return;
        }
    }
    
    
    
    class update {
        const pshare = "https://raw.github.com/sn0/pshare/master/index.php";
        const shasum = "https://raw.github.com/sn0/pshare/master/index.php.sha";
        const update_interval = 3600;
        
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
            global $db;
            
            self::$sha_local = @file_get_contents("./index.php.sha");
            self::$sha_remote = @file_get_contents(self::shasum);
    
            // no update while DEV is set
            if (DEV)
                return;
            
            // check update timestamp
            $res = $db->dbq("SELECT updateTime FROM config");
            if ($res[0][0]<time()-self::update_interval) {
                $db->dbq("UPDATE config SET updateTime='".time()."'");
            } else {
                return;
            }
            
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
