## DD-WRT NordVPN scripts


### Synopsis

The purpose of this project is to enable a router liberated by DD-WRT to connect to the service [NordVPN](https://nordvpn.com).
All the clients in the network attached to the router will be able to use NordVPN via one login.
The author of this software is in no way associated with embeDD GmbH or the hosting company NordVPN.
Furthermore, the author of this software does not take on any responsibilities for damages resulting from this software or information.


### Prerequisites

To run the shell scripts you need a router capable of running DD-WRT. You can check your router
at https://dd-wrt.com/site/support/router-database. If it's not in the list get a decent router which is in the list.
Your router should have enough memory to support curl: https://forum.dd-wrt.com/phpBB2/viewtopic.php?p=1183057.
The router must dispose of a USB slot. If it doesn't have a USB slot get a decent router which has a USB slot.
Then follow the instructions on dd-wrt.com to get the DD-WRT firmware running on your router.
When DD-WRT is up and running ensure that an OpenVPN client is available (_Services > VPN_) and supports User Pass Authentication.
If there is no User Pass Authentication in the OpenVPN client get a different version of DD-WRT for your router.
You will also need a USB memory stick. Get the cheapest you can find at your local supplier, that will do. Size doesn't matter ;-)
Last but not least you need a user account at [NordVPN](https://nordvpn.com) composed of username and password.

**Make sure you have erased your NVRAM after installing or upgrading DD-WRT or your router might behave weird after some time.**


### Disclaimer

With end of 2020 we have officially stopped supporting routers which cannot run curl on the DD-WRT command line interface. The trouble caused by websites like GitHub which enforce HTTPS was just too big to to solved on the long term run.
Under no circumstances we can be held responsible or liable in any way for any claims, damages, losses, expenses, costs or liabilities whatsoever. It is not likely that the software would cause any damage but we trust in your common sense and technical understanding to use the scripts in a responsible way. Do not put them in the microwave, do not shortcut, do not try to explode things with it.


### Router behind router setup

If you would like to connect your DD-WRT router to another router which contains a modem to connect to the internet this step might be of interest for you. If your DD-WRT router is connected to the internet directly you can skip this step.
Connect your DD-WRT router with an ordinary (preferably short) patch cable from its WAN port to one of the free LAN ports of your main router.
Ensure that the ip ranges of your outer router's network and your inner router's network do not overlap. Best go for 192.168.0.* in the outside network and for 10.0.0.* in the inside network or comparable.

_Usually the following step is not necessary but for the case you cannot ping the ip address of your outer router from the DD-WRT command line you might want to try this:_

Navigate in the DD-WRT menu to Setup > Basic Setup. Make sure "Gateway" and "Local DNS" are set to 0.0.0.0 and Static DNS 1 is set to the DD-WRT router's intranet IP. Navigate to Setup > Advanced Routing and set the following options:

- Operating Mode: Gateway
- Dynamic Routing Interface: Disable
- Route Name: _preferebly the name of your "outer" router_
- Metric: 0
- Destination LAN NET: _IP of "outer" router_
- Subnet Mask: _netmask of "outer" intranet_
- Gateway: _IP of "outer" router_
- Interface: LAN & WAN


### Configuring DD-WRT to use our scripts

Put the USB memory stick into the (preferably USB3) slot. In the DD-WRT menu navigate to _Administration > Diagnosis_.
Enter the following into the "commands" textbox:

```
sleep 5 && mount -o bin /dev/sda1 /jffs
sleep 30 && /jffs/usr/bin/startup
```

Save this as **Startup** with the button below.

This will mount your USB stick when the router boots up to /jffs and ensures your router gets a valid exit node right after booting, for the case your predefined exit node became invalid over time.

Now enter the following into the textbox:

```
iptables -I FORWARD -i br0 -o tun0 -j ACCEPT
iptables -I FORWARD -i tun0 -o br0 -j ACCEPT
iptables -I FORWARD -i br0 -o $(nvram get wan_iface) -j DROP
iptables -I INPUT -i tun0 -j REJECT
iptables -t nat -A POSTROUTING -o tun0 -j MASQUERADE
```

Save this as **Firewall** with the button below.

This is a [kill switch](https://en.wikipedia.org/wiki/Internet_kill_switch). We highly recommend doing this.
With these rules you prevent the router from letting devices in the intranet access the internet if the VPN connection is down.

In the DD-WRT menu navigate to _Services_ and enable SSHd. We will need this to connect to the router later.
Last we need some cron jobs to be defined.
Head over to _Administration > Management_ in the DD-WRT menu. In the **Cron** section hit **Enable**.
In the "Additional Cron Jobs" textbox enter the following (be sure to have the last line empty):

```
*/5 * * * * root /jffs/usr/bin/checkcon 2>&1
58 * * * * root killall -q vpn
59 * * * * root killall -q speedtest
 0 * * * * root /jffs/usr/bin/speedcheck 2>&1
 0 3 * * * root /jffs/usr/bin/update 2>&1
```

These jobs regularly check your internet connection and take on measurements if necessary (changing the VPN server or rebooting). The last line updates the scripts automatically every night at 3 am, so you don't need to update the scripts yourself manually all the time. Just don't use this line if you don't wish nightly auto updates.

Now reboot the router.

When the router is back up use a tool like [WinSCP](https://winscp.net) to upload [the scripts](https://github.com/TobseTobse/DD-WRT_NordVPN/archive/master.zip) to /jffs or simply put the USB stick into your desktop computer or notebook and copy the files the the stick's root directory.
Use a tool like [PuTTY](http://www.putty.org) and connect to your router.
Now let's make the scripts executable:

```
cd /jffs/usr/bin
chmod ugo+x *
```

Now let's define an initial VPN server to connect to. In the DD-WRT menu go to _Services > VPN_.
Pick one of the server configs you would like to connect to per default to after the router has booted.
You find these files in the serverconfigs directory.

Read the configuration values from the openvpn.conf file in the server directory you have chosen. Try to match the fields in the **OpenVPN Client** section of the DD-WRT administration interface as good as possible with the values from the configuration file. In my case the values look similar to this (values might slightly change with newer DD-WRT releases but this is about the core you need):

- Start OpenVPN Client: Enable
- Server IP/Name: _get the ip address from the "remote" line in openvpn.conf_
- Port: 1194
- Tunnel Device: TUN
- Tunnel Protocol: UDP
- Encryption Cipher: AES-256 CBC
- Hash Algorithm: SHA512
- **User Pass Authentication: Enable**
- Username: _Your NordVPN username_
- Password: _Your NordVPN password_
- Advanced Options: Enable
- TLS Ciper: TLS-DHE-RSA-WITH-AES-256-CBC-SHA256
- LZO Compression: Adaptive
- NAT: Enable
- Firewall Protection: Enable
- IP Address: _leave empty_
- Subnet Mask: _leave empty_
- Tunnel MTU setting: 1500
- Tunnel UDP Fragment: _leave empty_
- Tunnel UDP MSS-Fix: Disable
- nsCertType verification: nope
- TLS Auth Key: _copy & paste the content of the ta.key file in the chosen serverconfig directory_
- CA Cert: _copy & paste the content of the ca.crt file in the chosen serverconfig directory_

For all other fields not mentioned above: _leave empty or unchanged_.
Just bear in mind that NordVPN can change these values at any time (they have done that already) and that it's always better to match the values yourself manually than taking my values.

Save and reboot your router.
You should be good to go now.


### Check your external IP address

First of all we would like to know whether we are using NordVPN or not right now.
Connect your device with the DD-WRT router and disable all other connections from your device to any networks.
Now check your IP at https://ipinfo.io. Does this look any familiar? No? Good, then you are connected via a NordVPN exit node ;-)


### Tweaking the script configurations

Usually, the scripts should work without any intervention from your side. The core of the scripts is in /jffs/usr/bin.
You can tweak these scripts if you urgently feel the need to do so. In all other cases we recommend to refrain from doing that.
When you edit the scripts you will be able to change a few values in their respective head sections.
Please do not edit below the "configuration end" line.


### Usage of the scripts

If you connect to your router via SSH or Telnet you can use the scripts like that:

`config`

Don't touch this script. It will be overwritten with the next repository checkout. If you need a custom local configuration make a copy of this file and name the copy "myconfig", like this:

```
cd /jffs/usr/bin
cp config myconfig
```

Then modify the _myconfig_ file to your needs. The script collection always includes the _config_ file first. Then, if existent, the _myconfig_ will be included. Therefore all values specified in the _myconfig_ file will override the ones from the _config_ file automatically. If you don't specify a value in the _myconfig_ file, it will have the default value from the _config_. So usually the _myconfig_ is either not existent or just contains the `#!/bin/sh` header and a few other lines with settings you would like to override. There's no point in modifying the _config_ file, because it will be automatically overwritten by the update script when the cron demon calls it (usually once per day).

`checkcon`

This script checks if you can ping the host specified in the script configuration.
By default wikipedia.org is pinged 20 times. If there are not enough pongs coming back this script will change the VPN server.

`speedcheck`

This script first invokes the _checkcon_ script. If the connection is okay the script proceeds to the following step:
It downloads a test file from a server which is in the same country as the VPN server you're connected to.
If the connection speed is below the predefined threshold the script will change the VPN server.
You can call the script with a parameter: `speedcheck checkonly` doesn't change the VPN server if the measured bandwidth is too low.

`vpn {server shortcut}`

This script switches the VPN server to one of the servers in the serverconfigs directory (e.g. `vpn ca0006tcp` or `vpn nl0053udp`).
When you call the script with `vpn rnd` it will switch to a randomly selected VPN server from the list in the serverconfigs directory, respecting the rules defined in the configuration to either only connect to a server from a list of desired countries or to avoid connections to a server from a list of specified countries (this is default).

`startup`

This script should be invoked when the router boots up. It's main purpose is to clean up lock files and to start the MAC randomizer if desired.

`randommac`

This script is called by the startup script by default. When executed, it changes the MAC address of the WiFi interface to a randomly generated value. If you hide the SSID of your WiFi, there's no way for Google or other evil forces to reuse your router as an anchor for geolocation or worse. We recommend doing this, regardless if you hide your SSID or not. You could disable this function with a _myconfig_ override if desired.

`update`

This script should be called automatically by the cron demon and updates all scripts in /jffs/usr/bin from GitHub for you (best every night).


### Updating the server configuration files

It happens regularly that NordVPN changes (adds, removes, modifies) the list of servers they provide. Unfortunately, there is no easy way to handle this yet.
If you want to convert the OpenVPN files yourself you can download the ".upd1194.ovpn" files from https://nordvpn.com/ovpn and parse them with [PHP](https://secure.php.net). If you want to run PHP on your Windows machine you can use a framework like [XAMPP](https://www.apachefriends.org) or give it to someone who has a webserver running PHP. Put the ".upd1194.ovpn" files together with *make_serverconfig.php* into a directory and run it with

`php make_serverconfig.php`

If this is too much hassle for you just check on [the DD-WRT NordVPN project site](https://tobsetobse.github.io/DD-WRT_NordVPN) occasionally. We will try to keep the server configuration files a bit up-to-date but we won't include servers from a list of countries we personally consider untrustworthy. Deal with it 8-)


### WTF...? I found new files in the /jffs directory which I haven't copied there!

Breathe. It's all good. The VPN scripts write the following files into /jffs:
- *servers.good.log*: a log with measured connection speeds of servers above the configured speed threshold
- *servers.bad.log*: a log with measured connection speeds of servers below the configured speed threshold
- *speedtestservers.xml*: this file contains servers via which the connection speed can be measured
- *fastestspeed*: this file contains the maximum download speed ever measured by the speedcheck script in MBit/s
- *tmp*: this directory mainly contains lock files. The scripts need them to avoid parallel script executions or boot loops.


### Troubleshooting

- Try removing the kill switch we have added as firewall script. Open a website with a browser being connected to the DD-WRT router. Do you get a result? Then your hardware setup and routing is fine. No result? Dang! Try a different version of DD-WRT. DD-WRT releases are pretty buggy sometimes.
- Execute a *route* command via Administration > Commands (Diagnostics). Do you see your outer router's network in the list? If not read this manual again.
- Assuming your hardware setup and routing are fine you can put back the kill switch now. Now navigate to Administration > Commands (Diagnostics) in the DD-WRT menu and enter the following command into the "Commands" box: `cat /tmp/openvpncl/openvpn.log` and click on the button "Run Commands" below. Does this help you any further? No? Then ask Google or a friend who knows a bit more about OpenVPN.


### tl;dr ###

Yes, we understand. It's a lot of text to read. So here comes the short version for all impatient users:

- Flash [DD-WRT](https://dd-wrt.com) to your router
- Download the [master ZIP](https://github.com/TobseTobse/DD-WRT_NordVPN/archive/master.zip), extract it to a USB stick and put the stick into your DD-WRT router
- Login to [http://{your router ip}/admin](http://192.168.0.1/admin), copy the contents of the [Configuring DD-WRT to use our scripts](https://tobsetobse.github.io/DD-WRT_NordVPN#configuring-dd-wrt-to-use-our-scripts) text blocks to the respective fields
- make the scripts executable and reboot your router

That's it. Have fun!


### License

This project is released under the MIT License

Copyright (c) 2017 Tobse

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


### Source code

Check out the source code on [GitHub](https://github.com/TobseTobse/DD-WRT_NordVPN).
