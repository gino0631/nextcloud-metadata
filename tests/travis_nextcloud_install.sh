#!/bin/bash

# Try to use automatic error detection
set -e

# Get script arguments
WORKDIR=$PWD
APP_NAME=$1
CORE_BRANCH=$2
DB=$3
echo "Work directory: $WORKDIR"
echo "Database: $DB"

# Clone Nextcloud and copy the app
cd ..
git clone --recursive --depth 1 -b $CORE_BRANCH https://github.com/nextcloud/server
cd server
cp -R $WORKDIR apps/$APP_NAME

# Configure DB
case $DB in
  pgsql)
    psql -c "CREATE USER nc_autotest WITH LOGIN SUPERUSER PASSWORD 'nc_autotest'"
    ;;

  *)
    echo "Unsupported database $DB" >&2
    exit 1
    ;;
esac

# Install Nextcloud
php -f occ maintenance:install --database $DB --database-name nc_autotest --database-user nc_autotest --database-pass nc_autotest --admin-user admin --admin-pass admin
./occ app:enable files_external

# Show status and enable the app
./occ check
./occ status
./occ app:enable $APP_NAME
./occ app:list
