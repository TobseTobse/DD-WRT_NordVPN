<?php

########################################################################
#                                                                      #
#   Serverconfig conversion script v3.02                               #
#   (c) by Tobse (cthullu@protonmail.com) in 2017                      #
#                                                                      #
#   This script converts NordVPN OpenVPN files in the ovpn directory   #
#   ending on ".nordvpn.com.udp1194.ovpn" to a format the DD-WRT       #
#   scripts from https://tobsetobse.github.io/DD-WRT_NordVPN can       #
#   work with.                                                         #
#                                                                      #
#   The script is meant to be called from the CLI with the command     #
#                                                                      #
#   php make_serverconfig.php                                          #
#                                                                      #
########################################################################

// script may run one hour maximum
ini_set("max_execution_time", 3600);

// get all ovpn files from https://nordvpn.com/ovpn
// and save them to the "ovpn" folder
$nordvpnURL = "https://nordvpn.com/ovpn";
out("Getting HTML from " . $nordvpnURL . "...");
$html = @file_get_contents($nordvpnURL);
if (trim ($html) == "") {
  die ("Got no HTML. Please check internet connection.\n");
}
preg_match_all("%\"([^\"]*?udp1194\.ovpn)\"%sim", $html, $hits);
out("Retrieved data for " . count($hits[1])
   . " VPN servers from " . $nordvpnURL);
if (!file_exists("ovpn")) mkdir("ovpn");
foreach ($hits[1] as $hit) {
  $url = $hit;
  if (!preg_match("%^https?://%i", $hit)) {
    $url = $nordvpnURL . "/" . $hit;
  }
  $hitParts = explode("/", $hit);
  $filename = $hitParts[count($hitParts) - 1];
  if (!preg_match("%^[a-z]{2}\d+\.%i", $filename)
   || file_exists("ovpn/" . $filename)) continue;

  // download ovpn file
  file_put_contents("ovpn/" . $filename, file_get_contents($url));
  out("Got server config " . $filename);
}

// create "serverconfigs" directory
if (!file_exists("serverconfigs")) mkdir ("serverconfigs");

// get all files from ovpn directory
$files = scandir("ovpn");
natsort($files);

foreach ($files as $filename) {

  // skip files not ending on ".nordvpn.com.udp1194.ovpn"
  if (!preg_match("%\.nordvpn\.com\.udp1194\.ovpn$%", $filename)) {
    continue;
  }

  // read ovpn file
  $file = file_get_contents("ovpn/" . $filename);

  // extract "ch1" or "lv-tor1" shortcut from filename
  $sc = preg_replace("%\.nordvpn\.com\.udp1194.ovpn$%", "", $filename);
  
  // well-form shortcut ("hk7" => "hk007")
  preg_match("%^([a-z]+?)(\d+)%i", $sc, $scParts);
  if (is_array($scParts) && count($scParts) == 3) {
    $sc = $scParts[1] . sprintf("%03d", $scParts[2]);
  }
  $sc = "serverconfigs/" . $sc;

  // create directory for VPN node
  if (!file_exists($sc)) {
    mkdir($sc);
  }

  // extract ca-certificate from ovpn file
  preg_match("%<ca>(.+?)</ca>%sm", $file, $hits);
  $ca = trim($hits[1]);
  
  // write ca-certificate to target file
  file_put_contents($sc . "/ca.crt", $ca);

  // extract tls-key from ovpn file
  preg_match("%<tls-auth>(.+?)</tls-auth>%sm", $file, $hits);
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
     || preg_match("%^auth-user-pass%", $line)
     || preg_match("%^comp-lzo%", $line)) {
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
        . "dev tun1\n"
        . "auth sha1\n"
        . "comp-lzo adaptive\n"
        . "mtu-disc yes\n"
        . "passtos";

  // write remaining rest of ovpn to target file
  file_put_contents($sc . "/openvpn.conf", trim($file));
  out("Wrote config for server "
     . str_replace("serverconfigs/", "", $sc) . ".");
}

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