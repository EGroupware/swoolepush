# Push server for EGroupware based on PHP Swoole extension

> This is work in progress, do NOT use on a production system!

## Open tasks:
- [ ] add IP check and/or other authentication to sending messages
- [ ] check sessionid cookie when client opens a websocket connection

## Installation instructions
```
cd /path/to/egroupware
git clone git@github.com:EGroupware/swoolpush.git
cd swoolpush
docker run --rm -it -v $(pwd):/var/www -p9501:9501 phpswoole/swoole
```
Then visit setup and install swoolpush app (no run rights for users neccessary).

You need to proxy the /push URL into the container, eg. for Apache
```
<Location /egroupware/push>
    Order allow,deny
    Allow from all

    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /opt/local/apache2/htdocs/(.*)           ws://localhost:9501/$1 [P,L]
    RewriteCond %{HTTP:Upgrade} !=websocket [NC]
    RewriteRule /opt/local/apache2/htdocs/(.*)           http://localhost:9501/$1 [P,L]

    ProxyPreserveHost On
    ProxyPassReverse http://localhost:9501
</Location>
```

## Send a test-message 
You can get a token from the server output, when a client connects.
```
curl -i -H 'Content-Type: application/json' -X POST 'https://boulder.egroupware.org/egroupware/push?token=<token>' \
  -d '{"type":"message","data":{"message":"Hi ;)","type":"notice"}}'
```

> Remember you need to restart the Docker container, when you make changes to the server!
