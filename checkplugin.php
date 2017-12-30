<?php
// Plugin slug
if ( $_GET['slug'] ) {
	$plug_slug = $_GET['slug'];
	echo 'Plugin slug taken from url: ' . $plug_slug . '<br>';
} else {
	$plug_slug = 'bbp-toolkit';
	//$plug_slug = 'wpcasa-mail-alert';
	echo '<span style="color: orange;">' . 'No slug found in this url (add ?slug=myplugin). Using ' . $plug_slug . ' as example.</span>' . '<br>';
}	

// Check base dir	
$base_dir = 'https://plugins.svn.wordpress.org/' . $plug_slug;
$retcode = checkplug_get_retcode($base_dir . '/');
if ($retcode != 200) {
	echo '<span style="color: red;">' . 'Unable to find path ' . $base_dir . '/</span>' . '<br>';
	die();
}

echo 'Plugin <b>slug</b>: ' . $plug_slug . '<br>';

// Check if readme.txt exists in trunk
$trunk_readme = $base_dir . '/trunk/readme.txt';
$retcode = checkplug_get_retcode($trunk_readme);
if ($retcode != 200) {
	echo '<span style="color: red;">' . 'Unable to read file from ' . $trunk_readme . '</span>' . '<br>';
	die();
}
echo '<b>Readme file</b> is available on ' . $trunk_readme . '<br>';

// Get the file
/*
WordPress.org’s Plugin Directory works based on the information found in the field Stable Tag in the readme. When WordPress.org parses the readme.txt, the very first thing it does is to look at the readme.txt in the /trunk directory, where it reads the “Stable Tag” line. If the Stable Tag is missing, or is set to “trunk”, then the version of the plugin in /trunk is considered to be the stable version. If the Stable Tag is set to anything else, then it will go and look in /tags/ for the referenced version. So a Stable Tag of “1.2.3” will make it look for /tags/1.2.3/.
*/
$text = checkplug_get_file_contents($trunk_readme);
$lines = explode("\n", $text);
$stable_tag = 'notfound';
$req_at_least = 'notfound';
foreach ($lines as $line) {
	if (stripos($line, 'Requires at least:') !== false) {
		echo ' - ' . $line . '<br>';
		$req_at_least = trim(substr($line, strlen('Requires at least:')));
	}

	if (stripos($line, 'Stable Tag:') !== false) {
		echo ' - ' . $line . '<br>';
		$stable_tag = strtolower(trim(substr($line, strlen('Stable Tag:'))));
	}
}

// Check the tags in the readme.txt trunk
if ( $stable_tag == 'trunk' ) {
	echo '<span style="color: orange;">' . 'Stable tag is set to trunk, is this what is expected?' . '</span>' . '<br>';
}
if ( $stable_tag == 'notfound' ) {
	echo '<span style="color: red;">' . 'Stable tag not found so defaulting to trunk, better define it!' . '</span>' . '<br>';
	$stable_tag = 'trunk';
}

// Get the tags if any
$tags_folder = $base_dir . '/tags';
$tags_html = checkplug_get_file_contents($tags_folder);
$lines = explode("\n", $tags_html);
$tags = array();
foreach ($lines as $line) {
	if (strpos($line, '<li>') !== false) {
		$tag = checkplug_get_text_between('<li><a href="', '/"', $line);
		if ($tag != '..') {
			$tags[] = $tag;
		}
	}
}
if ($tags) {
	// sort versions
	usort($tags, 'version_compare');
	
	echo 'Tag folders found: ';
	$first = true;
	foreach ($tags as $tag) {
		if (!$first) {
			echo ', ';
		} else {
			$first = false;
		}
		echo $tag;
	}
	if ($stable_tag == 'trunk') {
		echo ' (but none are used, only trunk)';
	}
} else {
	if ($stable_tag == 'trunk') {
		echo 'No folders found under /tag, but trunk is used so that is fine.';
	} else {
		echo '<span style="color: red;">' . 'No folders found under /tag' . '</span>';
	}
}
echo '<br>';

// Make sure the needed folder exists
if ($stable_tag == 'trunk') {
	$folder = $base_dir . '/trunk/';
} else {
	$folder = $base_dir . '/tags/' . $stable_tag . '/';
}
if (checkplug_get_retcode($folder) != 200) {
	echo '<span style="color: red;">' . 'Unable to find or access ' . $folder . '</span>';
	die();
}

// Get the files in that folder
$files = checkplug_get_file_contents($folder);

// Get first php file
$lines = explode("\n", $files);

$php_file = '';
foreach ($lines as $line) {
	$line = trim($line);
	if (strpos($line, '<li>') !== false) {
		$f = checkplug_get_text_between('<li><a href="', '/"', $line);
		if (!$f) {
			$php_file = checkplug_get_text_between('<li><a href="', '.php"', $line);
			if ($php_file) {
				$php_file = $php_file . '.php';
				break;
			}
		}
	}
}

if (!$php_file) {
	echo '<span style="color: red;">' . 'Unable to find or access the php file in ' . $folder . '</span>';
	die();
}
echo 'Checking <b>' . $php_file . '</b> in ' . $folder . '<br>';

// Read the (hopefully main) php file
$main_php = checkplug_get_file_contents($folder . $php_file);

// Get the comment part, there might be more then 1 /* */
$occurrence = 1;
$main_php_comment = checkplug_get_text_between('/*', '*/', $main_php, $occurrence);
while ($main_php_comment) {
	$lines = explode("\n", $main_php_comment);
	$plug_info = array();
	foreach ($lines as $line) {
		$line = trim($line);
		$t = 'Plugin Name:';
		$i = stripos($line, $t);
		if ($i !== false) {
			$plug_info['name'] = trim(substr($line, strlen($t) + $i));
			echo ' - ' . $t . ' ' . $plug_info['name'] . '<br>';
		}
		$t = 'Version:';
		$i = stripos($line, $t);
		if ($i !== false) {
			$plug_info['version'] = trim(substr($line, strlen($t) + $i));
			echo ' - ' . $t . ' ' . $plug_info['version'] . '<br>';
		}
		$t = 'Text Domain:';
		$i = stripos($line, $t);
		if ($i !== false) {
			$plug_info['text_domain'] = trim(substr($line, strlen($t) + $i));
			echo ' - ' . $t . ' ' . $plug_info['text_domain'] . '<br>';
		}
	}
	
	// If we get the version, we have the correct comment block
	if ( $plug_info['version'] ) break;
	
	$occurrence = $occurrence + 1;
	$main_php_comment = checkplug_get_text_between('/*', '*/', $main_php, $occurrence);
}

// Check Text domain
if ($plug_info['text_domain'] != $plug_slug) {
	echo '<span style="color: red;">' . 'Your plugin slug is ' . $plug_slug . ', but your Text Domain is ' . $plug_info['text_domain'] .'. Change your text_domain so it is equal to your slug' . '</span>';
	die();
}

// If trunk, check if version has existing tag
if ( ( $stable_tag == 'trunk' ) && ( in_array($plug_info['version'], $tags) ) ) {
	echo '<span style="color: orange;">' . 'Trunk is being used, but there is also a folder under tags for version ' . $plug_info['version'] . '</span>';
}	

// load_text_domain checks
if ( version_compare($req_at_least, '4.6', '>=') ) {
	echo 'Required version (' . $req_at_least . ') is at least 4.6 so no <b>load_text_domain</b> is needed.<br>';
	echo '<span style="color: green;">(more code needed here to perform this check)</span><br>';
} else {
	echo 'Required version (' . $req_at_least . ') is below 4.6 so a <b>load_text_domain</b> is needed.<br>';
	echo '<span style="color: green;">(more code needed here to perform this check)</span><br>';
}
  // MORE CODE NEEDED HERE TO CHECK

// Language packs
echo '<br>Language packs created:<br>';
$f = checkplug_get_file_contents('https://api.wordpress.org/translations/plugins/1.0/?slug=' . $plug_slug);
if ($f) {
	$a = json_decode($f);
	foreach ($a->translations as $item) {
		echo $item->updated . ', ' . $item->english_name . ' (' . $item->language . '), ' . $item->version . '<br>';
	}
}


echo '<br>Translation status (% per locale):<br>';
echo '<span style="color: green;">(more code needed here to perform this check)</span><br>';

// Latest Revision log from https://plugins.trac.wordpress.org/log/$plug_slug/
echo '<br>Latest Revision log entries:<br>';
echo '<span style="color: green;">(more code needed here to perform this check)</span><br>';
  // MORE CODE NEEDED HERE TO CHECK





/*
 * FUNCTIONS
 */

function checkplug_get_file_contents($url) {
	$ch = curl_init();
	curl_setopt_array(
		$ch, array( 
		CURLOPT_URL => $url,
		CURLOPT_HEADER => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_AUTOREFERER => true,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31',
		CURLOPT_FOLLOWLOCATION => true
	));
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}

function checkplug_get_retcode($url) {
	$ch = curl_init();
	curl_setopt_array(
		$ch, array( 
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_NOBODY => false,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31',
	));
	$output = curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	// $retcode >= 400 -> not found, $retcode = 200, found.
	return $retcode;
}

function checkplug_get_text_between($sstr, $estr, $haystack, $occurrence = 1) {
	// subtract 1 from occurrence to fit with array numbering
	$occurrence = $occurrence - 1;
	
	// Get all occurrences of sstr
	$all_start_pos = array();
	$s = strpos($haystack, $sstr);
	if ($s !== false) {
		$all_start_pos[] = $s;
		while ($s !== false) {
			$s = strpos($haystack, $sstr, $s + 1);
			if ($s) $all_start_pos[] = $s;
		}
	} else {
		$return = null;
	}
	
	if ( isset( $all_start_pos[$occurrence] ) ) {
		$s = $all_start_pos[$occurrence];
		$e = strpos($haystack, $estr, $s + strlen($sstr));
		if ($e !== false) {
			$return = trim(substr($haystack, $s + strlen($sstr) , $e - $s - strlen($sstr)));
		} else {
			$return = null;
		}
	} else {
		$return = null;
	}
	return $return;
}

?>