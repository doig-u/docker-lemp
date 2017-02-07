# Docker Nginx+PHP+MySQL Single Container App & Workflow Example

Warning: It's only an example for running several services in one container and for a small projects development workflow and deployment and migration. It's not а very good choice for production. Consider to use Docker Compose instead.

## Image overview

Based on Ubuntu LTS 16.04 https://hub.docker.com/_/ubuntu/

Services controlled by Supervisord: https://docs.docker.com/engine/admin/using_supervisord/

Almost all defaults for nginx & php-pfm on Ubuntu. Look to Dockerfile & configs for minor changes. MySQL (Percona) configured as here: https://hub.docker.com/_/percona/

Also, all logs are in VOLUME /var/log (/var/logs/nginx etc.) for exclusion it from committing Images and migrations. On a production server you must use a logrotate for these.

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

The example is in mine github for doigu/npm in folder ./your-project, clone it.

In your project dir are folders ./etc with your service configs, ./code with your app code, ./initdb with your db init sql script, ./mysql for MySQL db in /var/lib/mysql, ./log for services /var/log.

Dockerfile example:
```
FROM doigu/npm
COPY ./etc/nginx/default.conf /etc/nginx/conf.d/
COPY ./initdb/* /docker-entrypoint-initdb.d/
```

Build with version tag:
```
$ docker build . -t yourrepo/yournpm:1
```

First run:
```
$ docker run -d -p 80:80 -p 3306:3306 -v $(pwd)/code:/var/www/code -v $(pwd)/db:/var/lib/mysql -v $(pwd)/log:/var/log -
e MYSQL_ROOT_PASSWORD=rootpassword -e MYSQL_DATABASE=testdb -e MYSQL_USER=testuser -e MYSQL_PASSWORD=testpassword --nam
e yournpm yourrepo/yournpm:1
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
$ curl localhost
Pageview # 7
...
```

For ex, you change the code and ready for deploy container to production:
```
$ curl localhost
New Pageview # 8
```

You must create new container with other version tag, copy code and db inside, and commit it:
```
$ docker create --name yournpm-prod yourrepo/yournpm:1
$ docker cp ./code yournpm-prod:/var/www/
$ docker cp ./mysql yournpm-prod:/var/lib/
$ docker commit yournpm-prod yourrepo/yournpm:2
```

Now in `docker images` will be the new image with tag 2. Check it:
```
$ docker kill yournpm && docker rm -v yournpm
$ docker run -d -p 80:80 -p 3306:3306 --name yournpm yourrepo/yournpm:2
$ curl localhost
New Pageview # 9
```

For deployment on a production server you can push it in your private repo on `$ docker push yourrepo/yournpm:2` your dev-host and pull\run on the server: `docker run -d -p 80:80 -p 3306:3306 --name yournpm yourrepo/yournpm:2`.

You can use it to migrate from one server to another: `docker commit && docker push` it on the server1 (if code & db is in container after deployment) and `docker run` on the server2.

Without repo you can `docker export` container with code & db to a archive, `scp archive` to the server and `docker import` there.

## TODO:

- Add a logrotate for the logs.