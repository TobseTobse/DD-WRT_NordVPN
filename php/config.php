<?php

// script may run maximum this amount of seconds, then die
$maxsecs = 3600;

// ignore the following top level country domains, separated by comma
$ignore = "at,ar,au,bh,br,ca,cl,cr,de,fr,hk,id,in,ir,jp,kr,mx,my,nz,pl,sg,sy,th,tr,tw,uk,us,vn,zw";

// maximum servers per one country
// (to keep list small enough to remain performant)
$maxPerCountry = 333;

// maximum servers in total
$maxServers = 3333;

// randomly download either TCP or UPD configuration for a server
// can be "tcp" or "udp" for only tcp or only udp,
// "all" for all protocols or "one" for one protocol per server
$protocol = "both";