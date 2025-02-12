# Messages fetching for Discord
This PHP script is aimed to fetch every message sent by a specific user on a Discord channel.\
You need to set the authorisation token within the script.

This script got a cooldown of 1.2s between each request.

## Pre-requiements
You need to get your authorisation token from Discord.\
To do so, open devtools, go to the Network tab, and look for a discord entry by searching "/messages".\
Take the "Authorization" entry from the request headers and paste it to the code.
