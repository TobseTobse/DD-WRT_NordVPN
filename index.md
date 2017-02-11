## DD-WRT NordVPN scripts

The purpose of this project is to enable a router liberated by DD-WRT to connect to the service [NordVPN](https://nordvpn.com).
All the clients in the network attached to the router will be able to use NordVPN via one login.
The author of this software is in no way associated with embeDD GmbH or the hosting company NordVPN.
Furthermore, the author of this software does not take on any responsibilities for damages resulting from this software or information.

### Prerequisites

To run the shell scripts you need a router capable of running DD-WRT. You can check your router
at http://dd-wrt.com/site/support/router-database. If it's not in the list get a decent router which is in the list.
The router must dispose of a USB slot. If it doesn't have a USB slot get a decent router which has a USB slot.
Then follow the instructions on dd-wrt.com to get the DD-WRT firmware running on your router.
When DD-WRT is up and running ensure that an OpenVPN client is available (_Services > VPN_) and supports User Pass Authentication.
If there is no User Pass Authentication in the OpenVPN client get a different version of DD-WRT for your router.
You will also need a USB memory stick. Get the cheapest you can find at your local supplier, that will do. Size doesn't matter ;-)
Last but not least you need a user account at [NordVPN](https://nordvpn.com) composed of username and password.

### Configuring DD-WRT

Put the USB memory stick into the slot. In the DD-WRT menu navigate to _Administration > Diagnosis_.
Enter the following into the "commands" textbox:

`sleep 5 && mount -o bin /dev/sda1 /jffs`

Save this as **Startup** with the button below.

This will mount your USB stick when the router boots up to /jffs.

Now enter the following into the textbox:

`iptables -I FORWARD -i br0 -o tun0 -j ACCEPT
iptables -I FORWARD -i tun0 -o br0 -j ACCEPT
iptables -I FORWARD -i br0 -o $(nvram get wan_iface) -j DROP
iptables -I INPUT -i tun0 -j REJECT
iptables -t nat -A POSTROUTING -o tun0 -j MASQUERADE`

Save this as **Firewall** with the button below.

This is a [kill switch](https://en.wikipedia.org/wiki/Internet_kill_switch). I highly recommend doing this.
With these rules you prevent the router from letting devices in the intranet access the internet if the VPN connection is down.

In the DD-WRT menu navigate to _Services_ and enable SSHd. Now reboot the router.

When the router is back up use a tool like [WinSCP](https://winscp.net) to upload the scripts to /jffs.
Use a tool like [PuTTY](http://www.putty.org) and connect to your router.
Now let's make the scripts executable:

`cd /jffs/usr/bin
chmod ugo+x *`

Now let's define an initial VPN server to connect to. In the DD-WRT menu go to _Services > VPN_.
Pick one of the server configs you would like to connect to per default after the router has booted.
You find these files in the serverconfigs/ directory.
In the **OpenVPN Client** section fill the fields as follows:

Start OpenVPN Client: Enable
Server IP/Name: _get the ip address from the "remote" line in openvpn.conf_
Port: 1194
Tunnel Device: TUN
Tunnel Protocol: UDP
Encryption Cipher: AES-256 CBC
Hash Algorithm: SHA1
**User Pass Authentication: Enable**
Username: _Your NordVPN username_
Password: _Your NordVPN password_
Advances Options: Enable
TLS Ciper: None
LZO Compression: Adaptive
NAT: _up to you_
Firewall Protection: Enable
IP Address: _leave empty_
Subnet Mask: _leave empty_
Tunnel MTU setting: 1500
Tunnel UDP Fragment: _leave empty_
Tunnel UDP MSS-Fix: Disable
nsCertType verification: nope
TLS Auth Key: _copy & paste the content of the ta.key file in the chosen serverconfig directory_
CA Cert: _copy & paste the content of the ca.crt file in the chosen serverconfig directory_

For all other fields not mentioned above: _leave empty or unchanged_

Save and reboot your router.
You should be good to go now.
