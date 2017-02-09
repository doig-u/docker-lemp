# Docker Nginx+PHP+MySQL Single Container App & Workflow Example

Warning: It's only an example for running several services in one container and for a small projects development workflow and deployment and migration. It's not а very good choice for production. Consider to use Docker Compose instead.

## Image overview

Based on Ubuntu LTS 16.04 https://hub.docker.com/_/ubuntu/

Services controlled by Supervisord: https://docs.docker.com/engine/admin/using_supervisord/

Almost all defaults for nginx & php-pfm on Ubuntu. Look to Dockerfile & configs for minor changes. MySQL (Percona) configured as here: https://hub.docker.com/_/percona/

All logs are in VOLUME /var/log (/var/logs/nginx etc.) for exclusion it from committing Images and migrations. On a production server you must use a logrotate for these.

Put (mount or copy) your code in /var/www/code.

For initialize empty DB (a first launch) put in the ./initdb folder your mysqldump.sql and use the option -v ./initdb:/docker-entrypoint-initdb.d - the script docker-entrypoint.sh will execute any *.sh & *.sql from there if no database in the /var/lib/mysql yet. MySQL Environment Variables for docker run are here: https://hub.docker.com/_/percona/

Exposed ports are http 80, https 443, mysql 3306. On Windows (or OsX) you can add to your docker default VirtualBox machine these ports forwarding for easy access.

Test:
```
$ docker run -d -p 80:80 -p 3306:3306 -e MYSQL_ROOT_PASSWORD=rootpassword --name npm doigu/npm
$ docker exec -it npm bash

# tail /var/log/supervisor/supervisord.log
2017-02-07 17:03:27,667 WARN Included extra file "/etc/supervisor/conf.d/supervisord.conf" during parsing
2017-02-07 17:03:27,683 INFO RPC interface 'supervisor' initialized
2017-02-07 17:03:27,683 CRIT Server 'unix_http_server' running without any HTTP authentication checking
2017-02-07 17:03:27,683 INFO supervisord started with pid 1
2017-02-07 17:03:28,689 INFO spawned: 'nginx' with pid 7
2017-02-07 17:03:28,720 INFO spawned: 'php-fpm' with pid 8
2017-02-07 17:03:28,723 INFO spawned: 'mysql' with pid 9
2017-02-07 17:03:29,942 INFO success: nginx entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2017-02-07 17:03:29,942 INFO success: php-fpm entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2017-02-07 17:03:29,942 INFO success: mysql entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)

# exit

$ curl localhost
<!DOCTYPE html>
<html>
<head>
<title>Welcome to nginx!</title>
...

$ mysql -uroot -prootpassword -h127.0.0.1
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 3
Server version: 5.7.17-11 Percona Server (GPL), Release '11', Revision 'f60191c'

Copyright (c) 2000, 2016, Oracle and/or its affiliates. All rights reserved.

Oracle is a registered trademark of Oracle Corporation and/or its
affiliates. Other names may be trademarks of their respective
owners.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql> quit;
Bye
```


After all, kill & remove container and volumes:
```
$ docker kill npm && docker rm -v npm
```

## Example Workflow

The example is in mine github for doigu/npm in folder ./example, clone it.

In your project dir are folders ./etc with your service configs, ./code with your app code, ./initdb with your db init sql script, ./mysql for MySQL db in /var/lib/mysql, ./log for services /var/log.

Dockerfile example:
```
FROM doigu/npm
COPY ./etc/nginx/default.conf /etc/nginx/conf.d/
COPY ./initdb/* /docker-entrypoint-initdb.d/
```


Build with "dev" version tag:
```
$ docker build . -t example:dev
```


First run:
```
$ docker run -d -p 80:80 -p 3306:3306 -v $(pwd)/code:/var/www/code -e MYSQL_ROOT_PASSWORD=rootpassword -e MYSQL_DATABASE=testdb -e MYSQL_USER=testuser -e MYSQL_PASSWORD=testpassword --name example example:dev
```


After the first run the MySQL server in the container will be initialized during some time (several seconds) with the testdb.sql dump from ./initdb.

Checks:
```
$ curl localhost
Connection failed: No such file or directory
$ curl localhost
Connection failed: Access denied for user 'testuser'@'localhost' (using password: YES)
...
$ curl localhost
Pageview # 5
$ curl localhost
Pageview # 6
...
```


For ex, you change the code and ready to deploy container to production:
```
$ curl localhost
New Pageview # 8
```


Make other Dockerfile for the production, with copying the code in a production build:
```
FROM doigu/npm
COPY ./etc/nginx/default.conf /etc/nginx/conf.d/
COPY ./initdb/* /docker-entrypoint-initdb.d/
COPY ./code /var/www/code/
```


When make the build:
```
$ docker build . -f Dockerfile-production -t yourrepo/example:1
```


Another way (method 2), you can create a new container, copy the code in, and commit it:
```
$ docker create --name example-prod example:dev
$ docker cp ./code example-prod:/var/www/
$ docker commit example-prod yourrepo/example:1
```


The `docker images` command will show the new image with production tag version 1. Check it:
```
$ docker run -d -p 80:80 -p 3306:3306 -e MYSQL_ROOT_PASSWORD=rootpassword -e MYSQL_DATABASE=testdb -e MYSQL_USER=testuser -e MYSQL_PASSWORD=testpassword --name example-prod-test yourrepo/example:1
...(wait for init db)...
$ curl localhost
New Pageview # 5
...
$ docker kill example-prod-test && docker rm -v example-prod-test
```


For deployment on a production server you can push it in your private repo `$ docker push yourrepo/example:1` on yours dev-host and pull\run on the server: `docker run -d -p 80:80 -p 3306:3306 -v /db/example:/var/lib/mysql --name example yourrepo/example:1`. If your production DB is already initialized in the server folder /db/example, you don't need the env variables `-e MYSQL_ROOT_PASSWORD=rootpassword -e MYSQL_DATABASE=testdb -e MYSQL_USER=testuser -e MYSQL_PASSWORD=testpassword` in the run. After you make a new version on your dev-host, you can `docker pull` it to the server, stop an old container, run the new, test, remove the old.

For a migration from one server to another you only need to make copy the DB from /db/example to the server 2, and make there `docker run`.

Without repo you can `docker export` container with code to a archive, `scp archive` to the server and `docker import` there.

And you can use it as virtual server with migrations capabilities through `docker commit\push\pull`.


## TODO:

- Fix logs.
- Add a logrotate for the logs.
