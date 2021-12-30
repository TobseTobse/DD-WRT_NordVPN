<?php

########################################################################
#                                                                      #
#   Serverconfig conversion script v6.02                               #
#   (c) by Tobse (cthullu@protonmail.com) in 2017                      #
#                                                                      #
#   This script converts NordVPN OpenVPN files in the ovpn directory   #
#   ending on ".nordvpn.com.*.ovpn" to a format the DD-WRT scripts     #
#   from https://tobsetobse.github.io/DD-WRT_NordVPN can work with.    #
#                                                                      #
#   The script is meant to be called from the CLI with the command     #
#                                                                      #
#   php make_serverconfig.php                                          #
#                                                                      #
########################################################################

// get configuration from config.php
include ("config.php");
ini_set("max_execution_time", $maxsecs);
$ignorecountries = explode(",", $ignore);

// regular expression server configs must match to be parsed
$protocolPattern = "(?:udp|tcp)";
if ($protocol == "tcp" || $protocol == "udp") {
  $protocolPattern = $protocol;
}
$fileMatchPattern = "%^[a-z-]{2,5}\d+?\." . $protocolPattern
                  . "(\d+?)\.ovpn$%i";

// get all ovpn files from https://nordvpn.com/ovpn
// and save them to the "ovpn" folder
$nordvpnURL = "https://nordvpn.com/ovpn";
out("Getting HTML from " . $nordvpnURL . "...");
$html = @file_get_contents($nordvpnURL);
if (trim ($html) == "") {
  die ("Got no HTML. Please check internet connection.\n");
}
preg_match_all("%\"([^\"]*?)" . $protocolPattern
             . "(\d+)\.ovpn\"%sim", $html, $hits);
$withProtocol = "";
if ($protocol == "tcp" || $protocol == "udp") {
  $withProtocol = "with protocol " . strtoupper($protocol) . " ";
}
out("Retrieved data for " . count($hits[1]) . " VPN server "
  . "configurations " . $withProtocol . "from " . $nordvpnURL);
if (!file_exists("ovpn")) mkdir("ovpn");
$hits = $hits[0];
out("Shuffling configs for random download.");
shuffle($hits);
out("Done.");
out("");

// get already downloaded server configurations
$downloaded = array();
chdir("ovpn");
$myconfigs = glob("*.ovpn");
chdir("..");
foreach ($myconfigs as $filename) {
  $servername = strstr($filename, ".", true);
  preg_match_all("%([a-z]{2})\d+%i", $servername, $spHits);
  foreach ($spHits[1] as $checkCountry) {
    if (!array_key_exists($checkCountry, $downloaded)) {
      $downloaded[$checkCountry] = 1;
    }
    else $downloaded[$checkCountry]++;
  }
}

// loop trough downloads
$cnt = 0;
foreach ($hits as $hit) {

  // stop downloading if maximum amount of server configs is reached
  $hasConfigs = count($myconfigs);
  if ($hasConfigs >= $maxServers) {
    out("Maximum of " . $maxServers . " server configurations "
      . "reached. Download stopped.");
    break;
  }

  // prepare filename
  $url = str_replace('"', "", $hit);
  $hitParts = explode("/", $url);
  $filename = preg_replace("%\.nordvpn\.com%", "",
                           $hitParts[count($hitParts) - 1]);
  $configParts = explode(".", $filename);

  // don't download configs from ignored countries
  $ignore = false;
  foreach ($ignorecountries as $country) {
    if (preg_match("%" . $country . "%i", $filename)) {
      $ignore = true;
      break;
    }
  }

  // don't download more than $maxPerCountry configs per country
  if (!$ignore) {
    $servername = strstr($filename, ".", true);
    preg_match_all("%([a-z]{2})\d+%i", $servername, $spHits);
    foreach ($spHits[1] as $checkCountry) {
      if (!array_key_exists($checkCountry, $downloaded)) {
        $downloaded[$checkCountry] = 0;
      }
      if ($downloaded[$checkCountry] > $maxPerCountry) {
        out("Country " . strtoupper($checkCountry) . " already "
          . "has " . $maxPerCountry . " servers to connect to.");
        $ignore = true;
        break;
      }
    }
  }

  // skip download if file already exists
  if (!$ignore && in_array($filename, $myconfigs)) {
    out("We already have a config for " . $configParts[0] . ".");
    $ignore = true;
  }

  // if only one protocol is allowed, skip if we already have
  // another configuration file for this particular server
  if (!$ignore && $protocol == "one") {
    if (count(glob("ovpn/" . $configParts[0] . "*.ovpn")) > 0) {
      out("Server " . $configParts[0] . " already has another config.");
      $ignore = true;
    }
  }

  // quit iteration if requirements for download are not met
  if ($ignore || !preg_match($fileMatchPattern, $filename)) {
    continue;
  }

  // download ovpn file
  if ($ovpn = @file_get_contents($url)) {
    file_put_contents("ovpn/" . $filename, $ovpn);
    $myconfigs[] = $filename;
    out("Downloaded server config for " . $configParts[0] . " ("
      . sprintf("%0.2f", 100/$maxServers*++$hasConfigs) . "%).");
    foreach ($spHits[1] as $downloadedCountry) {
      $downloaded[$downloadedCountry]++;
    }
  }
}

// create "serverconfigs" directory
if (!file_exists("serverconfigs")) mkdir ("serverconfigs");

// get all files from ovpn directory
$files = scandir("ovpn");
natcasesort($files);
out("");

$cnt = 0;
$scDirs = array();
foreach ($files as $filename) {
  $cnt++;

  // skip files not ending on ".ovpn"
  if (!preg_match($fileMatchPattern, $filename)) {
    continue;
  }

  // read ovpn file
  $file = file_get_contents("ovpn/" . $filename);

  // extract "ch1" or "lv-tor1" shortcut from filename
  $fnParts = explode(".", $filename);
  $sc = $fnParts[0] . "." . $fnParts[1];
  
  // well-form shortcut ("hk7" => "hk0007")
  preg_match("%^([a-z-]+?)(\d+)\.(tcp|udp)%i", $sc, $scParts);
  if (is_array($scParts) && count($scParts) == 4) {
    $sc = str_replace("-", "", $scParts[1])
        . sprintf("%04d", $scParts[2]) . $scParts[3];
  }
  $sc = "serverconfigs/" . $sc;
  $scDirs[] = $sc;
  
  // create directory for VPN node
  if (!file_exists($sc)) {
    mkdir($sc);
  }

  // extract ca-certificate from ovpn file
  preg_match("%<ca>(.+?)</ca>%sm", $file, $hits);
  if (!isset($hits[1])) continue;
  $ca = trim($hits[1]);
  
  // write ca-certificate to target file
  file_put_contents($sc . "/ca.crt", $ca);

  // extract tls-key from ovpn file
  preg_match("%<tls-auth>(.+?)</tls-auth>%sm", $file, $hits);
  if (!isset($hits[1])) continue;
  $tls = trim($hits[1]);
  
  // write tls-key to target file
  file_put_contents($sc . "/ta.key", $tls);

  // remove ca-certificate from ovpn
  $file = preg_replace("%<ca>.+?</ca>\s*%sm", "", $file);

  // remove tls-key from ovpn
  $file = preg_replace("%<tls-auth>.+?</tls-auth>\s*%sm", "", $file);

  // remove lines from ovpn we will overwrite if present
  $lines = preg_split("%[\r\n]%", $file);
  foreach ($lines as $key => $line) {
    if (preg_match("%^dev %", $line)
     || preg_match("%^auth-user-pass%", $line)) {
      unset($lines[$key]);
    }
  }
  $file = implode(chr(10), $lines);

  // add the following lines to the ovpn config
  $file.= "\nca /tmp/openvpncl/ca.crt\n"
        . "writepid /var/run/openvpncl.pid\n"
        . "auth-user-pass /tmp/openvpncl/credentials\n"
        . "tls-auth /tmp/openvpncl/ta.key 1\n"
        . "syslog\n"
        . "script-security 2\n"
        . "dev tun1\n";

  // write remaining rest of ovpn to target file
  file_put_contents($sc . "/openvpn.conf", trim($file));
  out("Wrote config for server "
     . str_replace("serverconfigs/", "", $sc) . " ("
     . sprintf("%0.2f", 100/count($files)*$cnt) . "%).");
}

out("");
// check if all server config directories are complete
$neededFiles = array("ca.crt", "openvpn.conf", "ta.key");
foreach ($scDirs as $scDir) {
  foreach ($neededFiles as $neededFile) {
    if (!file_exists($scDir . "/" . $neededFile)) {
      foreach ($neededFiles as $nukeFile) {
        if (file_exists($scDir . "/" . $nukeFile)) {
          unlink ($scDir . "/" . $nukeFile);
        }
      }
      if (file_exists($scDir)) rmdir ($scDir);
      $sc = str_replace("serverconfigs/", "", $scDir);
      out ("Removed incomplete server configs for " . $sc . ".");
    }
  }
}

out("");
out("Job's done.");

// display output
function dummyErrorHandler ($errno, $errstr, $errfile, $errline) {}
function out($text) {

  // determine current invocation (CLI or web server)
  $br = "\n";
  if (php_sapi_name() != "cli") {
    $br = "<br />" . $br;
  }

  echo $text . $br;
  ob_start();
  ob_end_clean();
  flush();
  set_error_handler("dummyErrorHandler");
  ob_end_flush();
  restore_error_handler();
}