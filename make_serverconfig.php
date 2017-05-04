<?php

########################################################################
#                                                                      #
#   Serverconfig conversion script v2.01                               #
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

// script may run 30 minutes maximum
ini_set("max_execution_time", 1800);

// determine current invocation (CLI or web server)
$br = "\n";
if (php_sapi_name() != "cli") {
  $br = "<br />" . $br;
}

// get all 1194 ovpn files from https://nordvpn.com/ovpn
// and save them to the "ovpn" folder
$nordvpnURL = "https://nordvpn.com/ovpn";
$html = file_get_contents($nordvpnURL);
echo "Got HTML from " . $nordvpnURL . $br;
preg_match_all("%\"([^\"]*?udp1194)\"%sim", $html, $hits);
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

  // skip de/uk/us servers
  if (preg_match("%^(?:de|uk|us)%i", $filename)) continue;

  // download ovpn file
  file_put_contents("ovpn/" . $filename, file_get_contents($url));
  echo "Got file " . $filename . $br;
}

// create "serverconfigs" directory
if (!file_exists("serverconfigs")) mkdir ("serverconfigs");

// get all files from ovpn directory
$files = scandir("ovpn");

foreach ($files as $filename) {

  // skip files not ending on ".nordvpn.com.udp1194.ovpn"
  if (!preg_match("%\.nordvpn\.com\.udp1194$%", $filename)) {
    continue;
  }

  // read ovpn file
  $file = file_get_contents("ovpn/" . $filename);

  // extract "ch1" or "lv-tor1" shortcut from filename
  $sc = preg_replace("%\.nordvpn\.com\.udp1194$%", "", $filename);
  
  // well-form shortcut ("hk7" => "hk07")
  if (preg_match("%[^\d]\d$%", $sc)) {
    $sc = substr($sc, 0, -1) . "0" . substr($sc, -1);
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
  file_put_contents($sc . "/openvpn.conf", $file);
  echo "Wrote config for server "
     . str_replace("serverconfigs/", "", $sc) . "." . $br;
}

echo "Job's done." . $br;