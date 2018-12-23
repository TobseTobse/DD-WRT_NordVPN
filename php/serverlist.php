<?php
# This script simply forwards the answer of the NordVPN API
# to the requesting client.
# It's basically an HTTPS to HTTP proxy for routers
# with limited wget functionality which doesn't support HTTPS

echo file_get_contents("https://api.nordvpn.com/server");