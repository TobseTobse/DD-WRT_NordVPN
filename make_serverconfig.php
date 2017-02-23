<?php

########################################################################
#                                                                      #
#   Serverconfig conversion script v1.01                               #
#   (c) by Tobse (cthullu@protonmail.com) in 2017                      #
#                                                                      #
#   This script converts NordVPN OpenVPN files in the current          #
#   directory ending on ".nordvpn.com.udp1194.ovpn" to a format the    #
#   DD-WRT scripts from https://tobsetobse.github.io/DD-WRT_NordVPN    #
#   can work with.                                                     #
#                                                                      #
#   The script is meant to be called from the CLI with the command     #
#                                                                      #
#   php make_serverconfig.php                                          #
#                                                                      #
########################################################################

# get all files in current directory
$files = scandir(".");

foreach ($files as $filename) {

  # skip files not ending on ".nordvpn.com.udp1194.ovpn"
  if (!preg_match("%\.nordvpn\.com\.udp1194\.ovpn$%", $filename)) {
    continue;
  }

  # read ovpn file
  $file = file_get_contents($filename);

  # extract "ch1" or "lv-tor1" shortcut from filename
  $sc = preg_replace("%\.nordvpn\.com\.udp1194\.ovpn$%", "", $filename);
  echo "Parsing config " . $sc . "\n";
  
  # well-form shortcut ("hk7" => "hk07")
  if (preg_match("%[^\d]\d$%", $sc)) {
    $sc = substr($sc, 0, -1) . "0" . substr($sc, -1);
  }

  # create target directory
  if (!file_exists($sc)) {
    mkdir($sc);
  }

  # extract ca-certificate from ovpn file
  preg_match("%<ca>(.+?)</ca>%sm", $file, $hits);
  $ca = trim($hits[1]);
  
  # write ca-certificate to target file
  file_put_contents($sc . "/ca.crt", $ca);

  # extract tls-key from ovpn file
  preg_match("%<tls-auth>(.+?)</tls-auth>%sm", $file, $hits);
  $tls = trim($hits[1]);
  
  # write tls-key to target file
  file_put_contents($sc . "/ta.key", $tls);

  # remove ca-certificate from ovpn
  $file = preg_replace("%<ca>.+?</ca>\s*%sm", "", $file);

  # remove tls-key from ovpn
  $file = preg_replace("%<tls-auth>.+?</tls-auth>\s*%sm", "", $file);

  # remove lines from ovpn we will overwrite if present
  $lines = preg_split("%[\r\n]%", $file);
  foreach ($lines as $key => $line) {
    if (preg_match("%^dev %", $line)
     || preg_match("%^auth-user-pass%", $line)
     || preg_match("%^comp-lzo%", $line)) {
      unset($lines[$key]);
    }
  }
  $file = implode(chr(10), $lines);

  # add the following lines to the ovpn config
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

  # write remaining rest of ovpn to target file
  file_put_contents($sc . "/openvpn.conf", $file);
}

echo "Job's done.\n";