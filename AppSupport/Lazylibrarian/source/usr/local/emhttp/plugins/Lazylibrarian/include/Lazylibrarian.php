<?PHP
# Setup page variables
$authorname="ziggyke";
$appname="Lazylibrarian";
$displayname="LazyLibrarian";
$appexecutable="LazyLibrarian.py";
$appconfigfile="config.ini";
$plglogfile="/var/log/{$authorname}-Logs/{$appname}.log";
$arrayState=trim(shell_exec( "grep fsState /var/local/emhttp/var.ini | sed -n 's!fsState=\"\(.*\)\"!\\1!p'" ));
$ver60check=trim(shell_exec( "grep version /etc/unraid-version | sed -n 's!version=\"\(.*\)\"!\\1!p'" ));
$appRepoURL="https://github.com/DobyTang/LazyLibrarian";
$authorURL="https://github.com/{$authorname}";
$versionsURL="{$authorURL}/unRAID/raw/master/Versions";

# Sets the loader background to match the theme based on unRAID version since 6 has a dark theme
$machine_type = trim(shell_exec( "uname -m" ));
if ($machine_type == "x86_64") {
	$loaderbgcolor = "html";
	$prefix = "";
	if (substr($ver60check, 0, 3) === "6.0") {
		$prefix = "/usr/local/emhttp";
	}
} else {
	$loaderbgcolor = ".Container";
	$prefix = "/usr/local/emhttp";
}
?>

<!-- ========================================================================= -->
<!-- Page load animation and text -->
<!-- ========================================================================= -->

<!-- # Add jquery library, show loader, fade out when loaded -->
<script type="text/javascript">
	if(typeof jQuery == 'undefined'){
		var oScriptElem = document.createElement("script");
		oScriptElem.type = "text/javascript";
		oScriptElem.src = "/plugins/<?=$appname;?>/scripts/jquery.min.js";
		document.head.insertBefore(oScriptElem, document.getElementsByTagName("script")[0])
    }
</script>
<div id="loading">
	<img id="loading-image" src='/plugins/<?=$appname;?>/icons/loader.gif' alt="Loading..." />
	<p id="loading-text">LOADING PLUGIN</p>
</div>
<script language="javascript" type="text/javascript">
	$("#loading").css("background-color",$("<?=$loaderbgcolor;?>").css("background-color"));
	$("#loading").show();
</script>
<script language="javascript" type="text/javascript">
	$(window).load(function() {$("#loading").fadeOut("slow");});
</script>

<!-- ========================================================================= -->
<!-- Load current config file and check if program is installed already -->
<!-- ========================================================================= -->

<?PHP
# Check for cache drive and make sure its an actual disk drive
$cacheCheck = trim(shell_exec( "/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname} checkcache" ));
if ($cacheCheck == "true")
	$commonDIR = "/mnt/cache/.{$authorname}-Common";
else
	$commonDIR = "/usr/local/{$authorname}-Common";

# This will clean any ^M characters caused by windows from the config file before use
if (file_exists("/boot/config/plugins/{$appname}/{$appname}.cfg"))
	shell_exec("sed -i 's!\r!!g' \"/boot/config/plugins/{$appname}/{$appname}.cfg\"");
if (file_exists("/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname}"))
	shell_exec("sed -i 's!\r!!g' \"/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname}\"");

# Check existence of files and make startfile if missing
$app_cfg = parse_ini_file( "/boot/config/plugins/{$appname}/{$appname}.cfg" );
$app_installed = file_exists( $app_cfg["INSTALLDIR"] . "/{$appexecutable}" ) ? "yes" : "no";
$app_rollback = file_exists( "boot/config/plugins/{$appname}.plg.old" ) ? "yes" : "no";
$app_startfile = file_exists( "{$commonDIR}/{$appname}/startcfg.sh" ) ? "yes" : "no";
if ($arrayState == "Started" && $app_installed == "yes" && $app_startfile == "no") {
	echo "<script>document.getElementById('loading-text').innerHTML = \"CHECKING DEPENDENCIES\";</script>";
	trim(shell_exec( "/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname} writeexport" ));
}
$python_installed = file_exists( "{$commonDIR}/usr/bin/python2.7" ) ? "yes" : "no";
$sqlite3_installed = file_exists( "{$commonDIR}/usr/bin/sqlite3" ) ? "yes" : "no";
$git_installed = file_exists( "{$commonDIR}/usr/bin/git" ) ? "yes" : "no";
$curl_installed = file_exists( "{$commonDIR}/usr/bin/curl" ) ? "yes" : "no";

# =========================================================================
## Collect local variables from config files and verify data as best as possible
# =========================================================================

# Check for forcecheck, this overrides the checkonline option
if ($machine_type == "x86_64") {
	if (isset($_POST["forcecheck"])) {
		$forcecheck = $_POST["forcecheck"];
	} else {
		$forcecheck = "no";
	}
} else {
	if (isset($GLOBALS["forcecheck"])) {
		$forcecheck = $GLOBALS["forcecheck"];
	} else {
		$forcecheck = "no";
	}
}

# See if plugin is allowed to check online for latest updates on GUI load
if (isset($app_cfg['CHECKONLINE']) && ($app_cfg['CHECKONLINE'] == "yes" || $app_cfg['CHECKONLINE'] == "no")) {
	$app_checkonline=$app_cfg["CHECKONLINE"];
} else {
	$app_checkonline="yes";
}

# Check if there is internet conneciton so page doesn't stall out, only check if CHECKONLINE is allowed
if ($app_checkonline == "yes" || $forcecheck == "yes") {
	$connected = @fsockopen("www.google.com", 80, $errno, $errstr, 5);
	if ($connected){
		$is_conn = "true";
		fclose($connected);
	} else {
		$is_conn = "false";
	}
} else {
	$is_conn = "false";
}

# Set readonly status if array is offline
if ($arrayState == "Started") {
	$app_readonly = "";
	$app_disabled = "";
} else {
	$app_readonly = 'readonly="readonly"';
	$app_disabled = 'disabled="disabled"';
}

# Plugin Current Version Variable
if (file_exists("/boot/config/plugins/{$appname}.plg")) {
	$app_plgver = trim(shell_exec ( "grep 'ENTITY plgVersion' /boot/config/plugins/{$appname}.plg | sed -n 's!.*\t\"\(.*\)\".*!\\1!p'" ));
	if ($app_plgver == "") {
		$app_plgver = "Unknown plugin version";
	}
} else {
	$app_plgver = "(Plugin File Missing)";
}

# Get latest release of the plugin
if ($is_conn == "true") {
	echo "<script>document.getElementById('loading-text').innerHTML = \"CHECKING LATEST PLUGIN VERSION\";</script>";
	$REPO2="{$versionsURL}";
	$app_newversionPLG = trim(shell_exec ( "wget -qO- --no-check-certificate $REPO2 | sed -n 's/{$appname}: \(.*\)/\\1/p'" ));
} else {
	$app_newversionPLG = "";
}

# Service Status Variable
if (isset($app_cfg['SERVICE']) && ($app_cfg['SERVICE'] == "enable" || $app_cfg['SERVICE'] == "disable"))
	$app_service = $app_cfg['SERVICE'];
else
	$app_service = "disable";

# Install Directory Variable
if (isset($app_cfg['INSTALLDIR']))
	$app_installdir = $app_cfg['INSTALLDIR'];
else
	$app_installdir = "/usr/local/{$appname}";

# Config Directory Variable
if (isset($app_cfg['CONFIGDIR'])) {
	$app_configdir = $app_cfg['CONFIGDIR'];
} else {
	$app_configdir = "{$app_installdir}/config";
}

# Custom Logs Directory
if ($arrayState=="Started" && file_exists("{$app_configdir}/{$appconfigfile}") && shell_exec("grep -m 1 '^log_dir =' {$app_configdir}/{$appconfigfile}")) {
	$app_logsdir = trim(shell_exec( "sed -n 's!log_dir = \(.*\)!\\1!p' {$app_configdir}/{$appconfigfile}" ));
	if ($app_logsdir == "logs")
		$app_logsdir = "";
} else if (isset($app_cfg['LOGDIR'])) {
	$app_logsdir = $app_cfg['LOGDIR'];
} else {
	$app_logsdir = "";
}

# Custom Cache Directory
if ($arrayState=="Started" && file_exists("{$app_configdir}/{$appconfigfile}") && shell_exec("grep -m 1 '^cache_dir =' {$app_configdir}/{$appconfigfile}")) {
	$app_cachedir = trim(shell_exec( "sed -n 's!cache_dir = \(.*\)!\\1!p' {$app_configdir}/{$appconfigfile}" ));
	if ($app_cachedir == "cache")
		$app_cachedir = "";
} else if (isset($app_cfg['CACHEDIR'])) {
	$app_cachedir = $app_cfg['CACHEDIR'];
} else {
	$app_cachedir = "";
}

# Use SSL Variable
if ($arrayState=="Started" && file_exists("{$app_configdir}/{$appconfigfile}") && shell_exec("grep -m 1 '^enable_https =' {$app_configdir}/{$appconfigfile}")) {
	$app_usessl = trim(shell_exec( "sed -n 's!enable_https = \([0-9]\)!\\1!p' {$app_configdir}/{$appconfigfile}" ));
	if ($app_usessl == "1")
		$app_usessl = "yes";
	else
		$app_usessl = "no";
} else if (isset($app_cfg['USESSL']) && ($app_cfg['USESSL'] == "yes" || $app_cfg['USESSL'] == "no")) {
	$app_usessl = $app_cfg['USESSL'];
} else {
	$app_usessl = "no";
}

# Port Number Variable
if ($arrayState=="Started" && file_exists("{$app_configdir}/{$appconfigfile}") && shell_exec("grep -m 1 '^http_port =' {$app_configdir}/{$appconfigfile}")) {
	$app_port = trim(shell_exec( "grep -m 1 '^http_port =' {$app_configdir}/{$appconfigfile} | sed -n 's!http_port = \([0-9][0-9]*\)!\\1!p'" ));
	if (is_numeric($app_port)) {
		if ($app_port < 0 || $app_port > 65535)
			$app_port = "5299";
	} else {
		$app_port = "5299";
	}
} else if (isset($app_cfg['PORT']) && is_numeric($app_cfg['PORT'])) {
	$app_port = $app_cfg['PORT'];
	if ($app_port < 0 || $app_port > 65535)
		$app_port = "5299";
} else {
	$app_port = "5299";
}

# URL Base Variable
if ($arrayState=="Started" && file_exists("{$app_configdir}/{$appconfigfile}") && shell_exec("grep -m 1 '^http_root =' {$app_configdir}/{$appconfigfile}")) {
	$app_urlbase = trim(shell_exec( "sed -n 's!http_root = /\(.*\)!\\1!p' {$app_configdir}/{$appconfigfile}" ));
} else if (isset($app_cfg['URLBASE'])) {
	$app_urlbase = $app_cfg['URLBASE'];
} else {
	$app_urlbase = "";
}

# Run As User Variable
if (isset($app_cfg['RUNAS']))
	$app_runas = $app_cfg['RUNAS'];
else
	$app_runas = "nobody";

# Repo Variable
if (isset($app_cfg['REPO']))
	$app_repo = $app_cfg['REPO'];
else
	$app_repo = $appRepoURL;

# Branch Variable - Get stored value from config file
if (isset($app_cfg['BRANCH']))
	$app_branch = $app_cfg['BRANCH'];
else
	$app_branch = "master";

# Get a list of branches for this repo
if ($is_conn == "true") {
	$get_branches = trim(shell_exec("wget --no-check-certificate -qO- {$app_repo} | grep -A1 'js-select-menu-filter-text' | grep -v 'js-select-menu-filter-text' | grep -v '\-\-' | sed -n 's![ \t]*\(.*\)!\\1!p'"));
} else {
	$get_branches = $app_branch;
}
$app_brancharray = explode ("\n", $get_branches);

# Check if branch from config exists in this repo (not case sensitive)
$checkbranchmatch = "0";
foreach ($app_brancharray as $thevalue) {
	if (strtolower($thevalue) == strtolower($app_branch)) {
		$checkbranchmatch = "1";
		$app_branch = $thevalue;
	}
}

# See if branch match existed, otherwise set to master (if it exists) or just first one found if no master exists)
if ($checkbranchmatch == "0") {
	$checkmasterbranch = "0";
	foreach ($app_brancharray as $thevalue2) {
		if (strtolower($thevalue2) == "master") {
			$checkmasterbranch = "1";
			$app_branch = $thevalue2;
		}
	}
	if ($checkmasterbranch == "0")
		$app_branch = $app_brancharray[0];
}

# Storage Check Status Variable
if (isset($app_cfg['PLG_STORAGESIZE']) && ($app_cfg['PLG_STORAGESIZE'] == "yes" || $app_cfg['PLG_STORAGESIZE'] == "no"))
	$app_storagesizestat = $app_cfg['PLG_STORAGESIZE'];
else
	$app_storagesizestat = "yes";

# Data Check Status Variable
if (isset($app_cfg['PLG_DATACHECK']) && ($app_cfg['PLG_DATACHECK'] == "yes" || $app_cfg['PLG_DATACHECK'] == "no"))
	$app_datacheckstat = $app_cfg['PLG_DATACHECK'];
else
	$app_datacheckstat = "yes";

# =========================================================================
## Check is program is installed and running to get extra information
# =========================================================================

# Get current installed version of the program
if ($arrayState=="Started") {
	$app_curversion = trim(shell_exec ( "/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname} currentversion" ));
	if ($app_curversion == "")
		$app_curversion = "UNKNOWN";
}

if ($app_installed=="yes" && $arrayState=="Started") {
	$app_running = trim(shell_exec( "[ -f /proc/`cat /var/run/{$appname}/{$appname}.pid 2> /dev/null`/exe ] && echo 'yes' || echo 'no' 2> /dev/null" ));
	if ($app_running == "yes")
		$app_updatestatus = "Running";
	else
		$app_updatestatus = "Stopped";

	# Get latest release version of the program
	if ($is_conn == "true") {
		echo "<script>document.getElementById('loading-text').innerHTML = \"CHECKING LATEST APP VERSION\";</script>";
		$app_newversion = trim(shell_exec ( "/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname} latestversion" ));
	} else {
		$app_newversion = "";
	}

	# Add storage size and data check if settings are set to yes
	if ($app_storagesizestat == "yes")
		$app_storagesize = shell_exec ( "/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname} storagesize" );
	if ($app_datacheckstat == "yes")
		$app_datacheck = shell_exec ( "/usr/local/emhttp/plugins/{$appname}/scripts/rc.{$appname} datacheck" );

	# Check if the app can be updated
	if ($app_newversion != "" && $app_newversion != $app_curversion)
		$app_canupdate = "yes";
	else
		$app_canupdate = "no";

	# Get dependency version numbers
	$python_ver = trim(shell_exec( "sudo -H -u nobody /bin/bash -c \". {$commonDIR}/{$appname}/startcfg.sh; python --version 2>&1\" | sed -n 's/Python \(.*\)/\\1/p'" ));
	if ($python_ver == "")
		$python_ver = "NOT WORKING";
	$sqlite3_ver = trim(shell_exec( "sudo -H -u nobody /bin/bash -c \". {$commonDIR}/{$appname}/startcfg.sh; sqlite3 --version 2>&1\" | sed -n 's/\([0-9.]*\).*/\\1/p'" ));
	if ($sqlite3_ver == "")
		$sqlite3_ver = "NOT WORKING";
	$git_ver = trim(shell_exec( "sudo -H -u nobody /bin/bash -c \". {$commonDIR}/{$appname}/startcfg.sh; git --version 2>&1\" | sed -n 's/git version \([0-9.]*\).*/\\1/p'" ));
	if ($git_ver == "")
		$git_ver = "NOT WORKING";
	$curl_ver = trim(shell_exec( "sudo -H -u nobody /bin/bash -c \". {$commonDIR}/{$appname}/startcfg.sh; curl --version 2>&1\" | grep curl | sed -n 's/curl \([0-9.]*\) .*/\\1/p'" ));
	if ($curl_ver == "")
		$curl_ver = "NOT WORKING";
}

# Get plugin version current and new
if ($app_newversionPLG != "" && $app_newversionPLG != $app_plgver )
	$app_canupdatePLG = "yes";
else
	$app_canupdatePLG = "no";

echo "<script>document.getElementById('loading-text').innerHTML = \"DONE\";</script>";
?>

<!-- ========================================================================= -->
<!-- Create the HTML code used to display the settings GUI -->
<!-- ========================================================================= -->

<div id="PANELALL"><div id="PANELLEFT">
	<div class="TITLEBARLEFT title" id="title">
		<span class="left"><img id="IMGICON" src="/plugins/<?=$appname;?>/icons/device_status.png">&#32;Status:
			<?if ($app_installed=="yes" && $arrayState=="Started"):?>
				<?if ($app_running=="yes"):?>
					<span class="green"><b>RUNNING</b></span>
					<span class="right">
						<?if ($app_usessl=="yes"):?>
							<a id="UPDATEBUTTON" class="UIBUTTON" href="https://<?=$var['NAME'];?>:<?=$app_port;?>/<?=$app_urlbase;?>" target="_blank">Web UI</a>
						<?else:?>
							<a id="UPDATEBUTTON" class="UIBUTTON" href="http://<?=$var['NAME'];?>:<?=$app_port;?>/<?=$app_urlbase;?>" target="_blank">Web UI</a>
						<?endif;?>
					</span>
				<?else:?>
					<span class="red"><b>STOPPED</b></span>
				<?endif;?>
			<?else:?>
				<span class="red"><b>NOT READY</b></span>
			<?endif;?>  
		</span>
	</div>
	<div id="DIVLEFT">
		<div id="T50LM">
			<?if ($arrayState == "Started"):?>
				<p id="PLINE">Array State:<b><span class="green">ONLINE</span></b></p>
				<?if ($app_curversion == "NOT INSTALLED" || $app_curversion == "UNKNOWN"):?>
					<p id="PLINE">Installed Version:<b><span class="red"><?=$app_curversion;?></span></b></p>
				<?else:?>
					<p id="PLINE" class="longver">Installed Version:<b><span class="green" title="<?=$app_curversion;?>"><?=$app_curversion;?></span></b></p>
				<?endif;?>
			<?else:?>
				<p id="PLINE">Array State:<b><span class="red">OFFLINE</span></b></p>
			<?endif;?>
		</div>
		<?if ($app_installed=="yes"):?>
			<?if ($app_running=="yes"):?>
				<div id="T25RM">
					<form name="app_stop" method="POST" action="/update.htm" target="progressFrame">
						<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
						<input type="hidden" name="arg1" value="stop"/>
						<input <?=$app_disabled;?> type="submit" name="runCmd" id="STDSMBUTTONR" value="Stop"/>
					</form>
				</div>
				<div id="T25LM">
					<form name="app_restart" method="POST" action="/update.htm" target="progressFrame">
						<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
						<input type="hidden" name="arg1" value="restart"/>
						<input <?=$app_disabled;?> type="submit" name="runCmd" id="STDSMBUTTONL" value="Restart"/>
					</form>
				</div>
			<?else:?>
				<div id="T50CM">
					<form name="app_start" method="POST" action="/update.htm" target="progressFrame">
						<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
						<input type="hidden" name="arg1" value="buttonstart"/>
						<input <?=$app_disabled;?> type="submit" name="runCmd" id="STDSMBUTTON" value="Start"/>
					</form>
				</div>
			<?endif;?>
		<?else:?>
			<div id="T50CM">
				<form name="app_install" method="POST" action="/update.htm" target="progressFrame">
					<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
					<input type="hidden" name="arg1" value="install"/>
					<input <?=$app_disabled;?> type="submit" name="runCmd" id="STDSMBUTTON" value="Install"/>
				</form>
			</div>
		<?endif;?>
	</div>
	<? if ($app_installed=="yes" && $arrayState=="Started"): ?>
		<?if ($app_canupdate=="yes"):?>
			<div id="DIVLEFT">
				<div id="T50LM">
					<p id="PLINE"></p>
				</div>
				<div id="T50CM">
					<form name="app_updateapp" method="POST" action="/update.htm" target="progressFrame">
						<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
						<input type="hidden" name="arg1" value="update"/>
						<input type="hidden" name="arg3" value="<?=$app_updatestatus;?>"/>
						<input id="UPDATEBUTTON" type="submit" name="runCmd" value="Update <?=$displayname;?>"/>
					</form>
					<p id="VERSION" class="longver" title="<?=$app_newversion;?>"><b>New Version: <?=$app_newversion;?></b></p>
				</div>
			</div>
		<?endif;?>
		<div class="TITLEBARLEFT title" id="title">
			<span class="left"><img id="IMGICON" src="/plugins/<?=$appname;?>/icons/depends.png">&#32;Dependencies:</span>
		</div>
		<div id="DIVLEFT">
			<div id="T50LT">
				<? if ($python_installed=="yes"): ?>
					<? if ($python_ver == "NOT WORKING"): ?>
						<p id="PLINE">Python Version:<b><span class="red"><?=$python_ver;?></span></b></p>
					<?else:?>
						<p id="PLINE">Python Version:<b><span class="green"><?=$python_ver;?></span></b></p>
					<? endif; ?>
				<?else:?>
					<p id="PLINE">Python Version:<b><span class="red">NOT INSTALLED</span></b></p>
				<? endif; ?>
				<? if ($sqlite3_installed=="yes"): ?>
					<? if ($sqlite3_ver == "NOT WORKING"): ?>
						<p id="PLINE">SQLite3 Version:<b><span class="red"><?=$sqlite3_ver;?></span></b></p>
					<?else:?>
						<p id="PLINE">SQLite3 Version:<b><span class="green"><?=$sqlite3_ver;?></span></b></p>
					<? endif; ?>
				<?else:?>
					<p id="PLINE">SQLite3 Version:<b><span class="red">NOT INSTALLED</span></b></p>
				<? endif; ?>
			</div>
			<div id="T50LT">
				<? if ($git_installed=="yes"): ?>
					<? if ($git_ver == "NOT WORKING"): ?>
						<p id="PLINE">GIT Version:<b><span class="red"><?=$git_ver;?></span></b></p>
					<?else:?>
						<p id="PLINE">GIT Version:<b><span class="green"><?=$git_ver;?></span></b></p>
					<? endif; ?>
				<?else:?>
					<p id="PLINE">GIT Version:<b><span class="red">NOT INSTALLED</span></b></p>
				<? endif; ?>
				<? if ($curl_installed=="yes"): ?>
					<? if ($curl_ver == "NOT WORKING"): ?>
						<p id="PLINE">cURL Version:<b><span class="red"><?=$curl_ver;?></span></b></p>
					<?else:?>
						<p id="PLINE">cURL Version:<b><span class="green"><?=$curl_ver;?></span></b></p>
					<? endif; ?>
				<?else:?>
					<p id="PLINE">cURL Version:<b><span class="red">NOT INSTALLED</span></b></p>
				<? endif; ?>
			</div>
		</div>
	<? endif; ?>
	<div class="TITLEBARLEFT title" id="title">
		<span class="left"><img id="IMGICON" src="/plugins/<?=$appname;?>/icons/information.png">&#32;Information:</span>
	</div>
	<? if ($is_conn == "false" && ($app_checkonline == "yes" || $forcecheck == "yes")):?>
		<div id="DIVLEFT">
			<div id="T100LT">
				<p id="PLINE"><img id="IMGICON" src="/plugins/<?=$appname;?>/icons/warning.png"/><span class="red" id="ERRORPAD"> <b>No Internet Connection Detected</b></span></p>
			</div>
		</div>
	<? endif;?>
	<? if ($arrayState != "Started"):?>
		<div id="DIVLEFT">
			<div id="T100LT">
				<p id="PLINE"><img id="IMGICON" src="/plugins/<?=$appname;?>/icons/warning.png"/><span class="red" id="ERRORPAD"> <b>Some settings cannot be changed while the array is stopped</b></span></p>
			</div>
		</div>
	<? endif;?>
	<? if ($app_installed=="yes" && $arrayState=="Started"): ?>
		<? if ($app_storagesizestat=="yes" || $app_datacheckstat=="yes"): ?>
			<div id="DIVLEFT">
				<? if ($app_storagesizestat == "yes"): ?>
					<div id="T50LT">
						<?=$app_storagesize;?>
					</div>
				<? endif; ?>
				<? if ($app_datacheckstat == "yes"): ?>
					<div id="T50LT">
						<?=$app_datacheck;?>
					</div>
				<? endif; ?>
			</div>
		<? endif; ?>
	<? endif; ?>
	<div id="DIVLEFT">
		<div id="T50LM">
			<p id="PLINE"><b>Plugin Version: <?=$app_plgver;?></b></p>
		</div>
		<? if ($app_rollback=="yes"):?>
			<div id="T25RM">
				<form name="app_downgrade" method="POST" action="/update.htm" target="progressFrame">
					<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
					<input type="hidden" name="arg1" value="downgradeplg"/>
					<input type="submit" name="runCmd" id="STDLGBUTTONR" value="Downgrade Plugin"
						onclick="return confirm('Pressing OK will install the previous backup of this plugin.')"/>
				</form>
			</div>
			<div id="T25LM">
				<form name="app_uninstall" method="POST" action="/update.htm" target="progressFrame">
					<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
					<input type="hidden" name="arg1" value="removeplg"/>
					<input type="submit" name="runCmd" id="STDLGBUTTONL" value="Uninstall Plugin" 
						onclick="return confirm('Pressing OK will remove this plugin, including all support and install files. Application directories will not be removed. \n\nIf the array is offline during uninstall, some dependencies may remain on the cache drive.')"/>
				</form>
			</div>
		<?else:?>
			<div id="T50CM">
				<form name="app_uninstall" method="POST" action="/update.htm" target="progressFrame">
					<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
					<input type="hidden" name="arg1" value="removeplg"/>
					<input type="submit" name="runCmd" id="STDLGBUTTON" value="Uninstall Plugin" 
						onclick="return confirm('Pressing OK will remove this plugin, including all support and install files. Application directories will not be removed. \n\nIf the array is offline during uninstall, some dependencies may remain on the cache drive.')"/>
				</form>
			</div>
		<?endif;?>
	</div>
	<div id="DIVLEFT">
		<div id="T50LT">
			<p id="PLINE"><a title="<?=$appname;?> Activity Log - <?=$plglogfile;?>" href="#" target="_blank" id="ACTIVITYLINK" onclick="openLog();return false;">View Activity Log</a></p>
		</div>
		<?if ($app_canupdatePLG=="yes"):?>
			<div id="T50CM">
				<form name="app_update1" method="POST" action="/update.htm" target="progressFrame">
					<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
					<input type="hidden" name="arg1" value="updateplg"/>
					<input id="UPDATEBUTTON" type="submit" name="runCmd" class="UPDATEBTN" value="Update Plugin"/>
				</form>
				<p id="VERSION"><b>New Version: <?=$app_newversionPLG;?></b></p>
			</div>
		<?endif;?>
		<? if ($app_checkonline=="no" && $forcecheck != "yes"):?>
			<div id="T50CM">
				<form method="POST" action="">
					<input type="hidden" id="forcecheck" name="forcecheck" value="yes"/>
					<input type="submit" id="UPDATEBUTTON" class="UPDATEBTN" value="Manual Update Check"/>
				</form>
			</div>
		<?endif;?>
	</div>
</div>
<div id="PANELRIGHT">
	<div class="TITLEBARRIGHT title" id="title">
		<span class="left"><img id="IMGICON" src="/plugins/<?=$appname;?>/icons/new_config.png">&#32;Configuration:</span>
	</div>
	<div id="DIVRIGHT">
		<form name="app_settings" method="POST" action="/update.htm" target="progressFrame">
			<input type="hidden" name="cmd" value="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/rc.<?=$appname;?>"/>
			<table class="settings" id="TABLESETTINGS">
				<tr>
					<td id="TDSETTINGS">Enable <?=$displayname;?>:</td>
					<td id="TDSETTINGS">
						<select name="arg1" size="1">
							<?=mk_option($app_service, "disable", "No");?>
							<?=mk_option($app_service, "enable", "Yes");?>
						</select>
					</td>
				</tr>
				<input type="hidden" name="arg2" value=""/>
				<tr>
					<td id="TDSETTINGS">Install directory:</td>
					<td id="TDSETTINGS"><input <?=$app_readonly;?> type="text" name="arg3" id="TEXTLARGE" maxlength="60" value="<?=$app_installdir;?>"/></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Config directory:</td>
					<td id="TDSETTINGS"><input <?=$app_disabled;?> type="checkbox" name="use_data" <?=($app_configdir != "{$app_installdir}/config")?"checked=\"checked\"":"";?> onChange="checkDATADIR(this.form);"/> 
					<input <?=$app_readonly;?> type="text" name="arg4" id="TEXTSMALL" maxlength="60" value="<?=$app_configdir;?>"/></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Custom logs directory:</td>
					<td id="TDSETTINGS"><input <?=$app_disabled;?> type="checkbox" name="use_logs" <?=(($app_logsdir != "{$app_configdir}/logs") && ($app_logsdir != "") && ($app_logsdir != "logs"))?"checked=\"checked\"":"";?> onChange="checkLOGDIR(this.form);"> 
					<input <?=$app_readonly;?> type="text" name="arg5" id="TEXTSMALL" maxlength="60" value="<?=$app_logsdir;?>"></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Custom cache directory:</td>
					<td id="TDSETTINGS"><input <?=$app_disabled;?> type="checkbox" name="use_cache" <?=(($app_cachedir != "{$app_configdir}/cache") && ($app_cachedir != "") && ($app_cachedir != "cache"))?"checked=\"checked\"":"";?> onChange="checkCACHEDIR(this.form);"> 
					<input <?=$app_readonly;?> type="text" name="arg6" id="TEXTSMALL" maxlength="60" value="<?=$app_cachedir;?>"></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Use SSL:</td>
					<td id="TDSETTINGS"><input <?=$app_disabled;?> type="checkbox" name="use_ssl" <?=($app_usessl=="yes")?"checked=\"checked\"":"";?> onChange="checkSSLPORT(this.form);">
					<input type="hidden" name="arg7" value="<?=$app_usessl;?>"></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Port:</td>
					<td id="TDSETTINGS"><input <?=$app_readonly;?> type="text" name="arg8" id="TEXTLARGE" maxlength="5" value="<?=$app_port;?>"/></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">URL base:</td>
					<td id="TDSETTINGS"><input <?=$app_readonly;?> type="text" name="arg9" id="TEXTLARGE" maxlength="60" value="<?=$app_urlbase;?>"></td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Repo:</td>
					<td id="TDSETTINGS">
						<select <?=$app_disabled;?> name="repo" size="1" onChange="checkREPO(this.form);">
							<?=mk_option($app_repo, "$appRepoURL", "Default");?>
							<option value='other'<?=($app_repo != "$appRepoURL")?" selected=yes":"" ;?>>Custom</option>
						</select>
						<img id="IMGICON" src="/plugins/<?=$appname?>/icons/warning.png" title="Custom repos may not be compatible with this plugin. Use this option at your own risk!"/>
					</td>
				</tr>
				<tr id="custom_repo">
					<td id="TDSETTINGS">Custom repo URL:</td>
					<td id="TDSETTINGS"><input <?=$app_readonly;?> type="hidden" name="arg10" id="TEXTLARGE" maxlength="60" value="<?=$app_repo;?>"></td>
				</tr>
				<tr id="repo_warning" style="display:none;">
					<td id="TDSETTINGS" colspan="2"><center><p class="red">WARNING: Changing the repo will delete your current install files and config/database files due to possible incompatibilities between the applications.
						<br><br><i>Backup your current install and config folders if you plan on reverting back to them in the future.</i></p></center>
					</td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Branch:</td>
					<td id="TDSETTINGS">
						<select <?=$app_disabled;?> name="branch" size="1" onChange="checkBRANCH(this.form);">
							<?foreach ($app_brancharray as $value):?>
								<?=mk_option($app_branch, $value, ucwords($value));?>
							<?endforeach;?>
						</select>
						<input <?=$app_readonly;?> type="hidden" name="arg11" value="<?=$app_branch;?>">
					</td>
				</tr>
				<tr><td id="TDSETTINGS" colspan="2"><div id="DIVIDER"> </div></td></tr>
				<tr>
					<td id="TDSETTINGS">Run as user:</td>
					<td id="TDSETTINGS">
						<select name="runas" size="1" onChange="checkUSER(this.form);">
							<?=mk_option($app_runas, "nobody", "nobody");?>
							<?=mk_option($app_runas, "root", "root");?>
							<option value="other" <?=($app_runas != "root" && $app_runas != "nobody") ? "selected=yes":"";?>>other</option>
						</select>
						<input type="hidden" name="arg12" id="TEXTUSER" maxlength="40" value="<?=$app_runas;?>"/>
					</td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Show storage memory usage:</td>
					<td id="TDSETTINGS">
						<select name="storagesize" size="1" onChange="checkSTORAGE(this.form);">
							<?=mk_option($app_storagesizestat, "yes", "Yes");?>
							<?=mk_option($app_storagesizestat, "no", "No");?>
						</select>
						<input type="hidden" name="arg13" value="<?=$app_storagesizestat;?>"/>
					</td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Show data persistency information:</td>
					<td id="TDSETTINGS">
						<select name="datacheck" size="1" onChange="checkDATAPERSIST(this.form);">
							<?=mk_option($app_datacheckstat, "yes", "Yes");?>
							<?=mk_option($app_datacheckstat, "no", "No");?>
						</select>
						<input type="hidden" name="arg14" value="<?=$app_datacheckstat;?>"/>
					</td>
				</tr>
				<tr>
					<td id="TDSETTINGS">Check for updates on page load:</td>
					<td id="TDSETTINGS">
						<select name="checkonline" size="1" onChange="checkONLINESTAT(this.form);">
							<?=mk_option($app_checkonline, "yes", "Yes");?>
							<?=mk_option($app_checkonline, "no", "No");?>
						</select>
						<input type="hidden" name="arg15" value="<?=$app_checkonline;?>"/>
					</td>
				</tr>
			</table><br>
			<div align="center">
				<input type="submit" name="runCmd" value="Apply" id="BOTTOMPAD" onClick="verifyDATA(this.form);"/>
				<input type="button" id="BOTTOMPAD" value="Done" onClick="done();">
			</div>
		</form>
	</div>
</div></div>
<div id="FOOTER2">
	Buy PhAzE a beer? <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=griptionize%40hotmail%2ecom&lc=CA&item_name=PhAzE%20Unraid%20Plugins%20%2d%20Donations&currency_code=CAD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted]PayPal" target="_blank"><img src="/plugins/<?=$appname;?>/icons/paypal.png"></a>
</div>

<!-- ========================================================================= -->
<!-- Javascript functions to verify data, and perform other GUI related tasks -->
<!-- ========================================================================= -->

<script type="text/javascript">

function openLog() {
	var machine_type = "<?=$machine_type;?>";
	var title="<?=$appname;?> Activity Log";
	if (machine_type == "x86_64") {
		<?if (substr($ver60check, 0, 3) === "6.0"):?>
			var url="/usr/bin/tail -n 42 -f <?=$plglogfile;?>";
			openWindow( url, title,600,900);
		<?else:?>
			var url="<?=$prefix;?>/plugins/<?=$appname;?>/scripts/tail_log&arg1=<?=$appname;?>.log";
			openWindow( url, title,600,900);
		<?endif;?>
	} else {
		var url="/logging.htm?title=" + title + "&cmd=/usr/bin/tail -n 42 -f <?=$plglogfile;?>&forkCmd=Start";
		openWindow( url, title.replace(/ /g, "_"),600,900);
	}
}
 
function checkDATADIR(form) {
	if (form.use_data.checked == false ) {
		form.arg4.value = form.arg3.value + "/config";
		form.arg4.type = "hidden";
	} else {
		form.arg4.value = "<?=$app_configdir;?>";
		form.arg4.type = "text";
	}
}

function checkLOGDIR(form) {
	if (form.use_logs.checked == false ) {
		form.arg5.value = "";
		form.arg5.type = "hidden";
	} else {
		form.arg5.value = "<?=$app_logsdir;?>";
		form.arg5.type = "text";
	}
}

function checkCACHEDIR(form) {
	if (form.use_cache.checked == false ) {
		form.arg6.value = "";
		form.arg6.type = "hidden";
	} else {
		form.arg6.value = "<?=$app_cachedir;?>";
		form.arg6.type = "text";
	}
}

function checkSSLPORT(form) {
	if (form.use_ssl.checked == false ) {
		form.arg7.value = "no";
	} else {
		form.arg7.value = "yes";
	}
}

var currentindex = document.app_settings.repo.selectedIndex;
function checkREPO(form) {
	if (form.repo.selectedIndex < 1 ) {
		form.arg10.value = form.repo.options[form.repo.selectedIndex].value;
		form.arg10.type = "hidden";
		document.getElementById("custom_repo").style.display = "none";
	} else {
		form.arg10.value = "<?=$app_repo;?>";
		form.arg10.type = "text";
		document.getElementById("custom_repo").style.display = "";
	}

	if (form.repo.selectedIndex == currentindex ) {
		document.getElementById("repo_warning").style.display = "none";
	} else {
		document.getElementById("repo_warning").style.display = "";
	}
}

function checkBRANCH(form) {
	form.arg11.value = form.branch.options[form.branch.selectedIndex].value;
}

function checkUSER(form) {
	if (form.runas.selectedIndex < 2 ) {
		form.arg12.value = form.runas.options[form.runas.selectedIndex].value;
		form.arg12.type = "hidden";
	} else {
		form.arg12.value = "<?=$app_runas;?>";
		form.arg12.type = "text";
	}
}

function checkSTORAGE(form) {
	form.arg13.value = form.storagesize.options[form.storagesize.selectedIndex].value;
}

function checkDATAPERSIST(form) {
	form.arg14.value = form.datacheck.options[form.datacheck.selectedIndex].value;
}

function checkONLINESTAT(form) {
	form.arg15.value = form.checkonline.options[form.checkonline.selectedIndex].value;
}

function verifyDATA(form) {
	if (form.arg3.value == null || !(/\S/.test(form.arg3.value))){
		form.arg3.value = "/usr/local/<?=$appname?>";
	}
	if (form.arg4.value == null || !(/\S/.test(form.arg4.value)) || form.use_data.checked == false){
		form.arg4.value = form.arg3.value + "/config";
	}
	if (form.arg5.value == null || !(/\S/.test(form.arg5.value)) || form.use_logs.checked == false) {
		form.arg5.value = "!";
	}
	if (form.arg6.value == null || !(/\S/.test(form.arg6.value)) || form.use_cache.checked == false) {
		form.arg6.value = "!";
	}
	if (form.arg7.value != "yes" && form.arg7.value != "no") {
		form.arg7.value = "no";
	}
	if (isNumber(form.arg8.value)) {
		if (form.arg8.value < 0 || form.arg8.value > 65535) {
			form.arg8.value = "5299";
		}
	} else {
		form.arg8.value = "5299";
	}
	if (!(/\S/.test(form.arg9.value))) {
		form.arg9.value = "!";
	}
	if (form.arg10.value == null || !(/\S/.test(form.arg10.value))) {
		form.arg10.value = "<?=$appRepoURL;?>";
	}
	if (form.arg11.value == null || !(/\S/.test(form.arg11.value))) {
		form.arg11.value = "master";
	}
	if (form.arg12.value == null || !(/\S/.test(form.arg12.value))) {
		form.arg12.value = "nobody";
	}
	if (form.arg13.value != "yes" && form.arg13.value != "no") {
		form.arg13.value = "yes";
	}
	if (form.arg14.value != "yes" && form.arg14.value != "no") {
		form.arg14.value = "yes";
	}
	if (form.arg15.value != "yes" && form.arg15.value != "no") {
		form.arg15.value = "yes";
	}

	form.arg1.value = form.arg1.value.replace(/ /g,"_");
	form.arg2.value = form.arg2.value.replace(/ /g,"_");
	form.arg3.value = form.arg3.value.replace(/ /g,"_");
	form.arg4.value = form.arg4.value.replace(/ /g,"_");
	form.arg5.value = form.arg5.value.replace(/ /g,"_");
	form.arg6.value = form.arg6.value.replace(/ /g,"_");
	form.arg7.value = form.arg7.value.replace(/ /g,"_");
	form.arg8.value = form.arg8.value.replace(/ /g,"_");
	form.arg9.value = form.arg9.value.replace(/ /g,"_");
	form.arg10.value = form.arg10.value.replace(/ /g,"_");
	form.arg11.value = form.arg11.value.replace(/ /g,"_");
	form.arg12.value = form.arg12.value.replace(/ /g,"_");
	form.arg13.value = form.arg13.value.replace(/ /g,"_");
	form.arg14.value = form.arg14.value.replace(/ /g,"_");
	form.arg15.value = form.arg15.value.replace(/ /g,"_");

	form.arg2.value = form.arg3.value
		+ " " + form.arg4.value
		+ " " + form.arg5.value
		+ " " + form.arg6.value
		+ " " + form.arg7.value
		+ " " + form.arg8.value
		+ " " + form.arg9.value
		+ " " + form.arg10.value
		+ " " + form.arg11.value
		+ " " + form.arg12.value
		+ " " + form.arg13.value
		+ " " + form.arg14.value
		+ " " + form.arg15.value;
	form.arg2.value = form.arg2.value.replace(/@20/g,"@@2200");
	form.arg2.value = form.arg2.value.replace(/ /g,"@20");
	return true;
}

function isNumber(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}

checkUSER(document.app_settings);
checkREPO(document.app_settings);
checkBRANCH(document.app_settings);
checkDATADIR(document.app_settings);
checkLOGDIR(document.app_settings);
checkCACHEDIR(document.app_settings);
checkSSLPORT(document.app_settings);
checkSTORAGE(document.app_settings);
checkDATAPERSIST(document.app_settings);
checkONLINESTAT(document.app_settings);
</script>
