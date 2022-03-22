# Push server for EGroupware based on PHP Swoole extension

## Open tasks:
- [ ] check session matches HTTP host / instance AND session is not anonymous
- [ ] rotate token by async job and push new tokens to active sessions
- [x] require Bearer token to authorize requests / send push messages
- [x] check sessionid cookie when client opens a websocket connection

## Installation instructions
> Most easy installation is the one comming with the [container based development system](https://github.com/EGroupware/egroupware/tree/master/doc/docker/development).

To install EGroupwares push server for a regular webserver running on the host follow these instructions:
```
cd /path/to/egroupware
git clone git@github.com:EGroupware/swoolpush.git
cd swoolpush
docker run --rm -it -v $(pwd):/var/www -v /var/lib/php/sessions:/var/lib/php/sessions -p9501:9501 phpswoole/swoole
```
> You need to adapt the session-directory, if you are not using Ubuntu.

Then visit setup and install swoolpush app (no run rights for users neccessary).

You need to proxy the /push URL into the container, eg. for Apache
```
<Location /egroupware/push>
    Order allow,deny
    Allow from all

    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /var/www/(.*)           ws://localhost:9501/$1 [P,L]
    RewriteCond %{HTTP:Upgrade} !=websocket [NC]
    RewriteRule /var/www/(.*)           http://localhost:9501/$1 [P,L]

    ProxyPreserveHost On
    ProxyPassReverse http://localhost:9501
</Location>
```
> You need to change the above /var/www, in case you use a different document root.

eg. for Nginx
```
location  /egroupware/push {
                proxy_http_version 1.1;
                proxy_set_header Host $http_host;
                proxy_set_header Upgrade $http_upgrade;
                proxy_set_header Connection "Upgrade";
                proxy_pass http://localhost:9501;
        }
```

## Send a test-message
You can get a token from the server output, when a client connects.
```
curl -i -H 'Content-Type: application/json' -X POST 'https://boulder.egroupware.org/egroupware/push?token=<token>' \
  -d '{"type":"message","data":{"message":"Hi ;)","type":"notice"}}'
```

> Remember you need to restart the Docker container, when you make changes to the server!
