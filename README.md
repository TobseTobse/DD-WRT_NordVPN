## DD-WRT NordVPN scripts

### Synopsis

The purpose of this project is to enable a router liberated by DD-WRT to connect to the service [NordVPN](https://nordvpn.com).
All the clients in the network attached to the router will be able to use NordVPN via one login.
The author of this software is in no way associated with embeDD GmbH or the hosting company NordVPN.
Furthermore, the author of this software does not take on any responsibilities for damages resulting from this software or information.

### Prerequisites

To run the shell scripts you need a router capable of running DD-WRT. You can check your router
at https://dd-wrt.com/site/support/router-database. If it's not in the list get a decent router which is in the list.
The router must dispose of a USB slot. If it doesn't have a USB slot get a decent router which has a USB slot.
Then follow the instructions on dd-wrt.com to get the DD-WRT firmware running on your router.
When DD-WRT is up and running ensure that an OpenVPN client is available (_Services > VPN_) and supports User Pass Authentication.
If there is no User Pass Authentication in the OpenVPN client get a different version of DD-WRT for your router.
You will also need a USB memory stick. Get the cheapest you can find at your local supplier, that will do. Size doesn't matter ;-)
Last but not least you need a user account at [NordVPN](https://nordvpn.com) composed of username and password.

### Router behind router setup

If you would like to connect your DD-WRT router to another router which is functioning as main router to the internet this step might be of interest for you. If your DD-WRT router is connected to the internet directly you can skip this step.
Connect your DD-WRT router with an ordinary (preferably short) patch cable from its WAN port to one of the free LAN ports of your main router.

Now navigate in the DD-WRT menu to Setup > Basic Setup. Make sure "Gateway" and "Local DNS" are set to 0.0.0.0 and Static DNS 1 is set to the DD-WRT router's intranet IP. Navigate to Setup > Advanced Routing and set the following options:

- Operating Mode: Gateway
- Dynamic Routing Interface: Disable
- Route Name: _preferebly the name of your "outer" router_
- Metric: 0
- Destination LAN NET: _IP of "outer" router_
- Subnet Mask: _netmask of "outer" intranet_
- Gateway: _IP of "outer" router_
- Interface: LAN & WAN

### Configuring DD-WRT to use our scripts

Put the USB memory stick into the slot. In the DD-WRT menu navigate to _Administration > Diagnosis_.
Enter the following into the "commands" textbox:

```
sleep 5 && mount -o bin /dev/sda1 /jffs
sleep 30 && /jffs/usr/bin/speedcheck
```

Save this as **Startup** with the button below.

This will mount your USB stick when the router boots up to /jffs.

Now enter the following into the textbox:

```
iptables -I FORWARD -i br0 -o tun0 -j ACCEPT
iptables -I FORWARD -i tun0 -o br0 -j ACCEPT
iptables -I FORWARD -i br0 -o $(nvram get wan_iface) -j DROP
iptables -I INPUT -i tun0 -j REJECT
iptables -t nat -A POSTROUTING -o tun0 -j MASQUERADE
```

Save this as **Firewall** with the button below.

This is a [kill switch](https://en.wikipedia.org/wiki/Internet_kill_switch). I highly recommend doing this.
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
```

These jobs regularly check your internet connection and take on measurements if necessary (changing the VPN server or rebooting).

Now reboot the router.

When the router is back up use a tool like [WinSCP](https://winscp.net) to upload [the scripts](https://github.com/TobseTobse/DD-WRT_NordVPN/archive/master.zip) to /jffs.
Use a tool like [PuTTY](http://www.putty.org) and connect to your router.
Now let's make the scripts executable:

```
cd /jffs/usr/bin
chmod ugo+x *
```

Now let's define an initial VPN server to connect to. In the DD-WRT menu go to _Services > VPN_.
Pick one of the server configs you would like to connect to per default after the router has booted.
You find these files in the serverconfigs directory.
In the **OpenVPN Client** section fill the fields as follows:

- Start OpenVPN Client: Enable
- Server IP/Name: _get the ip address from the "remote" line in openvpn.conf_
- Port: 1194
- Tunnel Device: TUN
- Tunnel Protocol: UDP
- Encryption Cipher: AES-256 CBC
- Hash Algorithm: SHA1
- **User Pass Authentication: Enable**
- Username: _Your NordVPN username_
- Password: _Your NordVPN password_
- Advanced Options: Enable
- TLS Ciper: None
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

For all other fields not mentioned above: _leave empty or unchanged_

Save and reboot your router.
You should be good to go now.

### Check your external IP address

First of all we would like to know whether we are using NordVPN or not right now.
Connect your device with the DD-WRT router and disable all other connections from your device to any networks.
Now check your IP at https://ipinfo.io. Does this look any familiar? No? Good, then you are connected via a NordVPN exit node ;-)

### Tweaking the script configurations

Usually, the scripts should work without any intervention from your side. The core of the scripts is in /jffs/usr/bin.
You can tweak these scripts if you urgently feel the need to do so. In all other cases I recommend to refrain from doing that.
When you edit the scripts you will be able to change a few values in their respective head sections.
Please do not edit below the "configuration end" line.

### Usage of the scripts

If you connect to your router via SSH or Telnet you can call the scripts like that:

`config`

Don't touch this script. It will be overwritten with the next repository checkout. If you need a custom configuration make a copy of this file and name the copy "myconfig".

```
cd /jffs/usr/bin
cp config myconfig
```

Then modify the myconfig file. It will override the config file automatically.

`checkcon`

This script checks if you can ping the host specified in the script configuration.
By default wikipedia.org is pinged 20 times. If there are not enough pongs coming back this script will change the VPN server.

`speedcheck`

This script first invokes the _checkcon_ script. If the connection is okay the script proceeds to the following step:
It downloads a 10 MB test file from a server which is in the same country as the VPN server.
If the connection speed is below the predefined threshold the script will change the VPN server.
You can call the script with a parameter: `speedcheck checkonly` doesn't change the VPN server if the measured bandwidth is too low.

`vpn {server shortcut}`

This script switches the VPN server to one of the servers in the serverconfigs directory (e.g. `vpn ca0006tcp` or `vpn nl0053udp`).
When you call the script with `vpn rnd` it will switch to a randomly selected VPN server from the list in the serverconfigs directory.

### Updating the server configuration files

It may happen that one day NordVPN will change (add, remove, modify) servers. Unfortunately, there is no easy way to handle this yet.
If you want to convert the OpenVPN files yourself you can download the ".upd1194.ovpn" files from https://nordvpn.com/ovpn and parse them with [PHP](https://secure.php.net). If you want to run PHP on your Windows machine you can use a framework like [XAMPP](https://www.apachefriends.org) or give it to someone who has a webserver running PHP. Put the ".upd1194.ovpn" files together with *make_serverconfig.php* into a directory and run it with

`php make_serverconfig.php`

If this is too much hassle for you just check on [the DD-WRT NordVPN project site](https://tobsetobse.github.io/DD-WRT_NordVPN) occasionally. I will try to keep the server configuration files a bit up-to-date but I won't include servers from US, UK and DE. Deal with it 8-)

### WTF...? I found new files in the /jffs directory

Breathe. It's all good. The VPN scripts write the following files into /jffs:
- *servers.good.log*: a log with measured connection speeds of servers above the configured speed threshold
- *servers.bad.log*: a log with measured connection speeds of servers below the configured speed threshold
- *speedtestservers.xml*: this file contains servers via which the connection speed can be measured

### Troubleshooting

- Try removing the kill switch we have added as firewall script. Open a website with a browser being connected to the DD-WRT router. Do you get a result? Then your hardware setup and routing is fine. No result? Dang! Try a different version of DD-WRT. DD-WRT releases are pretty buggy sometimes.
- Assuming your hardware setup and routing are fine you can put back the kill switch now. Now navigate in the DD-WRT menu to Administration > Commands (Diagnostics) and enter the following command into the "Commands" box: `cat /tmp/openvpncl/openvpn.log` and click on the button "Run Commands" below. Does this help you any further? No? Then ask Google or a friend who knows a bit more about OpenVPN.


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
