<?php
# This is an HTTPS to HTTP proxy for routers
# with limited wget functionality which doesn't support HTTPS

echo file_get_contents($_GET["url"]);