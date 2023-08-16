# sqldump

A small tool for assisting in administration of databases. My first 48 hours in Golang.

Unfortunately I do not have that much time, so it's just close to the backend with some basic html. 
Use your fantasy for best UX and choose high levels of abstraction and imagination for fancy output with latest js-technology. 

## prepare

    sudo mysqladmin --defaults-file=/etc/mysql/debian.cnf create gotestdb
    sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "GRANT ALL PRIVILEGES  ON gotestdb.*  TO 'go_user'@'localhost' IDENTIFIED BY 'mypassword'  WITH GRANT OPTION;"
    mysql -p"mypassword" -u go_user gotestdb -e 'create table posts (title varchar(64) default null, start date default null);'
    mysql -p"mypassword" -u go_user gotestdb -e 'insert into posts values("hello","2015-01-01");'
    mysql -p"mypassword" -u go_user gotestdb -e 'insert into posts values("more","2015-01-03");'
    mysql -p"mypassword" -u go_user gotestdb -e 'insert into posts values("end","2015-01-23");'
    mysql -p"mypassword" -u go_user gotestdb -B -e 'select * from posts;'

## install

    export GOPATH=$PWD
    git clone https://github.com/gophergala/sqldump .
    go get github.com/go-sql-driver/mysql
    go get github.com/gorilla/securecookie

## run

    go run sqldump.go auth.go dump.go aux.go

## usage

[http://localhost:8080](http://localhost:8080)

## caveats

- A database named 'favicon.ico' can't be accessed
- restriction on names of databases, tables, columns 
- basic protection against sql injection via URI

## perspectives

. output in tables
- choice for different database drivers
- insert and edit records


